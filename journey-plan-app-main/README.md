# Journey Plan App

Desktop app for Towell Group sales managers — builds and optimizes monthly **Permanent Journey Plans (PJPs)** for salesmen using OSRM (self-hosted, GCC-states OSM extract) + Leaflet/OpenStreetMap + Google OR-Tools. Runs fully offline against the bundled OSRM graph; no external API keys required.

## For Claude sessions

Read [`CLAUDE.md`](./CLAUDE.md) and [`MEMORY.md`](./MEMORY.md) at the start of every session. They define the rules and the current state.

## For humans

### Prerequisites

- **Node 20+** (we develop on 24) — [nodejs.org](https://nodejs.org)
- **pnpm 9+** — `npm install -g pnpm`
- **Python 3.12** (NOT 3.13/3.14 yet — OR-Tools wheels lag) — [python.org](https://python.org)
- **uv** — `pip install uv` or [astral.sh/uv](https://astral.sh/uv)
- **git**

### First-time setup

```bash
pnpm install
pnpm sidecar:install   # creates sidecar/.venv and installs Python deps
```

### Run in dev

```bash
pnpm dev
```

Opens an Electron window. The Electron main process auto-spawns the Python sidecar on a random localhost port. Click the **Ping sidecar** button to confirm the round-trip.

### Build the installer

```bash
pnpm sidecar:build     # PyInstaller bundles the Python sidecar
pnpm dist              # electron-builder produces installer/JourneyPlanApp-Setup.exe
```

### Layout

| Folder | Purpose |
|---|---|
| `app/` | Electron + React + TypeScript desktop shell |
| `sidecar/` | Python FastAPI + OR-Tools sidecar (spawned by Electron) |
| `shared/` | TypeScript types mirrored from Pydantic models |
| `installer/` | Build output (gitignored) |

See [`CLAUDE.md`](./CLAUDE.md) for the full architecture diagram and decisions.
