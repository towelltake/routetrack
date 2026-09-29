"""Upfront salesman assignment — runs BEFORE the per-week VRP (Phase 7a).

Decides which salesman "owns" each customer for the whole month. Output is the
algorithm's view; the user can override per-row via the Assignments screen,
which writes to `customers.pinned_salesman_id` (the existing manual-pin column).

Algorithm: constrained k-medoids on the OSRM travel-time matrix, solved with
OR-Tools CP-SAT.

- Decision vars: x[c, s] ∈ {0,1} for every (customer c, eligible salesman s).
- Eligibility: salesman.assigned_areas empty (catch-all) OR customer.area is in
  the salesman's area list. Mirrors the rule documented in vrp.py.
- Pin hard constraint: a pinned customer has exactly one x var, forced to 1.
- Coverage: sum_s x[c, s] = 1 for every customer with at least one eligible
  salesman. **No drop variable** (Phase 11, 2026-05-22): the user requires 100%
  stickiness, so every eligible customer MUST be assigned to one salesman.
  Customers with no eligible salesman at all are filtered upstream by
  `_classify_unassignable` and surface as `UnassignableCustomer(reason=...)`.
- Workload balance (soft): slack[s] >= |load[s] - target_load|. The lever that
  spreads overflow evenly across eligible salesmen — with no drops to relieve
  capacity, balance + travel are what differentiate one assignment from another.
- Capacity (soft hard cap): load[s] <= capacity[s] + cap_slack[s]. cap_slack is
  penalised heavily in the objective so it stays at 0 whenever a clean fit
  exists; absorbs overflow without going infeasible when the team is
  under-staffed relative to demand.
- Objective: 100·travel + λ·balance_slack + CAP_PENALTY·cap_slack.

Medoid iteration (max 3 passes):
- Iter 0: medoid = the customer in s's eligible set nearest by haversine to
  s.start_location. We use haversine here only because the matrix is
  customer-to-customer; salesman start-locations aren't in it.
- Iter 1+: medoid = the customer in s's CURRENT assigned set with minimum total
  matrix-driven travel time to peers (the true OSRM-medoid).
- Stop when no customer changes salesman across iterations, or at iteration 3.
"""

from __future__ import annotations

import math
import time
from typing import Any

from ortools.sat.python import cp_model

from sidecar.models import (
    AssignSalesmenRequest,
    AssignSalesmenResponse,
    CustomerAssignment,
    MatrixCell,
    OptimizeCustomer,
    OptimizeSalesman,
    SolverStatus,
    UnassignableCustomer,
)

_MAX_ITERATIONS = 3
_SOLVE_TIME_LIMIT_S = 10.0

# Capacity-aware assignment (added with sticky-priority work).
# Drive overhead: matches team_sizing.py's 1.3x; usable facetime ≈ gross / 1.3.
_DRIVE_OVERHEAD_FACTOR = 1.3
# Per-minute cost of capacity overflow. Still meaningful in Phase 11 even
# though dropping is no longer an alternative: when one eligible salesman has
# spare capacity and another doesn't, this penalty steers the customer toward
# the salesman with room. Only when EVERY eligible salesman is full does it
# fire across the board, at which point the balance-slack term drives even
# distribution.
_CAP_PENALTY_PER_MIN = 100_000

# Co-assignment micro-clusters (2026-06-10): customers within this haversine
# radius of each other are practically the same stop — splitting them across
# two salesmen guarantees two reps drive to the same block forever. Audit of
# plan #7 found 89 such pairs split across salesmen. Merges are taken
# nearest-pair-first; the DIAMETER cap is what stops single-linkage chaining
# from welding a whole 4-km shop street into one atomic block (a chain of
# 111-m hops is NOT one stop) — a block stays mergeable only while every
# member is within _COASSIGN_MAX_DIAMETER_KM of every other. The size cap is
# a second backstop for pathologically dense stacks.
_COASSIGN_RADIUS_KM = 0.25
_COASSIGN_MAX_DIAMETER_KM = 0.5
_COASSIGN_MAX_GROUP = 24


