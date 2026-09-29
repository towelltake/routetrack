# CLAUDE.md — Journey Plan App

> **Project-level instructions. Loaded automatically at the start of every Claude Code session in this folder. Treat as authoritative for this codebase.**

---

## 0. Session protocol (read this first, every session)

Every Claude session working in this repo MUST follow this protocol:

**At session start:**
1. Read this file (`CLAUDE.md`) in full.
2. **Read `MEMORY.md` in full.** It contains the current state of the project — what's done, what's next, open questions, and recent decisions. Do not propose work without reading it.
3. If the user's request is ambiguous, check `MEMORY.md` first; the answer is often there.

**At session end (before final reply):**
1. Update `MEMORY.md`:
   - Move completed items from **Next up** → **Currently done**.
   - Add any new decisions to the **Decision log** with today's date.
   - Update the **Current phase** line if it changed.
   - Append new follow-ups under **Next up** or **Open questions**.
2. Keep the update terse — it's a running ledger, not a report.
3. The `MEMORY.md` file has a template at the bottom showing exactly what to append.
4. **Run `pnpm memory:check`.** `MEMORY.md` is read in full at the start of
   every session, so its size is a tax on every future session. The script
   exits non-zero once the file reaches **100 KB** — when it does, optimize
   before you finish:
   - Cut the **oldest** Decision log entries and move them **verbatim** into
     `docs/memory-archive/decision-log-<first-date>-to-<last-date>.md`. Archive,
     never delete — the history stays in the repo either way.
   - Trim until the file is comfortably under the limit (~70 KB), not just one
     byte below it.
   - Always keep: Current phase, Roadmap, Next up, Open questions, Known
     issues, the session-end template, and the most recent entries.
   - Leave a one-line pointer in the Decision log saying what moved and where.
   - Consolidate while you are in there: fold superseded entries into the one
     that superseded them rather than carrying both.

This protocol is non-negotiable. If a session ends without updating `MEMORY.md`, the next session loses context.

---

## 1. Project mission

A Windows desktop application that helps Towell Group sales managers build and optimize **monthly journey plans** (a.k.a. **PJPs — Permanent Journey Plans**) for their salesmen. Given a list of customers (with addresses or lat/lng), a list of salesmen, and per-customer visit requirements, the app produces an optimized day-by-day route per salesman that:

- Respects working-hour limits per day
- Accounts for customer facetime (time spent on-site)
- Computes realistic drive time from a bundled OSRM routing engine (GCC-states OSM extract), not straight-line distance
- Hits the required monthly visit frequency per customer
- Honours allowed days-of-week per customer
- Draws the actual road polyline for each route on a map

**Users:** Sales managers in Towell Group's FMCG, Auto, and other distribution clusters. Output is consumed by the salesmen executing the plan on the ground.

---

## 2. Tech stack (locked)

| Area | Choice |
|---|---|
| Desktop framework | **Electron + React + TypeScript** (renderer in React, main process in Node) |
| Optimizer | **Python ≥3.11 + Google OR-Tools**, run as a **FastAPI sidecar** on localhost, spawned by Electron at startup |
| Maps & routing | **OSRM (self-hosted, GCC-states OSM extract from Geofabrik) for everything** — solver matrix calls AND user-visible route polylines. **Leaflet + OpenStreetMap tiles** for the map view. **No Google APIs.** Distance Matrix was removed 2026-05-18 (after a $4k cost incident); Directions, Roads, Maps JS, and Geocoding were all removed 2026-05-22. Re-introducing any Google API requires explicit user approval and a new dated decision-log entry. |
| Local storage | **SQLite** via `better-sqlite3` in the Electron main process |
| Data import | Excel/CSV via `xlsx` (renderer) + `pandas`/`openpyxl` (sidecar, when needed). **Every customer row must include lat/lng** — there is no geocoding fallback. |
| Secrets | None. The app holds no external service credentials. |
| Packaging | **electron-builder** (NSIS `.exe` installer) + **PyInstaller** to bundle the Python sidecar as a single binary inside `resources/` |
| Package manager | **pnpm** for the Electron app; **uv** (or pip + venv) for the Python sidecar |

