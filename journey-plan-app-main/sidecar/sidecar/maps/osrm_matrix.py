"""OSRM matrix client — sole matrix backend.

Hits a local OSRM instance's `/table/v1/driving/...?annotations=duration,distance`
endpoint. Backs the `/matrix/batch` HTTP endpoint exposed by the sidecar.

Why OSRM: a single VRP run for 500 customers fetches tens of thousands of
pairs. The previous Google Distance Matrix path cost $5 per 1,000 elements
(removed 2026-05-18 after a $4k incident). OSRM running on localhost against
a GCC-states OSM extract is free and ~10x faster.

OSRM `/table` takes ALL points as a single coordinate list and returns an
N x M sub-matrix selected by `?sources=...&destinations=...`. We send origins
and destinations as a flat list and use indices to carve out the rectangle.
"""

from __future__ import annotations

import asyncio
import os
from typing import Any

import httpx

from sidecar.models import MatrixCell, MatrixPoint

# Matches the port the Electron main process binds osrm-routed to
# (see app/src/main/osrm.ts OSRM_PORT). Only used for standalone Python runs
# (`pnpm sidecar:dev`, pytest) where OSRM_BASE_URL isn't set by Electron.
DEFAULT_BASE_URL = "http://127.0.0.1:5050"

# OSRM has no documented per-request cap but the URL length is bounded by the
# server (default 8 KB). Each coord is ~17 chars; cap origins+destinations to
# 350 total per call to stay well under that. Larger requests are chunked.
_MAX_POINTS_PER_CALL = 350
_SEMAPHORE_LIMIT = 8

# Urban-delay calibration.
#
# OSRM's stock `car.lua` profile uses free-flow times — no turn delays, no
# stop-sign idle, no congestion. A 30-cell spot-check vs Google Distance
# Matrix on real Towell customer pairs in Muscat / Salalah / Sohar (2026-05-18)
# showed OSRM under-estimates short urban legs by 30-55% and medium legs by
# ~10%; long highway trips match within 5%. To keep the VRP capacity budget
# realistic we scale each OSRM duration by a band-specific multiplier.
#
# Tunable per-deployment via env: OSRM_URBAN_MULT, OSRM_SUBURBAN_MULT,
# OSRM_HIGHWAY_MULT. Defaults are calibrated to bring the median delta from
# -3.4% to near zero across the spot-check sample.
_URBAN_MAX_S = 600       # <10 min: urban
_SUBURBAN_MAX_S = 1800   # 10-30 min: suburban / inter-city
# Longer trips: highway-dominated, OSRM matches Google within ~5%.

# The multiplier is applied as a CONTINUOUS ramp between band anchors rather
# than as a step at the band edges (changed 2026-09-21).
#
# Stepping made calibrated cost non-monotonic in true drive time: a raw 599 s
# leg became 839 s while a raw 600 s leg became 690 s, so of two candidate next
# stops the solver was handed a cost that PREFERRED the genuinely farther one,
# and the day budget was charged less for a longer drive. Every leg within
# ~20% of the 10-min or 30-min edge was mis-ordered against its neighbours.
#
# Anchors sit at each band's midpoint, where the spot-check sample is densest,
# so the calibration those measurements justify is preserved exactly; only the
# transitions between them change. f(t) = t * m(t) is strictly increasing
# across the whole domain — asserted in tests/test_osrm_matrix.py.
_URBAN_ANCHOR_S = _URBAN_MAX_S / 2                        # 300
_SUBURBAN_ANCHOR_S = (_URBAN_MAX_S + _SUBURBAN_MAX_S) / 2  # 1200
_HIGHWAY_ANCHOR_S = _SUBURBAN_MAX_S + (_SUBURBAN_MAX_S - _SUBURBAN_ANCHOR_S)  # 2400


def _multipliers() -> tuple[float, float, float]:
    """Read calibration multipliers from env; safe defaults if unset/invalid."""

    def _read(name: str, default: float) -> float:
        try:
            return float(os.environ.get(name, default))
        except ValueError:
            return default

    return (
        _read("OSRM_URBAN_MULT", 1.40),
        _read("OSRM_SUBURBAN_MULT", 1.15),
        _read("OSRM_HIGHWAY_MULT", 1.00),
    )


def _multiplier_at(raw_seconds: float, mults: tuple[float, float, float]) -> float:
    """Piecewise-linear multiplier: flat at the urban anchor and below, flat at
    the highway anchor and above, linearly interpolated in between."""
    urban, suburban, highway = mults
    if raw_seconds <= _URBAN_ANCHOR_S:
        return urban
    if raw_seconds <= _SUBURBAN_ANCHOR_S:
        span = _SUBURBAN_ANCHOR_S - _URBAN_ANCHOR_S
        t = (raw_seconds - _URBAN_ANCHOR_S) / span
        return urban + (suburban - urban) * t
    if raw_seconds <= _HIGHWAY_ANCHOR_S:
        span = _HIGHWAY_ANCHOR_S - _SUBURBAN_ANCHOR_S
        t = (raw_seconds - _SUBURBAN_ANCHOR_S) / span
        return suburban + (highway - suburban) * t
    return highway


