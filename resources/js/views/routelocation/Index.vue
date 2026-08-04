<script setup>
import { computed, onMounted, ref } from "vue";
import { Head } from "@inertiajs/vue3";
import axios from "axios";
import L from "leaflet";
import "leaflet/dist/leaflet.css";
import VueSelect from "vue-select";

const OMAN_BOUNDS = L.latLngBounds([16.0, 51.5], [27.0, 60.5]);
const DEFAULT_DATE = "2025-06-22";

const mapWrapperEl = ref(null);
const mapEl = ref(null);
const companies = ref([]);
const areas = ref([]);
const subareas = ref([]);
const selectedCompany = ref(null);
const selectedArea = ref(null);
const selectedSubarea = ref(null);
const selectedDate = ref(DEFAULT_DATE);
const loading = ref(false);
const error = ref(null);
const locations = ref([]);
const isFullscreen = ref(false);
const routeListSearch = ref("");

const filteredLocations = computed(() => {
    const search = routeListSearch.value.trim().toLowerCase();

    if (!search) {
        return locations.value;
    }

    return locations.value.filter(
        (route) =>
            String(route.routecode).includes(search) ||
            (route.routename ?? "").toLowerCase().includes(search) ||
            (route.salesmanname ?? "").toLowerCase().includes(search),
    );
});

let map = null;
let markersLayer = null;
const routeMarkers = {};

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

    markersLayer = L.layerGroup().addTo(map);

    const [areasRes, companiesRes] = await Promise.all([
        axios.get("/route-location/areas.json"),
        axios.get("/route-location/companies.json"),
    ]);
    areas.value = areasRes.data;
    companies.value = companiesRes.data;

    document.addEventListener("fullscreenchange", () => {
        isFullscreen.value = document.fullscreenElement === mapWrapperEl.value;
        setTimeout(() => map.invalidateSize(), 0);
    });

    showAllLocations();
});

async function onCompanyChange(companycode) {
    selectedArea.value = null;
    selectedSubarea.value = null;
    subareas.value = [];

    const { data } = await axios.get("/route-location/areas.json", { params: { companycode } });
    areas.value = data;
}

async function onAreaChange(areacode) {
    selectedSubarea.value = null;
    subareas.value = [];

    if (!areacode) {
        return;
    }

    const { data } = await axios.get("/route-location/subareas.json", {
        params: { areacode, companycode: selectedCompany.value },
    });
    subareas.value = data;
}

function toggleFullscreen() {
    if (document.fullscreenElement) {
        document.exitFullscreen();
    } else {
        mapWrapperEl.value.requestFullscreen();
    }
}

function locationIcon() {
    return L.divIcon({
        className: "route-location-marker",
        html: `<div style="background:#10b981;color:#fff;border-radius:50%;width:24px;height:24px;display:flex;align-items:center;justify-content:center;border:2px solid #fff;box-shadow:0 0 3px rgba(0,0,0,0.4)"><i class="fa fa-user" style="font-size:11px"></i></div>`,
        iconSize: [24, 24],
        iconAnchor: [12, 12],
    });
}

async function showAllLocations() {
    if (!selectedDate.value) {
        return;
    }

    loading.value = true;
    error.value = null;
    routeListSearch.value = "";
    markersLayer.clearLayers();
    Object.keys(routeMarkers).forEach((key) => delete routeMarkers[key]);

    try {
        const { data } = await axios.get("/route-location/last-locations.json", {
            params: {
                date: selectedDate.value,
                companycode: selectedCompany.value,
                areacode: selectedArea.value,
                subareacode: selectedSubarea.value,
            },
        });
        locations.value = data;

        if (!data.length) {
            error.value = "No GPS locations recorded for the selected filters on this date.";
            return;
        }

        const markerLatLngs = [];

        data.forEach((point) => {
            const marker = L.marker([point.lat, point.lng], { icon: locationIcon() }).bindPopup(
                `<div class="route-location-popup">
                    <strong>${point.routecode} - ${point.routename ?? ""}</strong>
                    <a href="${trackUrl(point.routecode)}" class="route-location-popup-track-btn" title="Track in Route Tracking">
                        <i class="fa fa-route"></i>
                    </a>
                    <br>Salesman: ${point.salesmanname ?? "N/A"}<br>Last known location at ${point.time ?? ""}
                </div>`,
            );
            markersLayer.addLayer(marker);
            routeMarkers[point.routecode] = marker;
            markerLatLngs.push([point.lat, point.lng]);
        });

        map.fitBounds(L.latLngBounds(markerLatLngs), { padding: [40, 40], maxZoom: 13 });
    } catch (e) {
        console.error(e);
        error.value = e.response?.data?.error || "Unable to load route locations.";
    } finally {
        loading.value = false;
    }
}

