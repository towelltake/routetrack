<script setup>
import { computed, nextTick, onBeforeUnmount, onMounted, ref, watch } from "vue";
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
const selectedOtpVisit = ref(null);
const selectedTransactionVisit = ref(null);
const selectedTransactionType = ref("sales");
const selectedTransaction = ref(null);
const transactionDetails = ref([]);
const transactionDetailsLoading = ref(false);
const selectedCustomerKey = ref(null);
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
    return (result.value?.planned?.customer_visits ?? []).map((visit, index) => ({
        ...visit,
        displayNumber: index + 1,
        listKey: `visit-${visit.logkey}`,
        type: "visit",
    }));
});

const customerVisitSummary = computed(() => {
    const planned = result.value?.planned;

    return {
        planned: planned?.customer_count ?? 0,
        plannedVisited: planned?.visited_count ?? 0,
        unplannedVisited: planned?.unplanned_visited_count ?? 0,
        plannedNotVisited: planned?.planned_not_visited_count ?? 0,
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
        if (customerVisitTab.value === "planned") {
            return customerVisits.value.filter((visit) => visit.planned);
        }
        if (customerVisitTab.value === "unplanned") {
            return customerVisits.value.filter((visit) => visit.journey_status === customerVisitTab.value);
        }
        if (customerVisitTab.value === "out_of_sequence") {
            return customerVisits.value.filter((visit) => ["out_of_sequence", "duplicate_visit"].includes(visit.journey_status));
        }
        if (customerVisitTab.value === "otp") {
            return customerVisits.value.filter((visit) => visit.otp_logs?.length);
        }
        if (customerVisitTab.value === "transactions") {
            return customerVisits.value.filter((visit) => transactionCount(visit) > 0);
        }
        return customerVisits.value;
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
const customerItemEls = {};
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
        if (customer.journey_status === "out_of_sequence") {
            return "#f59e0b";
        }
        if (customer.journey_status === "duplicate_visit") {
            return "#7c3aed";
        }
        return customer.planned ? "#16a34a" : "#f97316";
    }

    return plannedNotVisitedVisible.value && !customer.visited ? "#9ca3af" : "#2563eb";
}

function visitIcon(sequence, visit) {
    const color = customerStatusColor({ ...visit, type: "visit" });
    const warning = ["out_of_sequence", "duplicate_visit"].includes(visit.journey_status)
        ? '<span class="route-tracking-marker-warning">!</span>'
        : "";
    return L.divIcon({
        className: "route-tracking-customer-marker",
        html: `<div class="route-tracking-visit-marker" style="background:${color}">${sequence}${warning}</div>`,
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
        return {
            according_to_plan: "According to Journey Plan",
            out_of_sequence: "Out of Sequence",
            unplanned: "Unplanned Visit",
            duplicate_visit: "Duplicate Visit · Out of Sequence",
            sequence_unavailable: "Planned · Sequence Not Available",
        }[customer.journey_status] ?? "Customer Visit";
    }

    if (customer.visited) {
        return `${customer.visit_count} Customer Visit${customer.visit_count === 1 ? "" : "s"}`;
    }

    return "Planned Not Visited";
}

function journeyPlanDetails(visit) {
    if (visit.journey_status === "unplanned") {
        return "<br>Not included in the journey plan";
    }

    const details = [
        visit.planned_sequence && `Planned Sequence: ${visit.planned_sequence}`,
        `Actual Visit Position: ${visit.actual_visit_position}`,
        visit.journey_status === "duplicate_visit" && `Visit Number for This Customer: ${visit.customer_visit_number}`,
    ].filter(Boolean);

    return details.map((line) => `<br>${line}`).join("");
}

function customerDisplayCode(customer) {
    return customer.alternatecode ?? "";
}

function faceTimeLabel(customer) {
    if (customer.visit_duration_minutes === null) {
        return "Not Available";
    }

    const hours = Math.floor(customer.visit_duration_minutes / 60);
    const minutes = customer.visit_duration_minutes % 60;

    return hours ? `${hours}h : ${String(minutes).padStart(2, "0")} min` : `${minutes} min`;
}

function faceTimeVariance(customer) {
    if (customer.visit_duration_minutes === null || customer.default_face_time_minutes == null) {
        return null;
    }

    return customer.visit_duration_minutes - customer.default_face_time_minutes;
}

function openOtpDetails(visit) {
    selectedOtpVisit.value = visit;
}

function closeOtpDetails() {
    selectedOtpVisit.value = null;
}

function transactionCount(visit, type = null) {
    if (type) {
        return visit.transactions?.[type]?.length ?? 0;
    }

    return ["sales", "orders", "collections"].reduce((total, key) => total + transactionCount(visit, key), 0);
}

function openTransactions(visit) {
    selectedTransactionVisit.value = visit;
    selectedTransactionType.value = ["sales", "orders", "collections"].find((type) => transactionCount(visit, type)) ?? "sales";
    selectedTransaction.value = null;
    transactionDetails.value = [];
}

function closeTransactions() {
    selectedTransactionVisit.value = null;
    selectedTransaction.value = null;
    transactionDetails.value = [];
}

function selectTransactionType(type) {
    selectedTransactionType.value = type;
    selectedTransaction.value = null;
    transactionDetails.value = [];
}

async function showTransactionDetails(transaction) {
    transactionDetailsLoading.value = true;
    selectedTransaction.value = transaction;
    transactionDetails.value = [];

    try {
        const { data } = await axios.get("/route-tracking/transaction-details.json", {
            params: {
                type: transaction.type,
                transactionkey: transaction.transactionkey,
                routekey: selectedTransactionVisit.value.routekey,
                visitkey: selectedTransactionVisit.value.visitkey,
            },
        });
        transactionDetails.value = data;
    } finally {
        transactionDetailsLoading.value = false;
    }
}

function money(value) {
    return Number(value ?? 0).toFixed(3);
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
    selectedOtpVisit.value = null;
    closeTransactions();
    selectedCustomerKey.value = null;
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
            marker.on("click", () => revealCustomerInList({
                ...customer,
                displayNumber: index + 1,
                listKey: `planned-${customer.customercode}`,
                type: "planned",
            }));
            customerMarkers[customer.customercode] = marker;
        });

        result.value.planned.customer_visits.forEach((visit, index) => {
            if (visit.lat === null || visit.lng === null) {
                return;
            }

            const marker = L.marker([visit.lat, visit.lng], { icon: visitIcon(index + 1, visit) })
                .bindPopup(
                    `<strong>${index + 1}. ${visit.customername}</strong><br>Customer ${customerDisplayCode(visit)}<br>Journey Plan Status: <strong>${customerVisitStatus({ ...visit, type: "visit" })}</strong>${journeyPlanDetails(visit)}${customerVisitDetails(visit)}`,
                )
                .addTo(customerVisitLayer);
            marker.on("click", () => revealCustomerInList({
                ...visit,
                displayNumber: index + 1,
                listKey: `visit-${visit.logkey}`,
                type: "visit",
            }));
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
    selectedOtpVisit.value = null;
    closeTransactions();
    selectedCustomerKey.value = null;
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
    selectedCustomerKey.value = customer.listKey;
    const marker = customer.type === "visit" ? visitMarkers[customer.logkey] : customerMarkers[customer.customercode];
    if (!marker || !map) {
        return;
    }

    map.setView(marker.getLatLng(), Math.max(map.getZoom(), 15));
    marker.openPopup();
}

function setCustomerItemRef(key, element) {
    if (element) {
        customerItemEls[key] = element;
    } else {
        delete customerItemEls[key];
    }
}

async function revealCustomerInList(customer) {
    customerSearch.value = "";
    selectedCustomerKey.value = customer.listKey;

    if (customer.type === "visit") {
        customerListTab.value = "visits";
        customerVisitTab.value = "all";
    } else {
        customerListTab.value = "all";
    }

    await nextTick();
    customerItemEls[customer.listKey]?.scrollIntoView({ behavior: "smooth", block: "center" });
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
    const visibleVisits = tabCustomers.value;
    const markers = visibleVisits.map((visit) => visitMarkers[visit.logkey]).filter(Boolean);
    if (markers.length) {
        map.fitBounds(L.latLngBounds(markers.map((marker) => marker.getLatLng())), { padding: [60, 60] });
    }
}

function customerVisitTabLabel(tab) {
    return {
        all: "All",
        planned: "Planned",
        unplanned: "Unplanned",
        out_of_sequence: "Sequence Issues",
        otp: "OTP",
        transactions: "Transactions",
    }[tab];
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

            <div ref="mapWrapperEl" class="route-tracking-view">
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
                <div class="col-md-9 route-tracking-map-column">
                    <div class="route-tracking-map-wrapper" style="position: relative; height: 680px; width: 100%">
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
                <div class="col-md-3 route-tracking-panel-column">
                    <div class="card route-tracking-customer-list-card" style="height: 680px">
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
                                Planned
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
                                Not Visited
                            </button>
                        </div>
                        <div v-if="customerListTab === 'visits'" class="route-tracking-visit-tabs">
                            <button
                                v-for="tab in ['all', 'planned', 'unplanned', 'out_of_sequence', 'otp', 'transactions']"
                                :key="tab"
                                type="button"
                                class="route-tracking-visit-tab"
                                :class="{
                                    active: customerVisitTab === tab,
                                    unplanned: tab === 'unplanned',
                                    'out-of-sequence': tab === 'out_of_sequence',
                                    otp: tab === 'otp',
                                    transactions: tab === 'transactions',
                                }"
                                @click="selectCustomerVisitTab(tab)"
                            >
                                {{ customerVisitTabLabel(tab) }}
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
                                <div
                                    v-for="customer in filteredCustomers"
                                    :key="customer.listKey"
                                    :ref="(element) => setCustomerItemRef(customer.listKey, element)"
                                    class="list-group-item list-group-item-action route-tracking-customer-item"
                                    :class="{
                                        'journey-out-of-sequence': customer.journey_status === 'out_of_sequence',
                                        'journey-unplanned': customer.journey_status === 'unplanned',
                                        'journey-duplicate': customer.journey_status === 'duplicate_visit',
                                        'journey-according': customer.journey_status === 'according_to_plan',
                                        selected: selectedCustomerKey === customer.listKey,
                                    }"
                                    role="button"
                                    tabindex="0"
                                    @click="focusCustomer(customer)"
                                    @keydown.enter="focusCustomer(customer)"
                                >
                                    <span
                                        class="route-tracking-customer-dot"
                                        :style="{ background: customerStatusColor(customer) }"
                                    >
                                        {{ customer.displayNumber }}
                                    </span>
                                    <span class="route-tracking-customer-info">
                                        <span class="route-tracking-customer-name-row">
                                            <span class="fw-semibold small">{{ customer.customername }}</span>
                                            <span v-if="customer.type === 'visit'" class="route-tracking-face-time small">
                                                <i class="fa fa-clock" title="Customer face time"></i>
                                                {{ faceTimeLabel(customer) }}
                                                <strong
                                                    v-if="faceTimeVariance(customer) !== null"
                                                    :class="faceTimeVariance(customer) > 0 ? 'text-danger' : 'text-success'"
                                                >
                                                    ({{ faceTimeVariance(customer) > 0 ? "+" : "" }}{{ faceTimeVariance(customer) }} min)
                                                </strong>
                                            </span>
                                        </span>
                                        <span class="d-block text-muted small">
                                            {{ customerDisplayCode(customer) }} &middot;
                                            {{ customerVisitStatus(customer) }}
                                        </span>
                                        <span
                                            v-if="customer.type === 'visit' && customer.journey_status === 'out_of_sequence'"
                                            class="d-block small text-warning"
                                        >
                                            Planned {{ customer.planned_sequence }} &middot; Actual {{ customer.actual_visit_position }}
                                        </span>
                                        <span
                                            v-else-if="customer.type === 'visit' && customer.journey_status === 'duplicate_visit'"
                                            class="d-block small"
                                            style="color: #7c3aed"
                                        >
                                            Visit {{ customer.customer_visit_number }} to this customer
                                        </span>
                                        <span
                                            v-else-if="customer.type === 'visit' && customer.journey_status === 'unplanned'"
                                            class="d-block small"
                                            style="color: #f97316"
                                        >
                                            Not included in journey plan
                                        </span>
                                        <span v-if="customer.type === 'visit' && transactionCount(customer)" class="route-tracking-transaction-badges">
                                            <span v-if="transactionCount(customer, 'sales')" class="sales">S {{ transactionCount(customer, "sales") }}</span>
                                            <span v-if="transactionCount(customer, 'orders')" class="orders">O {{ transactionCount(customer, "orders") }}</span>
                                            <span v-if="transactionCount(customer, 'collections')" class="collections">C {{ transactionCount(customer, "collections") }}</span>
                                        </span>
                                    </span>
                                    <button
                                        v-if="customer.type === 'visit' && transactionCount(customer)"
                                        type="button"
                                        class="btn btn-sm route-tracking-transactions-btn"
                                        title="View transactions"
                                        @click.stop="openTransactions(customer)"
                                    >
                                        <i class="fa fa-receipt"></i>
                                    </button>
                                    <button
                                        v-if="customer.type === 'visit' && customer.otp_logs?.length"
                                        type="button"
                                        class="btn btn-sm route-tracking-otp-btn"
                                        title="View GPS override OTP details"
                                        @click.stop="openOtpDetails(customer)"
                                    >
                                        <i class="fa fa-key"></i>
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div
                v-if="selectedOtpVisit"
                class="modal fade show d-block route-tracking-otp-modal"
                tabindex="-1"
                role="dialog"
                aria-modal="true"
                aria-labelledby="otp-details-title"
                @click.self="closeOtpDetails"
            >
                <div class="modal-dialog modal-dialog-centered">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h5 id="otp-details-title" class="modal-title">GPS Override OTP</h5>
                            <button type="button" class="btn-close" aria-label="Close" @click="closeOtpDetails"></button>
                        </div>
                        <div class="modal-body">
                            <div class="fw-semibold mb-2">{{ selectedOtpVisit.customername }}</div>
                            <div
                                v-for="otp in selectedOtpVisit.otp_logs"
                                :key="otp.id"
                                class="route-tracking-otp-record"
                            >
                                <div><strong>Approved By:</strong> {{ otp.approved_by || "Not Available" }}</div>
                                <div><strong>OTP Type:</strong> {{ otp.type }}</div>
                                <div><strong>Reason:</strong> {{ otp.reason || "Not Available" }}</div>
                                <div><strong>Comments:</strong> {{ otp.comments || "Not Available" }}</div>
                                <div><strong>Date &amp; Time:</strong> {{ otp.date }} {{ otp.time }}</div>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" @click="closeOtpDetails">Close</button>
                        </div>
                    </div>
                </div>
            </div>
            <div
                v-if="selectedTransactionVisit"
                class="modal fade show d-block route-tracking-otp-modal"
                tabindex="-1"
                role="dialog"
                aria-modal="true"
                aria-labelledby="transaction-details-title"
                @click.self="closeTransactions"
            >
                <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable">
                    <div class="modal-content">
                        <div class="modal-header">
                            <div>
                                <h5 id="transaction-details-title" class="modal-title">Customer Transactions</h5>
                                <div class="small text-muted">
                                    {{ selectedTransactionVisit.customername }} &middot;
                                    Visit {{ selectedTransactionVisit.visit_start_time }}–{{ selectedTransactionVisit.visit_end_time || "Open" }}
                                </div>
                            </div>
                            <button type="button" class="btn-close" aria-label="Close" @click="closeTransactions"></button>
                        </div>
                        <div class="modal-body">
                            <template v-if="!selectedTransaction">
                                <div class="nav nav-tabs mb-3">
                                    <button
                                        v-for="type in ['sales', 'orders', 'collections']"
                                        :key="type"
                                        type="button"
                                        class="nav-link text-capitalize"
                                        :class="{ active: selectedTransactionType === type }"
                                        @click="selectTransactionType(type)"
                                    >
                                        {{ type }} ({{ transactionCount(selectedTransactionVisit, type) }})
                                    </button>
                                </div>
                                <div v-if="!transactionCount(selectedTransactionVisit, selectedTransactionType)" class="text-muted">
                                    No {{ selectedTransactionType }} for this visit.
                                </div>
                                <div
                                    v-for="transaction in selectedTransactionVisit.transactions[selectedTransactionType]"
                                    :key="transaction.transactionkey"
                                    class="route-tracking-transaction-record"
                                >
                                    <div>
                                        <strong>Document {{ transaction.documentnumber }}</strong>
                                        <span v-if="transaction.voided" class="badge bg-danger ms-2">Voided</span>
                                        <div class="small text-muted">{{ transaction.date }} {{ transaction.time }}</div>
                                    </div>
                                    <div class="route-tracking-transaction-amount">{{ money(transaction.amount) }}</div>
                                    <button type="button" class="btn btn-sm btn-outline-primary" @click="showTransactionDetails(transaction)">
                                        View Details
                                    </button>
                                </div>
                            </template>
                            <template v-else>
                                <button type="button" class="btn btn-sm btn-light mb-3" @click="selectTransactionType(selectedTransactionType)">
                                    <i class="fa fa-arrow-left me-1"></i> Back
                                </button>
                                <h6>Document {{ selectedTransaction.documentnumber }}</h6>
                                <div v-if="transactionDetailsLoading" class="text-muted">Loading details...</div>
                                <div v-else-if="!transactionDetails.length" class="text-muted">No detail records available.</div>
                                <div v-else class="table-responsive">
                                    <table class="table table-sm table-striped align-middle">
                                        <thead>
                                            <tr v-if="selectedTransaction.type === 'collections'">
                                                <th>Invoice</th><th>Date</th><th>Reference</th><th class="text-end">Invoice</th><th class="text-end">Paid</th><th class="text-end">Balance</th>
                                            </tr>
                                            <tr v-else>
                                                <th>Item</th><th>Description</th><th class="text-end">Sales Qty</th><th class="text-end">Return Qty</th><th class="text-end">Damaged</th><th class="text-end">Free</th><th class="text-end">Price</th><th class="text-end">Amount</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <template v-if="selectedTransaction.type === 'collections'">
                                                <tr v-for="(detail, index) in transactionDetails" :key="index">
                                                    <td>{{ detail.alternateinvoicenumber || detail.invoicenumber }}</td>
                                                    <td>{{ detail.invoicedate }}</td>
                                                    <td>{{ detail.referenceno || "-" }}</td>
                                                    <td class="text-end">{{ money(detail.totalinvoiceamount) }}</td>
                                                    <td class="text-end">{{ money(detail.amountpaid) }}</td>
                                                    <td class="text-end">{{ money(detail.invoicebalance) }}</td>
                                                </tr>
                                            </template>
                                            <template v-else>
                                                <tr v-for="detail in transactionDetails" :key="detail.itemcode">
                                                    <td>{{ detail.alternatecode || detail.itemcode }}</td>
                                                    <td>{{ detail.itemdescription || "-" }}</td>
                                                    <td class="text-end">{{ detail.salesqty }}</td>
                                                    <td class="text-end">{{ detail.returnqty }}</td>
                                                    <td class="text-end">{{ detail.damagedqty }}</td>
                                                    <td class="text-end">{{ detail.freesampleqty }}</td>
                                                    <td class="text-end">{{ money(detail.salesprice) }}</td>
                                                    <td class="text-end">{{ money(detail.sales_amount) }}</td>
                                                </tr>
                                            </template>
                                        </tbody>
                                    </table>
                                </div>
                            </template>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" @click="closeTransactions">Close</button>
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

.route-tracking-view:fullscreen {
    display: flex;
    flex-direction: column;
    width: 100vw;
    height: 100vh;
    padding: 0.75rem;
    background: #fff;

    > .row {
        flex: 1;
        min-height: 0;
    }

    > .row > [class*="col-"] {
        height: 100%;
    }

    .route-tracking-map-wrapper,
    .route-tracking-customer-list-card {
        height: 100% !important;
    }
}

@media (min-width: 768px) {
    .route-tracking-map-column {
        width: 72%;
    }

    .route-tracking-panel-column {
        width: 28%;
    }
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
    gap: 0.25rem;
    padding: 0.4rem;
    border-bottom: 1px solid #e5e7eb;
    background: #f8fafc;
}

.route-tracking-customer-tab {
    flex: 1;
    background: #fff;
    border: 1px solid transparent;
    border-radius: 5px;
    padding: 0.5rem 0.25rem;
    font-size: 0.75rem;
    font-weight: 600;
    color: #6c757d;
    cursor: pointer;
    text-align: center;

    &.active {
        background: #fff;
        border-color: #dbeafe;
        color: #3b82f6;
        box-shadow: 0 1px 3px rgba(15, 23, 42, 0.08);
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
    flex-wrap: wrap;
    gap: 0.25rem;
    padding: 0.5rem;
    border-bottom: 1px solid #e5e7eb;
}

.route-tracking-visit-tab {
    flex: 0 0 auto;
    border: 1px solid #dbe1e8;
    border-radius: 999px;
    background: #fff;
    padding: 0.25rem 0.55rem;
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

    &.out-of-sequence.active {
        border-color: #f59e0b;
        color: #b45309;
        background: #fffbeb;
    }

    &.otp.active {
        border-color: #7c3aed;
        color: #7c3aed;
        background: #f5f3ff;
    }

    &.transactions.active {
        border-color: #0f766e;
        color: #0f766e;
        background: #f0fdfa;
    }
}

.route-tracking-customer-list-card .card-body {
    display: flex;
    flex-direction: column;
    min-height: 0;
    flex: 1;
}

.route-tracking-customer-list {
    display: flex;
    flex-direction: column;
    gap: 0.35rem;
    overflow-y: auto;
    flex: 1;
    padding: 1px;
}

.route-tracking-customer-item {
    display: flex;
    align-items: center;
    gap: 8px;
    border: 1px solid #e5e7eb !important;
    border-radius: 6px !important;
    background: #fff;
    padding: 0.7rem 0.75rem;
    text-align: left;
    transition: box-shadow 0.15s ease, transform 0.15s ease;

    &:hover {
        box-shadow: 0 3px 9px rgba(15, 23, 42, 0.09);
    }

    &.journey-out-of-sequence {
        background: #fffbeb;
    }

    &.journey-unplanned {
        background: #fff7ed;
    }

    &.journey-duplicate {
        background: #f5f3ff;
    }

    &.selected {
        transform: translateX(3px);
        box-shadow: 0 4px 12px rgba(15, 23, 42, 0.18);
    }
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

.route-tracking-visit-marker {
    position: relative;
    display: flex;
    align-items: center;
    justify-content: center;
    width: 22px;
    height: 22px;
    border: 2px solid #fff;
    border-radius: 50%;
    color: #fff;
    box-shadow: 0 0 3px rgba(0, 0, 0, 0.4);
    font-size: 11px;
    font-weight: 700;
}

.route-tracking-marker-warning {
    position: absolute;
    top: -7px;
    right: -7px;
    display: flex;
    align-items: center;
    justify-content: center;
    width: 13px;
    height: 13px;
    border: 1px solid #fff;
    border-radius: 50%;
    background: #dc2626;
    color: #fff;
    font-size: 9px;
    line-height: 1;
}

.route-tracking-customer-info {
    min-width: 0;
    overflow: hidden;
}

.route-tracking-otp-btn {
    flex-shrink: 0;
    border: 1px solid #7c3aed;
    color: #7c3aed;
    font-size: 0.65rem;
    font-weight: 700;

    &:hover {
        background: #7c3aed;
        color: #fff;
    }
}

.route-tracking-transactions-btn {
    flex-shrink: 0;
    border: 1px solid #0f766e;
    color: #0f766e;

    &:hover {
        background: #0f766e;
        color: #fff;
    }
}

.route-tracking-transaction-badges {
    display: flex;
    gap: 0.35rem;
    margin-top: 0.3rem;
    padding-bottom: 0.35rem;

    span {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        min-width: 30px;
        padding: 0.2rem 0.45rem;
        border-radius: 4px;
        color: #fff;
        font-size: 0.6rem;
        font-weight: 700;
        line-height: 1.2;
    }

    .sales {
        background: #16a34a;
    }

    .orders {
        background: #2563eb;
    }

    .collections {
        background: #7c3aed;
    }
}

.route-tracking-transaction-record {
    display: grid;
    grid-template-columns: minmax(0, 1fr) auto auto;
    align-items: center;
    gap: 1rem;
    padding: 0.75rem;
    border: 1px solid #e5e7eb;
    border-radius: 5px;
    margin-bottom: 0.5rem;
}

.route-tracking-transaction-amount {
    font-weight: 700;
    text-align: right;
}

.route-tracking-otp-modal {
    z-index: 2000;
    background: rgba(0, 0, 0, 0.5);
}

.route-tracking-otp-record {
    padding: 0.65rem 0;
    border-bottom: 1px solid #e5e7eb;

    &:last-child {
        padding-bottom: 0;
        border-bottom: 0;
    }
}

.route-tracking-customer-info span {
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}

.route-tracking-customer-name-row {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 0.5rem;

    > :first-child {
        min-width: 0;
        overflow: hidden;
        text-overflow: ellipsis;
        white-space: nowrap;
    }
}

.route-tracking-face-time {
    flex-shrink: 0;
    color: #495057;
    text-align: right;
}
</style>
