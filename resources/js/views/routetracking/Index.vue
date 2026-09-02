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
const customerVisitTab = ref("all");
const plannedRouteVisible = ref(true);
const actualRouteVisible = ref(true);
const rawCoordinatesVisible = ref(false);
const plannedCustomersVisible = ref(true);
const customerVisitsVisible = ref(false);
const plannedNotVisitedVisible = ref(false);

const numberedCustomers = computed(() =>
    (result.value?.planned?.customers ?? []).map((customer, index) => ({
        ...customer,
        displayNumber: index + 1,
        listKey: `planned-${customer.customercode}`,
        type: "planned",
    })),
);

const customerVisits = computed(() => {
    const plannedCustomerCodes = new Set(
        (result.value?.planned?.customers ?? []).map((customer) => String(customer.customercode)),
    );

    return (result.value?.planned?.customer_visits ?? []).map((visit, index) => ({
        ...visit,
        displayNumber: index + 1,
        listKey: `visit-${visit.logkey}`,
        type: "visit",
        planned: plannedCustomerCodes.has(String(visit.customercode)),
    }));
});

const customerVisitSummary = computed(() => {
    const plannedCustomers = result.value?.planned?.customers ?? [];
    const plannedCodes = new Set(plannedCustomers.map((customer) => String(customer.customercode)));
    const plannedVisitedCodes = new Set(
        customerVisits.value.filter((visit) => visit.planned).map((visit) => String(visit.customercode)),
    );
    const unplannedVisitedCodes = new Set(
        customerVisits.value.filter((visit) => !visit.planned).map((visit) => String(visit.customercode)),
    );

    return {
        planned: plannedCustomers.length,
        plannedVisited: plannedVisitedCodes.size,
        unplannedVisited: unplannedVisitedCodes.size,
        plannedNotVisited: [...plannedCodes].filter((code) => !plannedVisitedCodes.has(code)).length,
    };
});

function plannedDayLabel(day) {
    const days = { sun: "Sunday", mon: "Monday", tue: "Tuesday", wed: "Wednesday", thu: "Thursday", fri: "Friday", sat: "Saturday" };
    return days[String(day ?? "").toLowerCase()] ?? day;
}

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

    if (!result.value.planned?.has_planned_data) {
        warnings.push("Route Sequence Data Not Available/Uploaded.");
    }

    if (!result.value.actual?.has_tracking_data) {
        warnings.push("Route Track Data Not Available.");
    }

    return warnings;
});

const tabCustomers = computed(() => {
    if (customerListTab.value === "visits") {
        return customerVisitTab.value === "all"
            ? customerVisits.value
            : customerVisits.value.filter((visit) => customerVisitTab.value === (visit.planned ? "planned" : "unplanned"));
    }

    if (customerListTab.value === "planned_not_visited") {
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
            customer.customername.toLowerCase().includes(search) || String(customer.alternatecode ?? "").toLowerCase().includes(search),
    );
});

let map = null;
const customerMarkers = {};
const visitMarkers = {};
let resultLayer = null;
let plannedLineLayer = null;
let actualLineLayer = null;
let rawCoordinatesLayer = null;
let plannedCustomerLayer = null;
let customerVisitLayer = null;
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
    if (customer.type === "visit") {
        return customer.planned ? "#16a34a" : "#f97316";
    }

    return plannedNotVisitedVisible.value && !customer.visited ? "#9ca3af" : "#2563eb";
}

function visitIcon(sequence, planned) {
    const color = planned ? "#16a34a" : "#f97316";
    return L.divIcon({
        className: "route-tracking-customer-marker",
        html: `<div style="background:${color};color:#fff;border-radius:50%;width:22px;height:22px;display:flex;align-items:center;justify-content:center;font-size:11px;font-weight:bold;border:2px solid #fff;box-shadow:0 0 3px rgba(0,0,0,0.4)">${sequence}</div>`,
        iconSize: [22, 22],
        iconAnchor: [11, 11],
    });
}

