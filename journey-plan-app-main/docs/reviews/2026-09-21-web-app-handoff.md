# Handoff: fixes applied to the desktop Journey Plan App — check which apply to the web version

**Audience:** the coding agent maintaining the *web* version of the Journey Plan App.
**Source:** the Windows desktop app (Electron + React + TypeScript renderer, Python FastAPI sidecar, OR-Tools VRP, self-hosted OSRM, SQLite). Full review report: `docs/reviews/2026-09-03-full-codebase-review.md`.
**Date applied:** 2026-09-21. Commits `dcd45d1`, `9f0a0f8`, `ffd351a`.

## How to use this document

Each item below is written as **symptom → root cause → fix → how to check yours**. The stacks differ, so do not port code. Port the *defect class*. Work top-down: Section 1 items are logic bugs that exist in any implementation of this product, Section 2 are server/data bugs, Section 3 are React bugs, Section 4 is almost certainly not applicable but is listed so you can confirm rather than assume.

For each item, record one of: **FIXED**, **NOT PRESENT** (say how you verified), or **N/A** (say why). Do not mark anything FIXED without a test or a reproduction.

Two conventions this product relies on, in case the web version drifted:
- Week starts **Sunday**; weekend is **Fri–Sat** (Oman). Day-of-week is `0=Sun … 6=Sat`.
- A plan is **exactly 4 calendar weeks** (28 days) from the period start.

---

## Section 1 — Domain logic bugs (port these first; they apply to any implementation)

### 1.1 Team sizing returns an empty roster once customers are assigned
- **Symptom:** "How many salesmen do I need?" returns zero recommendations and reports every customer unassigned — but only *after* the user has run the upfront assignment step.
- **Root cause:** the sizing run builds a roster of *synthetic* salesmen (ids from a reserved high range). The request builder sends `pinnedSalesmanId = userPin ?? assignedSalesman`, so after an assignment run **every** customer carries a pin to a **real** salesman id. Pins are a hard eligibility constraint, so no customer could be served by any synthetic salesman.
- **Fix:** strip pins from the customer copies before the sizing solve. Sizing answers "how many reps does this workload need", which is upstream of who owns whom.
- **Check yours:** find where the team-sizing request is built. If it copies the effective pin through, you have this bug. Test: run sizing with every customer pinned and assert a non-empty roster.

### 1.2 Multi-visit customers silently lose visits
- **Symptom:** a customer with monthly frequency 8 receives 4 visits. Solver status still says success and the unassigned list is empty. Only a frequency-conformance report reveals it.
- **Root cause:** a multi-visit customer is expanded into one clone **node** per visit-in-week. The unassigned report was computed by comparing *customer ids* between the input set and the placed set. Placing one clone marked the whole customer "assigned", so every other dropped clone vanished from the report.
- **Fix:** report drops **per node**. Count placements per customer id, decrement as you match, and emit one unassigned entry per unmatched clone. Add a distinct reason for the case where some clones placed and others did not.
- **Check yours:** if your solver expands frequency into repeated nodes and your "unassigned" logic uses a set of ids, you have this bug. Test: two clones of one customer, one with an allowed-day nobody works; assert exactly one placement and one reported drop.

### 1.3 Drive-time calibration was non-monotonic
- **Symptom:** the optimizer sometimes prefers a genuinely longer leg, and a day's time budget is charged *less* for a longer drive.
- **Root cause:** raw routing durations were scaled by a band-specific multiplier applied as a **step**: `<10 min × 1.40`, `<30 min × 1.15`, else `× 1.00`. At the edges this inverts. A raw 599 s leg became 839 s while a raw 600 s leg became 690 s. Every leg within ~20% of a band edge was mis-ordered against its neighbours, and the triangle-inequality assumption the solver leans on broke there.
- **Fix:** make the multiplier a **continuous piecewise-linear ramp** between band *midpoints* (300 s → 1.40, 1200 s → 1.15, 2400 s → 1.00, flat outside). This preserves the empirically calibrated value at each anchor exactly and changes only the transitions. Verified strictly non-decreasing across 0–7200 s; aggregate effect on a uniform sweep was **+0.73%**, so no re-baselining was needed.
- **Check yours:** if you apply any banded correction factor to routing durations, assert monotonicity over the full range in a test. This is the highest-value item in this document if your web version does its own calibration.

