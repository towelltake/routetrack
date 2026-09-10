<script setup>
import { computed, nextTick, onBeforeUnmount, onMounted, ref, watch } from "vue";
import { Head } from "@inertiajs/vue3";
import axios from "axios";
import L from "leaflet";
import "leaflet/dist/leaflet.css";
import VueSelect from "vue-select";
import DashboardCards from "./DashboardCards.vue";
import DashboardGraphs from "./DashboardGraphs.vue";
import RouteStatusDialog from "./RouteStatusDialog.vue";
import CustomerDetailsDialog from "./CustomerDetailsDialog.vue";
import { defineAsyncComponent } from "vue";
const DashboardAnalytics = defineAsyncComponent(() => import("./DashboardAnalytics.vue"));
import { filterFields, filterOptions, dateRangeForPreset, dateRangeError } from "./filters";

const activeView = ref(null);
const actionDialog = ref(null);
const detailMetrics = ref(null);
const detailLoading = ref(false);
const detailError = ref(null);
let detailController;
let mapController;
let mapRequest = 0;
let restored = null;
if (new URLSearchParams(window.location.search).get('restore') === '1') {
    try { restored = JSON.parse(sessionStorage.getItem('dashboard-return') || 'null'); } catch {}
}
const summary = computed(() => metrics.value?.action_summary ?? {});
function saveDashboard(event) {
    const link = event.target.closest('a');
    if (!link || !link.getAttribute('href')?.startsWith('/route-tracking')) return;
    try { sessionStorage.setItem('dashboard-return', JSON.stringify({ selected: selected.value, from: fromDate.value, to: toDate.value, preset: datePreset.value,
        actionScroll: actionDialog.value?.scrollTop ?? 0, view: activeView.value, search: routeListSearch.value, scroll: window.scrollY,
        map: map ? { center: [map.getCenter().lat, map.getCenter().lng], zoom: map.getZoom() } : null,
        analytics: analyticsView.value?.getState() })); } catch {}
}
async function openAction(view) {
    detailMetrics.value = null; detailError.value = null;
    detailLoading.value = view !== 'live';
    activeView.value = view;
    await nextTick();
    if (!actionDialog.value.open) actionDialog.value.showModal();
    if (view === 'live') {
        initializeMap();
        await loadLocations(locationRequest, dashboardRequestController?.signal);
        map?.invalidateSize();
        if (restored?.map) map.setView(restored.map.center, restored.map.zoom);
        if (restored?.search) routeListSearch.value = restored.search;
    } else {
        detailController?.abort();
        const current = new AbortController(); detailController = current;
        detailLoading.value = true; detailError.value = null; detailMetrics.value = null;
        try {
            const { data } = await axios.get('/dashboard/metrics.json', { params: { from_date: fromDate.value, to_date: toDate.value, ...selected.value, details: 1 }, signal: current.signal, timeout: 60000 });
            if (detailController !== current || current.signal.aborted) return;
            detailMetrics.value = data;
        } catch (e) { if (!axios.isCancel(e)) detailError.value = 'Unable to load details. Close and try again.'; }
        finally { if (detailController === current) detailLoading.value = false; }
    }
    await nextTick();
    if (actionDialog.value && restored) actionDialog.value.scrollTop = restored.actionScroll ?? 0;
}
function closeAction() {
    mapController?.abort(); mapRequest++; loading.value = false;
    detailController?.abort();
    actionDialog.value?.close();
    activeView.value = null;
    if (map) { map.remove(); map = null; markersLayer = null; }
    restored = null;
}

const OMAN_BOUNDS = L.latLngBounds([16.0, 51.5], [27.0, 60.5]);
const now = new Date();
const DEFAULT_DATE = new Date(now.getTime() - now.getTimezoneOffset() * 60_000).toISOString().slice(0, 10);

