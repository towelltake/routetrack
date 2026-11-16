# Dashboard permissions based on allowed routes

* **Author:** towelltake
* **Status:** User requested implementation after Technical Plan approval; see implementation note below
* **Date:** 2026-11-16
* **Source:** [Confirmed permission brief](./concepts_brief.md)

Implementation note (2026-11-16): the user left the staged design workflow and
requested implementation. The login resolver and Dashboard access query changes
are applied. Permission/filter regressions complete without assertion failures;
live database validation remains outstanding. See PROJECT_MEMORY.md for exact
checks and unrelated full-suite failures. Alternatives were drafted but did not
receive a separate approval; Detailed Implementation was not drafted.

## Overview

Resolve the logged-in user's permission entries into one set of allowed routes.
Use that set to constrain Dashboard data and derive available filter choices.
Combine routes from multiple entries without duplicates. Selecting All or leaving
a filter empty must never extend access beyond these routes.

| Permission | Allowed routes |
| --- | --- |
| Company | All routes belonging to that company, across regions. |
| Country | All routes of companies belonging to that country. |
| Region | All routes belonging to that region. |
| Depot | Routes reached through the depot's areas and subareas. |
| Area | Routes reached through the area's subareas. |
| Subarea | All routes belonging to that subarea. |

Company, region, legal entity and cluster choices must reflect the allowed
routes and their linked records. This design does not require adding new filter
controls for every permission level.

## The Problem

The user reports that Dashboard data and filter options do not follow assigned
permissions. Existing code already calculates access at login and restricts
Dashboard queries, but the reported failure has not been reproduced. Therefore,
the presence of checks is not evidence that the business rules are satisfied.

Current access resolution and Dashboard display use different paths for some
hierarchy values. Additional subarea checks can also exclude company-authorized
routes with missing geographic links. Filter catalogs and some data endpoints
apply different route-sequence eligibility rules. These are investigation
targets, not confirmed causes of the reported issue.

The intended outcome is that a user can see every route their permissions grant,
within applicable screen eligibility and date rules, and no route outside those
grants. Filter options must describe that same authorized population.

## Goals

- Apply the confirmed company, country, region, depot, area and subarea rules.
- Combine multiple permission entries without losing associations within each
  entry or granting unrelated routes.
- Derive available filter values from permitted routes rather than unrestricted
  company or geographic catalogs.
- Enforce access on the server for filters, metrics, maps, status and details;
  manually altered requests cannot expand access.
- Ensure a company grant does not require a separate geographic grant or revoke
  a route solely because descriptive geographic metadata is missing.
- Return no authorized routes for users with no usable permission entries.
- Keep route authorization distinct from active-status, sequence and date
  eligibility; explicitly resolve any required eligibility changes in the plan.

## Non-Goals

- Changing Dashboard calculations, CFT/OTP formulas or journey date semantics.
- Adding new filter controls, a permission administration screen or a general
  replacement authorization framework.
- Database migrations, imports or changes to legacy business records.
- Silently widening legacy entries containing both company and geography.
- Changing Route Tracking or Customer Location behavior beyond the authorized
  scope; shared-login effects must be addressed before implementation.

## Open Questions

- Verify the linking columns for country-to-company membership and route-region
  membership, including conflicting or incomplete hierarchy records.
- Establish the representation of legacy mixed company/geographic entries while
  preserving their restrictions.
- Permission changes take effect at the next successful login (resolved).
- Resolve filter/data sequence eligibility differences without treating them as
  permission grants.

## Critical User Journeys

### Company access across regions

**Given:** A user has access to company A only, with routes in multiple regions.
**When:** The user opens Dashboard and selects All.
**Then:** Only company A's eligible routes contribute to data, and region, legal
entity and cluster options reflect those routes. No separate region permission
is needed.

### Multiple subareas

**Given:** A user has access to subareas X and Y.
**When:** The user loads Dashboard filters and data.
**Then:** Routes from X and Y are combined without duplicates; unrelated routes
and filter values are excluded.

### Altered request

**Given:** A user has a restricted route set.
**When:** A request includes an unauthorized route, company or region.
**Then:** The server returns no unauthorized records, regardless of UI choices.

## Technical Plan (Architecture)

### Resolve grants before applying screen filters

Keep the existing Laravel session access contract and Dashboard query structure.
Correct the login resolver in
`app/Http/Controllers/Auth/AuthenticatedSessionController.php` so each usable
permission entry yields route membership under the confirmed rules. Combine
those memberships as OR between entries, deduplicate route codes, and derive
the existing company/geographic session lists from the resulting routes.
Do not combine independent company and subarea lists to invent route grants.

| Grant | Resolution path |
| --- | --- |
| Company | `routemaster.cmpycode` matches the granted company; no geography prerequisite. |
| Country | `company.countrycode` matches the granted country, then company `cmpycode` links to `routemaster.cmpycode`. |
| Region | `routemaster.regionmstcode` matches the granted region, consistent with Dashboard's displayed region. |
| Depot | Route `subareacode` links through `subareamaster.areacode` and `areamaster.depotcode`. |
| Area | Route `subareacode` links through `subareamaster.areacode`. |
| Subarea | `routemaster.subareacode` matches the grant. |

`CompanyMaster` includes `countrycode`; the schema snapshot and actual database
schema must be checked before implementation. The region path above follows the
user's route-region rule and existing Dashboard region metadata. It replaces
depot-region traversal for region grants; conflicting region fixtures must make
that deliberate behavior visible. Depot membership remains a hierarchy lookup.

For legacy entries containing company and geography, retain current company AND
deepest specified geography behavior while applying the updated scope paths.
Do not interpret an accompanying company code as a second unrestricted grant.
NULL/blank entries grant nothing. Establish the legacy meaning of zero before
normalizing values; current `filled()` considers zero populated.