def _parse_hhmm(s: str) -> int:
    h, m = s.split(":")
    return int(h) * 60 + int(m)


def _capacity_minutes(s: OptimizeSalesman) -> int:
    """Per-month usable facetime capacity for one salesman.

    `working_days_per_week * 4 weeks * daily_minutes / drive_overhead`.
    Matches the rough envelope `team_sizing.py` uses for its capacity check.
    A salesman with no working_days or zero-length shift returns 0; assignment
    treats that as "cannot fit anyone here" which forces customers elsewhere.
    """
    daily = max(0, _parse_hhmm(s.working_hours_end) - _parse_hhmm(s.working_hours_start))
    return int(len(s.working_days) * 4 * daily / _DRIVE_OVERHEAD_FACTOR)


def _haversine_km(lat1: float, lng1: float, lat2: float, lng2: float) -> float:
    r = 6371.0
    p1 = math.radians(lat1)
    p2 = math.radians(lat2)
    d_lat = p2 - p1
    d_lng = math.radians(lng2 - lng1)
    x = math.sin(d_lat / 2) ** 2 + math.sin(d_lng / 2) ** 2 * math.cos(p1) * math.cos(p2)
    return 2 * r * math.asin(math.sqrt(x))


def _matrix_to_dict(cells: list[MatrixCell]) -> dict[tuple[int, int], int]:
    """Build a (customer_id_a, customer_id_b) → seconds lookup.

    Matches the convention in vrp.py: origin_index/dest_index are customer IDs.
    Cells with status != 'ok' or null duration are skipped — the caller falls
    back to haversine.
    """
    out: dict[tuple[int, int], int] = {}
    for c in cells:
        if c.status == "ok" and c.duration_seconds is not None:
            out[(c.origin_index, c.dest_index)] = c.duration_seconds
    return out


def _seconds_between(
    matrix: dict[tuple[int, int], int],
    a: OptimizeCustomer,
    b: OptimizeCustomer,
) -> int:
    """Travel time a → b in seconds. Matrix-first, haversine fallback at 40 km/h
    to match vrp.py's behavior on missing cells."""
    sec = matrix.get((a.id, b.id))
    if sec is not None:
        return sec
    km = _haversine_km(a.lat, a.lng, b.lat, b.lng)
    # 40 km/h average — same fallback speed vrp.py uses.
    return max(1, round(km / 40.0 * 3600))


def _eligible_for(salesman: OptimizeSalesman, customer: OptimizeCustomer) -> bool:
    """Mirror of vrp.py's eligibility rule (sans pin — pin handling is at the
    caller in `_classify_unassignable`).

    Area / region: satisfied if salesman has no area-or-region constraint at
    all, OR customer carries neither tag, OR at least one of (customer.area,
    customer.region) lies in the salesman's matching list.

    Channel: independent AND. Empty channel_skills = no restriction.
    """
    has_area_constraint = bool(salesman.assigned_areas)
    has_region_constraint = bool(salesman.assigned_regions)
    if has_area_constraint or has_region_constraint:
        c_area = customer.area if customer.area else None
        c_region = customer.region if customer.region else None
        if c_area is not None or c_region is not None:
            area_match = (
                has_area_constraint
                and c_area is not None
                and c_area in salesman.assigned_areas
            )
            region_match = (
                has_region_constraint
                and c_region is not None
                and c_region in salesman.assigned_regions
            )
            if not (area_match or region_match):
                return False
    if (
        salesman.channel_skills
        and customer.channel is not None
        and customer.channel != ""
        and customer.channel not in salesman.channel_skills
    ):
        return False
    return True


def _pick_seed_medoid(
    salesman: OptimizeSalesman,
    eligible_customers: list[OptimizeCustomer],
) -> OptimizeCustomer | None:
    """Iter-0 medoid: the eligible customer closest (haversine) to the salesman's
    start_location. Returns None when the salesman has no eligible customers.

    When the salesman has no `start_location_lat/lng`, falls back to the customer
    nearest to the eligible-set's haversine centroid (deterministic, no random
    bias).
    """
    if not eligible_customers:
        return None
    if salesman.start_location_lat is not None and salesman.start_location_lng is not None:
        anchor_lat = salesman.start_location_lat
        anchor_lng = salesman.start_location_lng
    else:
        anchor_lat = sum(c.lat for c in eligible_customers) / len(eligible_customers)
        anchor_lng = sum(c.lng for c in eligible_customers) / len(eligible_customers)
    return min(
        eligible_customers,
        key=lambda c: _haversine_km(anchor_lat, anchor_lng, c.lat, c.lng),
    )


