<script setup>
import { computed, onBeforeUnmount, onMounted, ref } from "vue";
import { Head } from "@inertiajs/vue3";
import axios from "axios";
import L from "leaflet";
import "leaflet/dist/leaflet.css";
import VueSelect from "vue-select";

const OMAN_BOUNDS = L.latLngBounds([16.0, 51.5], [27.0, 60.5]);
const DEFAULT_DATE = "2025-06-22";
const SPEED_OPTIONS = [50, 100, 300, 600, 1000, 2000];

const mapWrapperEl = ref(null);
const mapEl = ref(null);
const companies = ref([]);
const areas = ref([]);
const subareas = ref([]);
const routes = ref([]);
const selectedCompany = ref(null);
const selectedArea = ref(null);
const selectedSubarea = ref(null);
const selectedRoute = ref(null);
const selectedDate = ref(DEFAULT_DATE);
const speedMultiplier = ref(300);
const loading = ref(false);
const error = ref(null);
const track = ref(null);
const isFullscreen = ref(false);
const playbackState = ref("idle"); // idle | playing | paused | finished
const progressPercent = ref(0);
const currentSpeedKmh = ref(0);
const currentTime = ref(null);
const distanceCoveredMeters = ref(0);
const liveLog = ref([]);

const totalDistanceKm = computed(() => (track.value ? (track.value.distance_meters / 1000).toFixed(1) : "0.0"));
const distanceCoveredKm = computed(() => (distanceCoveredMeters.value / 1000).toFixed(1));
const totalDurationLabel = computed(() => (track.value ? formatDuration(track.value.duration_seconds) : "—"));
const elapsedDurationLabel = computed(() =>
    track.value ? formatDuration(Math.round((progressPercent.value / 100) * track.value.duration_seconds)) : "—",
);

let map = null;
let pathLayer = null;
let startMarker = null;
let endMarker = null;
let vehicleMarker = null;
let timeline = []; // [{ elapsedMs, lat, lng, speedKmh, time }]
let animationFrameId = null;
let wallClockAnchor = 0;
let elapsedMsAnchor = 0;
let lastBracketIndex = -1;

onMounted(async () => {
    map = L.map(mapEl.value, { maxBounds: OMAN_BOUNDS, maxBoundsViscosity: 1.0, minZoom: 6 }).setView([20.5, 56], 8);

    const streetLayer = L.tileLayer(
        "https://server.arcgisonline.com/ArcGIS/rest/services/World_Street_Map/MapServer/tile/{z}/{y}/{x}",
        { attribution: "&copy; Esri" },
    ).addTo(map);

    const satelliteLayer = L.tileLayer(
        "https://server.arcgisonline.com/ArcGIS/rest/services/World_Imagery/MapServer/tile/{z}/{y}/{x}",
        { attribution: "&copy; Esri" },
    );

    L.control.layers({ Street: streetLayer, Satellite: satelliteLayer }).addTo(map);

    const [routesRes, areasRes, companiesRes] = await Promise.all([
        axios.get("/route-replay/routes.json"),
        axios.get("/route-replay/areas.json"),
        axios.get("/route-replay/companies.json"),
    ]);
    routes.value = routesRes.data;
    areas.value = areasRes.data;
    companies.value = companiesRes.data;

    // Deep-link support: Route Location's "Replay" button sends a route + date
    // here via query params so the replay loads immediately on arrival.
    const params = new URLSearchParams(window.location.search);
    const linkedRoute = params.get("routecode");
    const linkedDate = params.get("date");

    if (linkedRoute && routes.value.some((route) => route.routecode === Number(linkedRoute))) {
        selectedRoute.value = Number(linkedRoute);
        if (linkedDate) {
            selectedDate.value = linkedDate;
        }
        loadReplay();
    }

    document.addEventListener("fullscreenchange", () => {
        isFullscreen.value = document.fullscreenElement === mapWrapperEl.value;
        setTimeout(() => map.invalidateSize(), 0);
    });
});

onBeforeUnmount(() => {
    if (animationFrameId) {
        cancelAnimationFrame(animationFrameId);
    }
});

