# Route Tracking

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

The actual trail comes from PostgreSQL `trac_routetrack`, filtered by `routecode` and operation date. Invalid and duplicate GPS points are removed and implausible speed jumps are filtered. The remaining points are sent to OSRM Map Matching in overlapping requests of at most 100 points, where each request after the first reuses the preceding request's final point.

OSRM `tracepoints` identify input points omitted as outliers. Successful OSRM matching geometries and raw-GPS fallback sections are interleaved in travel order. A failed request falls back only for its own chunk; successful chunks remain map matched. Raw fallback is split rather than drawing across a GPS time gap over 60 seconds or an implausible jump.

Actual-route `geometry_source` is `osrm_match`, `mixed`, `raw_gps`, or `none`. Diagnostics also report matched/unmatched point counts, matched/fallback geometry counts, partially matched chunks, and failed chunks.

The parallel `geometry_sources` array identifies every returned geometry. OSRM sections render as solid red lines; raw-GPS fallback sections render as dashed orange lines.

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
