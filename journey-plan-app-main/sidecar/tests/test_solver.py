"""Solver fixtures — no Google mocks. Pass in a fixed matrix and assert
properties of the resulting plan.
"""

from __future__ import annotations

from sidecar.models import (
    MatrixCell,
    OptimizeCustomer,
    OptimizeRequest,
    OptimizeSalesman,
)
from sidecar.solver import run_optimize
from sidecar.solver.cycle_assignment import assign_weeks


def _cust(
    cid: int,
    *,
    facetime: int = 30,
    freq: int = 1,
    area: str | None = None,
    region: str | None = None,
    pinned: int | None = None,
    allowed_days: list[int] | None = None,
    channel: str | None = None,
    window: tuple[str, str] | None = None,
) -> OptimizeCustomer:
    return OptimizeCustomer(
        id=cid,
        lat=23.5 + cid * 0.001,
        lng=58.5 + cid * 0.001,
        facetime_minutes=facetime,
        monthly_frequency=freq,
        allowed_days=allowed_days,
        pinned_salesman_id=pinned,
        area=area,
        region=region,
        channel=channel,
        visit_window_start=window[0] if window else None,
        visit_window_end=window[1] if window else None,
    )


def _salesman(
    sid: int,
    *,
    areas: list[str] | None = None,
    regions: list[str] | None = None,
    channel_skills: list[str] | None = None,
    start: tuple[float, float] | None = None,
    include_commute: bool = False,
    working_days: list[int] | None = None,
) -> OptimizeSalesman:
    return OptimizeSalesman(
        id=sid,
        working_days=working_days if working_days is not None else [0, 1, 2, 3, 4],
        working_hours_start="08:00",
        working_hours_end="17:00",
        assigned_areas=areas or [],
        assigned_regions=regions or [],
        channel_skills=channel_skills or [],
        start_location_lat=start[0] if start else None,
        start_location_lng=start[1] if start else None,
        include_commute=include_commute,
    )


def _uniform_matrix(customer_ids: list[int], seconds: int = 300) -> list[MatrixCell]:
    cells: list[MatrixCell] = []
    for a in customer_ids:
        for b in customer_ids:
            if a == b:
                continue
            cells.append(
                MatrixCell(
                    origin_index=a,
                    dest_index=b,
                    duration_seconds=seconds,
                    distance_meters=seconds * 10,
                    status="ok",
                )
            )
    return cells


def _baseline_request(
    customers: list[OptimizeCustomer], salesmen: list[OptimizeSalesman]
) -> OptimizeRequest:
    return OptimizeRequest(
        period_start="2026-06-01",
        period_end="2026-06-28",
        salesmen=salesmen,
        customers=customers,
        matrix_cells=_uniform_matrix([c.id for c in customers]),
    )


def test_cycle_assignment_freq2_uses_even_spread_pattern() -> None:
    customers = [_cust(1, freq=2, facetime=30), _cust(2, freq=2, facetime=30)]
    weeks = assign_weeks(customers)
    valid_patterns = ({0, 2}, {1, 3})
    for cid, ws in weeks.items():
        assert set(ws) in valid_patterns, f"customer {cid} weeks {ws} not even-spread"


def test_cycle_assignment_freq3_picks_three_of_four() -> None:
    customers = [_cust(i, freq=3, facetime=20) for i in range(1, 4)]
    weeks = assign_weeks(customers)
    for cid, ws in weeks.items():
        assert len(ws) == 3, f"freq=3 customer {cid} got {len(ws)} weeks"
        assert all(0 <= w <= 3 for w in ws)


def test_cycle_assignment_freq4_picks_all_weeks() -> None:
    customers = [_cust(1, freq=4, facetime=15)]
    weeks = assign_weeks(customers)
    assert weeks[1] == [0, 1, 2, 3]


