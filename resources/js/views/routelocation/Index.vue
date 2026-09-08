<script setup>
import { computed, onMounted, ref, watch } from "vue";
import { Head } from "@inertiajs/vue3";
import axios from "axios";
import L from "leaflet";
import "leaflet/dist/leaflet.css";
import VueSelect from "vue-select";
import DashboardCards from "./DashboardCards.vue";
import { filterFields, filterOptions, dateRangeForPreset, dateRangeError } from "./filters";

const OMAN_BOUNDS = L.latLngBounds([16.0, 51.5], [27.0, 60.5]);
const now = new Date();
const DEFAULT_DATE = new Date(now.getTime() - now.getTimezoneOffset() * 60_000).toISOString().slice(0, 10);

const mapWrapperEl = ref(null);
const mapEl = ref(null);
const filterRows = ref([]);
const selected = ref(Object.fromEntries(filterFields.map(({ key }) => [key, []])));
const filtersReady = ref(false);
const options = computed(() => Object.fromEntries(filterFields.map((field) => [
    field.key, filterOptions(filterRows.value, selected.value, field),
])));
const datePreset = ref("today");
const fromDate = ref(DEFAULT_DATE);
const toDate = ref(DEFAULT_DATE);
const dateError = computed(() => dateRangeError(fromDate.value, toDate.value));
const loading = ref(false);
const metrics = ref(null);
const metricsLoading = ref(true);
const metricsError = ref(null);
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
let locationRequest = 0;

watch([selected, fromDate, toDate], () => {
    if (filtersReady.value) showAllLocations();
}, { deep: true });

function applyDatePreset() {
    if (datePreset.value === "custom") return;
    const today = new Date();
    const localDate = new Date(today.getTime() - today.getTimezoneOffset() * 60_000).toISOString().slice(0, 10);
    const range = dateRangeForPreset(datePreset.value, localDate);
    fromDate.value = range.from;
    toDate.value = range.to;
}

onMounted(async () => {
    map = L.map(mapEl.value, { maxBounds: OMAN_BOUNDS, maxBoundsViscosity: 1.0, minZoom: 6 }).setView([20.5, 56], 8);
    map.attributionControl.setPrefix("Maps powered by Towell-TAKE Solutions LLC");

    L.tileLayer("https://tile.openstreetmap.org/{z}/{x}/{y}.png", {
        maxZoom: 19,
        attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors',
    }).addTo(map);

    markersLayer = L.layerGroup().addTo(map);

    try {
        const { data } = await axios.get("/dashboard/filters.json");
        filterRows.value = data;
        filtersReady.value = true;
    } catch {
        error.value = "Unable to load Dashboard filters. Refresh the page to retry.";
        metricsLoading.value = false;
        metricsError.value = "Unable to load dashboard filters.";
    }

    document.addEventListener("fullscreenchange", () => {
        isFullscreen.value = document.fullscreenElement === mapWrapperEl.value;
        setTimeout(() => map.invalidateSize(), 0);
    });

    if (filtersReady.value) showAllLocations();
});

function toggleFullscreen() {
    if (document.fullscreenElement) {
        document.exitFullscreen();
    } else {
        mapWrapperEl.value.requestFullscreen();
    }
}

function locationIcon(closed) {
    return L.divIcon({
        className: "route-location-marker",
        html: `<div class="route-location-marker-dot ${closed ? "closed" : "live"}"><i class="fa fa-user"></i></div>`,
        iconSize: [24, 24],
        iconAnchor: [12, 12],
    });
}

