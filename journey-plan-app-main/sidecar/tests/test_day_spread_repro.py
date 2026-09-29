"""Reproduction test for the user-reported Nizwa/Rumais/Salalah cramming bug.

User flagged: solver leaves 3 days empty and crams all customers into 2 days
with massive overflow, even when spread would fit easily. Prove the bug exists
in isolation.

Setup: one salesman, 5 working days, 540-min window. 30 freq=4 customers (each
visits 1x/week), 30-min facetime each, drive 5 min between any pair. Per-week:
30 customers x 30 = 900 min facetime + ~150 min drive = 1050 min. Spread across
5 days = 210 min/day, zero overflow. Optimal solution is spread.

If the solver crams into 1-2 days, we'll see large cumul overflow.
"""

from __future__ import annotations

from collections import defaultdict

from sidecar.models import (
    MatrixCell,
    OptimizeCustomer,
    OptimizeRequest,
    OptimizeSalesman,
    OptimizeVisit,
)
from sidecar.solver import run_optimize


def test_solver_respects_max_visits_per_day_hard_cap() -> None:
    """30 light-facetime customers per week. With the hard cap (Phase 11b: 12
    visits/vehicle, down from 15) the solver structurally cannot put more than
    _MAX_VISITS_PER_DAY visits on any day, regardless of cost-model gradients."""
    from sidecar.solver.vrp import _MAX_VISITS_PER_DAY

    customers = [
        OptimizeCustomer(
            id=i,
            lat=23.5 + i * 0.001,
            lng=58.5 + i * 0.001,
            facetime_minutes=30,
            monthly_frequency=4,  # 1 visit per week, free day choice
        )
        for i in range(1, 31)
    ]
    salesman = OptimizeSalesman(
        id=100,
        working_days=[0, 1, 2, 3, 4],  # Sun-Thu, 5 days/week
        working_hours_start="08:00",
        working_hours_end="17:00",  # 540 min window
    )
    matrix = [
        MatrixCell(
            origin_index=a.id,
            dest_index=b.id,
            duration_seconds=300,  # 5 min drive between any pair
            distance_meters=3000,
            status="ok",
        )
        for a in customers for b in customers if a.id != b.id
    ]
    req = OptimizeRequest(
        period_start="2026-06-07",  # Sunday
        period_end="2026-07-04",
        salesmen=[salesman],
        customers=customers,
        matrix_cells=matrix,
    )
    resp = run_optimize(req, time_limit_seconds=10)

    # Group visits by date and check no day exceeds the operational cap.
    per_day: dict[str, list[OptimizeVisit]] = defaultdict(list)
    for v in resp.visits:
        per_day[v.scheduled_date].append(v)

    overcrammed = [(d, len(vs)) for d, vs in per_day.items() if len(vs) > _MAX_VISITS_PER_DAY]
    msg = (
        f"Cap = {_MAX_VISITS_PER_DAY} visits/day. Days violating cap: {overcrammed}. "
        f"Full per-day count: {sorted((d, len(vs)) for d, vs in per_day.items())}"
    )
    assert not overcrammed, msg
    # All customers must still be visited — the cap is a spread enforcement, not
    # a drop mechanism. With 30 visits/week and 5 days x 12 cap = 60 capacity/wk,
    # everything fits comfortably.
    assert {v.customer_id for v in resp.visits} == {c.id for c in customers}


def test_solver_does_not_open_fragment_days_for_single_customer() -> None:
    """Phase 11b regression test: with _FIXED_COST raised to 40, the solver
    should consolidate a tight geographic cluster onto fewer days per week
    rather than open a 1-customer day next to a 4-customer one.

    5 customers tightly clustered (3-min drives between any pair), 30-min
    facetime, freq=4 (so each is visited once per week — all 5 land in the
    same week, not spread across the month). 5 working days available.

    Expected: ≤ 2 days used PER WEEK (5 visits = 150 min face + drive fit on
    1-2 days even with the 12-cap). Pre-Phase-11b at fixed cost 15, the
    solver would happily open 4-5 days each with 1-2 customers because the
    marginal day-open cost (15) was below the marginal drive savings."""
    customers = [
        OptimizeCustomer(
            id=i,
            lat=23.5 + i * 0.0001,  # tight cluster
            lng=58.5 + i * 0.0001,
            facetime_minutes=30,
            monthly_frequency=4,  # one visit per week — all 5 visited every week
        )
        for i in range(1, 6)
    ]
    salesman = OptimizeSalesman(
        id=100,
        working_days=[0, 1, 2, 3, 4],
        working_hours_start="08:00",
        working_hours_end="17:00",
    )
    matrix = [
        MatrixCell(
            origin_index=a.id,
            dest_index=b.id,
            duration_seconds=180,  # 3 min — tight cluster
            distance_meters=1500,
            status="ok",
        )
        for a in customers for b in customers if a.id != b.id
    ]
    req = OptimizeRequest(
        period_start="2026-06-07",
        period_end="2026-07-04",
        salesmen=[salesman],
        customers=customers,
        matrix_cells=matrix,
    )
    resp = run_optimize(req, time_limit_seconds=10)

    # Group visits by ISO-week so we measure days-per-week, not days-per-month.
    from datetime import date as _date

    per_week: dict[int, set[str]] = defaultdict(set)
    for v in resp.visits:
        wk = _date.fromisoformat(v.scheduled_date).isocalendar().week
        per_week[wk].add(v.scheduled_date)

    worst = max((len(days) for days in per_week.values()), default=0)
    assert worst <= 2, (
        f"Solver used {worst} days in one week to deliver 5 tight-cluster visits. "
        f"Per-week day counts: { {w: sorted(d) for w, d in per_week.items()} }. "
        f"With Phase 11b fixed cost = 40 + 3-min cluster drives, expected ≤ 2 days/wk."
    )
    # All 20 visits (5 customers x 4 weeks) must land somewhere.
    assert len(resp.visits) == 20


