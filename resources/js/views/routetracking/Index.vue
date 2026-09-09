<script setup>
import { computed, nextTick, onBeforeUnmount, onMounted, ref, watch } from "vue";
import { Head } from "@inertiajs/vue3";
import { useTemplateStore } from "@/stores/template";
import axios from "axios";
import L from "leaflet";
import "leaflet/dist/leaflet.css";
import VueSelect from "vue-select";
import { filterFields, filterOptions } from "@/views/routelocation/filters";

const OMAN_BOUNDS = L.latLngBounds([16.0, 51.5], [27.0, 60.5]);
const store = useTemplateStore();

const mapWrapperEl = ref(null);
const mapEl = ref(null);
const scopeFields = filterFields.filter(({ key }) => key !== "routes");
const routeField = filterFields.find(({ key }) => key === "routes");
const filterRows = ref([]);
const selectedScopes = ref(Object.fromEntries(scopeFields.map(({ key }) => [key, []])));
const filtersReady = ref(false);
const selectedRoute = ref(null);
const filterSelection = computed(() => ({
    ...selectedScopes.value,
    routes: selectedRoute.value ? [selectedRoute.value] : [],
}));
const scopeOptions = computed(() => Object.fromEntries(scopeFields.map((field) => [
    field.key, filterOptions(filterRows.value, filterSelection.value, field),
])));
const routeOptions = computed(() => filterOptions(filterRows.value, { ...selectedScopes.value, routes: [] }, routeField));
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
const summaryModal = ref(null);
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
const stationaryVisible = ref(false);
const gpsGapsVisible = ref(false);

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

const stationaryPeriods = computed(() =>
    (result.value?.actual?.stationary_periods ?? []).map((period, index) => ({
        ...period,
        displayNumber: index + 1,
        listKey: `stationary-${index}`,
        markerIndex: index,
    })),
);

const gpsGaps = computed(() =>
    (result.value?.actual?.gps_gaps ?? []).map((gap, index) => ({
        ...gap,
        displayNumber: index + 1,
        listKey: `gps-gap-${index}`,
        markerIndex: index,
    })),
);

const customerVisitSummary = computed(() => {
    const planned = result.value?.planned;

    return {
        planned: planned?.customer_count ?? 0,
        plannedVisited: planned?.visited_count ?? 0,
        unplannedVisited: planned?.unplanned_visited_count ?? 0,
        plannedNotVisited: planned?.planned_not_visited_count ?? 0,
    };
});

const routeHeading = computed(() => {
    const details = result.value?.planned?.route_details;
    if (!details) return selectedRoute.value ? `Route ${selectedRoute.value} · Salesman not available` : "Select a route to view tracking";
    const salesman = details.salesmanname || (details.salesmancode ? `Salesman ${details.salesmancode}` : "Salesman not available");
    return `Route ${details.routecode} · ${salesman}`;
});

const summaryTransactions = computed(() => {
    const rows = { sales: [], orders: [], collections: [], returns: [] };
    const seen = new Set();
    for (const visit of customerVisits.value) {
        for (const type of ["sales", "orders", "collections"]) {
            for (const transaction of visit.transactions?.[type] ?? []) {
                const key = `${type}:${transaction.transactionkey}`;
                if (transaction.voided || seen.has(key)) continue;
                seen.add(key);
                const row = { ...transaction, customername: visit.customername, alternatecode: visit.alternatecode };
                rows[type].push(row);
                if (["sales", "orders"].includes(type) && Number(transaction.return_amount) > 0) {
                    rows.returns.push({ ...row, amount: transaction.return_amount });
                }
            }
        }
    }
    return rows;
});