async function onCompanyChange(companycode) {
    selectedArea.value = null;
    selectedSubarea.value = null;
    subareas.value = [];

    const { data } = await axios.get("/route-replay/areas.json", { params: { companycode } });
    areas.value = data;

    const { data: routeData } = await axios.get("/route-replay/routes.json", { params: { companycode } });
    routes.value = routeData;
    selectedRoute.value = null;
}

async function onAreaChange(areacode) {
    selectedSubarea.value = null;
    subareas.value = [];

    if (!areacode) {
        return;
    }

    const { data } = await axios.get("/route-replay/subareas.json", {
        params: { areacode, companycode: selectedCompany.value },
    });
    subareas.value = data;
}

async function onSubareaChange(subareacode) {
    const { data } = await axios.get("/route-replay/routes.json", {
        params: { subareacode, companycode: selectedCompany.value },
    });
    routes.value = data;
    selectedRoute.value = null;
}

function toggleFullscreen() {
    if (document.fullscreenElement) {
        document.exitFullscreen();
    } else {
        mapWrapperEl.value.requestFullscreen();
    }
}

function parseTime(value) {
    return new Date(value.replace(" ", "T")).getTime();
}

function formatDuration(totalSeconds) {
    const hours = Math.floor(totalSeconds / 3600);
    const minutes = Math.floor((totalSeconds % 3600) / 60);
    return `${hours}h ${minutes}m`;
}

function speedColor(kmh) {
    if (kmh >= 40) return "#16a34a"; // fast
    if (kmh >= 15) return "#eab308"; // moderate
    if (kmh >= 5) return "#f97316"; // slow
    return "#dc2626"; // stopped
}

function vehicleIcon() {
    return L.divIcon({
        className: "route-replay-vehicle-marker",
        html: `<div style="background:#4338ca;color:#fff;border-radius:50%;width:26px;height:26px;display:flex;align-items:center;justify-content:center;border:2px solid #fff;box-shadow:0 0 4px rgba(0,0,0,0.5)"><i class="fa fa-car-side" style="font-size:12px"></i></div>`,
        iconSize: [26, 26],
        iconAnchor: [13, 13],
    });
}

function flagIcon(color, label) {
    return L.divIcon({
        className: "route-replay-flag",
        html: `<div style="background:${color};color:#fff;border-radius:50%;width:22px;height:22px;display:flex;align-items:center;justify-content:center;font-size:11px;font-weight:bold;border:2px solid #fff;box-shadow:0 0 3px rgba(0,0,0,0.4)">${label}</div>`,
        iconSize: [22, 22],
        iconAnchor: [11, 11],
    });
}

async function loadReplay() {
    if (!selectedRoute.value || !selectedDate.value) {
        return;
    }

    stopAnimation();
    loading.value = true;
    error.value = null;
    track.value = null;
    liveLog.value = [];
    progressPercent.value = 0;
    distanceCoveredMeters.value = 0;
    currentSpeedKmh.value = 0;
    currentTime.value = null;

    if (pathLayer) {
        map.removeLayer(pathLayer);
        pathLayer = null;
    }
    [startMarker, endMarker, vehicleMarker].forEach((marker) => marker && map.removeLayer(marker));
    startMarker = endMarker = vehicleMarker = null;

    try {
        const { data } = await axios.get("/route-replay/track.json", {
            params: { routecode: selectedRoute.value, date: selectedDate.value },
        });
        track.value = data;

        const startMs = parseTime(data.start_time);
        let cumulativeDistance = 0;
        timeline = data.points.map((point, index) => {
            if (index > 0) {
                cumulativeDistance += haversineMeters(
                    data.points[index - 1].lat, data.points[index - 1].lng, point.lat, point.lng,
                );
            }
            return {
                elapsedMs: parseTime(point.time) - startMs,
                lat: point.lat,
                lng: point.lng,
                speedKmh: point.speed_kmh,
                time: point.time,
                cumulativeDistance,
            };
        });

        pathLayer = L.featureGroup().addTo(map);
        for (let i = 1; i < timeline.length; i++) {
            L.polyline(
                [[timeline[i - 1].lat, timeline[i - 1].lng], [timeline[i].lat, timeline[i].lng]],
                { color: speedColor(timeline[i].speedKmh), weight: 4 },
            ).addTo(pathLayer);
        }

        startMarker = L.marker([timeline[0].lat, timeline[0].lng], { icon: flagIcon("#16a34a", "S") })
            .bindPopup(`<strong>Start</strong><br>${timeline[0].time}`)
            .addTo(map);
        const lastPoint = timeline[timeline.length - 1];
        endMarker = L.marker([lastPoint.lat, lastPoint.lng], { icon: flagIcon("#dc2626", "E") })
            .bindPopup(`<strong>End</strong><br>${lastPoint.time}`)
            .addTo(map);
        vehicleMarker = L.marker([timeline[0].lat, timeline[0].lng], { icon: vehicleIcon() }).addTo(map);

        currentTime.value = timeline[0].time;

        const bounds = pathLayer.getBounds();
        if (bounds.isValid()) {
            map.fitBounds(bounds, { padding: [40, 40] });
        }
    } catch (e) {
        console.error(e);
        error.value = e.response?.data?.error || "Unable to load the GPS replay for this route.";
    } finally {
        loading.value = false;
    }
}