def test_cycle_freq1_bundles_into_hot_week_when_cluster_already_active() -> None:
    # A freq=2 customer in (S1, Muscat) takes W0+W2, leaving W1/W3 cold for
    # that cluster. A freq=1 customer in the same cluster MUST land in W0 or
    # W2 (a hot week) — never W1/W3 — even though pure load-balance would
    # send it to a cold week to even out total load.
    customers = [
        _cust(1, freq=2, facetime=60, area="Muscat", pinned=1),
        _cust(2, freq=1, facetime=60, area="Muscat", pinned=1),
    ]
    weeks = assign_weeks(customers)
    assert set(weeks[1]) == {0, 2}, "freq=2 anchor took the W0,W2 pattern"
    assert weeks[2][0] in (0, 2), (
        f"freq=1 cluster-mate landed in {weeks[2]} — should bundle into W0 or W2 "
        "where the salesman is already visiting Muscat"
    )


def test_cycle_freq2_pair_in_same_cluster_aligns_on_one_pattern() -> None:
    # Two freq=2 customers in the same (salesman, area) cluster. Under the old
    # pure load-balance logic, the first takes {W0,W2} and the second takes
    # {W1,W3} (the now-lighter pattern). With cluster-aware tiebreak, the
    # second should align with the first's pattern so the salesman makes one
    # alternating-week run through the cluster instead of two separate
    # alternating runs that together cover every week.
    customers = [
        _cust(1, freq=2, facetime=60, area="Muscat", pinned=1),
        _cust(2, freq=2, facetime=60, area="Muscat", pinned=1),
    ]
    weeks = assign_weeks(customers)
    assert set(weeks[1]) == set(weeks[2]), (
        f"same-cluster freq=2 customers should share a pattern — got {weeks[1]} and {weeks[2]}"
    )
    # And the second customer should NOT have flipped to the opposite pattern.
    assert set(weeks[2]) in ({0, 2}, {1, 3})


def test_cycle_freq2_large_cluster_alternates_instead_of_piling_up() -> None:
    # Regression for the 2026-05-21 Rumais finding: a single cluster with 18
    # freq=2 customers used to pile every customer onto the first pattern
    # picked, leaving the opposite pair starved (weeks 2 & 4 got +18 visits
    # from freq=2, weeks 1 & 3 got 0). The bundling cap forces the hot side
    # to stay at most one customer ahead of the cold side per cluster, so
    # large clusters now split roughly 50/50 instead of running away.
    customers = [
        _cust(i, freq=2, facetime=40, area="Rumais", pinned=1) for i in range(1, 19)
    ]
    weeks = assign_weeks(customers)
    n_pattern_a = sum(1 for ws in weeks.values() if set(ws) == {0, 2})
    n_pattern_b = sum(1 for ws in weeks.values() if set(ws) == {1, 3})
    assert n_pattern_a + n_pattern_b == 18, "every customer must land on a valid pattern"
    # With the +1 lead cap, 18 customers split 10/8 (the hot side maintains a
    # 1-2 customer lead throughout). Pre-fix would have been 18/0.
    assert abs(n_pattern_a - n_pattern_b) <= 2, (
        f"freq=2 customers piled unbalanced: {n_pattern_a} on {{0,2}}, "
        f"{n_pattern_b} on {{1,3}}"
    )


def test_cycle_no_cluster_bundling_without_salesman_pin() -> None:
    # No pinned_salesman_id → no cluster key → behavior identical to pre-2026-05-20
    # load-balance. With four equal customers and no pin, freq=1's spread evenly
    # across W0..W3, not all bundled together.
    customers = [_cust(i, freq=1, facetime=30, area="Muscat") for i in range(1, 5)]
    weeks = assign_weeks(customers)
    chosen = sorted(weeks[c.id][0] for c in customers)
    assert chosen == [0, 1, 2, 3], (
        f"without salesman pins we should see one customer per week, got {chosen}"
    )


