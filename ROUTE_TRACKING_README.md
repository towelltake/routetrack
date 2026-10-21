# Route Tracking

## Active customer requirement

All customer data is restricted to `customermaster.activecustomer = 1`, including
Customer Location, Dashboard and Route Tracking plans, visits, OTP, transaction
details/totals and customer-derived timing. This uses the current master status
even for historical dates. Missing master records and other/null statuses are
excluded. Route, company and geographic access restrictions still apply.

`CustomerMaster` applies this rule through a global scope. Raw query builders and
joins must explicitly filter the master alias or restrict customer codes using
`CustomerMaster::query()->select('customercode')` before aggregating.

## JP compliance and Unplanned Customers qualification

Dashboard JP compliance counts a scheduled customer only if at least one completed
visit without matched OTP has actual duration >= that visit's positive recorded
`customervisitlog.cft` target (minutes). Missing, zero and negative targets do not
qualify. Visits are checked separately; short revisits are never added together.
A separate OTP visit does not invalidate a qualifying non-OTP visit. Customers
count once per journey. JP denominator remains all scheduled active customers;
Unplanned Customers uses the same qualification for customers outside the scheduled
plan, divided by all unique visited active customers. Unplanned classification
still requires a journey with a scheduled plan. OTP breakdowns are informational,
can overlap qualified customers, and do not represent qualification.

The two popups list customers by route-status sequence, keeping chronological
visits/revisits together, with check-in/out, planned and actual CFT in minutes,
OTP status, visit qualification and customer qualification. Scheduled customers
not visited appear in JP; unscheduled route-status customers not visited appear
in Unplanned. Visits absent from route status appear last, marked Unsequenced.
Unvisited customers have no recorded visit CFT, so their target is unavailable.
Route Tracking's Unplanned Visits popup uses the same dialog and qualification rows for its selected journey. Raw Customer Coverage, operational timing and productivity rules are unchanged.

## SFA Dashboard filters

### Headline cards

`/dashboard/metrics.json` uses the same access and organisation filters as the map, but aggregates **all** eligible `startendday.routekey` values selected by inclusive `routestartdate`. It does not require GPS. The eight cards are Routes started, Planned coverage, Productive visits, Net sales, Order value, Collections, Customer Face Time, and OTP usage.

- Routes started counts journeys, with distinct routes shown separately. Routes not started is unavailable until an expected operating schedule is agreed.
- Coverage counts distinct planned `(routekey, customercode)` pairs with `schelduledflag = 1`. Repeated visits do not increase coverage. Unvisited customers are pending for open journeys and missed for closed journeys. Missing-plan journeys are identified separately.
- Productivity uses completed visits with a positive-value, non-voided sale or order, linked through `customeroperationscontrol.log_id` and `(routekey, visitkey)`. A visit counts once even if it has both; collection-only visits do not qualify. Missing or invalid visit end timestamps are excluded from the denominator.
- Net sales uses the recorded `invoiceheader.totalinvoiceamount`; Order value uses `salesorderheader.totalinvoiceamount`; Collections uses `arheader.amountpaid`. Voided documents are excluded. Totals are grouped by currency without currency conversion and never multiplied by visit joins. These are journey totals, not transaction-date totals; no additional deductions are applied to recorded invoice totals.
- CFT sums actual completed visit seconds, displayed in minutes. Variance compares only completed visits with a positive customer/channel default; visits with no configured default are excluded from variance.
- OTP includes all types. Since a direct journey key is not verified, events are associated by route and journey timestamps, bounded by recorded closure or the next journey start. Matched visits require the same customer and an event timestamp within the recorded visit. Multiple events in one visit count as multiple events but one matched visit. Events do not imply approval.
- Empty denominators show an unavailable rate, not 0%. Cards have independent loading/error states and discard stale responses after filters change.

### Charts, comparisons and investigation

The dashboard includes five interactive bar charts: customer coverage by journey start date, completed-visit productivity by journey start date, journey-plan exceptions, expected versus actual CFT, and completed-journey time breakdown. CFT and time charts show the top ten routes by their respective time totals; all routes remain available in the performance table. Each chart includes an accessible data table. Chart clicks filter the comparison/review table or open journey details.