const routeSummaryGroups = computed(() => {
    if (!result.value) return [];
    const planned = result.value.planned;
    const actual = result.value.actual;
    const transactions = result.value.transactions ?? {};
    const transactionCard = (label, type, icon, tone) => {
        const count = transactions[type]?.count ?? 0;
        return {
            label, icon, tone, action: type,
            value: money(type === "returns" ? -Math.abs(transactions[type]?.amount ?? 0) : transactions[type]?.amount),
            meta: `${count} ${count === 1 ? "document" : "documents"}`,
        };
    };
    const actualSeconds = Number(actual.duration) || 0;

    return [
        { key: "route", title: "Route", cards: [
            { label: "Route Status", icon: "fa-flag-checkered", tone: planned.route_closed ? "red" : "green", action: "route", value: planned.route_closed ? "Closed" : "Live", meta: "View journey details" },
        ] },
        { key: "customers", title: "Customers", cards: [
            { label: "Customer Coverage", icon: "fa-store", tone: "blue", action: "customers", value: pct(customerVisitSummary.value.planned ? customerVisitSummary.value.plannedVisited / customerVisitSummary.value.planned : null), meta: `${customerVisitSummary.value.plannedVisited} of ${customerVisitSummary.value.planned} visited · ${customerVisitSummary.value.plannedNotVisited} pending` },
            { label: "Unplanned Visits", icon: "fa-location-dot", tone: "orange", action: "unplanned", value: customerVisitSummary.value.unplannedVisited, meta: "View customer list" },
            { label: "OTP Requests", icon: "fa-key", tone: "purple", action: "otp", value: planned.otp_logs?.length ?? 0, meta: "View all requests" },
        ] },
        { key: "distance", title: "Distance", cards: [
            { label: "Planned Distance", icon: "fa-road", tone: "blue", value: `${km(planned.distance)} km`, meta: "" },
            { label: "Actual Distance", icon: "fa-location-arrow", tone: "red", value: `${km(actual.distance)} km`, meta: `${pct(result.value.distance_ratio)} of plan · ${actual.point_count} points` },
        ] },
        { key: "time", title: "Time", cards: [
            { label: "Actual Duration", icon: "fa-clock", tone: "navy", value: actual.duration === null ? "N/A" : stationaryDuration(actual.duration), meta: "Route start to route end" },
            { label: "Actual Face Time", icon: "fa-user-clock", tone: "green", value: stationaryDuration(actual.face_time), meta: `Planned ${stationaryDuration(planned.face_time)} · ${pct(planned.face_time ? actual.face_time / planned.face_time : null)} achieved` },
            { label: "Travel Time", icon: "fa-car", tone: "slate", value: actual.travel_time === null ? "N/A" : stationaryDuration(actual.travel_time), meta: `${pct(actualSeconds ? actual.travel_time / actualSeconds : null)} of actual time` },
            { label: "Idle Time", icon: "fa-pause", tone: "red", value: stationaryDuration(actual.idle_seconds), meta: `${actual.idle_periods?.length ?? 0} stops outside customer visits · ${pct(actualSeconds ? actual.idle_seconds / actualSeconds : null)}` },
        ] },
        { key: "transactions", title: "Transactions", cards: [
            transactionCard("Sales", "sales", "fa-file-invoice-dollar", "green"),
            transactionCard("Orders", "orders", "fa-cart-shopping", "blue"),
            transactionCard("Collections", "collections", "fa-hand-holding-dollar", "navy"),
            transactionCard("Returns", "returns", "fa-rotate-left", "red"),
        ] },
    ];
});

const summaryTitle = computed(() => ({
    route: "Route Journey Details",
    customers: "Customer Coverage",
    unplanned: "Unplanned Visits",
    otp: "OTP Requests",
    sales: "Sales",
    orders: "Orders",
    collections: "Collections",
    returns: "Returns",
}[summaryModal.value] ?? "Route Summary"));

const summaryCustomers = computed(() => summaryModal.value === "unplanned"
    ? customerVisits.value.filter((visit) => visit.journey_status === "unplanned")
    : numberedCustomers.value);

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
const stationaryMarkers = [];
const gpsGapMarkers = [];
const customerItemEls = {};
let resultLayer = null;
let plannedLineLayer = null;
let actualLineLayer = null;
let rawCoordinatesLayer = null;
let plannedCustomerLayer = null;
let customerVisitLayer = null;
let stationaryLayer = null;
let gpsGapLayer = null;
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
        const { data } = await axios.get("/route-tracking/filters.json");
        filterRows.value = data;
        filtersReady.value = true;

        // Deep-link support: Route Location's "Track" button sends a route + date
        // here via query params so the comparison runs immediately on arrival.
        const params = new URLSearchParams(window.location.search);
        const linkedRoute = params.get("routecode");
        const linkedDate = params.get("date");

        if (linkedRoute && routeOptions.value.some((route) => String(route.value) === String(linkedRoute))) {
            selectedRoute.value = Number(linkedRoute);
            if (linkedDate) {
                selectedDate.value = linkedDate;
            }
            await runComparison();
        }
    } catch {
        error.value = "Unable to load route filters. Refresh the page to retry.";
    } finally {
        store.pageLoader({ mode: "off" });
    }

    document.addEventListener("fullscreenchange", () => {
        isFullscreen.value = document.fullscreenElement === mapWrapperEl.value;
        setTimeout(() => map.invalidateSize(), 0);
    });
});

onBeforeUnmount(() => store.pageLoader({ mode: "off" }));

watch(selectedScopes, () => {
    if (selectedRoute.value && !routeOptions.value.some(({ value }) => String(value) === String(selectedRoute.value))) {
        selectedRoute.value = null;
    }
}, { deep: true });

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
        return customer.planned ? "#16a34a" : "#c2410c";
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

function openSummary(action) {
    if (action) summaryModal.value = action;
}

