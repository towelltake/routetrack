# MEMORY.md — Journey Plan App

> **Future Claude: read this file in full at the start of every session, BEFORE doing anything else.** This is the running state of the project. `CLAUDE.md` tells you the rules of the game; this file tells you the score.
>
> **At the end of every session, append your updates** using the template at the bottom. Keep entries terse — this is a ledger, not a report.
>
> **Older entries (pre 2026-05-21) live in [`docs/memory-archive/`](docs/memory-archive/).** Trim the decision log when `pnpm memory:check` reports this file at 100 KB; move the oldest entries to a dated archive file (see `CLAUDE.md` §0).

---

## Current phase

**Phase 12 ✅ shipped 2026-05-22.** Analytics dashboard + plan/dataset delete UX. Editorial UI redesign (Fraunces + Inter, paper palette, topographic background) shipped same evening.

**2026-09-21: code-review fix batch SHIPPED.** Six Tier-1 bugs + ~20 Tier-2 fixes, continuous drive-time calibration, per-salesman week balance, freq=1 bundle cap, an Analytics **Region** column, and a `pnpm memory:check` size gate. 77 pytest green, typecheck/ruff/mypy clean. `installer/JourneyPlanApp-Setup-0.1.0.exe` rebuilt **2026-09-23 07:59 (750 MB)** with atomic drag-to-reassign on top — current ship, **installed on prod 2026-09-28** (smoke test not yet reported).

**Phase 13 shipped 2026-06-10/11 (plan-scoped salesmen, migration 0013 + all-routes map).** `installer/JourneyPlanApp-Setup-0.1.0.exe` rebuilt **2026-07-05 22:03 (748 MB)** is the current ship — carries the OSRM `/route` NoRoute hardening (fix `482caf6`; sidecar sha `73efd5aa…`, rebuilt). Prior ship was 2026-06-14 16:42 (analytics polish batch). **13 migrations, no new migration since.** User install-over pending.

**2026-05-23: Audit hardening pass shipped as v0.12.1 → v0.12.3.** Codebase probe surfaced 19 findings across the sidecar, main process, and renderer; all 19 closed in four phases (commits `d4df32f..4b614ef`). v0.12.1 (791.8 MB) carries every fix plus migration 0010 (drops vestigial geocode schema) and dev/installer `userData` + OSRM-port split. v0.12.2 (19:34) adds the Windows brand icon. **v0.12.3 (792.6 MB, 21:14)** fixes a real regression my Phase 4 refactor introduced — OSRM's default `--max-table-size=100` rejected self-matrix calls for any region with >100 customers; now spawning OSRM with `--max-table-size 5000`. `installer/JourneyPlanApp-Setup-0.1.0.exe` is the current ship.

For earlier phase status, see the Roadmap table below or `docs/memory-archive/`.

---

## Roadmap (locked direction; details can evolve)