async function showAllLocations() {
    const request = ++locationRequest;
    metrics.value = null;
    metricsError.value = null;
    locations.value = [];
    markersLayer.clearLayers();
    Object.keys(routeMarkers).forEach((key) => delete routeMarkers[key]);
    error.value = null;
    if (dateError.value) {
        loading.value = false;
        metricsLoading.value = false;
        return;
    }

    loading.value = true;
    error.value = null;
    routeListSearch.value = "";
    loadMetrics(request);

    try {
        const { data } = await axios.get("/dashboard/last-locations.json", {
            params: {
                from_date: fromDate.value,
                to_date: toDate.value,
                ...selected.value,
            },
        });
        if (request !== locationRequest) return;
        locations.value = data;

        if (!data.length) {
            error.value = "No GPS locations found for journeys started within the selected date range.";
            return;
        }

        const markerLatLngs = [];

        data.forEach((point) => {
            const marker = L.marker([point.lat, point.lng], { icon: locationIcon(point.closed) }).bindPopup(
                `<div class="route-location-popup">
                    <strong>${point.routecode} - ${point.routename ?? ""}</strong>
                    <a href="${trackUrl(point.routecode, point.route_date)}" class="route-location-popup-track-btn" title="Track in Route Tracking">
                        <i class="fa fa-route"></i>
                    </a>
                    <br>Status: <strong style="color:${point.closed ? "#dc2626" : "#10b981"}">${point.status}</strong>
                    <br>Route Start: ${point.route_start_time ?? "Not Available"}
                    <br>Route End: ${point.route_end_time ?? "Not Available"}
                    <br>Salesman: ${point.salesmanname ?? "N/A"}<br>Last known location at ${point.time ?? ""}
                </div>`,
            );
            markersLayer.addLayer(marker);
            routeMarkers[point.routecode] = marker;
            markerLatLngs.push([point.lat, point.lng]);
        });

        map.fitBounds(L.latLngBounds(markerLatLngs), { padding: [40, 40], maxZoom: 13 });
    } catch (e) {
        if (request !== locationRequest) return;
        console.error(e);
        error.value = e.response?.data?.error || "Unable to load route locations.";
    } finally {
        if (request === locationRequest) loading.value = false;
    }
}

async function loadMetrics(request) {
    metricsLoading.value = true;
    try {
        const { data } = await axios.get("/dashboard/metrics.json", {
            params: { from_date: fromDate.value, to_date: toDate.value, ...selected.value },
        });
        if (request === locationRequest) metrics.value = data;
    } catch {
        if (request === locationRequest) metricsError.value = "Unable to load the overview figures.";
    } finally {
        if (request === locationRequest) metricsLoading.value = false;
    }
}

function trackUrl(routecode, routeDate) {
    return `/route-tracking?routecode=${routecode}&date=${routeDate}`;
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
    selected.value = Object.fromEntries(filterFields.map(({ key }) => [key, []]));
    datePreset.value = "today";
    applyDatePreset();
    locations.value = [];
    routeListSearch.value = "";
    markersLayer.clearLayers();
    Object.keys(routeMarkers).forEach((key) => delete routeMarkers[key]);
    map.setView([20.5, 56], 8);
}
</script>