const mapWrapperEl = ref(null);
const mapEl = ref(null);
const filterRows = ref([]);
const selected = ref(restored?.selected ?? Object.fromEntries(filterFields.map(({ key }) => [key, []])));
const filtersReady = ref(false);
const options = computed(() => Object.fromEntries(filterFields.map((field) => [
    field.key, filterOptions(filterRows.value, selected.value, field),
])));
const datePreset = ref(restored?.preset ?? "today");
const fromDate = ref(restored?.from ?? DEFAULT_DATE);
const toDate = ref(restored?.to ?? DEFAULT_DATE);
const dateError = computed(() => dateRangeError(fromDate.value, toDate.value));
const loading = ref(false);
const metrics = ref(null);
const metricsLoading = ref(true);
const metricsError = ref(null);
const analyticsView = ref(null);
const routeStatusDialog = ref(null);
const customerDetailsDialog = ref(null);
function inspectCard(title) {
    const kind = { 'Planned coverage': 'planned', 'Unplanned Customers': 'unplanned', 'OTP usage': 'otp', 'Productive visits': 'productive', 'Sales': 'sales', 'Order value': 'orders', 'Collections': 'collections', 'Returns': 'returns', 'Total Duration': 'duration', 'Operational Time': 'operational', 'OTP Customer Time': 'otp_time', 'Actual Face Time': 'actual_face', 'Time Outside Visits': 'outside' }[title];
    if (kind) {
        customerDetailsDialog.value.open(kind, { from_date: fromDate.value, to_date: toDate.value, ...selected.value });
        return;
    }
    if (title === "Routes Started / Total") routeStatusDialog.value.open({ from_date: fromDate.value, to_date: toDate.value, ...selected.value });
    else analyticsView.value?.openOverview(title);
}
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
let dashboardRequestController = null;

onBeforeUnmount(() => {
    locationRequest++;
    dashboardRequestController?.abort();
    detailController?.abort();
    mapController?.abort();
    document.removeEventListener("fullscreenchange", fullscreenChanged);
    map?.remove();
});

watch([selected, fromDate, toDate], () => {
    if (filtersReady.value) { closeAction(); restored = null; showAllLocations(); }
}, { deep: true });

function applyDatePreset() {
    if (datePreset.value === "custom") return;
    const today = new Date();
    const localDate = new Date(today.getTime() - today.getTimezoneOffset() * 60_000).toISOString().slice(0, 10);
    const range = dateRangeForPreset(datePreset.value, localDate);
    fromDate.value = range.from;
    toDate.value = range.to;
}

function initializeMap() {
    if (map) return;
    map = L.map(mapEl.value, { maxBounds: OMAN_BOUNDS, maxBoundsViscosity: 1.0, minZoom: 6 }).setView([20.5, 56], 8);
    map.attributionControl.setPrefix("Maps powered by Towell-TAKE Solutions LLC");

    L.tileLayer("https://tile.openstreetmap.org/{z}/{x}/{y}.png", {
        maxZoom: 19,
        attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors',
    }).addTo(map);

    markersLayer = L.layerGroup().addTo(map);
}

onMounted(async () => {
    document.addEventListener("fullscreenchange", fullscreenChanged);
    try {
        const { data } = await axios.get("/dashboard/filters.json", { timeout: 60000 });
        filterRows.value = data;
        filtersReady.value = true;
    } catch {
        error.value = "Unable to load Dashboard filters. Refresh the page to retry.";
        metricsLoading.value = false;
        metricsError.value = "Unable to load dashboard filters.";
    }

    if (filtersReady.value) {
        showAllLocations();
        if (restored?.view) await openAction(restored.view);
        window.scrollTo(0, restored?.scroll ?? 0);
    }

});

function fullscreenChanged() {
    isFullscreen.value = document.fullscreenElement === mapWrapperEl.value;
    setTimeout(() => map?.invalidateSize(), 0);
}

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
    dashboardRequestController?.abort();
    dashboardRequestController = new AbortController();
    const signal = dashboardRequestController.signal;
    metrics.value = null;
    metricsError.value = null;
    locations.value = [];
    markersLayer?.clearLayers();
    Object.keys(routeMarkers).forEach((key) => delete routeMarkers[key]);
    error.value = null;
    if (dateError.value) {
        loading.value = false;
        metricsLoading.value = false;
        return;
    }

    loadMetrics(request, signal);
    if (activeView.value === 'live') loadLocations(request, signal);
}

