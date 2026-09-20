# TRAC / routeTrack project memory

Last inspected: 2026-09-16. Snapshot: branch `main`, commit `61ffe82`
(merge of PR #9). Working tree was clean before this documentation task.
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
