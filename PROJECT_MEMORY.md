# TRAC / routeTrack project memory

## Route Tracking dismissible flash messages: 2026-10-21

Removed page-heading GPS tracking summary, route-marker notices and Planned route
vs actual GPS points subtitle. Removed permanent inline error/quality-warning
blocks. Route Tracking now shows warning/error flashes at top right, each with a
close button and 15-second automatic dismissal (paused on hover/focus). Notices
include tracking availability, missing route-event GPS and geometry fallbacks.
New comparisons/reset replace stale flashes; timers are cleared on unmount.
Existing tracking times remain available in the GPS card and marker popups.
Production build and diff check passed; browser visual verification outstanding.

## Refined Route Status timing and distance details: 2026-10-21

Current Route Status now shows available recorded route start/end times and dates
in compact icon rows. Distance has a km heading and aligned Planned/Actual tiles,
replacing wrapping label/value lines. Long values can wrap within tiles; unavailable
actual distance shows N/A. Uses existing route_details timestamps; no timing or
distance calculation changes. Production build and diff check passed; live
browser visual verification outstanding.

## Separate before/after GPS toggles: 2026-10-21

WorkTracking outside geometries now carry tracking_period=before/after using
recorded GPS timestamps versus route event times. Route Tracking has separate
Before Route Start (purple) and After Route End (orange) layers/toggles, both
visible by default; singleton dots match the respective color. Open routes have
no after geometry and their disabled control explains that an end time is needed.
No recorded start keeps available GPS in the before layer. Gap handling, full-day
tracking bounds and idle calculations are unchanged. README and PHP regressions
updated. Production build, PHP syntax, three direct temporal-layer checks and
diff check passed. Full Pest/browser verification remain outstanding.

## Auto-checkout badge in map-side visits panel: 2026-10-21

Route Tracking customer visit rows beside the map now display the existing amber
Auto-checkout badge beside the customer name when auto_checkout is true. Uses
the existing customervisitlog.autocheckout flag; planned-customer rows and manual
checkouts have no badge. Production build and diff check passed; browser visual
verification outstanding.

## Before/after toggle retains isolated readings: 2026-10-21

Found toggle was enabled only for drawable multi-point segments, dropping isolated
before/after observations or readings separated by gaps. WorkTracking now builds
outsideGeometries with Point geometry for single readings and LineString geometry
for connected observations. Map renders singleton points as purple dots. Temporal
classification remains strictly before route-start/after recorded closed-route
end; no spatial matching. Long gaps still are not connected. Disabled toggle
now explains when no outside readings exist for the selection. Added regression
for isolated points, gaps and exact timestamp boundaries. Production build,
three direct PHP geometry checks, PHP syntax and diff checks passed. Full Pest
and browser validation remain outstanding.

## Route event times in GPS marker popups: 2026-10-21

Track Start Time marker popup now also shows recorded Route Start Time when
available. Last Known Location popup similarly includes recorded Route End Time.
Values use planned.route_details start_time/end_time rather than requiring a
nearby route-event GPS marker. Missing route timestamps omit the additional row.
Existing GPS timestamps and separate route markers remain unchanged. Production
build and diff check passed; live browser visual verification outstanding.

## Distances moved into Current Route Status: 2026-10-21

Route Tracking now shows Planned/Actual Distance at the bottom of the Current
Route Status card, separated by a divider with blue values. Removed distance
rows from Actual GPS Tracking, leaving its start/end timestamps and note so the
Time row stays compact and balanced. Distance sources and calculations are
unchanged. Production build and diff check passed; browser visual verification
remains outstanding.

## Full-width selected Customer Performance: 2026-10-21

DashboardCards now has a native single-group layout when onlyGroups contains one
entry: one full-width grid column, no outer margins and full available height.
This prevents the selected Customer Performance section retaining the shared
12-column dashboard allocation in Route Tracking. Route Tracking uses a bounded
170?220px Current Route Status column and gives all remaining width to Customer
Performance; mobile still stacks. Default Dashboard layout remains unchanged.
Production build and diff check passed; live browser visual check outstanding.

## Remove configured work-hour restriction from tracking: 2026-10-21

User explicitly removed all routemaster hour restrictions. WorkTracking now loads
all usable GPS for selected device/calendar date, and observedBounds returns the
first/last timestamps. Neither starttime/endtime nor autojp hours are queried.
Today queries only current device assignment; historical lookup uses startendday
recorded device and never substitutes current assignment. No GPS gives null
bounds; one GPS gives identical start/end and unavailable idle evidence.

Map's purple before/after-route GPS layer is enabled by default, keeping every
available time segment visible while retaining the toggle. GPS start/end card,
tracking headers, Route Tracking idle/stationary popup and Dashboard idle use
observed first/last bounds. Removed configured-hour wording from UI/README.
Date/device, coordinate/accuracy and gap restrictions remain; adjacent dates
are excluded. Dashboard continues one observation window per selected route/date.
Existing journey/CFT/operational/travel formulas remain unchanged. No database
changes. This supersedes all prior configured work-window memory entries.