**Do not switch any of these without an explicit decision logged in `MEMORY.md`.** They are interdependent — e.g., swapping the optimizer breaks the sidecar contract; swapping the map provider breaks the polyline pipeline.

---

## 3. Architecture

```
┌───────────────────────────────────────────────────────────────────┐
│                     Electron Desktop App (.exe)                   │
│                                                                   │
│  ┌────────────────────────┐         ┌────────────────────────┐    │
│  │   Renderer (React/TS)  │  IPC    │   Main Process (Node)  │    │
│  │   - Map (Leaflet/OSM)  │ ◄─────► │   - SQLite r/w         │    │
│  │   - Tables, forms      │         │   - File dialogs       │    │
│  │   - Route preview      │         │   - Spawns BOTH        │    │
│  └────────────────────────┘         │     sidecars below     │    │
│                                     └────┬─────────────┬─────┘    │
│                                          │             │          │
│                              HTTP 127.0.0.1            │          │
│                                          ▼             ▼          │
│                     ┌────────────────────────┐  ┌──────────────┐  │
│                     │ Python Sidecar         │  │ osrm-routed  │  │
│                     │ (FastAPI, PyInstaller) │  │ (5050 / 5051)│  │
│                     │ - OR-Tools VRP solver  │  │ - /table     │  │
│                     │ - OSRM client (matrix) │  │   matrix     │  │
│                     │ - OSRM /route client   │  │ - /route     │  │
│                     │   for polylines        │  │ - GCC .osrm  │  │
│                     │                        │  │   MLD graph  │  │
│                     └─────────┬──────────────┘  └──────────────┘  │
└───────────────────────────────┼───────────────────────────────────┘
                                ▼
                  (OSM tile servers — img-only, for map tiles.
                   No other external network calls.)
```

- The **renderer** never talks to the sidecars directly — all traffic goes through the main process for security and centralised error handling.
- **No external service credentials.** The app talks only to localhost OSRM + the bundled Python sidecar, plus OSM tile servers for map imagery.
- The Python sidecar binds to `127.0.0.1` on a random free port; the main process discovers it via stdout handshake.
- **OSRM (osrm-routed.exe)** is a second sidecar process, started by the main process before the Python sidecar. It binds to `127.0.0.1:5050` when packaged and `127.0.0.1:5051` in dev, so a developer session and the installed app can run side by side (`app/src/main/osrm.ts` OSRM_PORT). It serves the GCC-states OSM graph (`gcc.osrm`). The Python sidecar's `/matrix/batch` and `/route/osrm` endpoints forward to OSRM via `OSRM_BASE_URL` env var.
- If OSRM is unreachable, `/matrix/batch` returns a hard `503` with a setup-hint message. There is no fallback — the Google Distance Matrix client was deleted from the repo on 2026-05-18 after a $4k cost incident.

---

## 4. Folder layout

```
journey-plan-app/
├── CLAUDE.md                  # this file
├── MEMORY.md                  # running state — read every session
├── README.md
├── LICENSE
├── package.json               # pnpm workspace root
├── pnpm-workspace.yaml
├── .npmrc
│
├── app/                       # Electron + React + TS
│   ├── src/
│   │   ├── main/              # Electron main process (IPC, SQLite, spawners)
│   │   │   ├── repo/          # one module per table
│   │   │   ├── import/        # Excel importer (excel/validate/commit)
│   │   │   ├── export/        # Excel writer
│   │   │   └── migrations/    # NNNN_*.sql, applied in filename order
│   │   ├── renderer/          # React UI
│   │   └── preload/           # contextBridge IPC surface
│   ├── scripts/               # dev/smoke helpers (not shipped)
│   ├── electron-builder.yml
│   └── package.json
│
├── sidecar/                   # Python FastAPI + OR-Tools + OSRM clients
│   ├── sidecar/
│   │   ├── main.py            # FastAPI app
│   │   ├── models.py          # Pydantic schemas (source of truth for wire types)
│   │   ├── solver/            # OR-Tools VRP + CP-SAT assignment
│   │   ├── maps/              # osrm_matrix + osrm_route clients
│   │   └── build_sidecar.py   # PyInstaller entry
│   ├── tests/                 # pytest
│   ├── osrm/                  # Created by sidecar/scripts/setup-osrm.ps1 (gitignored)
│   │   ├── bin/               # osrm-routed.exe + DLLs (conda-forge)
│   │   ├── graph/             # gcc.osrm* MLD-preprocessed graph (~400 MB)
│   │   └── profiles/          # car.lua etc.
│   ├── scripts/
│   │   └── setup-osrm.ps1     # one-time OSRM bootstrap
│   └── pyproject.toml
│
├── shared/                    # TypeScript types mirrored from Pydantic
│   └── src/index.ts
│
├── scripts/                   # repo-level audit/report tooling (Python + cjs)
├── docs/                      # design notes, reviews/, memory-archive/, brand/
├── installer/                 # electron-builder output + icons (gitignored)
└── .github/workflows/         # CI
```

