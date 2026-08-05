<script setup>
import { computed, onMounted, ref, watch } from "vue";
import { Head } from "@inertiajs/vue3";
import axios from "axios";
import L from "leaflet";
import "leaflet/dist/leaflet.css";
import "leaflet.markercluster";
import "leaflet.markercluster/dist/MarkerCluster.css";
import "leaflet.markercluster/dist/MarkerCluster.Default.css";
import markerIcon2x from "leaflet/dist/images/marker-icon-2x.png";
import markerIcon from "leaflet/dist/images/marker-icon.png";
import markerShadow from "leaflet/dist/images/marker-shadow.png";
import VueSelect from "vue-select";

function median(numbers) {
    const sorted = [...numbers].sort((a, b) => a - b);
    const mid = Math.floor(sorted.length / 2);
    return sorted.length % 2 ? sorted[mid] : (sorted[mid - 1] + sorted[mid]) / 2;
}

const OMAN_BOUNDS = L.latLngBounds([16.0, 51.5], [27.0, 60.5]);

const mapEl = ref(null);
const loading = ref(false);
const error = ref(null);
const companies = ref([]);
const routes = ref([]);
const customers = ref([]);
const selectedCompany = ref(null);
const selectedRoute = ref(null);
const selectedCustomer = ref(null);
const customerListSearch = ref("");

const numberedCustomers = computed(() =>
    customers.value.map((customer, index) => ({ ...customer, displayNumber: index + 1 })),
);

const filteredCustomerList = computed(() => {
    const search = customerListSearch.value.trim().toLowerCase();

    if (!search) {
        return numberedCustomers.value;
    }

    return numberedCustomers.value.filter(
        (customer) =>
            customer.customername.toLowerCase().includes(search) || String(customer.customercode).includes(search),
    );
});

const CUSTOMER_LIST_DISPLAY_LIMIT = 200;

const visibleCustomerList = computed(() => filteredCustomerList.value.slice(0, CUSTOMER_LIST_DISPLAY_LIMIT));

let map = null;
let clusterGroup = null;
const customerMarkers = {};

onMounted(async () => {
    delete L.Icon.Default.prototype._getIconUrl;
    L.Icon.Default.mergeOptions({
        iconRetinaUrl: markerIcon2x,
        iconUrl: markerIcon,
        shadowUrl: markerShadow,
    });

    map = L.map(mapEl.value, { maxBounds: OMAN_BOUNDS, maxBoundsViscosity: 1.0, minZoom: 6 }).setView([23.588, 58.4], 13);

    const streetLayer = L.tileLayer(
        "https://server.arcgisonline.com/ArcGIS/rest/services/World_Street_Map/MapServer/tile/{z}/{y}/{x}",
        { attribution: "&copy; Esri" },
    ).addTo(map);

    const satelliteLayer = L.tileLayer(
        "https://server.arcgisonline.com/ArcGIS/rest/services/World_Imagery/MapServer/tile/{z}/{y}/{x}",
        { attribution: "&copy; Esri" },
    );

    L.control.layers({ Street: streetLayer, Satellite: satelliteLayer }).addTo(map);

    const [routesRes, companiesRes] = await Promise.all([
        axios.get("/customer-location/routes.json"),
        axios.get("/customer-location/companies.json"),
    ]);
    routes.value = routesRes.data;
    companies.value = companiesRes.data;

});

