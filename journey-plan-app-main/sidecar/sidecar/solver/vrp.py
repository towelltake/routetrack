"""Per-week VRP solver using OR-Tools RoutingModel.

Vehicles are (salesman x working-day-in-this-week). Customers were pre-assigned
to weeks by `cycle_assignment`. The 2026-05-17 decision overrides the MEMORY.md
"round trip" default: salesmen start at the first customer and end at the last
customer — implemented via a dummy depot node with zero-cost arcs to/from every
customer node. The salesman's own start_location is unused by the optimizer.

Hard constraints encoded:
- working-hour minutes per day (Capacity dimension on the time callback).
- `customer.allowed_days` (if set) restricts which day-vehicles can carry it.
- `customer.pinned_salesman_id` (if set) hard-pins to that salesman's vehicles.
- Salesman.assigned_areas / assigned_regions: if BOTH are non-empty the customer
  needs to match either dimension to be eligible (area in assigned_areas OR
  region in assigned_regions). Empty area constraint OR empty region constraint
  on the salesman acts as "no constraint on that dimension". Customer with both
  area=None and region=None is eligible everywhere (still subject to channel + pin).
- Salesman.channel_skills (if non-empty) restricts to customers whose channel
  ("MT"/"TT") is in that list OR has `channel=None`.
"""

from __future__ import annotations

import math
from collections import Counter
from collections.abc import Callable
from datetime import date, timedelta

from ortools.constraint_solver import pywrapcp, routing_enums_pb2  # type: ignore[import-untyped]

from sidecar.models import (
    OptimizeCustomer,
    OptimizeSalesman,
    OptimizeVisit,
    UnassignedCustomer,
)

# 2026-05-20: drops are now reserved for genuinely-impossible cases (no
# eligible vehicle at all — area/channel/pin/allowed_days closes off every
# salesman-day). Capacity overflow is handled via a soft upper bound on the
# Time dimension, not by dropping. So DROP_PENALTY is jacked up to 1e9 to
# make it dominate any conceivable overflow cost. Eligibility-blocked
# customers still surface as unassigned via the AddDisjunction below.
DROP_PENALTY = 1_000_000_000

# Per-minute cost for working-window overflow. Calibration:
#   - Drop cost = 1e9 * freq^2 (1e9 for freq=1, 64e9 for freq=8). Drops are
#     reserved for ineligibility — overflow can never approach this so the
#     solver always prefers overflow to dropping a serviceable customer.
#   - Vehicle fixed cost = 10 (per opened day, see SetFixedCostOfAllVehicles
#     below for the lineage). 30 min of overflow = 6,000. Opening a fresh
#     day costs 10 + ~drive; the solver opens new days when one is available
#     and overflow only when all days are full or geographically irrelevant.
#   - Arc cost = facetime + drive_min (raw minutes). A 30 min visit moved
#     onto an overflowing day adds ~30 facetime + ~10 drive = 40 of arc
#     cost; the overflow penalty piles on another 6,000 — so the solver
#     vigorously avoids overflow when there's slack elsewhere.
#   - 2026-05-24: bumped 100 → 200 after live data showed weeks with red days
#     alongside under-loaded same-week days. Drive cost (1/min) is still 200x
#     cheaper than overflow, so geographic clustering remains the dominant
#     route-quality signal, but the solver now works harder to find spreads
#     that reduce overflow.
_OVERFLOW_PENALTY_PER_MIN = 200

# Hard upper bound on a single day's total time above the soft "end of working
# hours" line. 24 h cap leaves plenty of headroom for pathological inputs
# (e.g. a small team carrying the whole month) without ever bumping into the
# limit; the soft penalty does the actual balancing. The hard cap exists only
# to prevent the solver from looping a single vehicle to infinity in degenerate
# cases. In normal operation, overflow stays in tens of minutes.
_MAX_OVERFLOW_MINUTES = 1440

