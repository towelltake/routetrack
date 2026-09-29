# Full codebase review — 2026-09-03

Scope: whole repo at `bcd15bb` (main, clean tree). Five parallel reviewers (solver, sidecar HTTP/OSRM, Electron main, React renderer, cross-boundary contracts + conventions), then manual verification of every High finding and a sample of Mediums by the consolidating session. Findings marked **[verified]** were re-read in source by the consolidator; the rest are reviewer-reported with quoted evidence.

Headline: the wire contracts (Pydantic ↔ `shared/src/index.ts` ↔ preload ↔ ipcMain ↔ SQLite row types) are clean end to end, the OR-Tools encoding itself is sound, and the no-Google policy holds in live code. The defects sit in the glue: team sizing never adapted to the 2026-05-20 no-drop policy, two edit panels can silently overwrite the wrong record, boot has an unguarded await that can leave a windowless zombie, and a same-salesman drag can lose a visit on a transient error.

---

## Tier 1 — Fix before next installer (High)

| # | Where | Finding | Failure scenario |
|---|---|---|---|
| 1 | `sidecar/sidecar/solver/team_sizing.py:153` **[verified]** | Customers are passed to the virtual-roster solve with `pinned_salesman_id` intact; virtual ids are 900000+, so every pinned customer is unservable. `planner.ts:776` always sets `pinnedSalesmanId: effectivePinnedSalesmanId(c)`, so after Compute Assignments *every* customer is pinned. | Suggest team size returns `recommendedSalesmen=[]`, all customers unassigned, status `partial`. Fix: `model_copy(update={"pinned_salesman_id": None})` per customer before the loop. |
| 2 | `sidecar/sidecar/solver/vrp.py:585` **[verified]** | `solve_week` reports unassigned by *customer id*, not per clone node. A multi-visit customer with one clone placed and another dropped (e.g. allowed-day clone with no eligible vehicle) loses the dropped visit with no `unassigned_customers` entry and `solver_status='success'`. | freq-8 customer ships 4 visits; only the Analytics frequency panel reveals it. |
| 3 | `app/src/main/index.ts:520` **[verified]** | `await startSidecar()` (and `await runMigrations()` at :474) are unguarded inside `app.whenReady().then(...)`; `startOsrm()` two lines above *is* guarded. Neither `sidecar.ts` nor `osrm.ts` attaches a `proc.on('error')` listener. | Defender quarantines `sidecar.exe` or spawn ENOENT → rejection, `registerIpc()`/`createWindow()` never run, no window, `osrm-routed.exe` left running, user must kill from Task Manager. |
| 4 | `app/src/main/planner.ts:879` **[verified]** | Same-salesman day move re-sequences the SOURCE day (visit removed) before the DESTINATION day. The cross-salesman branch at :908 deliberately does dest-first "so a mid-flight failure leaves the customer visibly duplicated instead of silently vanished". | OSRM 503 on the second call → visit exists on neither day; only regenerate restores it. Swap the two calls. |
| 5 | `app/src/renderer/src/screens/CustomersScreen.tsx:97` **[verified]** | `EditPanel` is rendered without `key`; `useState<Customer>(props.customer)` seeds once. Clicking Edit on B while A's panel is open keeps A's draft (and id). | Save writes A's row with edits the user believed were B's. Fix: `key={selected.id}`. |
| 6 | `app/src/renderer/src/screens/SalesmenScreen.tsx:177` **[verified]** | Same bug: `SalesmanForm` unkeyed; "Add salesman" while editing keeps the existing salesman's draft (`id != 0`). | Save overwrites the existing salesman instead of creating one. Fix: `key={editing.id}`. |

## Tier 2 — Correctness / reliability (Medium)

