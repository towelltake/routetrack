# Journey Plan Optimisation integration

## Implemented, 2026-09-29

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

## Remaining implementation

Port the four-week scheduling, assignments, capacity checks, per-visit unmet-demand
reporting and analytics after mapping the business rules. The reference uses
Electron/React, SQLite and Python OR-Tools; its solver has not been ported or deployed.
The new screen uses the existing Laravel/Vue/Leaflet stack.

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

23 JavaScript tests passed, including the new preview regressions. Vue script and
template compilation and the full Vite production build passed. PHP access tests
are added but not executed because PHP/vendor are unavailable locally. Live
database and browser behaviour remain unverified. No business data was changed.