# Operational cap on visits per (salesman, day). With ~30 min average facetime,
# 12 x 30 = 360 min ≈ 6h field time per day before drive — still well under
# a 540 min window. Pre-2026-05-24 this was a HARD constraint; the solver
# MUST-open-more-days behavior solved one set of plans but broke another:
# when sticky assignment loads a salesman to ~11.5 visits/day across the
# whole cycle (e.g., Muscat #3 with 230 visits / 20 days), a freq=1 customer
# with no spare day to land in got dropped instead of overflowing. Phase 11's
# "100% sticky + no-drop" design explicitly forbids that — overflowing visits
# should land on the assigned salesman and paint red in the calendar, not
# disappear. So 12 is a SOFT upper bound (penalty per visit above 12); the
# hard ceiling that used to sit above it was removed 2026-06-10 (drop cliff —
# see below).
#
# 2026-06-10: 12 is now the FLOOR, not a universal constant. The 12 encoded a
# ~30-min-call (MT-ish) profile; TT beats with 5-10 min calls legitimately run
# 20-40 calls/day, and the fixed cap throttled them artificially (and, worse,
# the hard cap made dense short-call days unservable). Per salesman, the soft
# cap scales UP with the same 360-min field-time envelope:
#   cap(s) = max(12, round(360 / avg_facetime_of_eligible_customers)).
# It never scales DOWN — long-call datasets are already governed by the Time
# dimension, and lowering the cap would churn existing plans for no benefit.
_MAX_VISITS_PER_DAY = 12

# The field-time envelope behind the cap (12 visits x 30 min). Kept as a
# derived constant so the floor and the scaling can't drift apart.
_VISIT_BUDGET_MINUTES = _MAX_VISITS_PER_DAY * 30

# 2026-06-10 (live install regression): the former hard visit ceiling
# (_MAX_OVERFLOW_VISITS = soft+6) is GONE. It was a drop cliff: a roster whose
# assignment demanded >18 visits/day on every working day (Sohar #2, 109
# customers x freq 4 = 436 visits vs 20 days x 18 = 360 slots) made 19
# customers literally unplaceable — the exact "(unexpected with soft
# capacity)" drop class the 12.3 fix was meant to end. The Visits dimension
# is now soft-ONLY, mirroring Time: the soft penalty does the balancing, and
# the Time dimension's hard cap (working window + _MAX_OVERFLOW_MINUTES)
# remains the true physical bound on how much can ever fit in one day.

# Cost per extra visit above _MAX_VISITS_PER_DAY. Calibrated so overflow is
# strongly disfavored vs spare capacity (the solver should always prefer a
# day with <12 visits over piling on a 13th) but trivially cheaper than a
# drop (DROP_PENALTY = 1e9). 5000 ≈ the time-overflow cost of ~50 minutes,
# which is roughly one extra short visit's time cost — so visit-count
# overflow and time-window overflow have comparable weight in the objective.
_VISITS_OVERFLOW_PENALTY = 5000

# Drops are weighted by `monthly_frequency ** 2`. Pre-2026-05-20 this kept
# the solver from dropping high-freq hypermarkets first under hard capacity;
# now that drops are reserved for ineligibility, the freq^2 is mostly cosmetic
# but kept for parity with assign_salesmen.py's drop var.
def _drop_penalty_for(cust: OptimizeCustomer) -> int:
    freq = max(cust.monthly_frequency, 1)
    return DROP_PENALTY * (freq * freq)

# Defense-in-depth: if the matrix is missing a pair (caller bug or sparse fetch),
# fall back to a haversine estimate at this average speed instead of 0 minutes.
# 0 minutes would let the VRP cost-freely interleave faraway customers and
# produce sequences with obvious backtracking. 40 km/h reflects Oman urban-rural
# mix and intentionally overestimates so the solver prefers known-cheap pairs.
_FALLBACK_KMH = 40.0


def _haversine_km(lat1: float, lng1: float, lat2: float, lng2: float) -> float:
    earth_radius_km = 6371.0
    p1 = math.radians(lat1)
    p2 = math.radians(lat2)
    d_lat = p2 - p1
    d_lng = math.radians(lng2 - lng1)
    x = math.sin(d_lat / 2) ** 2 + math.sin(d_lng / 2) ** 2 * math.cos(p1) * math.cos(p2)
    return 2 * earth_radius_km * math.asin(math.sqrt(x))