<template>
    <Head title="Dashboard" />

    <div class="content route-location-content">
        <div class="route-location-page-heading">
            <h1 class="h3 fw-bold mb-1">Dashboard</h1>
            <h2 class="fs-base lh-base fw-medium text-muted mb-0">Last known GPS position for every route</h2>
        </div>

        <section class="dashboard-filters" aria-labelledby="dashboard-filters-title">
            <header class="dashboard-filters-header">
                <div class="dashboard-filters-heading">
                    <span class="dashboard-filter-icon"><i class="fa fa-sliders" aria-hidden="true"></i></span>
                    <div>
                        <h2 id="dashboard-filters-title">Explore your operations</h2>
                        <p>Choose your teams and reporting period.</p>
                    </div>
                </div>
                <div class="dashboard-filter-actions">
                    <button type="button" class="dashboard-reset" :disabled="loading" @click="resetFilters">Reset filters</button>
                    <button type="button" class="dashboard-refresh" :disabled="loading || !filtersReady || !!dateError" @click="showAllLocations">
                        <i class="fa fa-arrow-rotate-right" :class="{ 'fa-spin': loading }" aria-hidden="true"></i>
                        {{ loading ? "Refreshing..." : "Refresh" }}
                    </button>
                </div>
            </header>

            <div class="dashboard-scope-grid">
                <div v-for="field in filterFields" :key="field.key" class="dashboard-filter-field">
                    <label :for="`dashboard-${field.key}`">
                        {{ field.label }}
                        <span v-if="selected[field.key].length" class="dashboard-selection-count">{{ selected[field.key].length }}</span>
                    </label>
                    <VueSelect
                        :input-id="`dashboard-${field.key}`"
                        v-model="selected[field.key]"
                        :options="options[field.key]"
                        :reduce="(option) => option.value"
                        label="label"
                        multiple
                        :close-on-select="false"
                        :disabled="!filtersReady"
                        :placeholder="`All ${field.label.toLowerCase()}...`"
                    />
                </div>
            </div>

            <div class="dashboard-period-panel">
                <div class="dashboard-period-heading">
                    <span><i class="fa-regular fa-calendar" aria-hidden="true"></i> Reporting period</span>
                    <div class="dashboard-date-presets" role="group" aria-label="Date range presets">
                        <button
                            v-for="preset in [{ value: 'today', label: 'Today' }, { value: 'yesterday', label: 'Yesterday' }, { value: 'week', label: 'This week' }, { value: 'month', label: 'This month' }, { value: 'custom', label: 'Custom' }]"
                            :key="preset.value"
                            type="button"
                            :aria-pressed="datePreset === preset.value"
                            :class="{ active: datePreset === preset.value }"
                            @click="datePreset = preset.value; applyDatePreset()"
                        >{{ preset.label }}</button>
                    </div>
                </div>
                <div class="dashboard-date-grid">
                    <div class="dashboard-range-inputs">
                        <div class="dashboard-filter-field">
                            <label for="dashboard-from">From date</label>
                            <input id="dashboard-from" v-model="fromDate" type="date" :max="toDate || undefined" @input="datePreset = 'custom'" />
                        </div>
                        <span class="dashboard-date-arrow" aria-hidden="true">&rarr;</span>
                        <div class="dashboard-filter-field">
                            <label for="dashboard-to">To date</label>
                            <input id="dashboard-to" v-model="toDate" type="date" :min="fromDate || undefined" @input="datePreset = 'custom'" />
                        </div>
                    </div>
                    <div class="dashboard-map-date">
                        <p><strong>Filtered by route start date</strong><br>Latest journey per route within the selected range.</p>
                    </div>
                </div>
                <p v-if="dateError" class="dashboard-date-error" role="alert">{{ dateError }}</p>
            </div>
        </section>

        <DashboardCards :metrics="metrics" :loading="metricsLoading" :error="metricsError" />

        <BaseBlock title="Dashboard" :mode-loading="loading">
            <p v-if="error" class="text-danger">{{ error }}</p>
            <p v-else-if="locations.length" class="text-muted small">Showing {{ locations.length }} route(s) started from {{ fromDate }} to {{ toDate }}</p>

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
                                    <span class="route-location-route-dot" :class="route.closed ? 'closed' : 'live'">
                                        <i class="fa fa-user"></i>
                                    </span>
                                    <span class="route-location-route-info">
                                        <span class="d-block fw-semibold small">{{ route.routecode }} - {{ route.routename }}</span>
                                        <span class="d-block text-muted small">{{ route.salesmanname || "N/A" }}</span>
                                        <span class="d-block small" :class="route.closed ? 'text-danger' : 'text-success'">
                                            {{ route.status }}
                                        </span>
                                        <span class="d-block text-muted small">Last seen at {{ route.time }}</span>
                                    </span>
                                    <a
                                        :href="trackUrl(route.routecode, route.route_date)"
                                        class="route-location-track-btn"
                                        title="Track in Route Tracking"
                                        @click.stop
                                    >
                                        <i class="fa fa-route"></i>
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

