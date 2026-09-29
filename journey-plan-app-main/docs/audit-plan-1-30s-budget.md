# Audit — Plan #1 `Plan 2026-05-24`
_Period **2026-05-24** → **2026-06-20**, status **draft**, created 2026-05-18 12:26:05_

## Summary

- Visits: **1,475**
- Customers in active dataset: **532** (532 geocoded)
- Customers with ≥1 visit: **532** (100.0% coverage)
- Salesmen used: **10 / 10**
- Distinct working days covered: **20**

## 1. Unassigned customers — 0

None. Every dataset customer received at least one visit. 

## 2. Frequency conformance — 2 mismatched

- Over-visited: **0**, under-visited: **2**

First 10:
| Code | Name | Got | Want | Delta |
|---|---|---|---|---|
| CO92315 | LULU HYPERMARKET - MALL OF MUSCAT | 7 | 8 | -1 |
| CO91989 | MAJID AL FUTTAIM HYPERMARKET - AL ARAIMI-AL KHOUDH | 7 | 8 | -1 |

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

- Working days planned: **171**
- Avg utilization: **87.8%**
- Days ≥90% loaded (heavy): **114** (66.7%)
- Days <50% loaded (light): **10** (5.8%)

No day overruns the budget. 

## 8. Sequence sanity — 0 day(s) with gaps/dupes

All days have contiguous 1..N sequences. 

## 9. Visits to customers with no coordinates — 0

Every visited customer has a lat/lng. 
