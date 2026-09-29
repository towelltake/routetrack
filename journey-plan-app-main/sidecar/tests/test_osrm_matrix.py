"""Tests for /matrix/batch — OSRM-only.

Google Distance Matrix was removed 2026-05-18 after a $4k incident. There is
no fallback path to test.
"""

from __future__ import annotations

import re
from typing import Any

import pytest
from fastapi.testclient import TestClient
from pytest_httpx import HTTPXMock

from sidecar.main import app

_OSRM_TABLE_URL = re.compile(r"^http://osrm\.test:5000/table/v1/driving/.*")
_OSRM_NEAREST_URL = re.compile(r"^http://osrm\.test:5000/nearest/.*")


@pytest.fixture(autouse=True)
def _osrm_base_url(monkeypatch: pytest.MonkeyPatch) -> None:
    monkeypatch.setenv("OSRM_BASE_URL", "http://osrm.test:5000")


@pytest.fixture
def client() -> TestClient:
    return TestClient(app)


def _ok_table(
    durations: list[list[float | None]],
    distances: list[list[float | None]],
) -> dict[str, Any]:
    return {
        "code": "Ok",
        "durations": durations,
        "distances": distances,
    }


def test_matrix_batch_routes_through_osrm(
    client: TestClient, httpx_mock: HTTPXMock, monkeypatch: pytest.MonkeyPatch
) -> None:
    # Pin multipliers to 1.0 so this test stays focused on the data flow,
    # not the calibration. Calibration has its own dedicated tests below.
    monkeypatch.setenv("OSRM_URBAN_MULT", "1.0")
    monkeypatch.setenv("OSRM_SUBURBAN_MULT", "1.0")
    monkeypatch.setenv("OSRM_HIGHWAY_MULT", "1.0")

    httpx_mock.add_response(
        url=_OSRM_TABLE_URL,
        json=_ok_table(
            durations=[[120.4, 240.1], [60.7, 180.3]],
            distances=[[1000.0, 2000.0], [500.0, 1500.0]],
        ),
    )

    response = client.post(
        "/matrix/batch",
        json={
            "origins": [{"lat": 23.5, "lng": 58.5}, {"lat": 23.6, "lng": 58.6}],
            "destinations": [{"lat": 23.7, "lng": 58.7}, {"lat": 23.8, "lng": 58.8}],
        },
    )
    assert response.status_code == 200
    cells = response.json()["cells"]
    assert len(cells) == 4
    cells.sort(key=lambda c: (c["originIndex"], c["destIndex"]))
    assert cells[0] == {
        "originIndex": 0,
        "destIndex": 0,
        "durationSeconds": 120,
        "distanceMeters": 1000,
        "status": "ok",
    }
    assert cells[3]["durationSeconds"] == 180


def test_matrix_batch_null_cell_maps_to_zero_results(
    client: TestClient, httpx_mock: HTTPXMock
) -> None:
    # OSRM returns null when no route is possible (e.g. islanded coordinate).
    httpx_mock.add_response(
        url=_OSRM_TABLE_URL,
        json=_ok_table(durations=[[None]], distances=[[None]]),
    )

    response = client.post(
        "/matrix/batch",
        json={
            "origins": [{"lat": 23.5, "lng": 58.5}],
            "destinations": [{"lat": 24.0, "lng": 59.0}],
        },
    )
    assert response.status_code == 200
    cell = response.json()["cells"][0]
    assert cell["status"] == "zero_results"
    assert cell["durationSeconds"] is None


def test_matrix_batch_returns_503_when_osrm_unreachable(
    client: TestClient, httpx_mock: HTTPXMock
) -> None:
    # Safety rail: no Google fallback. OSRM down = hard 503. The whole point
    # of this design is that we can never accidentally bill Google again.
    httpx_mock.add_response(
        url=_OSRM_TABLE_URL,
        status_code=503,
        is_reusable=True,
    )

    response = client.post(
        "/matrix/batch",
        json={
            "origins": [{"lat": 23.0, "lng": 58.0}],
            "destinations": [{"lat": 24.0, "lng": 59.0}],
        },
    )
    assert response.status_code == 503
    detail = response.json()["detail"]
    assert "OSRM unreachable" in detail
    # Hint the user how to recover.
    assert "pnpm osrm:setup" in detail