.dashboard-filters {
    background: #fff;
    border: 1px solid #e2e8f0;
    border-radius: 16px;
    margin-bottom: 24px;
    color: #172b45;
    box-shadow: 0 4px 20px rgba(23, 43, 69, 0.04);

    button, input, .vs__dropdown-toggle { transition: border-color 0.15s, background-color 0.15s; }
    button:focus-visible, input:not(.vs__search):focus-visible { outline: 3px solid #93c5fd; outline-offset: 3px; }
    button:disabled { opacity: 0.5; cursor: not-allowed; }
    button { font: inherit; cursor: pointer; }
    .vs__dropdown-toggle { min-height: 44px; border: 1px solid #d7e0e9; border-radius: 8px; background: #fff; padding: 4px 7px; }
    .vs--open .vs__dropdown-toggle, .vs__dropdown-toggle:focus-within { border-color: #2563eb; box-shadow: 0 0 0 3px #eff6ff; }
    .vs__selected { background: #eff6ff; border: 0; border-radius: 5px; color: #1e40af; font-size: 12px; max-width: 100%; overflow-wrap: anywhere; }
    .vs__selected-options { min-width: 0; }
    .vs__search { min-width: 0; font-size: 13px; color: #52657b; }
    .vs__dropdown-menu { border: 1px solid #d7e0e9; border-radius: 8px; box-shadow: 0 8px 24px #172b451a; z-index: 1100; }
    .vs__dropdown-option { white-space: normal; font-size: 13px; padding: 9px 12px; }
    .vs__dropdown-option--highlight { background: #eff6ff; color: #1d4ed8; }
}
.dashboard-filters-header, .dashboard-filters-heading, .dashboard-filter-actions, .dashboard-period-heading { display: flex; align-items: center; justify-content: space-between; gap: 16px; }
.dashboard-filters-header { padding: 22px 24px; }
.dashboard-filters-heading { justify-content: flex-start; }
.dashboard-filters-heading h2 { margin: 0 0 4px; font-size: 17px; font-weight: 700; letter-spacing: -0.3px; }
.dashboard-filters-heading p { margin: 0; color: #64748b; font-size: 13px; }
.dashboard-filter-icon { display: grid; place-items: center; width: 42px; height: 42px; flex-shrink: 0; border-radius: 12px; background: #eff6ff; color: #2563eb; }
.dashboard-filter-actions button { border-radius: 8px; padding: 10px 15px; font-size: 13px; font-weight: 600; white-space: nowrap; }
.dashboard-reset { border: 1px solid transparent; color: #52657b; background: transparent; }
.dashboard-reset:hover { background: #f1f5f9; }
.dashboard-refresh { border: 1px solid #172b45; background: #172b45; color: #fff; }
.dashboard-refresh:hover { background: #274467; }
.dashboard-refresh i { margin-right: 7px; }
.dashboard-scope-grid { display: grid; grid-template-columns: repeat(5, minmax(0, 1fr)); gap: 16px; padding: 0 24px 24px; }
.dashboard-filter-field { min-width: 0; }
.dashboard-filter-field label { display: flex; align-items: center; gap: 6px; margin-bottom: 8px; color: #475569; font-size: 12px; font-weight: 650; }
.dashboard-selection-count { padding: 1px 6px; background: #dbeafe; border-radius: 5px; color: #1e40af; font-size: 10px; }
.dashboard-filter-field input[type="date"] { width: 100%; min-width: 0; min-height: 44px; padding: 9px 12px; background: #fff; border: 1px solid #d7e0e9; border-radius: 8px; color: #172b45; font: inherit; font-size: 13px; }
.dashboard-filter-field input:disabled { background: #f1f5f9; color: #64748b; }
.dashboard-period-panel { padding: 20px 24px; background: #f8fafc; border-top: 1px solid #edf1f5; }
.dashboard-period-heading { margin-bottom: 18px; flex-wrap: wrap; }
.dashboard-period-heading > span { font-size: 12px; font-weight: 650; color: #475569; }
.dashboard-period-heading i { margin-right: 7px; }
.dashboard-date-presets { display: flex; gap: 4px; flex-wrap: wrap; padding: 4px; border: 1px solid #e2e8f0; border-radius: 9px; background: #eef2f6; }
.dashboard-date-presets button { border: 1px solid transparent; border-radius: 6px; background: transparent; padding: 6px 12px; font-size: 12px; font-weight: 600; color: #52657b; }
.dashboard-date-presets button:hover { color: #1d4ed8; background: #fff; }
.dashboard-date-presets button.active { background: #fff; color: #1d4ed8; border-color: #dce4ee; box-shadow: 0 1px 3px #172b4510; }
.dashboard-date-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 32px; }
.dashboard-range-inputs { display: grid; grid-template-columns: minmax(0, 1fr) 16px minmax(0, 1fr); align-items: end; gap: 12px; }
.dashboard-date-arrow { padding-bottom: 12px; color: #94a3b8; }
.dashboard-map-date { display: flex; align-items: end; gap: 18px; border-left: 1px solid #dce4ee; padding-left: 32px; }
.dashboard-map-date .dashboard-filter-field { flex: 1; }
.dashboard-map-date p { flex: 1; color: #64748b; font-size: 12px; line-height: 1.6; margin: 0 0 3px; }
.dashboard-date-error { margin: 14px 0 0; font-size: 13px; color: #b91c1c; }
@media (max-width: 1199px) {
    .dashboard-scope-grid { grid-template-columns: repeat(3, minmax(0, 1fr)); }
    .dashboard-map-date { padding-left: 20px; gap: 12px; }
    .dashboard-map-date p { display: block; }
}
@media (max-width: 767px) {
    .dashboard-filters-header { align-items: flex-start; flex-wrap: wrap; }
    .dashboard-filter-actions { margin-left: auto; }
    .dashboard-scope-grid { grid-template-columns: repeat(2, minmax(0, 1fr)); }
    .dashboard-date-grid { grid-template-columns: 1fr; gap: 18px; }
    .dashboard-map-date { border-left: 0; padding-left: 0; }
    .dashboard-map-date p { display: block; }
    .dashboard-filters-header, .dashboard-period-panel { padding: 18px; }
    .dashboard-scope-grid { padding: 0 18px 18px; }
}
@media (max-width: 420px) {
    .dashboard-scope-grid { grid-template-columns: 1fr; }
    .dashboard-date-presets button { padding: 6px 8px; }
    .dashboard-range-inputs { gap: 6px; }
}

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

    &.closed {
        background: #dc2626;
    }
}

.route-location-marker-dot {
    position: relative;
    z-index: 1;
    display: flex;
    align-items: center;
    justify-content: center;
    width: 24px;
    height: 24px;
    border: 2px solid #fff;
    border-radius: 50%;
    background: #10b981;
    color: #fff;
    box-shadow: 0 0 3px rgba(0, 0, 0, 0.4);

    i {
        font-size: 11px;
    }

    &.closed {
        background: #dc2626;
    }

    &.live::before {
        position: absolute;
        z-index: -1;
        width: 100%;
        height: 100%;
        border-radius: 50%;
        background: #10b981;
        content: "";
        animation: route-location-pulse 1.5s ease-out infinite;
    }
}

@keyframes route-location-pulse {
    from {
        opacity: 0.75;
        transform: scale(1);
    }
    to {
        opacity: 0;
        transform: scale(2.4);
    }
}

@media (prefers-reduced-motion: reduce) {
    .route-location-marker-dot.live::before {
        animation: none;
    }
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
