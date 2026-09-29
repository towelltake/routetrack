# Audit — Plan #27 `Plan 2026-05-24`
_Period **2026-05-24** → **2026-06-20**, status **draft**, created 2026-05-18 15:41:30_

## Summary

- Visits: **1,381**
- Customers in active dataset: **532** (532 geocoded)
- Customers with ≥1 visit: **522** (98.1% coverage)
- Salesmen used: **9 / 9**
- Distinct working days covered: **20**

## 1. Unassigned customers — 10

- 0 missing lat/lng (cannot route)
- 10 have coordinates but no visits

First 10 unassigned with coords (likely solver-dropped):

| Code | Name | Freq | Area | Pinned to |
|---|---|---|---|---|
| COH0023 | AL HOME SHOPPING CENTRE - SOHAR | 1× | Sohar | — |
| CO5800 | AL JAHRA HYPERMARKET - AQAR R/A | 2× | Sohar | — |
| CO90144 | SAJA GOLDEN SHOPPING CENTRE - AL AYNAYN | 1× | Sohar | — |
| CO91975 | MAJID AL FUTTAIM HYPERMARKET - SALLAN | 8× | Sohar | — |
| COC0011 | MAJID AL FUTTAIM HYPERMARKET - SOHAR | 8× | Sohar | — |
| CO92678 | NESTO HYPERMARKET - SOHAR | 4× | Sohar | — |
| CO95935 | NESTO HYPER MARKET LLC - SAHAM | 4× | Sohar | — |
| CO96330 | AL EZZA SHOPPING CENTER-HAFEET | 1× | Sohar | — |
| COC0012 | MAJID AL FUTTAIM HYPERMARKET - BOUSHER | 8× | Muscat | — |
| CO95793 | AL MEERA MARKETS SAOC - AL AMARAT | 8× | Muscat | — |

## 2. Frequency conformance — 23 mismatched

- Over-visited: **0**, under-visited: **23**

First 10:
| Code | Name | Got | Want | Delta |
|---|---|---|---|---|
| COL013 | LULU HYPERMARKET - BOWSHER | 1 | 8 | -7 |
| CO90922 | RAMEZ INTERNATIONAL LLC - AL KHUWAIR | 2 | 9 | -7 |
| CO92410 | LULU HYPERMARKET - AL AMERAT | 2 | 8 | -6 |
| CO96084 | AL MEERA HYPERMARKET (PANORAMA MALL) -BAWSHAR | 4 | 8 | -4 |
| COL203 | LULU HYPERMARKET - SOHAR | 1 | 4 | -3 |
| COL021 | LULU HYPERMARKET - KHABURAH | 1 | 4 | -3 |
| CO94521 | NESTO HYPER MARKET LLC - FALAJ | 2 | 4 | -2 |
| CO91873 | KUBBA HYPERMARKET-AL KHABOURA | 1 | 3 | -2 |
| CO92164 | AL KARAMA HYPERMARKET - NEAR BANK MUSCAT GHALA | 4 | 6 | -2 |
| COC0010 | MAJID AL FUTTAIM HYPERMARKET - QURUM | 6 | 8 | -2 |

## 3. Allowed-days violations — 0

No visit lands on a weekday the customer's `allowed_days` excludes. 

## 4. Salesman working-day violations — 0

No visit assigned to a salesman on their day off. 

## 5. Pin violations — 0

Every pinned customer's visits went to the salesman they were pinned to. 

## 6. Area violations — 0

No visit places a customer outside their salesman's assigned areas (area-null customers and catch-all salesmen are allowed). 

## 7. Daily capacity — 0 day(s) over budget

_Budget = `working_hours_end − working_hours_start` + 10 min slack. Load = facetime + drive minutes._

- Working days planned: **160**
- Avg utilization: **85.1%**
- Days ≥90% loaded (heavy): **98** (61.2%)
- Days <50% loaded (light): **14** (8.8%)

No day overruns the budget. 

## 8. Sequence sanity — 0 day(s) with gaps/dupes

All days have contiguous 1..N sequences. 

## 9. Visits to customers with no coordinates — 0

Every visited customer has a lat/lng. 

## 10. Assignment coverage (Phase 7a) — 0 with no effective salesman

- Customers with `assigned_salesman_id` set (algorithm output): **532**
- Customers with `pinned_salesman_id` set (user override or import pin): **0**
- Customers with NEITHER set (no effective salesman): **0**

Per-salesman effective load (`COALESCE(pin, assigned)`):

| Salesman | Customer count | Load (min) |
|---|---|---|
| Suggested — Muscat #4 | 88 | 12,375 |
| Suggested — Sohar #1 | 86 | 12,335 |
| Suggested — Muscat #1 | 30 | 8,070 |
| Suggested — Muscat #3 | 50 | 8,045 |
| Suggested — Muscat #2 | 33 | 7,600 |
| Suggested — Salalah | 71 | 6,380 |
| Suggested — Nizwa | 52 | 5,935 |
| Suggested — Rumais | 67 | 5,820 |
| Suggested — Al Kamil | 55 | 5,625 |