def test_health_osrm_reports_reachability(
    client: TestClient, httpx_mock: HTTPXMock
) -> None:
    httpx_mock.add_response(
        url=_OSRM_NEAREST_URL,
        status_code=200,
        json={"code": "Ok"},
    )
    response = client.get("/health/osrm")
    assert response.status_code == 200
    body = response.json()
    assert body == {"reachable": True, "baseUrl": "http://osrm.test:5000"}


def test_health_osrm_reports_unreachable(
    client: TestClient, httpx_mock: HTTPXMock
) -> None:
    httpx_mock.add_response(
        url=_OSRM_NEAREST_URL,
        status_code=502,
        is_reusable=True,
    )
    response = client.get("/health/osrm")
    assert response.status_code == 200
    assert response.json()["reachable"] is False


def test_urban_calibration_inflates_short_legs(
    client: TestClient, httpx_mock: HTTPXMock
) -> None:
    # OSRM's free-flow times under-estimate urban legs (~40% on average per
    # the 2026-05-18 spot-check vs Google). The calibration applies a 1.4x
    # multiplier under 600s, 1.15x under 1800s, 1.0x otherwise. Verify all
    # three bands using one mocked /table response.
    httpx_mock.add_response(
        url=_OSRM_TABLE_URL,
        json=_ok_table(
            durations=[[100.0, 1200.0, 3000.0]],  # urban / suburban / highway
            distances=[[1000.0, 20000.0, 50000.0]],
        ),
    )

    response = client.post(
        "/matrix/batch",
        json={
            "origins": [{"lat": 23.5, "lng": 58.5}],
            "destinations": [
                {"lat": 23.51, "lng": 58.51},
                {"lat": 23.7, "lng": 58.7},
                {"lat": 24.5, "lng": 56.5},
            ],
        },
    )
    assert response.status_code == 200
    cells = sorted(response.json()["cells"], key=lambda c: c["destIndex"])
    # Urban: 100 * 1.4 = 140
    assert cells[0]["durationSeconds"] == 140
    # Suburban: 1200 * 1.15 = 1380
    assert cells[1]["durationSeconds"] == 1380
    # Highway: 3000 * 1.0 = 3000
    assert cells[2]["durationSeconds"] == 3000
    # Distances pass through unchanged.
    assert cells[0]["distanceMeters"] == 1000
    assert cells[2]["distanceMeters"] == 50000


def test_urban_calibration_env_overrides(
    client: TestClient, httpx_mock: HTTPXMock, monkeypatch: pytest.MonkeyPatch
) -> None:
    # Operators can disable the calibration with OSRM_URBAN_MULT=1.0 etc. — used
    # for A/B comparison against Google or when running tests that need raw
    # OSRM durations.
    monkeypatch.setenv("OSRM_URBAN_MULT", "1.0")
    monkeypatch.setenv("OSRM_SUBURBAN_MULT", "1.0")
    monkeypatch.setenv("OSRM_HIGHWAY_MULT", "1.0")

    httpx_mock.add_response(
        url=_OSRM_TABLE_URL,
        json=_ok_table(durations=[[123.0]], distances=[[1000.0]]),
    )
    response = client.post(
        "/matrix/batch",
        json={
            "origins": [{"lat": 23.5, "lng": 58.5}],
            "destinations": [{"lat": 23.51, "lng": 58.51}],
        },
    )
    assert response.status_code == 200
    assert response.json()["cells"][0]["durationSeconds"] == 123