function haversineMeters(lat1, lng1, lat2, lng2) {
    const earthRadiusMeters = 6371000;
    const latDelta = ((lat2 - lat1) * Math.PI) / 180;
    const lngDelta = ((lng2 - lng1) * Math.PI) / 180;
    const a =
        Math.sin(latDelta / 2) ** 2 +
        Math.cos((lat1 * Math.PI) / 180) * Math.cos((lat2 * Math.PI) / 180) * Math.sin(lngDelta / 2) ** 2;
    return earthRadiusMeters * 2 * Math.atan2(Math.sqrt(a), Math.sqrt(1 - a));
}

function positionAtElapsed(elapsedMs) {
    if (!timeline.length) {
        return null;
    }

    if (elapsedMs <= 0) {
        return { ...timeline[0], bracketIndex: 0 };
    }

    const totalMs = timeline[timeline.length - 1].elapsedMs;
    if (elapsedMs >= totalMs) {
        return { ...timeline[timeline.length - 1], bracketIndex: timeline.length - 1 };
    }

    let lo = 0;
    let hi = timeline.length - 1;
    while (hi - lo > 1) {
        const mid = Math.floor((lo + hi) / 2);
        if (timeline[mid].elapsedMs <= elapsedMs) {
            lo = mid;
        } else {
            hi = mid;
        }
    }

    const a = timeline[lo];
    const b = timeline[hi];
    const span = b.elapsedMs - a.elapsedMs || 1;
    const ratio = (elapsedMs - a.elapsedMs) / span;

    return {
        lat: a.lat + (b.lat - a.lat) * ratio,
        lng: a.lng + (b.lng - a.lng) * ratio,
        speedKmh: b.speedKmh,
        time: a.time,
        cumulativeDistance: a.cumulativeDistance + (b.cumulativeDistance - a.cumulativeDistance) * ratio,
        bracketIndex: lo,
    };
}

function applyPosition(elapsedMs) {
    const totalMs = timeline[timeline.length - 1].elapsedMs;
    const position = positionAtElapsed(elapsedMs);
    if (!position) {
        return;
    }

    vehicleMarker.setLatLng([position.lat, position.lng]);
    progressPercent.value = totalMs > 0 ? Math.min(100, (elapsedMs / totalMs) * 100) : 100;
    currentSpeedKmh.value = position.speedKmh;
    currentTime.value = position.time;
    distanceCoveredMeters.value = position.cumulativeDistance;

    if (position.bracketIndex !== lastBracketIndex) {
        lastBracketIndex = position.bracketIndex;
        liveLog.value.unshift({ time: position.time, speed: position.speedKmh });
        if (liveLog.value.length > 8) {
            liveLog.value.pop();
        }
    }
}