def _recompute_medoid(
    matrix: dict[tuple[int, int], int],
    assigned: list[OptimizeCustomer],
) -> OptimizeCustomer | None:
    """Iter-1+ medoid: the customer in `assigned` that minimises total OSRM
    travel time to its peers. Returns None for empty sets."""
    if not assigned:
        return None
    best: OptimizeCustomer | None = None
    best_total = -1
    for candidate in assigned:
        total = 0
        for peer in assigned:
            if peer.id == candidate.id:
                continue
            total += _seconds_between(matrix, candidate, peer)
        if best is None or total < best_total:
            best = candidate
            best_total = total
    return best


def _classify_unassignable(
    customers: list[OptimizeCustomer], salesmen: list[OptimizeSalesman]
) -> tuple[list[OptimizeCustomer], list[UnassignableCustomer]]:
    """Split customers into solvable (have ≥1 eligible salesman) and unassignable."""
    solvable: list[OptimizeCustomer] = []
    unassignable: list[UnassignableCustomer] = []
    for c in customers:
        if c.pinned_salesman_id is not None:
            # Pin always wins, even if the pinned salesman doesn't cover the
            # customer's area. The user's pin is authoritative.
            if any(s.id == c.pinned_salesman_id for s in salesmen):
                solvable.append(c)
                continue
            unassignable.append(
                UnassignableCustomer(
                    customer_id=c.id,
                    reason="no_eligible_salesman",
                )
            )
            continue
        eligible = [s for s in salesmen if _eligible_for(s, c)]
        if eligible:
            solvable.append(c)
        else:
            unassignable.append(
                UnassignableCustomer(
                    customer_id=c.id,
                    reason="no_eligible_salesman",
                )
            )
    return solvable, unassignable


def _coassign_groups(
    customers: list[OptimizeCustomer], salesmen: list[OptimizeSalesman]
) -> list[list[int]]:
    """Micro-clusters of customer ids that must share one salesman.

    Union-find over unpinned customer pairs that are within
    _COASSIGN_RADIUS_KM of each other AND have an identical eligibility
    fingerprint (same set of eligible salesmen) — the fingerprint guard keeps
    the equality constraints satisfiable (tying an MT-only customer to a
    TT-only neighbour would be infeasible, and tying across pins would fight
    the user). Pairs merge nearest-first; a merge is skipped when the merged
    block would exceed _COASSIGN_MAX_DIAMETER_KM corner-to-corner (chaining
    guard) or _COASSIGN_MAX_GROUP members (density backstop).
    """
    unpinned = [c for c in customers if c.pinned_salesman_id is None]
    by_id = {c.id: c for c in unpinned}
    fingerprint = {
        c.id: frozenset(s.id for s in salesmen if _eligible_for(s, c))
        for c in unpinned
    }
    by_fp: dict[frozenset[int], list[OptimizeCustomer]] = {}
    for c in unpinned:
        by_fp.setdefault(fingerprint[c.id], []).append(c)

    pairs: list[tuple[float, int, int]] = []
    for group in by_fp.values():
        for i in range(len(group)):
            for j in range(i + 1, len(group)):
                a, b = group[i], group[j]
                km = _haversine_km(a.lat, a.lng, b.lat, b.lng)
                if km <= _COASSIGN_RADIUS_KM:
                    pairs.append((km, a.id, b.id))
    pairs.sort()

    parent = {c.id: c.id for c in unpinned}
    members: dict[int, list[int]] = {c.id: [c.id] for c in unpinned}

    def find(cid: int) -> int:
        while parent[cid] != cid:
            parent[cid] = parent[parent[cid]]
            cid = parent[cid]
        return cid

    def merged_diameter_ok(ra: int, rb: int) -> bool:
        for a_id in members[ra]:
            a = by_id[a_id]
            for b_id in members[rb]:
                b = by_id[b_id]
                if _haversine_km(a.lat, a.lng, b.lat, b.lng) > _COASSIGN_MAX_DIAMETER_KM:
                    return False
        return True

    for _, a_id, b_id in pairs:
        ra, rb = find(a_id), find(b_id)
        if ra == rb:
            continue
        if len(members[ra]) + len(members[rb]) > _COASSIGN_MAX_GROUP:
            continue
        if not merged_diameter_ok(ra, rb):
            continue
        parent[ra] = rb
        members[rb].extend(members[ra])
        del members[ra]

    return [sorted(g) for g in members.values() if len(g) > 1]