def test_cycle_different_salesmen_do_not_share_cluster_heat() -> None:
    # Same area "Muscat", two different salesmen owning subsets of it. The
    # cluster key is (salesman, area), so S1's heat must not steer S2.
    #
    # Rewritten 2026-09-21. This used to assert S2's freq=1 lands on W1 or W3,
    # reasoning that S1 had already put 60 min of load on W0 and W2. That was
    # reading the SHARED roster-wide week_load, which is exactly the bug fixed
    # that day: two salesmen work in parallel, so S2 has no reason to avoid W0
    # just because S1 is busy then — making it do so serialises independent
    # people and is what left salesmen idle for half the month. S2 now balances
    # against its OWN weeks, all of which are empty, so W0 is correct.
    #
    # The heat-isolation property is asserted directly instead: S2's second
    # freq=1 must bundle onto S2's own hot week, not onto S1's.
    customers = [
        _cust(1, freq=2, facetime=60, area="Muscat", pinned=1),  # S1 takes W0+W2
        _cust(2, freq=1, facetime=60, area="Muscat", pinned=2),
        _cust(3, freq=1, facetime=50, area="Muscat", pinned=2),
    ]
    weeks = assign_weeks(customers)
    assert set(weeks[1]) == {0, 2}
    s2_first = weeks[2][0]
    s2_second = weeks[3][0]
    assert s2_second == s2_first, (
        f"S2's two freq=1 customers should bundle onto S2's own hot week "
        f"{s2_first}, got {s2_second}"
    )


def test_cycle_salesman_heat_does_not_leak_across_salesmen() -> None:
    # Direct check that heat is keyed by (salesman, area): S1 makes W1+W3 hot
    # in "Muscat"; S2's freq=2 in the same area must still choose on its own
    # merits and land on the canonical first pattern, not inherit S1's.
    customers = [
        _cust(1, freq=2, facetime=90, area="Muscat", pinned=1),
        _cust(2, freq=2, facetime=90, area="Muscat", pinned=1),
        _cust(3, freq=2, facetime=60, area="Muscat", pinned=2),
    ]
    weeks = assign_weeks(customers)
    assert set(weeks[3]) in ({0, 2}, {1, 3})
    assert len(weeks[3]) == 2


def test_single_salesman_can_serve_small_set() -> None:
    customers = [_cust(i, facetime=30) for i in range(1, 5)]
    salesmen = [_salesman(1)]
    response = run_optimize(_baseline_request(customers, salesmen), time_limit_seconds=2)
    assert response.solver_status == "success"
    assert len(response.visits) == 4
    assert {v.customer_id for v in response.visits} == {1, 2, 3, 4}


def test_pinned_customer_goes_to_pinned_salesman() -> None:
    customers = [
        _cust(1, pinned=10, facetime=30),
        _cust(2, facetime=30),
        _cust(3, facetime=30),
    ]
    salesmen = [_salesman(10), _salesman(20)]
    response = run_optimize(_baseline_request(customers, salesmen), time_limit_seconds=2)
    assert response.solver_status == "success"
    by_cust = {v.customer_id: v.salesman_id for v in response.visits}
    assert by_cust[1] == 10, "pinned customer 1 must be served by salesman 10"


def test_area_restriction_routes_customers_to_eligible_salesmen() -> None:
    customers = [
        _cust(1, area="North", facetime=30),
        _cust(2, area="North", facetime=30),
        _cust(3, area="South", facetime=30),
        _cust(4, area="South", facetime=30),
    ]
    salesmen = [_salesman(100, areas=["North"]), _salesman(200, areas=["South"])]
    response = run_optimize(_baseline_request(customers, salesmen), time_limit_seconds=2)
    assert response.solver_status == "success"
    by_cust = {v.customer_id: v.salesman_id for v in response.visits}
    assert by_cust[1] == 100 and by_cust[2] == 100
    assert by_cust[3] == 200 and by_cust[4] == 200


def test_region_match_routes_subarea_customers_to_region_salesman() -> None:
    # Phase 9: customers tagged with narrow sub-wilayats (area="Dhank", "Ibri")
    # and a parent region ("Nizwa"). Salesman is assigned at the region level
    # only — its assigned_areas is empty. The new eligibility rule lets the
    # region match satisfy area-or-region eligibility.
    customers = [
        _cust(1, area="Dhank", region="Nizwa", facetime=30),
        _cust(2, area="Ibri", region="Nizwa", facetime=30),
        _cust(3, area="Mirbat", region="Salalah", facetime=30),
        _cust(4, area="Awqat", region="Salalah", facetime=30),
    ]
    salesmen = [
        _salesman(100, regions=["Nizwa"]),
        _salesman(200, regions=["Salalah"]),
    ]
    response = run_optimize(_baseline_request(customers, salesmen), time_limit_seconds=2)
    assert response.solver_status == "success"
    by_cust = {v.customer_id: v.salesman_id for v in response.visits}
    assert by_cust[1] == 100 and by_cust[2] == 100
    assert by_cust[3] == 200 and by_cust[4] == 200


