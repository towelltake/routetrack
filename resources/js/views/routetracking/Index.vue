<script setup>
import { computed, onBeforeUnmount, onMounted, ref, watch } from "vue";
import { Head } from "@inertiajs/vue3";
import { useTemplateStore } from "@/stores/template";
import axios from "axios";
import L from "leaflet";
import "leaflet/dist/leaflet.css";
import VueSelect from "vue-select";

const OMAN_BOUNDS = L.latLngBounds([16.0, 51.5], [27.0, 60.5]);
const store = useTemplateStore();

const mapWrapperEl = ref(null);
const mapEl = ref(null);
const companies = ref([]);
const routes = ref([]);
const selectedCompany = ref(null);
const selectedRoute = ref(null);
const now = new Date();
const DEFAULT_DATE = new Date(now.getTime() - now.getTimezoneOffset() * 60_000).toISOString().slice(0, 10);
const selectedDate = ref(DEFAULT_DATE);
const loading = ref(false);
const error = ref(null);
const result = ref(null);
const isFullscreen = ref(false);
const customerSearch = ref("");
const customerListTab = ref("all");

const numberedCustomers = computed(() =>
    (result.value?.planned?.customers ?? []).map((customer, index) => ({ ...customer, displayNumber: index + 1 })),
);

const routeQualityWarnings = computed(() => {
    if (!result.value) {
        return [];
    }

    const warnings = [];

    if (result.value.planned?.used_fallback_geometry) {
        warnings.push(
            `Planned route has ${result.value.planned.fallback_legs ?? 0} straight-line segment(s) because OSRM routing failed.`,
        );
    }

    if (result.value.actual?.used_fallback_geometry) {
        warnings.push("Actual route is raw GPS trail because OSRM map matching failed.");
    }

    return warnings;
});

const tabCustomers = computed(() => {
    if (customerListTab.value === "visited") {
        return numberedCustomers.value.filter((customer) => customer.visited);
    }

    if (customerListTab.value === "not_visited") {
        return numberedCustomers.value.filter((customer) => !customer.visited);
    }

    return numberedCustomers.value;
});

const filteredCustomers = computed(() => {
    const search = customerSearch.value.trim().toLowerCase();

    if (!search) {
        return tabCustomers.value;
    }

    return tabCustomers.value.filter(
        (customer) =>
            customer.customername.toLowerCase().includes(search) || String(customer.customercode).includes(search),
    );
});

let map = null;
const customerMarkers = {};
let resultLayer = null;
let plannedLineLayer = null;
let actualLineLayer = null;
let startMarker = null;
let endMarker = null;

onMounted(async () => {
    store.pageLoader({ mode: "on" });
    map = L.map(mapEl.value, { maxBounds: OMAN_BOUNDS, maxBoundsViscosity: 1.0, minZoom: 6 }).setView([20.5, 56], 8);
    map.attributionControl.setPrefix("Maps powered by Towell-TAKE Solutions LLC");

    L.tileLayer("https://tile.openstreetmap.org/{z}/{x}/{y}.png", {
        maxZoom: 19,
        attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors',
    }).addTo(map);

    try {
        const [routesRes, companiesRes] = await Promise.all([
            axios.get("/route-tracking/routes.json"),
            axios.get("/route-tracking/companies.json"),
        ]);
        routes.value = routesRes.data;
        companies.value = companiesRes.data;

        // Deep-link support: Route Location's "Track" button sends a route + date
        // here via query params so the comparison runs immediately on arrival.
        const params = new URLSearchParams(window.location.search);
        const linkedRoute = params.get("routecode");
        const linkedDate = params.get("date");

        if (linkedRoute && routes.value.some((route) => route.routecode === Number(linkedRoute))) {
            selectedRoute.value = Number(linkedRoute);
            if (linkedDate) {
                selectedDate.value = linkedDate;
            }
            await runComparison();
        }
    } finally {
        store.pageLoader({ mode: "off" });
    }

    document.addEventListener("fullscreenchange", () => {
        isFullscreen.value = document.fullscreenElement === mapWrapperEl.value;
        setTimeout(() => map.invalidateSize(), 0);
    });
});

onBeforeUnmount(() => store.pageLoader({ mode: "off" }));

watch(selectedCompany, async (companycode) => {
    const { data: routeData } = await axios.get("/route-tracking/routes.json", { params: { companycode } });
    routes.value = routeData;
    selectedRoute.value = null;
});

function toggleFullscreen() {
    if (document.fullscreenElement) {
        document.exitFullscreen();
    } else {
        mapWrapperEl.value.requestFullscreen();
    }
}