### Solver
- `assemble.py:77` — unpinned multi-visit customers are spread over the *roster-wide* union of working days, not the eligible salesmen's days; with one Saturday-working rep, clones land on Saturday for customers only eligible for Sun–Thu reps → those clones can never be placed (feeds finding #2).
- `assemble.py:107` — when allowed days < visits-per-week, surplus clones are padded onto the same day; nothing stops two clones of one customer on one vehicle → same-day duplicate visits persist (UNIQUE is on sequence, not customer).
- `team_sizing.py:164` — with soft capacity the VRP never drops for capacity, so `if not resp.unassigned_customers: break` fires on the first iteration at the arithmetic lower bound. The grow-N loop and the Target-utilization slider are inert.
- `cycle_assignment.py:112` — freq=1 hot-week bundling has no counter-balance cap (freq=2 got one on 2026-05-21); a (salesman, area) cluster of only freq=1 customers collapses into week 0.
- `cycle_assignment.py:70` — `week_load` is roster-wide, not per salesman; interleaved input can leave one salesman idle two weeks of four while global totals look flat.

### Sidecar HTTP / OSRM
- `osrm_matrix.py:206` **[verified]** — chunking splits destinations only: `chunk_size = max(1, 350 - len(origins))`. A 400-customer area self-matrix → 400 calls of 401 points each, likely over the URL limit → 400/414 → surfaced as "OSRM unreachable, run pnpm osrm:setup". Split origins too.
- `osrm_matrix.py:120` — every non-200 from OSRM becomes `OsrmUnreachableError` → 503 "unreachable"; OSRM's JSON `code` (TooBig, InvalidQuery) is discarded. This is the v0.12.3 incident pattern and will recur.
- `osrm_matrix.py:70` — band calibration is discontinuous (599 s → 839 s, 600 s → 690 s). Cost is non-monotonic in true drive time around the 10- and 30-minute edges; the VRP will exploit it. Blend bands piecewise-linearly.
- `sidecar/tests/` — zero tests for the chunked matrix path or `/route/osrm` (the NoRoute-400 fix from 2026-07-05 is unguarded).

### Electron main
- `planner.ts:717` — `generatePlan` save is four writes (createPlan, snapshot, insertVisitsBulk, setSolverLog) with no transaction; a UNIQUE violation mid-way leaves an empty-shell plan.
- `index.ts:221` — `customers:upsert` / `salesmen:upsert` persist renderer payloads with no validation; the zod schemas in `validate.ts` are never applied to the edit path (frequency 0, lat 91, empty HH:MM all reach SQLite and then the sidecar as 422).
- `repo/customers.ts:201` + migration 0013 `ON DELETE CASCADE` — deleting a customer removes its visits from *every* plan, including `final` ones; plan deletion is guarded, salesman deletion was made non-destructive, customer deletion was not. (Reported by two reviewers.)
- `repo/datasets.ts:67` — `deleteDataset` bulk-deletes `journey_plans` including `final` ones, bypassing the unlock rule.
- `import/commit.ts:88` — new dataset is inserted and activated even when `validated.length === 0`; the renderer pre-check (`ImportScreen.tsx:93`) only requires external_code + name. Forgetting to map lat/lng deactivates the working dataset. (Reported by two reviewers.)
- `index.ts:335` — `plan:generate` / `plan:reassignVisit` have no in-flight guard in main; the guard is renderer-only.
- `diagnostics.ts:70` — `truncateSync` fallback in `rotateIfNeeded` is outside the try/catch; the locked-file case it was built for throws out of `appendError`.
- `db.ts:109` — backup retention is last-7-*launches*, not 7 days; the "yesterday's snapshot survives" comment is false after a day of relaunches.
- `planner.ts:555` — `defaultPeriodStart` formats a local Date via `toISOString()`; between 00:00–04:00 Oman time the default start is a Saturday. (Reported by two reviewers.)
- `export/excel.ts:23` — third copy of the leg-distance lookup; lacks the `-1` clamp and the commute leg, so Excel km ≠ Analytics km. (Reported by two reviewers.)
- `export/excel.ts:62` — weekend shading hard-coded Fri/Sat; Saturday-working rosters get regular days painted amber.