def test_area_or_region_either_match_satisfies_eligibility() -> None:
    # Salesman is assigned BOTH a narrow area and a broader region. Customers
    # tagged with the matching area or the matching region (or both) should
    # all be eligible.
    customers = [
        _cust(1, area="Dhank", region=None),       # matches via area
        _cust(2, area="Ibri", region="Nizwa"),     # matches via region
        _cust(3, area=None, region="Nizwa"),       # matches via region only
        _cust(4, area="Bahla", region=None),       # ineligible: outside both
    ]
    other = _salesman(200, areas=["Bahla"])  # picks up customer 4
    salesman = _salesman(100, areas=["Dhank"], regions=["Nizwa"])
    response = run_optimize(
        _baseline_request(customers, [salesman, other]), time_limit_seconds=2
    )
    assert response.solver_status == "success"
    by_cust = {v.customer_id: v.salesman_id for v in response.visits}
    assert by_cust[1] == 100
    assert by_cust[2] == 100
    assert by_cust[3] == 100
    assert by_cust[4] == 200


def test_channel_skill_restricts_eligibility() -> None:
    # 4 customers, 2 MT + 2 TT. Salesman A only has MT skill, B only TT.
    # Each must serve only customers in their channel.
    customers = [
        _cust(1, channel="MT", facetime=30),
        _cust(2, channel="MT", facetime=30),
        _cust(3, channel="TT", facetime=30),
        _cust(4, channel="TT", facetime=30),
    ]
    salesmen = [_salesman(100, channel_skills=["MT"]), _salesman(200, channel_skills=["TT"])]
    response = run_optimize(_baseline_request(customers, salesmen), time_limit_seconds=2)
    assert response.solver_status == "success"
    by_cust = {v.customer_id: v.salesman_id for v in response.visits}
    assert by_cust[1] == 100 and by_cust[2] == 100
    assert by_cust[3] == 200 and by_cust[4] == 200


def test_drop_priority_prefers_low_freq_when_overcapacity() -> None:
    # One salesman, capacity ~10,800 min/month. Mix:
    #  - 1 freq=8 hypermarket (facetime=90 → load 720)
    #  - 50 freq=1 small stores (facetime=300 each → load 300/each, 15,000 total)
    # Combined ~15,720 → must drop ~16-17 customers worth. The freq-weighted
    # drop penalty (64x for freq=8 vs 1x for freq=1) should make the solver
    # drop low-freq stores rather than the high-freq hypermarket.
    high = _cust(1, freq=8, facetime=90)
    low = [_cust(i, freq=1, facetime=300) for i in range(2, 52)]
    customers = [high, *low]
    salesmen = [_salesman(1)]
    response = run_optimize(_baseline_request(customers, salesmen), time_limit_seconds=10)
    visited = {v.customer_id for v in response.visits}
    assert 1 in visited, "freq=8 hypermarket must not be dropped"
    # And it should be visited the full 8 times — drop priority applies per-
    # visit-occurrence, so all 8 of its week-cycle nodes must stick.
    visits_for_high = [v for v in response.visits if v.customer_id == 1]
    assert len(visits_for_high) == 8


def test_capacity_overflow_assigns_everyone_with_no_drops() -> None:
    # 2026-05-20: drops for capacity reasons are disabled. Same setup as the
    # old "reports_unassigned" test (50 customers x 600 min facetime against
    # one salesman's 10,800 min/month) — but now we assert NO unassigned
    # customers and that the schedule pushes past working hours instead.
    customers = [_cust(i, facetime=600, freq=1) for i in range(1, 51)]
    salesmen = [_salesman(1)]
    response = run_optimize(_baseline_request(customers, salesmen), time_limit_seconds=5)
    assert response.unassigned_customers == [], (
        f"expected zero unassigned, got {len(response.unassigned_customers)}: "
        f"{[u.reason for u in response.unassigned_customers]}"
    )
    assert {v.customer_id for v in response.visits} == {c.id for c in customers}
    assert response.solver_status == "success"