function tick() {
    const totalMs = timeline[timeline.length - 1].elapsedMs;
    const elapsedMs = elapsedMsAnchor + (performance.now() - wallClockAnchor) * speedMultiplier.value;

    if (elapsedMs >= totalMs) {
        applyPosition(totalMs);
        playbackState.value = "finished";
        return;
    }

    applyPosition(elapsedMs);
    animationFrameId = requestAnimationFrame(tick);
}

function playAnimation() {
    if (!track.value || !timeline.length) {
        return;
    }

    if (playbackState.value === "finished") {
        elapsedMsAnchor = 0;
    } else {
        elapsedMsAnchor = (progressPercent.value / 100) * timeline[timeline.length - 1].elapsedMs;
    }

    wallClockAnchor = performance.now();
    playbackState.value = "playing";
    animationFrameId = requestAnimationFrame(tick);
}

function pauseAnimation() {
    if (animationFrameId) {
        cancelAnimationFrame(animationFrameId);
        animationFrameId = null;
    }
    playbackState.value = "paused";
}

function stopAnimation() {
    if (animationFrameId) {
        cancelAnimationFrame(animationFrameId);
        animationFrameId = null;
    }
    playbackState.value = "idle";
    progressPercent.value = 0;
    distanceCoveredMeters.value = 0;
    lastBracketIndex = -1;
    liveLog.value = [];

    if (timeline.length && vehicleMarker) {
        vehicleMarker.setLatLng([timeline[0].lat, timeline[0].lng]);
        currentSpeedKmh.value = 0;
        currentTime.value = timeline[0].time;
    }
}

function onScrub(event) {
    if (!timeline.length) {
        return;
    }

    const totalMs = timeline[timeline.length - 1].elapsedMs;
    const elapsedMs = (Number(event.target.value) / 100) * totalMs;

    applyPosition(elapsedMs);
    elapsedMsAnchor = elapsedMs;
    wallClockAnchor = performance.now();

    if (playbackState.value === "finished" && elapsedMs < totalMs) {
        playbackState.value = "paused";
    }
}

function resetFilters() {
    selectedCompany.value = null;
    selectedArea.value = null;
    selectedSubarea.value = null;
    subareas.value = [];
    selectedDate.value = DEFAULT_DATE;
}
</script>

