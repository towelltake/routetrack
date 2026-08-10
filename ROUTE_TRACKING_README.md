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
3. The nearest `trac_routetrack` GPS point to the visit start timestamp, limited to 15 minutes.

Coordinates must be non-zero and inside the configured Oman bounds. If no acceptable location is found, the visit still appears in the Customer Visits list but has no map marker.

## Actual GPS route

The actual trail comes from PostgreSQL `trac_routetrack`, filtered by `routecode` and operation date. Invalid and duplicate GPS points are removed, implausible speed jumps are filtered, and the remaining points are sent to OSRM Map Matching. If map matching fails, the cleaned raw GPS trail is displayed.

The first GPS point is Route Start. The final GPS point is Last Known Location.

## Route status

`startendday.routeclosed` controls the displayed status:

- `0`: Live
- `1`: Closed

## UI behavior

- Planned Customer: one entry per planned customer.
- Customer Visits: one entry per `customervisitlog` row; repeated customers appear repeatedly.
- Planned Not Visited: planned customers with no visit log for the route.
- Planned and actual route lines are visible initially and can be toggled independently.

## Deployment

No database migration is included because the required tables and `log_id` column already exist in the source database.

```bash
npm run build
php artisan optimize:clear
```