### 1.4 Low-frequency customers collapse into the first week
- **Symptom:** a territory made up of monthly (frequency 1) customers gets everything scheduled in week 1, and the rep has three empty weeks there.
- **Root cause:** week selection prefers a week the same (salesman, area) cluster is already committed to, so the rep piggy-backs on a trip they are already making. That preference is **absorbing**: the first customer makes its week "hot", every later customer in the cluster then sees that as the only hot option and piles on. A sibling rule for fortnightly customers already had a cap; the monthly rule did not.
- **Fix:** cap the bundling — a week may run at most **one customer ahead** of the cluster's lightest week. Small clusters still bundle fully (which is the point); large ones rotate. A 40-customer monthly territory went from 40/0/0/0 to 10/10/10/10.
- **Check yours:** look for any "prefer the week this cluster already uses" heuristic without a counter-balance. Test: 40 monthly customers in one cluster; assert every week is used and the spread is within a small tolerance.

### 1.5 Week balancing optimised the wrong quantity
- **Symptom:** aggregate week load looks perfectly flat, yet individual salesmen are idle two weeks out of four.
- **Root cause:** week load was a single **roster-wide** accumulator. With two pinned salesmen and interleaved input, one could end up on weeks {1,1,3,3} and the other on {2,2,4,4}, each idle half the month, while the global totals looked ideal.
- **Fix:** track week load **per effective salesman** (unpinned customers share one pool). Balancing each rep's own weeks implies the global balance too, since a sum of flat sequences is flat.
- **Check yours:** grep for a single shared per-week accumulator in week/cycle assignment. Test: two pinned reps, interleaved workloads; assert each covers all four weeks.
- **Note:** a pre-existing test asserted the *buggy* behaviour — it required rep B to avoid the weeks rep A had loaded. Two reps work in parallel, so making B dodge A's weeks serialises independent people and *causes* the idle-half-month pathology. If you have a similar test, read it critically before trusting it.

### 1.6 A user-editable field the solver never reads
- **Symptom:** the user shortens the plan period; visits are still generated for the full 4 weeks, two weeks past the displayed end date.
- **Root cause:** period end was editable, sent over the wire, and stored, but the week-assignment code hard-codes 4 weeks and the assembly code reads only the period **start**.
- **Fix:** derive period end from the start and make the control read-only, with a "(4 weeks)" hint. Variable-length plans stay a parked feature rather than a field that silently lies.
- **Check yours:** for every editable field on the plan form, trace it to a consumer. Anything with no consumer should be derived, disabled, or removed.

### 1.7 Routing-matrix batching degenerates on large groups
- **Symptom:** plans fail for large single-territory datasets with a misleading "routing service unreachable" error, while the routing service is healthy.
- **Root cause:** the matrix request chunker split only the **destination** side and kept the whole origins list in every call. That is fine while origins are a small fraction of the per-call cap, and pathological otherwise: a 400-point self-matrix produced `max(1, 350 − 400) = 1` destination per call, i.e. 400 calls each still carrying 401 coordinates — far past the URL limit, so every one was rejected.
- **Fix:** tile **both** sides. Halve the per-call budget between origins and destinations, and re-base both index sets onto the caller's full lists.
- **Check yours:** if you batch matrix requests, test with origins ≈ destinations ≈ 400 and assert no single call exceeds the cap and the returned rectangle is complete.

### 1.8 Routing-service rejections reported as outages
- **Symptom:** the user is told to reinstall or restart the routing service, which is running fine.
- **Root cause:** every non-2xx response was collapsed into one "unreachable" error carrying a "reinstall it" hint. The routing service actually returns a machine-readable error code in the body (e.g. a too-many-coordinates code) that names the real cause. This misdirected a real incident on this product once already.
- **Fix:** two distinct error types. *Unreachable* (transport failure, 5xx) keeps the restart hint; *request rejected* (4xx with a code) maps to HTTP 502 and quotes the service's own code and message, with no restart advice.
- **Check yours:** any place you translate an upstream error into user-facing text. The test to write: upstream returns 400 with a body code; assert the message names the real cause and does **not** tell the user to reinstall anything.

---

## Section 2 — Server, data and integrity (likely applicable)

### 2.1 Non-atomic multi-write on plan save
- **Symptom:** a plan appears in the list with zero visits, indistinguishable from a real one.
- **Root cause:** saving a plan was four separate writes (create plan row, snapshot the roster, bulk-insert visits, write the solver log). A failure part-way — a unique-constraint violation on a duplicate sequence, a full disk — committed the first writes and left an empty shell.
- **Fix:** wrap the whole save in one transaction.
- **Check yours:** every multi-statement write that must be all-or-nothing. In a web app with concurrent users this matters more, not less.