def _solve_one_pass(
    customers: list[OptimizeCustomer],
    salesmen: list[OptimizeSalesman],
    medoids: dict[int, OptimizeCustomer],
    matrix: dict[tuple[int, int], int],
    balance_lambda: float,
    coassign_groups: list[list[int]],
) -> tuple[dict[int, int], SolverStatus]:
    """One CP-SAT solve. Returns (customer_id → salesman_id, status).

    Salesmen without a medoid (no eligible customers this iteration) are dropped
    from the model — their slot stays empty and customers route to other
    eligible salesmen. This can happen if every customer in a salesman's area
    is pinned to a different salesman.

    Every solvable customer is assigned (Phase 11 — no drop var). Capacity
    overflow is absorbed by `cap_slack[s]` and reflected in the per-salesman
    load returned by the caller; downstream the VRP routes the overflow past
    working hours and the renderer's red-chip code flags it.
    """
    active_salesmen = [s for s in salesmen if s.id in medoids]
    if not active_salesmen:
        return {}, "infeasible"

    model = cp_model.CpModel()

    # x[(c.id, s.id)] = 1 iff customer c is assigned to salesman s.
    # IntVar is import-untyped from CP-SAT; using Any keeps mypy happy.
    x: dict[tuple[int, int], Any] = {}
    eligible_per_customer: dict[int, list[int]] = {}
    for c in customers:
        if c.pinned_salesman_id is not None:
            # Pinned: only one variable, forced to 1.
            pinned_id = c.pinned_salesman_id
            var = model.new_int_var(1, 1, f"x_{c.id}_{pinned_id}")
            x[(c.id, pinned_id)] = var
            eligible_per_customer[c.id] = [pinned_id]
            continue
        cands = [s for s in active_salesmen if _eligible_for(s, c)]
        if not cands:
            # Shouldn't happen — _classify_unassignable already filtered these out.
            # But guard the loop anyway to keep CP-SAT well-formed.
            return {}, "infeasible"
        eligible_per_customer[c.id] = [s.id for s in cands]
        for s in cands:
            x[(c.id, s.id)] = model.new_bool_var(f"x_{c.id}_{s.id}")
        # Phase 11: every customer must be assigned — no drop var.
        model.add(sum(x[(c.id, s.id)] for s in cands) == 1)

    # Co-assignment: members of a micro-cluster share one salesman. Members
    # have identical eligibility fingerprints by construction, so tying their
    # vars pairwise to the first member fully links the group without ever
    # referencing a variable that doesn't exist.
    for group in coassign_groups:
        first = group[0]
        for other in group[1:]:
            for s_id in eligible_per_customer.get(first, []):
                a_var = x.get((first, s_id))
                b_var = x.get((other, s_id))
                if a_var is not None and b_var is not None:
                    model.add(a_var == b_var)

    # Per-salesman load = sum of (facetime * frequency) over assigned customers.
    # Total load and target.
    total_load = sum(c.facetime_minutes * max(c.monthly_frequency, 1) for c in customers)
    target_load = total_load // max(len(active_salesmen), 1)

    load_vars: dict[int, Any] = {}
    slack_vars: dict[int, Any] = {}
    cap_slack_vars: dict[int, Any] = {}
    for s in active_salesmen:
        load_expr = sum(
            x[(c.id, s.id)] * (c.facetime_minutes * max(c.monthly_frequency, 1))
            for c in customers
            if (c.id, s.id) in x
        )
        load_var = model.new_int_var(0, total_load, f"load_{s.id}")
        # cp_model.LinearExpr handles the int 0 case where load_expr is `0`.
        model.add(load_var == load_expr)
        load_vars[s.id] = load_var

        slack = model.new_int_var(0, total_load, f"slack_{s.id}")
        # slack >= load - target AND slack >= target - load -> slack >= |load - target|.
        model.add(slack >= load_var - target_load)
        model.add(slack >= target_load - load_var)
        slack_vars[s.id] = slack

        # Capacity overflow slack: load[s] <= capacity[s] + cap_slack[s]. cap_slack
        # acts as a soft cap penalised in the objective so overflow degrades
        # gracefully instead of going infeasible when total demand > total capacity.
        cap = _capacity_minutes(s)
        cap_slack = model.new_int_var(0, total_load, f"cap_slack_{s.id}")
        model.add(load_var <= cap + cap_slack)
        cap_slack_vars[s.id] = cap_slack

    # Travel cost: sum over assignments of seconds to the salesman's medoid.
    # CP-SAT linear expressions are import-untyped — use Any to stay quiet.
    travel_terms: list[Any] = []
    for c in customers:
        for s_id in eligible_per_customer.get(c.id, []):
            medoid = medoids[s_id]
            if medoid.id == c.id:
                # Customer IS the medoid — zero cost. Common case in tight clusters.
                continue
            seconds = _seconds_between(matrix, c, medoid)
            travel_terms.append(x[(c.id, s_id)] * seconds)

    # CP-SAT requires integer coefficients on objective terms; scale lambda by 100
    # to allow 0.01 resolution and round.
    lambda_scaled = max(0, round(balance_lambda * 100))
    travel_sum = sum(travel_terms) if travel_terms else 0
    slack_sum = sum(slack_vars.values()) if slack_vars else 0
    cap_slack_sum = sum(cap_slack_vars.values()) if cap_slack_vars else 0
    # 100 * travel keeps numerator/denominator at the same scale as lambda_scaled.
    model.minimize(
        100 * travel_sum
        + lambda_scaled * slack_sum
        + _CAP_PENALTY_PER_MIN * cap_slack_sum
    )

    solver = cp_model.CpSolver()
    solver.parameters.max_time_in_seconds = _SOLVE_TIME_LIMIT_S
    # Determinism: CP-SAT is deterministic by default with a fixed seed.
    solver.parameters.random_seed = 1
    # Use a single worker for full determinism. Many workers parallel-search and
    # can return different (but equally-optimal) solutions across runs.
    solver.parameters.num_search_workers = 1

    status = solver.Solve(model)

    if status == cp_model.OPTIMAL:
        result_status: SolverStatus = "success"
    elif status == cp_model.FEASIBLE:
        result_status = "partial"
    elif status == cp_model.INFEASIBLE:
        return {}, "infeasible"
    else:
        return {}, "error"

    assignments: dict[int, int] = {}
    for (c_id, s_id), var in x.items():
        if solver.Value(var) == 1:
            assignments[c_id] = s_id
    return assignments, result_status


