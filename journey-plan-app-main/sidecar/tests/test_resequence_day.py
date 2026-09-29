"""Single-day re-sequencer fixtures."""

from __future__ import annotations

from sidecar.models import (
    MatrixCell,
    OptimizeCustomer,
    OptimizeSalesman,
    ResequenceDayRequest,
)
from sidecar.solver import resequence_day


def _cust(
    cid: int,
    *,
    facetime: int = 30,
    area: str | None = None,
    pinned: int | None = None,
    allowed_days: list[int] | None = None,
) -> OptimizeCustomer:
    return OptimizeCustomer(
        id=cid,
        lat=23.5 + cid * 0.001,
        lng=58.5 + cid * 0.001,
        facetime_minutes=facetime,
        monthly_frequency=1,
        allowed_days=allowed_days,
        pinned_salesman_id=pinned,
        area=area,
    )


def _salesman(sid: int, *, areas: list[str] | None = None) -> OptimizeSalesman:
    return OptimizeSalesman(
        id=sid,
        working_days=[0, 1, 2, 3, 4],
        working_hours_start="08:00",
        working_hours_end="17:00",
        assigned_areas=areas or [],
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


# 2026-06-01 is a Monday (our weekday code: 1).
WEEKDAY_DATE = "2026-06-01"


def test_empty_customers_returns_empty_success() -> None:
    response = resequence_day(
        ResequenceDayRequest(
            salesman=_salesman(1), customers=[], matrix_cells=[], date=WEEKDAY_DATE
        )
    )
    assert response.visits == []
    assert response.unassigned_customers == []
    assert response.solver_status == "success"


def test_single_customer_gets_sequence_one_at_working_hours_start() -> None:
    response = resequence_day(
        ResequenceDayRequest(
            salesman=_salesman(1),
            customers=[_cust(1, facetime=30)],
            matrix_cells=[],
            date=WEEKDAY_DATE,
        )
    )
    assert len(response.visits) == 1
    visit = response.visits[0]
    assert visit.customer_id == 1
    assert visit.salesman_id == 1
    assert visit.sequence == 1
    assert visit.scheduled_start_time == "08:00"
    assert visit.drive_minutes_to == 0


def test_five_customers_all_fit_and_drive_minutes_recorded() -> None:
    customers = [_cust(i, facetime=20) for i in range(1, 6)]
    response = resequence_day(
        ResequenceDayRequest(
            salesman=_salesman(1),
            customers=customers,
            matrix_cells=_uniform_matrix([c.id for c in customers], seconds=300),
            date=WEEKDAY_DATE,
        )
    )
    assert response.solver_status == "success"
    assert len(response.visits) == 5
    assert {v.sequence for v in response.visits} == {1, 2, 3, 4, 5}
    # First visit always has drive_minutes_to == 0 (starts at the customer site).
    seq_one = next(v for v in response.visits if v.sequence == 1)
    assert seq_one.drive_minutes_to == 0
    # Subsequent visits should have ~5 minutes of drive each (300s rounded).
    others = [v for v in response.visits if v.sequence > 1]
    assert all(v.drive_minutes_to == 5 for v in others)


def test_customer_with_wrong_allowed_day_is_dropped() -> None:
    # 2026-06-01 is a Monday (code 1); restrict customer to Wed (code 3).
    customers = [_cust(1, facetime=30, allowed_days=[3])]
    response = resequence_day(
        ResequenceDayRequest(
            salesman=_salesman(1),
            customers=customers,
            matrix_cells=[],
            date=WEEKDAY_DATE,
        )
    )
    assert response.visits == []
    assert len(response.unassigned_customers) == 1
    assert response.unassigned_customers[0].customer_id == 1
    assert "allowed_days" in response.unassigned_customers[0].reason


def test_pinned_to_different_salesman_is_dropped() -> None:
    customers = [_cust(1, facetime=30, pinned=99)]
    response = resequence_day(
        ResequenceDayRequest(
            salesman=_salesman(1),
            customers=customers,
            matrix_cells=[],
            date=WEEKDAY_DATE,
        )
    )
    assert response.visits == []
    assert response.unassigned_customers[0].customer_id == 1
    assert "area/pin" in response.unassigned_customers[0].reason