def _drive_minutes(
    matrix_seconds: dict[tuple[int, int], int],
    a: OptimizeCustomer,
    b: OptimizeCustomer,
) -> int:
    """Drive minutes from a to b. Prefers the matrix cell; falls back
    to a haversine estimate when the cell is missing (rather than 0, which
    would corrupt the route ordering)."""
    sec = matrix_seconds.get((a.id, b.id))
    if sec is not None:
        return (sec + 30) // 60
    km = _haversine_km(a.lat, a.lng, b.lat, b.lng)
    return max(1, round(km / _FALLBACK_KMH * 60))


def salesman_commutes(s: OptimizeSalesman) -> bool:
    return (
        s.include_commute
        and s.start_location_lat is not None
        and s.start_location_lng is not None
    )


def commute_drive_minutes(
    matrix_seconds: dict[tuple[int, int], int],
    s: OptimizeSalesman,
    cust: OptimizeCustomer,
    *,
    outbound: bool,
) -> int:
    """Drive minutes for a commute leg (home→customer when outbound, customer→
    home otherwise). The planner publishes salesman start points in the matrix
    under the pseudo customer id `-salesman_id`; missing cells fall back to the
    same pessimistic haversine estimate as customer pairs."""
    key = (-s.id, cust.id) if outbound else (cust.id, -s.id)
    sec = matrix_seconds.get(key)
    if sec is not None:
        return (sec + 30) // 60
    if s.start_location_lat is None or s.start_location_lng is None:
        return 0
    km = _haversine_km(s.start_location_lat, s.start_location_lng, cust.lat, cust.lng)
    return max(1, round(km / _FALLBACK_KMH * 60))


def _parse_hhmm(s: str) -> int:
    h, m = s.split(":")
    return int(h) * 60 + int(m)


def visit_window_minutes(c: OptimizeCustomer) -> tuple[int, int] | None:
    """Parsed (start, end) of the customer's optional time-of-day window, in
    absolute minutes-of-day. None when unset or malformed — the boundary
    (importer) validates; here a bad value silently means "no window" rather
    than failing a whole solve."""
    if not c.visit_window_start or not c.visit_window_end:
        return None
    try:
        ws = _parse_hhmm(c.visit_window_start)
        we = _parse_hhmm(c.visit_window_end)
    except ValueError:
        return None
    if ws >= we:
        return None
    return ws, we


def _format_hhmm(minutes: int) -> str:
    # Do NOT modulo by 24. Under soft-capacity overflow (2026-05-20), a visit
    # can land past midnight — start_offset + accumulated may exceed 1440. The
    # old `(minutes // 60) % 24` rendered an overflowed 26:00 as "02:00",
    # silently lying about the wall-clock. Returning the unwrapped hour value
    # ("26:00") keeps the format parseable by the renderer's parseHHMM (which
    # just calls Number(h)) and makes the overflow obvious in Excel/tooltips.
    # Normal visits (h < 24) are unchanged.
    h = minutes // 60
    m = minutes % 60
    return f"{h:02d}:{m:02d}"


def _our_dow(d: date) -> int:
    # Python's weekday(): Mon=0..Sun=6. Our convention (CLAUDE.md): Sun=0..Sat=6.
    return (d.weekday() + 1) % 7


def customer_eligible_for_salesman(c: OptimizeCustomer, s: OptimizeSalesman) -> bool:
    """Pin > (area OR region) AND channel.

    Pin takes precedence over everything. Otherwise the area/region dimension
    is satisfied if the salesman has no constraint at all on it OR the customer
    has no tags at all OR at least one of (area, region) matches the salesman's
    corresponding list. The channel dimension is an independent AND on top.
    """
    if c.pinned_salesman_id is not None:
        return c.pinned_salesman_id == s.id

    # Area / region. Both empty on the salesman = no area-or-region constraint.
    has_area_constraint = bool(s.assigned_areas)
    has_region_constraint = bool(s.assigned_regions)
    if has_area_constraint or has_region_constraint:
        c_area = c.area if c.area else None
        c_region = c.region if c.region else None
        # Untagged customer (no area AND no region) is eligible everywhere —
        # matches the pre-Phase-9 behavior for untagged rows.
        if c_area is not None or c_region is not None:
            area_match = (
                has_area_constraint and c_area is not None and c_area in s.assigned_areas
            )
            region_match = (
                has_region_constraint
                and c_region is not None
                and c_region in s.assigned_regions
            )
            if not (area_match or region_match):
                return False

    # Channel: empty channel_skills = catch-all. Customer with channel=None =
    # no channel constraint, eligible for any salesman.
    if (
        s.channel_skills
        and c.channel is not None
        and c.channel != ""
        and c.channel not in s.channel_skills
    ):
        return False
    return True


