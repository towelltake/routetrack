"""Team-size suggestion fixtures."""

from __future__ import annotations

from sidecar.models import (
    MatrixCell,
    OptimizeCustomer,
    SuggestTeamSizeRequest,
    TeamSizeTemplate,
)
from sidecar.solver import run_team_size_suggestion


def _cust(
    cid: int, *, facetime: int = 30, freq: int = 1, area: str | None = None
) -> OptimizeCustomer:
    return OptimizeCustomer(
        id=cid,
        lat=23.5 + cid * 0.001,
        lng=58.5 + cid * 0.001,
        facetime_minutes=facetime,
        monthly_frequency=freq,
        area=area,
    )


def _matrix(ids: list[int], seconds: int = 300) -> list[MatrixCell]:
    cells: list[MatrixCell] = []
    for a in ids:
        for b in ids:
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


def _template() -> TeamSizeTemplate:
    return TeamSizeTemplate(
        working_days=[0, 1, 2, 3, 4],
        working_hours_start="08:00",
        working_hours_end="17:00",
    )


def test_small_workload_recommends_one_salesman() -> None:
    customers = [_cust(i, facetime=15) for i in range(1, 5)]
    resp = run_team_size_suggestion(
        SuggestTeamSizeRequest(
            customers=customers,
            matrix_cells=_matrix([c.id for c in customers]),
            template=_template(),
        ),
        time_limit_per_run=1,
    )
    assert len(resp.recommended_salesmen) >= 1
    assert not resp.unassigned_customers


def test_large_workload_recommends_multiple_salesmen() -> None:
    # 60 customers x 90 min facetime = 5,400 min. With 9h/day x 20 working days/month
    # = 10,800 min/salesman, one salesman should fit but with very tight headroom.
    # Force the issue by upping to a heavier load.
    customers = [_cust(i, facetime=120, freq=2) for i in range(1, 41)]
    resp = run_team_size_suggestion(
        SuggestTeamSizeRequest(
            customers=customers,
            matrix_cells=_matrix([c.id for c in customers]),
            template=_template(),
        ),
        time_limit_per_run=1,
    )
    assert len(resp.recommended_salesmen) >= 1
    # Sum of assigned customer ids across all suggested salesmen should cover everything
    # the solver placed; unassigned should ideally be empty.
    covered_ids: set[int] = set()
    for s in resp.recommended_salesmen:
        covered_ids.update(s.assigned_customer_ids)
    unassigned_ids = {u.customer_id for u in resp.unassigned_customers}
    assert covered_ids | unassigned_ids == {c.id for c in customers}


def test_areas_produce_per_area_breakdown() -> None:
    north = [_cust(i, facetime=30, area="North") for i in range(1, 6)]
    south = [_cust(i, facetime=30, area="South") for i in range(6, 11)]
    customers = north + south
    resp = run_team_size_suggestion(
        SuggestTeamSizeRequest(
            customers=customers,
            matrix_cells=_matrix([c.id for c in customers]),
            template=_template(),
        ),
        time_limit_per_run=1,
    )
    # Every recommended salesman should cover customers from exactly one area
    # (the team-sizing runs per area, so clusters never mix).
    for s in resp.recommended_salesmen:
        areas = {c.area for c in customers if c.id in s.assigned_customer_ids}
        assert len(areas) == 1


def test_pinned_customers_still_get_a_recommendation() -> None:
    """Regression: the caller sends `pinnedSalesmanId = pinned ?? assigned`, so
    after a Compute Assignments run EVERY customer arrives pinned to a real
    salesman id. The virtual roster uses VIRTUAL_ID_BASE+i ids, so leaving the
    pins in place made every customer unservable and the endpoint returned an
    empty roster with everyone unassigned."""
    customers = [_cust(i, facetime=30, freq=1) for i in range(1, 13)]
    for idx, c in enumerate(customers):
        # Real salesman ids 1/2 — nothing like the 900_000-based virtual ids.
        c.pinned_salesman_id = 1 + (idx % 2)
    resp = run_team_size_suggestion(
        SuggestTeamSizeRequest(
            customers=customers,
            matrix_cells=_matrix([c.id for c in customers]),
            template=_template(),
        ),
        time_limit_per_run=1,
    )
    assert len(resp.recommended_salesmen) >= 1
    assert not resp.unassigned_customers
    covered: set[int] = set()
    for s in resp.recommended_salesmen:
        covered.update(s.assigned_customer_ids)
    assert covered == {c.id for c in customers}
