# TRAC V2 Database Architecture

## Docker Runtime

`trac-v2` can run in Docker using:

```bash
docker compose -f docker-compose.trac-v2.yml up -d --build
```

The container serves Laravel on:

```text
http://127.0.0.1:8001
```

The Docker environment is defined in `.env.docker`.

Current service routing:

```text
TRAC V2 container -> mysql:3306 -> Docker MySQL main TRAC database
TRAC V2 container -> host.docker.internal:5433 -> Docker PostgreSQL trac_pg GPS database
TRAC V2 container -> host.docker.internal:5001 -> OSRM
```

Docker MySQL is exposed to the host on port `3307`:

```text
Host: 127.0.0.1
Port: 3307
Database: trac
Username: root
Password: tracroot
```

Useful commands:

```bash
docker compose -f docker-compose.trac-v2.yml ps
docker compose -f docker-compose.trac-v2.yml logs -f trac-v2
docker compose -f docker-compose.trac-v2.yml down
```

Last updated: 2026-07-01

This document maps the `trac-v2` Laravel application by URL/module, controller, Vue page, database connection, and database tables. It is a living document: the GPS/map modules are mapped from direct controller review, while very large CRUD/report areas are summarized by table family and should be expanded route by route as those modules are changed.

## System Overview

`trac-v2` is a Laravel + Inertia/Vue application over a legacy TRAC database.

Main runtime pieces:

- Backend framework: Laravel.
- Frontend: Inertia + Vue under `resources/js/views`.
- Main database connection: MySQL, `DB_CONNECTION=mysql`.
- GPS read connection: PostgreSQL connection named `pgsql_transfer`.
- Route geometry dependency: OSRM, configured through `config/services.php`.

## Database Connections

| Connection | Driver | Purpose | Current use |
|---|---|---|---|
| `mysql` | MySQL | Main legacy operational database | Masters, transactions, reports, users, route metadata, customer data |
| `pgsql_transfer` | PostgreSQL | High-volume GPS track store | `trac_routetrack` reads for route tracking, route replay, route location |

Important: most modules use the default MySQL connection unless the controller explicitly calls `DB::connection('pgsql_transfer')`.

## Route Groups

The application has many routes. `php artisan route:list` currently shows about 882 routes. The main route groups are:

| URL prefix | Area | Main route file | Main controller namespace |
|---|---|---|---|
| `/dashboard`, `/analytics/*` | Dashboard and analytics | `routes/web.php` | `Analytics` |
| `/basic/*` | Basic masters | `routes/web.php` | `Basic` |
| `/operation/*` | Operational masters | `routes/web.php` | `Operation` |
| `/organisation/*` | Organisation masters | `routes/web.php` | `Organisation` |
| `/inventory/*` | Inventory masters and delivery/load setup | `routes/web.php` | `Inventory` |
| `/account/*` | Customer/salesman/account transactions | `routes/web.php` | `Account` |
| `/transaction/*` | Transaction inquiry/detail pages | `routes/web.php` | `Transaction` |
| `/reports/*` | Reports | `routes/web.php` | `Reports` |
| `/merchandizing/*` | Merchandizing setup | `routes/web.php` | `Merchandizing` |
| `/links/*` | Bulk linking/mapping tools | `routes/web.php` | `Links` |
| `/scheme/*` | Promotions, pricing, loyalty | `routes/web.php` | `Scheme` |
| `/customer-location/*` | Customer map | `routes/customerlocation.php` | `CustomerLocation` |
| `/route-location/*` | Last route GPS position | `routes/routelocation.php` | `RouteLocation` |
| `/route-replay/*` | GPS replay | `routes/routereplay.php` | `RouteReplay` |
| `/route-tracking/*` | Planned vs actual route | `routes/routetracking.php` | `RouteTracking` |
| `/api/*` | Mobile/legacy API endpoints | `routes/api.php` | `Api` |
| `/usermanagement/*` | Users, roles, permissions | `routes/web.php` | `Usermanagement` |
| `/settings/*` | Admin settings | `routes/web.php` | `Admin` |

## Exact Module Map: Route Tracking

Purpose: compare planned customer sequence against actual GPS trail for one route/date.