The performance table supports Route, Division, Legal Entity, Cluster, Region and Salesperson groupings, search, sorting, optional extra columns and pagination. Coverage/productivity percentages are calculated from summed numerators and denominators, and unique customers are deduplicated within the selected group. Sales, orders and collections remain separated by currency. Cards and table rows open a paginated journey dialog with Route Tracking links and recorded OTP details.

The review queue identifies missed customers, unplanned/out-of-sequence/repeat visits, missing expected CFT and incomplete plans/timestamps. These are review items, not automatic violations. It counts each journey once regardless of the number of flags.

Journey time charts use only closed journeys with valid start/end timestamps. Customer visit intervals are clipped to the journey window and merged to avoid double-counting overlaps. Remaining time is not labelled driving or idle time. Stationary time is unavailable until reliable device stop events exist. Recorded distance currently uses `routeendodometer - routestartodometer` for closed journeys with a positive starting reading and nondecreasing ending reading, displayed in km; it is not OSRM/GPS distance. Missing readings are excluded from averages/totals and the number of usable journeys is shown.

The date presets default to Today; This Week runs Sunday through today and This Month runs from month start through today. From/To filter `startendday.routestartdate` inclusively. There is no separate Map Date. The map shows the latest journey started within the range for each accessible route, with GPS bounded by journey start, recorded end (for closed journeys), and the next journey start. Overnight GPS is included for eligible journeys. Tracking links use that journey's start date. Routes without a matching journey or valid GPS are omitted. Cards, charts and tables aggregate all selected journeys; the map remains a latest-journey view. Legacy API `date` requests are interpreted as a single route-start day.

The Dashboard (`/dashboard`, routes in `routes/dashboard.php`, controller `Dashboard/DashboardController`) shows the latest GPS position per route and operation date. Its Legal Entity, Cluster, Division, Region, and Route filters allow multiple selections and automatically refresh the map. Values within a filter are combined with OR; separate filters are combined with AND. Empty selections mean all accessible values.

- Legal Entity uses distinct nonblank `company.entity` values.
- Cluster joins `company.clustercode` to `clustermaster.clustercode`.
- Division uses active companies (`company.activestatus = 1`). Routes join through `routemaster.cmpycode`.
- Region joins `routemaster.regionmstcode` to `regionmaster.regionmstcode`, displaying `regionmstname`. There is no region active-status column.
- Region narrows Route options independently of the company hierarchy. Selected routes narrow the available Legal Entity, Cluster, Division, and Region options.
- Each dropdown ignores its own selections when calculating options, allowing additional values to be selected. Clearing selections restores options; Reset restores all filters and today's date.

Options and GPS results retain the existing session route/company/subarea access restrictions and the requirement for a route to appear in `routesequence`. Required company and cluster columns/tables must already exist in the legacy database; this change does not import SQL dumps or alter that schema.

The Route Tracking page compares the planned customer sequence with the actual GPS trail and displays every recorded customer visit for a route journey.

## Work-hour GPS tracking and route-event markers

Route Tracking uses `WorkTracking` to read PostgreSQL `trac_routetrack` by
`deviceid`, including points with no routecode or routekey. For today's selected
date (Asia/Muscat by default, configurable with TRACKING_TIMEZONE), the device
comes from `routemaster.device_assigned_id`. Historical dates use the selected
`startendday.dataconrefnumber`; a missing historical device never falls back to
today's assignment. Route access is authorized before this lookup.

The tracking window uses `routemaster.autojp_work_start_time` and
`autojp_work_end_time` on the selected operation date. An end earlier than the
start crosses midnight. Missing/invalid/equal hours make tracking unavailable.
Historical work hours currently use the route's current configuration; no
historical schedule snapshot has been identified. GPS timestamps use recorded
`date + time`, not the audit creation time. Accuracy filters still apply.

Map markers distinguish first observed Track Start (T), Last Known Location (L),
Route Start (S) and Route End (E). Route events use the nearest measured GPS point
within the configured maximum gap (120 seconds by default); the popup includes
both event and observed GPS timestamps. Missing nearby GPS is reported instead
of inventing event coordinates. First/last tracking markers remain available
when no route has started.

The normal actual trail is restricted to the selected route's start/end window
inside tracking work hours. For an open route it continues through the available
work-hour readings. Before / after route toggles a purple dashed GPS layer within
the work window, including all available work-hour tracking when no route start
is recorded. This layer is drawn from measured GPS and split across long gaps;
its visibility does not change idle totals. Points outside work hours are omitted.