### Renderer
- `PlanScreen.tsx:140` — `generate()` completion handlers belong to the mounting instance; navigate away and back during a solve and the new plan never appears until re-entering the screen.
- `PlanScreen.tsx:504` — `createRoster` has no catch: partial roster, no error, retry duplicates salesmen.
- `SettingsScreen.tsx:151` / `AssignmentsScreen.tsx:200` — SQLite `CURRENT_TIMESTAMP` (`YYYY-MM-DD HH:MM:SS`, UTC, no zone) parsed by `new Date()` as *local* → shown 4 h early in Oman.
- `analytics/panels.tsx:119` — subtitle promises an "orange line = capacity" that is never plotted; orange is the Drive bar.
- Mutation handlers across Customers/Salesmen/Settings/Import/Plan swallow IPC failures (no catch, no UI state).

### Contracts / templates
- `scripts/make-template.cjs:17` **[verified]** — the shipped template's snake_case headers do not match the auto-suggest regexes (which use `' ?'`, never `_`); `autoSuggestMapping` has no normalisation. 7 of 14 columns, including required `external_code`, must be hand-mapped on every upload.
- `PlanScreen.tsx:230` / `models.py:111` **[verified]** — `periodEnd` is editable, sent, and stored, but no sidecar code reads it; plans are always 28 days from `periodStart` (`NUM_WEEKS = 4`).

## Tier 3 — Low / cleanup

- `vrp.py:549` — `routing.status()` never inspected; a time-limit-before-first-solution is reported as "infeasible" with a textually wrong reason; `timeout` status is never emitted.
- `assign_salesmen.py:590` — `solver_status='partial'` means two different things on two code paths.
- `models.py:146` — `max_customers_per_day` is never read; `no_area` reason never emitted; `no_coordinates` branch unreachable (lat/lng are non-optional floats).
- Duplicated helpers: eligibility (`assign_salesmen._eligible_for` vs `vrp.customer_eligible_for_salesman`, 36 lines), `_matrix_to_dict` ×3, `_parse_hhmm` ×3, `_haversine_km` ×2, OSRM base-url/retry loop duplicated between `osrm_matrix.py` and `osrm_route.py`, week/date math ×3 in renderer (`weekIndexOf`, `DAY_LABELS` ×3), day-picker UI ×3 (`WorkingScheduleFields` exists).
- `main.py:94` — no exception handler maps `ValueError` from bad ISO/HH:MM input to 4xx; surfaces as opaque 500.
- `main.py:139` — readiness poll runs ~30 s but Electron gives up at 20 s; `_pick_free_port` releases the port before bind.
- `main.py:157` — uvicorn INFO on stderr is logged as sidecar *errors* on every launch (4 spurious lines).
- `osrm_route.py:55` — `<2 waypoints` is a caller error returned as 503 with a reinstall hint.
- `osrm_matrix.py:167` — `-1` same-node sentinel passes through as `ok`; clamp belongs at the OSRM boundary, not `cache.ts`.
- `osrm_matrix.py:72` — `_multipliers()` re-reads 3 env vars per cell (~370k lookups per 350² table) on the event loop.
- `osrm.ts:165` — startup timeout leaves the process alive; a late handshake flips state after the sidecar was launched with the default URL. Dev fallback port 5050 vs dev OSRM 5051 means a failed dev OSRM silently talks to the installed app's OSRM.
- `osrm.ts:195` / `sidecar.ts` — `stopX()` nulls state before taskkill, so every normal quit logs a fake "exited with code 1".
- `import/validate.ts:114` — `idx('external_code')!` masks a missing-header case as "external_code is empty". (Reported by two reviewers.)
- `planner.ts:649` — "missing coordinates" skip list can never populate (repo already filters NOT NULL); `SolverLog.skippedCustomers` is permanently empty.
- `repo/assignmentRuns.ts:73` — `maxCustomerUpdatedAt()` computed and discarded behind a misleading comment.
- Dead exports: `deleteVisitsForDay`, `deleteVisit`, `ValidationDefaults`, `configureDb`, `configureDiagnostics` (no vitest suite exists; `pnpm test` is a no-op).
- `index.ts:325` — `import:reparse` / `import:commit` accept any renderer-supplied path; nothing binds them to the dialog-approved path.
- `PlanScreen.tsx:440` — `new Date(iso).getDay()` instead of `dowOf()`; `:68` `reloadPlans` closes over stale `selectedPlanId` so deleting the selected plan leaves nothing selected.
- `MapScreen.tsx:282` — geometry workers never cancelled on unmount; `:538` fresh `eventHandlers` object and `DivIcon` per marker per render (~1000 markers × ~120 re-renders).
- `listCustomers` caps differ per screen (2000 / 5000 / 10000 / 100000) with no truncation signal.
- `panels.tsx:435` — `SolverHealthPanel` takes an unused prop and renders a hidden span to silence lint.
- `repo/customers.ts:38` — nullable `dataset_id` coerced to `0` sentinel and typed non-null.
- `preload/index.ts:25` — payload types re-declared rather than imported from main; match by coincidence.
- Stale Google/keytar metadata: `pyproject.toml:4` description, `build_sidecar.py:33` certifi comment, `pnpm-workspace.yaml:14` `keytar: true`, `electron-builder.yml:18`, `DirectionsRequest` type in `models.py:178` + `shared/src/index.ts:269`; `python-dotenv` declared with zero imports.
- `index.html:11` — CSP allows `*.basemaps.cartocdn.com`, never used.
- `setup-osrm.ps1:33` — version and asset name are independent params (bumping one 404s); header still says `oman.osrm`, hint says port 5000.
- `CLAUDE.md §4` — layout drifted: `scripts/`, `.github/` untracked in the doc; `shared/types.ts`, `sidecar/build_sidecar.py`, `oman.osrm*` paths do not exist.
- Tests: `test_drop_priority_prefers_low_freq_when_overcapacity` asserts a mechanism removed 2026-05-20 (trivially passes); every team-sizing test asserts only `len(recommended) >= 1`, none passes a pinned customer or checks the slider changes anything.