def test_weekly_customer_keeps_same_weekday_across_all_weeks() -> None:
    # PJP call-day regularity (2026-06-10): a freq=4 customer must be visited
    # on the SAME weekday in all 4 weeks, even when freq=1 noise customers
    # land in different weeks and change each week's problem shape. Without
    # the anchoring in assemble.run_optimize this held only by accident of
    # solver determinism.
    from datetime import date as _date

    weekly = [_cust(i, freq=4, facetime=60) for i in range(1, 6)]
    noise = [_cust(i, freq=1, facetime=240) for i in range(10, 22)]
    customers = weekly + noise
    salesmen = [_salesman(1)]
    response = run_optimize(_baseline_request(customers, salesmen), time_limit_seconds=5)
    assert response.solver_status == "success"
    for c in weekly:
        visits = [v for v in response.visits if v.customer_id == c.id]
        assert len(visits) == 4
        dows = {_date.fromisoformat(v.scheduled_date).weekday() for v in visits}
        assert len(dows) == 1, (
            f"freq=4 customer {c.id} drifted across weekdays: "
            f"{sorted((v.scheduled_date) for v in visits)}"
        )


def test_fortnightly_customer_keeps_same_weekday_on_both_visits() -> None:
    from datetime import date as _date

    fortnightly = [_cust(i, freq=2, facetime=60) for i in range(1, 4)]
    noise = [_cust(i, freq=1, facetime=240) for i in range(10, 18)]
    customers = fortnightly + noise
    salesmen = [_salesman(1)]
    response = run_optimize(_baseline_request(customers, salesmen), time_limit_seconds=5)
    assert response.solver_status == "success"
    for c in fortnightly:
        visits = [v for v in response.visits if v.customer_id == c.id]
        assert len(visits) == 2
        dows = {_date.fromisoformat(v.scheduled_date).weekday() for v in visits}
        assert len(dows) == 1, (
            f"freq=2 customer {c.id} visited on different weekdays: "
            f"{sorted(v.scheduled_date for v in visits)}"
        )


def test_anchoring_does_not_break_uneven_multivisit_weeks() -> None:
    # freq=5 = one 2-visit week + three 1-visit weeks. If the customer's first
    # solved week is a 1-visit week, the single-day anchor must NOT be applied
    # to the 2-visit week (it can't host two same-day visits) — the guard in
    # run_optimize skips anchoring when count > len(anchor). All 5 visits must
    # materialise either way.
    customers = [_cust(1, freq=5, facetime=30), _cust(2, freq=1, facetime=30)]
    salesmen = [_salesman(1)]
    response = run_optimize(_baseline_request(customers, salesmen), time_limit_seconds=5)
    assert response.unassigned_customers == []
    assert len([v for v in response.visits if v.customer_id == 1]) == 5


def _hhmm_to_min(s: str) -> int:
    h, m = s.split(":")
    return int(h) * 60 + int(m)


def test_visit_window_lower_bound_delays_visit_until_window_opens() -> None:
    # Single customer windowed 10:00-12:00 against an 08:00 shift start. The
    # rep must WAIT for the window (slack), and the end-cumul finalizer should
    # then schedule the visit at exactly the window open.
    customers = [_cust(1, window=("10:00", "12:00"))]
    salesmen = [_salesman(1)]
    response = run_optimize(_baseline_request(customers, salesmen), time_limit_seconds=2)
    assert response.solver_status == "success"
    assert len(response.visits) == 1
    assert response.visits[0].scheduled_start_time == "10:00"