Updated regressions include no schedule columns at all, early/late recordings,
wrong devices, adjacent date exclusion, empty and single-point bounds, historic
device identity and updated observed-range idle expectations. Production build,
20 dashboard JS tests, PHP syntax and direct observation-bound checks passed.
Full Pest and live database/browser verification remain outstanding without local
vendor dependencies/configuration.

## Fix production tracking work-hour columns: 2026-10-21

Production error confirmed WorkTracking queried nonexistent autojp_work_start_time
and autojp_work_end_time. Corrected loader to routemaster.starttime/endtime,
verified in the legacy schema (TIME fields, default 08:00:00/17:00:00). Previous
implementation incorrectly inferred database columns from model fillable names.
Updated WorkTracking and DashboardOutsideVisits regression fixtures to use the
real legacy columns and corrected README. Device identity rules and authorized
route access remain unchanged. No schema changes or database writes required.
This supersedes the autojp work-hour field names in older memory entries.
Validation: PHP syntax, direct work-window checks and diff check passed. Full
Pest execution is unavailable without vendor dependencies. Production recheck
remains outstanding after deploying app/Services/WorkTracking.php.

## Dashboard-style Route Tracking customer performance: 2026-10-21

Route Tracking now reuses DashboardCards with onlyGroups=['customers'] to match
Dashboard Customer Coverage above JP/Unplanned, plus Efficiency, Productivity
and OTP usage/comment breakdowns. Current Route Status replaces the Dashboard's
Route Start Compliance at left and retains the journey-details action. Removed
the prior Customers and standalone Distance groups from the tracking summary.
Planned/Actual Distance appear beneath GPS start/end in the final Time card,
separated by a divider; responsive layouts and existing Time/Transactions remain.

compare.json returns DashboardMetrics for precisely the selected recorded journey
and selected_scope (routecode, operation date, routekey). A new authenticated
Route Tracking customer-details endpoint validates that scope, authorizes route,
company and subarea access, and constrains the requested routekey to that route.
DashboardCustomerDetails supplies identical popup rules. Pinning the displayed
routekey prevents a later same-day journey replacing the popup's selected journey.
CustomerDetailsDialog has a configurable endpoint with unchanged Dashboard default.
Unplanned cached popup remains supported. No recorded journey yields unavailable
customer metrics. Previous timing/CFT/GPS and qualification rules remain.

Production build, 20 dashboard JS tests, PHP syntax and diff check passed. Added
PHP regression cases for displayed journey pinning and route/company/geographic
access checks; execution is outstanding without vendor dependencies. Live browser
and business database verification remain outstanding.

## Actual GPS times card: 2026-10-21

Added Actual GPS Tracking as the last Route Tracking Time card. Shows GPS Track
Start Time and GPS Track End Time from the first/last measured work-window GPS
observations, including dates for overnight clarity. Uses tracking_start and
last_known_location timestamps; no scheduled or route-event timestamp fallback.
Absent readings show Unavailable. Existing responsive six-column desktop Time
layout now fills all six positions. Production build and diff check passed;
live browser visual verification remains outstanding.

## Work-hour tracking and historical devices: 2026-10-21

Implemented WorkTracking service for device-based work-hour GPS. Today uses
routemaster.device_assigned_id; historical selected journeys use startendday.
dataconrefnumber, verified as device ID in legacy schema/procedures. Historical
missing device does not fall back to current assignment. GPS query filters by
deviceid and configured autojp_work_start_time/end_time, with overnight support,
strict time validation and existing accuracy rules. TRACKING_TIMEZONE defaults
to Asia/Muscat for determining today. All callers retain route authorization.
Historical work hours use current route settings; no schedule snapshots found.

RouteTrackingController uses all work-window points for stops/gaps, and selected
route start/closed end to split the displayed actual trail from before/after
tracking. Work data is available before a route starts. Map shows Track Start,
Last Known Location and separate Route Start/End markers; route event markers
require a GPS observation within max configured gap and disclose GPS timestamp.
Purple dashed Before / after route toggle shows only work-hour observations,
not GPS outside configured hours. Toggle never changes idle calculations.
DashboardOutsideVisits uses the same loader, clips visits to work hours and
counts one route/start-date work window across repeated selected journeys.
Dashboard workday scope still derives from eligible selected journeys, so it
does not introduce unstarted-route workdays. Existing journey/CFT/operational/
travel formulas are preserved. Missing tracking/insufficient GPS yields unknown
idle. UI exposes work-window and unavailable-marker information. README updated.