Future sessions: do not invent new top-level folders without updating this
section, and update it when one moves.

---

## 5. Coding conventions

- **TypeScript**: `strict: true`, no `any` without a `// reason:` comment, prefer `unknown` + type guards.
- **Python**: ≥3.11, type hints everywhere, `from __future__ import annotations`, Pydantic v2 models. Run `ruff` + `mypy`.
- **Comments**: default to none. Only write a comment when it explains a non-obvious **why** (a constraint, a workaround, a subtle invariant). Don't narrate what the code does.
- **Functions**: small, named for behaviour, no unused parameters.
- **Errors**: at boundaries only (HTTP, IPC, file I/O, user input). Don't wrap internal calls in defensive try/except.
- **Tests**: pytest for the sidecar, vitest for the app. Solver tests use small fixed VRP fixtures with known optimal solutions.
- **Commits**: small and focused. One concern per commit. Conventional-commits style (`feat:`, `fix:`, `chore:`, `refactor:`).
- **No premature abstraction.** Three concrete copies beats one wrong abstraction.

---

## 6. Domain glossary

Anchor terms across the codebase, UI, and conversations:

| Term | Definition |
|---|---|
| **PJP** | Permanent Journey Plan — the monthly route schedule per salesman. Industry-standard term in FMCG distribution. |
| **Journey plan** | Synonym for PJP. Use **PJP** in code, **Journey plan** in user-facing UI text. |
| **Salesman** | A single field rep with a home/start location, working days, daily working-hour window, and an assigned customer set (or pool). |
| **Customer** | An outlet/retailer/account. Has a location (lat/lng), a facetime, a monthly visit frequency, and optionally allowed days of week. |
| **Facetime** | Time in minutes the salesman spends on-site with the customer per visit. Default if not provided: 15 minutes. |
| **Drive time** | Realistic travel time between two points. v1 source: OSRM `/table` against the GCC-states OSM extract (replaced Google Distance Matrix after the 2026-05-18 cost incident). NOT Haversine + assumed speed, except as a safety fallback when a matrix cell is missing. |
| **Frequency** | Required visits per month per customer. Common values: 1 (monthly), 2 (fortnightly), 4 (weekly). |
| **Cycle** | A sub-period within the month that repeats — e.g., a fortnightly customer creates 2 cycles. The optimizer assigns visits to cycle-days. |
| **Working day** | A day a given salesman works. Default working week: **Sun–Thu** (Oman standard). Weekend: **Fri–Sat**. Overridable per salesman. |
| **Route** | The ordered sequence of customers a salesman visits on a single day. |
| **Visit** | A single (salesman, customer, date, scheduled-time) record. The output of optimization. |
| **Polyline** | The road path drawn on the map for a route. Sourced from OSRM `/route` via the sidecar (Google Directions was removed 2026-05-22), NOT straight lines between markers. |

---

## 7. Oman / GCC context