| URL | Method | Controller method | Vue page | Tables |
|---|---|---|---|---|
| `/route-tracking` | GET | `RouteTrackingController@index` | `resources/js/views/routetracking/Index.vue` | none directly, page shell only |
| `/route-tracking/companies.json` | GET | `companies` | same page AJAX | `routemaster`, `routesequencecustomerstatus`, `company` |
| `/route-tracking/areas.json` | GET | `areas` | same page AJAX | `routemaster`, `routesequencecustomerstatus`, `subareamaster`, `areamaster` |
| `/route-tracking/subareas.json` | GET | `subareas` | same page AJAX | `routemaster`, `routesequencecustomerstatus`, `subareamaster` |
| `/route-tracking/routes.json` | GET | `routes` | same page AJAX | `routemaster`, `routesequencecustomerstatus` |
| `/route-tracking/planned-route.json` | GET | `plannedRoute` | same page AJAX | `startendday`, `routesequencecustomerstatus`, `customermaster` |
| `/route-tracking/actual-route.json` | GET | `actualRoute` | same page AJAX | PostgreSQL `trac_routetrack` |
| `/route-tracking/compare.json` | GET | `compare` | same page AJAX | all planned + actual tables above |

Data flow:

1. User selects company/area/subarea/route/date.
2. Route filters come from MySQL route master data.
3. Planned route finds `routekey` from `startendday`; if missing, falls back to `routesequencecustomerstatus` by route/date week logic.
4. Planned customers come from `routesequencecustomerstatus` joined to `customermaster`.
5. Actual GPS points come from PostgreSQL `trac_routetrack`.
6. OSRM `/route` is used for planned legs.
7. OSRM `/match` is used for actual GPS map matching.

Important columns:

- `startendday`: `routekey`, `routecode`, `routestartdate`, `routeenddate`.
- `routesequencecustomerstatus`: `routekey`, `routecode`, `customercode`, `sequencenumber`, `schelduledflag`, `servicedflag`, `scannedflag`, `seqweekday`, `seqweeknumber`.
- `customermaster`: `customercode`, `customername`, `fixedlatitude`, `fixedlongitude`.
- PostgreSQL `trac_routetrack`: `entryid`, `routekey`, `routecode`, `salesmancode`, `entrydate`, `entrytime`, `devicetimestamp`, `latitude`, `longitude`.

Flag rules:

- `schelduledflag = 1`: customer is scheduled.
- `servicedflag = 0`: not visited.
- `servicedflag = 1`: visited with sale.
- `servicedflag = 2`: visited, no sale.

## Exact Module Map: Route Replay

Purpose: animate the actual historical GPS path for one route/date.

| URL | Method | Controller method | Vue page | Tables |
|---|---|---|---|---|
| `/route-replay` | GET | `RouteReplayController@index` | `resources/js/views/routereplay/Index.vue` | none directly |
| `/route-replay/companies.json` | GET | `companies` | same page AJAX | `routemaster`, `routesequence`, `company` |
| `/route-replay/areas.json` | GET | `areas` | same page AJAX | `routemaster`, `routesequence`, `subareamaster`, `areamaster` |
| `/route-replay/subareas.json` | GET | `subareas` | same page AJAX | `routemaster`, `routesequence`, `subareamaster` |
| `/route-replay/routes.json` | GET | `routes` | same page AJAX | `routemaster`, `routesequence` |
| `/route-replay/track.json` | GET | `track` | same page AJAX | PostgreSQL `trac_routetrack` |

Data flow:

1. User filters route by company/area/subarea.
2. Route list is limited to routes that exist in `routesequence`.
3. GPS trail is read from PostgreSQL `trac_routetrack` for `routecode` and `entrydate`.
4. Controller removes duplicated/stationary GPS pings and impossible speed anomalies.
5. Vue animates the path and marker.

Important GPS expression:

`COALESCE(devicetimestamp, entrydate + entrytime) as effective_timestamp`

## Exact Module Map: Route Location

Purpose: show the last known GPS point for every route on a selected date.

| URL | Method | Controller method | Vue page | Tables |
|---|---|---|---|---|
| `/route-location` | GET | `RouteLocationController@index` | `resources/js/views/routelocation/Index.vue` | none directly |
| `/route-location/companies.json` | GET | `companies` | same page AJAX | `routemaster`, `routesequence`, `company` |
| `/route-location/areas.json` | GET | `areas` | same page AJAX | `routemaster`, `routesequence`, `subareamaster`, `areamaster` |
| `/route-location/subareas.json` | GET | `subareas` | same page AJAX | `routemaster`, `routesequence`, `subareamaster` |
| `/route-location/last-locations.json` | GET | `lastLocations` | same page AJAX | `routemaster`, `routesequence`, `subareamaster`, PostgreSQL `trac_routetrack`, `salesman` |

