"""OSRM /route client for user-visible route polylines.

Sole polyline source after Google Directions was removed 2026-05-22. We ask
OSRM for `geometries=geojson` and return an array of `MatrixPoint` (lat/lng
pairs) directly — react-leaflet's `Polyline positions` prop expects exactly
that shape, no encoded-polyline + decode step on the renderer side.
"""

from __future__ import annotations

import asyncio
import os
from typing import Any

import httpx

from sidecar.models import MatrixPoint, OsrmRouteResponse

# Matches the port the Electron main process binds osrm-routed to
# (see app/src/main/osrm.ts OSRM_PORT). Only used for standalone Python runs
# where OSRM_BASE_URL isn't set by Electron.
DEFAULT_BASE_URL = "http://127.0.0.1:5050"


class OsrmRouteError(RuntimeError):
    """OSRM /route failed or returned no drivable route."""


# OSRM signals "these specific pins can't be drawn as a route" with an HTTP 400
# and one of these body codes — a customer pinned on a one-way/dead-end segment
# the graph can enter but not leave, or a pin that won't snap to any road. These
# are data problems, not sidecar faults, so we degrade to an empty polyline (the
# map draws dashed straight legs) instead of raising a 503 that spams the log.
_NO_GEOMETRY_CODES = frozenset({"NoRoute", "NoSegment"})


def _empty_route() -> OsrmRouteResponse:
    return OsrmRouteResponse(
        coordinates=[],
        total_distance_meters=0,
        total_duration_seconds=0,
    )


def _base_url() -> str:
    return os.environ.get("OSRM_BASE_URL", DEFAULT_BASE_URL).rstrip("/")


def _coord_str(points: list[MatrixPoint]) -> str:
    # OSRM expects lng,lat (not lat,lng).
    return ";".join(f"{p.lng},{p.lat}" for p in points)


async def route_for_waypoints(waypoints: list[MatrixPoint]) -> OsrmRouteResponse:
    if len(waypoints) < 2:
        raise OsrmRouteError("at least 2 waypoints required")

    url = f"{_base_url()}/route/v1/driving/{_coord_str(waypoints)}"
    params = {
        "overview": "full",
        "geometries": "geojson",
        "continue_straight": "default",
    }
    async with httpx.AsyncClient() as client:
        for attempt in range(3):
            try:
                response = await client.get(url, params=params, timeout=15.0)
            except httpx.HTTPError as err:
                if attempt == 2:
                    raise OsrmRouteError(f"OSRM transport error: {err}") from err
                await asyncio.sleep(0.3 * (2**attempt))
                continue
            if response.status_code in (429, 500, 502, 503, 504):
                if attempt == 2:
                    raise OsrmRouteError(f"OSRM HTTP {response.status_code}")
                await asyncio.sleep(0.3 * (2**attempt))
                continue
            if response.status_code != 200:
                # A 400 with a no-geometry code is an unroutable pin, not a
                # fault — return an empty polyline so the map falls back to
                # dashed legs. Anything else is a real error worth surfacing.
                body: dict[str, Any] = {}
                try:
                    body = response.json()
                except ValueError:
                    body = {}
                if body.get("code") in _NO_GEOMETRY_CODES:
                    return _empty_route()
                raise OsrmRouteError(f"OSRM HTTP {response.status_code}")
            payload: dict[str, Any] = response.json()
            break
        else:
            raise OsrmRouteError("OSRM retries exhausted")

    if payload.get("code") != "Ok":
        # `NoRoute` is the typical case when an off-road pin can't be matched.
        return _empty_route()

    routes = payload.get("routes") or []
    if not routes:
        return _empty_route()

    route = routes[0]
    geom = route.get("geometry") or {}
    raw_coords: list[list[float]] = geom.get("coordinates") or []
    # OSRM returns [lng, lat]; flip to {lat, lng} for the wire format.
    coords = [MatrixPoint(lat=c[1], lng=c[0]) for c in raw_coords if len(c) >= 2]
    return OsrmRouteResponse(
        coordinates=coords,
        total_distance_meters=round(route.get("distance") or 0),
        total_duration_seconds=round(route.get("duration") or 0),
    )