function flagIcon(color, label) {
    return L.divIcon({
        className: "route-tracking-flag",
        html: `<div style="background:${color};color:#fff;border-radius:50%;width:24px;height:24px;display:flex;align-items:center;justify-content:center;font-size:11px;font-weight:bold;border:2px solid #fff;box-shadow:0 0 3px rgba(0,0,0,0.4)">${label}</div>`,
        iconSize: [24, 24],
        iconAnchor: [12, 12],
    });
}

function customerStatusColor(customer) {
    if (customer.visited) {
        return "#16a34a";
    }

    return "#9ca3af";
}

function numberedIcon(sequence, customer) {
    const color = customerStatusColor(customer);
    return L.divIcon({
        className: "route-tracking-customer-marker",
        html: `<div style="background:${color};color:#fff;border-radius:50%;width:22px;height:22px;display:flex;align-items:center;justify-content:center;font-size:11px;font-weight:bold;border:2px solid #fff;box-shadow:0 0 3px rgba(0,0,0,0.4)">${sequence}</div>`,
        iconSize: [22, 22],
        iconAnchor: [11, 11],
    });
}

function plannedRouteStyle() {
    if (result.value?.planned?.used_fallback_geometry) {
        return { color: "#3b82f6", weight: 3, opacity: 0.7, dashArray: "8 8" };
    }

    return { color: "#3b82f6", weight: 4 };
}

function actualRouteStyle() {
    if (result.value?.actual?.used_fallback_geometry) {
        return { color: "#ef4444", weight: 3, opacity: 0.75, dashArray: "8 8" };
    }

    return { color: "#ef4444", weight: 4 };
}

function customerVisitStatus(customer) {
    if (customer.visited) {
        return "Visited Customer";
    }

    return "Not Visited Customer";
}

function customerVisitDetails(customer) {
    if (!customer.visited) {
        return "";
    }

    const start = [customer.visit_start_date, customer.visit_start_time].filter(Boolean).join(", ");
    const end = [customer.visit_end_date, customer.visit_end_time].filter(Boolean).join(", ");
    const duration = customer.visit_duration_minutes !== null
        ? `${Math.floor(customer.visit_duration_minutes / 60)}h ${customer.visit_duration_minutes % 60}m`
        : null;

    return [start && `Visit Start: ${start}`, end && `Visit End: ${end}`, duration && `Visit Duration: ${duration}`]
        .filter(Boolean)
        .map((line) => `<br>${line}`)
        .join("");
}

async function runComparison() {
    if (!selectedRoute.value || !selectedDate.value) {
        return;
    }

    loading.value = true;
    store.pageLoader({ mode: "on" });
    error.value = null;
    result.value = null;

    if (resultLayer) {
        map.removeLayer(resultLayer);
        resultLayer = null;
    }

    plannedLineLayer = null;
    actualLineLayer = null;
    startMarker = null;
    endMarker = null;
    Object.keys(customerMarkers).forEach((key) => delete customerMarkers[key]);
    customerSearch.value = "";
    customerListTab.value = "all";

    const params = {
        routecode: selectedRoute.value,
        date: selectedDate.value,
    };

    try {
        const { data } = await axios.get("/route-tracking/compare.json", { params });
        result.value = data;

        resultLayer = L.featureGroup().addTo(map);
        const actualLayer = L.featureGroup().addTo(resultLayer);

        plannedLineLayer = L.featureGroup().addTo(resultLayer);
        result.value.planned.geometries.forEach((geometry) => {
            L.geoJSON(geometry, { style: plannedRouteStyle() }).addTo(plannedLineLayer);
        });

        result.value.planned.customers.forEach((customer, index) => {
            const marker = L.marker([customer.lat, customer.lng], { icon: numberedIcon(index + 1, customer) })
                .bindPopup(
                    `<strong>${index + 1}. ${customer.customername}</strong><br>Customer ${customer.customercode}<br>${customerVisitStatus(customer)}${customerVisitDetails(customer)}`,
                )
                .addTo(resultLayer);
            customerMarkers[customer.customercode] = marker;
        });

        actualLineLayer = L.featureGroup().addTo(actualLayer);
        result.value.actual.geometries.forEach((geometry) => {
            L.geoJSON(geometry, { style: actualRouteStyle() }).addTo(actualLineLayer);
        });

        const { start, end } = result.value.actual;
        startMarker = L.marker([start.lat, start.lng], { icon: flagIcon("#16a34a", "S") })
            .bindPopup(`<strong>Route Start</strong><br>${start.time ?? ""}`)
            .addTo(actualLayer);
        endMarker = L.marker([end.lat, end.lng], { icon: flagIcon("#dc2626", "E") })
            .bindPopup(`<strong>Route End</strong><br>${end.time ?? ""}`)
            .addTo(actualLayer);

        // Zoom to the actual start/end points (smaller, more relevant area) rather than
        // fitting both planned + actual — the planned route stays drawn but out of frame.
        const bounds = actualLayer.getBounds();
        if (bounds.isValid()) {
            map.fitBounds(bounds, { padding: [60, 60] });
        }
    } catch (e) {
        console.error(e);
        error.value = e.response?.data?.error || "Unable to compute comparison.";
    } finally {
        loading.value = false;
        store.pageLoader({ mode: "off" });
    }
}

