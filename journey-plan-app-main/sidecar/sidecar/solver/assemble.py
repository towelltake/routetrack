"""Top-level optimizer orchestration.

Stitches `cycle_assignment` + `vrp.solve_week` per calendar week into a flat
`OptimizeResponse`. The planner (main process) is responsible for pre-fetching
the matrix — this layer takes the matrix as input and stays pure.
"""

from __future__ import annotations

import time
from datetime import date

from sidecar.models import (
    MatrixCell,
    OptimizeCustomer,
    OptimizeRequest,
    OptimizeResponse,
    OptimizeVisit,
    SolverStatus,
    UnassignedCustomer,
)
from sidecar.solver.cycle_assignment import NUM_WEEKS, assign_weeks
from sidecar.solver.vrp import _our_dow, solve_week

# Default working week (Oman) used when a customer has no `allowed_days` set.
# Matches Sun..Thu. Kept here so the day-spread logic has a sensible fallback
# without dragging Settings into the solver.
_DEFAULT_WORKING_DAYS = [0, 1, 2, 3, 4]


def _matrix_to_dict(cells: list[MatrixCell]) -> dict[tuple[int, int], int]:
    """origin_index/dest_index in OptimizeRequest are CUSTOMER IDs (see plan).
    Bad cells (status != 'ok' or duration None) are silently dropped — the
    solver treats them as zero-distance which is overly optimistic but matches
    our v1 stance of trusting the matrix.
    """
    out: dict[tuple[int, int], int] = {}
    for c in cells:
        if c.status == "ok" and c.duration_seconds is not None:
            out[(c.origin_index, c.dest_index)] = c.duration_seconds
    return out


def _pick_spread_days(allowed: list[int], n: int) -> list[int]:
    """Pick `n` days from `allowed` with maximum spread (evenly distributed).

    For n >= len(allowed) returns all allowed days (capped to avoid duplicates;
    the caller should not request more visits than there are working days).
    For n=1 returns [allowed[0]]. For n>=2 spreads via index = round(i * (L-1) / (n-1))
    so e.g. 3 visits over [0..4] => [0, 2, 4].
    """
    a = sorted(set(allowed))
    if n <= 0:
        return []
    if n >= len(a):
        return a
    if n == 1:
        return [a[0]]
    return [a[round(i * (len(a) - 1) / (n - 1))] for i in range(n)]


def _spread_universe(
    cust: OptimizeCustomer,
    salesman_days: dict[int, list[int]],
    all_working_days: list[int],
) -> list[int]:
    """Days a multi-visit-per-week customer's clones may be spread over:
    the servicing salesman's actual working days (pinned salesman if set,
    else the union across the roster), intersected with the customer's own
    allowed_days. 2026-06-10 fix — this used to assume Sun-Thu, which made
    a Mon-Fri roster's spread clones land on days nobody works. Falls back
    progressively rather than returning an empty set (the VRP surfaces a
    genuinely unservable combination as unassigned with a reason)."""
    if cust.pinned_salesman_id is not None and cust.pinned_salesman_id in salesman_days:
        working = salesman_days[cust.pinned_salesman_id]
    else:
        working = all_working_days
    if not working:
        working = list(_DEFAULT_WORKING_DAYS)
    if cust.allowed_days:
        narrowed = sorted(set(cust.allowed_days) & set(working))
        return narrowed if narrowed else sorted(set(cust.allowed_days))
    return sorted(set(working))


def _expand_customers_for_week(
    customers_in_week_with_count: list[tuple[OptimizeCustomer, int]],
    salesman_days: dict[int, list[int]],
    all_working_days: list[int],
) -> list[OptimizeCustomer]:
    """For each (customer, visit_count) pair, return `visit_count` copies with
    `allowed_days` narrowed to a single spread day each (for count>1) — this
    pre-spaces multi-visit-per-week customers so the VRP never assigns them
    to consecutive days, and lets the per-day routing happen naturally."""
    expanded: list[OptimizeCustomer] = []
    for cust, count in customers_in_week_with_count:
        if count <= 1:
            expanded.append(cust)
            continue
        allowed = _spread_universe(cust, salesman_days, all_working_days)
        # Can't pre-assign more days than the customer is allowed to be visited.
        # If count exceeds the allowed-day count, the surplus visits get the
        # same days again (the solver will then drop them via the disjunction
        # since two nodes can't be on the same day-vehicle without doubling up).
        # In practice freq cap = 20 over a 5-day working week prevents this.
        days = _pick_spread_days(allowed, count)
        if len(days) < count:
            days = days + [days[-1]] * (count - len(days))
        for d in days:
            clone = cust.model_copy(update={"allowed_days": [d]})
            expanded.append(clone)
    return expanded