Validation: production build, 20 dashboard JS tests, ten direct PHP work-window/
event-marker cases, all changed/new PHP syntax and diff checks passed. Added
WorkTrackingTest for device reassignment, no route start, wrong devices, missing
historical identity, exact work boundaries, overnight windows and marker gaps;
adapted stationary/idle PHP regressions. Full PHP regression execution remains
outstanding without vendor dependencies; live DB/browser validation required.
This supersedes earlier Route Tracking routecode/calendar-date GPS and
journey-window idle descriptions. No schema/database mutations performed.

## Automatic checkout popup badges: 2026-10-21

Dashboard Face Time Compliance and Productivity detail rows, plus Route Tracking
Face Time Compliance/OTP time and Productivity popups, display a small amber
Auto-checkout badge beneath a recorded checkout when customervisitlog.autocheckout
is 1. The schema confirms autocheckout (not the request's autockeout spelling).
DashboardCustomerDetails and RouteTrackingController expose boolean auto_checkout;
manual/null flags do not display a badge. Duration, CFT, qualification, productivity
and access logic are unchanged. Updated PHP fixtures and regressions for automatic
and manual checkout flags. PHP syntax, 20 dashboard JS tests and diff check passed;
production build passed. Full PHP tests remain unavailable without vendor
 dependencies; live browser/database verification remains outstanding.

## Compliance popup status styling: 2026-10-21

Shared JP/Unplanned CustomerDetailsDialog now gives unvisited customer rows a
light red background and leaves their OTP cells empty. Qualified visits show a
green circle-check; customer qualification uses green check/red cross icons
with accessible labels and hover titles. Nonqualifying visit reasons remain
visible. Applies to Dashboard and Route Tracking's shared Unplanned popup.
Production build and diff check passed; live browser visual check outstanding.

## JP route-status base clarified: 2026-10-21

Verified JP uses distinct scheduled (schelduledflag = 1) active customers from
routesequencecustomerstatus per journey as the denominator for both percentages.
Main numerator requires one completed non-OTP visit meeting a positive recorded
CFT; lower numerator counts base customers with matched OTP, without requiring
CFT. Existing formulas already matched the request. Clarified DashboardCards
labels/help and added a JP popup summary of base, qualified and unique OTP
customer counts. A customer with separate qualifying and OTP visits can appear
in both counts. Production build and diff check passed; browser verification is
outstanding. Backend formulas and existing access restrictions are unchanged.

## Shared Unplanned Customers popup in Route Tracking: 2026-10-21

Route Tracking's Unplanned Visits card now opens the same CustomerDetailsDialog
used for Dashboard JP/Unplanned compliance. computePlannedRoute includes scoped
unplanned_compliance data from DashboardCustomerDetails for its selected journey;
the dialog consumes cached data, preserving Route Tracking operation-date journey
selection without requesting Dashboard's separate start-date range endpoint.
Shows route-status sequence, individual visits/revisits, actual/planned CFT,
qualification, OTP markers, qualification filters and unvisited unscheduled
route-status customers. Dashboard already used this shared compliance layout.
Production build, 20 dashboard JavaScript tests, controller PHP syntax and diff
checks passed. Full PHP tests remain unavailable without vendor dependencies;
live browser/database verification remains outstanding.

## CFT qualification for dashboard compliance: 2026-10-21

Implemented positive-target CFT qualification for Dashboard JP compliance and
Unplanned Customers. CustomerFaceTime::qualifies is shared by metric aggregation
and popup rows: one complete non-OTP visit must meet its own recorded positive
CFT target. User confirmed missing/zero targets do not qualify. Distinct customers
count once per journey; short visits cannot combine. A qualifying non-OTP revisit
can qualify a customer with another OTP visit. Existing OTP breakdowns remain
informational and may overlap qualified counts. JP and unplanned denominators,
raw coverage, productivity, operation dates and access restrictions are preserved.

DashboardCustomerDetails::complianceRows orders customers by route-status sequence
and each customer's visits chronologically. Includes revisit numbers, check-in/out,
actual/planned CFT in minutes, OTP markers, per-visit reasons and per-customer
qualification. Unvisited scheduled customers appear in JP, unvisited unscheduled
route-status customers in Unplanned; their CFT is unavailable without a visit.
Unsequenced visited customers appear last. Dialog has qualification filters and
unique customer qualification counts. DashboardCards help and README updated.

Validation: final production build, 20 dashboard JS tests, 10 directly executed
PHP qualification cases, changed PHP syntax checks and diff check passed.
Updated PHP regressions cover target boundaries, missing/negative/zero targets,
OTP/non-OTP revisits, sequence order and unvisited customers. Full Pest regression
execution is outstanding because vendor dependencies are absent. Live browser
and business database verification remain outstanding. This supersedes earlier
notes that any OTP visit places a customer only in the OTP group for compliance.

## Planned CFT from recorded visits: 2026-10-21

Implemented planned CFT sourcing exclusively from customervisitlog.cft (minutes)
for Dashboard metrics/analysis, customer detail dialogs, Route Tracking visits,
CFT summaries and planned face-time totals. CustomerFaceTime::plannedMinutes
normalizes each visit independently: null/non-numeric/nonpositive values become
zero, positive fractional minutes are retained. No customer master or cluster
mapping fallback remains. Repeated visits can have distinct targets; historical
visits use recorded values. Completion rules, OTP exclusions, actual durations,
variance formula, average/totals display and access restrictions are unchanged.
Updated screen help, ROUTE_TRACKING_README.md and PHP regression fixtures for
recorded targets, missing values, decimals, repeated visits and journey scope.
This supersedes the September 29 customer-master/cluster fallback decision.
Validation: PHP syntax checks on all six changed PHP files, all 20 dashboard JS
tests, production build and diff check passed. PHP unit execution is blocked by
missing vendor dependencies (vendor/bin/pest absent); local PHP 8.4.15 was found
under WAMP and emits OCI/Xdebug configuration warnings. Live browser/database
verification and execution of the updated PHP regressions remain outstanding.

## Remove dashboard and tracking metrics: 2026-10-21

Removed Route Close Compliance from the Dashboard Route Start Compliance card
and its help text. Removed Dashboard Total Duration and Route Tracking Journey
Duration cards. Dashboard groups retain the remaining cards in their existing
order, with five desktop Time columns. Backend calculations and access rules are
unchanged. Relevant files: routelocation/DashboardCards.vue and
routetracking/Index.vue under resources/js/views.
Validation: all 20 dashboard JavaScript tests, npm.cmd run build and git diff
--check passed. Live browser visual verification remains outstanding.

## Towell login watermark hidden: 2026-10-01

Login.vue temporarily hides the Towell watermark with display: none on its
scoped class, on all screen sizes. Asset and positioning styles remain for
restoration. Diff check passed; production build passed. Browser visual
verification remains outstanding.

## Login visual refinement: 2026-10-01

Login.vue uses a 44/56 desktop split and a centered 400px form to reduce the
gap between the illustration and login. Reduced Enhance logo size and heading
spacing, softened and reduced the Towell watermark, and constrained company
footer width. Inputs have subtle borders, rounded corners and brand-red focus
states; the login button is solid red with visible keyboard focus and a readable
disabled state. Actions wrap on narrow screens. The password-reset divider only
renders when reset is available. Authentication behavior is unchanged.
Validation: production build (npm.cmd run build) and git diff --check passed.
Live browser visual verification remains outstanding.

## Dashboard Time averages and totals: 2026-10-01

DashboardCards has an Average / Totals switch beside Time, defaulting to Average
on mount. All five headline durations plus Actual/Planned CFT divide unrounded
minutes by metrics.routes_started before h:mm formatting. This is the existing
filtered routecode/start-date count, summed across the selected inclusive date
range, not journeys_started or only measured journeys. Missing durations stay
unavailable; zero/absent route starts yield unavailable averages. CFT variance,
journey availability counts, other card groups and drilldown records are unchanged.
Idle minutes use the same divisor when their asynchronous response arrives.

The switch displays its denominator and has keyboard/ARIA support. Time cards
perform a staggered 360-degree rotation on mode changes; reduced-motion users
get an immediate update. No animation on initial load. Mode persists while
filters change, without saving across page mounts. Helper: routelocation/time-display.js.
Validation: all 25 dashboard JS tests (including five new averaging cases),
production build and diff check passed. Browser visual verification outstanding.

## Sidebar logo and header spacing: 2026-10-01

Moved eg.png from Authenticated.vue's header into the shared Sidebar.vue home
link, replacing TRAC 1.0. Logo remains 44px high and header height is unchanged.
Authenticated header content uses full width with 8px inline-start padding,
removing the earlier narrow-layout gap beside the hamburger. Production build
and diff check passed; live browser verification remains outstanding.

## Towell login watermark: 2026-10-01

Login.vue uses public/assets/TOWELL LOGO.png as a decorative original-color watermark
behind the right login panel, upright and fully visible at a reduced responsive
size with an inset from the right edge. Opacity is 8% on desktop and 4% on smaller screens, with no pointer
events or accessibility announcement. The white background and form layout
remain. Production build and diff check passed; browser visual check outstanding.

## Fixed light appearance: 2026-10-01

Removed dark-mode and color/settings dropdowns from the shared sidebar.
BaseLayout now applies light mode and the default palette on every mount,
overriding saved preferences; it no longer listens for OS theme changes. Store
actions enforce those defaults even for older callers. Removed alternate color
stylesheet imports from app.js. The existing dark sidebar styling is part of the
default light layout and remains; its appearance controls are removed.
Production build, diff check and direct Pinia assertions for forced light mode,
saved preference cleanup and preservation of unrelated HTML classes passed.
Live browser verification remains outstanding.

## Company logos in login left footer: 2026-10-01

Login.vue now shows three public/assets/Companies logos beneath enhance.png,
confined to the desktop left panel. Updated filename order is Enhance Group
(1 EG .png), Fairtrade, MDC. Logos use three equal columns,
contain sizing and company-name alt text. Illustration height reserves space for
the footer; white background and existing mobile-hidden panel behavior remain.
Production build and diff check passed; live browser verification outstanding.

## Login illustration panel: 2026-10-01

Replaced the login left panel's marketing copy, desktop footer and dark grid
background with public/assets/enhance.png on white. The illustration is centered,
scales within the existing desktop column and viewport without cropping, and
retains the previous mobile-hidden behavior. Removed unused panel CSS. Login
form and eg.png logo remain. Production build and diff check passed; live browser
visual validation remains outstanding.

## Enhance Group branding and dashboard heading: 2026-10-01

Login.vue replaces the Log In heading with the supplied public/assets/eg.png
logo, centered at 180px wide above the welcome message. Authenticated.vue shows
the same logo immediately after the hamburger button at 44px high within the
existing 4rem header; header height is unchanged. Both images have Enhance Group
alt text. Dashboard Index.vue removes the Field performance subtitle. Production
builds and diff checks passed; live browser visual validation remains outstanding.

## All operation links for productivity and efficiency: 2026-09-30

Dashboard and Route Tracking now inspect all distinct positive visitkeys linked
through the same routekey and customeroperationscontrol.log_id to a visit log.
Any qualifying invoice, order or collection makes the completed visit productive;
a newer operation without transactions no longer hides older transactions. Each
visit counts once and each productive customer counts once per journey. Existing
completion, positive amount, void, active-customer, LPO and access rules remain.

DashboardMetrics, DashboardAnalysis and DashboardCustomerDetails use grouped
operation links; drilldown document counts deduplicate repeated visitkeys. Route
Tracking retains the latest operation for coordinates and exposes all visitkeys
for transaction loading. Its popup requests details using the document's visitkey.
Financial totals and timing formulas are unchanged.

Added PHP regressions for older productive/latest empty operations, split
collection and sales keys, duplicate links, journey isolation, drilldown counts
and Route Tracking key extraction. Twenty JavaScript tests, production build and
diff check passed. PHP regression execution remains outstanding: php is not on
PATH and vendor dependencies are absent. Live browser/database validation remains
outstanding.

## Remove CFT status caption: 2026-09-29

Removed the planned-time status caption (above/below/on plan and missing-plan
caption) from Dashboard and Route Tracking Face Time Compliance cards. Dashboard
omits empty note elements; percentage and actual/planned values remain visible.
Production build and diff check passed.

## Compact stacked CFT rows: 2026-09-29

Face Time Compliance on Dashboard and Route Tracking now places Actual CFT above
Planned CFT in compact rows, labels left and values right. Values use 14px text
and 4px row gaps to fit the existing card space; variance remains the main metric.
This supersedes the side-by-side layout below. Build and diff check passed;
live browser layout verification remains outstanding.

## Face Time card metric hierarchy: 2026-09-29

Dashboard and Route Tracking now show variance percentage first as the primary
Face Time Compliance metric (23–28px), retaining negative red/positive green.
Actual and planned CFT sit side by side below a divider with smaller 14–18px
values, equal-width columns and wrapping protection for long durations.
Production build and diff check passed; live browser verification outstanding.

## Face Time variance color: 2026-09-29

Dashboard and Route Tracking Face Time Compliance cards show negative variance
percentages in red; positive values retain green. Styling uses the numeric
variance, leaving calculations unchanged. Production build and diff check passed.

## Planned CFT customer and cluster fallback: 2026-09-29

Dashboard, Route Tracking and CFT popups now share `CustomerFaceTime::minutesFor`.
It uses positive `customermaster.customerfacetime`, otherwise positive
`customerclustermapping.cft`, matching BOTH master `DivisionCode` to mapping
`divisioncode` and master `channel` to mapping `channel`. These are text fields;
numeric `channelcode` is not the join. Missing/nonpositive targets resolve to zero
minutes. Confirmed against user-provided `sfa_struct1.sql`; no schema was imported
or changed. This supersedes older notes about visit-log or channelmaster targets.

Current master settings apply to historical visits. Existing journey/date/access
scope, active-customer filtering, visit counting, actual timing and OTP exclusions
are preserved. Dashboard analysis consumes the same resolved targets. Screen and
popup help text describes the fallback. Regression fixtures now configure master
targets; tests cover override precedence, matching both keys, absent/nonpositive
targets, popup/card consistency and OTP exclusion.

Validation: 20 JavaScript tests, production build and diff check passed. PHP tests
could not run because PHP and vendor dependencies are unavailable locally; PHP
regression execution and live database/browser verification remain outstanding.

## Customer Coverage clickable visit details: 2026-09-29

Renamed Total Visits to Customer Coverage and wired click/Enter/Space to the
existing customer-details popup using type coverage. It retains session access
and selected journey-start-date filters and active-customer filtering. Shows all
visit records including repeats, incomplete visits and LPO customers, with actual
visit date, route, customer name/code, full check-in/checkout timestamps and
per-visit OTP/Non-OTP status using existing OTP matching. Missing checkout is
Not recorded. The card remains unique customers per journey.

Revisit #2/#3/etc. is assigned chronologically per journey/customer before UI
filtering/pagination. Repeat rows and badges are lavender. Existing date/journey,
search and pagination controls apply, with OTP/Non-OTP tabs added for this type.
PHP regression covers repeats, journey reset, mixed OTP, overnight checkout,
incomplete and LPO visits, inactive filtering and empty journeys. All 20 JS tests,
production build and diff checks passed; PHP tests and live browser verification
remain unavailable locally without PHP/vendor.

## Total Visits alignment and subtle tint: 2026-09-29

Total Visits now uses three equal-width, centered columns with consistent padding
and aligned value tops. Added a compact users icon beside the heading and a subtle
blue-tinted card surface with a slightly stronger count tile. Tightened outer gaps
and padding to retain the compact stack. Production build and diff check passed;
live browser verification remains outstanding.

## Remove JP compliance footer: 2026-09-29

Removed the pending/missed footer line (including its optional no-plan text),
unused footer markup/styles and the empty final row in the compact coverage cards.
Underlying counts remain unchanged. Production build and diff check passed.

## Compact Total Visits / JP / Unplanned stack: 2026-09-29

JP compliance and Unplanned Customers now opt into stacked-coverage styling:
removed their 360px minimum, reduced padding/icon/gaps and breakdown spacing,
and moved breakdown/footer into consecutive rows. The Total Visits header and
the pair share the existing customer section height instead of adding a header
above two full-height cards. Content can grow when needed to avoid clipping.
Other cards retain their existing minimum height. Production build and diff
check passed; live browser layout verification remains outstanding.

## Total Visits summary above planned/unplanned cards: 2026-09-29

Added a horizontal Total Visits card spanning JP compliance and Unplanned Customers.
Shows total unique customers visited per journey and blue without-OTP/orange
with-OTP percentages with numerator/denominator counts. DashboardMetrics exposes
unique_visited_with_otp and unique_visited_without_otp from existing visited and
matched-OTP customer sets. Any matched OTP places that journey/customer only in
the OTP group, including mixed repeat visits; unmatched OTP events do not create
visits. Includes active LPO/incomplete visits and journeys without plans. Empty
totals show zero counts with unavailable percentages. Other card formulas remain.
CSS grid areas preserve placement above the pair at desktop/tablet/mobile widths.
Added PHP coverage for repeated/mixed OTP customers, unmatched events, LPO and
incomplete visits, absent plans and empty periods. All 20 JS tests, production
build and diff check passed. PHP tests and live browser verification remain
unavailable locally without PHP/vendor.

## JP compliance pending/missed footer: 2026-09-29

Moved JP compliance's pending/missed line (including journeys without a plan when
present) below the OTP breakdown at the bottom of the card. DashboardCards uses
a dedicated footer field and final grid row. Calculations are unchanged.
Production build and diff check passed; browser verification remains outstanding.