function numberedIcon(sequence, customer) {
    const color = plannedNotVisitedVisible.value && !customer.visited ? "#9ca3af" : "#2563eb";
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

function rawCoordinatesStyle() {
    return {
        color: "#b45309",
        weight: 3,
        opacity: 0.9,
        dashArray: "10 8",
    };
}

function customerVisitStatus(customer) {
    if (customer.type === "visit") {
        return "Customer Visit";
    }

    if (customer.visited) {
        return `${customer.visit_count} Customer Visit${customer.visit_count === 1 ? "" : "s"}`;
    }

    return "Planned Not Visited";
}

function customerDisplayCode(customer) {
    return customer.alternatecode ?? "";
}

function customerVisitDetails(customer) {
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
    rawCoordinatesLayer = null;
    plannedCustomerLayer = null;
    customerVisitLayer = null;
    startMarker = null;
    endMarker = null;
    Object.keys(customerMarkers).forEach((key) => delete customerMarkers[key]);
    Object.keys(visitMarkers).forEach((key) => delete visitMarkers[key]);
    customerSearch.value = "";
    customerListTab.value = "all";
    customerVisitTab.value = "all";
    plannedRouteVisible.value = true;
    actualRouteVisible.value = true;
    rawCoordinatesVisible.value = false;
    plannedCustomersVisible.value = true;
    customerVisitsVisible.value = false;
    plannedNotVisitedVisible.value = false;

    const params = {
        routecode: selectedRoute.value,
        date: selectedDate.value,
    };

    try {
        const { data } = await axios.get("/route-tracking/compare.json", { params });
        result.value = data;
        const hasPlannedData = data.planned.has_planned_data;
        const hasTrackingData = data.actual.has_tracking_data;
        plannedRouteVisible.value = hasPlannedData;
        actualRouteVisible.value = hasTrackingData;
        rawCoordinatesVisible.value = false;
        plannedCustomersVisible.value = hasPlannedData;
        plannedNotVisitedVisible.value = false;
        customerVisitsVisible.value = !hasPlannedData;
        customerListTab.value = hasPlannedData ? "all" : "visits";

        resultLayer = L.featureGroup().addTo(map);
        const actualLayer = L.featureGroup().addTo(resultLayer);
        plannedCustomerLayer = L.featureGroup();
        customerVisitLayer = L.featureGroup();

        if (hasPlannedData) {
            plannedCustomerLayer.addTo(resultLayer);
        } else {
            customerVisitLayer.addTo(resultLayer);
        }

        plannedLineLayer = L.featureGroup();
        if (hasPlannedData) {
            plannedLineLayer.addTo(resultLayer);
        }
        result.value.planned.geometries.forEach((geometry) => {
            L.geoJSON(geometry, { style: plannedRouteStyle() }).addTo(plannedLineLayer);
        });

        result.value.planned.customers.forEach((customer, index) => {
            const marker = L.marker([customer.lat, customer.lng], { icon: numberedIcon(index + 1, customer) })
                .bindPopup(
                    `<strong>${index + 1}. ${customer.customername}</strong><br>Customer ${customerDisplayCode(customer)}<br>${customerVisitStatus(customer)}`,
                )
                .addTo(plannedCustomerLayer);
            customerMarkers[customer.customercode] = marker;
        });

        const plannedCustomerCodes = new Set(
            result.value.planned.customers.map((customer) => String(customer.customercode)),
        );
        result.value.planned.customer_visits.forEach((visit, index) => {
            if (visit.lat === null || visit.lng === null) {
                return;
            }

            const planned = plannedCustomerCodes.has(String(visit.customercode));
            const marker = L.marker([visit.lat, visit.lng], { icon: visitIcon(index + 1, planned) })
                .bindPopup(
                    `<strong>${index + 1}. ${visit.customername}</strong><br>Customer ${customerDisplayCode(visit)}<br>${planned ? "Planned" : "Unplanned"} Customer Visit${customerVisitDetails(visit)}`,
                )
                .addTo(customerVisitLayer);
            visitMarkers[visit.logkey] = marker;
        });

        actualLineLayer = L.featureGroup();
        if (hasTrackingData) {
            actualLineLayer.addTo(resultLayer);
        }
        result.value.actual.geometries.forEach((geometry) => {
            L.geoJSON(geometry, { style: actualRouteStyle() }).addTo(actualLineLayer);
        });

        rawCoordinatesLayer = L.featureGroup();
        if (result.value.actual.raw_geometry) {
            L.geoJSON(result.value.actual.raw_geometry, { style: rawCoordinatesStyle() }).addTo(rawCoordinatesLayer);
        }

        const { start, end } = result.value.actual;
        if (start && end) {
            startMarker = L.marker([start.lat, start.lng], { icon: flagIcon("#16a34a", "S") })
                .bindPopup(`<strong>Route Start</strong><br>${start.time ?? "Not Available"}`)
                .addTo(actualLayer);
            endMarker = L.marker([end.lat, end.lng], { icon: flagIcon("#dc2626", "L") })
                .bindPopup(`<strong>Last Known Location</strong><br>${end.time ?? ""}`)
                .addTo(actualLayer);
        }

        // Zoom to the actual start/end points (smaller, more relevant area) rather than
        // fitting both planned + actual — the planned route stays drawn but out of frame.
        const bounds = actualLayer.getBounds();
        if (bounds.isValid()) {
            map.fitBounds(bounds, { padding: [60, 60] });
        } else if (resultLayer.getBounds().isValid()) {
            map.fitBounds(resultLayer.getBounds(), { padding: [60, 60] });
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
    customerVisitTab.value = "all";
    plannedRouteVisible.value = true;
    actualRouteVisible.value = true;
    rawCoordinatesVisible.value = false;
    plannedCustomersVisible.value = true;
    customerVisitsVisible.value = false;
    plannedNotVisitedVisible.value = false;

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
    const marker = customer.type === "visit" ? visitMarkers[customer.logkey] : customerMarkers[customer.customercode];
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

function fitToVisitMarkers() {
    const markers = Object.values(visitMarkers);
    if (markers.length) {
        map.fitBounds(L.latLngBounds(markers.map((marker) => marker.getLatLng())), { padding: [60, 60] });
    }
}

function updatePlannedCustomerIcons() {
    numberedCustomers.value.forEach((customer) => {
        customerMarkers[customer.customercode]?.setIcon(numberedIcon(customer.displayNumber, customer));
    });
}

function showPlannedCustomers() {
    if (!result.value?.planned?.has_planned_data) {
        return;
    }

    if (!plannedCustomersVisible.value) {
        resultLayer.addLayer(plannedCustomerLayer);
        plannedCustomersVisible.value = true;
    }
}

function togglePlannedCustomers() {
    plannedCustomersVisible.value = !plannedCustomersVisible.value;
    plannedCustomersVisible.value
        ? resultLayer.addLayer(plannedCustomerLayer)
        : resultLayer.removeLayer(plannedCustomerLayer);
    customerListTab.value = "all";
}

function toggleCustomerVisits() {
    customerVisitsVisible.value = !customerVisitsVisible.value;
    customerVisitsVisible.value ? resultLayer.addLayer(customerVisitLayer) : resultLayer.removeLayer(customerVisitLayer);
    customerListTab.value = customerVisitsVisible.value ? "visits" : "all";

    if (customerVisitsVisible.value) {
        fitToVisitMarkers();
    } else {
        showPlannedCustomers();
    }
}

function togglePlannedNotVisited() {
    plannedNotVisitedVisible.value = !plannedNotVisitedVisible.value;
    if (plannedNotVisitedVisible.value) {
        showPlannedCustomers();
    }
    updatePlannedCustomerIcons();
    customerListTab.value = plannedNotVisitedVisible.value ? "planned_not_visited" : "all";

    if (plannedNotVisitedVisible.value) {
        fitToCustomers((customer) => !customer.visited);
    }
}

function selectCustomerTab(tab) {
    customerListTab.value = tab;

    if (tab === "visits") {
        if (!customerVisitsVisible.value) {
            customerVisitsVisible.value = true;
            resultLayer.addLayer(customerVisitLayer);
        }
        fitToVisitMarkers();
    } else if (tab === "planned_not_visited") {
        plannedNotVisitedVisible.value = true;
        showPlannedCustomers();
        updatePlannedCustomerIcons();
        fitToCustomers((customer) => !customer.visited);
    } else {
        plannedNotVisitedVisible.value = false;
        showPlannedCustomers();
        updatePlannedCustomerIcons();
        fitToCustomers(() => true);
    }
}

function selectCustomerVisitTab(tab) {
    customerVisitTab.value = tab;
}

function togglePlannedRoute() {
    if (!plannedLineLayer || !resultLayer || !map) {
        return;
    }

    plannedRouteVisible.value = !plannedRouteVisible.value;
    plannedRouteVisible.value ? resultLayer.addLayer(plannedLineLayer) : resultLayer.removeLayer(plannedLineLayer);

    const bounds = plannedLineLayer.getBounds();
    if (plannedRouteVisible.value && bounds.isValid()) {
        map.fitBounds(bounds, { padding: [60, 60] });
    }
}

function toggleActualRoute() {
    if (!actualLineLayer || !resultLayer || !map) {
        return;
    }

    actualRouteVisible.value = !actualRouteVisible.value;
    actualRouteVisible.value ? resultLayer.addLayer(actualLineLayer) : resultLayer.removeLayer(actualLineLayer);

    const bounds = actualLineLayer.getBounds();
    if (actualRouteVisible.value && bounds.isValid()) {
        map.fitBounds(bounds, { padding: [60, 60] });
    }
}

function toggleRawCoordinates() {
    if (!rawCoordinatesLayer || !resultLayer || !map) {
        return;
    }

    rawCoordinatesVisible.value = !rawCoordinatesVisible.value;
    rawCoordinatesVisible.value
        ? resultLayer.addLayer(rawCoordinatesLayer)
        : resultLayer.removeLayer(rawCoordinatesLayer);

    const bounds = rawCoordinatesLayer.getBounds();
    if (rawCoordinatesVisible.value && bounds.isValid()) {
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
            <h2 class="fs-base lh-base fw-medium text-muted mb-0">Planned route vs actual GPS points</h2>
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
                    <div>
                        Status:
                        <span :class="result.planned.route_closed ? 'text-danger' : 'text-success'">
                            {{ result.planned.route_closed ? "Closed" : "Live" }}
                        </span>
                    </div>
                    <div>{{ km(result.planned.distance) }} km</div>
                    <div>{{ minutes(result.planned.duration) }} min</div>
                    <div class="text-muted small">
                        Total Planned Customers ({{ plannedDayLabel(result.planned.day) }}): {{ customerVisitSummary.planned }}
                    </div>
                    <div v-if="result.planned.used_fallback_geometry" class="text-warning small">
                        Approximate fallback, not road routed
                    </div>
                    <div class="text-success small">Planned Customers Visited: {{ customerVisitSummary.plannedVisited }}</div>
                    <div class="small" style="color: #f97316">
                        Unplanned Customers Visited: {{ customerVisitSummary.unplannedVisited }}
                    </div>
                    <div class="text-muted small">Planned But Not Visited: {{ customerVisitSummary.plannedNotVisited }}</div>
                </div>
                <div class="col-md-4">
                    <div class="fw-bold text-danger">Actual (matched GPS)</div>
                    <div>{{ km(result.actual.distance) }} km</div>
                    <div>
                        Actual Time:
                        {{ result.actual.duration === null ? "Not Available" : `${minutes(result.actual.duration)} min` }}
                    </div>
                    <div>Total Customer Face Time: {{ minutes(result.actual.face_time) }} min</div>
                    <div>
                        Total Travel Time:
                        {{ result.actual.travel_time === null ? "Not Available" : `${minutes(result.actual.travel_time)} min` }}
                    </div>
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
                <button
                    type="button"
                    class="route-tracking-legend-item"
                    :class="{ active: plannedRouteVisible }"
                    :disabled="!result || !result.planned.has_planned_data"
                    @click="togglePlannedRoute"
                >
                    <span class="text-primary">&#9632;</span>
                    {{ result?.planned?.used_fallback_geometry ? "Planned Approx." : "Planned Route" }}
                </button>
                <button
                    type="button"
                    class="route-tracking-legend-item"
                    :class="{ active: plannedCustomersVisible }"
                    :disabled="!result || !result.planned.has_planned_data"
                    @click="togglePlannedCustomers"
                >
                    <span style="color: #2563eb">&#9679;</span> Planned Visits
                </button>
                <button
                    type="button"
                    class="route-tracking-legend-item"
                    :class="{ active: customerVisitsVisible }"
                    :disabled="!result"
                    @click="toggleCustomerVisits"
                >
                    <span style="color: #16a34a">&#9679;</span> Customer Visits
                </button>
                <button
                    type="button"
                    class="route-tracking-legend-item"
                    :class="{ active: plannedNotVisitedVisible }"
                    :disabled="!result || !result.planned.has_planned_data"
                    @click="togglePlannedNotVisited"
                >
                    <span style="color: #9ca3af">&#9679;</span> Planned Not Visited
                </button>
                <button
                    type="button"
                    class="route-tracking-legend-item"
                    :class="{ active: actualRouteVisible }"
                    :disabled="!result || !result.actual.has_tracking_data"
                    @click="toggleActualRoute"
                >
                    <span class="text-danger">&#9632;</span>
                    {{ result?.actual?.used_fallback_geometry ? "Actual Raw GPS" : "Actual Matched GPS Route" }}
                </button>
                <button
                    type="button"
                    class="route-tracking-legend-item"
                    :class="{ active: rawCoordinatesVisible }"
                    :disabled="!result || !result.actual.raw_geometry"
                    @click="toggleRawCoordinates"
                >
                    <span style="color: #b45309">&#9632;</span>
                    Raw Coordinates
                </button>
                <button
                    type="button"
                    class="route-tracking-legend-item"
                    :disabled="!result || !result.actual.has_tracking_data"
                    @click="focusStart"
                >
                    <span style="color: #16a34a">&#9632;</span> Route Start (S)
                </button>
                <button
                    type="button"
                    class="route-tracking-legend-item"
                    :disabled="!result || !result.actual.has_tracking_data"
                    @click="focusEnd"
                >
                    <span style="color: #dc2626">&#9632;</span> Last Known Location (L)
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
                                ({{ result.planned.visit_count }} visits)
                            </span>
                        </div>
                        <div class="route-tracking-customer-tabs">
                            <button
                                type="button"
                                class="route-tracking-customer-tab"
                                :class="{ active: customerListTab === 'all' }"
                                :disabled="!result || !result.planned.has_planned_data"
                                @click="selectCustomerTab('all')"
                            >
                                Planned Visits
                            </button>
                            <button
                                type="button"
                                class="route-tracking-customer-tab"
                                :class="{ active: customerListTab === 'visits' }"
                                :disabled="!result"
                                @click="selectCustomerTab('visits')"
                            >
                                Customer Visits
                            </button>
                            <button
                                type="button"
                                class="route-tracking-customer-tab"
                                :class="{ active: customerListTab === 'planned_not_visited' }"
                                :disabled="!result || !result.planned.has_planned_data"
                                @click="selectCustomerTab('planned_not_visited')"
                            >
                                Planned Not Visited
                            </button>
                        </div>
                        <div v-if="customerListTab === 'visits'" class="route-tracking-visit-tabs">
                            <button
                                v-for="tab in ['all', 'planned', 'unplanned']"
                                :key="tab"
                                type="button"
                                class="route-tracking-visit-tab"
                                :class="{ active: customerVisitTab === tab, unplanned: tab === 'unplanned' }"
                                @click="selectCustomerVisitTab(tab)"
                            >
                                {{ tab }}
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
                                    :key="customer.listKey"
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
                                            {{ customerDisplayCode(customer) }} &middot;
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

    &.active {
        background: #e8f1ff;
        border-color: #3b82f6;
        color: #212529;
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

.route-tracking-visit-tabs {
    display: flex;
    gap: 0.25rem;
    padding: 0.4rem 0.5rem;
    border-bottom: 1px solid #e5e7eb;
}

.route-tracking-visit-tab {
    flex: 1;
    border: 1px solid #dbe1e8;
    border-radius: 4px;
    background: #fff;
    padding: 0.25rem;
    color: #6c757d;
    font-size: 0.7rem;
    font-weight: 600;
    text-transform: capitalize;

    &.active {
        border-color: #16a34a;
        color: #16a34a;
        background: #f0fdf4;
    }

    &.unplanned.active {
        border-color: #f97316;
        color: #f97316;
        background: #fff7ed;
    }
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