<template>
    <Head title="Route Replay" />

    <div class="content route-replay-content">
        <div class="route-replay-page-heading">
            <h1 class="h3 fw-bold mb-1">Route Replay</h1>
            <h2 class="fs-base lh-base fw-medium text-muted mb-0">Animated playback of real recorded GPS history</h2>
        </div>

        <BaseBlock title="Filters" class="route-replay-filters">
            <div class="row align-items-end mb-3 g-3">
                <div class="col-md-4">
                    <label class="form-label">Company</label>
                    <VueSelect
                        v-model="selectedCompany"
                        :options="companies"
                        :reduce="(company) => company.cmpycode"
                        label="name"
                        placeholder="All companies..."
                        @update:model-value="onCompanyChange"
                    />
                </div>
                <div class="col-md-4">
                    <label class="form-label">Area</label>
                    <VueSelect
                        v-model="selectedArea"
                        :options="areas"
                        :reduce="(area) => area.areacode"
                        label="areaname"
                        placeholder="All areas..."
                        @update:model-value="onAreaChange"
                    />
                </div>
                <div class="col-md-4">
                    <label class="form-label">Sub Area</label>
                    <VueSelect
                        v-model="selectedSubarea"
                        :options="subareas"
                        :reduce="(subarea) => subarea.subareacode"
                        label="subareaname"
                        :disabled="!selectedArea"
                        placeholder="All sub areas..."
                        @update:model-value="onSubareaChange"
                    />
                </div>
            </div>
            <div class="row align-items-end g-3">
                <div class="col-md-4">
                    <label class="form-label">Route</label>
                    <VueSelect
                        v-model="selectedRoute"
                        :options="routes"
                        :reduce="(route) => route.routecode"
                        :get-option-label="(route) => `${route.routecode} - ${route.routename}`"
                        :filter-by="(route, label, search) => label.toLowerCase().includes(search.toLowerCase())"
                        placeholder="Select a route..."
                    />
                </div>
                <div class="col-md-4">
                    <label class="form-label">Operation Date</label>
                    <input v-model="selectedDate" type="date" class="form-control" />
                </div>
                <div class="col-md-4">
                    <button class="btn btn-primary w-100" :disabled="loading || !selectedRoute" @click="loadReplay">
                        {{ loading ? "..." : "Load Replay" }}
                    </button>
                </div>
            </div>
            <div class="row align-items-end g-3 mt-3">
                <div class="col-md-4">
                    <button class="btn btn-light w-100" :disabled="loading" @click="resetFilters">Reset</button>
                </div>
            </div>
        </BaseBlock>

        <BaseBlock title="Route Replay">
            <p v-if="error" class="text-danger">{{ error }}</p>

            <div v-if="track" class="row mb-3 g-3">
                <div class="col-md-3">
                    <div class="fw-bold text-primary">Total Distance</div>
                    <div>{{ totalDistanceKm }} km</div>
                </div>
                <div class="col-md-3">
                    <div class="fw-bold text-primary">Recorded Duration</div>
                    <div>{{ totalDurationLabel }}</div>
                </div>
                <div class="col-md-3">
                    <div class="fw-bold text-primary">GPS Points</div>
                    <div>{{ track.point_count }}</div>
                </div>
                <div class="col-md-3">
                    <div class="fw-bold text-primary">Recorded Window</div>
                    <div class="small">{{ track.start_time }} &rarr; {{ track.end_time }}</div>
                </div>
            </div>

            <div v-if="track" class="route-replay-controls d-flex align-items-center gap-2 mb-3 flex-wrap">
                <button
                    type="button"
                    class="btn btn-success"
                    :disabled="playbackState === 'playing'"
                    @click="playAnimation"
                >
                    <i class="fa fa-play"></i> {{ playbackState === "paused" || playbackState === "finished" ? "Resume" : "Start" }}
                </button>
                <button type="button" class="btn btn-warning" :disabled="playbackState !== 'playing'" @click="pauseAnimation">
                    <i class="fa fa-pause"></i> Pause
                </button>
                <button type="button" class="btn btn-outline-danger" @click="stopAnimation">
                    <i class="fa fa-stop"></i> Stop
                </button>
                <label class="form-label mb-0 ms-2 small text-muted">Speed</label>
                <select v-model.number="speedMultiplier" class="form-select form-select-sm" style="width: auto">
                    <option v-for="option in SPEED_OPTIONS" :key="option" :value="option">{{ option }}x</option>
                </select>
                <span class="badge bg-light text-dark border ms-auto">{{ playbackState }}</span>
            </div>

            <div v-if="track" class="route-replay-scrubber mb-3">
                <input
                    type="range"
                    class="form-range"
                    min="0"
                    max="100"
                    step="0.1"
                    :value="progressPercent"
                    @input="onScrub"
                />
                <div class="d-flex justify-content-between text-muted small">
                    <span>{{ elapsedDurationLabel }}</span>
                    <span>{{ progressPercent.toFixed(1) }}%</span>
                    <span>{{ totalDurationLabel }}</span>
                </div>
            </div>

            <div class="row g-3">
                <div class="col-md-8">
                    <div ref="mapWrapperEl" class="route-replay-map-wrapper" style="position: relative; height: 600px; width: 100%">
                        <button
                            type="button"
                            class="btn btn-light route-replay-fullscreen-btn"
                            :title="isFullscreen ? 'Exit fullscreen' : 'Fullscreen'"
                            @click="toggleFullscreen"
                        >
                            <i :class="isFullscreen ? 'fa fa-compress' : 'fa fa-expand'"></i>
                        </button>
                        <div ref="mapEl" style="height: 100%; width: 100%"></div>
                    </div>
                </div>
                <div class="col-md-4 d-flex flex-column gap-3">
                    <div class="card route-replay-status-card">
                        <div class="card-header"><strong>Live Status</strong></div>
                        <div class="card-body">
                            <p v-if="!track" class="text-muted small mb-0">Load a replay to see live status.</p>
                            <template v-else>
                                <div class="route-replay-status-row">
                                    <span class="text-muted small">Status</span>
                                    <span class="fw-semibold">{{ playbackState }}</span>
                                </div>
                                <div class="route-replay-status-row">
                                    <span class="text-muted small">Speed</span>
                                    <span class="fw-semibold">{{ currentSpeedKmh.toFixed(1) }} km/h</span>
                                </div>
                                <div class="route-replay-status-row">
                                    <span class="text-muted small">Progress</span>
                                    <span class="fw-semibold">{{ progressPercent.toFixed(1) }}%</span>
                                </div>
                                <div class="route-replay-status-row">
                                    <span class="text-muted small">Recorded Time</span>
                                    <span class="fw-semibold">{{ currentTime }}</span>
                                </div>
                                <div class="route-replay-status-row">
                                    <span class="text-muted small">Distance Covered</span>
                                    <span class="fw-semibold">{{ distanceCoveredKm }} / {{ totalDistanceKm }} km</span>
                                </div>
                            </template>
                        </div>
                    </div>
                    <div class="card route-replay-log-card flex-fill">
                        <div class="card-header"><strong>Live Log</strong></div>
                        <div class="card-body p-2">
                            <div class="route-replay-log-list">
                                <p v-if="!liveLog.length" class="text-muted small px-1">No playback activity yet.</p>
                                <div v-for="(entry, index) in liveLog" :key="index" class="route-replay-log-entry">
                                    <span class="route-replay-log-time">{{ entry.time }}</span>
                                    <span class="route-replay-log-message">speed {{ entry.speed.toFixed(1) }} km/h</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </BaseBlock>
    </div>
