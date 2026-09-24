# TRAC / routeTrack project memory

Productive Visits popup's journey Date column is now labelled Route Start Date
(2026-09-24). Display label only; visit start/end dates remain separate. Diff
check passed.

## Productive visit date/time layout: 2026-09-24

Productive Visits popup now shows the recorded visit date above its time in both
start and end columns, including same-day checkouts. Added start_date to detail
rows rather than using journey date. Later-date checkout remains red; revisit
badges remain below start time. Missing timestamps show Unavailable. Vue script
syntax, date/time markup and diff checks passed; build/browser and PHP execution
remain unverified locally.

## Productive popup revisit badge: 2026-09-24

Productive Visits popup shows a blue Revisit #2/#3/etc. badge under visit start
time for subsequent visits to the same customer within the same journey. First
visits are unmarked. DashboardCustomerDetails numbers chronologically sorted logs
before filtering incomplete visits, resetting per journey/customer; filtering and
pagination cannot change the badge. Existing OTP/LPO and overnight styling remains.
Updated existing PHP drilldown assertions for first/repeat visits and journey reset.
Vue script syntax and diff checks passed. PHP tests and build/browser validation
remain unavailable with the missing local runtime/dependencies.

## Unplanned popup explicit OTP status: 2026-09-24

Added a dedicated OTP Status column to the Unplanned Customers Visited popup,
with purple With OTP and neutral Without OTP badges derived from the existing
otp_visit_count. Existing visit status, OTP count and filters remain available.
CustomerDetailsDialog only; no calculation or endpoint changes. Vue script syntax,
targeted markup and diff checks passed; browser/build remains unverified locally.

## Unplanned customer OTP split: 2026-09-24

Unplanned Customers card now shows unique unplanned customers without any matched
OTP as its main count, with a full-width purple OTP customer count below. Counts
are per journey/customer; any matched OTP puts a customer only in the OTP group,
including mixed OTP/non-OTP repeats and incomplete visits. Journeys without plans
remain excluded. Existing total unplanned analysis/graph metrics are preserved.
The unplanned popup retains both groups with status filters, purple OTP highlights
and OTP visit counts, matching the planned popup. Shared breakdown tiles now support
counts as well as percentages. Updated DashboardMetrics, DashboardCustomerDetails,
DashboardCards and CustomerDetailsDialog. PHP regression added for deduplication,
mixed visits, popup statuses, absent plans and empty periods. All 20 existing JS
tests, both Vue script syntax checks and diff checks passed; PHP tests/build/browser
checks remain unverified with missing local runtime/dependencies.

## Productive visit checkout display: 2026-09-24

Dashboard Productive Visits popup now includes Visit end time beside Visit start
time. When the recorded checkout date is later than the visit check-in date,
the checkout time and its date underneath are red, including on LPO rows.
Missing/invalid checkout timestamps display Unavailable. DashboardCustomerDetails
supplies end_time, end_date and ends_later_date; CustomerDetailsDialog renders
them. Metric calculations are unchanged. Vue script syntax and diff checks passed;
PHP execution and build/browser verification remain unavailable locally.

## Planned customer OTP split: 2026-09-24

Dashboard Planned Customer Visits now displays unique planned customers visited
without OTP / unique scheduled route sequence customers, with a full-width purple
OTP percentage and count tile below using the same denominator. Uniqueness remains
per journey/customer across the selected journeys. Any matched OTP visit places
that customer in the OTP group only, even with a non-OTP repeat; all OTP types
qualify. Unplanned customers and unmatched OTP events do not enter these counts.
Empty plans yield unavailable percentages. Existing overall coverage graph metrics
remain unchanged; the card uses explicit new with/without-OTP metrics.

The planned popup retains every planned customer, highlights OTP customers purple,
adds OTP visit counts and filters for visited with/without OTP and not visited.
Changed DashboardMetrics, DashboardCustomerDetails, DashboardCards, dashboard Index
and CustomerDetailsDialog. Added PHP regression coverage for repeat visits, journey
scope, mixed OTP/non-OTP customers, unplanned OTP, popup status and empty plans.
Validation: 20 JavaScript tests, modified Vue script syntax and diff checks passed.
PHP tests and full build/browser rendering remain unverified: PHP/vendor and
node_modules are unavailable locally.

Last inspected: 2026-09-20. Snapshot: branch `main`, commit `6875b7b`
(laptop handoff documentation). Working tree was clean before this review.
Author listed in README: Jyothish Thyagarajan. Old workspace:
`D:\PHP Development\routeTrack` (the new laptop may use any path).

## Purpose and current work

This application displays SFA route performance, customer locations, and planned
versus actual GPS journeys. The owner intends further customizations and is moving
to another laptop. No specific next customization was supplied in this conversation.
The current task is preserving context and setup knowledge; no application behavior
has been changed. This memory is based on repository inspection and this conversation,
not on unavailable earlier conversations or a live database audit.

Recent commits mention operational time, variance, CFT compliance/actual time, OTP,
dashboard sizing and UI colors. Use `git log` and the relevant diff for exact history;
commit messages alone are not requirements.

## Architecture and places to change