def test_solver_moves_distant_cluster_to_light_day() -> None:
    """Phase 11b spread test: two geographically distinct clusters (A and B,
    ~30 min drive apart), one salesman, freq=4 (one visit each per week, all
    customers present in every week's solve), 5 working days. The solver
    should NOT bundle A+B onto the same day when separating them yields a
    much shorter route — the Seeb/Amerat split case the user described.

    Cluster A: 4 customers, tight (3-min drives within).
    Cluster B: 6 customers, tight (3-min drives within).
    Cross-cluster drive: 30 min.

    Bundle-cost: at least 30 min of cross-cluster drive wasted per bundled
    day. Split-cost: extra `_FIXED_COST = 40` to open a second day. Drive
    savings dominate, so the solver should split.

    Assertion: in every week, no single day contains members of both A and B."""
    cluster_a = [
        OptimizeCustomer(
            id=i,
            lat=23.50 + i * 0.001,
            lng=58.50 + i * 0.001,
            facetime_minutes=60,
            monthly_frequency=4,
        )
        for i in range(1, 5)
    ]
    cluster_b = [
        OptimizeCustomer(
            id=i,
            lat=24.00 + (i - 100) * 0.001,
            lng=58.50 + (i - 100) * 0.001,
            facetime_minutes=60,
            monthly_frequency=4,
        )
        for i in range(101, 107)
    ]
    customers = cluster_a + cluster_b
    salesman = OptimizeSalesman(
        id=100,
        working_days=[0, 1, 2, 3, 4],
        working_hours_start="08:00",
        working_hours_end="17:00",
    )

    def _drive_sec(a: OptimizeCustomer, b: OptimizeCustomer) -> int:
        same_cluster = (a.id < 100) == (b.id < 100)
        return 180 if same_cluster else 1800  # 3 min vs 30 min

    matrix = [
        MatrixCell(
            origin_index=a.id,
            dest_index=b.id,
            duration_seconds=_drive_sec(a, b),
            distance_meters=_drive_sec(a, b) * 10,
            status="ok",
        )
        for a in customers for b in customers if a.id != b.id
    ]
    req = OptimizeRequest(
        period_start="2026-06-07",
        period_end="2026-07-04",
        salesmen=[salesman],
        customers=customers,
        matrix_cells=matrix,
    )
    resp = run_optimize(req, time_limit_seconds=10)

    per_day: dict[str, set[int]] = defaultdict(set)
    for v in resp.visits:
        per_day[v.scheduled_date].add(v.customer_id)

    cluster_a_ids = {c.id for c in cluster_a}
    cluster_b_ids = {c.id for c in cluster_b}

    mixed_days = [
        d for d, ids in per_day.items()
        if (ids & cluster_a_ids) and (ids & cluster_b_ids)
    ]
    assert not mixed_days, (
        f"Solver bundled clusters A and B on the same day(s): {mixed_days}. "
        f"Per-day membership: { {d: sorted(ids) for d, ids in per_day.items()} }. "
        f"Expected separated days (Phase 11b cluster-respecting spread)."
    )
    # And every customer should be visited.
    assert {v.customer_id for v in resp.visits} == {c.id for c in customers}


def test_solver_avoids_overflow_when_empty_days_available() -> None:
    """Stronger version: each customer is 60 min facetime so cramming all 30
    onto 2 days CAUSES overflow (30x60=1800 face + drive ~ 1900 min vs 540
    soft cap = 1360 min overflow per day -> 136,000 penalty). Spread = 0
    overflow. If the solver still crams, the soft penalty is not winning."""
    customers = [
        OptimizeCustomer(
            id=i,
            lat=23.5 + i * 0.001,
            lng=58.5 + i * 0.001,
            facetime_minutes=60,
            monthly_frequency=4,
        )
        for i in range(1, 31)
    ]
    salesman = OptimizeSalesman(
        id=100,
        working_days=[0, 1, 2, 3, 4],
        working_hours_start="08:00",
        working_hours_end="17:00",
    )
    matrix = [
        MatrixCell(
            origin_index=a.id,
            dest_index=b.id,
            duration_seconds=300,
            distance_meters=3000,
            status="ok",
        )
        for a in customers for b in customers if a.id != b.id
    ]
    req = OptimizeRequest(
        period_start="2026-06-07",
        period_end="2026-07-04",
        salesmen=[salesman],
        customers=customers,
        matrix_cells=matrix,
    )
    resp = run_optimize(req, time_limit_seconds=15)

    per_day: dict[str, list[OptimizeVisit]] = defaultdict(list)
    for v in resp.visits:
        per_day[v.scheduled_date].append(v)

    # If solver crams to 2 days, each carries ~15 visits x 60 face = 900 + drive
    # ~ 970 min, overflow = 430 min/day. Total weekly overflow = 860 min x 4 weeks
    # = 3440 min x 100 = 344,000 penalty.
    # If solver spreads to 5 days, each carries 6 visits x 60 = 360 + drive = 385,
    # 0 overflow.
    overflowing_days = []
    for d_iso, vs in per_day.items():
        face_sum = sum(60 for _ in vs)
        drive_sum = sum(v.drive_minutes_to or 0 for v in vs)
        total = face_sum + drive_sum
        if total > 540:
            overflowing_days.append((d_iso, len(vs), total))

    assert not overflowing_days, (
        f"Solver chose to overflow {len(overflowing_days)} days when empty "
        f"days were available. Worst: {sorted(overflowing_days, key=lambda x: -x[2])[:3]}"
    )