def test_visit_window_upper_bound_pulls_visit_early_in_the_day() -> None:
    # One "call before 09:30" customer in a day-sized pack of windowless
    # peers. The window penalty must pull the windowed visit to the front of
    # its route so it starts inside [08:00, 09:30].
    windowed = _cust(1, facetime=30, window=("08:00", "09:30"))
    peers = [_cust(i, facetime=45) for i in range(2, 10)]
    customers = [windowed, *peers]
    salesmen = [_salesman(1)]
    response = run_optimize(_baseline_request(customers, salesmen), time_limit_seconds=5)
    assert response.solver_status == "success"
    visit = next(v for v in response.visits if v.customer_id == 1)
    start = _hhmm_to_min(visit.scheduled_start_time)
    assert _hhmm_to_min("08:00") <= start <= _hhmm_to_min("09:30"), (
        f"windowed visit scheduled at {visit.scheduled_start_time}, "
        "outside its 08:00-09:30 window"
    )


def test_no_window_schedules_pack_from_shift_start() -> None:
    # Windowless plans must keep the old earliest-possible timing: with slack
    # now allowed in the Time dimension, the finalizer must squeeze waiting
    # back out. First visit of any day starts exactly at shift start.
    customers = [_cust(i, facetime=30) for i in range(1, 5)]
    salesmen = [_salesman(1)]
    response = run_optimize(_baseline_request(customers, salesmen), time_limit_seconds=2)
    assert response.solver_status == "success"
    by_day: dict[str, list[str]] = {}
    for v in response.visits:
        by_day.setdefault(v.scheduled_date, []).append(v.scheduled_start_time)
    for day, times in by_day.items():
        assert min(times) == "08:00", f"{day} first visit at {min(times)}, expected 08:00"


def test_commute_legs_priced_and_timed_when_enabled() -> None:
    # include_commute on + matrix rows under the pseudo-id -salesman_id: the
    # day must start with the home→first leg (first visit time = shift start +
    # commute, drive_minutes_to = commute minutes).
    customers = [_cust(1, facetime=30)]
    salesmen = [_salesman(1, start=(23.4, 58.4), include_commute=True)]
    request = _baseline_request(customers, salesmen)
    request.matrix_cells.extend(
        [
            MatrixCell(
                origin_index=-1, dest_index=1, duration_seconds=1800,
                distance_meters=18000, status="ok",
            ),
            MatrixCell(
                origin_index=1, dest_index=-1, duration_seconds=1800,
                distance_meters=18000, status="ok",
            ),
        ]
    )
    response = run_optimize(request, time_limit_seconds=2)
    assert response.solver_status == "success"
    assert len(response.visits) == 1
    visit = response.visits[0]
    assert visit.drive_minutes_to == 30
    assert visit.scheduled_start_time == "08:30"


def test_commute_defaults_off_and_keeps_legacy_schedule() -> None:
    # Same salesman with a start location but include_commute left False:
    # behavior must be the legacy dummy-depot one — day starts at the first
    # customer at shift start with zero drive.
    customers = [_cust(1, facetime=30)]
    salesmen = [_salesman(1, start=(23.4, 58.4))]
    response = run_optimize(_baseline_request(customers, salesmen), time_limit_seconds=2)
    assert response.solver_status == "success"
    visit = response.visits[0]
    assert visit.drive_minutes_to == 0
    assert visit.scheduled_start_time == "08:00"


def test_commute_falls_back_to_haversine_without_matrix_rows() -> None:
    # Commute enabled but no -salesman_id rows in the matrix → the pessimistic
    # 40 km/h haversine estimate kicks in, so the first leg is non-zero.
    customers = [_cust(1, facetime=30)]
    salesmen = [_salesman(1, start=(23.0, 58.0), include_commute=True)]
    response = run_optimize(_baseline_request(customers, salesmen), time_limit_seconds=2)
    assert response.solver_status == "success"
    assert response.visits[0].drive_minutes_to >= 1


def test_visit_cap_scales_up_for_short_call_rosters() -> None:
    # 20 customers x 10-min facetime forced onto one Sunday. The legacy fixed
    # cap (12 soft / 18 hard) made customers 19-20 unservable; the scaled cap
    # (360 / 10 = 36) hosts the whole TT-style day without penalty.
    from datetime import date as _date

    from sidecar.solver.vrp import solve_week

    customers = [_cust(i, facetime=10, allowed_days=[0]) for i in range(1, 21)]
    salesmen = [_salesman(1)]
    visits, unassigned = solve_week(
        customers, salesmen, {}, _date(2026, 6, 7), 0, time_limit_seconds=5
    )
    assert unassigned == []
    assert len(visits) == 20
    assert len({v.scheduled_date for v in visits}) == 1