| Area | Main files | Responsibility |
| --- | --- | --- |
| Entry/routing | `bootstrap/app.php`, `routes/web.php`, `routes/auth.php` | Middleware, login, page registration; `/` redirects to `/dashboard` |
| Dashboard | `routes/dashboard.php`, `app/Http/Controllers/Dashboard/DashboardController.php` | Authorized filters, latest positions, metrics and drilldowns |
| Dashboard calculations | `app/Services/DashboardMetrics.php`, `DashboardAnalysis.php`, `DashboardCustomerDetails.php`, `DashboardOutsideVisits.php` in the same folder | Financial/productivity/CFT metrics, journey breakdowns and customer details |
| Dashboard UI | `resources/js/views/routelocation/` | `Index.vue`, cards, charts, analytics, dialogs; pure helpers `analytics.js`, `filters.js` |
| Route Tracking | `routes/routetracking.php`, `app/Http/Controllers/RouteTracking/RouteTrackingController.php`, `resources/js/views/routetracking/Index.vue` | Planned/actual routes, visits, OTP, transaction details, timing and map layers |
| GPS stop detection | `app/Services/StationaryDetection.php`, `config/tracking.php` | Stationary periods, signal gaps and accuracy filtering |
| Customer Location | `routes/customerlocation.php`, `app/Http/Controllers/CustomerLocation/`, `resources/js/views/customerlocation/Index.vue` | Customer positions and OSRM route geometry |
| Authentication/access | `app/Auth/SfaUserProvider.php`, `app/Models/User.php`, `app/Http/Requests/Auth/LoginRequest.php`, `app/Http/Controllers/Auth/AuthenticatedSessionController.php` | Legacy credentials and session access scope |
| Navigation/theme | `resources/js/config/navigation.js`, `resources/js/layouts/`, `resources/js/components/`, `resources/scss/` | Existing OneUI/Bootstrap layout and shared components |
| Locale/UI helpers | `resources/js/composables/useI18n.js`, `usePermissions.js`, `useAmountFormatter.js` in the same folder | Translation, permissions and display formatting |
| Configuration | `config/database.php`, `config/services.php`, `config/tracking.php` | Both databases, OSRM and detection thresholds |
| Schema support | `database/migrations/`, `app/Support/LegacySchemaBootstrap.php`, `LegacySchemaSnapshot.php` in the same folder | Existing-schema validation and feature/support migrations |

Route Replay has a controller, Vue view and `routes/routereplay.php`, but
`routes/web.php` does not include that route file and navigation does not expose it.
`routes/api.php` is empty apart from the PHP opening tag. Old README API examples
are therefore not evidence of available endpoints.

## Stack and runtime

- Composer requires PHP `^8.2`, Laravel `^12.37.0`, Inertia Laravel `^2.0.10`.
- Frontend uses Vue 3, Inertia Vue, Vite 7, Pinia, Bootstrap 5.3.8, Leaflet and Chart.js.
- Installed Vite requires Node `^20.19.0 || >=22.12.0`, stricter than README's “20+”.
- Observed old laptop: PHP 8.4.15 from WAMP and Node 20.19.5. Paths are machine-specific.
- Install from `composer.lock` and `package-lock.json`; use `composer install` and `npm ci`.

## Data and access

`config/database.php` defaults to `sfa_mysql` unless `DB_CONNECTION` overrides it.
This named connection uses `SFA_DB_*`; some models and login queries explicitly
use it. Setting only generic `DB_*` credentials does not configure those queries.
GPS queries explicitly use `tracking_pgsql`, configured through `TRACKING_DB_*`.

Key data relationships:

- `routemaster`, `company`, `clustermaster`, `regionmaster`: route and organization hierarchy.
- `startendday`: journey identity (`routekey`), start/end times, closure and odometers.
- `routesequencecustomerstatus`: journey plan; scheduled flag is literally `schelduledflag`.
- `routesequence`: also used for route eligibility and sequence information.
- `customervisitlog`: all visits, including repeats; `logkey` is the visit log key.
- `customeroperationscontrol`: links a visit through same `routekey` plus
  `log_id = customervisitlog.logkey`, and supplies transaction `visitkey`/coordinates.
- `customermaster`, `channelmaster`: customer/channel master data; current visit
  CFT targets come from `customervisitlog.cft`, not the older master-data fallback.
- `invoiceheader`, `salesorderheader`, `arheader`: sales, orders and collections.
- PostgreSQL `trac_routetrack`: GPS readings with route, date/time and coordinates;
  accuracy/provider support must tolerate legacy missing columns.

Login uses `usermaster` (`userid`) via custom `sfa` auth. The provider compares the
stored password string directly and does not rehash it. Do not casually replace it
with standard Laravel hashing during unrelated work; credential migration would
be a separate coordinated change. Remember tokens are disabled.

Login calculates `user_access` session values from `useraccesscodes` and geography,
retaining company restrictions per permission row. Controllers enforce access
scope. Form permission infrastructure also exists, but do not assume middleware
is active merely because its class exists; check registration and route behavior.

## Business behavior to preserve