</template>

<style lang="scss">
@import "vue-select/dist/vue-select.css";
@import "@scss/vendor/vue-select";

// Rendered directly inside .route-replay-content, so it already inherits
// that div's 0.5rem side padding. The extra 1.25rem here matches the
// "FILTERS" label's own block-header inset, so both texts line up exactly.
.route-replay-page-heading {
    padding: 0.75rem 0 0.75rem 1.25rem;
}

// Wider than the default page content padding — this map-heavy page benefits
// from extra horizontal room; scoped to this page only via .route-replay-content.
.route-replay-content {
    width: 100% !important;
    max-width: 100% !important;
    margin-left: 0 !important;
    margin-right: 0 !important;
    padding-left: 0.5rem !important;
    padding-right: 0.5rem !important;
    padding-bottom: 1.875rem !important;

    > .block > .block-content {
        padding-bottom: 1.25rem !important;
    }
}

.route-replay-map-wrapper:fullscreen {
    height: 100vh !important;
}

.route-replay-fullscreen-btn {
    position: absolute;
    bottom: 10px;
    left: 10px;
    z-index: 1000;
    box-shadow: 0 1px 4px rgba(0, 0, 0, 0.3);
}

.route-replay-status-card,
.route-replay-log-card {
    display: flex;
    flex-direction: column;
}

.route-replay-status-row {
    display: flex;
    justify-content: space-between;
    padding: 0.35rem 0;
    border-bottom: 1px solid #f1f3f5;

    &:last-child {
        border-bottom: none;
    }
}

.route-replay-log-card .card-body {
    display: flex;
    flex-direction: column;
    min-height: 0;
    flex: 1;
}

.route-replay-log-list {
    overflow-y: auto;
    max-height: 220px;
}

.route-replay-log-entry {
    display: flex;
    justify-content: space-between;
    gap: 8px;
    padding: 0.3rem 0.5rem;
    font-size: 0.75rem;
    border-bottom: 1px solid #f1f3f5;

    &:last-child {
        border-bottom: none;
    }
}

.route-replay-log-time {
    color: #6c757d;
    flex-shrink: 0;
}

.route-replay-log-message {
    color: #16a34a;
    font-weight: 600;
}
</style>