## LPO exclusion count on performance cards: 2026-09-29

Efficiency and Productivity display "N LPO customers excluded" below their
breakdowns, aligned to the card bottom. DashboardMetrics.lpo_customers_excluded
is all unique visited journey/customers minus eligible unique journey/customers,
using the existing toplpo exclusion. Repeats count once within a journey; counts
include incomplete LPO visits and respect active-customer filtering. Zero is shown,
and singular wording is used for one. Existing formulas are unchanged.
Extended the existing LPO regression for zero, repeated-customer and all-excluded
counts. All 20 JS tests, production build and diff checks passed. PHP tests remain
unexecuted because PHP/vendor are unavailable; browser verification is outstanding.

## OTP percentage uses unique visits: 2026-09-29

DashboardCards OTP usage now divides total OTP events by the existing
all_unique_visited_customers metric, not total_visits. Each customer counts once
per journey; repeats within that journey do not increase the denominator. The
same customer in another journey counts again, and ranges sum journey counts.
Active-customer filtering, incomplete visits and LPO inclusion are preserved.
The numerator still counts all OTP events; zero unique visits is unavailable.
Updated the visible count note and tooltip. All 20 JavaScript tests, production
build and diff checks passed; live browser verification remains outstanding.

## Active customers throughout the application: 2026-09-29