def _calibrate_duration(raw_seconds: float, mults: tuple[float, float, float]) -> int:
    """Apply the calibration ramp and round to int seconds.

    `mults` is passed in rather than read from the environment here: this runs
    once per matrix CELL (122,500 times for a 350x350 table) on the event-loop
    thread, and re-reading three env vars per cell blocked the /health polls
    the renderer makes while large tables are being mapped."""
    return round(raw_seconds * _multiplier_at(raw_seconds, mults))


class OsrmUnreachableError(RuntimeError):
    """OSRM endpoint failed to respond. Caller decides whether to fall back."""


class OsrmRequestRejectedError(RuntimeError):
    """OSRM is alive and answered, but rejected THIS request (e.g. code
    "TooBig" when the table exceeds --max-table-size, or "InvalidQuery").

    Kept distinct from OsrmUnreachableError because the recovery is completely
    different: the server is healthy and reinstalling it changes nothing.
    Collapsing the two is what made the 2026-05-23 --max-table-size incident
    read as "OSRM unreachable — run pnpm osrm:setup" while OSRM was serving
    normally."""


def _base_url() -> str:
    return os.environ.get("OSRM_BASE_URL", DEFAULT_BASE_URL).rstrip("/")


def _rejection_error(response: httpx.Response, point_count: int) -> RuntimeError:
    """Turn a non-200 /table response into an error that names the real cause.

    OSRM puts a machine-readable `code` and a `message` in the JSON body of its
    4xx replies; without reading it every rejection looked like an outage."""
    code = ""
    message = ""
    try:
        body = response.json()
        if isinstance(body, dict):
            code = str(body.get("code", ""))
            message = str(body.get("message", ""))
    except ValueError:
        pass

    if code == "TooBig":
        return OsrmRequestRejectedError(
            f"OSRM refused a {point_count}-point /table request as too big "
            f"({message or 'TooBig'}). osrm-routed is running but its "
            "--max-table-size is lower than this request needs."
        )
    if response.status_code == 414 or (response.status_code == 400 and not code):
        return OsrmRequestRejectedError(
            f"OSRM rejected a {point_count}-point /table request "
            f"(HTTP {response.status_code}) — the request URL is likely too long."
        )
    if code:
        return OsrmRequestRejectedError(
            f"OSRM rejected the /table request: {code}"
            f"{f' — {message}' if message else ''}."
        )
    return OsrmUnreachableError(f"OSRM HTTP {response.status_code}")


def _coord_str(points: list[MatrixPoint]) -> str:
    # OSRM expects lng,lat (not lat,lng).
    return ";".join(f"{p.lng},{p.lat}" for p in points)


async def _table_call(
    client: httpx.AsyncClient,
    points: list[MatrixPoint],
    src_indices: list[int],
    dst_indices: list[int],
) -> dict[str, Any]:
    url = f"{_base_url()}/table/v1/driving/{_coord_str(points)}"
    params = {
        "sources": ";".join(str(i) for i in src_indices),
        "destinations": ";".join(str(i) for i in dst_indices),
        "annotations": "duration,distance",
    }
    for attempt in range(3):
        try:
            response = await client.get(url, params=params, timeout=30.0)
        except httpx.HTTPError as err:
            if attempt == 2:
                raise OsrmUnreachableError(f"OSRM transport error: {err}") from err
            await asyncio.sleep(0.3 * (2**attempt))
            continue

        if response.status_code in (429, 500, 502, 503, 504):
            if attempt == 2:
                raise OsrmUnreachableError(f"OSRM HTTP {response.status_code}")
            await asyncio.sleep(0.3 * (2**attempt))
            continue

        if response.status_code != 200:
            raise _rejection_error(response, len(points))

        return response.json()  # type: ignore[no-any-return]

    raise OsrmUnreachableError("OSRM retries exhausted")