Dashboard filters combine selections within one dimension with OR and dimensions
with AND. Empty selections mean all accessible values. Filters include legal entity,
cluster, division, region and route. Region is independent of the company hierarchy.
Dropdown options ignore their own selection while calculating additional choices.

Dashboard From/To selects journey start dates inclusively. Cards/tables aggregate
all eligible journeys; the map shows the latest eligible journey per route with
usable GPS, bounded by journey timestamps and the next journey start. A journey
without GPS can contribute to cards even if absent from the map. Default is Today;
This Week begins Sunday. Preserve overnight journey handling.

Coverage deduplicates planned journey/customer pairs. Repeated visits remain
visible but do not multiply planned coverage. Unvisited plans are pending for open
journeys and missed for closed journeys. Productive completed visits require
positive non-void sales/orders; collection-only visits are not productive.
Financial totals stay separated by currency and must not multiply through joins.
Missing denominators/data should remain unavailable rather than become invented zeros.

Current expected CFT uses `customervisitlog.cft` in minutes, with null/zero becoming
zero. The older README's customer/channel fallback is superseded. Route Tracking's
planned face-time total sums visit-log CFT for planned customers in the journey.
Visit duration uses recorded visit start/end timestamps. OTP events are not proof
of approval. Timing and CFT formulas vary by screen; inspect tests before sharing
or refactoring calculations.

Current code supersedes parts of `ROUTE_TRACKING_README.md`:

- `DashboardAnalysis::journeyTiming` uses recorded end for closed journeys and
  `last_location_time` for open ones. `visit_time` is summed actual CFT;
  remaining time is clamped duration minus visit time. The README's older
  closed-only/merged-interval description is not this method's current behavior.
- `DashboardOutsideVisits` computes a separate outside-visit breakdown using GPS
  stationary periods and visit overlaps. Do not generalize the README's statement
  that dashboard stationary information is unavailable to every drilldown.
- Route Tracking `summarizeVisitTime` calculates face time, OTP-customer time,
  actual CFT excluding OTP-customer time, planned CFT for completed non-OTP visits,
  and percentage variance when planned CFT is positive. Its travel-time field is
  duration minus stationary seconds. Check the dedicated tests for intended formulas.
- Route Tracking `findRouteDay` can match start, end or spanning dates, then fall
  back to visit logs and scheduled sequence data. It is not simply an exact
  journey-start-date lookup.

Visit coordinates prefer linked operation coordinates, then fixed customer
coordinates, then nearby GPS within five minutes, subject to Oman bounds and
validity. Visits with no valid coordinates still appear in lists.
OSRM supplies planned geometry and map matching; fallback geometry is intentional.
Raw trail is a separate toggle. Missing plan or GPS data must not hide other usable data.

Stationary defaults: 5 minutes, 30 metre radius, 120 second maximum gap, accuracy
threshold 50 metres. Unknown accuracy remains supported. Stops use observed
timestamps, not the current clock. GPS gaps describe missing evidence, not a proven
cause. Stationary periods may overlap customer visits. See `config/tracking.php`
and `StationaryDetectionTest.php` for exact behavior.

## Known setup/documentation gaps

1. `.env.example` exists locally but is ignored by `.gitignore`'s `.env.*` rule and
   is not tracked. A fresh clone will not contain it. The handoff includes a safe template.
2. The older README's generic MySQL configuration omits the separate named SFA/GPS
   setup needed by current code.
3. Only `AdminBootstrapSeeder.php` is present under tracked seeders; no
   `DatabaseSeeder.php` exists. That seeder references absent `UserPermissionSync`
   and hashes passwords unlike the current auth provider. Do not rely on the
   README's `php artisan db:seed` recipe for restoring this project.
4. Legacy schema must already exist. Migration names mentioning SQL imports do
   not imply a complete automatic database restore. Root `sfa_enhance_*.sql` files
   are not a verified backup of both working databases.
5. Route Replay is currently unregistered; old mobile API examples are inactive.
6. Queue table migrations are absent; keep synchronous queues unless a queue
   implementation is deliberately provisioned.

These are observed limitations, not newly authorized repair tasks.

## New laptop review: 2026-09-20

Reviewed the handoff against registered routes, active Vue screens, authentication,
access queries, dashboard services, tracking calculations and relevant tests.
No application behavior was changed. Current workspace is
`C:\Development\RouteTack\routetrack`.

Additional current-code details that supersede older documentation:

- Dashboard `routes_started` counts distinct route/start-date pairs, while
  `journeys_started` counts journeys. `total_routes` is matching active accessible
  routes multiplied by inclusive calendar days, including weekends;
  `routes_not_started` is the nonnegative difference. Metrics/status include routes
  without sequence data; filter catalog and map require sequence membership.
- Sales uses `invoiceheader.totalsalesamount`, orders use
  `salesorderheader.totalinvoiceamount`, and collections use `arheader.amountpaid`.
  Dashboard returns combine good/damaged returns from invoices and orders and
  display negative values. Returns require `voidflag = 0`; ordinary sales/order/
  collection totals accept null or zero void flags. Preserve these distinctions.