async function loadLocations(request) {
    mapController?.abort();
    mapController = new AbortController();
    const signal = mapController.signal;
    const currentMapRequest = ++mapRequest;
    loading.value = true;
    error.value = null;
    markersLayer?.clearLayers();
    try {
        const { data } = await axios.get("/dashboard/last-locations.json", {
            signal,
            timeout: 60000,
            params: {
                from_date: fromDate.value,
                to_date: toDate.value,
                ...selected.value,
            },
        });
        if (currentMapRequest !== mapRequest || request !== locationRequest || activeView.value !== 'live' || !map) return;
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

        map?.fitBounds(L.latLngBounds(markerLatLngs), { padding: [40, 40], maxZoom: 13 });
    } catch (e) {
        if (currentMapRequest !== mapRequest || request !== locationRequest || axios.isCancel(e)) return;
        console.error(e);
        error.value = e.response?.data?.error || "Unable to load route locations.";
    } finally {
        if (currentMapRequest === mapRequest && request === locationRequest) loading.value = false;
    }
}

async function loadMetrics(request, signal) {
    metricsLoading.value = true;
    try {
        const { data } = await axios.get("/dashboard/metrics.json", {
            signal,
            timeout: 60000,
            params: { from_date: fromDate.value, to_date: toDate.value, ...selected.value, summary: 1 },
        });
        if (request === locationRequest) metrics.value = data;
    } catch {
        if (request === locationRequest) metricsError.value = "Unable to load the overview figures.";
    } finally {
        if (request === locationRequest) metricsLoading.value = false;
    }
}

function trackUrl(routecode, routeDate) {
    return `/route-tracking?routecode=${encodeURIComponent(routecode)}&date=${encodeURIComponent(routeDate)}&from=dashboard`;
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
    markersLayer?.clearLayers();
    Object.keys(routeMarkers).forEach((key) => delete routeMarkers[key]);
    map?.setView([20.5, 56], 8);
}
</script>

<template>
    <Head title="Dashboard" />

    <div class="content route-location-content" @click.capture="saveDashboard">
        <div class="route-location-page-heading">
            <h1 class="h3 fw-bold mb-1">Dashboard</h1>
            <h2 class="fs-base lh-base fw-medium text-muted mb-0">Field performance across every route journey</h2>
        </div>

        <section class="dashboard-filters" aria-labelledby="dashboard-filters-title">
            <header class="dashboard-filters-header">
                <div class="dashboard-filters-heading">
                    <span class="dashboard-filter-icon"><i class="fa fa-sliders" aria-hidden="true"></i></span>
                    <div>
                        <h2 id="dashboard-filters-title">Filters</h2>
                    </div>
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
                            <label for="dashboard-from">Route start: from</label>
                            <input id="dashboard-from" v-model="fromDate" type="date" :max="toDate || undefined" @input="datePreset = 'custom'" />
                        </div>
                        <span class="dashboard-date-arrow" aria-hidden="true">&rarr;</span>
                        <div class="dashboard-filter-field">
                            <label for="dashboard-to">To date</label>
                            <input id="dashboard-to" v-model="toDate" type="date" :min="fromDate || undefined" @input="datePreset = 'custom'" />
                        </div>
                    </div>
                </div>
                <p v-if="dateError" class="dashboard-date-error" role="alert">{{ dateError }}</p>
            </div>
            <div class="dashboard-filter-actions">
                <button type="button" class="dashboard-reset" @click="resetFilters">Reset filters</button>
                <button type="button" class="dashboard-refresh" :disabled="!filtersReady || !!dateError" @click="showAllLocations">
                    <i class="fa fa-arrow-rotate-right" :class="{ 'fa-spin': loading || metricsLoading }" aria-hidden="true"></i>
                    Refresh
                </button>
            </div>
        </section>

        <DashboardCards :metrics="metrics" :loading="metricsLoading" :error="metricsError" @inspect="inspectCard" />
        <RouteStatusDialog ref="routeStatusDialog" />
        <CustomerDetailsDialog ref="customerDetailsDialog" />
        <div class="dashboard-actions">
            <article><i class="fa fa-chart-column"></i><h2>Performance comparison</h2><strong>{{ summary.customers ?? '\u2014' }} <small>customers visited</small></strong><p>{{ metrics?.journeys_started ?? '\u2014' }} journeys &middot; Compare routes and teams</p><button :disabled="!metrics || !!dateError" @click="openAction('performance')">Compare performance <span aria-hidden="true">&rarr;</span></button></article>
            <article class="attention"><i class="fa fa-flag"></i><h2>Journeys needing attention</h2><strong>{{ summary.review ?? '\u2014' }} <small>journeys to review</small></strong><p>{{ summary.repeat ?? '\u2014' }} repeat visits &middot; Execution and data issues</p><button :disabled="!metrics || !!dateError" @click="openAction('attention')">Review journeys <span aria-hidden="true">&rarr;</span></button></article>
            <article class="live"><i class="fa fa-map-location-dot"></i><h2>Track your live routes</h2><strong>{{ summary.open ?? '\u2014' }} <small>open journeys</small></strong><p>Last known locations for the selected routes and period</p><button :disabled="!filtersReady || !!dateError" @click="openAction('live')">Open live map <span aria-hidden="true">&rarr;</span></button></article>
        </div>
        <DashboardGraphs :metrics="metrics" :loading="metricsLoading" />
        <dialog ref="actionDialog" class="dashboard-action-dialog" @cancel.prevent="closeAction">
            <header><h2>{{ activeView === 'live' ? 'Track your live routes' : activeView === 'attention' ? 'Journeys needing attention' : 'Performance comparison' }}</h2><button @click="closeAction" aria-label="Close">&times;</button></header>
            <p v-if="detailError && activeView !== 'live'" role="alert">{{ detailError }}</p>
            <DashboardAnalytics v-if="activeView && activeView !== 'live'" ref="analyticsView" :metrics="detailMetrics" :loading="detailLoading" :view="activeView" :initial-state="restored?.analytics" />

        <BaseBlock v-if="activeView === 'live'" title="Route locations" :mode-loading="loading">
            <button type="button" class="btn btn-sm btn-light mb-2" :disabled="loading" @click="loadLocations(locationRequest)"><i class="fa fa-rotate-right me-1"></i> Refresh locations</button>
            <p v-if="error" class="text-danger">{{ error }}</p>
            <p v-else-if="locations.length" class="text-muted small">Latest matching journey for each of {{ locations.length }} routes. Selected route-start period: {{ fromDate }} to {{ toDate }}.</p>

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
        </dialog>
    </div>
