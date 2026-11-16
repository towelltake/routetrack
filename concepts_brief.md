# Dashboard permitted-route scope

Author: towelltake  
Date: 2026-11-16  
Status: Permission model confirmed; scoped implementation applied on user request

Implementation note (2026-11-16): login scope paths and Dashboard shared query
paths have been corrected; next-login refresh is preserved. Historical review
findings below describe the pre-change investigation. Current verification and
remaining limitations are recorded in PROJECT_MEMORY.md.

Permission refresh decision confirmed during design: changed grants apply at the
next successful login. Existing sessions retain their resolved access; no
per-request refresh or permission-version mechanism is required. A successful
login replaces the previous scope, including empty grants.

## Overview

### Confirmed permission resolution

Resolve each permission entry to routes first, then combine all resulting route
sets without duplicates. Use that permitted route set to constrain Dashboard
data and derive available filter values. "All" means all permitted routes.

| Permission level | Routes granted |
| --- | --- |
| Company | All routes belonging to that company, across regions. |
| Country | Identify companies in that country, then all their routes. |
| Region | All routes belonging to that region. |
| Depot | Routes under its areas and subareas. |
| Area | Routes under its subareas. |
| Subarea | Routes belonging to that subarea. |

Derive company, country, region, depot, area, subarea, legal entity and cluster
choices from the resulting permitted routes and their linked hierarchy. A company
grant must not require a separately granted region. Missing descriptive hierarchy
must not by itself revoke a route granted through company membership. Geographic
grants still require evidence that the route belongs to the granted scope.

This clarification was confirmed after the seven reviews below. The reviews
remain evidence for the earlier draft, not a separate review of this revision.

Dashboard data and every filter choice must derive from the logged-in user's permitted routes. Multiple company or geographic permission entries combine as a union. A company-only grant has no region restriction and grants that company's depots/routes. Geography follows Country > Region > Depot > Area > Subarea; each depot belongs to a company. Region, legal entity and cluster choices come from permitted routes. Server-side enforcement is mandatory; “All” means all accessible records within the applicable Dashboard eligibility and date rules.

The existing failure cause is unconfirmed. This brief records inspected behavior, risks and proposed next steps; it does not claim a production defect has been reproduced or fixed.

## Analysis

### Confirmed code evidence

| Evidence | Current behavior |
| --- | --- |
| `app/Http/Controllers/Auth/AuthenticatedSessionController.php:35–94` | Login unions permission rows. Within each row it chooses the deepest populated geography and intersects company when supplied. Company-only entries match `route.cmpycode` across geography. Empty/no grants fail closed. Resolved route and geographic lists are stored in session. |
| `app/Support/LegacySchemaSnapshot.php:957–983` | Snapshot depot has company and region foreign keys. Login traverses route → subarea → area → depot → region → country, but matches company on the route, not depot. Snapshot evidence does not establish live data consistency. |
| `app/Http/Controllers/Dashboard/DashboardController.php:24–101` | `matchingRoutes()` enforces session route/company/subarea intersections and active company/route status. Filter catalog requires route-sequence membership. Legacy company/route endpoints have separate query paths. |
| `app/Http/Controllers/Dashboard/DashboardController.php:169–245,307` | Details, status and metrics use `matchingRoutes(..., false)`; map journeys and catalog retain its default sequence requirement. Some data can include permitted routes absent from sequence-restricted choices/map. This existing distinction is tested behavior, not automatically a bug. |
| `app/Http/Controllers/Dashboard/DashboardController.php:29,41` | Displayed/filter region comes directly from `routemaster.regionmstcode`, unlike login's depot-derived region. |
| `resources/js/views/routelocation/filters.js`; `Index.vue:198` | UI choices derive from the server route catalog. Region options remain independent of company hierarchy selections except selected routes. UI filtering does not authorize requests. |
| `tests/Unit/LoginCompanyPermissionsTest.php:30–48`; `DashboardFiltersTest.php:197–224` | Existing coverage includes company-only, mixed grants, multiple company scopes, geography-only, empty grants and Dashboard scope/filter combinations. Login fixtures omit depot company, so depot ownership mismatch is not covered. |

### Material risks and unresolved assumptions

