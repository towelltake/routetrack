"""FastAPI sidecar entry point.

Spawned by the Electron main process at app startup. Binds to 127.0.0.1 on a
random free port and announces itself on stdout with the line
`SIDECAR_READY port=<n>` — the parent process parses that line to learn where
to send HTTP requests.
"""

from __future__ import annotations

import asyncio
import os
import socket
import sys

import uvicorn
from fastapi import FastAPI, HTTPException, Request
from fastapi.responses import JSONResponse

from sidecar import __version__
from sidecar.maps.osrm_matrix import (
    OsrmRequestRejectedError,
    OsrmUnreachableError,
)
from sidecar.maps.osrm_matrix import (
    health_check as osrm_health_check,
)
from sidecar.maps.osrm_matrix import (
    matrix_batch as osrm_matrix_batch,
)
from sidecar.maps.osrm_route import (
    OsrmRouteError,
)
from sidecar.maps.osrm_route import (
    route_for_waypoints as osrm_route_for_waypoints,
)
from sidecar.models import (
    AssignSalesmenRequest,
    AssignSalesmenResponse,
    DirectionsRequest,
    HealthResponse,
    MatrixBatchRequest,
    MatrixBatchResponse,
    OptimizeRequest,
    OptimizeResponse,
    OsrmRouteResponse,
    ResequenceDayRequest,
    ResequenceDayResponse,
    SuggestTeamSizeRequest,
    SuggestTeamSizeResponse,
)
from sidecar.solver import (
    resequence_day,
    run_assign_salesmen,
    run_optimize,
    run_team_size_suggestion,
)

app = FastAPI(title="Journey Plan Sidecar", version=__version__)


@app.get("/health", response_model=HealthResponse)
async def health() -> HealthResponse:
    return HealthResponse(status="ok", version=__version__, sidecar_pid=os.getpid())


@app.get("/health/osrm")
async def osrm_health_endpoint() -> dict[str, object]:
    """Probe OSRM liveness. Renderer surfaces this as a 'matrix backend' badge."""
    ok = await osrm_health_check()
    return {
        "reachable": ok,
        "baseUrl": os.environ.get("OSRM_BASE_URL", "http://127.0.0.1:5050"),
    }


@app.post("/matrix/batch", response_model=MatrixBatchResponse)
async def matrix_batch_endpoint(request: MatrixBatchRequest) -> MatrixBatchResponse:
    # OSRM-only. Google Distance Matrix was removed 2026-05-18 after a $4k
    # incident — no fallback, no env-var toggle, no escape hatch. If OSRM is
    # unreachable the caller gets a 503 and must fix OSRM, not bill Google.
    try:
        cells = await osrm_matrix_batch(request.origins, request.destinations)
    except OsrmRequestRejectedError as err:
        # OSRM is healthy and said no to this particular request. 502 rather
        # than 503, and no "reinstall OSRM" hint — that advice sent the user
        # down the wrong path during the 2026-05-23 --max-table-size incident.
        raise HTTPException(status_code=502, detail=str(err)) from err
    except OsrmUnreachableError as err:
        raise HTTPException(
            status_code=503,
            detail=f"OSRM unreachable: {err}. Run 'pnpm osrm:setup' or check osrm-routed logs.",
        ) from err
    return MatrixBatchResponse(cells=cells)


@app.exception_handler(ValueError)
async def _value_error_handler(_request: Request, exc: ValueError) -> JSONResponse:
    """A malformed date or HH:MM string in the request body reaches the solver
    as a plain ValueError from `date.fromisoformat` / `_parse_hhmm` and used to
    surface as a bare 500 "Internal Server Error", naming neither the field nor
    the value. It is caller input, so answer 422 and say what failed."""
    return JSONResponse(status_code=422, content={"detail": f"Invalid request value: {exc}"})


@app.post("/optimize", response_model=OptimizeResponse)
async def optimize_endpoint(request: OptimizeRequest) -> OptimizeResponse:
    # Solver is CPU-bound and pure Python; run in a thread to keep the event
    # loop responsive (matters for long datasets at the 5s/week limit).
    return await asyncio.to_thread(run_optimize, request)


@app.post("/optimize/suggest-team-size", response_model=SuggestTeamSizeResponse)
async def suggest_team_size_endpoint(
    request: SuggestTeamSizeRequest,
) -> SuggestTeamSizeResponse:
    return await asyncio.to_thread(run_team_size_suggestion, request)


@app.post("/optimize/resequence-day", response_model=ResequenceDayResponse)
async def resequence_day_endpoint(request: ResequenceDayRequest) -> ResequenceDayResponse:
    return await asyncio.to_thread(resequence_day, request)


@app.post("/assign-salesmen", response_model=AssignSalesmenResponse)
async def assign_salesmen_endpoint(request: AssignSalesmenRequest) -> AssignSalesmenResponse:
    """Upfront salesman-to-customer assignment (Phase 7a). CP-SAT, CPU-bound."""
    return await asyncio.to_thread(run_assign_salesmen, request)


@app.post("/route/osrm", response_model=OsrmRouteResponse)
async def osrm_route_endpoint(request: DirectionsRequest) -> OsrmRouteResponse:
    """Route geometry from local OSRM — sole polyline source after Google
    Directions was removed 2026-05-22."""
    if len(request.waypoints) < 2:
        raise HTTPException(
            status_code=422, detail="at least 2 waypoints are required to build a route"
        )
    try:
        return await osrm_route_for_waypoints(request.waypoints)
    except OsrmRouteError as err:
        raise HTTPException(
            status_code=503,
            detail=f"OSRM /route failed: {err}. Run 'pnpm osrm:setup' or check osrm-routed.",
        ) from err


def _pick_free_port() -> int:
    with socket.socket(socket.AF_INET, socket.SOCK_STREAM) as s:
        s.bind(("127.0.0.1", 0))
        port: int = s.getsockname()[1]
        return port


async def _announce_when_ready(port: int) -> None:
    # Poll the socket until the server is accepting connections, then write the
    # handshake line. Electron parses stdout and only routes traffic once it
    # sees this line.
    for _ in range(200):
        try:
            with socket.create_connection(("127.0.0.1", port), timeout=0.1):
                sys.stdout.write(f"SIDECAR_READY port={port}\n")
                sys.stdout.flush()
                return
        except OSError:
            await asyncio.sleep(0.05)
    sys.stderr.write("SIDECAR_FAILED could not confirm bind\n")
    sys.stderr.flush()


def run() -> None:
    port = _pick_free_port()
    config = uvicorn.Config(
        app,
        host="127.0.0.1",
        port=port,
        # INFO goes to stderr, which the Electron side records as diagnostics
        # errors — four bogus entries per launch. Only warnings and worse.
        log_level="warning",
        access_log=False,
    )
    server = uvicorn.Server(config)

    async def _main() -> None:
        await asyncio.gather(server.serve(), _announce_when_ready(port))

    asyncio.run(_main())


if __name__ == "__main__":
    run()