Stationary detection, idle totals and their popups now use the full tracking
window, including time before route start and after route end. Customer-visit
overlap is excluded from idle outside visits. Dashboard idle detail aggregates
each selected route/start-date workday once so repeated journeys do not multiply
the same window. The Dashboard still selects its workdays from eligible journeys
in the selected start-date range. No GPS/missing device means unavailable idle,
not fabricated time. Existing CFT, operational-time and journey-duration formulas
remain separate. This supersedes older route/date-only GPS and journey-bound idle
notes below.

## Route Tracking customer performance cards

Route Tracking reuses Dashboard's customer performance cards for its selected
recorded journey: Coverage, JP compliance, Unplanned Customers, Efficiency,
Productivity and OTP usage with comment counts. Current Route Status remains
at the left in place of Dashboard Route Start Compliance. Planned/Actual Distance
are displayed beneath GPS timestamps in the Time section's Actual GPS Tracking
card. No separate Distance block is shown.

Customer popups use /route-tracking/customer-details.json with the displayed
routecode, operation date and routekey. Route/company/subarea access is checked
server-side and the routekey must belong to the authorized route. Results stay
on that journey, including when a newer journey appears on the same date; they
do not aggregate Dashboard's route-start-date range. Shared Dashboard formulas
and popup qualification rules apply. Missing recorded journeys show unavailable
customer metrics.

## Data flow

```text
Selected routecode + operation date
    |
    +-- startendday
    |     routecode/date -> routekey, routeclosed
    |
    +-- routesequencecustomerstatus
    |     routekey -> planned customer sequence
    |
    +-- customermaster
    |     customercode -> customer name and fixed coordinates
    |
    +-- customervisitlog
    |     routekey -> every customer visit, ordered by logkey
    |
    +-- customeroperationscontrol
    |     routekey + log_id = customervisitlog.logkey -> visit coordinates
    |
    +-- PostgreSQL trac_routetrack
          routecode + date -> actual GPS trail and visit-location fallback
```

## Planned customers

`routesequencecustomerstatus` remains the planner source. Scheduled rows are filtered by `routekey` and `schelduledflag = 1`, joined to `customermaster` by `customercode`, and ordered by `sequencenumber`.

The planned road geometry is calculated between consecutive customer coordinates using OSRM. If an OSRM leg fails, that leg uses a straight-line fallback.

## Customer visits

`customervisitlog` is filtered by the selected `routekey`. Every row is returned, including repeated visits to the same customer, and the results are ordered by `logkey`.

Visit start, end, and duration use:

- `logstartdate` and `logstarttime`
- `logenddate` and `logendtime`
- duration = end timestamp minus start timestamp

If the end date or time is unavailable, Visit End and Visit Duration are omitted.

Planned customer face time uses `customervisitlog.cft` from each individual visit, in minutes. Missing, zero or negative values resolve to zero, without fallback to customer master or cluster mapping. Repeat visits retain their own recorded targets, including historical visits. Dashboard, analysis, Route Tracking and their popups share `CustomerFaceTime::plannedMinutes`. Existing completed-visit counting and OTP exclusions remain in place. Actual face time still uses recorded visit timestamps.

## Planned Not Visited

The application takes the distinct `customercode` values in `customervisitlog` for the route and compares them with the planned customers from `routesequencecustomerstatus`.

```text
Planned Not Visited = planned customers - distinct logged customer codes
```

## Visit marker coordinates

Each visit uses the first acceptable location in this order:

1. `customeroperationscontrol.latitude/longitude`, linked where `customeroperationscontrol.log_id = customervisitlog.logkey` for the same `routekey`.
2. `customermaster.fixedlatitude/fixedlongitude`.
3. The nearest `trac_routetrack` GPS point to the visit start timestamp, limited to 5 minutes.

Coordinates must be non-zero and inside the configured Oman bounds. If no acceptable location is found, the visit still appears in the Customer Visits list but has no map marker.

## Actual GPS route

The actual trail comes from PostgreSQL `trac_routetrack`, filtered by `routecode` and operation date. Invalid and duplicate GPS points are removed, implausible speed jumps are filtered, and the remaining points are sent to OSRM Map Matching. If map matching fails, the cleaned raw GPS trail is displayed.

