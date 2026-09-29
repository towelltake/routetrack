"""Tests for the Phase 7a upfront salesman-assignment CP-SAT model.

Fixture pattern mirrors test_solver.py — fixed matrices, asserted properties.
"""

from __future__ import annotations

from sidecar.models import (
    AssignSalesmenRequest,
    MatrixCell,
    OptimizeCustomer,
    OptimizeSalesman,
)
from sidecar.solver import run_assign_salesmen


def _cust(
    cid: int,
    *,
    lat: float | None = None,
    lng: float | None = None,
    facetime: int = 30,
    freq: int = 1,
    area: str | None = None,
    region: str | None = None,
    pinned: int | None = None,
    channel: str | None = None,
) -> OptimizeCustomer:
    return OptimizeCustomer(
        id=cid,
        lat=23.5 + cid * 0.001 if lat is None else lat,
        lng=58.5 + cid * 0.001 if lng is None else lng,
        facetime_minutes=facetime,
        monthly_frequency=freq,
        allowed_days=None,
        pinned_salesman_id=pinned,
        area=area,
        region=region,
        channel=channel,
    )


def _salesman(
    sid: int,
    *,
    areas: list[str] | None = None,
    regions: list[str] | None = None,
    channel_skills: list[str] | None = None,
    start_lat: float = 23.5,
    start_lng: float = 58.5,
) -> OptimizeSalesman:
    return OptimizeSalesman(
        id=sid,
        working_days=[0, 1, 2, 3, 4],
        working_hours_start="08:00",
        working_hours_end="17:00",
        assigned_areas=areas or [],
        assigned_regions=regions or [],
        channel_skills=channel_skills or [],
        start_location_lat=start_lat,
        start_location_lng=start_lng,
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


def test_unpinned_customer_assigned_to_eligible_salesman() -> None:
    # Customer in area North; salesman A covers North, salesman B covers South.
    # Only A is area-eligible → C must go to A.
    c1 = _cust(1, area="North")
    a = _salesman(1, areas=["North"])
    b = _salesman(2, areas=["South"])

    resp = run_assign_salesmen(
        AssignSalesmenRequest(
            customers=[c1],
            salesmen=[a, b],
            matrix_cells=_uniform_matrix([1]),
        )
    )

    assert len(resp.assignments) == 1
    assert resp.assignments[0].customer_id == 1
    assert resp.assignments[0].salesman_id == a.id
    assert resp.unassignable == []


def test_pinned_customer_overrides_area() -> None:
    # Customer in area North, pinned to salesman B (who covers South only).
    # Pin must win — assignment goes to B despite the area mismatch.
    c1 = _cust(1, area="North", pinned=2)
    a = _salesman(1, areas=["North"])
    b = _salesman(2, areas=["South"])

    resp = run_assign_salesmen(
        AssignSalesmenRequest(
            customers=[c1],
            salesmen=[a, b],
            matrix_cells=_uniform_matrix([1]),
        )
    )

    assert len(resp.assignments) == 1
    assert resp.assignments[0].salesman_id == b.id


def test_workload_balance_when_travel_equal() -> None:
    # 10 identical customers, 2 identical salesmen, uniform matrix → both
    # salesmen must end up with 5 each (balance is the only differentiator).
    # Customers are spaced ~1.5 km apart: the default _cust coords step only
    # ~150 m, which the 2026-06-10 co-assignment rule correctly treats as
    # atomic micro-blocks — that would make a perfect 5/5 split impossible
    # and is tested separately in the coassignment tests below.
    customers = [
        _cust(i, area="X", facetime=60, freq=1, lat=23.5 + i * 0.01, lng=58.5)
        for i in range(1, 11)
    ]
    salesmen = [_salesman(101, areas=["X"]), _salesman(102, areas=["X"])]

    resp = run_assign_salesmen(
        AssignSalesmenRequest(
            customers=customers,
            salesmen=salesmen,
            matrix_cells=_uniform_matrix([c.id for c in customers]),
        )
    )

    per = resp.per_salesman_load_minutes
    # Each customer contributes 60 min * 1 freq = 60 min. Total = 600. Target = 300.
    # With LAMBDA=1 and uniform travel costs, balance dominates -> 5/5 split.
    assert per[101] == 300
    assert per[102] == 300
    assert resp.target_load_minutes == 300


def test_no_eligible_salesman_reported_unassignable() -> None:
    # Customer in area "Mars" — no salesman covers it. Goes to unassignable.
    c1 = _cust(1, area="Mars")
    a = _salesman(1, areas=["Earth"])

    resp = run_assign_salesmen(
        AssignSalesmenRequest(
            customers=[c1],
            salesmen=[a],
            matrix_cells=_uniform_matrix([1]),
        )
    )

    assert resp.assignments == []
    assert len(resp.unassignable) == 1
    assert resp.unassignable[0].customer_id == 1
    assert resp.unassignable[0].reason == "no_eligible_salesman"


def test_medoid_iteration_converges() -> None:
    # 4 customers cluster into 2 spatial pairs: (1,2) close together, (3,4)
    # close together but far from (1,2). Two salesmen, both area-eligible,
    # with start_locations biased so that iter-0 seeds are NOT the eventual
    # medoids — iter 1+ must improve the assignment.
    c1 = _cust(1, lat=23.50, lng=58.50)
    c2 = _cust(2, lat=23.51, lng=58.51)
    c3 = _cust(3, lat=24.00, lng=58.50)
    c4 = _cust(4, lat=24.01, lng=58.51)
    # Both salesmen seeded at midpoint — bad seed for both, iter 1+ should split.
    a = _salesman(101, start_lat=23.75, start_lng=58.50)
    b = _salesman(102, start_lat=23.76, start_lng=58.50)
    # Custom matrix: within-pair short, cross-pair long.
    cells: list[MatrixCell] = []
    for x in [1, 2, 3, 4]:
        for y in [1, 2, 3, 4]:
            if x == y:
                continue
            same_pair = (x in (1, 2) and y in (1, 2)) or (x in (3, 4) and y in (3, 4))
            cells.append(
                MatrixCell(
                    origin_index=x,
                    dest_index=y,
                    duration_seconds=60 if same_pair else 3600,
                    distance_meters=1000 if same_pair else 60000,
                    status="ok",
                )
            )

    resp = run_assign_salesmen(
        AssignSalesmenRequest(
            customers=[c1, c2, c3, c4],
            salesmen=[a, b],
            matrix_cells=cells,
        )
    )

    # The pairs must end up on the same salesman as their close peer — that's
    # the whole point of the medoid iteration. We don't fix WHICH salesman gets
    # WHICH pair (symmetric problem), just that pairs stay together.
    pair_a = {x.salesman_id for x in resp.assignments if x.customer_id in (1, 2)}
    pair_b = {x.salesman_id for x in resp.assignments if x.customer_id in (3, 4)}
    assert len(pair_a) == 1, f"customers 1,2 split across salesmen: {pair_a}"
    assert len(pair_b) == 1, f"customers 3,4 split across salesmen: {pair_b}"
    assert pair_a != pair_b, "both pairs went to same salesman — balance failed"
    assert resp.medoid_iterations >= 1


def test_balance_lambda_zero_minimizes_only_travel() -> None:
    # With LAMBDA=0 the model should ignore balance entirely. 4 customers,
    # 2 salesmen, both eligible. Salesman A's medoid is near c1+c2, B's medoid
    # is near c3+c4. Without balance, every customer should go to the nearest
    # medoid → A gets {1,2}, B gets {3,4}.
    c1 = _cust(1, lat=23.50, lng=58.50, freq=4)  # heavier — would normally rebalance
    c2 = _cust(2, lat=23.51, lng=58.51, freq=4)
    c3 = _cust(3, lat=24.00, lng=58.50, freq=1)
    c4 = _cust(4, lat=24.01, lng=58.51, freq=1)
    a = _salesman(101, start_lat=23.50, start_lng=58.50)
    b = _salesman(102, start_lat=24.00, start_lng=58.50)

    cells: list[MatrixCell] = []
    for x in [1, 2, 3, 4]:
        for y in [1, 2, 3, 4]:
            if x == y:
                continue
            same_pair = (x in (1, 2) and y in (1, 2)) or (x in (3, 4) and y in (3, 4))
            cells.append(
                MatrixCell(
                    origin_index=x,
                    dest_index=y,
                    duration_seconds=60 if same_pair else 3600,
                    distance_meters=1000 if same_pair else 60000,
                    status="ok",
                )
            )

    resp = run_assign_salesmen(
        AssignSalesmenRequest(
            customers=[c1, c2, c3, c4],
            salesmen=[a, b],
            matrix_cells=cells,
            balance_lambda=0.0,
        )
    )

    by_customer = {a.customer_id: a.salesman_id for a in resp.assignments}
    # c1 and c2 should land on the same salesman; c3 and c4 likewise — purely
    # by minimising travel-time to their closer medoid.
    assert by_customer[1] == by_customer[2]
    assert by_customer[3] == by_customer[4]
    assert by_customer[1] != by_customer[3]


def test_region_match_routes_subarea_customers_to_region_salesman() -> None:
    # Phase 9: sub-wilayat-tagged customers route to the region salesman whose
    # assigned_regions covers them, even when assigned_areas wouldn't match the
    # narrow area string. Mirrors the floater pattern that motivated Phase 9.
    customers = [
        _cust(1, area="Dhank", region="Nizwa"),
        _cust(2, area="Ibri", region="Nizwa"),
        _cust(3, area="Mirbat", region="Salalah"),
        _cust(4, area="Awqat", region="Salalah"),
    ]
    nizwa = _salesman(100, regions=["Nizwa"])
    salalah = _salesman(200, regions=["Salalah"])

    resp = run_assign_salesmen(
        AssignSalesmenRequest(
            customers=customers,
            salesmen=[nizwa, salalah],
            matrix_cells=_uniform_matrix([c.id for c in customers]),
        )
    )

    by = {a.customer_id: a.salesman_id for a in resp.assignments}
    assert by[1] == nizwa.id and by[2] == nizwa.id
    assert by[3] == salalah.id and by[4] == salalah.id
    assert resp.unassignable == []


def test_region_sticky_when_two_salesmen_cover_same_region() -> None:
    # Two Nizwa-region salesmen. Five region-eligible customers (sub-wilayat
    # tagged) must each get pinned to exactly one of them — sticky to the
    # assignment that wins, no cross-salesman flex. The output is what Phase 7b
    # later hardpins into the VRP, so verifying single-assignment per customer
    # is sufficient.
    customers = [
        _cust(i, area=area, region="Nizwa")
        for i, area in enumerate(
            ["Dhank", "Ibri", "Bahla", "Manah", "Adam"], start=1
        )
    ]
    nizwa_a = _salesman(100, regions=["Nizwa"], start_lat=23.5, start_lng=58.5)
    nizwa_b = _salesman(101, regions=["Nizwa"], start_lat=23.6, start_lng=58.6)

    resp = run_assign_salesmen(
        AssignSalesmenRequest(
            customers=customers,
            salesmen=[nizwa_a, nizwa_b],
            matrix_cells=_uniform_matrix([c.id for c in customers]),
        )
    )

    # Each customer assigned exactly once to one of the two region salesmen.
    assigned_ids = [a.customer_id for a in resp.assignments]
    assert sorted(assigned_ids) == [c.id for c in customers]
    sals = {a.customer_id: a.salesman_id for a in resp.assignments}
    assert all(sid in (nizwa_a.id, nizwa_b.id) for sid in sals.values())
    assert resp.unassignable == []


def test_channel_skill_routes_mt_to_mt_skilled_salesman() -> None:
    # 2 MT + 2 TT customers, two salesmen each with one channel skill.
    # MT customers must go to the MT-skilled salesman, TT to the TT-skilled.
    customers = [
        _cust(1, channel="MT"),
        _cust(2, channel="MT"),
        _cust(3, channel="TT"),
        _cust(4, channel="TT"),
    ]
    mt = _salesman(101, channel_skills=["MT"])
    tt = _salesman(102, channel_skills=["TT"])

    resp = run_assign_salesmen(
        AssignSalesmenRequest(
            customers=customers,
            salesmen=[mt, tt],
            matrix_cells=_uniform_matrix([c.id for c in customers]),
        )
    )

    by = {a.customer_id: a.salesman_id for a in resp.assignments}
    assert by[1] == mt.id and by[2] == mt.id
    assert by[3] == tt.id and by[4] == tt.id
    assert resp.unassignable == []


def test_capacity_overflow_assigns_everyone() -> None:
    # Phase 11: inverted contract — capacity overflow no longer drops customers.
    # One salesman, default 5d * 9h capacity ≈ 8308 min/month. 50 low-freq
    # customers * 200 min = 10,000 min total — over capacity by ~1700 min. The
    # solver MUST still assign every customer; the overflow is absorbed by
    # cap_slack and shows up as per_salesman_load > capacity.
    customers = [_cust(i, facetime=200, freq=1) for i in range(1, 51)]
    salesmen = [_salesman(1)]

    resp = run_assign_salesmen(
        AssignSalesmenRequest(
            customers=customers,
            salesmen=salesmen,
            matrix_cells=_uniform_matrix([c.id for c in customers]),
        )
    )

    assigned_ids = {a.customer_id for a in resp.assignments}
    assert assigned_ids == {c.id for c in customers}, (
        "every customer must be assigned — Phase 11 no-drop policy"
    )
    # No customer surfaces as "capacity_overflow" (that reason no longer exists).
    assert all(u.reason != "capacity_overflow" for u in resp.unassignable)
    # The load is past the salesman's capacity envelope — that's the whole point;
    # the red-chip code in the renderer is what surfaces this to the user.
    # Default capacity ≈ 8307; we should be over it.
    assert resp.per_salesman_load_minutes[1] > 8307


def test_no_drops_even_with_mixed_freq_overflow() -> None:
    # Mix of high-freq and low-freq customers, total load past capacity. Every
    # single customer must still be assigned — no freq-based drop policy any
    # more (Phase 11). Both groups land on the only salesman; cap_slack absorbs.
    high_freq = [_cust(i, facetime=90, freq=8) for i in range(1, 11)]
    low_freq = [_cust(i, facetime=200, freq=1) for i in range(11, 17)]
    customers = high_freq + low_freq
    salesmen = [_salesman(1)]

    resp = run_assign_salesmen(
        AssignSalesmenRequest(
            customers=customers,
            salesmen=salesmen,
            matrix_cells=_uniform_matrix([c.id for c in customers]),
        )
    )

    assigned_ids = {a.customer_id for a in resp.assignments}
    assert assigned_ids == {c.id for c in customers}, (
        "no customer should be dropped regardless of freq"
    )
    assert resp.unassignable == []


def test_overflow_distributes_evenly_across_eligible_salesmen() -> None:
    # 40 freq=2 customers, facetime=120, total load = 40 * 120 * 2 = 9600 min.
    # Two same-area salesmen, each capped at ~8307 min — so the team is
    # under-capacity by ~1000 min. With drops removed, balance objective must
    # spread the overflow ≈evenly rather than pile everything on one salesman.
    customers = [_cust(i, facetime=120, freq=2, area="X") for i in range(1, 41)]
    a = _salesman(101, areas=["X"], start_lat=23.5, start_lng=58.5)
    b = _salesman(102, areas=["X"], start_lat=23.5, start_lng=58.5)

    resp = run_assign_salesmen(
        AssignSalesmenRequest(
            customers=customers,
            salesmen=[a, b],
            matrix_cells=_uniform_matrix([c.id for c in customers]),
        )
    )

    # All 40 assigned, none unassignable.
    assert len(resp.assignments) == 40
    assert resp.unassignable == []
    # Each salesman gets close to half the customers (40/2 = 20). With uniform
    # matrix and balance_lambda=1.0, count split should be tight — allow ±2
    # slack for CP-SAT tie-breaking.
    by_salesman: dict[int, int] = {a.id: 0, b.id: 0}
    for asg in resp.assignments:
        by_salesman[asg.salesman_id] += 1
    delta = abs(by_salesman[a.id] - by_salesman[b.id])
    assert delta <= 2, (
        f"overflow not evenly distributed: a={by_salesman[a.id]} "
        f"b={by_salesman[b.id]} (delta={delta})"
    )


def test_coassignment_keeps_close_customers_on_one_salesman() -> None:
    # Three customers stacked within ~100 m, two identical catch-all salesmen.
    # Pure balance would split them 2/1 (slack 60 beats slack 180); the
    # co-assignment micro-cluster constraint must force all three onto ONE
    # salesman anyway — splitting a block between reps is never acceptable.
    customers = [
        _cust(1, lat=23.5000, lng=58.5000, facetime=60),
        _cust(2, lat=23.5005, lng=58.5000, facetime=60),
        _cust(3, lat=23.5000, lng=58.5005, facetime=60),
    ]
    a = _salesman(101, start_lat=23.5, start_lng=58.5)
    b = _salesman(102, start_lat=23.5, start_lng=58.5)
    resp = run_assign_salesmen(
        AssignSalesmenRequest(
            customers=customers,
            salesmen=[a, b],
            matrix_cells=_uniform_matrix([c.id for c in customers], seconds=60),
        )
    )
    assert len(resp.assignments) == 3
    owners = {asg.salesman_id for asg in resp.assignments}
    assert len(owners) == 1, (
        f"micro-cluster split across salesmen: "
        f"{[(asg.customer_id, asg.salesman_id) for asg in resp.assignments]}"
    )


def test_coassignment_does_not_tie_across_channel_boundaries() -> None:
    # Two customers 50 m apart but in different channels, served by two
    # channel-restricted salesmen. The eligibility-fingerprint guard must skip
    # the merge (tying them would be infeasible) and each customer must land
    # on their own channel's salesman.
    customers = [
        _cust(1, lat=23.5000, lng=58.5000, channel="MT"),
        _cust(2, lat=23.5004, lng=58.5000, channel="TT"),
    ]
    mt = _salesman(101, channel_skills=["MT"])
    tt = _salesman(102, channel_skills=["TT"])
    resp = run_assign_salesmen(
        AssignSalesmenRequest(
            customers=customers,
            salesmen=[mt, tt],
            matrix_cells=_uniform_matrix([1, 2], seconds=60),
        )
    )
    assert resp.unassignable == []
    by_cust = {asg.customer_id: asg.salesman_id for asg in resp.assignments}
    assert by_cust[1] == 101
    assert by_cust[2] == 102


def test_coassignment_respects_group_bounds() -> None:
    # A 30-customer chain (each ~55 m from the next) spans ~1.6 km — far past
    # the 500 m diameter cap, so it must break into bounded blocks instead of
    # welding into one atomic group. The solve still assigns everyone.
    customers = [
        _cust(i, lat=23.5 + i * 0.0005, lng=58.5, facetime=60) for i in range(1, 31)
    ]
    a = _salesman(101, start_lat=23.5, start_lng=58.5)
    b = _salesman(102, start_lat=23.52, start_lng=58.5)
    resp = run_assign_salesmen(
        AssignSalesmenRequest(
            customers=customers,
            salesmen=[a, b],
            matrix_cells=_uniform_matrix([c.id for c in customers], seconds=60),
        )
    )
    assert len(resp.assignments) == 30
    assert resp.unassignable == []