Only customers whose current customermaster.activecustomer equals 1 are included,
also when viewing historical dates. CustomerMaster now has an active_customer
global scope, covering Customer Location and customer lookups. Raw customer
queries explicitly filter joined master aliases or use the scoped model as a
customercode subquery; Eloquent scopes do not automatically apply to raw queries.
DashboardMetrics, DashboardCustomerDetails, DashboardOutsideVisits and
RouteTrackingController filter plans, visits, OTP, transaction headers/returns,
timing inputs and direct transaction detail requests before aggregation. Inactive,
null/other-status and missing-master customers are excluded. Current route/company/
geographic access checks, legacy linking keys and journey duration rules remain.
No business records or schema were changed.

Added ActiveCustomerFilteringTest for model/map/route data and transaction access,
dashboard regressions for all excluded status cases and matching drilldowns/totals,
idle and visit-transaction regressions; updated existing in-memory fixtures with
active master records and transaction customer codes. All 20 JavaScript dashboard
tests and git diff --check passed. PHP unit tests could not run: php is not on PATH
and vendor is absent. Backend/browser verification remains outstanding.

## Route filter salesman labels: 2026-09-29

Dashboard and Route Tracking share DashboardController::filters and
routelocation/filters.js. Route options now display code - route name - salesman
name, using the current routemaster.salesmancode assignment and
salesman.salesmanname1. A left join retains routes without a salesman; blank names
leave the previous label intact. Route values and access restrictions are unchanged.
All 20 dashboard JavaScript tests, production build (npm.cmd run build), and diff
checks passed. PHP/backend execution and browser verification remain unverified;
PHP and vendor dependencies are unavailable locally.

