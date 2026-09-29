"""Pydantic models — source of truth for the wire format.

When fields change here, mirror the change in `shared/src/index.ts` until
Phase 5 introduces code generation.
"""

from __future__ import annotations

from typing import Literal

from pydantic import BaseModel, ConfigDict, Field


class _ApiModel(BaseModel):
    model_config = ConfigDict(populate_by_name=True)


class HealthResponse(_ApiModel):
    status: Literal["ok", "starting", "error"]
    version: str
    sidecar_pid: int = Field(alias="sidecarPid")


# ---- Matrix (POST /matrix/batch) — backed by OSRM ----


MatrixCellStatus = Literal["ok", "zero_results", "not_found", "error"]


class MatrixPoint(_ApiModel):
    lat: float
    lng: float


class MatrixBatchRequest(_ApiModel):
    origins: list[MatrixPoint]
    destinations: list[MatrixPoint]


class MatrixCell(_ApiModel):
    origin_index: int = Field(alias="originIndex")
    dest_index: int = Field(alias="destIndex")
    duration_seconds: int | None = Field(default=None, alias="durationSeconds")
    distance_meters: int | None = Field(default=None, alias="distanceMeters")
    status: MatrixCellStatus


class MatrixBatchResponse(_ApiModel):
    cells: list[MatrixCell]


# ---- Optimizer (POST /optimize) ----


SolverStatus = Literal["success", "partial", "infeasible", "timeout", "error"]


class OptimizeCustomer(_ApiModel):
    id: int
    lat: float
    lng: float
    facetime_minutes: int = Field(alias="facetimeMinutes")
    monthly_frequency: int = Field(alias="monthlyFrequency")
    allowed_days: list[int] | None = Field(default=None, alias="allowedDays")
    pinned_salesman_id: int | None = Field(default=None, alias="pinnedSalesmanId")
    area: str | None = None
    # Phase 9: broader free-text region (e.g. "Nizwa") that rolls up sub-areas
    # (e.g. "Dhank", "Ibri"). Used as a second eligibility dimension alongside
    # `area`; satisfying EITHER on the salesman's side is enough.
    region: str | None = None
    # Phase 8: FMCG channel — "MT" (Modern Trade), "TT" (Traditional Trade), or
    # "WS" (Wholesale).
    # None = no channel restriction (eligible for any salesman regardless of skills).
    channel: str | None = None
    # Optional visit time-of-day window ("HH:MM" wall clock), e.g. MT receiving
    # hours. Soft constraint: visits outside the window are penalised like
    # working-window overflow, never dropped. Both must be set for the window
    # to apply; None = any time of day.
    visit_window_start: str | None = Field(default=None, alias="visitWindowStart")
    visit_window_end: str | None = Field(default=None, alias="visitWindowEnd")


class OptimizeSalesman(_ApiModel):
    id: int
    working_days: list[int] = Field(alias="workingDays")
    working_hours_start: str = Field(alias="workingHoursStart")
    working_hours_end: str = Field(alias="workingHoursEnd")
    assigned_areas: list[str] = Field(default_factory=list, alias="assignedAreas")
    # Phase 9: parallel region coverage list. Mirrors assigned_areas's shape.
    # Customer is eligible if EITHER customer.area is in assigned_areas OR
    # customer.region is in assigned_regions. Both empty on the salesman side
    # means no area/region restriction (still subject to channel + pin).
    assigned_regions: list[str] = Field(default_factory=list, alias="assignedRegions")
    # Phase 8: channel skills (subset of {"MT","TT","WS"}). Empty = no channel
    # restriction, mirrors assigned_areas's empty-list "catch-all" semantics.
    channel_skills: list[str] = Field(default_factory=list, alias="channelSkills")
    # Used by /assign-salesmen for iter-0 medoid seeding, and by the VRP when
    # include_commute is on (commute legs from/to this point). Optional so
    # existing tests/callers that don't supply them keep working.
    start_location_lat: float | None = Field(default=None, alias="startLocationLat")
    start_location_lng: float | None = Field(default=None, alias="startLocationLng")
    # Opt-in (2026-06-10): when True AND start_location is set, the VRP prices
    # and times the home→first and last→home legs instead of treating the day
    # as starting at the first customer. Default False = legacy dummy-depot
    # behavior, identical schedules to before.
    include_commute: bool = Field(default=False, alias="includeCommute")


class OptimizeRequest(_ApiModel):
    period_start: str = Field(alias="periodStart")  # ISO date
    period_end: str = Field(alias="periodEnd")
    salesmen: list[OptimizeSalesman]
    customers: list[OptimizeCustomer]
    matrix_cells: list[MatrixCell] = Field(alias="matrixCells")


class OptimizeVisit(_ApiModel):
    customer_id: int = Field(alias="customerId")
    salesman_id: int = Field(alias="salesmanId")
    scheduled_date: str = Field(alias="scheduledDate")  # ISO date
    scheduled_start_time: str = Field(alias="scheduledStartTime")  # HH:MM
    sequence: int
    drive_minutes_to: int = Field(alias="driveMinutesTo")