def test_visit_cap_unchanged_for_thirty_minute_calls() -> None:
    # A ~30-min-call roster must keep the legacy 12-cap behavior exactly: 14
    # one-day customers can't all fit under the soft cap, so the day overflows
    # past 12 only via the penalty (all still served).
    from datetime import date as _date

    from sidecar.solver.vrp import _MAX_VISITS_PER_DAY, solve_week

    customers = [_cust(i, facetime=30, allowed_days=[0]) for i in range(1, 15)]
    salesmen = [_salesman(1)]
    visits, unassigned = solve_week(
        customers, salesmen, {}, _date(2026, 6, 7), 0, time_limit_seconds=5
    )
    assert unassigned == []
    assert len(visits) == 14
    assert _MAX_VISITS_PER_DAY == 12


def test_cycle_assignment_freq3_always_has_a_gap_week() -> None:
    # 2026-06-10: freq=3 must never take 3 consecutive weeks ({W0,W1,W2} or
    # {W1,W2,W3}) — that strings 3 visits then a 2-week hole. Only the
    # gap-inside patterns are legal.
    customers = [_cust(i, freq=3, facetime=20 + i) for i in range(1, 10)]
    weeks = assign_weeks(customers)
    legal = ({0, 1, 3}, {0, 2, 3})
    for cid, ws in weeks.items():
        assert set(ws) in legal, f"customer {cid} got consecutive weeks {ws}"


def test_multivisit_spread_adapts_to_salesman_working_week() -> None:
    # freq=8 customer pinned to a Mon-Fri salesman. The spread used to assume
    # Sun-Thu, planting a Sunday clone nobody works (visit lost). Now the
    # spread universe is the pinned salesman's actual working week.
    from datetime import date as _date

    customers = [_cust(1, freq=8, facetime=30, pinned=1)]
    salesmen = [_salesman(1, working_days=[1, 2, 3, 4, 5])]  # Mon-Fri
    response = run_optimize(_baseline_request(customers, salesmen), time_limit_seconds=5)
    assert response.unassigned_customers == []
    visits = [v for v in response.visits if v.customer_id == 1]
    assert len(visits) == 8
    dows = {(_date.fromisoformat(v.scheduled_date).weekday() + 1) % 7 for v in visits}
    assert dows <= {1, 2, 3, 4, 5}, f"visits landed outside Mon-Fri: {sorted(dows)}"


def test_no_drops_when_demand_exceeds_old_hard_visit_ceiling() -> None:
    # Live-install regression (2026-06-10): Sohar #2 carried 109 customers x
    # freq 4 = 436 visits/month against the old hard ceiling of 20 days x 18 =
    # 360 slots -> 19 customers were unplaceable and dropped. Reproduced at
    # week scale: 95 thirty-minute customers on one salesman's 5-day week =
    # 19/day, above the old 18 hard cap. With the soft-only Visits dimension
    # every customer must be placed (days paint red instead).
    from datetime import date as _date

    from sidecar.solver.vrp import solve_week

    customers = [_cust(i, facetime=30) for i in range(1, 96)]
    salesmen = [_salesman(1)]
    visits, unassigned = solve_week(
        customers, salesmen, {}, _date(2026, 6, 7), 0, time_limit_seconds=10
    )
    assert unassigned == [], (
        f"{len(unassigned)} customers dropped: {[u.reason for u in unassigned[:3]]}"
    )
    assert len(visits) == 95