### 2.2 Edit endpoints bypassed all import validation
- **Symptom:** a frequency of 0 or a latitude of 91, typed into an edit form, reaches the database and only fails much later as an opaque 422 from the solver.
- **Root cause:** the importer had a thorough validation layer (coordinate bounds, facetime 1–240, frequency 1–20, `HH:MM` format, window start before end, valid weekday set). The create/update endpoints for the same entities had **none** — they trusted the client payload.
- **Fix:** entity-level schemas defined **next to** the importer's field rules so there is one source of truth, applied at the endpoint boundary, returning one human-readable message.
- **Check yours:** this is the highest-probability finding for a web app, where the client is fully untrusted. Every write endpoint needs server-side validation even if the form already validates.

### 2.3 Deletes cascade into locked records
- **Symptom:** a plan marked final silently loses a stop; the following stop keeps a stale drive time and the sequence numbers show a gap.
- **Root cause:** deleting a customer cascaded to visits in **every** plan including finalised ones. Deleting a dataset bulk-deleted its plans, also including finalised ones. Meanwhile the per-plan delete endpoint correctly refused to touch a final plan — so the rule existed but only on one of three paths.
- **Fix:** enforce the same guard on every path that can reach the data, with an error naming the blocking plans.
- **Check yours:** enumerate every route to deleting a customer or dataset and confirm each one enforces the lock. Guards belong on the data layer, not on one screen.

### 2.4 A failed import destroys the working dataset
- **Symptom:** the user mis-maps a column, every row fails, and the customer/map/plan screens all go blank.
- **Root cause:** the import committed a new dataset row and **activated** it before checking whether anything had validated. Zero valid rows still deactivated the dataset the user was working with.
- **Fix:** refuse the import when nothing validated, with an error quoting the first row error and pointing at the coordinate columns. Leave the active dataset alone.
- **Check yours:** any "replace the active set" operation must be preceded by a "did we actually get anything" check.

### 2.5 Timestamps rendered in the wrong timezone
- **Symptom:** "Imported at" and "Last computed" show 4 hours earlier than reality (Oman is UTC+4).
- **Root cause:** the database writes `CURRENT_TIMESTAMP` as `YYYY-MM-DD HH:MM:SS` in **UTC with no zone designator**. `new Date()` parses that shape as **local** time.
- **Fix:** a single formatter that appends the zone designator when the string lacks one, then formats. Everything goes through it.
- **Check yours:** grep for `new Date(` applied to any value that came from the database. Also check your API serialisation — if the server hands out naive timestamps, fix it there instead.

### 2.6 Local dates formatted via UTC
- **Symptom:** opening the app between midnight and 04:00 Oman time gives a default plan start of **Saturday** instead of the intended Sunday, shifting every week bucket by a day.
- **Root cause:** building a date in local time, then calling `toISOString()` and slicing the date part, converts to UTC first.
- **Fix:** format year/month/day from the local getters. Never round-trip a calendar date through UTC.
- **Check yours:** grep for `toISOString().slice(0, 10)` and for `new Date('YYYY-MM-DD')` — the latter parses as UTC midnight and drifts the other way in negative-offset zones. Both patterns are timezone bugs waiting for a user in a different zone, which is far more likely in a web app than a desktop one.

### 2.7 Bad input returns 500 instead of 4xx
- **Symptom:** a malformed date or time string produces a bare "Internal Server Error" naming neither the field nor the value.
- **Root cause:** parse errors from the request body escaped as unhandled exceptions rather than being classified as caller error.
- **Fix:** an exception handler mapping parse errors to 422 with the offending value in the message. Separately, a caller-side validation failure (fewer than two waypoints for a route request) was returning a service-outage status; now 422.
- **Check yours:** anything that returns 5xx for input you could have validated.

### 2.8 Export used a private copy of a shared calculation
- **Symptom:** kilometres in the Excel export disagree with kilometres in the analytics dashboard.
- **Root cause:** three separate implementations of "sum the distance of each leg". The export's copy lacked a clamp that had been added to the shared one, and omitted the home-to-first-stop commute leg.
- **Fix:** clamp at the export too (and at the routing boundary, item 2.9). The real fix — collapsing three copies into one — is still open.
- **Check yours:** any figure that appears in more than one surface. If two screens can disagree, they are computing it twice.