class UnassignedCustomer(_ApiModel):
    customer_id: int = Field(alias="customerId")
    reason: str


class OptimizeResponse(_ApiModel):
    visits: list[OptimizeVisit]
    unassigned_customers: list[UnassignedCustomer] = Field(alias="unassignedCustomers")
    objective_value: float = Field(alias="objectiveValue")
    runtime_seconds: float = Field(alias="runtimeSeconds")
    solver_status: SolverStatus = Field(alias="solverStatus")


# ---- Team-size suggestion (POST /optimize/suggest-team-size) ----


class TeamSizeTemplate(_ApiModel):
    working_days: list[int] = Field(alias="workingDays")
    working_hours_start: str = Field(alias="workingHoursStart")
    working_hours_end: str = Field(alias="workingHoursEnd")
    max_customers_per_day: int | None = Field(default=None, alias="maxCustomersPerDay")
    # Grow the team until average utilization has fallen to AT OR BELOW this
    # threshold. Utilization decreases as the team grows, so a lower target
    # buys more salesmen at lighter load and a higher target fewer at heavier
    # load. 0-100; 0 disables (returns the arithmetic lower bound).
    target_utilization_pct: int = Field(default=85, alias="targetUtilizationPct")


class SuggestTeamSizeRequest(_ApiModel):
    customers: list[OptimizeCustomer]
    matrix_cells: list[MatrixCell] = Field(alias="matrixCells")
    template: TeamSizeTemplate


class SuggestedSalesman(_ApiModel):
    index: int
    assigned_customer_ids: list[int] = Field(alias="assignedCustomerIds")
    suggested_area_label: str = Field(alias="suggestedAreaLabel")
    suggested_home_lat: float = Field(alias="suggestedHomeLat")
    suggested_home_lng: float = Field(alias="suggestedHomeLng")


class SuggestTeamSizeResponse(_ApiModel):
    recommended_salesmen: list[SuggestedSalesman] = Field(alias="recommendedSalesmen")
    unassigned_customers: list[UnassignedCustomer] = Field(alias="unassignedCustomers")
    runtime_seconds: float = Field(alias="runtimeSeconds")
    solver_status: SolverStatus = Field(alias="solverStatus")


# ---- Waypoints input (POST /route/osrm) ----


class DirectionsRequest(_ApiModel):
    waypoints: list[MatrixPoint]


# ---- OSRM route (POST /route/osrm) — sole polyline source ----


class OsrmRouteResponse(_ApiModel):
    coordinates: list[MatrixPoint]
    total_distance_meters: int = Field(alias="totalDistanceMeters")
    total_duration_seconds: int = Field(alias="totalDurationSeconds")


# ---- Single-day re-sequencer (POST /optimize/resequence-day) ----


class ResequenceDayRequest(_ApiModel):
    salesman: OptimizeSalesman
    customers: list[OptimizeCustomer]
    matrix_cells: list[MatrixCell] = Field(alias="matrixCells")
    date: str  # ISO date


class ResequenceDayResponse(_ApiModel):
    visits: list[OptimizeVisit]
    unassigned_customers: list[UnassignedCustomer] = Field(alias="unassignedCustomers")
    solver_status: SolverStatus = Field(alias="solverStatus")
    runtime_seconds: float = Field(alias="runtimeSeconds")


# ---- Salesman assignment (POST /assign-salesmen) — Phase 7a ----
#
# Computes a customer → salesman mapping before the per-week VRP runs. The
# algorithm is constrained k-medoids on the OSRM travel-time matrix, balanced
# by workload, with pinned customers fixed as hard constraints.


class AssignSalesmenRequest(_ApiModel):
    customers: list[OptimizeCustomer]
    salesmen: list[OptimizeSalesman]
    matrix_cells: list[MatrixCell] = Field(alias="matrixCells")
    balance_lambda: float = Field(default=3.0, alias="balanceLambda")


class CustomerAssignment(_ApiModel):
    customer_id: int = Field(alias="customerId")
    salesman_id: int = Field(alias="salesmanId")
    travel_time_seconds: int = Field(alias="travelTimeSeconds")


class UnassignableCustomer(_ApiModel):
    customer_id: int = Field(alias="customerId")
    # 'no_eligible_salesman' | 'no_area' | 'no_coordinates'
    reason: str


class AssignSalesmenResponse(_ApiModel):
    assignments: list[CustomerAssignment]
    unassignable: list[UnassignableCustomer]
    per_salesman_load_minutes: dict[int, int] = Field(alias="perSalesmanLoadMinutes")
    target_load_minutes: int = Field(alias="targetLoadMinutes")
    runtime_seconds: float = Field(alias="runtimeSeconds")
    solver_status: SolverStatus = Field(alias="solverStatus")
    medoid_iterations: int = Field(alias="medoidIterations")