- Dashboard includes operational time, OTP customer time, actual/planned face
  time, variance, total duration, outside-visit time and returns cards in addition
  to coverage/productivity/financial cards. Performance, review and map views open
  on demand; summary requests omit full journey analysis, with details fetched
  separately. Filter changes cancel or invalidate stale requests.
- Route Tracking GPS geometry, stops and gaps use the selected calendar date.
  Its journey duration independently uses journey start/end or last reported GPS
  before the next journey, potentially spanning midnight. These scopes differ.
- Customer Location currently exposes company and optional route selection,
  customer search/jump, clustered markers and a searchable list capped at 200
  visible entries. A company is required to Apply. Area/subarea and OSRM endpoints
  exist, but the active view does not call them.

Local readiness: Node `v24.20.0` is available. PHP and Composer were not found on
the current PATH (this does not establish that they are absent elsewhere).
`.env`, `vendor/autoload.php`, `node_modules/` and the frontend build manifest are
absent. Dependencies were not installed and private configuration was not created.
Both database connections and OSRM remain unverified.

Validation: all 10 standalone JavaScript tests passed using
`node --test tests/dashboard-filters.test.mjs tests/dashboard-analytics.test.mjs`.
PHP tests, Artisan route listing, frontend build and browser/database integration
could not be verified with the present setup. The 81 PHP tests recorded below are
historical results from the old laptop, not results from this review.

## Efficiency metric: 2026-09-20

Implemented Efficiency in Dashboard Customer performance and Route Tracking
Customers summary cards. Formula: unique productive customers / unique visited
customers * 100, rounded to one decimal; no visited customers yields null (dash).
Uniqueness is per journey/customer, with Dashboard combining those counts across
the selected journeys rather than averaging percentages. This scope was stated
when implementing after the owner approved the proposed metric.

All logged customers count in the denominator, including incomplete and unplanned
visits. A customer qualifies once if any completed visit has a linked positive,
non-void invoice sale or sales order under existing productivity rules. Repeated
visits/multiple documents cannot inflate counts; collections and returns alone
do not qualify. Existing Productive visits calculations remain unchanged.

Backend files: `DashboardMetrics.php` and `RouteTrackingController.php`.
UI files: `routelocation/DashboardCards.vue`, `routetracking/Index.vue`.
The cards show percentage and numerator/denominator, with a definition tooltip;
Efficiency is a summary metric without a dedicated drilldown.
Regression cases added to `DashboardMetricsTest.php` and
`RouteTrackingTransactionSummaryTest.php` cover journey/customer deduplication,
qualification, incomplete visits and empty denominators. PHP execution and Vue
build/browser validation remain blocked by the local setup described above.
The existing 10 JavaScript tests and `git diff --check` passed after this change.

## Graph percentages: 2026-09-20

All five Dashboard graphs now default to percentage bars, with a Values toggle
retaining counts/minutes. Each graph includes percentage summary metrics;
tooltips and accessible tables show raw values and percentages together.
Denominators: coverage uses planned customers; productivity uses completed
visits; each exception category uses all recorded visits; planned/operational CFT
uses planned CFT; journey time uses measured duration. Top-ten time/CFT summaries
cover only displayed routes. Summaries divide summed numerators by summed valid
denominators, not an average of row percentages. Zero/missing denominators remain
unavailable. Values above 100% are preserved; exception categories overlap.

Changed `DashboardGraphs.vue`, `DashboardChart.vue`, and `analytics.js`.
DashboardController chart summaries now include `visits` for exception rates.
Added JavaScript percentage tests and a PHP summary-denominator assertion.
Validation: 13 JavaScript tests passed, changed Vue script syntax checks passed,
and `git diff --check` passed. PHP tests, Vue template compilation/build and live
browser checks remain unverified due to the missing runtime/dependencies.

## Journey clock timeline: 2026-09-20

Replaced only "Where journey time goes" with `JourneyTimeChart.vue`: a horizontal
00:00-24:00 axis, floating interval bars at actual recorded times, and separate
journey/calendar-date rows. Top ten routes are selected by summed measured journey
duration within the authorized filters. Multiple journeys retain separate rows;
overnight journeys split at midnight. Open journeys use the existing last reported
location cutoff. Missing or nonpositive durations produce no timeline bar.

`DashboardAnalysis.php` includes recorded start/end and completed visit intervals;
the controller exposes the selected routes' timeline in the chart summary.
`journeyTimeline.js` interprets these as database wall-clock values without browser
timezone conversion, clips visits to journey bounds and merges overlaps. Gray
segments mean no completed visit recorded, not proven driving/idle time. Timeline
percentages use merged intervals and remain visible above the chart and in the
table/tooltips; existing summed visit-time metrics are unchanged and can differ.
Other graphs retain the percentage/value toggles.

Validation: all 16 JavaScript tests passed, including clock positioning, clipping,
overlaps, midnight splitting, separate journeys and unavailable timing. PHP tests
added for interval serialization and summary payload. Vue script syntax and
`git diff --check` passed. PHP execution, full build and browser validation remain
unavailable with the current local setup.

## Customer productivity exclusion: 2026-09-20