## Top three OTP comments and aligned cards: 2026-09-24

Removed View all comments link and its styles; card now shows only the top three
comment groups. Clicking the OTP card still opens the full popup breakdown.
Long comment previews clamp to two lines with full text in the hover title and
popup. Journey/customer cards share a 360px minimum height, retaining stretching
and equal grid rows for aligned card edges; content can grow without clipping.
Vue script syntax, targeted OTP markup and diff checks passed. Full build/browser
visual verification remains unavailable because node_modules is absent.

## Top four OTP comments and full popup breakdown: 2026-09-24

OTP card shows the first four comment groups (highest counts), with View all
comments when additional groups exist. Link opens the existing OTP popup without
duplicating the card click event. Popup now includes every comment/count group,
above event details, aggregating all filtered rows before pagination. Date,
journey and search filters apply to the breakdown; blank comments are Unspecified.
Long lists scroll within the summary. Two Vue script syntax checks and diff check
passed; full build/browser remains unverified without local dependencies.

## Stacked tiles and OTP comments correction: 2026-09-24

Efficiency and Productivity now show Collection above Orders/Invoices as two
full-width tiles directly below the count note, matching the supplied reference.
This supersedes the earlier side-by-side placement. OTP card now groups by trimmed
comments, not otpreason, and reads otp_comments with comment/count rows. Heading
is OTP by comments. Empty comments remain Unspecified; selected journey scope,
count ordering and OTP totals remain unchanged. Updated regression uses distinct
comments with identical reasons to catch accidental reason grouping. Twenty JS
tests, Vue script syntax and diff checks passed. PHP tests and full build/browser
remain unverified with local runtime/dependencies unavailable.