## Refuted during verification

- *"Analytics coverage can exceed 100% after customer deletion"* (`computeMetrics.ts:268`) — refuted. `visits.customer_id ON DELETE CASCADE` removes the deleted customer's visits, so the visited set cannot exceed the dataset set. The underlying cascade is itself a Tier 2 finding.

## Boundaries traced and found clean

All 22 Pydantic models ↔ TS types (aliases, optionality, defaults, enums); all 40 preload methods ↔ `safeHandle` channels ↔ renderer call sites; all repo row types ↔ post-0013 schema; import field list (14) consistent across `ImportFieldName`, `validate.ts`, `commit.ts`, template, and `ImportScreen`; zero TS `any` / `@ts-ignore`; no live Google/Mapbox/HERE/keytar/geocode code or deps; only external URL is OSM tiles; handshake string identical in `main.py` and `sidecar.ts`.

## Suggested fix order

1. Tier 1 #1, #5, #6 — three one-line fixes (strip pins in team sizing; `key=` on two panels).
2. Tier 1 #3, #4 — guard boot awaits + `proc.on('error')`; swap same-salesman resequence order.
3. Tier 1 #2 + `assemble.py` spread/padding — per-node unassigned reporting, eligibility-aware spread. Add the multi-visit partial-placement test first.
4. `osrm_matrix.py` — read OSRM `code`, split origins in chunking, smooth calibration bands; add chunked + `/route` tests.
5. Boundary validation — apply `validate.ts` schemas to `customers:upsert`/`salesmen:upsert`; refuse empty-dataset activation; guard customer/dataset deletes against `final` plans.
6. Consolidate the three leg-distance lookups and the duplicated solver helpers.