The owner added `customermaster.toplpo`. Customers whose flag equals 1 are now
excluded from BOTH numerator and denominator for Dashboard Productivity and
Efficiency, and Route Tracking Efficiency. Null, zero and other values remain
eligible; absent master records are not automatically excluded. An entirely
excluded population yields unavailable percentages rather than zero percent.

DashboardMetrics loads flagged visited customer codes and annotates visits for
DashboardAnalysis. Both aggregate and per-journey productive/completed counts
respect the exclusion, including charts and comparison tables. The productive
customer drilldown uses the same exclusion. Route Tracking reads `cm.toplpo` into
visit records and filters only its efficiency calculation. Coverage, visits,
plans, timing/CFT, OTP, financial totals and timeline intervals retain flagged
customers. No migration or SQL import was run; runtime schema must contain the
owner's new column. User SQL-file changes were preserved.

Tests added for excluded productive/nonproductive/repeated customers, null/zero/
other flags, all-excluded denominators, dashboard drilldown/analysis consistency,
and retaining time/coverage/financial data. Test customer schemas include toplpo.
Validation: existing 16 JavaScript tests and `git diff --check` passed. PHP tests
and frontend build/browser verification remain blocked by missing PHP/dependencies.

## Ignored customers in metric popups: 2026-09-20

Dashboard Productivity details now retain flagged visits (including incomplete
flagged visits) with status Ignored and an exclusion reason. Added a Dashboard
Efficiency drilldown through the same authorized customer-details endpoint,
grouped once per journey/customer; any qualifying completed visit makes an
eligible customer productive. Both dialogs default to All and offer Productive,
Nonproductive and Ignored filters. All-excluded populations remain inspectable.
Route Tracking Efficiency now opens its summary modal with unique customers,
visit counts, status and exclusion reasons using existing authorized visit data.
Ignored rows remain excluded from all metric numerators and denominators.

Files: DashboardCustomerDetails, DashboardController, DashboardCards, dashboard
Index/CustomerDetailsDialog, tracking Index, and analytics.js. Updated PHP tests
for visible ignored rows, grouped efficiency and all-excluded data; added a JS
test for the tracking popup. Validation: 17 JavaScript tests passed and diff/script
syntax checks passed. PHP tests and full build/browser checks remain unavailable
with the present local setup. This supersedes the earlier note that Efficiency
had no drilldown and Productivity details omitted flagged customers.

## Collection productivity and efficiency: 2026-09-20

Positive non-void collections now qualify as productive, alongside invoice sales
and sales orders. This supersedes the earlier collection-only exclusion. Existing
visit linkage, completed-visit requirement, null/zero void flag handling and
toplpo exclusions are retained. Productivity uses eligible completed visits;
Efficiency uses unique eligible customers per journey. Totals use the union of
qualifying sources: collection and sales/order breakdowns can overlap, but neither
multiple documents nor overlapping sources inflate the combined total.

Dashboard cards show combined rates with Collection and Sales orders + invoices
rates below. Productivity and a new Efficiency graph show combined and source
series as grouped bars, combined summary above, source rates below; all rates use
the same denominator. Route Tracking Efficiency shows the same breakdown.
Dashboard analysis/summary and comparison table reflect collection productivity.
Metric popups identify collection versus sales/order qualification, retaining
ignored rows. Financial, coverage and timing metrics are unchanged.

Regression expectations updated for collection-only visits. Added overlap,
duplicate document, nonpositive/void collection and excluded customer cases.
Validation: 18 JavaScript tests passed. PHP tests remain unexecuted because PHP
and vendor dependencies are absent. Full Vue build/browser checks remain pending
frontend dependencies. Script syntax and diff whitespace checks passed.

## Operational time window: 2026-09-20

Operational Time now means first non-OTP customer check-in to the checkout of
the last customer visit (ordered by check-in) without OTP, per journey. This is
elapsed time including intervening gaps/travel and any OTP visits between those
boundaries, not summed service time. An unfinished/invalid last checkout or no
usable non-OTP visits yields unavailable, not an earlier checkout or the current
clock. Overnight dates are preserved. Dashboard sums available journey windows
and reports unavailable journey counts.

Shared `app/Services/OperationalTime.php` normalizes Dashboard and Route Tracking
visits. Tracking uses DashboardMetrics' all-type, journey-bounded OTP association
for operational boundaries via `operational_otp`; existing GPS-IN OTP fields for
face time and visit displays retain their prior behavior. Repeated visits to the
same customer are considered separately. toplpo only affects productivity, not
operational boundaries.

Dashboard card, performance table and operational popup use the new window;
popup shows one row per journey with first/last customer and timestamps. Tracking
card shows the same span and timestamps. Actual/planned CFT, summed completed
visit time, travel/stationary calculations and the visit timeline are unchanged.
Time Outside Visits remains duration minus summed completed visits (not the new
operational span); its popup and CFT graph labels now say customer visit time.

Tests: OperationalTimeTest covers matching screen adapters, OTP boundary
exclusion, intervening time, midnight, missing checkout and all-OTP/empty data.
Updated dashboard operational totals and popup expectations. 18 JavaScript tests,
Vue script syntax checks and diff whitespace checks passed. PHP tests and full
build/browser checks remain unverified with the missing local runtime/dependencies.

