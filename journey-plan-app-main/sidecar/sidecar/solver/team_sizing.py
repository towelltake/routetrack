"""'How many salesmen do I need?' — runs the optimizer once against a roster of
generic salesmen sized at the workload's arithmetic lower bound, then reports
how many of those vehicles the solver actually used, plus the customer cluster
and centroid each one ended up with. Existing pins/assignments are stripped for
the run: sizing asks how many reps the workload needs, which is upstream of who
owns whom.

Inputs: customer set + matrix + a single generic template (working days, hours,
optional max-customers-per-day). The team-sizing run does NOT save a plan — it
just returns the recommended team size, the per-virtual-salesman customer
clusters, and centroid coordinates the caller can use to seed real salesman
records.

When customers carry `area` tags, we size each area separately so the
recommendation respects territory boundaries. The total recommended size is the
sum across areas.
"""

from __future__ import annotations

import time
from collections import Counter, defaultdict
from datetime import date, timedelta
from math import ceil

from sidecar.models import (
    MatrixCell,
    OptimizeCustomer,
    OptimizeRequest,
    OptimizeSalesman,
    SolverStatus,
    SuggestedSalesman,
    SuggestTeamSizeRequest,
    SuggestTeamSizeResponse,
    UnassignedCustomer,
)
from sidecar.solver.assemble import run_optimize

VIRTUAL_ID_BASE = 900_000  # synthetic salesman IDs reserved for team-sizing runs
TIME_LIMIT_PER_RUN = 4


def _parse_hhmm(s: str) -> int:
    h, m = s.split(":")
    return int(h) * 60 + int(m)


def _working_days_per_month(working_dow: list[int]) -> int:
    # 4 calendar weeks x working days/week.
    return 4 * len(working_dow)


def _next_period_start() -> date:
    """Anchor the sizing run at the next Sunday — keeps day-of-week math stable
    and lets the run reuse the same VRP machinery as a real /optimize call."""
    today = date.today()
    # weekday(): Mon=0..Sun=6. We want next Sunday (or today if today is Sunday).
    days_ahead = (6 - today.weekday()) % 7
    return today + timedelta(days=days_ahead or 7)


def _lower_bound_count(
    customers: list[OptimizeCustomer], minutes_per_day: int, days_per_month: int
) -> int:
    if days_per_month == 0:
        return 0
    facetime_minutes = sum(c.facetime_minutes * max(c.monthly_frequency, 1) for c in customers)
    # Add 30% buffer for drive time — refined later when the actual VRP runs.
    estimated_total = facetime_minutes * 1.3
    capacity_per_salesman = minutes_per_day * days_per_month
    if capacity_per_salesman == 0:
        return 0
    return max(1, ceil(estimated_total / capacity_per_salesman))


def _make_virtual_salesman(
    idx: int, template_working_days: list[int], start: str, end: str
) -> OptimizeSalesman:
    return OptimizeSalesman(
        id=VIRTUAL_ID_BASE + idx,
        working_days=list(template_working_days),
        working_hours_start=start,
        working_hours_end=end,
        assigned_areas=[],
    )


def _centroid(coords: list[tuple[float, float]]) -> tuple[float, float]:
    if not coords:
        return (0.0, 0.0)
    lat = sum(p[0] for p in coords) / len(coords)
    lng = sum(p[1] for p in coords) / len(coords)
    return (lat, lng)


def _most_common_area(customers: list[OptimizeCustomer], fallback: str) -> str:
    areas = [c.area for c in customers if c.area]
    if not areas:
        return fallback
    return Counter(areas).most_common(1)[0][0]


