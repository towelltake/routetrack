# Sidecar

Python sidecar for the Journey Plan App. Hosts the FastAPI server that Electron talks to, and (from Phase 3) the OR-Tools VRP solver + Google Maps client.

## Run standalone

```bash
uv sync
uv run python -m sidecar.main
```

The process prints `SIDECAR_READY port=<n>` on stdout once the HTTP server accepts connections. Electron's main process reads that line to discover where to send requests.

## Test

```bash
uv run pytest
```