### 2.9 Sentinel value leaked past the boundary that understood it
- **Symptom:** a negative distance in exports and totals.
- **Root cause:** the routing service returns `-1` metres for a same-node pair (two customers a few metres apart). This was clamped in one consumer, so other consumers inherited the raw value.
- **Fix:** clamp at the boundary that knows the upstream semantics, so no consumer has to rediscover them.
- **Check yours:** general principle — normalise upstream quirks once, at the adapter.

---

## Section 3 — Frontend (React; directly applicable if the web app is React)

### 3.1 Edit panel saves over the wrong record ← *highest-severity frontend item*
- **Symptom:** the user clicks Edit on customer A, then Edit on customer B without closing the panel. The table highlights B but the panel still shows A's values. Saving writes **A's** record.
- **Root cause:** the panel seeds its draft with `useState(props.record)`, which only reads props on **mount**. Rendering it as `{selected && <EditPanel record={selected} />}` reuses the same component instance when `selected` changes, so the draft — including the id — stays on the first record opened.
- **Fix:** `key={selected.id}` on the panel, which remounts it when the target changes.
- **Check yours:** this occurred **twice** in this codebase (customers and salesmen). On the salesmen form the same bug meant "Add new" while editing **overwrote** the existing record instead of creating one, because the stale draft kept a non-zero id. Grep for `useState(props.` and for any conditionally rendered detail/edit panel without a `key`. This is a generic React defect and very likely present in a web version built from the same designs.

### 3.2 Stale closure leaves nothing selected after a delete
- **Symptom:** the user deletes the selected plan; the detail pane shows the empty state even though other plans exist.
- **Root cause:** the reload function read `selectedPlanId` captured when the closure was created. After the delete it was still non-null, so the "auto-select the first item" branch was skipped.
- **Fix:** use the functional state updater and validate the current selection against the list just fetched.
- **Check yours:** any `async` handler reading state it also sets.

### 3.3 Mutation handlers swallow failures
- **Symptom:** the user presses Save, nothing visible happens, and they press it again.
- **Root cause:** handlers `await` the mutation with no `catch`. The rejection goes to a log; the UI shows neither an error nor a state change. In one case — bulk-creating a suggested roster in a loop — a mid-loop failure left a partial roster, showed nothing, and kept the dialog open with the same data, so retrying **duplicated** everyone already created.
- **Fix:** catch, surface the message, and when a partial write may have happened say so explicitly and warn against a naive retry.
- **Check yours:** every mutation handler. Partial-write loops need idempotency or an explicit warning.

### 3.4 Chart caption described something not drawn
- **Symptom:** a caption promised "orange line = capacity", but the chart drew only two stacked bars and orange was the drive-time bar. A reader concluded every rep was at capacity.
- **Root cause:** the caption outlived a chart change; the computed capacity value was never plotted.
- **Fix:** rewrite the caption to describe what is actually rendered and point at the table for capacity.
- **Check yours:** read every chart caption against its series list. Wrong labels on an executive dashboard are worse than no labels.

### 3.5 Timezone-unsafe weekday derivation in one component
- **Symptom:** a detail panel shows "(Sat)" for a date the calendar column above labels "Sun".
- **Root cause:** `new Date('2026-07-05').getDay()` parses as UTC midnight then reads a local weekday. The codebase had a safe helper; one component did not use it.
- **Fix:** route every weekday derivation through the shared helper.
- **Check yours:** see item 2.6 — same root cause, different surface.

### 3.6 Inconsistent list caps with no truncation signal
- **Symptom:** different screens disagree about how many customers exist.
- **Root cause:** each screen passed its own page size (2000 / 5000 / 10000 / 100000) and none indicated truncation.
- **Status:** **not fixed** in the desktop app; listed because a web app with server-side pagination will hit it harder.
- **Check yours:** any list that can silently truncate should either paginate visibly or report the total.

### 3.7 Long-running work tied to a screen's lifecycle
- **Symptom:** the user starts a multi-minute optimisation, navigates away and back, and the finished result never appears until they leave and re-enter.
- **Root cause:** progress state was hoisted to a context so it survives navigation, but the **completion** handlers still belonged to the original screen instance and no-op'd after unmount.
- **Status:** **not fixed** in the desktop app.
- **Check yours:** in a web app this is worse, since a refresh or a second tab is trivial. Long jobs want a server-side job record polled by id, not component state.

---

## Section 4 — Desktop-specific (confirm N/A rather than assuming)

These arose from process spawning and local file handling. A web app has different shapes of the same class, listed after each.