def _dates_in_week(period_start: date, week_index: int, working_days: set[int]) -> list[date]:
    week_start = period_start + timedelta(days=week_index * 7)
    return [
        week_start + timedelta(days=off)
        for off in range(7)
        if _our_dow(week_start + timedelta(days=off)) in working_days
    ]


def solve_week(
    customers_in_week: list[OptimizeCustomer],
    salesmen: list[OptimizeSalesman],
    matrix_seconds: dict[tuple[int, int], int],
    period_start: date,
    week_index: int,
    # 480 s/week = 8 min/week, ~32 min total over 4 weeks (raised from 300 s on
    # 2026-05-24 to give GLS more room for multi-customer day-balance moves).
    # Earlier values: 5 s (too aggressive on real data — dropped hypermarkets),
    # 30 s (still leaving improvements on the table), 300 s (good but missed
    # some week-internal rebalancing), 0 = unbounded (GUIDED_LOCAL_SEARCH has no
    # natural convergence on ~500-customer problems and ran 30+ min without
    # stopping). `0` is still honored as "no limit" for users who want to push
    # further; pass it explicitly.
    time_limit_seconds: int = 480,
) -> tuple[list[OptimizeVisit], list[UnassignedCustomer]]:
    if not customers_in_week:
        return [], []

    vehicles: list[tuple[OptimizeSalesman, date]] = []
    for s in salesmen:
        for d in _dates_in_week(period_start, week_index, set(s.working_days)):
            vehicles.append((s, d))

    if not vehicles:
        return [], [
            UnassignedCustomer(customer_id=c.id, reason="no working days in week")
            for c in customers_in_week
        ]

    # Node 0 = dummy depot; 1..N = visits (a customer may appear multiple times
    # in the same week — each occurrence gets its own node so the VRP can place
    # them on different days). Don't build a customer_id -> node map: it'd
    # collide on duplicates.
    n_customers = len(customers_in_week)
    n_nodes = n_customers + 1
    cust_at_node: dict[int, OptimizeCustomer] = {
        i + 1: customers_in_week[i] for i in range(n_customers)
    }

    manager = pywrapcp.RoutingIndexManager(
        n_nodes,
        len(vehicles),
        [0] * len(vehicles),
        [0] * len(vehicles),
    )
    routing = pywrapcp.RoutingModel(manager)

    # One transit callback per salesman (2026-06-10, commute feature): the
    # depot arcs are zero for legacy salesmen but carry real home→customer /
    # customer→home drive time when the salesman opted into include_commute.
    # Vehicles of the same salesman share a callback.
    def make_time_callback(s: OptimizeSalesman) -> Callable[[int, int], int]:
        commutes = salesman_commutes(s)

        def time_callback(from_index: int, to_index: int) -> int:
            from_node = manager.IndexToNode(from_index)
            to_node = manager.IndexToNode(to_index)
            facetime = 0 if from_node == 0 else cust_at_node[from_node].facetime_minutes
            if from_node == 0 and to_node == 0:
                drive_min = 0
            elif from_node == 0:
                drive_min = (
                    commute_drive_minutes(
                        matrix_seconds, s, cust_at_node[to_node], outbound=True
                    )
                    if commutes
                    else 0
                )
            elif to_node == 0:
                drive_min = (
                    commute_drive_minutes(
                        matrix_seconds, s, cust_at_node[from_node], outbound=False
                    )
                    if commutes
                    else 0
                )
            else:
                drive_min = _drive_minutes(
                    matrix_seconds, cust_at_node[from_node], cust_at_node[to_node]
                )
            return facetime + drive_min

        return time_callback

    transit_by_salesman: dict[int, int] = {}
    vehicle_transits: list[int] = []
    for v_idx, (s, _) in enumerate(vehicles):
        t = transit_by_salesman.get(s.id)
        if t is None:
            t = routing.RegisterTransitCallback(make_time_callback(s))
            transit_by_salesman[s.id] = t
        vehicle_transits.append(t)
        routing.SetArcCostEvaluatorOfVehicle(t, v_idx)

    # Per-vehicle fixed cost = "what does it cost to open this day?" Without
    # this, the solver freely opens 5 days each carrying 1 customer because the
    # incremental cost of using an extra day is zero.
    # 2026-05-20: lowered from 90 to 15 to escape the Nizwa/Rumais/Sohar cramming
    #     bug (the 90 was discouraging a fresh-day open even when the alternative
    #     was 1h+ of overflow).
    # 2026-05-22 (Phase 11b): raised from 15 to 40 to refuse 1-visit fragmented
    #     days. Phase 11.1 same day: dialed back to 20 — at 40 the solver
    #     over-consolidated tight-cluster regions (Rumais lost 4 days of spread
    #     vs Plan #2; Sohar #1 + balance hollow-out compounded it). 20 still
    #     beats the original 15 enough to keep the anti-fragmentation behavior
    #     while letting the solver use all 5 working days when load warrants it.
    # 2026-05-24: lowered from 20 to 10 — paired with the overflow penalty bump
    #     (100 → 200) and visit-cap soft-overflow change, the slightly weaker
    #     anti-fragmentation incentive lets the solver fill genuinely-idle days
    #     when overflowing the cluster-day would cost more. Empty-day cases like
    #     s3 Muscat #2 06-08 (1 visit while 06-11 ran 169 min red) are the
    #     specific motivator.
    routing.SetFixedCostOfAllVehicles(10)

    # Soft working-hours cap. 2026-05-20 change: every customer must be assigned
    # (per user request — see MEMORY.md), so capacity is no longer a hard cliff.
    # Instead we:
    #   1. set a very generous HARD cap (working_window + _MAX_OVERFLOW_MINUTES)
    #      so the solver can't accidentally loop a single day to infinity;
    #   2. attach a SOFT upper bound at the actual working_window so any minute
    #      above that costs _OVERFLOW_PENALTY_PER_MIN. The objective minimises
    #      overflow, but never at the cost of dropping a customer.
    # The 10-min "operational slack" that pre-dated this change is folded into
    # the soft bound (no slack on the soft cliff — at 100/min, 1 min over costs
    # only 100 which is trivial; what we want to discourage is hour-scale
    # overflow piling onto a single day).
    # The Time cumul is ABSOLUTE wall-clock minutes-of-day (2026-06-10, visit-
    # window feature): each vehicle's start cumul is pinned to the salesman's
    # shift start instead of zero, so per-customer time-of-day windows can be
    # expressed as plain cumul bounds even when salesmen start at different
    # hours. Output times read the cumul directly (no start-offset addition).
    shift_starts = [_parse_hhmm(s.working_hours_start) for s, _ in vehicles]
    shift_ends = [_parse_hhmm(s.working_hours_end) for s, _ in vehicles]
    hard_caps = [e + _MAX_OVERFLOW_MINUTES for e in shift_ends]
    # slack_max = _MAX_OVERFLOW_MINUTES lets a vehicle WAIT at a node — needed
    # so a visit-window lower bound ("don't call before 10:00") is satisfiable
    # by waiting instead of being an unavoidable penalty on short routes. The
    # end-cumul finalizers below squeeze the waiting back out wherever no
    # window needs it, so windowless plans schedule identically to the old
    # zero-slack behavior.
    routing.AddDimensionWithVehicleTransitAndCapacity(
        vehicle_transits,
        _MAX_OVERFLOW_MINUTES,
        hard_caps,
        False,
        "Time",
    )
    time_dim = routing.GetDimensionOrDie("Time")
    for v_idx in range(len(vehicles)):
        time_dim.CumulVar(routing.Start(v_idx)).SetRange(
            shift_starts[v_idx], shift_starts[v_idx]
        )
        time_dim.SetCumulVarSoftUpperBound(
            routing.End(v_idx), shift_ends[v_idx], _OVERFLOW_PENALTY_PER_MIN
        )
        routing.AddVariableMinimizedByFinalizer(
            time_dim.CumulVar(routing.End(v_idx))
        )
    # NOTE: a previous attempt added SetGlobalSpanCostCoefficient(10) here to
    # spread load — it did nothing useful on single-salesman regions because
    # the global max is always pinned by the busiest salesman across the whole
    # week's vehicles. The existing per-vehicle SetCumulVarSoftUpperBound above
    # is the right tool for per-day overflow; for "too many visits even within
    # the time budget" we use the hard Visits dimension below.

    # Soft per-vehicle cap on visit count. Each non-depot node contributes 1
    # to the day's visit cumul. Soft-only (no hard ceiling — see the drop-cliff
    # note near _VISIT_BUDGET_MINUTES): the soft upper bound adds
    # _VISITS_OVERFLOW_PENALTY per visit above the per-salesman cap so the
    # solver strongly prefers spare days but can always place a sticky-assigned
    # customer. The renderer's isVisitOverCapacity check (visit end >
    # working_window_end) naturally paints overflow visits red because an
    # over-cap visit pushes the day past the window.
    def visit_count_callback(from_index: int) -> int:
        from_node = manager.IndexToNode(from_index)
        return 0 if from_node == 0 else 1

    # Per-salesman soft cap, scaled to the call profile (see the
    # _MAX_VISITS_PER_DAY comment): a 30-min-call roster keeps the legacy 12,
    # a 10-min-call TT roster gets 36.
    def _visit_cap_for(s: OptimizeSalesman) -> int:
        facetimes = [
            c.facetime_minutes
            for c in customers_in_week
            if customer_eligible_for_salesman(c, s)
        ]
        if not facetimes:
            return _MAX_VISITS_PER_DAY
        avg = sum(facetimes) / len(facetimes)
        if avg <= 0:
            return _MAX_VISITS_PER_DAY
        return max(_MAX_VISITS_PER_DAY, round(_VISIT_BUDGET_MINUTES / avg))

    cap_by_salesman: dict[int, int] = {}
    soft_visit_caps: list[int] = []
    for s, _ in vehicles:
        cap = cap_by_salesman.get(s.id)
        if cap is None:
            cap = _visit_cap_for(s)
            cap_by_salesman[s.id] = cap
        soft_visit_caps.append(cap)

    visit_idx = routing.RegisterUnaryTransitCallback(visit_count_callback)
    # Capacity = the week's whole node count, i.e. no hard ceiling — only the
    # soft bound below shapes the day. See the drop-cliff note at the top.
    routing.AddDimensionWithVehicleCapacity(
        visit_idx,
        0,
        [n_customers] * len(vehicles),
        True,
        "Visits",
    )
    visit_dim = routing.GetDimensionOrDie("Visits")
    for v_idx in range(len(vehicles)):
        end_index = routing.End(v_idx)
        visit_dim.SetCumulVarSoftUpperBound(
            end_index, soft_visit_caps[v_idx], _VISITS_OVERFLOW_PENALTY
        )

    # Per-customer eligibility (salesman pin + area; allowed_days).
    # Constraint: VehicleVar must be in {-1, *allowed}.  -1 == unperformed.
    # IMPORTANT: in OR-Tools 9.10+, `IntVar.SetValues()` on a RoutingModel-managed
    # VehicleVar silently does nothing (it returns OK but the domain is never
    # narrowed). The CP solver's MemberCt is the supported way to constrain a
    # VehicleVar to a value set. Skipping this constraint causes the solver to
    # ignore area pins and allowed_days entirely at scale (small problems
    # accidentally work because drive-time cost steers things).
    solver = routing.solver()
    for node, cust in cust_at_node.items():
        index = manager.NodeToIndex(node)
        # Optional time-of-day window (e.g. MT receiving hours): soft bounds on
        # the visit's start time, priced like working-window overflow. Soft, not
        # hard — a window the day genuinely can't honour paints red instead of
        # dropping the customer, consistent with the no-drop philosophy.
        window = visit_window_minutes(cust)
        if window is not None:
            win_start, win_end = window
            time_dim.SetCumulVarSoftLowerBound(
                index, win_start, _OVERFLOW_PENALTY_PER_MIN
            )
            time_dim.SetCumulVarSoftUpperBound(
                index, win_end, _OVERFLOW_PENALTY_PER_MIN
            )
        allowed: list[int] = []
        for v_idx, (s, d) in enumerate(vehicles):
            if not customer_eligible_for_salesman(cust, s):
                continue
            if cust.allowed_days is not None and _our_dow(d) not in cust.allowed_days:
                continue
            allowed.append(v_idx)
        solver.Add(solver.MemberCt(routing.VehicleVar(index), [-1, *allowed]))
        routing.AddDisjunction([index], _drop_penalty_for(cust))

    search = pywrapcp.DefaultRoutingSearchParameters()
    search.first_solution_strategy = (
        routing_enums_pb2.FirstSolutionStrategy.PATH_CHEAPEST_ARC
    )
    search.local_search_metaheuristic = (
        routing_enums_pb2.LocalSearchMetaheuristic.GUIDED_LOCAL_SEARCH
    )
    # `time_limit_seconds == 0` means "no limit" — skip setting the field so
    # OR-Tools leaves the time_limit Duration unset and the search runs until
    # GLS stops finding improvements.
    if time_limit_seconds > 0:
        search.time_limit.seconds = time_limit_seconds

    solution = routing.SolveWithParameters(search)

    visits: list[OptimizeVisit] = []
    if solution is not None:
        for v_idx in range(len(vehicles)):
            s, day = vehicles[v_idx]
            idx = solution.Value(routing.NextVar(routing.Start(v_idx)))
            seq = 1
            prev_node = 0
            while not routing.IsEnd(idx):
                node = manager.IndexToNode(idx)
                cust = cust_at_node[node]
                if prev_node == 0:
                    drive_min = (
                        commute_drive_minutes(matrix_seconds, s, cust, outbound=True)
                        if salesman_commutes(s)
                        else 0
                    )
                else:
                    drive_min = _drive_minutes(
                        matrix_seconds, cust_at_node[prev_node], cust
                    )
                # Cumul is absolute minutes-of-day (start cumul pinned to the
                # shift start above), so it formats directly.
                accumulated = solution.Value(time_dim.CumulVar(idx))
                visits.append(
                    OptimizeVisit(
                        customer_id=cust.id,
                        salesman_id=s.id,
                        scheduled_date=day.isoformat(),
                        scheduled_start_time=_format_hhmm(accumulated),
                        sequence=seq,
                        drive_minutes_to=drive_min,
                    )
                )
                seq += 1
                prev_node = node
                idx = solution.Value(routing.NextVar(idx))

    # Report drops per NODE, not per customer id. A multi-visit customer is
    # expanded into one clone node per visit-in-week (assemble.py), so matching
    # on id alone lets a PARTIALLY placed customer disappear from this list —
    # the plan then ships fewer visits than the requested frequency while
    # solver_status still reads "success", visible only in the Analytics
    # frequency panel.
    placed_counts = Counter(v.customer_id for v in visits)
    remaining_placed = placed_counts.copy()
    unassigned: list[UnassignedCustomer] = []
    for cust in customers_in_week:
        if remaining_placed[cust.id] > 0:
            remaining_placed[cust.id] -= 1
            continue
        if not any(customer_eligible_for_salesman(cust, s) for s, _ in vehicles):
            reason = "no salesman covers this area / pin"
        elif cust.allowed_days is not None and not any(
            _our_dow(d) in cust.allowed_days for _, d in vehicles
        ):
            reason = "no working day matches allowed_days"
        elif placed_counts[cust.id] > 0:
            # Another clone of this customer WAS placed this week; this repeat
            # had no day left that it was both eligible for and not already on.
            reason = "repeat visit this week had no remaining eligible day"
        else:
            # Pre-2026-05-20 this branch was "could not fit within working
            # hours". Soft capacity means that case is no longer possible —
            # the solver assigns past the window instead of dropping. If we
            # somehow still get here, surface it explicitly so the user can
            # report it.
            reason = "no eligible vehicle (unexpected with soft capacity)"
        unassigned.append(UnassignedCustomer(customer_id=cust.id, reason=reason))

    return visits, unassigned
