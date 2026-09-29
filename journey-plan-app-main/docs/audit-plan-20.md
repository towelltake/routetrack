# Audit — Plan #20 `Plan 2026-05-24`
_Period **2026-05-03** → **2026-07-02**, status **draft**, created 2026-05-18 09:11:03_

## Summary

- Visits: **1,372**
- Customers in active dataset: **532** (532 geocoded)
- Customers with ≥1 visit: **531** (99.8% coverage)
- Salesmen used: **9 / 21**
- Distinct working days covered: **20**

## 1. Unassigned customers — 1

- 0 missing lat/lng (cannot route)
- 1 have coordinates but no visits

First 10 unassigned with coords (likely solver-dropped):

| Code | Name | Freq | Area | Pinned to |
|---|---|---|---|---|
| CO92410 | LULU HYPERMARKET - AL AMERAT | 8× | Muscat | — |

## 2. Frequency conformance — 21 mismatched

- Over-visited: **0**, under-visited: **21**

First 10:
| Code | Name | Got | Want | Delta |
|---|---|---|---|---|
| COC001 | MAJID AL FUTTAIM HYPERMARKETS - SEEB | 11 | 20 | -9 |
| CO94907 | MAJID AL FUTTAIM - MALL OF OMAN - BOUSHER | 11 | 20 | -9 |
| CO92315 | LULU HYPERMARKET - MALL OF MUSCAT | 1 | 8 | -7 |
| CO91989 | MAJID AL FUTTAIM HYPERMARKET - AL ARAIMI-AL KHOUDH | 1 | 8 | -7 |
| COL013 | LULU HYPERMARKET - BOWSHER | 1 | 8 | -7 |
| CO95793 | AL MEERA MARKETS SAOC - AL AMARAT | 1 | 8 | -7 |
| COL005 | LULU HYPERMARKET - WADI KABIR | 1 | 8 | -7 |
| CO92096 | LULU HYPERMARKET- AL KHOUD | 2 | 8 | -6 |
| COC0012 | MAJID AL FUTTAIM HYPERMARKET - BOUSHER | 2 | 8 | -6 |
| COC0010 | MAJID AL FUTTAIM HYPERMARKET - QURUM | 2 | 8 | -6 |

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

- Working days planned: **155**
- Avg utilization: **85.0%**
- Days ≥90% loaded (heavy): **91** (58.7%)
- Days <50% loaded (light): **14** (9.0%)

No day overruns the budget. 

## 8. Sequence sanity — 0 day(s) with gaps/dupes

All days have contiguous 1..N sequences. 

## 9. Visits to customers with no coordinates — 0

Every visited customer has a lat/lng. 