Missing hierarchy labels must not erase company-granted routes. Geography lookup
joins must therefore not turn company membership into a requirement for complete
subarea/area/depot records. A geographic grant requires a verified membership
path, rather than a fallback that grants unrelated routes.

### Enforce the same authorized base on Dashboard endpoints

Keep `DashboardController::matchingRoutes()` as the common query entry point.
Every Dashboard route query must intersect resolved route membership. Preserve
the company session restriction. Adjust the secondary subarea restriction so
missing descriptive geography cannot veto an otherwise valid company grant;
any exception must still require explicit route and company membership.

Route `companies()` and `routes()` through the same authorized base instead of
maintaining partially duplicated restrictions. Preserve response field names
and existing request parameters. `filters()`, `metrics()`, `routeStatus()`,
`customerDetails()` and `mapJourneys()` must all use this base before applying
user selections. Journey and GPS lookups remain constrained to those routes.

Active company/route checks remain screen eligibility rules. Preserve the
existing sequence requirement for catalog/map and its absence for
metrics/status/details in this correction. All endpoints share authorization,
but need not return identical populations when their eligibility or dates differ.
Test these differences explicitly rather than hiding them in access resolution.

### Derive filter choices from route metadata

Return the existing flat, authorized route catalog from `filters()` with company,
legal entity, cluster, region and route metadata. The existing
`resources/js/views/routelocation/filters.js` can derive option values from those
rows. Do not load independent unrestricted catalogs. Keep the existing selection
rules: OR within a dimension, AND between dimensions, and the independent-region
option behavior. Every option remains bounded by the authorized catalog.

Keep existing controls and response shapes; permission levels do not require new
country, depot, area or subarea controls on Dashboard. Missing optional labels
must not invent a region or remove an allowed route from route choices.

```text
User permission entries
          |
          v
Resolve each entry to routes --> union and deduplicate
                                      |
                                      v
                         Session route/company/geography scope
                                      |
                                      v
                       Shared Dashboard authorized route query
                              /                    \
                             v                      v
                  Filter route catalog      Selected/date-scoped data
                             |              metrics/status/details/map
                             v
                 Legal entity/cluster/company/region/route options
```

### Permission refresh decision

User decision: resolve permissions at each successful login and replace the
previous session scope in full. Do not refresh grants on each data request or add
a permission-version mechanism. Existing sessions retain their resolved grants;
added or revoked permissions apply when the user next signs in successfully.

If the next login finds no usable grants, replace any previous access with empty
lists. Never merge the new scope with a previous login's route list. Test a grant
change across two logins and verify that ordinary requests between those logins
continue using the original scope.

After deployment or rollback, sign out and sign in again when validating the
resolver; a page refresh alone does not recalculate access. Shared-login changes
require Route Tracking and Customer Location compatibility checks without
changing their operation-date rules or dropping their endpoint restrictions.

### Validation boundaries

Add focused backend cases for all six grant levels, union and overlap, mixed
entries, unauthorized requests, missing hierarchy, company-country membership
and conflicting direct/depot regions. Verify all Dashboard endpoint families and
shared-login consumers. Keep CFT/OTP/timing formulas unchanged. Existing PHP tests
cannot currently execute because `vendor/autoload.php` is absent; install from
the lockfile in an authorized implementation step before claiming backend
verification. No migrations or business-database writes are needed.

## Alternatives Considered

The first four alternatives come from the brief's Alternatives Weighed section.
The refresh alternatives document the user's later decision. Technical rejection
reasons below do not attribute decisions to an upstream maintainer.

### Filter unauthorized choices only in the browser

This would require fewer backend edits and could hide unwanted options in normal
use. It would leave data endpoints exposed to manually altered requests and
would not repair an incorrectly resolved session scope. Rejected because access
must be enforced on the server before either data or option values are returned.

### Combine company and geographic lists independently

Separate lists are easy to store and useful as metadata derived from allowed
routes. They cannot replace route resolution: entries granting company A in
subarea X and company B in subarea Y could incorrectly produce access to company
A in Y or company B in X. Rejected as the grant model because it loses each
entry's restrictions. Resolve each entry first, then union its routes.

### Correct the existing login resolver and Dashboard query paths

This is the approved direction. It retains existing session keys and API shapes,
implements the confirmed membership paths, and uses the common Dashboard route
query for server enforcement. Its main limitation is that shared login changes
can affect Route Tracking and Customer Location, so those consumers need focused
compatibility checks. It also requires distinguishing authorization from endpoint
eligibility rather than assuming every query should be identical.

### Replace the authorization framework, add caching or redesign the schema

A new framework could centralize more policy, and caching could reduce repeated
lookups if measured load requires it. However, none is necessary to express the
confirmed route rules or resolve grants at login. Deferred because this would
expand migration, rollout and maintenance work before the reported failure is
reproduced. A small reusable helper may still be appropriate if implementation
reveals concrete duplication; it is not a framework replacement.

### Refresh permissions on every request

This would apply revocations on the next request, but would add repeated grant
and hierarchy queries and change the agreed session behavior. Rejected by
author: changed grants should take effect at the next successful login. Keep
the current login snapshot throughout the session.

### Refresh when a permission version changes

A version check could avoid full resolution on most requests while allowing
earlier updates. It needs a reliable version source and cooperation from every
system that edits legacy permissions; missed version updates leave stale grants.
Rejected by author: next-login refresh satisfies the required behavior, so the
additional change tracking mechanism is unnecessary.

*Detailed Implementation will follow approval of Alternatives Considered.*

*Drafted with EGM*