| Phase | Goal | Status |
|---|---|---|
| **0** | Bootstrap — `CLAUDE.md` + `MEMORY.md` | ✅ Done (2026-05-14) |
| **1** | Scaffold — Electron+React+TS app shell, FastAPI sidecar with `/health`, electron-builder dev config, SQLite migration system, pnpm workspace, basic CI scripts | ✅ Done (2026-05-15) |
| **2** | Data layer — Excel/CSV importer, customer + salesman CRUD UI, Google Geocoding for missing lat/lng, map view with customer markers | ✅ Done (2026-05-17) |
| **3** | Optimizer — OR-Tools VRP with time windows, facetime, frequency, allowed days; Distance Matrix integration; route preview with real Directions polylines per salesman per day | ✅ Done (2026-05-17) |
| **4** | Journey plan output — day-by-day calendar view, Excel export of the schedule, manual override (drag a visit to a different day/salesman and re-optimize the rest) | ✅ Done (2026-05-17) — PDF dropped |
| **5** | Polish + ship — first-run wizard (API key, default salesman config), installer build, in-app help, basic telemetry-free error log | ✅ Done (2026-05-18) — 5a polish + 5b installer + real-dataset E2E + installed-app launch verified. |
| **6** | OSRM matrix migration — replace Google Distance Matrix with self-hosted OSRM (GCC-states OSM extract) to stop the cost bleed. | ✅ Done (2026-05-18) — `installer/JourneyPlanApp-Setup-0.1.0.exe` 743 MB. |
| **7a** | Upfront salesman assignment — CP-SAT k-medoids algorithm + Assignments screen + `customers.assigned_salesman_id` column. | ✅ Done (2026-05-18) |
| **7b** | Solver enforcement — feed `COALESCE(pinned, assigned)` into `vrp.py` as a hard pin so customers stick to their assigned salesman for the whole month. | ✅ Done (2026-05-18) |
| **7c** | Capacity-aware assignment + low-freq drop priority — high-freq sticky; low-freq flexes when overflowing. | ✅ Done (2026-05-18) |
| **8**  | **Channel skills (MT / TT)** — customers tagged `MT`/`TT`, salesmen carry one or both. Eligibility = area AND channel. | ✅ Done (2026-05-18) — migration 0008. |
| **9**  | **Region as a second eligibility dimension** — customers tagged with broader `region` next to narrow `area`. Eligibility = (area OR region) AND channel. | ✅ Done (2026-05-21) — migration 0009. |
| **10** | **Full Google purge — app is fully offline.** Delete every Google API client + FirstRunWizard API-key step + keytar slots + deps. Map: Leaflet + OSM tiles + OSRM polylines. Importer requires lat+lng. | ✅ Done (2026-05-22) — installer `JourneyPlanApp-Setup-0.1.0.exe` 778.9 MB. |
| **11** | **100% sticky assignment + tighter day-balance.** No-drop assignment (overflow → `cap_slack`); `_MAX_VISITS_PER_DAY` 15→12; `_FIXED_COST` 15→40. | ✅ Done (2026-05-22) — bundled with 11.1. |
| **11.1** | **Calibration: stronger balance + softer consolidation.** `balance_lambda` 1.0→3.0; `_FIXED_COST` 40→20. Resolves Sohar #1 hollow-out + Rumais empty days. | ✅ Done (2026-05-22) — installer 778.9 MB at `installer/`. |
| **12** | **Analytics dashboard.** New `/analytics` route surfaces per-salesman load, capacity utilization, week balance, DOW heatmap, facetime/drive split, frequency conformance, red-visit count. New dep: `recharts`. | ✅ Done (2026-05-22). Shipped to prod with the v0.12.1 audit-hardening rebuild on 2026-05-23 (`JourneyPlanApp-Setup-0.1.0.exe` 791.8 MB). |
| **12.1** | **Audit-hardening pass.** Codebase probe + 19 fixes across four phases — OSRM client port default, dead-code removal, stale-write race guard in MapScreen, TopoBg `prefers-reduced-motion`, `osrm:health` sidecar-booting/error split, salesman-delete final-plan guard, diagnostics retry-with-backoff + secondary log path, `balance_lambda` single-source consolidation, OSRM startup-fail + post-handshake-death surfacing, migration 0010 dropping vestigial geocode schema, per-group self-matrix replacing the 25-pair OSRM over-fetch, dev/installer `userData` + OSRM-port split. | ✅ Done (2026-05-23) — tagged `v0.12.1`. |
| **12.2** | **Brand icon encoder rewrite.** v0.12.2's `to-ico`-based pipeline shipped an ICO whose frames stored 32bpp data with a 24bpp directory entry → Windows rendered every icon surface as dithered green noise. Hand-rolled PNG-in-ICO writer replaces `to-ico`. Installer rebuilt at 755 MB; full uninstall + userData wipe + Windows icon-cache clear + fresh install verified end-to-end. | ✅ Done (2026-05-23) — commit `dce22b5`. |
| **12.3** | **VRP visit-cap soft-overflow fix.** Phase 11's "100% sticky + no-drop" was broken by a HARD per-day visit cap of 12 with no soft fallback — when sticky-assigned salesmen saturated all working days at the cap, freq=1 customers got dropped with "no eligible vehicle (unexpected with soft capacity)". Visits dimension now mirrors the Time dim's hard (12+6) + soft (5000/extra-visit) pattern. Verified end-to-end: 2 previously-dropped Muscat customers placed on s4 with 1 day overflowing to 14 visits / 788 min (paints red), 100% coverage restored. | ✅ Done (2026-05-24) — commit `9867025`. |
| **13** | **Plan-scoped salesmen + all-routes map.** Migration 0013 snapshots each plan's roster into `plan_salesmen`; `visits` rebuilt without the FK-to-roster cascade, so roster deletes never touch existing plans/analytics. Map screen draws every salesman's routes in unique colors with week/salesman/day filters + clickable legend. | ✅ Done (2026-06-10) — live-verified + installer rebuilt same night. |
| **12.4** | **Day-balance calibration: overflow penalty + fixed cost + time budget.** Live data showed weeks with red days alongside underloaded same-week days (s3 Muscat #2 06-08 with 1 visit while 06-11 ran 169 min red). Drive cost self-protects route quality, so leaned on overflow penalty: `_OVERFLOW_PENALTY_PER_MIN` 100→200, `SetFixedCostOfAllVehicles` 20→10, solver `time_limit_seconds` 300→480/week (8 min/week, ~32 min total). End-to-end: red visits −28% (83→60), red days −11%, worst day −30 min, objective −7.8%. Routes stayed tight (avg drive/visit 5.8 min unchanged). 2 of 4 s3 weeks rebalanced fully; 2 weeks still have residual overflow from multi-customer-move local-optima GLS missed. | ✅ Done (2026-05-24) — commit `f2c93be`. |

---

## Decision log (append-only, dated)

Older entries live in `docs/memory-archive/`: [2026-05-14 to 2026-05-20](docs/memory-archive/decision-log-2026-05-14-to-2026-05-20.md) · [2026-05-21 to 2026-05-24](docs/memory-archive/decision-log-2026-05-21-to-2026-05-24.md) (v0.12.x audit-hardening, brand icon, solver calibration, and the Phase 9–12 ship entries).

### 2026-09-23 — Drag-to-reassign is now atomic
- **Phase change:** still Phase 13. **Installer rebuilt 2026-09-23 07:59 (750 MB) — current ship.** Sidecar unchanged (bundled sha `5917e7c8…` = repo); asar verified to carry `resequenceDay` + the renderer error-path reload. 13 migrations, none new — install-over safe. **Installed on prod 2026-09-28.**
- **Shipped:** `planner.reassignVisit` used to re-sequence and write each affected day in turn (2 days for a day move, up to ~8 for a cross-salesman handover), so an OSRM/sidecar failure mid-way left a half-applied move (duplicate visit, wrong counts). `resequenceDayInternal` → `resequenceDay`, which solves and returns rows without writing; all day rewrites + the plan-snapshot insert now commit in ONE `getDb().transaction`, with every `await` outside it (better-sqlite3 transactions are sync). The snapshot insert also moved behind validation — a rejected drop no longer grows `plan_salesmen`. `PlanCalendarGrid` now reloads the plan on error as well as on success.
- **Decisions:** the "destination day first → recoverable duplicate" ordering from 2026-06-10 / 2026-09-21 is superseded — with an atomic commit, order no longer matters.
- **Gates:** `pnpm typecheck` clean. No sidecar change. Not live-tested with a forced mid-move failure.
- **Next session should start with:** user installs the 07:59 build and smoke-tests a drag (same-salesman day move + cross-salesman handover), plus the 2026-09-21 checklist below.

### 2026-09-21 (later) — Calibration ramp + week-balance fixes, then SHIPPED to installer
- **Phase change:** still Phase 13. **Installer rebuilt — this is now the current ship.**
- **Shipped:**
  - **Drive-time calibration is now a continuous ramp** (`osrm_matrix._multiplier_at`). The urban/suburban/highway multipliers used to STEP at 600 s and 1800 s, so raw 599 s → 839 s but raw 600 s → 690 s: calibrated cost was non-monotonic in true drive time and the VRP could be handed a cheaper number for a genuinely longer leg. Anchors moved to band midpoints (300 / 1200 / 2400 s) with linear interpolation between them, so every value the 2026-05-18 spot-check justified is preserved EXACTLY and only the transitions change. Verified strictly non-decreasing over 0..7200 s (was 2 inversions); aggregate effect on a uniform sweep **+0.73%**, so no re-baseline needed. `OSRM_*_MULT` env overrides still work and now set the anchor values.
  - **freq=1 bundle cap** (`cycle_assignment`) — the freq=1 counterpart of the 2026-05-21 freq=2 cap. Heat preference was absorbing: first customer makes a week hot, all later freq=1 in that cluster pile on. A 40-customer freq=1 territory went **40/0/0/0 → 10/10/10/10**; clusters of ≤2 still bundle fully.
  - **`week_load` is now per effective salesman** (unpinned share one pool). The roster-wide counter optimised the wrong quantity — two salesmen could land on {0,0,2,2} and {1,1,3,3}, each idle half the month, while global totals looked flat. Both now cover 4/4 weeks in the repro.
  - Also: `-1` same-node distance sentinel clamped at the OSRM boundary (not just in `cache.ts`); `_multipliers()` read once per payload instead of once per cell (~370k env reads per 350² table, on the event-loop thread).
- **Decisions:**
  - **`test_cycle_different_salesmen_do_not_share_cluster_heat` was asserting the bug.** It required S2's freq=1 to avoid W0/W2 because S1 had loaded them — i.e. it read the shared roster-wide counter. Two salesmen work in PARALLEL; making S2 dodge S1's weeks serialises independent people and is exactly what caused the idle-half-month pathology. Rewritten to assert heat isolation directly (S2's second freq=1 bundles onto S2's own hot week), which is what the test's own comment always described.
  - **Utilization slider still parked** at user's instruction — `targetUtilizationPct` remains inert. Unchanged from the earlier entry today.
- **SHIPPED:** `installer/JourneyPlanApp-Setup-0.1.0.exe` rebuilt **2026-09-21 03:01 (750 MB)**. Sidecar `sha256 5917e7c8…` (02:56), verified byte-identical to the copy inside `win-unpacked/resources/sidecar/`. Renderer asar confirmed to carry the Region column and the derived period-end. **13 migrations, no new migration — install-over is safe and the existing DB is untouched.**
- **Gates:** 77 pytest green, `pnpm typecheck` clean, ruff clean, mypy clean (21 files).
- **Next session should start with:** user installs over and smoke-tests (see checklist note below). Then the utilization slider decision.
- **Smoke-test focus for this build (drive-time numbers WILL move slightly):** generate a plan on the real dataset and compare total drive/distance vs the 2026-07-05 build — expect roughly +1% on drive time, not more. Check the Analytics **Region** column populates, that week balance no longer shows a salesman with empty weeks, and that Suggest team size returns a roster after Compute Assignments (previously returned nothing).

### 2026-09-21 — Review fixes shipped (Tier-1 + Tier-2), analytics Region column, docs refresh
- **Phase change:** still Phase 13 shipped. Code-only changes; **installer NOT rebuilt** — dev tree is ahead of `installer/JourneyPlanApp-Setup-0.1.0.exe` (2026-07-05 22:03).
- **Shipped — Tier-1 (all six, each verified in source, two covered by new tests):**
  - `team_sizing.py` now strips pins before the virtual-roster solve. Pins name REAL salesman ids, virtuals are 900000+, so after any Compute Assignments run every customer was unservable and Suggest team size returned an empty roster. Test: `test_pinned_customers_still_get_a_recommendation`.
  - `vrp.solve_week` reports drops **per clone node**, not per customer id. A partially placed multi-visit customer used to lose visits silently with `solver_status="success"`. New reason string `repeat visit this week had no remaining eligible day`. Test: `test_partially_placed_multivisit_customer_reports_the_dropped_clone`.
  - `index.ts` boot: `runMigrations()` failure → error dialog + clean quit; `startSidecar()` failure → logged, window still opens; whole `whenReady` chain has a `.catch` that kills both children. Added `proc.on('error')` to BOTH spawners (`sidecar.ts`, `osrm.ts`) — a quarantined exe used to leave a windowless zombie with OSRM running.
  - `planner.reassignVisit` same-salesman branch now re-sequences DEST before SOURCE, matching the cross-salesman invariant. A mid-flight OSRM failure duplicates the visit (recoverable) instead of deleting it.
  - `CustomersScreen` EditPanel + `SalesmenScreen` SalesmanForm now have `key=`. Switching Edit targets (or Add-while-editing) kept the first record's draft and saved over the WRONG row.
- **Shipped — Tier-2:** generatePlan save wrapped in one transaction (no more empty-shell plans); `customers:upsert`/`salesmen:upsert` now validated by zod schemas living beside the importer's (`customerUpsertSchema`/`salesmanUpsertSchema` in `import/validate.ts`); customer-delete and dataset-delete refuse to touch **final** plans; an import where 0 rows validate now throws instead of creating+activating an empty dataset; OSRM `/table` chunking tiles BOTH sides (a 400-customer self-matrix used to emit 400 calls of 401 points and fail); OSRM 4xx now maps to `OsrmRequestRejectedError` → HTTP 502 naming `--max-table-size`, no longer "OSRM unreachable, run pnpm osrm:setup"; sidecar `ValueError` → 422; `/route/osrm` <2 waypoints → 422; uvicorn `log_level="warning"` + stderr INFO filter + `stopping` flags, killing ~6 bogus diagnostics entries per launch; Excel export clamps the `-1` sentinel and shades off-days from each salesman's OWN working days; `defaultPeriodStart`/`defaultPeriodEnd` use local-date formatting (Oman 00:00–04:00 used to yield a Saturday); SQLite `CURRENT_TIMESTAMP` rendered via new `formatDbTimestamp` (was showing 4 h early); `diagnostics.rotateIfNeeded` truncate fallback can no longer throw out of `appendError`; PlanScreen `reloadPlans` stale-closure re-select, `dowOf` instead of `new Date().getDay()`, and `createRoster` now catches and warns about partial roster creation.
- **Decisions:**
  - **Import template auto-map fixed by normalising separators**, not by renaming template columns — `autoSuggestMapping` strips `_ . -` to spaces before matching, plus widened facetime/salesman patterns. Our own `journey-plan-template.xlsx` previously auto-mapped only 7/14 columns (regexes used optional SPACES, template uses snake_case); now 14/14, and legacy space-separated headers still map.
  - **`periodEnd` is now derived and read-only in the UI.** `cycle_assignment.NUM_WEEKS = 4` and `assemble.py` reads only `periodStart`, so an edited end date changed the label and nothing else. Variable-length plans remain parked.
  - **Team-sizing grow-loop REMOVED, not repaired.** It exited on its first iteration every time since the 2026-05-20 soft-capacity change. Growing N cannot help: `_FIXED_COST` makes the VRP consolidate into as few vehicles as it can, so handing it more vehicles changes nothing (verified empirically — N=2 recommended at targets 10/50/95). Now one run at the arithmetic lower bound. **`targetUtilizationPct` is therefore inert** — see Open questions.
  - Did NOT touch the OSRM duration calibration bands or `cycle_assignment`'s freq=1 bundling / per-salesman week balance. All three change every generated plan and need a product call, not a code call. See Open questions.
- **Next session should start with:** the two open questions below, then `pnpm dist` to ship these fixes (user install-over still pending from 2026-07-05).
- **New open questions:**
  - **Target utilization slider:** make it real or remove it? Making it real means splitting the clusters the VRP forms (recommend N from `load / (capacity × target)`, then k-split the biggest territories) — that is a feature, not a fix. Removing it means dropping the control from the Suggest-team-size dialog.
  - **Calibration bands are discontinuous** (`osrm_matrix._calibrate_duration`): raw 599 s → 839 s but raw 600 s → 690 s, so cost is non-monotonic in true drive time near the 10- and 30-min edges and the VRP can prefer a genuinely longer leg. A piecewise-linear blend fixes it but shifts every drive estimate, so it needs a re-baseline on a real dataset.
  - **`cycle_assignment` freq=1 hot-week bundling has no counter-balance cap** (freq=2 got one 2026-05-21), and `week_load` is roster-wide rather than per salesman — a (salesman, area) cluster of only freq=1 customers can collapse into week 0. Design intent or defect?
- **New risks / issues:** none new. Full review report with everything deliberately NOT fixed (~60 findings, tiered) is at `docs/reviews/2026-09-03-full-codebase-review.md`.

### 2026-09-03 — Full codebase review (assessment only; zero code change)
- **Phase change:** still Phase 13 shipped; no new installer.
- **Shipped:** `docs/reviews/2026-09-03-full-codebase-review.md` — 5-reviewer fan-out (solver, sidecar HTTP/OSRM, main, renderer, contracts) + manual verification of every High. ~60 findings, 6 Tier-1.
- **Decisions:** review only, no fixes applied — user to pick fix order. All wire contracts (Pydantic↔TS↔preload↔ipcMain↔SQLite) traced clean; no live Google code.
- **Tier-1 (verified in source):** (1) `team_sizing.py` passes pins through to virtual salesmen → Suggest team size returns nothing once Compute Assignments has run; (2) `vrp.solve_week` reports unassigned per customer-id, so partially-placed multi-visit clones vanish silently with status success; (3) `index.ts` `await startSidecar()` unguarded + no `proc.on('error')` → windowless zombie with OSRM left running; (4) `planner.reassignVisit` same-salesman branch re-sequences source before dest → visit lost on transient sidecar error; (5)(6) `CustomersScreen` EditPanel / `SalesmenScreen` SalesmanForm unkeyed → edits save to the wrong record.
- **Next session should start with:** Tier-1 one-liners (#1 strip pins, #5/#6 `key=`), then #3/#4. Fix order in the report's last section.
- **New risks / issues:** `osrm_matrix` chunking degenerates for area groups >350 customers (per-destination calls, URL overflow → misreported as "OSRM unreachable"); import template headers never auto-map (regexes use spaces, template uses underscores); `periodEnd` is editable but never read by the solver.

### 2026-07-05 — Prod-plan triage: freq-8 data bug (user-fixed) + polyline NoRoute hardening

- **Phase change:** none — bug triage on prod plan (user's live install DB, plan 39→40).
- **Two unrelated issues the user hit on a real plan:**
  - **"Salalah/Al Kamil only used 2 days a week" — DATA, user-fixed.** Every customer in those two compact single-town territories was tagged `monthly_frequency = 8` (twice-weekly). Freq 8 = 2 visit-days/week minimum, and the drive-min + consolidation objective packed the whole ~30-stop town onto the same 2 days (each ~810 min facetime vs 540-min window → red), leaving Mon–Wed idle. **Not a solver bug** — the "don't spread tight clusters to fill blank days" durable principle working as intended. User confirmed freq 8 was a data-entry mistake, lowered to 4, regenerated (plan 40 "Final Pre-Sales JP"): Salalah now 30 custs × freq 4 = 120 visits over 20 days. Resolved. **No code change.**
  - **Map polyline `OSRM /route failed: OSRM HTTP 400` spam — CODE, fixed this session.** Root cause was ONE customer, id 20275 "Noor Express - Ansab" (23.5385455, 58.3587136): pin snapped 7.4 m onto a one-way/dead-end segment the OSRM graph can enter but not leave (`→20275` Ok, `20275→` anything NoRoute). As an intermediate stop it must be departed, so OSRM returned `NoRoute` (HTTP **400**) for the whole day-route — hit all 4 Muscat #4 days it's scheduled. User fixed the data (nudged pin ~31 m east to 23.538546, 58.359014, which routes both ways).
- **Code fix shipped ([osrm_route.py](sidecar/sidecar/maps/osrm_route.py)):** the client raised `OsrmRouteError` on *any* non-200 → sidecar 503 → map logged an error and re-fetched (the spam). But the graceful "empty coords → dashed fallback" branch only ran on HTTP 200, and **modern OSRM returns HTTP 400 for `NoRoute`/`NoSegment`**, so that branch was dead code. Now a 400 whose body `code ∈ {NoRoute, NoSegment}` returns an empty polyline (map draws dashed straight legs); any other non-200 still raises so real bugs surface. Added `_empty_route()` helper (dedups 3 call sites). One bad pin now degrades silently instead of 503-spamming.
- **Verification:** mocked all 4 paths against the real client — `NoRoute`/`NoSegment` 400 → empty no-raise, `InvalidQuery` 400 → still raises, `Ok` 200 → normal polyline. ruff + mypy clean. (Live OSRM was down mid-session — app closed — so used response mocks; logic is server-agnostic.)
- **Shipped:** commits `482caf6` (fix) + `41c10da` (memory) pushed to `main`. **Sidecar rebuilt** (PyInstaller detected the `osrm_route.py` change): `sidecar/dist/sidecar.exe` sha `8c585ce…`→`73efd5aa…`, smoke-tested (boots, `SIDECAR_READY`, `/health` ok). **Installer rebuilt 2026-07-05 22:03 — `installer/JourneyPlanApp-Setup-0.1.0.exe` 748 MB**, `pnpm dist` exit 0; bundled `resources/sidecar/sidecar.exe` sha = repo `73efd5aa…` (verified byte-identical). No new migration → low-risk install-over. **User install-over pending.**
- **Next session should start with:** clean — polyline hardening shipped end-to-end (source + installer). Only open item is user installing the 22:03 build over prod if they want the belt-and-braces in the running app (the data pin-fix already clears the actual error).

### 2026-06-14 — Analytics polish: distance-in-km + lazy-load + small fixes; OSRM calibration closed

- **Phase change:** still Phase 13. User cleared most of the optional backlog in one pass.
- **Shipped (3 commits, pushed):**
  - **`4cae5c3` Small fix — `-1` distance clamp.** Clamp OSRM same-node sentinel to ≥0 at cache write AND read in [cache.ts](app/src/main/cache.ts). Read-side clamp neutralizes the 2 rows already poisoned in existing DBs without a migration — now relevant because the new distance total sums `distance_meters`.
  - **`a15106a` Polish — lazy-load Analytics.** `React.lazy` + `Suspense` on the `/analytics` route → recharts (~895 kB) splits into its own chunk; main renderer bundle 1.9 MB → 1.05 MB. PDF export window (loads the route) covered by its 20s ready-timeout.
  - **`61d5287` Polish — distance-driven km + freq empty-state.** New IPC `plan:distance` → [planDistance.ts](app/src/main/planDistance.ts): reconstructs road distance at render time by grouping persisted route legs per (salesman, day) and summing `distance_cache` hits. **No visits column, no migration, no regenerate — works on every existing plan.** Surfaced as a KPI tile + per-salesman "Distance" column. Frequency panel shows a "perfect conformance" note instead of an empty grid when zero mismatches (the small UX nit from the PDF review).
- **Decisions:**
  - **Distance via render-time cache reconstruction, NOT a persisted column.** The solver only emits `drive_minutes_to` per leg (route-level `total_distance_meters` exists only in the OSRM polyline response). Persisting would need a migration + regenerating every existing plan (~30 min each). The `distance_cache` already holds `distance_meters` for every solved leg, so reconstruction lights up all existing plans immediately. If distance is ever wanted in the Excel export too, persisting becomes worth it then.
  - **Distance mirrors drive-TIME leg semantics exactly:** every customer→customer hop + home→first commute leg when `includeCommute` (snapshot) is on; final leg home excluded (no visit records it), same as the drive-time total. So km is the spatial companion to the drive time already shown — same legs. Cache misses are surfaced + excluded, never guessed.
  - **OSRM-multiplier calibration closed (no change).** User verified in prod; my live OSRM city-pair spot-check confirmed near-exact distances + realistic-conservative durations. Env override stays if real execution data ever warrants it.
  - **Map km > Analytics km by ~2% is EXPECTED — do not "fix".** User noticed Map showed 9,616.6 km vs Analytics 9,420 km for plan #10. SAME legs (1,932 / 120 routes, verified), different OSRM service: Analytics sums `/table` matrix legs from `distance_cache` (what the solver priced; keeps km consistent with the drive-time beside it); Map sums the `/route` polyline it draws (continuous thread through each stop → via-point approach/turn handling adds ~0.1 km per intermediate stop). Both correct. User chose "leave as-is + note" over aligning (alignment options were: persist `/route` distance at generation [migration + regenerate], or live `/route` in Analytics [~120 calls/load, rejected]). Shipped `8191fa8`: footnote under the Distance column (carries into PDF) + tooltip on the KPI tile.
- **Verification:** typecheck clean. `electron-vite build` confirmed the recharts chunk split (894 kB `AnalyticsScreen` chunk vs 1.05 MB main). Temp main-process hook (removed before commit) ran `distanceForPlan(10)` + re-exported the PDF: total 9,419.9 km, found 1,932 legs (= 2,052 visits − 120 salesman-days, commute off), **0 missing**, 47 km/h avg; per-salesman km maps to the right rep (Kamil #1 highest distance 2,805 km despite mid load = spread-out territory). PDF pages eyeballed: Distance tile, Distance column, and "perfect conformance" note all render; `break-inside` still paginates cleanly (taller 11-tile KPI strip pushed the load card to page 2).
- **Installer rebuilt 2026-06-14 16:42 (748.8 MB), `pnpm dist` exit 0.** Verified: `app.asar` (77 MB) carries the new code (`plan:distance` ×2, `Distance driven`, recharts chunk packed inside the asar); bundled `resources/sidecar/sidecar.exe` sha = repo `sidecar/dist/sidecar.exe` (8c585ce…, the 12:19 build, no Python change); OSRM graph bundled. No new migration → low-risk install-over. **User install-over pending.**
- **PROD VERIFIED (2026-06-14 16:53):** user installed the 16:42 build over prod and exported analytics for a new plan "Without Overtime Sat" (11-rep roster, 692 customers, 2,768 visits, 100% coverage, 251h29m drive). 6-page PDF checked page-by-page: Distance tile (11,367 km, 45 km/h avg), per-salesman Distance column for all 11 reps, table split cleanly across pages 2–3, and the matrix-vs-Map note rendered. **The missing-legs safety net fired correctly for the first time on real data:** "4 legs not in the distance cache were excluded" (4 of ~2,548 = 0.16%, surfaced + excluded not guessed) — expected when a manual drag-drop / post-generation customer edit creates a consecutive pair the solver's matrix never priced; regenerate refreshes. User sign-off "All good". Nizwa #2 again surfaced the high-km/low-visit spread-territory insight (2,308 km on 224 visits).
- **Next session should start with:** clean — the whole 2026-06-12 + 06-14 analytics batch is shipped and prod-verified.
- **New open questions:** none.
- **New risks / issues:** distance reconstruction does ~N point-queries against `distance_cache` per plan load (1,932 for plan #10, indexed, <100 ms) — fine on desktop; the small-fixes "(none open)" backlog is now empty.

### 2026-06-12 — Analytics PDF snapshot export + Plan→Analytics deep-link

- **Phase change:** still Phase 13. User request: make Analytics downloadable as PDF or contained HTML.
- **Shipped (2 commits, pushed):**
  - **`8df52bc` Analytics PDF export.** "Download PDF" button → save dialog → hidden 740 px BrowserWindow loads `#/analytics?planId=X&print=1` → Chromium `printToPDF` (A4 portrait, printBackground, plan-name + page-number footer). New [app/src/main/export/pdf.ts](app/src/main/export/pdf.ts); `printMode.ts` adds `body.print-mode` (app chrome hidden, single-column chart grid, `break-inside: avoid` per card, chart animations off, freq-details forced open, white paper). Renderer signals ready over IPC 2 frames + 300 ms after charts mount; main guards with a 20 s timeout. Two non-obvious requirements: `backgroundThrottling: false` (hidden windows throttle rAF — ready signal would stall forever) and the 740 px window width ≈ A4 printable width (ResponsiveContainer bakes measured pixels into the SVGs; printToPDF never re-measures).
  - **`a6b3f0c`** "View analytics" button on PlanScreen deep-links `/analytics?planId=X` (closes polish item #4).
- **Decisions:**
  - **PDF over contained HTML** (user approved the recommendation): corporate mail gateways commonly block `.html` attachments; `printToPDF` = zero new deps, fully offline, vector recharts output. HTML snapshot dropped.
  - **`?planId=` honored only when the plan is in the ACTIVE dataset's plan list.** Caught live: exporting plan #9 (dataset 10) while dataset 11 was active charted coverage/frequency against the wrong customer set (513/0, 0.0%) — `computeMetrics` filters customers by the plan's datasetId but `customers:list` serves the active dataset. The interactive list was never exposed (dataset-scoped); only the new param could reach it. In print mode there is no list[0] fallback — export times out loudly rather than silently exporting a different plan.
- **Verification:** typecheck clean. Headless E2E via a temp main-process env hook (removed before commit): plan #10 "Nestle Van 20 Mins" → 4-page A4 PDF eyeballed page-by-page (513/513 = 100% coverage, vector charts, heatmap colors intact, breaks land between cards, footer paginates). Sample kept at `tmp/analytics-test.pdf` (gitignored). Installer rebuilt 17:55 exit 0; asar verified to carry the new IPC channel. Dev app closed clean.
- **Next session should start with:** clean — feature fully shipped and verified on BOTH dev and prod.
- **PROD VERIFIED (same evening, 22:00):** user installed the 17:55 build over prod, generated a NEW plan "Reduced HC Nestle" (11-rep reduced-headcount roster, 692 customers, 2,768 visits, 100% coverage, solver success, 83% cache hit) and exported its analytics PDF — 6 pages, page-by-page checked: tall 11-rep load card moved whole to its own page (`break-inside` working as designed, no mid-row splits), all cross-page numbers reconcile (red 51+40=91, pie 85.7% = facetime/total hours, footer "Page 6 of 6"). Dev verification earlier: plan #10 export from Downloads, "rendered well" (user). Implicitly also confirms plan generation works on the new prod build.
- **Prod data note:** prod now carries plan "Reduced HC Nestle" (11-rep roster) alongside #11 "Final" / #12 "Plan 2026-06-14" — user is exploring reduced headcount scenarios; Muscat #1 (111.3%) and Muscat #4 (107.8%) run over capacity by design (91 red visits = the team-sizing signal).
- **New open questions:** none.
- **New risks / issues:** cards taller than one A4 page (e.g. a 14-rep load table) split mid-card — `break-inside` is a hint, acceptable. Print window loads the full app shell for its ~5 s life (Layout's 3 s dataset poll runs once or twice) — harmless.

### 2026-06-11 — Verification day: dual dev+prod run, aborted-generation safety, deep DB sanity (zero code change)

- **Phase change:** still Phase 13. No code shipped; pure verification + ops.
- **Aborted generation is damage-free (confirmed live):** user closed the dev app mid-generate (forgot Compute Assignments first). Verified: no plan row (plan+visits only write at the 95% "Saving" step), clean WAL close, only artifact = beneficial distance-cache growth (403k → 404.8k rows).
- **Dev + installer ran SIMULTANEOUSLY without conflict** (user compared plans side by side): OSRM 5051/5050 + userData split held; both closed clean.
- **New plans this session:** dev #9 "Nestle Van Final" + #10 "Nestle Van 20 Mins" — 2,052 visits each on the 5-rep (post-Rumais) roster, full coverage (his 5 customers absorbed). Prod #11 "Final" (14-rep snapshot) + #12 "Plan 2026-06-14" (12-rep snapshot, 2,768 visits, full coverage).
- **Deep DB sanity (quiesced, both DBs): ALL GREEN.** Full integrity_check ok, 0 FK violations, 0 orphan snapshot rows, 0 visits w/ salesman missing from snapshot, 0 cross-dataset visits, 0 same-day dupes, 0 broken sequences, ~0% free pages. Only finding: benign `-1` distance / 0-duration OSRM sentinel for one ~3 m-apart customer pair (both DBs) — logged under Small fixes (clamp at cache-write in cache.ts on next polish pass).
- **Ops lesson:** sandboxed Get-CimInstance/Get-NetTCPConnection can't see host processes/ports — process checks for the detached app need `dangerouslyDisableSandbox`.
- **Next session should start with:** clean. Prod on the 00:07 build, both DBs verified. Small-fixes backlog has the -1 clamp.

### 2026-06-10 (post-midnight) — Cross-salesman drag-drop unblocked + whole-customer handover semantics

- **Phase change:** still Phase 13. User hit this testing the fresh prod install: couldn't drag a visit Muscat 1 → Muscat 2 ("assigned to another salesman").
- **Root cause:** after Compute Assignments EVERY customer carries `assigned_salesman_id`, and `customerCoversSalesman` treated it as a hard pin — so cross-salesman drags were always rejected. Second hidden layer: the sidecar day-resequencer pre-screens eligibility with pin-wins semantics and would have silently dropped the moved customer from the destination day.
- **Shipped (2 commits):**
  - **`d1e74c3`** — manual drag overrides the ALGORITHM's assignment (user PIN from the import stays hard; area/region/channel/working-day/allowed-day checks still apply). Resequence requests now pin every day member to that day's salesman: day membership is the caller's validated decision, the day-solver only sequences (can never drop a moved customer).
  - **`6682569`** — **cross-salesman drag = relationship handover (user decision):** ALL of the customer's visits in the plan move to the new salesman, siblings keep their days, dragged visit lands on the drop date. Same-salesman day-moves stay single-visit. If the dest salesman doesn't work one of the visit days → whole handover rejected up front, nothing moves. Dest days rebuilt before source days (failure leaves a visible duplicate, not a vanished customer). New repo fn `listVisitsForCustomerInPlan`.
- **Decisions (user, 2026-06-11 q&a):** (a) move ALL visits on cross-salesman drag — chosen over single-visit and ask-each-time; (b) `assigned_salesman_id` NOT auto-updated — the handover is plan-level only; future generations follow the Assignments screen until changed there.
- **Verification:** `pnpm typecheck` clean. No sidecar change (pin-override is TS-side; `customer_eligible_for_salesman` pin-wins semantics confirmed by reading vrp.py). Installer rebuilt (3rd build tonight — the 23:26 and the d1e74c3-only builds are superseded); user install-over + live drag test pending.
- **Also fixed (`13251c3`):** DOW-heatmap salesman names clipped — label column 140→210px; label cell flex→block so `text-overflow: ellipsis` actually renders (ellipsis doesn't apply inside a flex container). User initially suspected the new Saturday working days; actual cause was the long "Suggested — " names vs the fixed column.
- **FINAL BUILD of the night: `installer/JourneyPlanApp-Setup-0.1.0.exe`, 748.8 MB, 2026-06-11 00:07:43** — carries Phase 13 + drag unblock + whole-customer handover + heatmap fix. This supersedes the 23:26 and 23:56 builds.
- **PROD HANDOVER VERIFIED (2026-06-11, DB-level + user eyes-on):** plan 10 "Nestle 2 Saturday" — SPAR SPR1009 CBD (freq 4) handed Muscat #1 (#15) → Muscat #2 (#16): 4/4 visits moved (dragged → 06-17, siblings kept 06-22/06-29/07-06), assignment untouched (#15) as designed, zero same-day duplicates, ALL (plan, salesman, day) sequences in the whole prod DB contiguous. Prod runs 13 migrations; plans 6–10 carry 14-salesman snapshots; pre-0013 empty shells (3–5) have none, expected. User reinstalled the final 00:07 build.
- **Next session should start with:** clean. Prod is current with the 00:07 build; all of tonight's features live-verified.
- **New risks / issues:** a freq=4 handover re-sequences ~8 days ≈ 15–25 s with the "Moving…" state showing; acceptable. Dragging onto a date where the customer already has a visit still merges silently (pre-existing behavior, unchanged).

### 2026-06-10 (night) — Phase 13: plan-scoped salesmen (migration 0013) + all-routes map view

- **Phase change:** Phase 13. Two user-requested features, both shipped on `main` (installer rebuilt later the same night — see below).
- **Shipped (2 commits):**
  - **`de45276` Plan-scoped salesman snapshots.** New `plan_salesmen` table (migration 0013) freezes the roster into each plan at generation time; `visits` rebuilt WITHOUT the `ON DELETE CASCADE` FK to `salesmen` (salesman_id is now a plain snapshot key). Deleting / clear-all-ing salesmen only affects future plans — existing calendars, analytics, map, and Excel exports resolve from the snapshot via `plan:get` (now returns `salesmen`), `repo/planSalesmen.ts`. Migration backfills snapshots for existing plans from the current roster. Drag-drop to a roster newcomer adds him to the plan snapshot on the fly (`addSalesmanToPlanSnapshot`). The `salesmen:delete` final-plan guard + `countVisitsInFinalPlansForSalesman` were removed (obsolete — delete is no longer destructive); SalesmenScreen confirm copy rewritten.
  - **`8e64022` All-routes map view.** Selecting a plan draws EVERY salesman's day-routes at once, one stable color per salesman (16-color palette → golden-angle overflow). Filters: week (7-day buckets matching Analytics), salesman, day; clickable color legend with visit counts doubles as a salesman switcher. OSRM polylines fetched per (salesman, day) at concurrency 6, progressively painted, cached per plan; dashed straight-leg fallback on OSRM failure. Numbered stop markers only when exactly one route is displayed; otherwise colored dots over a dimmed customer layer. Unused `plan:visitsForDay` IPC removed.
- **Decisions:**
  - **Snapshot model (copy-on-generate), not a per-plan roster editor.** The Salesmen screen stays the single roster used for NEW plans; each plan freezes its own copy. This satisfies "each plan and its analytics are unique" with zero workflow change.
  - Analytics "salesmen total" now means the PLAN's salesman count (snapshot), not the live roster — intended semantic change.
  - Plans deleted → snapshot cascades with the plan (`journey_plan_id` FK). Pre-0013 empty-shell plans (whose visits were wiped by old cascades) get no snapshot — nothing recoverable, they render as before.
- **Verification:** `pnpm typecheck` clean. Migration smoke test (tmp/test-migration-0013.mjs, run under `ELECTRON_RUN_AS_NODE` for the Electron ABI): backfill, no-cascade delete, clear-all, plan-delete cascade, UNIQUE + indexes, FK check — 14/14 pass. Dry-run on a copy of the real dev DB: plan #8 "Nestle Van" 2,052 visits → 6 snapshot rows, 0 FK violations, quick_check ok, 6 ms. No sidecar change (pytest not needed).
- **LIVE VERIFICATION (same night, dev app): both features PASS, user sign-off.** Migration 0013 applied on the real dev DB at launch. Map: all-routes polylines "render nicely" (user). Salesman delete: user deleted `Suggested — Rumais` (id 38) from the roster — he STILL shows on map, analytics, and calendar for plan #8 (snapshot intact, 20 visits, total 2,052 unchanged, his 5 assigned customers SET NULL as designed, no IPC errors). Roster down to 5.
- **Live fix during the session (`7058b0c`):** dev log showed each route fetched 3–5× — the fetch effect re-runs on every geometry arrival but keys were only marked in-flight inside the async worker. Now the whole batch is marked in-flight synchronously in the effect body.
- **Installer rebuilt same night (23:26, 748.8 MB)** after a clean close-out: no processes left, WAL checkpointed, quick_check ok, 0 FK violations, 13/13 migrations, working tree clean. Bundle verified: 0013_plan_salesmen.sql present, sidecar.exe = the 12:19 build (no Python change). **User install-over pending.**
- **Next session should start with:** confirm the user installed over prod; first prod launch applies 0013 and backfills snapshots for the NFT plans (1,212 customers, 18 salesmen). **Note: dev DB roster now lacks Rumais** (deleted in the live test) — re-add or re-run Suggest team size before regenerating dev plans, or his 5 customers go unassigned.
- **New open questions:** none.
- **New risks / issues:** first paint of an unfiltered 6×20-route plan fires ~120 local OSRM calls (~seconds, progressive). The v0.12.1 smoke-test item "salesman-delete guard" is superseded — the guard no longer exists by design.

### 2026-06-10 (evening) — Dev-launch lifecycle lesson + dev DB clean (no code change)

- **Phase change:** still Phase 12. Zero code/installer change.
- **Dev app "closed by itself" twice — NOT an app fault.** Launches hosted in a sandboxed background shell get reaped by the sandbox supervisor (whole tree killed, no crash events, no teardown log; second kill landed mid-plan-generation). Fix: **always launch `pnpm dev` DETACHED** (`Start-Process cmd /c "pnpm dev > tmp\dev-app.log 2>&1"`, sandbox disabled for the spawn) — saved as auto-memory `launch-dev-app-detached`. Close it later by CommandLine-filtered kill (`journey-plan-app|osrm-routed|@journey`).
- **Post-kill DB forensics:** integrity_check/quick_check ok, 0 FK violations — WAL rolled the interrupted write back; only artifact was an empty plan shell. SQLite survived a hard tree-kill mid-generation cleanly.
- **Dev DB cleaned (user-requested, pre-clean backup at `db-backups\journey-plan-pre-clean-20260610-180848.db`):** dropped 2 inactive datasets (1,744 customers), 2 empty plans, 2 stale assignment runs; VACUUM 52.5 → 44.8 MB. **Kept: active dataset `Van Nestle` (513 customers), 6 salesmen, 403k-row distance_cache.**
- **Next session opener:** user will work with the Van Nestle dataset in dev (launch detached!). Open business decision from earlier today still stands (3rd Sohar rep vs Buraimi rebalance on the prod dataset).
- **New risks / issues:** none.

### 2026-06-10 (afternoon) — Live-install drop regression → Visits dimension is now soft-ONLY (no hard ceiling)

- **Phase change:** still Phase 12. User's first prod plan on the new installer (plan #6 "NFT", 1,212 customers) **dropped 19 customers** with "no eligible vehicle (unexpected with soft capacity)" — violating the no-drop contract.
- **Root cause (exact math):** Sohar #2 was assigned 109 customers × freq 4 = **436 visits/month vs a hard envelope of 20 days × 18 = 360 slots** (the 12.3-era hard cap at soft+6). 436 − 360 = 76 = 19 customers × 4 — they were literally unplaceable. Roster context: only 2 Sohar reps with identical coverage; the Buraimi pocket (~50 min away) makes travel cost resist rebalancing toward Sohar #1 (109/70 split), and even a perfect split (~90/89) would sit AT the cliff. Any hard visit ceiling is a drop cliff when assignment demand exceeds it.
- **Fix (vrp.py):** Visits dimension hard capacity → the week's whole node count (i.e. unbounded); the per-salesman SOFT cap + 5000/visit penalty still does the balancing. `_MAX_OVERFLOW_VISITS` deleted. The Time dimension's hard cap (window + 1440 min) remains the true physical day bound, so pathological cram is still impossible. Overloaded rosters now produce walls of red (team-sizing signal) with 100% coverage — the designed contract.
- **Regression test:** 95×30-min customers on one 5-day salesman (19/day > old 18 ceiling) must place all 95.
- **Verification:** full pytest green post-change, ruff + mypy clean; installer rebuilt with the new sidecar.
- **User insight for the data side:** Sohar is genuinely under-staffed (179 customers × 120 min = 21.5k min vs 2 × 8.3k capacity). Red days on Sohar will persist until a 3rd rep or rebalance. Sohar #1 verified genuinely full (101% capacity, 280/240 visit slots); only ~12 of Sohar #2's customers are cheap transfers (centroids 66 km apart).
- **FINAL VERIFICATION (plan #7 "NFT 2" on the reinstalled 12:23 build): ALL PASS.** 1,212/1,212 coverage, 0 unassigned, all 9 audit categories clean, weekday consistency 1,212/1,212, windowed customer 4/4 visits at 09:07 inside 08:00–12:00, co-assignment 187/187 blocks whole, 32 over-budget days (vs 44), util 83.7%. Sohar #1 fits (worst 530/540); Sohar #2 red on all 20 days (worst 895 min) = the designed team-sizing signal. User signed off.
- **Next session opener:** clean. Open business decision (user's, not code): 3rd Sohar rep vs ~12-customer Buraimi-side rebalance via Assignments screen.

### 2026-06-10 (midday) — Co-assignment micro-clusters in /assign-salesmen + cluster-integrity audit

- **Phase change:** still Phase 12.
- **Trigger:** User asked whether very close customers share a route. Audit of plan #7: ≤200 m same-salesman pairs ride the same day 95.3% (76% as consecutive stops); 100% of the misses were capacity-forced (both candidate days at/over the visit cap) — zero unexplained splits. Cluster integrity confirmed healthy. BUT 89 close pairs (≤0.5 km) were split across two salesmen — un-fixable downstream of assignment.
- **Shipped — co-assignment in [assign_salesmen.py](sidecar/sidecar/solver/assign_salesmen.py):** unpinned customers within **250 m** with an **identical eligibility fingerprint** (same eligible-salesman set) merge into atomic micro-blocks (union-find, nearest-pair-first) that CP-SAT must assign to ONE salesman (pairwise var equality). Guards: **500 m diameter cap** (stops single-linkage chaining — a 4-km strip of 111-m hops is not one stop; this broke 2 balance tests before the cap), 24-member size cap, fingerprint equality (never ties across channels/pins — infeasibility-safe). Groups computed once (geometry+eligibility only, stable across medoid iterations). Real-data sim: 362 groups / 917 customers, largest block 14.
- **Key diagnosis (don't "fix" further):** of plan #7's 36 cross-salesman pairs ≤250 m → 4 same-fingerprint (now fixed), **16 MT|TT neighbours (channel specialization — CORRECT to keep split)**, 16 disjoint eligibility (structurally unmergeable). Do NOT extend merging to fingerprint-intersection: it would override channel route design.
- **Also answered:** installed-app old plans show blank because the deliberate salesman wipe cascade-deleted all visits (`visits.salesman_id ON DELETE CASCADE`); plans remain as empty shells. Expected behavior, documented in clearAllSalesmen.
- **Verification:** 65/65 pytest, ruff + mypy clean. One pre-existing test fixture respaced (test_workload_balance_when_travel_equal — its default coords stepped ~150 m, accidentally forming a chain the new rule correctly treats as blocks).
- **Installer:** rebuilt post-change (sidecar.exe + `pnpm dist`) — see commit; user must install over for co-assignment to reach prod.
- **Next session should start with:** user re-runs Compute Assignments + regenerate on real data; check the 4 fixed pairs land together and balance deltas stay acceptable.
- **New risks / issues:** co-assignment makes balance granularity coarser in dense areas (blocks move as units) — acceptable by design; watch for complaints on small rosters.

### 2026-06-10 (morning, post-ship) — Dev smoke of the full PJP batch: PASS; installer confirmed current

- **Dev test (plan #7, fresh 1,212-customer import, 18 suggested salesmen):** 100% coverage, 0 violations in all 9 audit categories, **all 1,212 freq=4 customers on the SAME weekday across all 4 weeks (zero drift)** — anchoring verified live. First visit of every day at exactly 08:00 → absolute-clock Time refactor shifted nothing. User verdict: day balance "much better".
- **Untested live (dataset had none):** visit windows, commute, freq=2/3 patterns — all covered by pytest; windows/commute confirmed inert when unused.
- **Data finding:** `Suggested — Rumais #1` carries 83 customers ≈ 17 visits/day × 30-min facetime → all his 20 days over budget (the audit's 44 over-budget days are mostly him; 18-visit days = hard ceiling). Team-sizing/assignment lever, not solver — move ~15-20 customers via Assignments screen or add Rumais #2.
- **Installer:** the 02:44 build (748.8 MB) already carries every commit through `1914159` (bundled sidecar.exe verified byte-identical to the fresh build). Confirmed to user as the ship — no rebuild needed. Template regenerated (`journey-plan-template.xlsx`, gitignored output) with the visit-window columns.
- **Ops note:** dev `pnpm dev` from this session resolves userData to the REAL `%APPDATA%\@journey\app-dev\` (not the Claude_* sandbox redirect MEMORY warned about). Seeded it from the old sandbox-path DB (52.5 MB); the fresh empty DB it created is preserved as `journey-plan.db.empty-fresh-bak` alongside.

### 2026-06-10 (late) — Audit latent-assumption fixes: scaled visit cap, freq=3 gap week, working-day spread

- **Phase change:** still Phase 12. User triaged the audit's 5 latent assumptions: #1 fix now, #2 breaks → later stage, #3 confirmed 4-week-of-working-days design is the intent (no change), #4 fix now, #5 fix now.
- **Shipped (3 commits on `main`):**
  - **`58f9903` Visit cap scales with call profile.** Per-salesman soft cap = `max(12, round(360 / avg facetime of eligible customers in week))`; hard ceiling = soft + `max(6, soft // 2)`. 10-min TT calls → 36/day; 30-min+ rosters keep the legacy 12 exactly (never scales DOWN — Time dimension governs long calls; avoids churning existing plans). `_VISIT_BUDGET_MINUTES = 12 × 30` keeps floor and scaling coupled.
  - **`18c3b09` freq=3 gap week.** Only non-consecutive patterns {W0,W1,W3} / {W0,W2,W3} (heat then load picks). No more 3-consecutive-weeks-then-2-week-hole cadence.
  - **`408d15c` Working-day-aware spread.** `_expand_customers_for_week` spread universe = pinned salesman's working days (union of roster when unpinned) ∩ customer allowed_days, with progressive fallback. Fixes lost visits for non-Sun-Thu teams (e.g. Mon–Fri). `_DEFAULT_WORKING_DAYS` is now last-ditch only.
- **Parked:** break modeling (lunch/prayer) — user: later stage. Workaround stands (shorten working window).
- **Confirmed by user:** the prod-DB salesman wipe was deliberate (no issue); plan horizon = 4 weeks of working days, not calendar month — current behavior is correct by design.
- **Verification:** 62/62 sidecar pytest, ruff + mypy clean. Solver-only changes — no TS surface touched, no migration. Installer rebuild still pending for everything shipped today.
- **Next session should start with:** `pnpm dist` + install-over (carries weekday anchoring, windows, commute, WS fix, these 3 solver fixes = migrations 0011+0012); then a live regenerate to sanity-check weekday consistency and red-day counts.
- **New open questions:** none.
- **New risks / issues:** scaled cap is avg-based — a roster mixing 90-min hypermarkets with 10-min kiosks gets a blended cap; acceptable (Time dimension still binds), revisit only if a real dataset shows day-cramming.

### 2026-06-10 (evening) — PJP gaps shipped: weekday anchoring (default) + visit windows & commute legs (opt-in)

- **Phase change:** still Phase 12. Implements the top 3 findings from the same-day PJP audit, per user: Gap 1 required, Gaps 2+3 optional-to-use.
- **Shipped (4 commits on `main`):**
  - **`35f7888` Gap 1 — same-weekday anchoring (always on).** `assemble.run_optimize` records each customer's weekday(s) from the first week they're visited and narrows `allowed_days` to that anchor in later weeks → freq 2–4 customers see the rep on the same weekday every cycle (the "P" in PJP), regardless of week-mix changes. Guard: anchor only applied when this week's visit count fits the anchor (protects freq=5+ uneven weeks). freq>4 was already consistent via `_pick_spread_days`. 3 new tests.
  - **`5c576ee` Gap 2 — per-customer visit time windows (opt-in by data).** `visit_window_start/end` (HH:MM): migration 0011, importer columns (HH:MM + Excel time-serial parsing, both-or-neither, start<end), Customers edit panel, template docs. Solver: **Time dimension refactored to ABSOLUTE wall-clock cumuls** (start cumul pinned to shift start; output formats cumul directly), slack enabled so a rep can wait for a window to open, `AddVariableMinimizedByFinalizer` on end cumuls keeps windowless schedules earliest-possible (verified identical). Windows are SOFT bounds at `_OVERFLOW_PENALTY_PER_MIN` — out-of-window paints red, never drops. Blank window = exactly-old behavior. 3 new tests.
  - **`24ff9b0` fix — customer-side WS coercion.** `coerceChannel` in repo/customers.ts dropped `WS` → null on read (the 2026-06-09 WS rollout missed it); WS customers were readable by any salesman. One-liner.
  - **`079dcb7` Gap 3 — opt-in commute legs.** Per-salesman `include_commute` (migration 0012, checkbox in SalesmenScreen). When on + start location set: planner publishes the start point in the matrix under pseudo-id **`-salesmanId`** (MatrixNode refactor in planner.ts; spec types narrowed from Customer to {id,lat,lng}), VRP prices/schedules home→first + last→home via **per-salesman transit callbacks** + `AddDimensionWithVehicleTransitAndCapacity`; first visit's `drive_minutes_to` = commute, day must end home by shift end. Haversine fallback when matrix rows missing. Default off = byte-identical schedules (tested). resequence-day gets all three features too. 3 new tests.
- **Decisions:**
  - Anchoring is **default-on with no flag** — call-day regularity is core PJP, not a preference. First-solved week is the master.
  - Windows and commute are **soft / opt-in** respectively — consistent with the no-drop/red-chip philosophy and the user's "optional to use, not necessary".
  - Negative pseudo-ids for salesman points in the matrix (can't collide with SQLite rowids); commute rows only fetched for commute-enabled salesmen's own groups.
- **Verification:** sidecar pytest **58/58** on final code; `ruff` + `mypy` clean; `pnpm typecheck` clean (`pnpm test` is a no-op — no vitest suites exist yet, pre-existing). **No installer rebuild yet** — `pnpm dist` needed before any of this reaches the installed app.
- **Next session should start with:** (a) `pnpm dist` + install-over to ship v0.13 features; (b) live-data sanity: regenerate a plan and confirm weekday consistency + unchanged route quality (anchoring constrains later weeks, watch red-day deltas); (c) consider surfacing visit windows on the calendar tooltip.
- **New open questions:** none.
- **New risks / issues:** anchoring slightly constrains weeks 2–4 (could nudge overflow up on tight rosters — not observed in tests); Time-dimension slack adds search freedom (suite shows no regression, but watch solve quality on the 532-customer dataset).

### 2026-06-10 (later) — PJP best-practice logic audit (assessment only; zero code change)

- **Phase change:** still Phase 12. Read-only audit of planning logic vs FMCG PJP best practice (user request). Findings delivered in chat; nothing implemented.
- **Conforms (8 dimensions):** frequency adherence by construction; freq=2 {W0,W2}/{W1,W3} fortnightly spacing; freq>4 intra-week spread via single-day clones; beat compactness (drive-dominant objective + cluster heat + the 2026-06-10 durable principle); OSRM real road times with overestimating haversine fallback; workload balance (k-medoids + soft caps); territory/channel eligibility (area/region × MT/TT/WS); exception surfacing (red overflow, reasoned unassigned) instead of silent drops.
- **Gaps found (ranked):**
  1. **Call-day regularity not enforced** — the "P" in PJP. Weekday consistency across weeks is *emergent* (deterministic solver on identical weekly inputs), not constrained. Measured: all-freq=4 dev plans → 100% same weekday; dev plan #2 (slightly different input) → 26/1061 drifted. Mixed-freq datasets (real Towell data) are structurally exposed since week compositions differ. Also no month-over-month stability anchor on regeneration.
  2. **No per-customer time-of-day windows** (MT receiving hours / call-before-noon norms). Model has allowed_days only.
  3. **Home→first / last→home legs excluded** (dummy depot, locked 2026-05-17 decision) — day length understated by the commute; capacity math slightly optimistic.
  4. **No break modeling** (lunch/prayer); workaround = shorter working window.
  5. **12/18 visit caps assume ~30-min call profile** — contradicts TT norms (20–40 short calls/day) if a short-facetime TT beat ever loads. Hard-coded, not facetime/channel-derived.
  - Minor: freq=3 has no spacing rule; `period_end` accepted but ignored (plan is always exactly 28 days); `_expand_customers_for_week` spreads clones over default Sun–Thu without intersecting the salesman's actual working days (edge case for non-standard weeks).
- **Observation (data, not code):** prod DB (`%APPDATA%\@journey\app`) currently has **0 salesmen and 0 visits**; plans 3–5 are empty shells (visits likely cascade-deleted with a salesman wipe). 532 customers + 1 dataset intact. Dev DB fine (plans 2–6 populated). Flagged to user.
- **Next session should start with:** user picks which gaps (if any) become roadmap items — same-weekday anchoring for freq≥2 is the highest-value candidate.
- **New open questions:** weekday anchoring as hard constraint vs soft penalty; do MT customers need receiving-hour windows in v1?
- **New risks / issues:** none (no code touched).

### 2026-06-10 — Day-balance investigation → REVERTED. Do NOT spread clusters to fill blank days.

- **Phase change:** still Phase 12. Long investigative session; **net solver change = ZERO** (reverted).
- **Trigger:** User: light/blank days appear across the 4 weeks; "identify the trend." 9-agent workflow root-caused it (high confidence): the VRP objective minimises drive + a small per-day fixed cost with soft UPPER bounds only — **no day-balance / lower-bound / spread term**. So lone geographic OUTLIERS get parked on their own near-empty day, and because all 4 weeks (freq=4 = one weekly visit each) solve independently on identical inputs, the same outlier orphans on the same weekday every week. The 16 orphan days traced to exactly 4 outlier customers on the 2 smallest rosters; r(roster_size, day-spread) = −0.79.
- **What we tried and rejected (all reverted):**
  - **Dimensionless per-vehicle fixed cost** `β × working_window` (replacing the absolute 10). A POSITIVE fixed cost minimises *days used* → folds small rosters → trades 1-visit "orphan" days for 0-visit "blank" days. β=0.09 made blanks worse; β=0 removed blanks but **fragments tiny tight clusters into 1-visit days** (fails `test_solver_does_not_open_fragment_days_for_single_customer`). No single β satisfies both (tiny-cluster consolidation needs β≥~0.05; medium-roster spread needs β≈0).
  - **Per-day soft LOWER bound** at each salesman's balanced target (to force spread, no blanks). The user **rejected the entire premise**, and they are right (durable principle below). Also it under-performed (Rumais kept folding) and risks distance-dumb filling.
- **DURABLE PRINCIPLE (do not re-litigate):** **Never spread geographically-close customers across days to eliminate a blank day.** Customers that belong together (near each other) stay on the same day — tight low-drive routes are the entire value of the plan. A blank/light day is NOT a routing defect to fix in the solver; it is a **team-sizing signal** (too few customers for the headcount, e.g. Rumais #1 at 29 customers) or a **manual drag-drop** touch-up. The drive-clustering objective is CORRECT as-is. Future sessions: do not add fixed-cost calibration, lower bounds, span costs, or any "fill the light day" mechanism for this.
- **Decision:** `git checkout HEAD -- sidecar/sidecar/solver/vrp.py` — full revert to the committed solver (`SetFixedCostOfAllVehicles(10)`, soft upper bounds only). Shipped only the day's real wins (WS channel + 8-min label, already committed `09a33e2` + `09c506a`).
- **Data-side findings (user fixes in the Excel template, not code):**
  - **2 mis-tagged customers** sitting in Muscat but tagged Rumais → retag area+region to Muscat: **`CE94164` HORMUZ COLD STORES-AMERAT** (Amerat is Muscat; 0.6 km from a Muscat #7 customer) and **`CE90142` AL AREEN SHOPPING-MOBELLAH** (0.1 km from a Muscat #5 customer). These caused systematic orphan days; fix is data hygiene, not solver.
  - Genuine far outliers (e.g. `CE93784` Tahwa lat 22.40, `CE96793` Sur Dhabab) are correctly tagged — inherently lone-visit days; handle by manual drag, not solver.
  - **WS team-config lesson:** a WS-ONLY salesman in a low-WS region overloads its peers. `Suggested — Sohar #3` was set channel_skills=`WS` only, but Sohar has just 2 WS customers (37 WS total dataset-wide), so Sohar #3 carried 2 while Sohar #2 ballooned to 107 customers > 5×18 hard visit-slot ceiling = 17 dropped (the 12.3 "(unexpected with soft capacity)" message). Fix: give a WS rep `TT,WS` (like Kamil #2 / Muscat #8) unless WS volume justifies a dedicated route.
- **Tooling note:** dev DB lives at `%LOCALAPPDATA%\Packages\Claude_*\LocalCache\Roaming\@journey\app-dev\journey-plan.db` (sandbox redirect). Audit script: `JOURNEY_DB_PATH=<path> PYTHONUTF8=1 uv --project sidecar run python scripts/audit-latest-plan.py` (PYTHONUTF8 needed — it prints `→`). Headless solver experiments live in `tmp/` (gitignored): load customers/salesmen from dev DB, fetch matrix from the live sidecar `/matrix/batch`, call `assemble.run_optimize` — bypasses Electron/IPC, no ECONNRESET, fast.
- **Verification:** `pnpm typecheck`, `ruff`, `mypy` clean throughout. `pytest` 49/49 on HEAD (the reverted state). Installer rebuilt with WS + label (sidecar binary functionally unchanged — WS works via value-agnostic eligibility + TS/renderer layer).
- **Session close:** dead-code sweep (commit `949cd34`) removed 3 unused TS items — `autoSuggestMapping()` + `dryRun()` exports and a `ReferenceLine` import; sidecar verified clean (ruff F-rules already gate unused imports). User applied the 2 retags (CE94164/CE90142 → Muscat) and broadened Sohar #3 to `TT,WS` in the Excel template. Final installer `installer/JourneyPlanApp-Setup-0.1.0.exe` 749 MB, signed, 2026-06-10 01:14 — carries WS + label + reverted solver.
- **Minor backlog note:** column auto-mapping regexes are duplicated (renderer `ImportScreen.tsx` keeps the live copy after the main-process one was deleted). If it ever drifts, lift the patterns into `shared/`.
- **Next session opener:** clean. Day-balance is closed per the durable principle above. Backlog still applies (OSRM graph slimming, code-signing, Muscat #3 load via Assignments screen).
- **New open questions / risks:** none.

### 2026-06-09 — Add WS (Wholesale) to the channel taxonomy

- **Phase change:** still Phase 12 (small additive feature, no roadmap goal change).
- **Trigger:** User asked to add "Ws for Wholesale" to the salesman's skillset.
- **Decision:** Extended the single shared `SalesChannel` taxonomy (`'MT' | 'TT'` → add `'WS'`) rather than splitting customer-channel vs salesman-skill enums. A salesman WS skill is only meaningful if customers can also be tagged WS, so WS is surfaced on both the customer-channel dropdown and the salesman channel-skills picker — same as MT/TT.
- **Sanity-check earlier this session:** prod DB clean — `integrity_check` ok, `foreign_key_check` 0, no lingering WAL; the repeated installed-app launches each took an integrity-verified startup backup (7 backups now in `db-backups\`). `sidecar exited with code 1` on close is the normal teardown signature, not a fault.
- **Shipped (not yet a new installer build):**
  - `shared/src/index.ts` — `SalesChannel` widened to include `'WS'` + comment.
  - `app/src/main/import/validate.ts` — importer accepts `WS`; error text now "MT, TT, WS, or empty".
  - `app/src/main/repo/salesmen.ts` — `parseChannelSkills` no longer silently drops `WS` when reading salesmen back from the DB.
  - `app/src/main/planner.ts` — `toOptimizeCustomer` channel type widened from inline `'MT'|'TT'|null` to `SalesChannel | null`.
  - `app/src/renderer/.../SalesmenScreen.tsx` — `CHANNELS` adds `WS`; binary label ternary replaced with a `CHANNEL_LABELS` map (scales past 2 values).
  - `app/src/renderer/.../CustomersScreen.tsx` — adds `WS (Wholesale)` option.
  - `scripts/make-template.cjs` — import-template channel docs updated to MT/TT/WS.
  - `sidecar/sidecar/models.py` — comments updated (no functional change; `channel`/`channel_skills` are plain `str`, accept WS already).
- **No migration needed:** `0008` declares `channel TEXT` / `channel_skills_csv TEXT` with no CHECK constraint, so WS is valid at the DB layer untouched. Solver eligibility (`vrp.py` / `assign_salesmen.py`) is value-agnostic string-membership — no solver change.
- **Verification:** `pnpm typecheck` clean (shared + app); `ruff check models.py` clean. Sidecar pytest not re-run (comment-only Python change). **Not yet rebuilt into an installer** — needs `pnpm dist` before it reaches the installed app.
- **Next session opener:** if user wants WS live on the installed app, run `pnpm dist` and install over the existing prod copy (userData preserved). Otherwise prior backlog applies (search-strategy tuning for s3 06-14/06-21 residual, OSRM graph slimming, code-signing).
- **New open questions:** none.
- **New risks / issues:** none — strict superset of the prior taxonomy; existing MT/TT data and salesmen behave identically.

---

## Next up

Phase 12 + the v0.12.1 audit pass + dev userData split + v0.12.2 brand icon all shipped to prod. `installer/JourneyPlanApp-Setup-0.1.0.exe` rebuilt 2026-05-23 19:34 (792.6 MB, tagged `v0.12.2`) is the current ship. The probe report at `~/.claude/plans/lauch-a-probe-on-refactored-origami.md` no longer reflects current state — all 19 findings closed.

### Real backlog (actionable)

- **Smoke test** of the 2026-09-23 07:59 build (installed on prod 2026-09-28) (750 MB; supersedes 09-21 03:01, adds atomic drag-to-reassign). Drive-time totals should move ~+1% vs the 07-05 build; anything larger means the calibration ramp needs a look.
- **Utilization slider decision** — make `targetUtilizationPct` real (needs cluster splitting) or remove the control. The other two open questions from today (calibration bands, freq=1 bundling) were CLOSED later the same day.
- **`docs/reviews/2026-09-03-full-codebase-review.md`** — Tier-1 all closed 2026-09-21; the Tier-3 cleanup list (dead exports, duplicated helpers, stale Google/keytar metadata) is still open.

1. **Optional graph slimming pass** — omit `.edges`, `.enw`, `.cnbg`, `.cnbg_to_ebg` from `extraResources` (not loaded by `osrm-routed --algorithm mld` at runtime; would save ~200 MB on the installer, currently 792.6 MB).
2. **Code-signing cert procurement** — parked until app is finalized. Required before broader-than-internal distribution to remove the Windows SmartScreen "Run anyway" prompt and the Defender first-run quarantine on the unsigned OSRM binary. Also makes the diagnostics first-launch lock disappear (Defender skips its heuristic on signed binaries).

### Optional polish

3. ~~**Distance-in-km on Analytics.**~~ ✅ Done 2026-06-14. Reconstructed at render time from route legs + `distance_cache` (new IPC `plan:distance` → `app/src/main/planDistance.ts`); KPI tile + per-salesman column. No column/migration/regenerate — works on existing plans. Mirrors drive-time leg semantics. Verified dev #10: 9,420 km, 0 missing legs, 47 km/h avg.
4. ~~**`?planId=X` deep-link** on Analytics~~ ✅ Done 2026-06-12 (shipped with the analytics PDF export — "View analytics" button on PlanScreen).
5. ~~**Lazy-load Analytics with `React.lazy`**~~ ✅ Done 2026-06-14. recharts (~895 kB) now a separate chunk; main renderer bundle 1.9 MB → 1.05 MB.

### Post-deployment (blocked on real execution data)

6. ~~**Real-world OSRM-multiplier calibration.**~~ ✅ Closed 2026-06-14 — user verified in production ("working beautifully so far"). Spot-check of live OSRM vs known Oman road figures: Muscat→Sohar 210.7 km/2h24 (~230/2h20), Muscat→Nizwa 160.6 km/1h48 (~160/1h45), Muscat→Buraimi 319.4 km/3h36 (~320/3h15) — distances near-exact, raw durations realistic and lean conservative (before the 1.4× urban mult). `OSRM_URBAN_MULT` env override stays available if real execution data ever warrants a tweak, but no calibration change needed.

### Parked indefinitely

7. **PDF export of the plan schedule (calendar)** — parked from Phase 4. Revisit if a customer asks. (The ANALYTICS dashboard PDF shipped 2026-06-12 — the hidden-window `printToPDF` pipeline in `app/src/main/export/pdf.ts` is reusable if this ever revives.)
8. **Arabic UI** — parked. Revisit when there's a use case.
9. **Public holiday calendar** — parked. v1 has manual non-working-date markers.
10. **Multi-month / rolling plans** — v1 is one 4-week period.

**Out of scope:** cloud sync, multi-user auth, web backend, Mapbox/HERE introduction, custom heuristic optimizer, re-introducing any Google API. See `CLAUDE.md` §10.

### Smoke-test checklist for v0.12.1 (pending eyes-on)

Not fixes — items to verify after installing `JourneyPlanApp-Setup-0.1.0.exe` over the existing prod copy. From the 2026-05-23 audit close-out entry:

- **OSRM badge state machine.** Launch should show yellow `Initialising…` briefly, then green `OSRM live`. Kill `osrm-routed.exe` via Task Manager → badge turns red with the post-handshake reason in the tooltip.
- ~~**Salesman-delete guard.** Attempt to delete a salesman with visits in a final plan; expect a clear error toast naming the visit count.~~ Superseded 2026-06-10 by Phase 13: the guard was removed — salesman deletes no longer touch plans at all.
- **Migration 0010.** First launch over an existing prod DB should apply cleanly. `error.log` should show "applying migration 0010_drop_geocode.sql" and the app should boot normally.
- **Diagnostics fallback.** Verify the post-`backupDb()` "DB integrity ok" line lands in `error.log` (was dropping silently to stderr before).
- **Plan generation.** Run a full plan; in SolverLog inspect `matrixCellsRequested` / `matrixCellsCached` — expect requested to drop substantially on cold cache (no more cross-cluster off-diagonal waste) and cached to climb toward 100% on a warm-cache replan.

---

## Small fixes (low-priority UX / polish)

Backlog of small surface-area bugs and rough edges that aren't worth a dedicated session but should be batched into a polish pass.

- ~~**Clamp `-1` distance sentinel in distance_cache**~~ ✅ Done 2026-06-14 (commit `4cae5c3`). Clamped to ≥0 at BOTH cache-write (keeps it out of new rows) and cache-read in [cache.ts](app/src/main/cache.ts) (neutralizes the 2 already-poisoned rows without a data migration — matters now that the analytics distance total sums `distance_meters` directly).
- _(none open)_

---

## Phase 3 inputs (locked, all resolved)

- **Even-week-spread rule** (`solver/cycle_assignment.py`):
  - `frequency = 1`: any single week — balance picks the lightest.
  - `frequency = 2`: hard `{W0, W2}` or `{W1, W3}` — balance picks the lighter pair, with per-cluster freq=2 bundling cap (Phase 9) to prevent pile-on.
  - `frequency = 3`: any 3 of 4 weeks, no spacing constraint, balance picks the 3 lightest.
  - `frequency = 4`: all 4 weeks.
  - `frequency > 4`: distribute proportionally across all 4 weeks (no intra-week spacing in v1).
- **Balanced weekly workload.** Implemented greedily (heaviest customer first picks the freest pattern) rather than as a full MIP.
- **Route shape.** Salesman starts at first customer, ends at last customer. No depot in route. `start_location_lat/lng` stays on the salesman record for map reference only.
- **Pinned customer.** Hard pin. Overrides area, region, and channel.
- **Areas + regions** (Phase 8/9). Optional `customer.area` + `customer.region` text columns; `salesman.assigned_areas[]` + `assigned_regions[]` multi-select. Eligibility = (area-in-assigned-areas OR region-in-assigned-regions) AND channel-in-channel-skills. Each empty side = catch-all.

---

## Open questions / parked items

- **Public-holiday handling** — auto vs manual. Parked. v1 = manual non-working-date marker.
- **Arabic UI** — parked.
- **Cost / KM-billed views** — parked. Not in v1.
- **Multi-month / rolling plans** — v1 is one 4-week period at a time.
- **Distance-in-km on Analytics** — surface from cache lookup at render time, or persist on Visit? Defer until asked.

---

## Known issues / risks

- **OSRM is a single point of failure for the planner.** If `osrm-routed.exe` won't boot (e.g. graph corruption, port 5050 taken), `/matrix/batch` returns 503 and the user cannot generate plans. The 503 message names `pnpm osrm:setup` as the recovery hint; no automatic "limp mode" — that was the billable path we deliberately removed.
- **PyInstaller + OR-Tools binary size** — sidecar.exe is 63 MB; installer is ~779 MB (dominated by the 2.2 GB OSRM graph compressed inside NSIS). Acceptable; flag if it grows past ~1 GB.
- **Python 3.14 on host is unsupported by OR-Tools wheels.** Sidecar pinned to `>=3.11,<3.13` via `pyproject.toml`; `uv` auto-installs Python 3.12 in `.venv/`. Don't relax this pin until OR-Tools ships 3.13+ wheels.
- **OneDrive sync of `node_modules`** — original repo location was inside OneDrive; moved to `C:\dev\journey-plan-app\` to avoid file-locking errors during installs/builds. If a future contributor runs from a OneDrive path, expect issues.
- **`postinstall` rebuild requires `electron-builder install-app-deps`** — already wired in `app/package.json`. If a future contributor switches package managers or removes the postinstall, `better-sqlite3` will load wrong native bindings.
- **`uv sync` requires `UV_LINK_MODE=copy`** on this machine — the uv cache lives under `C:\Users\tarek\AppData\Local\uv\cache` on OneDrive's reparse-point filesystem and rejects hardlinks. Set permanently or pass per-command.
- **`pnpm dist` requires Windows Developer Mode ON.** electron-builder downloads `winCodeSign-2.6.0.7z` which contains macOS dylib symlinks. 7zip-bin on Windows cannot create symbolic links without `SeCreateSymbolicLinkPrivilege`, granted only via Developer Mode. Enable at Settings → Privacy & security → For developers.
- **Installer is unsigned in v1.** Windows SmartScreen shows "Windows protected your PC" on first launch; users click "More info" → "Run anyway". Procure code-signing cert before broader-than-internal distribution.
- **No application icon shipped.** `app/electron-builder.yml` doesn't set `win.icon`. Installed app uses default Electron icon. Add `installer/icon.ico` (256×256 multi-resolution) before broader distribution.
- **PyInstaller `--noconsole` builds detach from parent console on Windows.** Running `./sidecar.exe` from bash shows no output. Test via Node `child_process.spawn` with `stdio: 'pipe'` (which is what Electron uses in prod).
- **Orphan child-process leakage on abnormal Electron exit** — fixed 2026-05-22 via `killTree` (`taskkill /T /F /PID` on Windows) wired into `stopSidecar` + `stopOsrm`. Dev tree carries it; installer rebuild still needed to ship to prod (see Next up #1). The plain `proc.kill()` = `TerminateProcess` doesn't propagate to PyInstaller's bootloader child or the dev `cmd → uv → python` chain; `taskkill /T` walks the descendant tree and reaps every process. Hard-quit cases (SIGKILL on the Electron process itself, power loss, OS reboot) still leak, but those are unsolvable from inside the parent.
- **`PRAGMA integrity_check` against a live-writer DB can false-positive.** When run via a separate read-only connection (e.g., external Python forensic script using `?mode=ro`) while the main Electron process is actively writing, the integrity walker traverses b-tree pages across many separate reads and can see transient inconsistencies that aren't actually persisted corruption — typically reported as "invalid page number" errors against rapidly-mutating trees (visits, distance_cache, indexes on those). **Always run integrity_check only against a quiesced DB** (after Electron has closed and `closeDb()` has checkpointed the WAL — verify by checking that `.db-wal` and `.db-shm` files are gone). Surfaced in the 2026-05-23 forensic session: trees 10-13 looked corrupt with invalid pages 2380-2419; closing the app and re-running integrity_check returned `ok` with all 1,064 customers / 1 plan / 1,473 visits intact. Bonus protocol note: when checking if a writer is alive, regex must include `'Journey Plan App'` (installer's process name), not just `'electron'` (dev's) — getting this wrong made me think the app was dead when it was actively writing.

---

## Session-end update template

> Before ending each session, append a block in this shape. Keep it terse.

```markdown
### YYYY-MM-DD — <one-line summary>
- **Phase change:** <e.g. "still Phase 12" or "moved to Phase 13">
- **Shipped:** <bullets — what's now done that wasn't before>
- **Decisions:** <bullets — what was chosen, why, with anything that overrides earlier entries>
- **Next session should start with:** <one or two concrete tasks>
- **New open questions:** <if any>
- **New risks / issues:** <if any>
```

Then:
1. Update the **Current phase** line at the top if it changed.
2. Mark roadmap rows ✅ as phases complete.
3. **Run `pnpm memory:check`.** It fails once this file reaches **100 KB**.
   When it does, move the OLDEST decision-log entries verbatim into
   `docs/memory-archive/decision-log-<first>-to-<last>.md` until the file is
   back to ~70 KB, and leave a pointer line here saying what moved. Archive,
   never delete. Keep Current phase, Roadmap, Next up, Open questions, Known
   issues, this template, and the recent entries. Full rules: `CLAUDE.md` §0.