function resetFilters() {
    selectedCompany.value = null;
    selectedRoute.value = null;
    selectedDate.value = DEFAULT_DATE;
    error.value = null;
    result.value = null;
    customerSearch.value = "";
    customerListTab.value = "all";

    if (resultLayer) {
        map.removeLayer(resultLayer);
        resultLayer = null;
    }

    map.setView([20.5, 56], 8);
}

function km(meters) {
    return (meters / 1000).toFixed(1);
}

function minutes(seconds) {
    return Math.round(seconds / 60);
}

function pct(ratio) {
    return ratio === null || ratio === undefined ? "n/a" : Math.round(ratio * 100) + "%";
}

function focusCustomer(customer) {
    const marker = customerMarkers[customer.customercode];
    if (!marker || !map) {
        return;
    }

    map.setView(marker.getLatLng(), Math.max(map.getZoom(), 15));
    marker.openPopup();
}

function fitToCustomers(predicate) {
    const markers = (result.value?.planned?.customers ?? [])
        .filter(predicate)
        .map((customer) => customerMarkers[customer.customercode])
        .filter(Boolean);

    if (!markers.length || !map) {
        return;
    }

    map.fitBounds(L.latLngBounds(markers.map((marker) => marker.getLatLng())), { padding: [60, 60] });
}

function focusVisitedCustomers() {
    customerListTab.value = "visited";
    fitToCustomers((customer) => customer.visited);
}

function focusNotVisitedCustomers() {
    customerListTab.value = "not_visited";
    fitToCustomers((customer) => !customer.visited);
}

function selectCustomerTab(tab) {
    customerListTab.value = tab;

    if (tab === "visited") {
        fitToCustomers((customer) => customer.visited);
    } else if (tab === "not_visited") {
        fitToCustomers((customer) => !customer.visited);
    } else {
        fitToCustomers(() => true);
    }
}

function focusPlannedRoute() {
    if (!plannedLineLayer || !map) {
        return;
    }

    const bounds = plannedLineLayer.getBounds();
    if (bounds.isValid()) {
        map.fitBounds(bounds, { padding: [60, 60] });
    }
}

function focusActualRoute() {
    if (!actualLineLayer || !map) {
        return;
    }

    const bounds = actualLineLayer.getBounds();
    if (bounds.isValid()) {
        map.fitBounds(bounds, { padding: [60, 60] });
    }
}

function focusStart() {
    if (!startMarker || !map) {
        return;
    }

    map.setView(startMarker.getLatLng(), Math.max(map.getZoom(), 15));
    startMarker.openPopup();
}

function focusEnd() {
    if (!endMarker || !map) {
        return;
    }

    map.setView(endMarker.getLatLng(), Math.max(map.getZoom(), 15));
    endMarker.openPopup();
}
</script>

