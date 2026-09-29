"""Single-day re-sequencer.

Used after a manual drag-to-reassign: takes a fixed `(salesman, date)` plus the
customers that should be visited that day, and returns the optimal visit
sequence within the salesman's working-hour window.

Differs from `vrp.solve_week`:
- one vehicle (not salesman x working-days), so no eligibility loop across
  vehicles — eligibility is asserted up-front and reflected in `unassigned`.
- one fixed date, so allowed-days check is a single boolean per customer.
- shorter time limit (2s) since the problem is tiny.
"""

from __future__ import annotations

import time
from datetime import date

from ortools.constraint_solver import pywrapcp, routing_enums_pb2  # type: ignore[import-untyped]

from sidecar.models import (
    MatrixCell,
    OptimizeCustomer,
    OptimizeVisit,
    ResequenceDayRequest,
    ResequenceDayResponse,
    SolverStatus,
    UnassignedCustomer,
)
from sidecar.solver.vrp import (
    _MAX_OVERFLOW_MINUTES,
    _OVERFLOW_PENALTY_PER_MIN,
    _drop_penalty_for,
    _format_hhmm,
    _our_dow,
    _parse_hhmm,
    commute_drive_minutes,
    customer_eligible_for_salesman,
    salesman_commutes,
    visit_window_minutes,
)


def _matrix_to_dict(cells: list[MatrixCell]) -> dict[tuple[int, int], int]:
    out: dict[tuple[int, int], int] = {}
    for c in cells:
        if c.status == "ok" and c.duration_seconds is not None:
            out[(c.origin_index, c.dest_index)] = c.duration_seconds
    return out