async function loadLocations() {
    loading.value = true;
    error.value = null;
    selectedCustomer.value = null;
    customerListSearch.value = "";
    Object.keys(customerMarkers).forEach((key) => delete customerMarkers[key]);

    if (clusterGroup) {
        map.removeLayer(clusterGroup);
        clusterGroup = null;
    }

    try {
        const { data } = await axios.get("/customer-location/locations.json", {
            params: {
                companycode: selectedCompany.value,
                routecode: selectedRoute.value,
            },
        });
        customers.value = data;

        clusterGroup = L.markerClusterGroup();

        data.forEach((customer) => {
            const marker = L.marker([customer.lat, customer.lng]).bindPopup(
                `<strong>${customer.customername}</strong> (${customer.customercode})<br>${customer.address || ""}`,
            );
            customerMarkers[customer.customercode] = marker;
            clusterGroup.addLayer(marker);
        });

        map.addLayer(clusterGroup);

        if (data.length) {
            // Outliers (bad/placeholder coordinates) shouldn't drag the default
            // zoom out to fit them — only fit to customers near the data's center.
            const medianLat = median(data.map((c) => c.lat));
            const medianLng = median(data.map((c) => c.lng));
            const MAX_DEGREES_FROM_MEDIAN = 5;

            const coreCustomers = data.filter(
                (c) =>
                    Math.abs(c.lat - medianLat) <= MAX_DEGREES_FROM_MEDIAN &&
                    Math.abs(c.lng - medianLng) <= MAX_DEGREES_FROM_MEDIAN,
            );

            const boundsSource = coreCustomers.length ? coreCustomers : data;
            const bounds = L.latLngBounds(boundsSource.map((c) => [c.lat, c.lng]));
            map.fitBounds(bounds, { padding: [30, 30] });
        }
    } catch (e) {
        error.value = "Unable to load customer locations.";
    } finally {
        loading.value = false;
    }
}

watch(selectedCompany, async (companycode) => {
    selectedRoute.value = null;
    routes.value = [];
    const { data } = await axios.get("/customer-location/routes.json", {
        params: { companycode },
    });
    routes.value = data;
});

function focusCustomer(customercode) {
    const marker = customerMarkers[customercode];
    if (!marker || !clusterGroup) {
        return;
    }

    clusterGroup.zoomToShowLayer(marker, () => marker.openPopup());
}

async function resetFilters() {
    selectedCompany.value = null;
    selectedRoute.value = null;
    selectedCustomer.value = null;
    customers.value = [];
    customerListSearch.value = "";
    routes.value = [];

    if (clusterGroup) {
        map.removeLayer(clusterGroup);
        clusterGroup = null;
    }
    Object.keys(customerMarkers).forEach((key) => delete customerMarkers[key]);
    map.setView([20.5, 56], 8);

}
</script>

