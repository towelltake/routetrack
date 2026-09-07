# Route Tracking

## SFA Dashboard filters

The Dashboard (`/dashboard`, routes in `routes/dashboard.php`, controller `Dashboard/DashboardController`) shows the latest GPS position per route and operation date. Its Legal Entity, Cluster, Division, Region, and Route filters allow multiple selections and automatically refresh the map. Values within a filter are combined with OR; separate filters are combined with AND. Empty selections mean all accessible values.

- Legal Entity uses distinct nonblank `company.entity` values.
- Cluster joins `company.clustercode` to `clustermaster.clustercode`.
- Division uses active companies (`company.activestatus = 1`). Routes join through `routemaster.cmpycode`.
- Region joins `routemaster.regionmstcode` to `regionmaster.regionmstcode`, displaying `regionmstname`. There is no region active-status column.
- Region narrows Route options independently of the company hierarchy. Selected routes narrow the available Legal Entity, Cluster, Division, and Region options.
- Each dropdown ignores its own selections when calculating options, allowing additional values to be selected. Clearing selections restores options; Reset restores all filters and today's date.

Options and GPS results retain the existing session route/company/subarea access restrictions and the requirement for a route to appear in `routesequence`. Required company and cluster columns/tables must already exist in the legacy database; this change does not import SQL dumps or alter that schema.

The Route Tracking page compares the planned customer sequence with the actual GPS trail and displays every recorded customer visit for a route journey.

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

No database migration is included because the required tables and `log_id` column already exist in the source database.

```bash
npm run build
php artisan optimize:clear
```