## Customer Face Time excludes OTP: 2026-09-20

Customer Face Time now excludes every visit matched to any OTP type, consistently
on Dashboard and Route Tracking. Dashboard `cft_minutes`, actual CFT analysis,
configured CFT comparison/variance and CFT graphs omit OTP durations/targets.
Tracking CFT/OTP time and planned comparison use the shared journey OTP flag
(`operational_otp`), falling back to attached OTP logs for legacy test inputs.
Non-OTP repeat visits to the same customer remain eligible. Operational Time
continues to use the previously implemented first/last non-OTP boundaries.

Added explicit Customer Face Time cards on both screens. CFT and compliance
popups retain all visit rows, with OTP visits in red and an explicit excluded
label. Dashboard exposes `otp_excluded` and `recorded_actual_cft`, while counted
actual/planned CFT is zero and variance unavailable for excluded visits. Tracking
popup shows recorded duration alongside counted CFT. Incomplete non-OTP visits
remain unavailable, not fabricated durations. toplpo does not exclude CFT.

Preserved all-visit duration separately (`recorded_visit_minutes`, tracking
`face_time`, analysis `visit_time`) for Time Outside Visits and the chronological
visit timeline. These include recorded OTP visits and retain their existing
meaning. Dashboard Outside details sum recorded rather than counted CFT.

Validation: 18 JavaScript tests passed; diff and modified Vue script syntax checks
passed. Added/updated PHP regression cases for all-type OTP exclusion, retained
popup rows, repeat visits, graph totals and keeping raw timing unchanged. PHP
tests, full frontend build and browser rendering remain unverified because the
local runtime/dependencies are missing.

## Route Tracking CFT popup refinement: 2026-09-20

Route Tracking Customer Face Time opens an extra-wide, scrollable popup using
every recorded visit without customer deduplication or OTP filtering. Added
numbered visits, separate customer code/name, planned CFT, actual counted CFT and
variance alongside check-in/out and recorded duration. OTP visits are red with
an explicit excluded label, zero counted CFT/plan and unavailable variance.
The heading reports total visits and OTP visits; empty results have a message.
No calculation or endpoint behavior changed in this refinement.
Validation: 18 existing JS tests, Vue script syntax and diff checks passed;
full build and browser rendering remain unverified without local dependencies.

## Percentage breakdown presentation: 2026-09-20

Dashboard productivity/efficiency and Route Tracking efficiency breakdowns now
show bold 24px percentages above their labels in two softly tinted tiles:
purple for Collection, blue for Sales orders + invoices. Responsive grids stack
tiles when narrow. Calculation behavior is unchanged. Diff and Vue script syntax
checks passed; full build/rendering was not verified in this workspace.

## Card alignment and shorter labels: 2026-09-20

Renamed displayed sales/order breakdown labels to Orders/Invoices in Dashboard
cards, graphs, comparisons and popups and Route Tracking cards/popups. Dashboard
customer cards now align title/value/note rows and anchor full-width, equal-column
percentage tiles to the bottom. Journey section uses two columns of the outer
twelve-column grid, giving the five customer cards more room. Six time cards use
six columns on wide screens and balanced three/two columns at smaller widths.
No calculation changes. Diff and Vue script syntax checks passed; visual/build
verification remains unavailable without installed frontend dependencies.

## Verification and continuing work

Existing tests cover dashboard filters/metrics, outside visits, CFT, journey
duration, tracking time/transactions, stationary detection, legacy auth and
company permissions. JavaScript helper tests live in the two `tests/*.test.mjs` files.
See the handoff for commands and the recorded validation result. On 2026-09-16,
81 PHP unit tests (558 assertions) and 10 JavaScript tests passed; route listing
completed successfully. No live browser/database integration check was performed.

After a customization, update this file with its purpose, changed behavior,
important decisions and checks. Keep credentials and live customer data out.
If a historical decision cannot be found in code, tests, Git or these notes, say
it is unknown instead of inventing a rationale.

## Journey timeline display refinement: 2026-09-20

Removed journey numbers from visible route/date labels while preserving unique
internal row keys for multiple journeys. Renamed the canvas container to avoid
OneUI's global timeline pseudo-element drawing the unwanted left-hand line.
Dashboard summary now returns all authorized, filtered journeys with usable
boundaries. The chart defaults to the ten routes with greatest total duration;
Expand all routes / Collapse to top 10 controls the displayed selection.
OTP intervals use the existing all-type by_visit matching. Non-OTP visit segments
are teal, OTP segments purple and outside-recorded-visit segments red. OTP takes
precedence for overlapping intervals; all durations still count once. Outside
visits is labelled idle / travel, since this data cannot isolate GPS idle time.
Percentages and the values table include all three categories and follow the
currently displayed routes. No operational/CFT metric formulas were changed.
Validation: 20 JavaScript tests passed, Vue script syntax and diff checks passed.
Updated PHP timeline expectation; PHP tests, build and visual verification remain
unavailable without local PHP/vendor/frontend dependencies.

## Deferred timeline expansion: 2026-09-20