## Efficiency and Productivity tile placement: 2026-09-24

Collection and Orders/Invoices remain side by side inside Efficiency and
Productivity but now sit immediately below the visit-count note. These two
cards opt into compactBreakdown, removing the flexible blank grid row that
previously pushed their tiles to the bottom. Other card layouts and calculations
are unchanged. Vue script syntax and diff checks passed; browser/build unverified.

## OTP card reason breakdown: 2026-09-24

OTP usage card now fills its space beneath the headline with OTP by reason:
left-aligned reason labels and right-aligned purple count badges on pale purple
rows. Shows actual selected-journey event counts, sorted descending then by label.
DashboardMetrics aggregates existing scoped OTP details into otp_reasons using
trimmed otpreason; missing/blank reasons become Unspecified. Includes unmatched
and overnight events already in the OTP numerator, so counts reconcile to it.
Empty periods show an explicit no-events message. Card click still opens OTP
details. No additional query or change to access scope or OTP matching.
Added PHP regression for selected windows, trimmed/blank reasons, unmatched
overnight events, total reconciliation and empty periods. All 20 existing JS
tests, Vue script syntax and diff checks passed. PHP tests and build/browser
remain unverified because PHP/vendor/node_modules are unavailable locally.

## Route compliance final labels: 2026-09-24

Renamed Routes Started / Total to Route Start Compliance in the card, popup title
and popup action mapping. Renamed Daily Route Close Compliance to Route Close
Compliance in the orange breakdown and tooltip. Calculations unchanged. Three
Vue script syntax checks and diff check passed; full build/browser unverified.