</template>

<style lang="scss">
.dashboard-actions { display:grid; grid-template-columns:repeat(3,minmax(0,1fr)); gap:16px; margin:20px 0; }
.dashboard-actions article { padding:20px; border:1px solid #dce5f0; border-radius:12px; background:white; display:flex; flex-direction:column; gap:10px; color:#172b45; }
.dashboard-actions i { color:#2563eb; font-size:22px; } .dashboard-actions .attention i { color:#b45309; } .dashboard-actions .live i { color:#0f766e; }
.dashboard-actions h2 { font-size:16px; margin:0; } .dashboard-actions strong { font-size:26px; } .dashboard-actions small { font-size:12px; color:#64748b; font-weight:400; }
.dashboard-actions p { font-size:12px; color:#64748b; flex:1; margin:0; }
.dashboard-actions button { padding:9px 12px; background:#eff6ff; color:#1d4ed8; border:0; border-radius:7px; text-align:left; } .dashboard-actions button span { float:right; } .dashboard-actions button:disabled { opacity:.5; }
.dashboard-action-dialog { width:96vw; max-width:1600px; max-height:92vh; padding:20px; border:0; border-radius:12px; color:#172b45; }
.dashboard-action-dialog::backdrop { background:#0f172a88; } .dashboard-action-dialog > header { display:flex; justify-content:space-between; align-items:center; margin-bottom:12px; } .dashboard-action-dialog > header h2 { font-size:20px; margin:0; } .dashboard-action-dialog > header button { border:0; background:#f1f5f9; font-size:24px; border-radius:6px; }
@media(max-width:850px) { .dashboard-actions { grid-template-columns:1fr; } }
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
    .vs__dropdown-toggle { min-height: 36px; border: 1px solid #d7e0e9; border-radius: 8px; background: #fff; padding: 1px 7px; }
    .vs--open .vs__dropdown-toggle, .vs__dropdown-toggle:focus-within { border-color: #2563eb; box-shadow: 0 0 0 3px #eff6ff; }
    .vs__selected { background: #eff6ff; border: 0; border-radius: 5px; color: #1e40af; font-size: 12px; max-width: 100%; overflow-wrap: anywhere; }
    .vs__selected-options { min-width: 0; }
    .vs__search { min-width: 0; font-size: 13px; color: #52657b; }
    .vs__dropdown-menu { border: 1px solid #d7e0e9; border-radius: 8px; box-shadow: 0 8px 24px #172b451a; z-index: 1100; }
    .vs__dropdown-option { white-space: normal; font-size: 13px; padding: 9px 12px; }
    .vs__dropdown-option--highlight { background: #eff6ff; color: #1d4ed8; }
}
.dashboard-filters-header, .dashboard-filters-heading, .dashboard-filter-actions, .dashboard-period-heading { display: flex; align-items: center; justify-content: space-between; gap: 16px; }
.dashboard-filters-header { padding: 10px 16px; }
.dashboard-filter-actions { justify-content: flex-end; padding: 8px 16px 10px; }
.dashboard-filters-heading { justify-content: flex-start; }
.dashboard-filters-heading h2 { margin: 0 0 4px; font-size: 17px; font-weight: 700; letter-spacing: -0.3px; }
.dashboard-filters-heading p { margin: 0; color: #64748b; font-size: 13px; }
.dashboard-filter-icon { display: grid; place-items: center; width: 28px; height: 28px; flex-shrink: 0; border-radius: 8px; background: #eff6ff; color: #2563eb; }
.dashboard-filter-actions button { border-radius: 8px; padding: 6px 12px; font-size: 12px; font-weight: 600; white-space: nowrap; }
.dashboard-reset { border: 1px solid transparent; color: #52657b; background: transparent; }
.dashboard-reset:hover { background: #f1f5f9; }
.dashboard-refresh { border: 1px solid #172b45; background: #172b45; color: #fff; }
.dashboard-refresh:hover { background: #274467; }
.dashboard-refresh i { margin-right: 7px; }
.dashboard-scope-grid { display: grid; grid-template-columns: repeat(5, minmax(0, 1fr)); gap: 10px; padding: 0 16px 12px; }
.dashboard-filter-field { min-width: 0; }
.dashboard-filter-field label { display: flex; align-items: center; gap: 6px; margin-bottom: 4px; color: #475569; font-size: 11px; font-weight: 650; }
.dashboard-selection-count { padding: 1px 6px; background: #dbeafe; border-radius: 5px; color: #1e40af; font-size: 10px; }
.dashboard-filter-field input[type="date"] { width: 100%; min-width: 0; min-height: 36px; padding: 6px 10px; background: #fff; border: 1px solid #d7e0e9; border-radius: 8px; color: #172b45; font: inherit; font-size: 13px; }
.dashboard-filter-field input:disabled { background: #f1f5f9; color: #64748b; }
.dashboard-period-panel { display: flex; flex-wrap: wrap; align-items: end; gap: 10px 20px; padding: 10px 16px; background: #f8fafc; border-top: 1px solid #edf1f5; }
.dashboard-period-heading { order: 1; margin-left: auto; flex-wrap: wrap; }
.dashboard-period-heading > span { font-size: 12px; font-weight: 650; color: #475569; }
.dashboard-period-heading i { margin-right: 7px; }
.dashboard-date-presets { display: flex; gap: 4px; flex-wrap: wrap; padding: 4px; border: 1px solid #e2e8f0; border-radius: 9px; background: #eef2f6; }
.dashboard-date-presets button { border: 1px solid transparent; border-radius: 6px; background: transparent; padding: 6px 12px; font-size: 12px; font-weight: 600; color: #52657b; }
.dashboard-date-presets button:hover { color: #1d4ed8; background: #fff; }
.dashboard-date-presets button.active { background: #fff; color: #1d4ed8; border-color: #dce4ee; box-shadow: 0 1px 3px #172b4510; }
.dashboard-date-grid { width: 440px; max-width: 100%; }
.dashboard-range-inputs { display: grid; grid-template-columns: minmax(0, 1fr) 16px minmax(0, 1fr); align-items: end; gap: 12px; }
.dashboard-date-arrow { padding-bottom: 12px; color: #94a3b8; }
.dashboard-map-date { display: flex; align-items: end; gap: 18px; border-left: 1px solid #dce4ee; padding-left: 32px; }
.dashboard-map-date .dashboard-filter-field { flex: 1; }
.dashboard-map-date p { flex: 1; color: #64748b; font-size: 12px; line-height: 1.6; margin: 0 0 3px; }
.dashboard-date-error { order: 2; flex-basis: 100%; margin: 0; font-size: 13px; color: #b91c1c; }
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
    .dashboard-filters-header, .dashboard-period-panel { padding: 10px 12px; }
    .dashboard-scope-grid { padding: 0 12px 12px; }
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