def _cells_from_payload(
    payload: dict[str, Any],
    origin_indices: list[int],
    dest_indices: list[int],
) -> list[MatrixCell]:
    """Map an OSRM /table response into MatrixCell[] using absolute indices.

    OSRM returns `durations` (seconds, float) and `distances` (metres, float)
    as 2-D arrays aligned to the `sources` x `destinations` selection. A `null`
    cell means OSRM could not route between the pair — we surface that as
    `zero_results` so the solver's haversine fallback kicks in.
    """
    durations = payload.get("durations") or []
    distances = payload.get("distances") or []
    # Read the env-tunable multipliers ONCE per payload, not once per cell.
    mults = _multipliers()
    cells: list[MatrixCell] = []
    for row_idx, origin_index in enumerate(origin_indices):
        dur_row = durations[row_idx] if row_idx < len(durations) else []
        dist_row = distances[row_idx] if row_idx < len(distances) else []
        for col_idx, dest_index in enumerate(dest_indices):
            dur = dur_row[col_idx] if col_idx < len(dur_row) else None
            dist = dist_row[col_idx] if col_idx < len(dist_row) else None
            if dur is None or dist is None:
                cells.append(
                    MatrixCell(
                        origin_index=origin_index,
                        dest_index=dest_index,
                        duration_seconds=None,
                        distance_meters=None,
                        status="zero_results",
                    )
                )
            else:
                cells.append(
                    MatrixCell(
                        origin_index=origin_index,
                        dest_index=dest_index,
                        # Distances pass through unchanged (OSRM's road-network
                        # geometry is accurate); only durations get the urban
                        # calibration applied.
                        duration_seconds=_calibrate_duration(dur, mults),
                        # OSRM returns -1 for a same-node pair. Clamp here, at
                        # the boundary that knows OSRM's semantics, rather than
                        # leaving each consumer to rediscover it.
                        distance_meters=max(0, round(dist)),
                        status="ok",
                    )
                )
    return cells


async def matrix_batch(
    origins: list[MatrixPoint],
    destinations: list[MatrixPoint],
) -> list[MatrixCell]:
    """Compute a duration/distance matrix for origins x destinations via OSRM.

    Indices in the returned cells are absolute into `origins` / `destinations`.
    """
    if not origins or not destinations:
        return []

    # Build a single deduped point list with stable indices, then partition.
    # OSRM `/table` is happiest when the sources and destinations are part of
    # ONE coordinate list, so we concatenate and remember each side's slice.
    # For very large requests we further split the destinations side to keep
    # URL length sane; each call still uses the full origins set.
    all_points = list(origins) + list(destinations)
    origin_idx = list(range(len(origins)))
    dest_idx_in_payload = list(range(len(origins), len(origins) + len(destinations)))

    if len(all_points) <= _MAX_POINTS_PER_CALL:
        async with httpx.AsyncClient() as client:
            payload = await _table_call(client, all_points, origin_idx, dest_idx_in_payload)
        return _cells_from_payload(
            payload,
            origin_indices=list(range(len(origins))),
            dest_indices=list(range(len(destinations))),
        )

    # Chunked path: split BOTH sides into tiles.
    #
    # This used to hold the origins set whole and split only destinations,
    # which works while origins are a small fraction of the cap but degenerates
    # badly once they are not: a self-matrix of 400 same-area customers gave
    # `chunk_size = max(1, 350 - 400) = 1`, i.e. 400 calls each still carrying
    # all 401 coordinates — far past OSRM's URL limit, so every one of them was
    # rejected. Halving the budget between the two sides keeps each call at or
    # under the cap no matter how the request is shaped.
    half = max(1, _MAX_POINTS_PER_CALL // 2)
    o_chunk = min(len(origins), half)
    d_chunk = max(1, _MAX_POINTS_PER_CALL - o_chunk)
    semaphore = asyncio.Semaphore(_SEMAPHORE_LIMIT)
    flat: list[MatrixCell] = []
    async with httpx.AsyncClient() as client:

        async def _do(o_start: int, o_end: int, d_start: int, d_end: int) -> list[MatrixCell]:
            sub_origins = origins[o_start:o_end]
            sub_dests = destinations[d_start:d_end]
            points = list(sub_origins) + list(sub_dests)
            o_idx = list(range(len(sub_origins)))
            d_idx = list(range(len(sub_origins), len(sub_origins) + len(sub_dests)))
            async with semaphore:
                payload = await _table_call(client, points, o_idx, d_idx)
            # Re-base both index sets back onto the caller's full lists.
            return _cells_from_payload(
                payload,
                origin_indices=list(range(o_start, o_end)),
                dest_indices=list(range(d_start, d_end)),
            )

        tasks = [
            asyncio.create_task(
                _do(
                    o_start,
                    min(o_start + o_chunk, len(origins)),
                    d_start,
                    min(d_start + d_chunk, len(destinations)),
                )
            )
            for o_start in range(0, len(origins), o_chunk)
            for d_start in range(0, len(destinations), d_chunk)
        ]
        results = await asyncio.gather(*tasks)
    for batch in results:
        flat.extend(batch)
    return flat


async def health_check() -> bool:
    """Quick OSRM liveness check — returns True if /nearest responds 200.

    Used by the sidecar's `/health/osrm` endpoint so the renderer can surface
    OSRM state to the user. Returns False on any failure; the caller surfaces
    that as a hard 503 from `/matrix/batch` — there is no fallback path
    (Google Distance Matrix was removed 2026-05-18 after a $4k incident).
    """
    try:
        async with httpx.AsyncClient() as client:
            # /nearest is the cheapest OSRM endpoint and exists in all profiles.
            response = await client.get(
                f"{_base_url()}/nearest/v1/driving/58.5,23.5",
                timeout=2.0,
            )
            return response.status_code == 200
    except httpx.HTTPError:
        return False