def resequence_day(
    request: ResequenceDayRequest,
    time_limit_seconds: int = 2,
) -> ResequenceDayResponse:
    start_wall = time.monotonic()
    salesman = request.salesman
    day = date.fromisoformat(request.date)
    matrix_seconds = _matrix_to_dict(request.matrix_cells)

    # Pre-screen customers for hard ineligibility so the solver never sees them.
    eligible: list[OptimizeCustomer] = []
    unassigned: list[UnassignedCustomer] = []
    for c in request.customers:
        if not customer_eligible_for_salesman(c, salesman):
            unassigned.append(
                UnassignedCustomer(
                    customer_id=c.id, reason="not eligible for this salesman (area/pin)"
                )
            )
            continue
        if c.allowed_days is not None and _our_dow(day) not in c.allowed_days:
            unassigned.append(
                UnassignedCustomer(
                    customer_id=c.id, reason="weekday not in allowed_days"
                )
            )
            continue
        eligible.append(c)

    if not eligible:
        return ResequenceDayResponse(
            visits=[],
            unassigned_customers=unassigned,
            solver_status="infeasible" if request.customers else "success",
            runtime_seconds=round(time.monotonic() - start_wall, 3),
        )

    # Node 0 = dummy depot (zero-cost arcs to/from every customer so the route
    # starts at the first customer and ends at the last — same convention as
    # `solve_week`, locked 2026-05-17).
    n_customers = len(eligible)
    n_nodes = n_customers + 1
    cust_at_node = {i + 1: eligible[i] for i in range(n_customers)}
    node_for_cust = {eligible[i].id: i + 1 for i in range(n_customers)}

    manager = pywrapcp.RoutingIndexManager(n_nodes, 1, [0], [0])
    routing = pywrapcp.RoutingModel(manager)

    commutes = salesman_commutes(salesman)

    def time_callback(from_index: int, to_index: int) -> int:
        from_node = manager.IndexToNode(from_index)
        to_node = manager.IndexToNode(to_index)
        facetime = 0 if from_node == 0 else cust_at_node[from_node].facetime_minutes
        if from_node == 0 and to_node == 0:
            drive_min = 0
        elif from_node == 0:
            drive_min = (
                commute_drive_minutes(
                    matrix_seconds, salesman, cust_at_node[to_node], outbound=True
                )
                if commutes
                else 0
            )
        elif to_node == 0:
            drive_min = (
                commute_drive_minutes(
                    matrix_seconds, salesman, cust_at_node[from_node], outbound=False
                )
                if commutes
                else 0
            )
        else:
            drive_sec = matrix_seconds.get(
                (cust_at_node[from_node].id, cust_at_node[to_node].id), 0
            )
            drive_min = (drive_sec + 30) // 60
        return facetime + drive_min

    transit_idx = routing.RegisterTransitCallback(time_callback)
    routing.SetArcCostEvaluatorOfAllVehicles(transit_idx)

    shift_start = _parse_hhmm(salesman.working_hours_start)
    shift_end = _parse_hhmm(salesman.working_hours_end)
    # Soft cap, same shape as vrp.solve_week — absolute wall-clock cumul
    # (start pinned to shift start) so per-customer visit windows are plain
    # cumul bounds. The user can drag any number of customers onto one day;
    # the solver assigns them all and surfaces overflow via end-time >
    # working_hours_end — the renderer paints those red.
    # slack allows waiting for a visit-window lower bound; the end finalizer
    # squeezes waiting out when no window needs it (mirrors vrp.solve_week).
    routing.AddDimensionWithVehicleCapacity(
        transit_idx,
        _MAX_OVERFLOW_MINUTES,
        [shift_end + _MAX_OVERFLOW_MINUTES],
        False,
        "Time",
    )
    time_dim = routing.GetDimensionOrDie("Time")
    time_dim.CumulVar(routing.Start(0)).SetRange(shift_start, shift_start)
    time_dim.SetCumulVarSoftUpperBound(
        routing.End(0), shift_end, _OVERFLOW_PENALTY_PER_MIN
    )
    routing.AddVariableMinimizedByFinalizer(time_dim.CumulVar(routing.End(0)))

    for cust in eligible:
        node = node_for_cust[cust.id]
        index = manager.NodeToIndex(node)
        window = visit_window_minutes(cust)
        if window is not None:
            win_start, win_end = window
            time_dim.SetCumulVarSoftLowerBound(
                index, win_start, _OVERFLOW_PENALTY_PER_MIN
            )
            time_dim.SetCumulVarSoftUpperBound(
                index, win_end, _OVERFLOW_PENALTY_PER_MIN
            )
        routing.AddDisjunction([index], _drop_penalty_for(cust))

    search = pywrapcp.DefaultRoutingSearchParameters()
    search.first_solution_strategy = (
        routing_enums_pb2.FirstSolutionStrategy.PATH_CHEAPEST_ARC
    )
    search.local_search_metaheuristic = (
        routing_enums_pb2.LocalSearchMetaheuristic.GUIDED_LOCAL_SEARCH
    )
    search.time_limit.seconds = time_limit_seconds

    solution = routing.SolveWithParameters(search)

    visits: list[OptimizeVisit] = []
    if solution is not None:
        time_dim = routing.GetDimensionOrDie("Time")
        idx = solution.Value(routing.NextVar(routing.Start(0)))
        seq = 1
        prev_node = 0
        while not routing.IsEnd(idx):
            node = manager.IndexToNode(idx)
            cust = cust_at_node[node]
            if prev_node == 0:
                drive_min = (
                    commute_drive_minutes(
                        matrix_seconds, salesman, cust, outbound=True
                    )
                    if commutes
                    else 0
                )
            else:
                drive_sec = matrix_seconds.get(
                    (cust_at_node[prev_node].id, cust.id), 0
                )
                drive_min = (drive_sec + 30) // 60
            # Cumul is absolute minutes-of-day (start pinned to shift start).
            accumulated = solution.Value(time_dim.CumulVar(idx))
            visits.append(
                OptimizeVisit(
                    customer_id=cust.id,
                    salesman_id=salesman.id,
                    scheduled_date=day.isoformat(),
                    scheduled_start_time=_format_hhmm(accumulated),
                    sequence=seq,
                    drive_minutes_to=drive_min,
                )
            )
            seq += 1
            prev_node = node
            idx = solution.Value(routing.NextVar(idx))

    assigned_ids = {v.customer_id for v in visits}
    for cust in eligible:
        if cust.id not in assigned_ids:
            # With soft capacity (2026-05-20), an eligible customer should
            # always end up in `visits`. Reaching here means the solver was
            # unable to converge — surface explicitly so the user can report.
            unassigned.append(
                UnassignedCustomer(
                    customer_id=cust.id, reason="solver did not place customer"
                )
            )

    status: SolverStatus
    if not visits and request.customers:
        status = "infeasible"
    elif unassigned:
        status = "partial"
    else:
        status = "success"

    return ResequenceDayResponse(
        visits=visits,
        unassigned_customers=unassigned,
        solver_status=status,
        runtime_seconds=round(time.monotonic() - start_wall, 3),
    )