def run_assign_salesmen(request: AssignSalesmenRequest) -> AssignSalesmenResponse:
    """Public entry point. Wired to `POST /assign-salesmen` in main.py."""
    started = time.perf_counter()

    customers = [c for c in request.customers if c.lat is not None and c.lng is not None]
    no_coords = [
        UnassignableCustomer(customer_id=c.id, reason="no_coordinates")
        for c in request.customers
        if c.lat is None or c.lng is None
    ]
    salesmen = request.salesmen
    matrix = _matrix_to_dict(request.matrix_cells)

    solvable, no_eligible = _classify_unassignable(customers, salesmen)
    unassignable = no_coords + no_eligible

    if not solvable or not salesmen:
        return AssignSalesmenResponse(
            assignments=[],
            unassignable=unassignable,
            per_salesman_load_minutes={},
            target_load_minutes=0,
            runtime_seconds=time.perf_counter() - started,
            solver_status="success" if not unassignable else "partial",
            medoid_iterations=0,
        )

    # Eligible-customer set per salesman (does not change across iterations
    # because eligibility depends only on area + pin, not on current assignment).
    eligible_per_salesman: dict[int, list[OptimizeCustomer]] = {}
    for s in salesmen:
        # Pinned customers count as eligible for their pinned salesman even if
        # the salesman's area list doesn't cover the customer's area — the pin
        # is authoritative. Without this, a salesman with only pinned (no area-
        # eligible) customers would get no medoid and drop out of the model,
        # leaving their pinned customers with no destination.
        eligible_per_salesman[s.id] = [
            c
            for c in solvable
            if _eligible_for(s, c) or c.pinned_salesman_id == s.id
        ]

    # Iter 0 seed.
    medoids: dict[int, OptimizeCustomer] = {}
    for s in salesmen:
        seed = _pick_seed_medoid(s, eligible_per_salesman[s.id])
        if seed is not None:
            medoids[s.id] = seed

    # Micro-cluster co-assignment groups are geometry+eligibility only, so
    # they're stable across medoid iterations — compute once.
    coassign_groups = _coassign_groups(solvable, salesmen)

    previous_assignments: dict[int, int] = {}
    last_status: SolverStatus = "error"
    iterations = 0
    for iteration in range(_MAX_ITERATIONS):
        iterations = iteration + 1
        assignments, status = _solve_one_pass(
            solvable, salesmen, medoids, matrix, request.balance_lambda, coassign_groups
        )
        last_status = status
        if status in ("infeasible", "error"):
            # Bail out: return what we have, mark status, let UI surface it.
            break
        if assignments == previous_assignments:
            break
        previous_assignments = assignments
        # Recompute medoids from this iteration's assignments.
        per_salesman: dict[int, list[OptimizeCustomer]] = {s.id: [] for s in salesmen}
        for c_id, s_id in assignments.items():
            cust = next(c for c in solvable if c.id == c_id)
            per_salesman[s_id].append(cust)
        new_medoids: dict[int, OptimizeCustomer] = {}
        for s in salesmen:
            assigned = per_salesman[s.id]
            recomputed = _recompute_medoid(matrix, assigned)
            if recomputed is not None:
                new_medoids[s.id] = recomputed
            elif s.id in medoids:
                # Keep last iteration's medoid if this salesman ended up empty.
                new_medoids[s.id] = medoids[s.id]
        medoids = new_medoids

    final_assignments = previous_assignments

    # Build response with per-salesman load.
    per_salesman_load: dict[int, int] = {s.id: 0 for s in salesmen}
    assignment_items: list[CustomerAssignment] = []
    for c in solvable:
        assigned_id = final_assignments.get(c.id)
        if assigned_id is None:
            # Should not happen with the no-drop model — every solvable customer
            # gets a 1 somewhere. If we hit this, treat it as a true ineligibility
            # surface so the user sees it in the UI rather than a silent loss.
            unassignable.append(
                UnassignableCustomer(customer_id=c.id, reason="no_eligible_salesman")
            )
            continue
        medoid = medoids.get(assigned_id)
        travel_s = _seconds_between(matrix, c, medoid) if (medoid and medoid.id != c.id) else 0
        per_salesman_load[assigned_id] += c.facetime_minutes * max(c.monthly_frequency, 1)
        assignment_items.append(
            CustomerAssignment(
                customer_id=c.id,
                salesman_id=assigned_id,
                travel_time_seconds=travel_s,
            )
        )

    total_load = sum(per_salesman_load.values())
    target = total_load // max(len([s for s in salesmen if s.id in medoids]), 1)

    return AssignSalesmenResponse(
        assignments=assignment_items,
        unassignable=unassignable,
        per_salesman_load_minutes=per_salesman_load,
        target_load_minutes=target,
        runtime_seconds=time.perf_counter() - started,
        solver_status=last_status,
        medoid_iterations=iterations,
    )


__all__ = ["run_assign_salesmen"]
