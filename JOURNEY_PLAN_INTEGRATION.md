# Journey Plan Optimisation integration

## Current browser planning workspace, 2026-09-29

Open SFA > Journey Plan Optimisation > Planning workspace. Select source routes,
set initial frequency/face time and load planning inputs. Review Customers and
Salesmen, calculate or edit Assignments, then choose a start date and Generate on
the Plan tab. Review individual days, unmet demand and Analytics; export plan CSV.
Existing SFA plan remains a separate tab for reviewing original recurring records.

Customer inputs support location, frequency, face time, allowed weekdays, fixed
salesman, area/region/channel and preferred visit-start windows. Salesman inputs
support working schedules, daily visit target, territory/channel coverage and
optional commute. One editable planning salesman is seeded per route; this is not
yet a verified mapping to distinct SFA salesman records. Customer frequency codes
and restriction flags remain unmapped; values are entered in the planning UI.

The JS planner runs in a cancellable browser worker. It assigns customers using
face-time workload and geographic proximity, then generates a 28-day schedule
using frequency patterns and daily load/travel/overflow costs. It counts unplaced
visits individually, prevents duplicate customer/day visits, and honours pins and
allowed working weekdays. Time windows, shift hours and visit targets are soft
limits with warnings. Repeat weekday consistency and visit spacing are preferences.
Travel is great-circle distance at the entered speed, including optional commute;
the desktop OR-Tools engine and OSRM road travel are not integrated.

The Plan tab includes calendar day cards, visit times, map and manual move controls.
Cross-salesman moves transfer all the customer's visits in this preview, while
same-salesman moves affect one visit. Moves validate before changing any day and
resequence affected days. They can alter weekly cadence; frequency analytics checks
total demand. Analytics also reports utilisation, overload, weekly distribution and
face/travel/wait time. Team estimate uses arithmetic workload/visit capacity with a
travel allowance; it is not the source application's solver-backed team sizing.

All inputs and generated snapshots stay in page memory. Leaving/refreshing loses
them; the UI states this explicitly. Editing inputs marks existing results stale.
No database writes, migrations, local-storage persistence or new server endpoints.
The owner explicitly deferred backend saving until the last stage.

## Earlier existing-plan preview

SFA > Journey Plan Optimisation (`/journey-plan`) is a native Inertia/Vue screen
backed by authenticated Laravel endpoints. Route catalog and plan reads intersect
session route/company/subarea permissions and explicitly use `sfa_mysql`.
The added desktop application and supplied SQL remain reference files, unchanged.

The screen reads existing `routesequence` records and `customermaster` names,
coordinates and frequency codes. It displays actual week codes without assuming
a 1–4 cycle. Week records expose all seven day sequences and raw restriction flags.
The selected day's positive sequence records can be reordered manually, exported
as a CSV preview, or passed through nearest-neighbour ordering. Automatic ordering
preserves the first customer, never drops customers and keeps the original order
if the estimated distance would increase. It requires valid coordinates for every
candidate and is limited to 1,000 candidates per day. Distances are explicitly
straight-line, exclude depot/return travel, and are not OSRM driving distances.
Preview is transient and does not write to SFA. This is an initial review feature,
not parity with the desktop application's constraint solver.

## Mapping decisions requiring business input

- `rp32weeknumber`: valid cycle values and calendar alignment.
- `callrestrictiondays1`–`7`: weekday numbering and meaning of each flag value.
- Whether a positive day sequence implies a visit or only an order when scheduled.
- `customermaster.callfrequency`: code meanings (not assumed to be monthly counts).
- Direct-update scope: recurring days/sequences, route/customer assignments,
  customer settings, or some combination. Confirm the existing SFA edit permission.
- Additional logic: working days/hours, daily capacity, CFT source, customer time
  windows, depot coordinates, channel eligibility and customer ownership rules.

Questions were sent to the owner. No answer was available during initial work.
Do not implement schedule writes by guessing these mappings.

## Remaining integration work

Confirm SFA mappings and additional business rules, then replace manual/default
planning inputs with appropriate data sources. Native heuristic scheduling,
assignments, capacity warnings, per-visit unmet demand and analytics now exist as
described above. Exact desktop solver parity, road routing, solver-backed team
sizing and persistent drafts remain unimplemented. The reference uses Electron/
React, SQLite and Python OR-Tools; its solver has not been ported or deployed.

Direct updates should validate all identifiers and edited values on the server,
recheck current route/company/geographic and edit access, show an exact before/after
review, detect stale plans, and apply an atomic transaction with an audit trail.
Historical `routesequencecustomerstatus` rows must not be treated as recurring
templates. Draft/version/audit persistence is not yet implemented; required support
tables must be agreed and provisioned separately without importing the SQL dump.

Use the desktop handoff (`journey-plan-app-main/docs/reviews/2026-09-21-web-app-handoff.md`)
as a regression checklist when implementing the solver: per-rep balancing,
per-visit drop accounting, monotonic travel calibration and atomic saves.

## Validation

32 JavaScript tests passed, including preview and workspace regression coverage.
All three Vue components compile and the full Vite production build passed,
including the worker bundle. PHP access tests
are added but not executed because PHP/vendor are unavailable locally. Live
database and browser behaviour remain unverified. No business data was changed.