# Default 480 s/week (8 min/week → ~32 min total). See solve_week's comment.
# Tests + team_sizing pass explicit short caps where bounded runtime matters.
# 2026-05-24: bumped from 300 — paired with the day-balance calibration to
# give GLS more time to escape local optima where a single week has both
# overflow days and underutilized days.
def run_optimize(request: OptimizeRequest, time_limit_seconds: int = 480) -> OptimizeResponse:
    start_wall = time.monotonic()
    period_start = date.fromisoformat(request.period_start)
    matrix = _matrix_to_dict(request.matrix_cells)

    week_for_cust = assign_weeks(request.customers)
    cust_by_id: dict[int, OptimizeCustomer] = {c.id: c for c in request.customers}

    # Working-day context for multi-visit spreading (2026-06-10): pinned
    # customers spread over their salesman's actual week; unpinned ones over
    # the union of the roster's working days.
    salesman_days: dict[int, list[int]] = {
        s.id: list(s.working_days) for s in request.salesmen
    }
    all_working_days = sorted({d for s in request.salesmen for d in s.working_days})

    all_visits: list[OptimizeVisit] = []
    all_unassigned: list[UnassignedCustomer] = []
    unassigned_ids: set[int] = set()

    # Same-weekday anchoring (PJP call-day regularity, 2026-06-10): the first
    # week a customer is actually visited fixes their weekday(s); later weeks
    # narrow `allowed_days` to that anchor so the outlet sees the rep on the
    # same day every cycle. Weeks otherwise solve independently, so without
    # this the weekday was only consistent by accident of determinism — any
    # change in a week's customer mix could move a weekly customer to a
    # different day. The anchor is always a subset of the customer's own
    # allowed_days (it came from a vehicle-day that passed that check), and
    # the same weekday exists in every week, so anchoring never makes a
    # servable customer unservable. freq>4 customers get the full weekday SET
    # from their multi-visit week; their multi-visit weeks are already
    # pre-spread deterministically by _expand_customers_for_week.
    anchored_days: dict[int, list[int]] = {}

    for week_idx in range(NUM_WEEKS):
        # Count how many visits this customer has in this week (multiplicity).
        in_week_counts: list[tuple[OptimizeCustomer, int]] = []
        for cid, weeks in week_for_cust.items():
            count = weeks.count(week_idx)
            if count > 0:
                cust = cust_by_id[cid]
                anchor = anchored_days.get(cid)
                # Only narrow when the anchor can host this week's visit count;
                # a freq=5 customer anchored on a 1-visit week ([Sun]) must not
                # have a later 2-visit week squeezed onto a single day.
                if anchor is not None and count <= len(anchor):
                    cust = cust.model_copy(update={"allowed_days": anchor})
                in_week_counts.append((cust, count))
        in_week = _expand_customers_for_week(
            in_week_counts, salesman_days, all_working_days
        )

        visits, unassigned = solve_week(
            in_week,
            request.salesmen,
            matrix,
            period_start,
            week_idx,
            time_limit_seconds=time_limit_seconds,
        )
        all_visits.extend(visits)
        week_dows: dict[int, set[int]] = {}
        for v in visits:
            week_dows.setdefault(v.customer_id, set()).add(
                _our_dow(date.fromisoformat(v.scheduled_date))
            )
        for cid, dows in week_dows.items():
            anchored_days.setdefault(cid, sorted(dows))
        for u in unassigned:
            if u.customer_id not in unassigned_ids:
                all_unassigned.append(u)
                unassigned_ids.add(u.customer_id)

    runtime = time.monotonic() - start_wall

    # Objective = total drive minutes across all visits.
    objective = float(sum(v.drive_minutes_to for v in all_visits))

    status: SolverStatus
    if not all_visits and request.customers:
        status = "infeasible"
    elif all_unassigned:
        status = "partial"
    else:
        status = "success"

    return OptimizeResponse(
        visits=all_visits,
        unassigned_customers=all_unassigned,
        objective_value=objective,
        runtime_seconds=round(runtime, 3),
        solver_status=status,
    )