The Raw Coordinates toggle is off initially. When enabled, it independently displays the complete cleaned GPS trail as a dashed dark-orange line without changing the OSRM route layer.

The first GPS point is Route Start. The final GPS point is Last Known Location.

## Route status

`startendday.routeclosed` controls the displayed status:

- `0`: Live
- `1`: Closed

## UI behavior

- Planned Visits: one blue marker and one list entry per planned customer. This layer is visible initially.
- Customer Visits: one green marker and one list entry per `customervisitlog` row; repeated customers appear repeatedly. This layer is initially off.
- Planned Not Visited: an on/off highlight that changes the existing unvisited planned markers to grey and filters the list. It does not create duplicate markers.
- Planned and actual route lines are visible initially and can be toggled independently.
- Customer codes shown in lists and popups use `customermaster.alternatecode`; the internal `customercode` remains the linking key.
- If no scheduled planner rows exist, a Route Sequence Data Not Available/Uploaded warning is shown. Planned Route, Planned Visits, and Planned Not Visited are disabled while actual GPS and Customer Visits continue to load; Customer Visits becomes the active list and marker layer.
- If fewer than two usable GPS points exist, a Route Track Data Not Available warning is shown. Actual Route, Route Start, and Last Known Location are disabled while planned and visit data remain available.

## Deployment

### Route tracking stationary markers

Route Tracking shows tinted geographic circles for GPS-detected stationary periods. The legend toggles the layer. A Stationary tab in the side panel lists each period with duration, time range, visit overlap, and legacy accuracy status; selecting a row zooms to its circle, while selecting a circle reveals its row. Popups show the complete details. Stationary time can include customer service; it is not automatically non-working time.

Configuration is in `config/tracking.php`, with these optional environment overrides (defaults apply without editing `.env`):

```dotenv
TRACKING_STATIONARY_MINUTES=5
TRACKING_STATIONARY_RADIUS_M=30
TRACKING_STATIONARY_MAX_GAP_SECONDS=120
TRACKING_MAX_ACCURACY_M=50
```

After changing environment settings, run `php artisan config:clear` (or rebuild the configuration cache with `php artisan config:cache` in deployments that cache config). No settings table is required.

Detection uses ordered device `date + time` readings for the selected route/date before distance downsampling. Points remain within the configured radius of the first point; two consecutive outside readings confirm departure. Duration ends at the last inside observation, never at the current clock time. A gap exceeding the configured limit between usable readings breaks the period. Duplicate timestamps cannot increase duration. The displayed path and visit GPS fallback also exclude known accuracy values greater than or equal to the maximum; null/missing accuracy and provider remain supported, including source tables without those columns. Provider is retained but is not used as a quality filter.

Devices should send a fresh GPS heartbeat about every 30 seconds even while stationary. Movement-only uploads cannot establish stationary duration across silent intervals. Rejected low-quality readings cannot bridge gaps; no detected stops does not establish continuous movement. The five-minute default is a heuristic, and historical gaps remain unknown. This feature uses the existing Route Tracking calendar-date scope and does not modify Dashboard metrics.

Consecutive usable readings separated by more than `TRACKING_STATIONARY_MAX_GAP_SECONDS` are also returned as bounded GPS-unavailable gaps. Route Tracking shows a red warning flag at the last usable coordinate and lists the gap in a GPS Gaps side-panel tab with the last-signal time, recovery time, and full interval duration. The application cannot determine whether a gap was caused by coverage, the device, permissions, GPS settings, or deliberate action. Gaps before the first reading and after the last reading are not inferred because they have no recovery boundary in the selected data.

Route Tracking uses the Dashboard's authorised Legal Entity, Cluster, Division, Region, and Route metadata for dynamic filters. Organisation filters allow multiple values; Route is a required single selection and Operation Date is a required single date. Its compact journey cards cover plan status and coverage, planned/actual distance and time, face/travel/stationary/GPS-gap time, unplanned visits, and Sales, Orders, Collections, and Returns. Transaction cards exclude voided documents and deduplicate transaction keys. Returns combine `invoiceheader.totalreturnamount` (good returns) with `invoiceheader.totaldamagedamount` (bad returns).

No database migration is included because the required tables and `log_id` column already exist in the source database.

```bash
npm run build
php artisan optimize:clear
```