def test_capacity_overflow_still_drops_when_no_eligible_vehicle() -> None:
    # Eligibility hole (not capacity): a customer pinned to a salesman that
    # doesn't exist in the roster. No vehicle can serve them, so the
    # MemberCt + Disjunction kicks in and the customer surfaces as unassigned
    # with a specific reason. Soft-capacity does NOT mean we ignore eligibility.
    pinned_to_missing = _cust(1, pinned=999)  # no salesman id 999 in roster
    normal = _cust(2)
    salesmen = [_salesman(1)]
    response = run_optimize(
        _baseline_request([pinned_to_missing, normal], salesmen),
        time_limit_seconds=2,
    )
    assert any(u.customer_id == 1 for u in response.unassigned_customers)
    reasons = {u.reason for u in response.unassigned_customers if u.customer_id == 1}
    assert reasons == {"no salesman covers this area / pin"}, reasons
    assert any(v.customer_id == 2 for v in response.visits)


def test_partially_placed_multivisit_customer_reports_the_dropped_clone() -> None:
    """Regression: a multi-visit customer is expanded into one clone NODE per
    visit-in-week. solve_week used to report drops by customer id, so placing
    one clone marked the whole customer 'assigned' and the other clone vanished
    with no unassigned entry — the plan silently shipped fewer visits than the
    requested frequency while solver_status still read 'success'."""
    from datetime import date as _date

    from sidecar.solver.vrp import solve_week

    # Two clones of customer 1: one on a day the salesman works, one on Friday
    # (5), which nobody works. The Friday clone has no eligible vehicle.
    clone_mon = _cust(1, facetime=30, allowed_days=[1])
    clone_fri = _cust(1, facetime=30, allowed_days=[5])
    salesmen = [_salesman(1, working_days=[0, 1, 2, 3, 4])]

    visits, unassigned = solve_week(
        [clone_mon, clone_fri], salesmen, {}, _date(2026, 6, 7), 0, time_limit_seconds=5
    )

    assert len(visits) == 1, "the Monday clone should still be placed"
    assert len(unassigned) == 1, "the Friday clone must be reported, not swallowed"
    assert unassigned[0].customer_id == 1
    assert "allowed_days" in unassigned[0].reason


def test_freq1_cluster_does_not_collapse_into_one_week() -> None:
    """Regression: freq=1 cluster bundling had no counter-balance cap (freq=2
    got one on 2026-05-21). The heat preference is absorbing — the first
    customer makes its week hot, every later one in the cluster then sees that
    week as the only hot option and piles on — so a territory of freq=1-only
    customers collapsed entirely into W0 and the salesman had three empty weeks
    there."""
    from collections import Counter

    customers = [_cust(i, freq=1, facetime=30, pinned=1, area="Rustaq") for i in range(1, 41)]
    assignments = assign_weeks(customers)

    per_week = Counter(w for weeks in assignments.values() for w in weeks)
    assert set(per_week) == {0, 1, 2, 3}, f"some weeks got nothing: {dict(per_week)}"
    assert max(per_week.values()) - min(per_week.values()) <= 2, (
        f"freq=1 cluster is lopsided across weeks: {dict(per_week)}"
    )


def test_small_freq1_cluster_stays_bundled() -> None:
    """The cap must not destroy the bundling it is capping: a cluster with only
    a couple of freq=1 customers should still share one week so the salesman
    makes one trip to that area, matching the freq=2 rule's design."""
    customers = [_cust(i, freq=1, facetime=30, pinned=1, area="Tiny") for i in (1, 2)]
    assignments = assign_weeks(customers)
    weeks = {w for ws in assignments.values() for w in ws}
    assert len(weeks) == 1, f"small cluster was split across {weeks}"


def test_week_load_is_balanced_per_salesman_not_globally() -> None:
    """Regression: week_load was a single roster-wide accumulator, so the greedy
    balancer optimised the wrong quantity. With two pinned salesmen and
    interleaved facetimes it could leave A on weeks {0,0,2,2} and B on
    {1,1,3,3} — each idle half the month — while the global per-week totals
    looked perfectly flat."""
    customers = [_cust(i, freq=1, facetime=30 + i, pinned=1 + (i % 2)) for i in range(1, 9)]
    assignments = assign_weeks(customers)

    for sid in (1, 2):
        weeks = {
            w
            for c in customers
            if c.pinned_salesman_id == sid
            for w in assignments[c.id]
        }
        assert weeks == {0, 1, 2, 3}, f"salesman {sid} only works weeks {sorted(weeks)}"