- **Major — grant broadening:** Company-only unrestricted access does not settle legacy mixed company/geography semantics. Preserve existing per-row intersection until explicitly decided. Example: `(company A AND subarea X) OR (company B AND subarea Y)` must not become either company across both subareas.
- **Major — hierarchy disagreement:** Route company versus depot company, and direct route region versus depot region, may disagree. Determine authoritative ownership and region metadata before changing predicates. No live mismatch or causal connection to the reported symptom has been established.
- **Major — missing hierarchy:** Company-only login can include routes with missing geographic ancestors, while Dashboard subarea intersections may exclude them. Define intended behavior without silently dropping existing session restrictions.
- **Major — authorization versus eligibility:** Decide whether active permitted routes lacking sequences should appear as choices. Preserve existing active-status, journey-range, and selected-operation-date semantics until specifically changed.
- **Major — session freshness:** Permissions resolve at login. Define whether changed grants apply on next login or require explicit invalidation/refresh. Do not add a cache or refresh subsystem without that requirement.
- **Major — shared-login compatibility:** Changing resolved grants affects Route Tracking and Customer Location as well as Dashboard. Preserve session keys and each screen's date/access rules; include existing-session rollout and rollback behavior in an implementation plan.
- **Major — diagnosis and scale:** Distinguish no grants, inactive records, missing hierarchy, sequence exclusion and stale sessions using privacy-conscious aggregate counts if needed. Broad grants may enlarge session/catalog payloads; measure before introducing performance infrastructure.

## Goals vs Non-Goals

Goals: preserve grant-row union and server enforcement; derive filter metadata from the same permitted-route scope used by Dashboard data; establish authoritative hierarchy and explicit eligibility rules; cover multi-company/multi-geography and forged requests with focused regressions.

Non-goals: production implementation during brainstorming; database changes, migrations or imports; metric/CFT/OTP formula changes; changing Route Tracking date semantics; a new general permission framework; automatic broadening of mixed grants; independent unrestricted geography/company catalogs; speculative caching or schema redesign.

## Alternatives Weighed

| Alternative | Assessment |
| --- | --- |
| UI-only filtering | Reject: cannot enforce authorization or repair server/session resolution. |
| Independently union company and geography arrays | Reject as a grant model: loses association within mixed rows and can create unauthorized combinations. Keep resolved route membership and current secondary session checks pending an explicit policy decision. |
| Small correction around existing login resolver and Dashboard `matchingRoutes()` | Preferred starting point after diagnosis; reuse existing conventions and align relevant query paths deliberately. Extract a reusable resolver only where demonstrated duplication warrants it. |
| Full authorization rewrite, cache or schema redesign | Defer: current evidence supports a scoped investigation and regression correction first. |

Inventive synthesis: no false compromise was identified. Reusing the existing authorized-route query provides the desired consistency with modest change; keep authorization and endpoint eligibility explicit so query reuse does not accidentally impose sequence requirements on metrics or remove them from maps.

## Key Decisions & Staging for Next Steps

1. Confirm one failing scenario through authorized, read-only diagnostics without copying customer records or credentials into artifacts. Compare resolved-route counts with Dashboard active/sequence/hierarchy exclusions; verify current session age.
2. Implement the confirmed route-resolution rules above in the design. Verify the live linking columns for country-to-company membership and route-region membership against schema and tests; do not substitute depot ownership for a company grant over its routes. Preserve mixed-row restrictions until their legacy representation is established. Treat sequence/activity rules as endpoint eligibility, distinct from permission grants.
3. Draft the smallest implementation proposal using existing resolver and Dashboard query conventions. All request filters must intersect the authorized base; forged route/company/region combinations cannot expand access. Require implementation authorization separately.
4. Validate with synthetic fixtures: multiple company-only grants spanning regions; multiple geographic grants; mixed and overlapping rows; unrelated user/no/empty grants; missing ancestors; conflicting route/depot company and region; inactive records; sequence absence; stale scope behavior; all/empty and forged selections across catalog, metrics, map, status, details and legacy endpoints. Verify region/entity/cluster choices reference eligible permitted routes and retain intended independent-region UI behavior.
5. Run focused PHP tests, existing frontend tests and appropriate build checks after an authorized change. No production database mutation is a verification step.

Validation performed for this brainstorm: read-only code/schema/test inspection on branch `UI--ehancement`; initial working tree clean. `node --test tests/dashboard-filters.test.mjs tests/dashboard-analytics.test.mjs`: **20 passed**. PHP/Pest execution unavailable because `vendor/autoload.php` is absent; backend behavior and native MySQL data consistency remain unverified. No production code or database changes were made.

### Goldfish completion status

Fresh conversation contexts used `fork_turns: none`; project/host instruction-file exclusion is unverified. No conversation history was retrieved. Reviewers read their personas and applicable named standards; this is a concept brainstorm, not implementation approval.

| Reviewer | Status |
| --- | --- |
| egm-design | completed |
| egm-codebase-details | completed |
| egm-zoom-out | completed |
| egm-comprehension | completed |
| egm-implementation-readiness | completed |
| egm-structural | completed |
| egm-inventive-catalyst | completed |