def _solve_for_area(
    customers: list[OptimizeCustomer],
    matrix: list[MatrixCell],
    template_working_days: list[int],
    start: str,
    end: str,
    area_label_fallback: str,
    minutes_per_day: int,
    target_utilization_pct: int,
    time_limit_per_run: int = TIME_LIMIT_PER_RUN,
) -> tuple[list[SuggestedSalesman], list[UnassignedCustomer], SolverStatus]:
    if not customers:
        return [], [], "success"

    days_per_month = _working_days_per_month(template_working_days)
    lb = _lower_bound_count(customers, minutes_per_day, days_per_month)
    period_start = _next_period_start()

    # Pins name REAL salesman ids; the virtual roster uses VIRTUAL_ID_BASE+i, so
    # any surviving pin makes its customer unservable by construction. The
    # caller always sends `pinnedSalesmanId = pinned ?? assigned`, so after a
    # Compute Assignments run EVERY customer is pinned — without this the sizing
    # run returns an empty roster. Sizing asks "how many reps would I need",
    # which is upstream of who owns whom.
    unpinned = [
        c.model_copy(update={"pinned_salesman_id": None}) if c.pinned_salesman_id is not None else c
        for c in customers
    ]

    # ONE run at the arithmetic lower bound, then read the team size off the
    # vehicles the solver actually used.
    #
    # This used to be a loop that grew N until every customer was placed. That
    # stopped meaning anything on 2026-05-20 when capacity became soft: the VRP
    # no longer drops for capacity, so the "all assigned" exit fired on the
    # first iteration every time. Growing N does not help either — `_FIXED_COST`
    # makes the VRP consolidate into as few vehicles as it can, so handing it
    # more vehicles changes nothing. `target_utilization_pct` is therefore inert
    # today; honouring it needs a way to SPLIT the clusters the VRP forms, which
    # is a product decision, not a loop condition. See MEMORY.md 2026-09-21.
    virtuals = [
        _make_virtual_salesman(i, template_working_days, start, end) for i in range(lb)
    ]
    req = OptimizeRequest(
        period_start=period_start.isoformat(),
        period_end=(period_start + timedelta(days=28)).isoformat(),
        salesmen=virtuals,
        customers=unpinned,
        matrix_cells=matrix,
    )
    resp = run_optimize(req, time_limit_seconds=time_limit_per_run)
    last_unassigned: list[UnassignedCustomer] = resp.unassigned_customers
    last_status: SolverStatus = resp.solver_status
    chosen_visits = resp.visits
    chosen_n = lb

    # Group visits by virtual salesman → cluster of customer ids → centroid.
    by_salesman: dict[int, set[int]] = defaultdict(set)
    for v in chosen_visits:
        by_salesman[v.salesman_id].add(v.customer_id)

    customers_by_id = {c.id: c for c in customers}
    suggested: list[SuggestedSalesman] = []
    for i in range(chosen_n):
        sid = VIRTUAL_ID_BASE + i
        cust_ids = sorted(by_salesman.get(sid, set()))
        if not cust_ids:
            continue
        member_customers = [customers_by_id[cid] for cid in cust_ids]
        coords = [(c.lat, c.lng) for c in member_customers]
        lat, lng = _centroid(coords)
        suggested.append(
            SuggestedSalesman(
                index=len(suggested) + 1,
                assigned_customer_ids=cust_ids,
                suggested_area_label=_most_common_area(member_customers, area_label_fallback),
                suggested_home_lat=lat,
                suggested_home_lng=lng,
            )
        )

    return suggested, last_unassigned, last_status


def run_team_size_suggestion(
    request: SuggestTeamSizeRequest,
    time_limit_per_run: int = TIME_LIMIT_PER_RUN,
) -> SuggestTeamSizeResponse:
    """time_limit_per_run governs each inner run_optimize call's per-week budget.
    Default mirrors production; tests override to keep the test suite fast."""
    start_wall = time.monotonic()

    template = request.template
    minutes_per_day = _parse_hhmm(template.working_hours_end) - _parse_hhmm(
        template.working_hours_start
    )

    # Group customers by area; treat None/'' as a single 'unassigned' group.
    groups: dict[str, list[OptimizeCustomer]] = defaultdict(list)
    for c in request.customers:
        key = (c.area or "").strip()
        groups[key].append(c)

    all_suggested: list[SuggestedSalesman] = []
    all_unassigned: list[UnassignedCustomer] = []
    overall_status: SolverStatus = "success"

    for area_key, group in sorted(groups.items()):
        label_fallback = area_key if area_key else f"Cluster {len(all_suggested) + 1}"
        suggested, unassigned, status = _solve_for_area(
            group,
            request.matrix_cells,
            template.working_days,
            template.working_hours_start,
            template.working_hours_end,
            label_fallback,
            minutes_per_day,
            template.target_utilization_pct,
            time_limit_per_run=time_limit_per_run,
        )
        # Renumber across area groups so indices stay 1..N globally.
        for s in suggested:
            s.index = len(all_suggested) + 1
            all_suggested.append(s)
        all_unassigned.extend(unassigned)
        if status == "partial" and overall_status == "success":
            overall_status = "partial"
        elif status in ("infeasible", "error", "timeout") and overall_status != "infeasible":
            overall_status = status

    runtime = time.monotonic() - start_wall

    return SuggestTeamSizeResponse(
        recommended_salesmen=all_suggested,
        unassigned_customers=all_unassigned,
        runtime_seconds=round(runtime, 3),
        solver_status=overall_status,
    )