Data flow:

1. User selects date and optional company/area/subarea.
2. Matching route codes come from `routemaster` filtered through `routesequence`.
3. Last GPS point per route is read from PostgreSQL `trac_routetrack`.
4. Salesman display names come from `salesman`.

## Exact Module Map: Customer Location

Purpose: show customer fixed coordinates on a map.

| URL | Method | Controller method | Vue page | Tables |
|---|---|---|---|---|
| `/customer-location` | GET | `CustomerLocationController@index` | `resources/js/views/customerlocation/Index.vue` | none directly |
| `/customer-location/companies.json` | GET | `companies` | same page AJAX | `routemaster`, `routesequence`, `company` |
| `/customer-location/areas.json` | GET | `areas` | same page AJAX | `routemaster`, `routesequence`, `subareamaster`, `areamaster` |
| `/customer-location/subareas.json` | GET | `subareas` | same page AJAX | `routemaster`, `routesequence`, `subareamaster` |
| `/customer-location/locations.json` | GET | `locations` | same page AJAX | `routemaster`, `routesequence`, `customermaster` |
| `/customer-location/route.json` | GET | `OsrmRouteController@route` | same page AJAX | no DB expected; calls OSRM |

Data flow:

1. User filters customers by route hierarchy.
2. Route hierarchy comes from `routemaster`, `routesequence`, `company`, `areamaster`, `subareamaster`.
3. Customer coordinates come from `customermaster.fixedlatitude` and `customermaster.fixedlongitude`.
4. OSRM route endpoint can calculate road path between customer points.

## Major Table Families

These are the main legacy table families seen in controllers/models. Use this section to understand where to start before drilling into a specific controller.

| Functional area | Common URL prefix | Common tables |
|---|---|---|
| Company/region/depot/area setup | `/basic/*`, `/operation/*`, `/organisation/*` | `company`, `country`, `regionmaster`, `depotmaster`, `areamaster`, `subareamaster`, `routecategory`, `vanmaster`, `vehiclemaster`, `devicemaster` |
| Route setup | `/organisation/route*`, `/operation/routes*` | `routemaster`, `routesequence`, `routetemplate`, `routecategory`, `salesman`, `vehiclemaster`, `subareamaster` |
| Customer master | `/account/customer*` | `customermaster`, `routemaster`, `channelmaster`, `categorymaster`, `visualheader`, `controlpanel` |
| Customer sequence | `/account/customer-sequence*` | `routesequence`, `routesequencecustomerstatus`, `customermaster`, `routemaster`, `startendday` |
| Salesman master | `/account/salesman*`, `/operation/salesman*` | `salesman`, `routemaster`, `company`, related route/org tables |
| Inventory item master | `/inventory/item*` | `itemmaster`, `itemgroup`, `submajorcategory`, `majorcategory`, `companygroup`, `uom`, `controlpanel` |
| Delivery | `/inventory/delivery*` | `deliveryheader`, `deliverydetail`, `routemaster`, `salesman`, `customermaster`, `itemmaster`, `routeitemmapping`, `controlpanel` |
| Daily salesman load | `/inventory/dailysalesmanload*` | `inventorytransactionheader`, `inventorytransactiondetail`, `routemaster`, `salesman`, `itemmaster`, `routeitemmapping`, `controlpanel` |
| Route item groups | `/inventory/routeitemgroup*`, `/links/route-item-group*` | `routeitemgrp`, `routeitemmapping`, `routemaster`, `itemmaster`, `regionmaster`, `depotmaster`, `areamaster`, `subareamaster` |
| Transactions inquiry | `/transaction/*` | `invoiceheader`, `invoicedetail`, `arheader`, `ardetail`, `inventorytransactionheader`, `inventorytransactiondetail`, `customerinventory`, `startendday`, `customermaster`, `routemaster`, `salesman` |
| Account transactions | `/account/transaction/*`, `/account/settlement/*` | `arheader`, `ardetail`, `cashcheckdetail`, `customerinvoice`, `invoiceheader`, `routemaster`, `salesman`, `customermaster`, `controlpanel` |
| Promotions | `/scheme/promotion*`, `/links/promotion*` | `promokeyheader`, `promokeydetail`, `promoplanheader`, `promoplandetail`, `promotioncontrol`, `productgroupheader`, `productgroupdetail`, `customermaster` |
| Special pricing | `/scheme/special-price*`, `/links/special-price*` | `customerpricingplanheader1`, `customerpricing1`, `pricingdetail1`, `itemmaster`, `customermaster` |
| Loyalty | `/scheme/loyalty*` | `loyaltykeyheader`, `loyaltykeydetail`, `loyaltyplanheader`, `loyaltyplandetail`, `productgroupheader`, `productgroupdetail`, `itemmaster` |
| Merchandizing | `/merchandizing/*` | `visualheader`, `visualdetail`, `visualdetail_temp`, `customerimages`, `customersurveydefinition`, `customersurveyplan`, `customersurveykey`, `lookupindexheader`, `lookupindexdetail`, `posmaster`, `posinstructions`, `customerposlimit` |
| Reports | `/reports/*` | Mostly read-only combinations of transaction, route, customer, item, AR, inventory, survey, POS, and merchandizing tables |
| Users and permissions | `/usermanagement/*` | `users`, `user_type`, `user_type_detail`, `user_detail`, `module_header`, `module_detail`, `user_access_code` |
| Settings | `/settings/*` | `controlpanel`, `email_configurations`, `email_templates`, related app settings tables |
| Mobile/legacy API | `/api/*` | Sync/login/transaction/customer/delivery/GPS tables; high risk, inspect method before changing |