<template>
    <Head title="Route Tracking" />

    <div class="content route-tracking-content">
        <div class="route-tracking-page-heading">
            <h1 class="h3 fw-bold mb-1">Route Tracking</h1>
            <h2 class="fs-base lh-base fw-medium text-muted mb-0">Planned route vs actual start/end GPS points</h2>
        </div>

        <BaseBlock title="Filters" class="route-tracking-filters">
            <div class="row align-items-end g-3">
                <div class="col-md-4">
                    <label class="form-label">Company</label>
                    <VueSelect
                        v-model="selectedCompany"
                        :options="companies"
                        :reduce="(company) => company.cmpycode"
                        label="name"
                        placeholder="All companies..."
                    />
                </div>
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
            </div>
            <div class="row align-items-end g-3 mt-3">
                <div class="col-md-4">
                    <button class="btn btn-primary w-100" :disabled="loading || !selectedRoute || !selectedDate" @click="runComparison">
                        {{ loading ? "..." : "Apply" }}
                    </button>
                </div>
                <div class="col-md-4">
                    <button class="btn btn-light w-100" :disabled="loading" @click="resetFilters">Reset</button>
                </div>
            </div>
        </BaseBlock>

        <BaseBlock title="Route Tracking" :mode-loading="loading">
            <p v-if="error" class="text-danger">{{ error }}</p>

            <div v-if="routeQualityWarnings.length" class="alert alert-warning py-2">
                <div v-for="warning in routeQualityWarnings" :key="warning">{{ warning }}</div>
            </div>

            <div v-if="result" class="row mb-3 g-3">
                <div class="col-md-4">
                    <div class="fw-bold text-primary">Planned</div>
                    <div>{{ km(result.planned.distance) }} km</div>
                    <div>{{ minutes(result.planned.duration) }} min</div>
                    <div class="text-muted small">{{ result.planned.customer_count }} customers ({{ result.planned.day }})</div>
                    <div v-if="result.planned.used_fallback_geometry" class="text-warning small">
                        Approximate fallback, not road routed
                    </div>
                    <div class="text-success small">
                        {{ result.planned.visited_count }} / {{ result.planned.customer_count }} visited
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="fw-bold text-danger">Actual (matched GPS)</div>
                    <div>{{ km(result.actual.distance) }} km</div>
                    <div>{{ minutes(result.actual.duration) }} min</div>
                    <div class="text-muted small">
                        {{ result.actual.point_count }}
                        {{ result.actual.used_fallback_geometry ? "raw GPS points" : "GPS points matched to roads" }}
                    </div>
                    <div v-if="result.actual.used_fallback_geometry" class="text-warning small">
                        Approximate fallback, not map matched
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="fw-bold">Efficiency ratio</div>
                    <div>Distance: {{ pct(result.distance_ratio) }}</div>
                    <div>Time: {{ pct(result.duration_ratio) }}</div>
                </div>
            </div>

            <div class="route-tracking-legend small">
                <button type="button" class="route-tracking-legend-item" :disabled="!result" @click="focusPlannedRoute">
                    <span class="text-primary">&#9632;</span>
                    {{ result?.planned?.used_fallback_geometry ? "Planned Approx." : "Planned Route" }}
                </button>
                <button type="button" class="route-tracking-legend-item" :disabled="!result" @click="focusVisitedCustomers">
                    <span style="color: #16a34a">&#9679;</span> Visited Customer
                </button>
                <button type="button" class="route-tracking-legend-item" :disabled="!result" @click="focusNotVisitedCustomers">
                    <span style="color: #9ca3af">&#9679;</span> Not Visited Customer
                </button>
                <button type="button" class="route-tracking-legend-item" :disabled="!result" @click="focusActualRoute">
                    <span class="text-danger">&#9632;</span>
                    {{ result?.actual?.used_fallback_geometry ? "Actual Raw GPS" : "Actual Matched GPS Route" }}
                </button>
                <button type="button" class="route-tracking-legend-item" :disabled="!result" @click="focusStart">
                    <span style="color: #16a34a">&#9632;</span> Route Start (S)
                </button>
                <button type="button" class="route-tracking-legend-item" :disabled="!result" @click="focusEnd">
                    <span style="color: #dc2626">&#9632;</span> Route End (E)
                </button>
            </div>

            <div class="row g-3">
                <div class="col-md-9">
                    <div ref="mapWrapperEl" class="route-tracking-map-wrapper" style="position: relative; height: 600px; width: 100%">
                        <button
                            type="button"
                            class="btn btn-light route-tracking-fullscreen-btn"
                            :title="isFullscreen ? 'Exit fullscreen' : 'Fullscreen'"
                            @click="toggleFullscreen"
                        >
                            <i :class="isFullscreen ? 'fa fa-compress' : 'fa fa-expand'"></i>
                        </button>
                        <div ref="mapEl" style="height: 100%; width: 100%"></div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card route-tracking-customer-list-card" style="height: 600px">
                        <div class="card-header">
                            <strong>Customers</strong>
                            <span v-if="result" class="text-muted small">
                                ({{ result.planned.visited_count }} / {{ result.planned.customer_count }} visited)
                            </span>
                        </div>
                        <div class="route-tracking-customer-tabs">
                            <button
                                type="button"
                                class="route-tracking-customer-tab"
                                :class="{ active: customerListTab === 'all' }"
                                :disabled="!result"
                                @click="selectCustomerTab('all')"
                            >
                                Planned Customer
                            </button>
                            <button
                                type="button"
                                class="route-tracking-customer-tab"
                                :class="{ active: customerListTab === 'visited' }"
                                :disabled="!result"
                                @click="selectCustomerTab('visited')"
                            >
                                Visited Customer
                            </button>
                            <button
                                type="button"
                                class="route-tracking-customer-tab"
                                :class="{ active: customerListTab === 'not_visited' }"
                                :disabled="!result"
                                @click="selectCustomerTab('not_visited')"
                            >
                                Not Visited Customer
                            </button>
                        </div>
                        <div class="route-tracking-customer-search p-2">
                            <input
                                v-model="customerSearch"
                                type="text"
                                class="form-control form-control-sm"
                                placeholder="Search name or code..."
                                :disabled="!result"
                            />
                        </div>
                        <div class="card-body p-2">
                            <div class="route-tracking-customer-list">
                                <p v-if="!result" class="text-muted small px-1">Run a comparison to see customers.</p>
                                <p v-else-if="!filteredCustomers.length" class="text-muted small px-1">No customers match.</p>
                                <button
                                    v-for="customer in filteredCustomers"
                                    :key="customer.customercode"
                                    type="button"
                                    class="list-group-item list-group-item-action route-tracking-customer-item"
                                    @click="focusCustomer(customer)"
                                >
                                    <span
                                        class="route-tracking-customer-dot"
                                        :style="{ background: customerStatusColor(customer) }"
                                    >
                                        {{ customer.displayNumber }}
                                    </span>
                                    <span class="route-tracking-customer-info">
                                        <span class="d-block fw-semibold small">{{ customer.customername }}</span>
                                        <span class="d-block text-muted small">
                                            {{ customer.customercode }} &middot;
                                            {{ customerVisitStatus(customer) }}
                                        </span>
                                    </span>
                                </button>
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