### 4.1 Startup failure left a running process with no window
- Unguarded `await` in the startup sequence meant a failed child-process spawn aborted the rest of boot: no window, no request handlers registered, and the second child process left running with no way to reach it except Task Manager. Neither spawn had an `error` listener, so a spawn failure could also crash the host process outright.
- **Fix:** classify by severity — a database-migration failure shows a dialog and exits cleanly; a solver-service failure is logged and the window opens anyway so the user can read diagnostics. A catch-all on the whole chain kills both children and reports.
- **Web analogue:** server startup that throws after binding the port, or a failed dependency check that leaves the process up but not serving. Make readiness explicit and fail loudly.

### 4.2 Log rotation could throw out of the error handler
- The fallback path in log rotation ran outside its own try/catch, so the exact scenario it existed for — a locked file — threw from inside the error handler, replacing the real error and, in one path, terminating the process.
- **Web analogue:** any logging or telemetry call reachable from a `catch` block or an uncaught-exception handler must be incapable of throwing.

### 4.3 Normal shutdown logged fake crashes
- Teardown nulled its state before killing the child, so the exit handler could not distinguish a deliberate stop from a crash and wrote a spurious error on **every** quit. Separately, the solver service logged its startup banner at INFO to stderr, and the parent recorded all stderr as errors — about six bogus entries per launch, burying real ones.
- **Fix:** an explicit `stopping` flag checked in the exit handler, plus a severity filter on captured stderr and a quieter log level at the source.
- **Web analogue:** graceful-shutdown paths that log as errors, and any upstream log capture that misclassifies severity. Noise costs you the next real incident.

### 4.4 Endpoints accepted arbitrary file paths from the client
- The file-preview and file-commit handlers read whatever path the client supplied; nothing bound them to the path the user had actually chosen in the file dialog.
- **Status:** **not fixed** (low risk for a local single-user desktop app).
- **Web analogue:** this one is **serious** on the web. Any endpoint taking a path, key or identifier from the client must authorise it against what that user is allowed to read. Treat this as a must-check even though it is parked here.

---

## Section 5 — Feature added (port if the web version has the same dashboard)

**Region column on the analytics tables.** Added to both the per-salesman load/utilisation table and the week-balance table, so territory performance can be read without cross-referencing the roster.

Semantics worth copying:
- Derived from the regions of the customers the rep **actually visits in that plan**, ranked by visit count, so it reflects the plan rather than roster configuration.
- Falls back to the rep's configured regions, then to an em dash. Region is an optional field, so a dataset that never populated it correctly shows nothing rather than inventing a label.
- A rep straddling territories shows the dominant region plus `+N`, with the full list on hover, keeping the column one line wide so it survives PDF export.
- Both tables read from the same derived value, so they cannot disagree.

---

## Section 6 — Deliberately not fixed (decide for yourself)

- **The target-utilisation control is inert.** The team-sizing loop that grew the roster until utilisation hit a target stopped working when capacity became a soft constraint: the optimizer consolidates into as few vehicles as it can, so handing it more changes nothing. Verified empirically — targets of 10, 50 and 95 all returned the same team size. The dead loop was removed; the control still exists and does nothing. Making it real requires splitting the territories the optimizer forms, which is a feature. **If the web version exposes this control, it is probably lying to the user too.**
- **Three duplicate implementations** of the leg-distance calculation, and several duplicated helpers across the solver and frontend (week bucketing, weekday labels, working-day pickers).
- **Dead code:** exported functions with no callers, a response field never populated, a documented enum value never emitted.

---

## Verification standard used here

Every fix above was verified before shipping. Adopt whichever of these you can:

| Gate | Result |
|---|---|
| Solver/API test suite | 77 passing, including 9 new regression tests |
| TypeScript typecheck | clean |
| Python lint (ruff) and types (mypy) | clean, 21 files |
| Calibration monotonicity | 0 inversions across 0–7200 s, was 2 |
| Week-balance repro | 40-customer cluster 40/0/0/0 → 10/10/10/10 |

The regression tests are the part worth copying in spirit: each one names the bug it prevents and describes the failure in the comment, so a future reader knows why the assertion exists.

**One caution on porting item 1.3:** changing drive-time calibration shifts every estimate in the product. The desktop change was designed to preserve the calibrated anchors exactly and measured at +0.73% aggregate before shipping. If your web version has its own calibration, measure the aggregate delta on a real dataset before and after, and do not accept a large shift without re-baselining against ground truth.