function closeSummary() {
    summaryModal.value = null;
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
    stationaryMarkers.length = 0;
    gpsGapMarkers.length = 0;
    customerSearch.value = "";
    customerListTab.value = "all";
    customerVisitTab.value = "all";
    selectedOtpVisit.value = null;
    closeSummary();
    closeTransactions();
    selectedCustomerKey.value = null;
    plannedRouteVisible.value = true;
    actualRouteVisible.value = true;
    rawCoordinatesVisible.value = false;
    plannedCustomersVisible.value = true;
    customerVisitsVisible.value = false;
    plannedNotVisitedVisible.value = false;
    stationaryVisible.value = false;
    gpsGapsVisible.value = false;

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
        stationaryLayer = L.featureGroup();
        (data.actual.stationary_periods ?? []).forEach((period, index) => {
            const popup = document.createElement('div');
            const heading = document.createElement('strong');
            heading.textContent = `Stationary: ${minutes(period.duration_seconds)} min`;
            popup.append(heading);
            const visits = period.customer_visits ?? [];
            const lines = [
                `From: ${period.start_time}`,
                `To: ${period.end_time} (last observed stationary)`,
                visits.length ? `Overlaps recorded customer visit: ${visits.map((visit) => visit.customername).join(', ')}` : 'No completed customer visit overlap recorded',
                ...(period.accuracy_unknown ? ['GPS accuracy unknown for some readings'] : []),
            ];
            lines.forEach((text) => {
                const line = document.createElement('div');
                line.textContent = text;
                popup.append(line);
            });
            const outsideCustomerVisit = !visits.length;
            const circle = L.circle([period.lat, period.lng], {
                radius: period.radius_m, stroke: false,
                fillColor: outsideCustomerVisit ? '#ef4444' : '#fbbf24', fillOpacity: 0.2,
            }).bindPopup(popup).addTo(stationaryLayer);
            circle.bindTooltip(`Stationary ${minutes(period.duration_seconds)} min`);
            circle.on("click", () => revealStationaryInList(index));
            stationaryMarkers[index] = circle;
        });
        gpsGapLayer = L.featureGroup();
        (data.actual.gps_gaps ?? []).forEach((gap, index) => {
            const popup = document.createElement("div");
            const heading = document.createElement("strong");
            heading.textContent = `GPS unavailable: ${stationaryDuration(gap.duration_seconds)}`;
            popup.append(heading);
            [
                `Last usable GPS: ${gap.start_time}`,
                `GPS restored: ${gap.end_time}`,
                "Reason unknown: coverage, device, permission, or GPS may have been unavailable.",
            ].forEach((text) => {
                const line = document.createElement("div");
                line.textContent = text;
                popup.append(line);
            });
            const marker = L.marker([gap.lat, gap.lng], { icon: flagIcon("#dc2626", "!") })
                .bindPopup(popup)
                .bindTooltip(`GPS unavailable ${stationaryDuration(gap.duration_seconds)}`)
                .addTo(gpsGapLayer);
            marker.on("click", () => revealGpsGapInList(index));
            gpsGapMarkers[index] = marker;
        });
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
    selectedScopes.value = Object.fromEntries(scopeFields.map(({ key }) => [key, []]));
    selectedRoute.value = null;
    selectedDate.value = DEFAULT_DATE;
    error.value = null;
    result.value = null;
    customerSearch.value = "";
    customerListTab.value = "all";
    customerVisitTab.value = "all";
    selectedOtpVisit.value = null;
    closeSummary();
    closeTransactions();
    selectedCustomerKey.value = null;
    plannedRouteVisible.value = true;
    actualRouteVisible.value = true;
    rawCoordinatesVisible.value = false;
    plannedCustomersVisible.value = true;
    customerVisitsVisible.value = false;
    plannedNotVisitedVisible.value = false;
    stationaryVisible.value = false;
    gpsGapsVisible.value = false;
    stationaryMarkers.length = 0;
    gpsGapMarkers.length = 0;

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

function stationaryDuration(seconds) {
    const total = minutes(seconds);
    return total < 60 ? `${total} min` : `${Math.floor(total / 60)}h ${total % 60}m`;
}

function stationaryTime(timestamp) {
    return String(timestamp ?? "").slice(11, 16) || "—";
}

function toggleStationary() {
    if (!resultLayer || !stationaryLayer) return;
    stationaryVisible.value = !stationaryVisible.value;
    stationaryVisible.value ? resultLayer.addLayer(stationaryLayer) : resultLayer.removeLayer(stationaryLayer);
}

function toggleGpsGaps() {
    if (!resultLayer || !gpsGapLayer) return;
    gpsGapsVisible.value = !gpsGapsVisible.value;
    gpsGapsVisible.value ? resultLayer.addLayer(gpsGapLayer) : resultLayer.removeLayer(gpsGapLayer);
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

function focusStationary(period) {
    selectedCustomerKey.value = period.listKey;
    const marker = stationaryMarkers[period.markerIndex];
    if (!marker || !map) return;
    if (!stationaryVisible.value) toggleStationary();
    map.setView(marker.getLatLng(), Math.max(map.getZoom(), 16));
    marker.openPopup();
}

function focusGpsGap(gap) {
    selectedCustomerKey.value = gap.listKey;
    const marker = gpsGapMarkers[gap.markerIndex];
    if (!marker || !map) return;
    if (!gpsGapsVisible.value) toggleGpsGaps();
    map.setView(marker.getLatLng(), Math.max(map.getZoom(), 16));
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

async function revealStationaryInList(index) {
    customerListTab.value = "stationary";
    selectedCustomerKey.value = `stationary-${index}`;
    await nextTick();
    customerItemEls[`stationary-${index}`]?.scrollIntoView({ behavior: "smooth", block: "center" });
}

async function revealGpsGapInList(index) {
    customerListTab.value = "gps_gaps";
    selectedCustomerKey.value = `gps-gap-${index}`;
    await nextTick();
    customerItemEls[`gps-gap-${index}`]?.scrollIntoView({ behavior: "smooth", block: "center" });
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

    if (!customerVisitsVisible.value) {
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

}

function selectCustomerTab(tab) {
    customerListTab.value = tab;

    if (tab === "gps_gaps") {
        if (!gpsGapsVisible.value) toggleGpsGaps();
    } else if (tab === "stationary") {
        if (!stationaryVisible.value) toggleStationary();
    } else if (tab === "visits") {
        if (!customerVisitsVisible.value) {
            customerVisitsVisible.value = true;
            resultLayer.addLayer(customerVisitLayer);
        }
    } else if (tab === "planned_not_visited") {
        plannedNotVisitedVisible.value = true;
        showPlannedCustomers();
        updatePlannedCustomerIcons();
    } else {
        plannedNotVisitedVisible.value = false;
        showPlannedCustomers();
        updatePlannedCustomerIcons();
    }
}

function selectCustomerVisitTab(tab) {
    customerVisitTab.value = tab;
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
}

function toggleActualRoute() {
    if (!actualLineLayer || !resultLayer || !map) {
        return;
    }

    actualRouteVisible.value = !actualRouteVisible.value;
    actualRouteVisible.value ? resultLayer.addLayer(actualLineLayer) : resultLayer.removeLayer(actualLineLayer);
}

function toggleRawCoordinates() {
    if (!rawCoordinatesLayer || !resultLayer || !map) {
        return;
    }

    rawCoordinatesVisible.value = !rawCoordinatesVisible.value;
    rawCoordinatesVisible.value
        ? resultLayer.addLayer(rawCoordinatesLayer)
        : resultLayer.removeLayer(rawCoordinatesLayer);
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

        <section class="tracking-filters" aria-labelledby="tracking-filters-title">
            <header class="tracking-filters-header">
                <div class="tracking-filters-heading">
                    <span class="tracking-filter-icon"><i class="fa fa-route" aria-hidden="true"></i></span>
                    <div>
                        <h2 id="tracking-filters-title">Choose a route journey</h2>
                        <p>Narrow the organisation, then select one route and operation date.</p>
                    </div>
                </div>
            </header>

            <div class="tracking-filter-grid">
                <div v-for="field in scopeFields" :key="field.key" class="tracking-filter-field">
                    <label :for="`tracking-${field.key}`">
                        {{ field.label }}
                        <span v-if="selectedScopes[field.key].length" class="tracking-selection-count">{{ selectedScopes[field.key].length }}</span>
                    </label>
                    <VueSelect
                        :input-id="`tracking-${field.key}`"
                        v-model="selectedScopes[field.key]"
                        :options="scopeOptions[field.key]"
                        :reduce="(option) => option.value"
                        label="label"
                        multiple
                        :close-on-select="false"
                        :disabled="!filtersReady"
                        :placeholder="`All ${field.label.toLowerCase()}...`"
                    />
                </div>
                <div class="tracking-filter-field tracking-route-field">
                    <label for="tracking-route">Route <span class="tracking-required">Required</span></label>
                    <VueSelect
                        input-id="tracking-route"
                        v-model="selectedRoute"
                        :options="routeOptions"
                        :reduce="(option) => option.value"
                        label="label"
                        :disabled="!filtersReady"
                        placeholder="Select one route..."
                    />
                </div>
                <div class="tracking-filter-field">
                    <label for="tracking-date">Operation Date <span class="tracking-required">Required</span></label>
                    <input id="tracking-date" v-model="selectedDate" type="date" />
                </div>
            </div>
            <div class="tracking-filter-actions">
                <button type="button" class="tracking-reset" :disabled="loading" @click="resetFilters">Reset</button>
                <button type="button" class="tracking-apply" :disabled="loading || !filtersReady || !selectedRoute || !selectedDate" @click="runComparison">
                    <i class="fa fa-magnifying-glass" aria-hidden="true"></i>
                    {{ loading ? "Loading..." : "Show route" }}
                </button>
            </div>
        </section>

        <BaseBlock :title="routeHeading" :mode-loading="loading">
            <p v-if="error" class="text-danger">{{ error }}</p>

            <div v-if="routeQualityWarnings.length" class="alert alert-warning py-2">
                <div v-for="warning in routeQualityWarnings" :key="warning">{{ warning }}</div>
            </div>

            <section v-if="result" class="route-summary-groups" aria-label="Route journey summary">
                <div v-for="group in routeSummaryGroups" :key="group.key" class="route-summary-group" :class="`route-summary-group-${group.key}`">
                    <h3>{{ group.title }}</h3>
                    <div class="route-summary-cards">
                        <button
                            v-for="card in group.cards"
                            :key="card.label"
                            type="button"
                            class="route-summary-card"
                            :class="[`tone-${card.tone}`, { clickable: card.action }]"
                            :disabled="!card.action"
                            @click="openSummary(card.action)"
                        >
                            <span class="route-summary-icon"><i class="fa" :class="card.icon" aria-hidden="true"></i></span>
                            <span class="route-summary-copy">
                                <span class="route-summary-label">{{ card.label }}</span>
                                <strong>{{ card.value }}</strong>
                                <span v-if="card.meta">{{ card.meta }}</span>
                            </span>
                            <i v-if="card.action" class="fa fa-chevron-right route-summary-open" aria-hidden="true"></i>
                        </button>
                    </div>
                </div>
            </section>

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
                <button type="button" class="route-tracking-legend-item"
                    :class="{ active: stationaryVisible }"
                    :aria-pressed="stationaryVisible"
                    :disabled="!result?.actual?.stationary_periods?.length"
                    @click="toggleStationary">
                    <span style="color: #b45309">&#9679;</span>
                    Stationary
                </button>
                <button type="button" class="route-tracking-legend-item"
                    :class="{ active: gpsGapsVisible }"
                    :aria-pressed="gpsGapsVisible"
                    :disabled="!gpsGaps.length"
                    @click="toggleGpsGaps">
                    <span class="text-danger">&#9873;</span>
                    GPS Unavailable
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
                        <div class="card-header route-tracking-panel-header">
                            <div>
                            <strong>{{ customerListTab === 'gps_gaps' ? 'GPS unavailable' : customerListTab === 'stationary' ? 'Stationary periods' : 'Customers' }}</strong>
                            <span v-if="result" class="text-muted small">
                                ({{ customerListTab === 'gps_gaps' ? `${gpsGaps.length} gaps` : customerListTab === 'stationary' ? `${stationaryPeriods.length} stops` : `${result.planned.visit_count} visits` }})
                            </span>
                            </div>
                            <select
                                class="route-tracking-more"
                                aria-label="More tracking details"
                                :value="['stationary', 'gps_gaps'].includes(customerListTab) ? customerListTab : ''"
                                :disabled="!result"
                                @change="selectCustomerTab($event.target.value)"
                            >
                                <option value="" disabled>More</option>
                                <option value="stationary" :disabled="!stationaryPeriods.length">Stationary</option>
                                <option value="gps_gaps" :disabled="!gpsGaps.length">GPS Gaps</option>
                            </select>
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
                        <div v-if="!['stationary', 'gps_gaps'].includes(customerListTab)" class="route-tracking-customer-search p-2">
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
                                <template v-if="customerListTab === 'gps_gaps'">
                                    <p v-if="!gpsGaps.length" class="text-muted small px-1">No GPS gaps detected.</p>
                                    <button
                                        v-for="gap in gpsGaps"
                                        :key="gap.listKey"
                                        :ref="(element) => setCustomerItemRef(gap.listKey, element)"
                                        type="button"
                                        class="list-group-item list-group-item-action route-tracking-customer-item route-tracking-gps-gap-item"
                                        :class="{ selected: selectedCustomerKey === gap.listKey }"
                                        @click="focusGpsGap(gap)"
                                    >
                                        <span class="route-tracking-gps-gap-flag">!</span>
                                        <span class="route-tracking-customer-info">
                                            <span class="route-tracking-customer-name-row">
                                                <span class="fw-semibold text-danger">{{ stationaryDuration(gap.duration_seconds) }}</span>
                                                <span class="small text-muted">{{ stationaryTime(gap.start_time) }}–{{ stationaryTime(gap.end_time) }}</span>
                                            </span>
                                            <span class="d-block small">GPS unavailable between usable readings</span>
                                            <span class="d-block small text-muted">Reason cannot be determined from GPS data</span>
                                        </span>
                                        <i class="fa fa-location-dot text-danger" aria-hidden="true"></i>
                                    </button>
                                </template>
                                <template v-else-if="customerListTab === 'stationary'">
                                    <p v-if="!stationaryPeriods.length" class="text-muted small px-1">No stationary periods detected.</p>
                                    <button
                                        v-for="period in stationaryPeriods"
                                        :key="period.listKey"
                                        :ref="(element) => setCustomerItemRef(period.listKey, element)"
                                        type="button"
                                        class="list-group-item list-group-item-action route-tracking-customer-item route-tracking-stationary-item"
                                        :class="{
                                            selected: selectedCustomerKey === period.listKey,
                                            'outside-customer': !period.customer_visits?.length,
                                        }"
                                        @click="focusStationary(period)"
                                    >
                                        <span class="route-tracking-stationary-dot" :class="{ 'outside-customer': !period.customer_visits?.length }">
                                            {{ period.displayNumber }}
                                        </span>
                                        <span class="route-tracking-customer-info">
                                            <span class="route-tracking-customer-name-row">
                                                <span class="fw-semibold">{{ stationaryDuration(period.duration_seconds) }}</span>
                                                <span class="small text-muted">{{ stationaryTime(period.start_time) }}–{{ stationaryTime(period.end_time) }}</span>
                                            </span>
                                            <span class="d-block small text-muted">
                                                {{ period.customer_visits?.length
                                                    ? `At customer: ${period.customer_visits.map((visit) => visit.customername).join(', ')}`
                                                    : 'Outside completed customer visits' }}
                                            </span>
                                            <span v-if="period.accuracy_unknown" class="d-block small text-warning">GPS accuracy unknown</span>
                                        </span>
                                        <i
                                            class="fa fa-location-dot"
                                            :class="period.customer_visits?.length ? 'text-warning' : 'text-danger'"
                                            aria-hidden="true"
                                        ></i>
                                    </button>
                                </template>
                                <template v-else>
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
                                            style="color: #c2410c"
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
                                </template>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div
                v-if="summaryModal && result"
                class="modal fade show d-block route-tracking-otp-modal"
                tabindex="-1"
                role="dialog"
                aria-modal="true"
                aria-labelledby="route-summary-modal-title"
                @click.self="closeSummary"
            >
                <div class="modal-dialog modal-dialog-centered modal-lg modal-dialog-scrollable">
                    <div class="modal-content">
                        <div class="modal-header">
                            <div>
                                <h5 id="route-summary-modal-title" class="modal-title">{{ summaryTitle }}</h5>
                                <div class="small text-muted">{{ routeHeading }}</div>
                            </div>
                            <button type="button" class="btn-close" aria-label="Close" @click="closeSummary"></button>
                        </div>
                        <div class="modal-body">
                            <dl v-if="summaryModal === 'route'" class="route-detail-grid mb-0">
                                <div><dt>Route</dt><dd>{{ result.planned.route_details?.routecode }} - {{ result.planned.route_details?.routename || "Not available" }}</dd></div>
                                <div><dt>Salesman</dt><dd>{{ result.planned.route_details?.salesmanname || "Not available" }} <span v-if="result.planned.route_details?.salesmancode" class="text-muted">({{ result.planned.route_details.salesmancode }})</span></dd></div>
                                <div><dt>Version Number</dt><dd>{{ result.planned.route_details?.version || "Not available" }}</dd></div>
                                <div><dt>Route Start Time</dt><dd>{{ result.planned.route_details?.start_time || "Not available" }}</dd></div>
                                <div><dt>Route End Time</dt><dd>{{ result.planned.route_details?.end_time || "Not available" }}</dd></div>
                                <div><dt>Route Start Odometer</dt><dd>{{ result.planned.route_details?.start_odometer ?? "Not available" }}</dd></div>
                                <div><dt>Route End Odometer</dt><dd>{{ result.planned.route_details?.end_odometer ?? "Not available" }}</dd></div>
                            </dl>

                            <div v-else-if="['customers', 'unplanned'].includes(summaryModal)" class="table-responsive">
                                <table class="table table-sm table-hover align-middle mb-0">
                                    <thead><tr><th>#</th><th>Customer</th><th>Code</th><th>Status</th></tr></thead>
                                    <tbody>
                                        <tr v-for="(customer, index) in summaryCustomers" :key="customer.listKey || `${customer.customercode}-${index}`">
                                            <td>{{ index + 1 }}</td>
                                            <td>{{ customer.customername }}</td>
                                            <td>{{ customer.alternatecode || customer.customercode }}</td>
                                            <td>{{ customer.type === 'visit' ? customerVisitStatus(customer) : customer.visited ? `${customer.visit_count} visit${customer.visit_count === 1 ? '' : 's'}` : 'Not visited' }}</td>
                                        </tr>
                                        <tr v-if="!summaryCustomers.length"><td colspan="4" class="text-center text-muted py-4">No customers found.</td></tr>
                                    </tbody>
                                </table>
                            </div>

                            <div v-else-if="summaryModal === 'otp'" class="table-responsive">
                                <table class="table table-sm table-hover align-middle mb-0">
                                    <thead><tr><th>Customer</th><th>Type</th><th>Date & Time</th><th>Recorded By</th><th>Reason / Comments</th></tr></thead>
                                    <tbody>
                                        <tr v-for="otp in result.planned.otp_logs" :key="otp.id">
                                            <td>{{ otp.customername }}<br><span class="small text-muted">{{ otp.alternatecode || otp.customercode }}</span></td>
                                            <td>{{ otp.type }}</td>
                                            <td>{{ otp.date }} {{ otp.time }}</td>
                                            <td>{{ otp.approved_by || "Not available" }}</td>
                                            <td>{{ [otp.reason, otp.comments].filter(Boolean).join(" · ") || "—" }}</td>
                                        </tr>
                                        <tr v-if="!result.planned.otp_logs?.length"><td colspan="5" class="text-center text-muted py-4">No OTP requests found.</td></tr>
                                    </tbody>
                                </table>
                            </div>

                            <div v-else class="table-responsive">
                                <table class="table table-sm table-hover align-middle mb-0">
                                    <thead><tr><th>Type</th><th>Document</th><th>Customer</th><th>Date & Time</th><th class="text-end">Amount</th></tr></thead>
                                    <tbody>
                                        <tr v-for="transaction in summaryTransactions[summaryModal]" :key="`${transaction.type}-${transaction.transactionkey}`">
                                            <td>{{ transaction.type === 'sales' ? 'Invoice' : transaction.type === 'orders' ? 'Order' : 'Collection' }}</td>
                                            <td>{{ transaction.documentnumber }}</td>
                                            <td>{{ transaction.customername }}<br><span class="small text-muted">{{ transaction.alternatecode }}</span></td>
                                            <td>{{ transaction.date }} {{ transaction.time }}</td>
                                            <td class="text-end fw-semibold">{{ money(summaryModal === 'returns' ? -Math.abs(transaction.amount) : transaction.amount) }}</td>
                                        </tr>
                                        <tr v-if="!summaryTransactions[summaryModal]?.length"><td colspan="5" class="text-center text-muted py-4">No documents found.</td></tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                        <div class="modal-footer"><button type="button" class="btn btn-secondary" @click="closeSummary">Close</button></div>
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

.tracking-filters {
    margin-bottom: 1.5rem;
    overflow: visible;
    border: 1px solid #e2e8f0;
    border-radius: 16px;
    background: #fff;
    color: #172b45;
    box-shadow: 0 4px 20px rgba(23, 43, 69, 0.04);

    button, input, .vs__dropdown-toggle { transition: border-color 0.15s, background-color 0.15s; }
    button:focus-visible, input:not(.vs__search):focus-visible { outline: 3px solid #93c5fd; outline-offset: 3px; }
    button:disabled { cursor: not-allowed; opacity: 0.5; }
    .vs__dropdown-toggle { min-height: 42px; border: 1px solid #d7e0e9; border-radius: 8px; padding: 3px 6px; background: #fff; }
    .vs--open .vs__dropdown-toggle, .vs__dropdown-toggle:focus-within { border-color: #2563eb; box-shadow: 0 0 0 3px #eff6ff; }
    .vs__selected { max-width: 100%; overflow-wrap: anywhere; border: 0; border-radius: 5px; background: #eff6ff; color: #1e40af; font-size: 12px; }
    .vs__selected-options { min-width: 0; }
    .vs__search { min-width: 0; font-size: 13px; }
    .vs__dropdown-menu { z-index: 1100; border: 1px solid #d7e0e9; border-radius: 8px; box-shadow: 0 8px 24px #172b451a; }
    .vs__dropdown-option { padding: 9px 12px; white-space: normal; font-size: 13px; }
    .vs__dropdown-option--highlight { background: #eff6ff; color: #1d4ed8; }
}

.tracking-filters-header, .tracking-filters-heading, .tracking-filter-actions {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 14px;
}

.tracking-filters-header { padding: 18px 22px; }
.tracking-filter-actions { justify-content: flex-end; padding: 0 22px 16px; }
.tracking-filters-heading { justify-content: flex-start; }
.tracking-filters-heading h2 { margin: 0 0 3px; font-size: 17px; font-weight: 700; }
.tracking-filters-heading p { margin: 0; color: #64748b; font-size: 13px; }
.tracking-filter-icon { display: grid; width: 40px; height: 40px; flex: 0 0 40px; place-items: center; border-radius: 11px; background: #eff6ff; color: #2563eb; }
.tracking-filter-actions button { border-radius: 8px; padding: 9px 14px; border: 1px solid transparent; font: inherit; font-size: 13px; font-weight: 650; white-space: nowrap; }
.tracking-reset { background: transparent; color: #52657b; }
.tracking-reset:hover { background: #f1f5f9; }
.tracking-apply { background: #172b45; color: #fff; }
.tracking-apply:hover { background: #274467; }
.tracking-apply i { margin-right: 6px; }
.tracking-filter-grid { display: grid; grid-template-columns: repeat(6, minmax(0, 1fr)); gap: 14px; padding: 0 22px 20px; }
.tracking-filter-field { min-width: 0; }
.tracking-filter-field label { display: flex; align-items: center; gap: 6px; margin-bottom: 7px; color: #475569; font-size: 12px; font-weight: 650; }
.tracking-filter-field input[type="date"] { width: 100%; min-height: 42px; padding: 8px 11px; border: 1px solid #d7e0e9; border-radius: 8px; background: #fff; color: #172b45; font: inherit; font-size: 13px; }
.tracking-selection-count { padding: 1px 6px; border-radius: 5px; background: #dbeafe; color: #1e40af; font-size: 10px; }
.tracking-required { padding: 1px 6px; border-radius: 999px; background: #fef2f2; color: #b91c1c; font-size: 9px; text-transform: uppercase; }

.route-summary-groups {
    display: grid;
    grid-template-columns: repeat(12, minmax(0, 1fr));
    gap: 10px;
    margin-bottom: 1rem;
}

.route-summary-group {
    min-width: 0;
    padding: 9px;
    border: 1px solid #e8edf3;
    border-radius: 10px;
    background: #f8fafc;
}

.route-summary-group-route { grid-column: span 2; }
.route-summary-group-customers { grid-column: span 6; }
.route-summary-group-distance { grid-column: span 4; }
.route-summary-group-time { grid-column: span 6; }
.route-summary-group-transactions { grid-column: span 6; }

.route-summary-group h3 {
    margin: 0 0 6px 2px;
    color: #475569;
    font-size: 11px;
    font-weight: 750;
    letter-spacing: 0.08em;
    text-transform: uppercase;
}

.route-summary-cards {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(135px, 1fr));
    gap: 8px;
}

.route-summary-card {
    --tone: #475569;
    --wash: #f1f5f9;
    display: flex;
    align-items: flex-start;
    gap: 8px;
    min-height: 72px;
    padding: 9px;
    border: 1px solid #e2e8f0;
    border-radius: 10px;
    background: #fff;
    box-shadow: 0 2px 8px rgba(15, 23, 42, 0.035);
    color: inherit;
    font: inherit;
    text-align: left;
    cursor: default;

    &:disabled { opacity: 1; }
    &.clickable { cursor: pointer; }
    &.clickable:hover, &.clickable:focus-visible { border-color: var(--tone); box-shadow: 0 4px 12px rgba(15, 23, 42, 0.1); transform: translateY(-1px); }

    &.tone-blue { --tone: #2563eb; --wash: #eff6ff; }
    &.tone-green { --tone: #15803d; --wash: #f0fdf4; }
    &.tone-red { --tone: #dc2626; --wash: #fef2f2; }
    &.tone-amber { --tone: #b45309; --wash: #fffbeb; }
    &.tone-orange { --tone: #c2410c; --wash: #fff7ed; }
    &.tone-navy { --tone: #172b45; --wash: #eef2f6; }
    &.tone-purple { --tone: #7c3aed; --wash: #f5f3ff; }
}

.route-summary-icon { display: grid; width: 27px; height: 27px; flex: 0 0 27px; place-items: center; border-radius: 7px; background: var(--wash); color: var(--tone); font-size: 11px; }
.route-summary-copy { min-width: 0; flex: 1; }
.route-summary-label, .route-summary-copy > span:last-child { display: block; color: #64748b; font-size: 10.5px; line-height: 1.3; }
.route-summary-copy strong { display: block; margin: 2px 0; color: var(--tone); font-size: 16px; line-height: 1.15; overflow-wrap: anywhere; }
.route-summary-open { align-self: center; color: #94a3b8; font-size: 9px; }

.route-detail-grid {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 12px;

    > div { padding: 12px; border: 1px solid #e2e8f0; border-radius: 8px; background: #f8fafc; }
    dt { margin-bottom: 4px; color: #64748b; font-size: 11px; font-weight: 650; }
    dd { margin: 0; color: #172b45; font-size: 13px; font-weight: 650; overflow-wrap: anywhere; }
}

@media (max-width: 1199px) {
    .tracking-filter-grid { grid-template-columns: repeat(3, minmax(0, 1fr)); }
}

@media (max-width: 991px) {
    .route-summary-groups { grid-template-columns: 1fr; }
    .route-summary-group-route,
    .route-summary-group-customers,
    .route-summary-group-distance,
    .route-summary-group-time,
    .route-summary-group-transactions { grid-column: auto; }
}

@media (max-width: 767px) {
    .tracking-filters-header { align-items: flex-start; flex-wrap: wrap; }
    .tracking-filter-actions { width: 100%; justify-content: flex-end; }
    .tracking-filter-grid { grid-template-columns: repeat(2, minmax(0, 1fr)); padding: 0 18px 18px; }
    .tracking-filters-header { padding: 18px; }
}

@media (max-width: 480px) {
    .tracking-filter-grid { grid-template-columns: 1fr; }
    .route-summary-cards { grid-template-columns: repeat(2, minmax(0, 1fr)); }
    .route-detail-grid { grid-template-columns: 1fr; }
}

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
    flex-wrap: wrap;
    gap: 0.25rem;
    padding: 0.4rem;
    border-bottom: 1px solid #e5e7eb;
    background: #f8fafc;
}

.route-tracking-panel-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 8px;
}

.route-tracking-more {
    max-width: 115px;
    padding: 4px;
    border: 1px solid #e2e8f0;
    border-radius: 5px;
    background: #fff;
    color: #64748b;
    font-size: 11px;
}

.route-tracking-customer-tab {
    flex: 1 1 0;
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

    &.stationary.active {
        border-color: #f59e0b;
        color: #92400e;
        background: #fffbeb;
    }

    &.gps-gaps.active {
        border-color: #dc2626;
        color: #b91c1c;
        background: #fef2f2;
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
        border-color: #c2410c;
        color: #c2410c;
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

.route-tracking-stationary-item {
    border-color: #fde68a !important;
    background: #fffbeb;

    &.selected {
        border-color: #b45309 !important;
        box-shadow: 0 0 0 2px rgba(180, 83, 9, 0.12);
    }

    &.outside-customer {
        border-color: #fecaca !important;
        background: #fef2f2;

        &.selected {
            border-color: #dc2626 !important;
            box-shadow: 0 0 0 2px rgba(220, 38, 38, 0.12);
        }
    }
}

.route-tracking-stationary-dot {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    flex: 0 0 28px;
    width: 28px;
    height: 28px;
    border: 2px solid #b45309;
    border-radius: 50%;
    background: #fef3c7;
    color: #92400e;
    font-size: 0.75rem;
    font-weight: 700;

    &.outside-customer {
        border-color: transparent;
        background: #fecaca;
        color: #b91c1c;
    }
}

.route-tracking-gps-gap-item {
    border-color: #fecaca !important;
    background: #fef2f2;

    &.selected {
        border-color: #dc2626 !important;
        box-shadow: 0 0 0 2px rgba(220, 38, 38, 0.12);
    }
}

.route-tracking-gps-gap-flag {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    flex: 0 0 28px;
    width: 28px;
    height: 28px;
    border-radius: 50%;
    background: #dc2626;
    color: #fff;
    font-size: 0.8rem;
    font-weight: 800;
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