function trackUrl(routecode) {
    return `/route-tracking?routecode=${routecode}&date=${selectedDate.value}`;
}

function replayUrl(routecode) {
    return `/route-replay?routecode=${routecode}&date=${selectedDate.value}`;
}

function focusRoute(routecode) {
    const marker = routeMarkers[routecode];
    if (!marker) {
        return;
    }

    map.setView(marker.getLatLng(), 15);
    marker.openPopup();
}

function resetFilters() {
    selectedCompany.value = null;
    selectedArea.value = null;
    selectedSubarea.value = null;
    subareas.value = [];
    selectedDate.value = DEFAULT_DATE;
    showAllLocations();
}
</script>

<template>
    <Head title="Route Location" />

    <div class="content route-location-content">
        <div class="route-location-page-heading">
            <h1 class="h3 fw-bold mb-1">Route Location</h1>
            <h2 class="fs-base lh-base fw-medium text-muted mb-0">Last known GPS position for every route</h2>
        </div>

        <BaseBlock title="Filters" class="route-location-filters">
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
                    />
                </div>
            </div>
            <div class="row align-items-end g-3">
                <div class="col-md-4">
                    <label class="form-label">Operation Date</label>
                    <input v-model="selectedDate" type="date" class="form-control" />
                </div>
                <div class="col-md-4">
                    <button class="btn btn-primary w-100" :disabled="loading" @click="showAllLocations">
                        {{ loading ? "..." : "Apply" }}
                    </button>
                </div>
                <div class="col-md-4">
                    <button class="btn btn-light w-100" :disabled="loading" @click="resetFilters">Reset</button>
                </div>
            </div>
        </BaseBlock>

        <BaseBlock title="Route Location">
            <p v-if="error" class="text-danger">{{ error }}</p>
            <p v-else-if="locations.length" class="text-muted small">Showing {{ locations.length }} route(s) on {{ selectedDate }}</p>

            <div class="row g-3">
                <div class="col-md-8">
                    <div ref="mapWrapperEl" class="route-location-map-wrapper" style="position: relative; height: 600px; width: 100%">
                        <button
                            type="button"
                            class="btn btn-light route-location-fullscreen-btn"
                            :title="isFullscreen ? 'Exit fullscreen' : 'Fullscreen'"
                            @click="toggleFullscreen"
                        >
                            <i :class="isFullscreen ? 'fa fa-compress' : 'fa fa-expand'"></i>
                        </button>
                        <div ref="mapEl" style="height: 100%; width: 100%"></div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="card route-location-route-list-card" style="height: 600px">
                        <div class="card-header">
                            <strong>Routes</strong>
                            <span class="text-muted small">({{ locations.length }})</span>
                        </div>
                        <div class="route-location-route-search p-2">
                            <input
                                v-model="routeListSearch"
                                type="text"
                                class="form-control form-control-sm"
                                placeholder="Search route code, name or salesman..."
                                :disabled="!locations.length"
                            />
                        </div>
                        <div class="card-body p-2">
                            <div class="route-location-route-list">
                                <p v-if="!locations.length" class="text-muted small px-1">No routes to show.</p>
                                <p v-else-if="!filteredLocations.length" class="text-muted small px-1">No routes match.</p>
                                <div
                                    v-for="route in filteredLocations"
                                    :key="route.routecode"
                                    class="list-group-item list-group-item-action route-location-route-item"
                                    role="button"
                                    tabindex="0"
                                    @click="focusRoute(route.routecode)"
                                >
                                    <span class="route-location-route-dot"><i class="fa fa-user"></i></span>
                                    <span class="route-location-route-info">
                                        <span class="d-block fw-semibold small">{{ route.routecode }} - {{ route.routename }}</span>
                                        <span class="d-block text-muted small">{{ route.salesmanname || "N/A" }}</span>
                                        <span class="d-block text-muted small">Last seen at {{ route.time }}</span>
                                    </span>
                                    <a
                                        :href="trackUrl(route.routecode)"
                                        class="route-location-track-btn"
                                        title="Track in Route Tracking"
                                        @click.stop
                                    >
                                        <i class="fa fa-route"></i>
                                    </a>
                                    <a
                                        :href="replayUrl(route.routecode)"
                                        class="route-location-replay-btn"
                                        title="Replay in Route Replay"
                                        @click.stop
                                    >
                                        <i class="fa fa-play"></i>
                                    </a>
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