Supersedes the eager all-route timeline response above. Dashboard summary sends
only the top ten routes' timeline rows plus timeline_route_count. Expand requests
metrics.json with timeline_only=1 and the filter snapshot used for the displayed
summary. That response includes only timeline rows, through the same authorized
metrics query and validation. Existing backend metric calculations still run;
this optimization reduces initial payload and browser work, not database work.
The chart caches expanded rows until refresh/filter change, cancels stale requests,
resets expansion on new data, and shows loading and retryable errors. Collapse
and reopening reuse loaded rows. PHP regression coverage added for ten-row-route
selection versus explicit all-route response. Twenty JS tests, modified Vue script
syntax and diff checks passed. PHP tests, production build and browser checks
remain unverified because local runtime/dependencies are unavailable.

## All-route timeline popup: 2026-09-20

Expand all routes now opens a wide native dialog, consistent with the dashboard
popups, while the dashboard retains its top-ten preview. Extracted the shared
chart, percentage summary and values table into JourneyTimelinePlot.vue so both
views use identical timing/color logic. The popup opens immediately with a loading
state, fetches on demand, supports retry, and reuses successful data until filters
or metrics refresh. Close, Escape and backdrop dismiss the dialog and abort pending
requests; the chart is unmounted while closed. Expansion is also available when
there are ten or fewer routes. No endpoint or calculation changes.
Validation: 20 existing JS tests, both Vue script syntax checks and diff checks
passed. Production build/browser layout remain unverified without node_modules.

## Temporarily hidden planned/CFT graph: 2026-09-20

DashboardGraphs.vue now gates Planned vs customer visit time behind the local
showPlannedVisitTime=false flag. Set it to true to restore the graph. Its data
and calculations remain available; other charts and CFT cards are unaffected.
Vue script syntax and diff checks passed; browser/build not run locally.

## Dashboard stationary idle card: 2026-09-20

Replaced the displayed Customer Face Time card with Idle Time Outside Customer
Visits, last in the six-card Time row. It uses DashboardOutsideVisits.stationary:
GPS-detected stationary intervals minus the union of all recorded customer visit
intervals, including OTP visits. Travel is excluded; existing stop thresholds,
GPS gap rules and journey boundaries apply. Other timing cards are unchanged.
The authorized customer-details endpoint accepts type=idle and resolves live
journey endpoints just as for outside/duration. Dashboard requests idle separately
after overview metrics render, with the same filters/cancellation signal and stale
response guards. It shows its own loading/error state, excludes unavailable
journeys and labels partial totals. Successful route details are retained for the
idle popup, avoiding recomputation on click. This does add GPS/server work; no
live performance measurement was performed.
Validation: 20 JS tests, modified Vue script syntax and diff checks passed. Added
PHP integration coverage for stationary-minus-visits and unavailable GPS through
the popup service. PHP tests/build/browser verification remain unavailable locally.

## Face Time Compliance labels: 2026-09-20

Dashboard compliance card now explicitly labels Actual CFT, Planned CFT (each
with h:mm units) and Variance (%). Uses existing backend values and formula;
missing planned time now displays N/A for variance instead of 0%. The two time
values use equal-width columns. The previously hidden planned-vs-visit graph
remains hidden. Vue script syntax and diff checks passed; build/browser unverified.

## Face Time Compliance popup columns: 2026-09-20

Clarification: planned CFT and variance percentage were requested in the popup
grid. CustomerDetailsDialog actual_face now displays Actual CFT (h:mm), Planned
CFT (h:mm) and signed Variance (%). Percentage uses each row's existing actual and
planned values; zero/missing plan, incomplete actual time and OTP-excluded visits
show N/A. OTP rows remain red. OTP-time popup columns are unchanged. No new
requests or backend calculations. Vue syntax, six direct variance cases and diff
checks passed; browser/build unverified locally.

## LPO popup labels and highlighting: 2026-09-20

Dashboard productivity/efficiency popup status labels and filter tab display
LPO Customers instead of Ignored, retaining the internal Ignored status so counts
and filtering stay unchanged. toplpo-excluded rows have a pale yellow background
and dark amber text. Route Tracking efficiency popup uses the same label and row
highlight. OTP/CFT red styling remains separate. No calculation changes.
Both Vue script syntax checks and diff checks passed; browser/build unverified.

## LPO graph wording: 2026-09-20

Updated productivity/efficiency graph notes and matching card tooltips to say
LPO Customers instead of Ignored customers. Internal status keys and calculations
are unchanged. Diff check passed; this is a text-only change.

## Route Tracking slow-load investigation: 2026-09-20

Live URL could not be accessed using the web tool; production timing/root cause
is unconfirmed. Bare Route Tracking initially loads filters.json; a route/date
link also runs compare.json. Compare sequentially calls OSRM for planned legs
and actual GPS chunks. Added a shared routingGet helper with configurable
OSRM_CONNECT_TIMEOUT (2s) and OSRM_TIMEOUT (5s). A connection failure suppresses
further OSRM attempts within that controller request, using existing labelled
straight-line/raw-GPS fallbacks. Healthy responses and all auth checks remain
unchanged. This bounds individual failed network waits, not total DB/server time
or the cumulative time of many successful routing calls. Dashboard idle request
is not invoked directly by Route Tracking.
Added PHP regression tests for one failed attempt per request, retry in a later
request, and healthy routing calls. Diff check passed; tests cannot run without
PHP/vendor. Requested whether delay is before dropdown or after Compare and the
slow Network request/duration; awaiting that evidence for live diagnosis.