.route-tracking-legend {
    display: flex;
    flex-wrap: wrap;
    gap: 0.5rem;
    margin-bottom: 1rem;
}

.route-tracking-legend-item {
    display: inline-flex;
    align-items: center;
    gap: 0.35rem;
    background: #fff;
    border: 1px solid #e5e7eb;
    border-radius: 999px;
    padding: 0.25rem 0.75rem;
    color: #6c757d;
    cursor: pointer;
    transition: background-color 0.15s ease, border-color 0.15s ease;

    &:hover:not(:disabled) {
        background: #f1f3f5;
        border-color: #ced4da;
    }

    &:disabled {
        cursor: not-allowed;
        opacity: 0.6;
    }
}

// Rendered directly inside .route-tracking-content, so it already inherits
// that div's 0.5rem side padding. The extra 1.25rem here matches the
// "FILTERS" label's own block-header inset, so both texts line up exactly.
.route-tracking-page-heading {
    padding: 0.75rem 0 0.75rem 1.25rem;
}

// Wider than the default page content padding — this map-heavy page benefits
// from extra horizontal room; scoped to this page only via .route-tracking-content.
.route-tracking-content {
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

.route-tracking-map-wrapper:fullscreen {
    height: 100vh !important;
}

.route-tracking-fullscreen-btn {
    position: absolute;
    bottom: 10px;
    left: 10px;
    z-index: 1000;
    box-shadow: 0 1px 4px rgba(0, 0, 0, 0.3);
}

.route-tracking-customer-list-card {
    display: flex;
    flex-direction: column;
}

.route-tracking-customer-tabs {
    display: flex;
    border-bottom: 1px solid #e5e7eb;
}

.route-tracking-customer-tab {
    flex: 1;
    background: #fff;
    border: none;
    border-bottom: 2px solid transparent;
    padding: 0.5rem 0.25rem;
    font-size: 0.75rem;
    font-weight: 600;
    color: #6c757d;
    cursor: pointer;
    text-align: center;

    &.active {
        color: #3b82f6;
        border-bottom-color: #3b82f6;
    }

    &:disabled {
        cursor: not-allowed;
        opacity: 0.6;
    }
}

.route-tracking-customer-search {
    border-bottom: 1px solid #e5e7eb;
}

.route-tracking-customer-list-card .card-body {
    display: flex;
    flex-direction: column;
    min-height: 0;
    flex: 1;
}

.route-tracking-customer-list {
    overflow-y: auto;
    flex: 1;
}

.route-tracking-customer-item {
    display: flex;
    align-items: center;
    gap: 8px;
    text-align: left;
}

.route-tracking-customer-dot {
    flex-shrink: 0;
    color: #fff;
    border-radius: 50%;
    width: 22px;
    height: 22px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 11px;
    font-weight: bold;
}

.route-tracking-customer-info {
    min-width: 0;
    overflow: hidden;
}

.route-tracking-customer-info span {
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}
</style>