## Reports Module Table Map

Reports are mostly read-only and use direct query builder joins. These are verified from controller scans and should be expanded per report when editing.

| Report URL family | Controller | Main tables |
|---|---|---|
| `/reports/daily-report/route-inventory` | `RouteInventoryController` | `startendday`, `routemaster`, `salesman`, `inventorysummarydetail`, `itemmaster`, `itemgroup`, `submajorcategory`, `majorcategory` |
| `/reports/daily-report/route-activity` | `RouteActivityController` | route/day/customer/transaction summary tables; needs deeper method review |
| `/reports/daily-report/route-deposit-summary` | `RouteDepositSummaryController` | settlement/deposit/route tables; needs deeper method review |
| `/reports/daily-report/discount-summary` | `DiscountSummaryController` | `invoiceheader`, `customermaster`, `salesman`, `routemaster` |
| `/reports/daily-report/pricing-summary` | `PricingSummaryController` | `invoiceheader`, `invoicedetail`, `itemmaster`, `customermaster`, `routemaster` |
| `/reports/transaction-report/sales-summary` | `SalesSummaryController` | `invoiceheader`, `invoicedetail`, `itemmaster`, `customermaster`, `routemaster`, `salesman` |
| `/reports/transaction-report/collection-summary` | `CollectionSummaryController` | `arheader`, `ardetail`, `cashcheckdetail`, `customermaster`, `routemaster` |
| `/reports/transaction-report/item-history` | `ItemHistoryController` | `startendday`, `routemaster`, `salesman`, `inventorysummarydetail`, `itemmaster`, `itemgroup`, `submajorcategory`, `majorcategory` |
| `/reports/transaction-report/route-visit-summary` | `RouteVisitSummaryController` | route/customer operation and transaction tables; needs deeper method review |
| `/reports/merchandizing-report/assets-availability` | `AssetsAvailabilityController` | `customeroperationscontrol`, `posequipmentchangedetail`, `customermaster`, `posmaster`, `posinstructions`, `routemaster` |
| `/reports/merchandizing-report/survey-tracking` | `SurveyTrackingController` | `customeroperationscontrol`, `surveyauditdetail`, `customersurveydefinition`, `salesman`, `routemaster`, `customermaster` |
| `/reports/merchandizing-report/merchandized-stock` | `MerchandizedStockController` | `customeroperationscontrol`, source stock table, `customermaster`, `itemmaster`, `routemaster`, optional `distributioncheckdetail` |
| `/reports/accounts-report/route-pending-balance` | `RoutePendingBalanceController` | `customerinvoice`, `routemaster`, `salesman` |
| `/reports/accounts-report/customer-pending-balance` | `CustomerPendingBalanceController` | `customerinvoice`, `customermaster`, `salesman` |
| `/reports/data-analysis/route-monthly-revenue` | `RouteMonthlyRevenueController` | `customerinvoice`, `routemaster`, `salesman` |
| `/reports/data-analysis/item-group-wise-sales` | `ItemGroupWiseSalesController` | `invoiceheader`, `invoicedetail`, `itemmaster`, `itemgroup`, `submajorcategory`, `majorcategory` |

## API Route Map

These endpoints are legacy/mobile facing. Treat each change as high risk because mobile devices may depend on request/response format.