## Daily Route Close Compliance label: 2026-09-24

Renamed the orange Routes Closed breakdown to Daily Route Close Compliance and
updated its tooltip in DashboardCards. Same-date closure calculation unchanged.
Diff check passed; display-only change, full build/browser unverified locally.

## Routes closed on start date: 2026-09-24

Routes Closed now requires routeclosed = 1 and recorded end calendar date equal
to start calendar date for every journey in that route/start-date group. Overnight
closures and missing end dates do not qualify, even inside a multi-day selection.
Denominator remains started route/date groups. DashboardController shares the
predicate between the metric and a new closed_same_date route-status field;
actual closed state remains separate. Popup Closed/Not Closed filters and labels
use the new rule. Other journey timing and analysis closure behavior is unchanged.
Updated card tooltip and PHP regression cases for same-day, next-day and missing
end dates, with existing duplicate/mixed-open coverage retained. Twenty JS tests,
two Vue script syntax checks and diff check passed. PHP tests and full build/browser
remain unverified because PHP/vendor/node_modules are unavailable locally.

## Closed routes denominator correction: 2026-09-24

Routes Closed orange breakdown now uses closed / started routes x 100, with
closed / started counts below. No started routes yields unavailable. Updated
DashboardCards tooltip to explain both denominators. This supersedes earlier
closed / total notes; the main Routes Started percentage still uses total routes.
Vue script syntax and diff checks passed; full build/browser unverified locally.

## Route status Not Closed filter: 2026-09-24

Added Not Closed to the Routes Started popup status dropdown. Selects started
route/start-date groups with at least one open journey, including mixed open and
closed journey groups. Not Started remains separate. Existing date filtering and
pagination reset are preserved. RouteStatusDialog Vue script syntax and diff
checks passed; full build/browser remains unverified without dependencies.

## Closed routes inside started card (corrected layout): 2026-09-24

User clarified Routes Closed belongs inside the Routes Started card, matching
JP compliance's full-width breakdown tile. Removed the separate card and added
an orange breakdown with closed / filtered total percentage and counts. Shared
breakdown supports an orange tone; existing purple/blue tiles are unchanged.
Route card opens the existing All status popup with Closed filtering available.
Supersedes the separate-card layout below. Three Vue script syntax checks and
diff check passed; full build/browser remains unverified without dependencies.

## Separate closed routes card: 2026-09-24

Moved closed route counts out of the Routes Started note into a separate orange
Routes Closed card in the Journeys group. Displays closed / filtered total as a
percentage with counts below, using the existing route/start-date closure metric.
Clicking opens the route status popup with Closed selected; the started card
still opens All. Updated DashboardCards, Index and RouteStatusDialog. All three
Vue script syntax checks and diff check passed; full build/browser unverified
because node_modules is absent. No backend calculation changes.

## Dashboard JP compliance and percentage cards: 2026-09-24

Renamed Planned Customer Visits and its popup to JP compliance, preserving the
existing calculation and popup action. Routes Started / Total now displays a
percentage with filtered started/total and closed counts below. Counts remain
per route/start date; closed requires all journeys on that route/start date to
be closed. Route status popup includes closed counts, status labels and filter.
OTP usage is total OTP events / all visit logs in selected journeys x 100;
new total_visits includes repeats, incomplete visits and LPO customers. Date
ranges use summed counts and retain journey-start-date/overnight scope. Zero
denominators are unavailable; rates above 100% are preserved.

Updated DashboardController, DashboardMetrics, four dashboard Vue files and PHP
regression assertions for route closure deduplication and total visits. All 20
existing JavaScript tests, four Vue script syntax checks, card ratio checks and
diff check passed. PHP tests and full build/browser verification remain unavailable
because PHP, vendor and node_modules are absent locally.

## Dashboard productivity naming/order: 2026-09-24

Renamed the Productive visits card and popup title to Productivity, updating its
popup action mapping. Customer card order is Planned, Unplanned, Efficiency,
Productivity, OTP usage. Calculations unchanged. Three Vue script syntax checks
and diff check passed; browser/build unverified locally.

## Unplanned percentages: 2026-09-24

Unplanned Customers now displays without-OTP and with-OTP percentages, each
divided by all unique visited customers in the selected journeys, with counts
underneath. Preserves journey/customer uniqueness used by the planned card;
date ranges divide summed counts. Denominator includes planned/unplanned, OTP,
LPO and incomplete visits, including journeys without plans. Such journeys still
cannot contribute to unplanned numerators. Zero denominator yields unavailable.
New all_unique_visited_customers avoids using the LPO-excluding efficiency count.
Updated DashboardMetrics, DashboardCards and PHP assertions. All 20 JS tests,
Vue script syntax and diff checks passed. PHP tests/build/browser remain unverified
without local runtime/dependencies.

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