<template>
    <Head title="Customer Location" />

    <div class="content customer-location-content">
        <div class="customer-location-page-heading">
            <h1 class="h3 fw-bold mb-1">Customer Location</h1>
            <h2 class="fs-base lh-base fw-medium text-muted mb-0">View customer locations on a map</h2>
        </div>

        <BaseBlock title="Filters" class="customer-location-filters">
            <div class="row align-items-end mb-3 g-3">
                <div class="col-md-6">
                    <label class="form-label">Company</label>
                    <VueSelect
                        v-model="selectedCompany"
                        :options="companies"
                        :reduce="(company) => company.cmpycode"
                        label="name"
                        placeholder="All companies..."
                    />
                </div>
                <div class="col-md-6">
                    <label class="form-label">Route</label>
                    <VueSelect
                        v-model="selectedRoute"
                        :options="routes"
                        :reduce="(route) => route.routecode"
                        label="routename"
                        :disabled="!selectedCompany"
                        placeholder="All routes..."
                    />
                </div>
            </div>
            <div class="row align-items-end g-3">
                <div class="col-md-6">
                    <label class="form-label">Customer</label>
                    <VueSelect
                        v-model="selectedCustomer"
                        :options="customers"
                        :reduce="(customer) => customer.customercode"
                        :get-option-label="(customer) => `${customer.customercode} - ${customer.customername}`"
                        :filter-by="(customer, label, search) => label.toLowerCase().includes(search.toLowerCase())"
                        placeholder="Jump to a customer..."
                    />
                </div>
                <div class="col-md-3">
                    <button
                        type="button"
                        class="btn btn-primary w-100"
                        :disabled="loading || !selectedCompany"
                        @click="loadLocations"
                    >
                        {{ loading ? "..." : "Apply" }}
                    </button>
                </div>
                <div class="col-md-3">
                    <button type="button" class="btn btn-light w-100" @click="resetFilters">Reset</button>
                </div>
            </div>
        </BaseBlock>

        <BaseBlock title="Map" :mode-loading="loading">
            <p v-if="error" class="text-danger">{{ error }}</p>
            <p class="text-muted small">{{ customers.length }} customer(s) shown</p>

            <div class="row g-3">
                <div class="col-md-8">
                    <div ref="mapEl" style="height: 600px; width: 100%"></div>
                </div>
                <div class="col-md-4">
                    <div class="card customer-location-customer-list-card" style="height: 600px">
                        <div class="card-header">
                            <strong>Customers</strong>
                            <span class="text-muted small">({{ customers.length }})</span>
                        </div>
                        <div class="customer-location-customer-search p-2">
                            <input
                                v-model="customerListSearch"
                                type="text"
                                class="form-control form-control-sm"
                                placeholder="Search name or code..."
                                :disabled="!customers.length"
                            />
                        </div>
                        <div class="card-body p-2">
                            <div class="customer-location-customer-list">
                                <p v-if="!customers.length" class="text-muted small px-1">No customers to show.</p>
                                <p v-else-if="!filteredCustomerList.length" class="text-muted small px-1">No customers match.</p>
                                <p v-else-if="filteredCustomerList.length > CUSTOMER_LIST_DISPLAY_LIMIT" class="text-muted small px-1">
                                    Showing first {{ CUSTOMER_LIST_DISPLAY_LIMIT }} of {{ filteredCustomerList.length }} &mdash;
                                    search or narrow the filters above to see more.
                                </p>
                                <button
                                    v-for="customer in visibleCustomerList"
                                    :key="customer.customercode"
                                    type="button"
                                    class="list-group-item list-group-item-action customer-location-customer-item"
                                    @click="focusCustomer(customer.customercode)"
                                >
                                    <span class="customer-location-customer-dot">{{ customer.displayNumber }}</span>
                                    <span class="customer-location-customer-info">
                                        <span class="d-block fw-semibold small">{{ customer.customername }}</span>
                                        <span class="d-block text-muted small">
                                            {{ customer.customercode }} &middot; {{ customer.address || "No address" }}
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

// Rendered directly inside .customer-location-content, so it already inherits
// that div's 0.25rem side padding. The extra 1.25rem here matches the
// "FILTERS" label's own block-header inset, so both texts line up exactly.
.customer-location-page-heading {
    padding: 0.75rem 0 0.75rem 1.25rem;
}

// Matches the width treatment used on Route Tracking — this map-heavy page
// benefits from extra horizontal room; scoped to this page only via
// .customer-location-content (the shared .content/.block-content mixins
// otherwise add wide side padding and leave only 1px of bottom padding).
.customer-location-content {
    width: 100% !important;
    max-width: 100% !important;
    margin-left: 0 !important;
    margin-right: 0 !important;
    padding-left: 0.25rem !important;
    padding-right: 0.25rem !important;
    padding-bottom: 1.875rem !important;

    > .block > .block-content {
        padding-bottom: 1.25rem !important;
    }
}

.customer-location-customer-list-card {
    display: flex;
    flex-direction: column;
}

.customer-location-customer-search {
    border-bottom: 1px solid #e5e7eb;
}

.customer-location-customer-list-card .card-body {
    display: flex;
    flex-direction: column;
    min-height: 0;
    flex: 1;
}

.customer-location-customer-list {
    overflow-y: auto;
    flex: 1;
}

.customer-location-customer-item {
    display: flex;
    align-items: center;
    gap: 8px;
    text-align: left;
}

.customer-location-customer-dot {
    flex-shrink: 0;
    background: #3b82f6;
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

.customer-location-customer-info {
    min-width: 0;
    overflow: hidden;
}

.customer-location-customer-info span {
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}
</style>