@pytest.mark.asyncio
async def test_large_self_matrix_tiles_both_sides(
    httpx_mock: HTTPXMock, monkeypatch: pytest.MonkeyPatch
) -> None:
    """Regression: chunking used to split destinations only and keep the whole
    origins list in every call. A 400-point self-matrix (one Muscat-sized area
    group) then produced 400 calls of 401 coordinates each — over OSRM's URL
    limit, so the whole plan failed with a misleading "OSRM unreachable".
    Every call must now stay within the per-call point cap, and the cells must
    still carry absolute indices covering the full rectangle."""
    from sidecar.maps.osrm_matrix import _MAX_POINTS_PER_CALL, matrix_batch
    from sidecar.models import MatrixPoint

    n = 400
    points = [MatrixPoint(index=i, lat=23.5 + i * 0.001, lng=58.5 + i * 0.001) for i in range(n)]

    seen_point_counts: list[int] = []

    def _respond(request: Any) -> Any:
        import json as _json

        import httpx as _httpx

        coords = str(request.url).split("/driving/")[1].split("?")[0]
        count = len(coords.split(";"))
        seen_point_counts.append(count)
        sources = request.url.params["sources"].split(";")
        dests = request.url.params["destinations"].split(";")
        body = {
            "code": "Ok",
            "durations": [[60.0] * len(dests) for _ in sources],
            "distances": [[1000.0] * len(dests) for _ in sources],
        }
        return _httpx.Response(200, content=_json.dumps(body))

    httpx_mock.add_callback(_respond, url=_OSRM_TABLE_URL, is_reusable=True)

    cells = await matrix_batch(points, points)

    assert seen_point_counts, "expected the request to be chunked, not skipped"
    assert max(seen_point_counts) <= _MAX_POINTS_PER_CALL, (
        f"a call carried {max(seen_point_counts)} points, over the {_MAX_POINTS_PER_CALL} cap"
    )
    assert len(cells) == n * n
    assert {c.origin_index for c in cells} == set(range(n))
    assert {c.dest_index for c in cells} == set(range(n))


def test_osrm_toobig_is_not_reported_as_unreachable(
    client: TestClient, httpx_mock: HTTPXMock
) -> None:
    """Regression for the 2026-05-23 --max-table-size incident: OSRM answering
    400 TooBig means the server is healthy and rejected the request. Reporting
    it as 503 "OSRM unreachable — run pnpm osrm:setup" sent the user to
    reinstall a service that was working fine."""
    httpx_mock.add_response(
        url=_OSRM_TABLE_URL,
        status_code=400,
        json={"code": "TooBig", "message": "Too many table coordinates"},
    )
    resp = client.post(
        "/matrix/batch",
        json={
            "origins": [{"index": 0, "lat": 23.5, "lng": 58.5}],
            "destinations": [{"index": 1, "lat": 23.6, "lng": 58.6}],
        },
    )
    assert resp.status_code == 502
    detail = resp.json()["detail"]
    assert "too big" in detail.lower()
    assert "max-table-size" in detail
    assert "osrm:setup" not in detail


def test_calibration_is_monotonic_in_raw_duration() -> None:
    """Regression: the calibration used to STEP at the band edges, so a raw
    599 s leg calibrated to 839 s while a raw 600 s leg calibrated to 690 s.
    Cost was therefore non-monotonic in true drive time and the solver could
    be handed a cheaper number for a genuinely longer leg."""
    from sidecar.maps.osrm_matrix import _calibrate_duration, _multipliers

    mults = _multipliers()
    prev = -1
    for raw in range(0, 7201):
        value = _calibrate_duration(raw, mults)
        assert value >= prev, f"calibration fell at raw={raw}s ({prev} -> {value})"
        prev = value


def test_calibration_preserves_band_anchors() -> None:
    """The ramp only changes the TRANSITIONS between bands. At each band's
    anchor — where the 2026-05-18 spot-check sample is densest — the multiplier
    must still be exactly the calibrated one, or we would be silently
    re-baselining every drive estimate in the app."""
    from sidecar.maps.osrm_matrix import _calibrate_duration, _multipliers

    mults = _multipliers()
    urban, suburban, highway = mults
    assert _calibrate_duration(300, mults) == round(300 * urban)
    assert _calibrate_duration(1200, mults) == round(1200 * suburban)
    assert _calibrate_duration(2400, mults) == round(2400 * highway)
    assert _calibrate_duration(7200, mults) == round(7200 * highway)


def test_same_node_distance_sentinel_is_clamped() -> None:
    """OSRM answers -1 metres for a same-node pair. Clamping belongs at this
    boundary — leaving it to consumers put two poisoned rows into prod DBs and
    made the Excel export disagree with Analytics."""
    from sidecar.maps.osrm_matrix import _cells_from_payload

    payload = {"durations": [[0.0]], "distances": [[-1.0]]}
    cells = _cells_from_payload(payload, origin_indices=[7], dest_indices=[9])
    assert len(cells) == 1
    assert cells[0].distance_meters == 0
    assert cells[0].status == "ok"