## Route Tracking card redesign and productivity: 2026-09-20

Restyled Route Tracking summary using full-width card values/notes and two-column
percentage tiles, avoiding narrow flex content. Customer cards get a dedicated
responsive full-width group, with separate route/distance row. Time cards are now
exactly Journey Duration, Operational Time, Face Time Compliance, OTP Customer
Time, Travel Time, Stationary Time. Every summary card has an action; added timing
and distance details and an OTP-only visit grid. CFT popup also shows variance %.
Operational Time already uses the shared OperationalTime service with all-type
OTP boundaries, same as Dashboard; formula unchanged. Added completed-visit
Productivity alongside unique-customer Efficiency, with Collection and
Orders/Invoices breakdowns. Both use existing positive/nonvoid transaction rules
and exclude toplpo=1 before numerator/denominator calculation. Productivity popup
retains all visits, marks incomplete visits and highlights LPO Customers yellow.
No additional requests or GPS processing introduced. Missing timing now displays
Unavailable rather than being formatted as zero in popup details.
Validation: 20 JS tests passed; Vue script syntax, runtime card order/action
assertions and diff checks passed. Extended PHP tests for productivity repeat
visits, source overlap, incomplete visits, empty totals and LPO exclusion. PHP
suite/build/browser rendering remain unverified without local dependencies.

## Route Tracking popup styling and duration formatting: 2026-09-20

Matched summary, OTP and transaction modal surfaces to Dashboard details:
wide responsive 1250px dialogs, rounded borders, muted backdrop, consistent
headers, padded readable grids and pale table headers. OTP-red and LPO-yellow
row styles remain intact. Summary supports Escape while focused in the popup.
Added shared presentation helper routetracking/duration.js for visit labels,
map popup duration and time summaries. Fractional minutes round before hour/minute
splitting (59.9 becomes 1h 00m); positive durations under a minute display <1 min.
Missing/invalid values display Unavailable. Map HTML escapes the less-than sign.
Underlying duration calculations are unchanged; visit variance text is rounded
to one decimal to prevent floating-point text overflow.
Validation: all 22 JS tests passed, including new duration boundary/invalid-data
cases; Vue script syntax and diff checks passed. Build and browser verification
remain unavailable without local frontend dependencies.

## Route Tracking popup simplification: 2026-09-20

Planned Distance and Actual Distance cards are display-only again: removed their
actions, chevrons and popup detail definitions. Other card actions remain.
The shared CFT/OTP visit grid now shows only Variance (%) rather than both duration
variance and percentage; adjusted explanatory text and empty-row column span.
Vue script syntax, targeted display assertions and diff check passed.

## Route Tracking time popup tables: 2026-09-20

Operational Time, Journey Duration and Travel Time now use dashboard-style
horizontal tables with Route and Salesman columns plus the relevant timing
values. Calculation descriptions appear above the table rather than as tiles.
Stationary Time retains its existing breakdown/expandable-stop layout, as
requested. No metric formulas or requests changed. Vue script syntax, targeted
table checks and diff checks passed; browser/build verification unavailable.

## Travel subtraction correction: 2026-09-20

Journey Duration calculation remains unchanged. New TravelTime service computes
travel as existing journey seconds minus the union of completed customer visit
intervals, stationary intervals and GPS gaps/uncovered GPS boundaries, clipped
to the journey window. Returns disjoint visit (all visits including OTP/LPO),
idle-outside-visits and unknown deductions for the Travel popup. Overlaps count
once. Missing timing returns unavailable; no GPS evidence leaves time outside
visits unknown instead of travel. Residual travel remains an estimate: short
undetected stops and incomplete visit records limit accuracy. Existing stationary,
CFT and operational calculations are unchanged. Reuses fetched GPS data; no extra
queries. Added PHP regression cases for overlap, missing GPS, boundaries and
unchanged duration; updated time-summary expectation for clipped intervals.
Vue script syntax and diff checks passed. PHP tests/build/browser verification
remain unavailable without local runtime/dependencies.

## Compact Route Tracking customer cards: 2026-09-20

Fixed the route-summary-copy > span:last-child rule overriding display:grid on
the percentage breakdown due to CSS specificity. Restricted that text styling
to route-summary-meta, restoring side-by-side Collection and Orders/Invoices
tiles. Reduced customer-card gaps/padding and removed forced 36px note height
and filler rows; reduced tile padding while keeping prominent percentages.
Customer card rows size naturally while retaining aligned heights within each
row. Diff check passed. CSS-only change; browser/build remains unverified.

## Route Tracking idle card only: 2026-09-20

Renamed the Stationary Time card to Idle Time and bound its displayed value to
stationary_without_customer_seconds (the popup's Idle time without customer
visits total). Card note explicitly identifies that measure. The stationary
popup, click action and calculations remain unchanged. Diff check passed.