// Rendered directly inside .route-location-content, so it already inherits
// that div's 0.5rem side padding. The extra 1.25rem here matches the
// "FILTERS" label's own block-header inset, so both texts line up exactly.
.route-location-page-heading {
    padding: 0.75rem 0 0.75rem 1.25rem;
}

// Wider than the default page content padding — this map-heavy page benefits
// from extra horizontal room; scoped to this page only via .route-location-content.
.route-location-content {
    width: 100% !important;
    max-width: 100% !important;
    margin-left: 0 !important;
    margin-right: 0 !important;
    padding-left: 0.5rem !important;
    padding-right: 0.5rem !important;
    padding-bottom: 1.875rem !important;

    // The shared .block-content mixin sets only 1px of bottom padding (its
    // 3-value padding shorthand is top/sides/bottom, not top/sides/bottom-equal),
    // so each card's bottom inset is far smaller than its top inset. Equalize
    // them here rather than touching the shared mixin used by every other page.
    > .block > .block-content {
        padding-bottom: 1.25rem !important;
    }
}

.route-location-map-wrapper:fullscreen {
    height: 100vh !important;
}

.route-location-fullscreen-btn {
    position: absolute;
    bottom: 10px;
    left: 10px;
    z-index: 1000;
    box-shadow: 0 1px 4px rgba(0, 0, 0, 0.3);
}

.route-location-route-list-card {
    display: flex;
    flex-direction: column;
}

.route-location-route-search {
    border-bottom: 1px solid #e5e7eb;
}

.route-location-route-list-card .card-body {
    display: flex;
    flex-direction: column;
    min-height: 0;
    flex: 1;
}

.route-location-route-list {
    overflow-y: auto;
    flex: 1;
}

.route-location-route-item {
    display: flex;
    align-items: center;
    gap: 8px;
    text-align: left;
}

.route-location-route-dot {
    flex-shrink: 0;
    background: #10b981;
    color: #fff;
    border-radius: 50%;
    width: 22px;
    height: 22px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 11px;
}

.route-location-route-info {
    min-width: 0;
    overflow: hidden;
}

.route-location-route-info span {
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}

.route-location-track-btn {
    flex-shrink: 0;
    display: flex;
    align-items: center;
    justify-content: center;
    width: 28px;
    height: 28px;
    border-radius: 50%;
    background: #eef2ff;
    color: #4338ca;

    &:hover {
        background: #4338ca;
        color: #fff;
    }
}

.route-location-replay-btn {
    flex-shrink: 0;
    display: flex;
    align-items: center;
    justify-content: center;
    width: 28px;
    height: 28px;
    border-radius: 50%;
    background: #ecfdf5;
    color: #047857;

    &:hover {
        background: #047857;
        color: #fff;
    }
}

// Leaflet popup content is raw HTML (not Vue-rendered), so these styles target
// the .route-location-popup-track-btn class directly rather than scoped attrs.
.route-location-popup-track-btn {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 22px;
    height: 22px;
    border-radius: 50%;
    background: #eef2ff;
    color: #4338ca;
    margin-left: 6px;
    vertical-align: middle;

    &:hover {
        background: #4338ca;
        color: #fff;
    }
}
</style>