- **Default map centre:** Muscat (23.5880° N, 58.3829° E).
- **Default working week:** Sun–Thu (5 days). Weekend: Fri–Sat. **Override per salesman.**
- **Public holidays:** Oman has variable Islamic-calendar holidays (Eid, Prophet's Birthday, etc.) plus fixed national days. Phase 4+ should let users mark non-working dates manually; we are NOT building an auto holiday calendar in v1.
- **Address format:** Omani customer addresses are often a mix of English + Arabic transliteration ("Way", "Street", building numbers, and a landmark). Many customers lack a formal address — lat/lng pin is the only acceptable input now that geocoding has been removed.
- **Currency:** OMR (1 OMR ≈ 2.6 USD) — only relevant when we add cost views later.
- **Distances:** kilometres. Speeds: km/h. Times: 24-hour clock, local time (UTC+4, no DST).
- **Language:** All UI is English in v1. Arabic UI is parked as a future enhancement.

---

## 8. Build & run commands

```bash
# install
pnpm install
pnpm sidecar:install        # uv sync for the Python sidecar
pnpm osrm:setup             # ONE-TIME: download osrm-backend + GCC-states OSM extract,
                            # preprocess MLD graph. ~600 MB total, ~5 min. Re-run
                            # with `pnpm osrm:setup:force` to rebuild the graph.

# dev
pnpm dev                    # Electron + auto-spawns BOTH sidecars (Python + osrm-routed)
pnpm sidecar:dev            # Python sidecar standalone (uvicorn --reload)

# typecheck / lint / test
pnpm typecheck              # tsc across shared + app — the real TS gate
pnpm sidecar:test           # pytest (the whole solver suite runs ~7 min)
pnpm memory:check           # MEMORY.md size gate (see §0)
cd sidecar && uv run ruff check . && uv run mypy .

# build installer
pnpm sidecar:build          # PyInstaller -> app/resources/sidecar/sidecar.exe
pnpm dist                   # electron-builder -> installer/JourneyPlanApp-Setup-<ver>.exe
                            # (bundles BOTH sidecar.exe and sidecar/osrm/*)
```

Machine-specific gotchas live in `MEMORY.md` -> Known issues (uv needs
`UV_LINK_MODE=copy` here; `pnpm dist` needs Windows Developer Mode on).

> `pnpm test` and `pnpm lint` currently resolve to nothing — no workspace
> defines either script (no vitest suite, no eslint config yet). Sidecar
> coverage is real; the TS gate is `pnpm typecheck` plus manual verification.
> Don't cite a green `pnpm test` or `pnpm lint` as evidence.

---

## 9. Secrets handling

The app holds no external service credentials. All Google API clients (Distance Matrix, Directions, Roads, Maps JS, Geocoding) were removed by 2026-05-22; the keytar slots that stored the Google Maps API key were dropped at the same time. If a future feature needs a service credential, reintroduce keytar with a fresh per-service slot.

---

## 10. What NOT to do

- **Don't add features beyond what's in `MEMORY.md` → Next up** without asking the user first. This app has a finite v1 scope; scope creep is the main risk.
- **Don't reintroduce any Google API.** Distance Matrix was removed 2026-05-18 after a $4k cost incident; Directions, Roads, Maps JS, and Geocoding were removed 2026-05-22. No fallback, no flag, no escape hatch — re-adding any of them requires explicit user approval and a new dated decision-log entry in `MEMORY.md`. Matrix and polylines come from OSRM; map tiles come from OpenStreetMap.
- **Don't introduce a new map provider for the user-visible map.** Mapbox, HERE, and Google are all out. Leaflet + OSM tiles is the locked choice.
- **Don't replace OR-Tools** with a hand-rolled heuristic, even if "it would be simpler." OR-Tools handles the constraint matrix we need; a heuristic won't.
- **Don't add cloud sync, multi-user auth, or a web backend.** This is a local desktop app by design. State lives in SQLite on the user's laptop.
- **Don't add a geocoding fallback.** Customers must arrive at import with lat/lng — the importer rejects rows without coordinates.
- **Don't write Arabic-language UI strings** in v1. Park it.
- **Don't auto-install Python on the user's laptop.** The sidecar ships as a PyInstaller binary — that's the whole point. If you find yourself writing "user must install Python 3.11", you're off-path.

---

## 11. When the user asks for something

- Pyramid Principle: answer first, then the reasoning, then evidence.
- Short, direct, GCOO-facing register. No marketing padding, no boilerplate disclaimers.
- If the request conflicts with this file or `MEMORY.md`, surface the conflict before acting.
- If the request is genuinely ambiguous, ask one targeted question — don't guess on big decisions.
- If the request would change a locked tech-stack decision, treat that as a major decision and confirm explicitly.