| URL pattern | Method | Controller | Purpose | Tables |
|---|---|---|---|---|
| `/api/index/salesmanlogin/{tail?}` | POST | `Api\IndexController@salesmanLogin` | Mobile login | likely `salesman`, `routemaster`, device/sync tables |
| `/api/index/companyidbydevice/{tail?}` | POST | `Api\IndexController@companyIdByDevice` | Find company by device | likely device/company tables |
| `/api/index/getsyncdata/{tail?}` | GET | `Api\IndexController@getSyncData` | Mobile incremental sync | many master/sync tables |
| `/api/index/getsyncfulldata/{tail?}` | GET | `Api\IndexController@getSyncFullData` | Mobile full sync | many master/sync tables |
| `/api/index/updatesyncdate/{tail?}` | GET/POST | `Api\IndexController@updateSyncDate` | Update sync marker | sync metadata tables |
| `/api/customer/customermaster/{tail?}` | GET/POST | `Api\CustomerController@customerMaster` | Mobile customer master | `customermaster` and related lookup tables |
| `/api/transaction/trandata/{tail?}` | GET | `Api\TransactionController@tranData` | Mobile transaction fetch | transaction tables |
| `/api/ws/senddata/{tail?}` | GET/POST | `Api\WsController@sendData` | Mobile upload data | many transaction tables |
| `/api/ws/routetrackl12/{tail?}` | GET/POST | `Api\WsController@routeTrackL12` | Legacy route GPS upload | PostgreSQL `trac_routetrack` after current changes |
| `/api/ws/getdelivery/{tail?}` | GET/POST | `Api\WsController@getDelivery` | Delivery fetch | `deliveryheader`, `deliverydetail` |
| `/api/ws/getwhstock/{tail?}` | GET/POST | `Api\WsController@getWhStock` | Warehouse stock | stock/inventory tables |
| `/api/ws/getcustomerbalance/{tail?}` | GET/POST | `Api\WsController@getCustomerBalance` | Customer balance | `customermaster`, `customerinvoice` |
| `/api/ws/getroutebalance/{tail?}` | GET/POST | `Api\WsController@getRouteBalance` | Route balance | route/customer invoice tables |
| `/api/image/upload` | POST | `Api\ImageController@upload` | Upload images | image/storage tables and filesystem |
| `/upload/upload.php` | GET/POST | `Api\DeviceBackupUploadController@upload` | Device backup upload compatibility path | filesystem and backup metadata |

## Known High-Risk Tables

| Table | Why it matters |
|---|---|
| `customermaster` | Central customer master used by route maps, invoices, promotions, pricing, reports |
| `routemaster` | Central route master used by almost every operational/reporting module |
| `routesequence` | Legacy planned customer sequence source |
| `routesequencecustomerstatus` | Newer planned/visited customer status source used by route tracking |
| `startendday` | Route day/session header, links route/date to `routekey` |
| PostgreSQL `trac_routetrack` | High-volume GPS points |
| `invoiceheader`, `invoicedetail` | Sales transaction core |
| `arheader`, `ardetail`, `cashcheckdetail` | Collections/payment core |
| `inventorytransactionheader`, `inventorytransactiondetail` | Load/unload/inventory transaction core |
| `customerinvoice` | Outstanding balance and ageing reports |
| `controlpanel` | Numbering and global behavior switches |

## How To Audit Any URL

Use this repeatable process before changing a module:

1. Find the route:

```bash
php artisan route:list --path=route-tracking
```

2. Open the controller method listed by the route.
3. Search the controller for:

```bash
rg "DB::table|DB::connection|join\\(|leftJoin\\(|ModelName::|Inertia::render" app/Http/Controllers
```

4. If a model is used, open it and check `$table`, `$primaryKey`, and relationships.
5. Check the Vue page from `Inertia::render(...)`.
6. Record the final mapping in this document.

## Coverage Status

Done in this version:

- Database connection overview.
- Main route/module group inventory.
- Exact table map for `route-tracking`.
- Exact table map for `route-replay`.
- Exact table map for `route-location`.
- Exact table map for `customer-location`.
- Initial report table map from controller scans.
- Initial API endpoint map from `routes/api.php`.

Still to expand:

- Every individual CRUD route under `/basic`, `/operation`, `/organisation`, `/inventory`, `/account`, `/transaction`, `/merchandizing`, `/links`, and `/scheme`.
- Exact table lists for each mobile API method.
- Exact table lists for all reports with export methods.
- ER-style relationship diagram for core tables: route, customer, salesman, transaction, inventory, GPS.
