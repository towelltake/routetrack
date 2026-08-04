<script setup>
import { computed, nextTick, onBeforeUnmount, ref, watch } from "vue";
import { Head, router, useForm, usePage } from "@inertiajs/vue3";

const props = defineProps({
  filters: { type: Object, required: true },
  mapConfig: { type: Object, default: () => ({}) },
  optionSets: { type: Object, required: true },
  selectedRoute: { type: Object, default: null },
  homeCustomers: { type: Array, default: () => [] },
  candidateCustomers: { type: Array, default: () => [] },
  plan: { type: Object, default: null },
  recentPlans: { type: Object, default: () => ({ data: [] }) },
});
const t = usePage().props.translations.ui;

const filterForm = useForm({
  routecode: props.filters.routecode ?? "",
  week: props.filters.week ?? "",
  external_routecode: props.filters.external_routecode ?? "",
  customer_search: props.filters.customer_search ?? "",
});

const generateForm = useForm({
  routecode: props.filters.routecode ?? "",
  week: props.filters.week ?? "",
  customer_codes: initialSelectedCustomerCodes(),
});

const selectedCustomerCount = computed(
  () => generateForm.customer_codes.length,
);
const selectedCustomersPage = ref(1);
const selectedCustomersPerPage = ref(10);
const selectedCustomersPerPageOptions = [10, 25, 50, 100, 200];
const candidateCustomersPage = ref(1);
const candidateCustomersPerPage = ref(10);
const candidateCustomersPerPageOptions = [10, 25, 50, 100, 200];
const draftPreviewPage = ref(1);
const draftPreviewPerPage = ref(10);
const draftPreviewPerPageOptions = [10, 25, 50, 100, 200];
const recentPlansPerPageOptions = [10, 25, 50, 100, 200];
const recentPlansPerPage = ref(Number(props.recentPlans?.per_page || 10));
const selectedMapDay = ref("");
const mapContainer = ref(null);
const mapStatus = ref("");

let googleMapsApiPromise = null;
let mapInstance = null;
let mapMarkers = [];
let mapDirectionsRenderer = null;

const selectedCustomersTotalPages = computed(() =>
  Math.max(
    1,
    Math.ceil(props.homeCustomers.length / selectedCustomersPerPage.value),
  ),
);

const paginatedHomeCustomers = computed(() => {
  const start =
    (selectedCustomersPage.value - 1) * selectedCustomersPerPage.value;

  return props.homeCustomers.slice(
    start,
    start + selectedCustomersPerPage.value,
  );
});

const selectedCustomersRange = computed(() => {
  if (!props.homeCustomers.length) {
    return { from: 0, to: 0 };
  }

  const from =
    (selectedCustomersPage.value - 1) * selectedCustomersPerPage.value + 1;
  const to = Math.min(
    from + selectedCustomersPerPage.value - 1,
    props.homeCustomers.length,
  );

  return { from, to };
});

const candidateCustomersTotalPages = computed(() =>
  Math.max(
    1,
    Math.ceil(
      props.candidateCustomers.length / candidateCustomersPerPage.value,
    ),
  ),
);

const paginatedCandidateCustomers = computed(() => {
  const start =
    (candidateCustomersPage.value - 1) * candidateCustomersPerPage.value;

  return props.candidateCustomers.slice(
    start,
    start + candidateCustomersPerPage.value,
  );
});

const candidateCustomersRange = computed(() => {
  if (!props.candidateCustomers.length) {
    return { from: 0, to: 0 };
  }

  const from =
    (candidateCustomersPage.value - 1) * candidateCustomersPerPage.value + 1;
  const to = Math.min(
    from + candidateCustomersPerPage.value - 1,
    props.candidateCustomers.length,
  );

  return { from, to };
});

const draftPreviewItems = computed(() => props.plan?.items ?? []);
const scheduledDraftItems = computed(() =>
  draftPreviewItems.value
    .filter(
      (item) =>
        Number(item.assigned_weekday) > 0 &&
        Number(item.assigned_sequence) > 0 &&
        Number.isFinite(Number(item.fixedlatitude)) &&
        Number.isFinite(Number(item.fixedlongitude)),
    )
    .sort((left, right) => {
      if (Number(left.assigned_weekday) !== Number(right.assigned_weekday)) {
        return Number(left.assigned_weekday) - Number(right.assigned_weekday);
      }

      return Number(left.assigned_sequence) - Number(right.assigned_sequence);
    }),
);

const mapDayOptions = computed(() => {
  const options = [];
  const seen = new Set();

  scheduledDraftItems.value.forEach((item) => {
    const id = String(item.assigned_weekday ?? "");
    if (!id || seen.has(id)) {
      return;
    }

    seen.add(id);
    options.push({
      id,
      label: item.assigned_weekday_label || weekdayLabel(id),
    });
  });

  return options;
});

const filteredMapItems = computed(() => {
  if (!selectedMapDay.value) {
    return [];
  }

  return scheduledDraftItems.value.filter(
    (item) => String(item.assigned_weekday) === String(selectedMapDay.value),
  );
});

const mapJourneyUrl = computed(() =>
  buildGoogleMapsJourneyUrl(filteredMapItems.value),
);

const draftPreviewTotalPages = computed(() =>
  Math.max(
    1,
    Math.ceil(draftPreviewItems.value.length / draftPreviewPerPage.value),
  ),
);

const paginatedDraftPreviewItems = computed(() => {
  const start = (draftPreviewPage.value - 1) * draftPreviewPerPage.value;

  return draftPreviewItems.value.slice(
    start,
    start + draftPreviewPerPage.value,
  );
});

const draftPreviewRange = computed(() => {
  if (!draftPreviewItems.value.length) {
    return { from: 0, to: 0 };
  }

  const from = (draftPreviewPage.value - 1) * draftPreviewPerPage.value + 1;
  const to = Math.min(
    from + draftPreviewPerPage.value - 1,
    draftPreviewItems.value.length,
  );

  return { from, to };
});

watch(
  () => [filterForm.routecode, filterForm.week],
  ([routecode, week]) => {
    generateForm.routecode = routecode;
    generateForm.week = week;
  },
);

watch(
  () => props.homeCustomers.length,
  () => {
    selectedCustomersPage.value = 1;
  },
);

watch(selectedCustomersPerPage, () => {
  selectedCustomersPage.value = 1;
});

watch(
  () => props.candidateCustomers.length,
  () => {
    candidateCustomersPage.value = 1;
  },
);

watch(candidateCustomersPerPage, () => {
  candidateCustomersPage.value = 1;
});

watch(
  () => props.plan?.id,
  () => {
    draftPreviewPage.value = 1;
  },
);

watch(
  () => draftPreviewItems.value.length,
  () => {
    draftPreviewPage.value = 1;
  },
);

watch(draftPreviewPerPage, () => {
  draftPreviewPage.value = 1;
});

watch(
  () => props.recentPlans?.per_page,
  (value) => {
    recentPlansPerPage.value = Number(value || 10);
  },
  { immediate: true },
);

watch(
  () => props.plan?.id,
  () => {
    selectedMapDay.value = mapDayOptions.value[0]?.id || "";
  },
  { immediate: true },
);

watch(
  mapDayOptions,
  (options) => {
    if (!options.length) {
      selectedMapDay.value = "";
      return;
    }

    if (!options.some((option) => option.id === selectedMapDay.value)) {
      selectedMapDay.value = options[0].id;
    }
  },
  { immediate: true },
);

watch(
  () => [props.plan?.id, selectedMapDay.value, filteredMapItems.value.length],
  async () => {
    await nextTick();
    await renderJourneyPlanMap();
  },
);

onBeforeUnmount(() => {
  clearJourneyPlanMap();
});

function initialSelectedCustomerCodes() {
  if (props.plan?.items?.length) {
    return props.plan.items.map((item) => item.customercode);
  }

  return props.homeCustomers.map((customer) => customer.customercode);
}

function loadRouteData() {
  router.get(
    "/account/auto-jp-management",
    {
      routecode: filterForm.routecode || undefined,
      week: filterForm.week || undefined,
      external_routecode: filterForm.external_routecode || undefined,
      customer_search: filterForm.customer_search || undefined,
    },
    {
      preserveState: true,
      preserveScroll: true,
    },
  );
}

function toggleCustomer(customerCode, checked) {
  const next = [...generateForm.customer_codes];
  const code = Number(customerCode);

  if (checked) {
    if (!next.includes(code)) {
      next.push(code);
    }
  } else {
    const index = next.indexOf(code);
    if (index >= 0) {
      next.splice(index, 1);
    }
  }

  generateForm.customer_codes = next;
}

function isSelected(customerCode) {
  return generateForm.customer_codes.includes(Number(customerCode));
}

function selectAllHome() {
  const next = new Set(generateForm.customer_codes);
  props.homeCustomers.forEach((customer) =>
    next.add(Number(customer.customercode)),
  );
  generateForm.customer_codes = [...next];
}

function clearExternalSelections() {
  const homeCodes = new Set(
    props.homeCustomers.map((customer) => Number(customer.customercode)),
  );
  generateForm.customer_codes = generateForm.customer_codes.filter((code) =>
    homeCodes.has(Number(code)),
  );
}

function goToSelectedCustomersPage(page) {
  selectedCustomersPage.value = Math.min(
    Math.max(page, 1),
    selectedCustomersTotalPages.value,
  );
}

function goToCandidateCustomersPage(page) {
  candidateCustomersPage.value = Math.min(
    Math.max(page, 1),
    candidateCustomersTotalPages.value,
  );
}

function goToDraftPreviewPage(page) {
  draftPreviewPage.value = Math.min(
    Math.max(page, 1),
    draftPreviewTotalPages.value,
  );
}

function generateDraft() {
  generateForm.post("/account/auto-jp-management/generate", {
    preserveScroll: true,
  });
}

function openPlan(planId) {
  openPlanWithOptions(planId);
}

function openPlanMap(planId) {
  openPlanWithOptions(planId, { focusMap: true });
}

function openPlanWithOptions(planId, { focusMap = false } = {}) {
  router.get(
    "/account/auto-jp-management",
    {
      routecode: filterForm.routecode || undefined,
      week: filterForm.week || undefined,
      external_routecode: filterForm.external_routecode || undefined,
      customer_search: filterForm.customer_search || undefined,
      recent_page: props.recentPlans?.current_page || undefined,
      recent_rows: recentPlansPerPage.value || undefined,
      plan_id: planId,
    },
    {
      preserveScroll: true,
      preserveState: true,
      onSuccess: () => {
        if (!focusMap) {
          return;
        }

        nextTick(() => {
          document
            .getElementById("journey-plan-map")
            ?.scrollIntoView({ behavior: "smooth", block: "start" });
        });
      },
    },
  );
}

function publishPlan() {
  if (!props.plan) {
    return;
  }

  router.post(
    `/account/auto-jp-management/${props.plan.id}/publish`,
    {},
    {
      preserveScroll: true,
    },
  );
}

function goToRecentPlansPage(page) {
  const targetPage = Math.max(
    1,
    Math.min(page, props.recentPlans?.last_page || 1),
  );

  router.get(
    "/account/auto-jp-management",
    {
      routecode: filterForm.routecode || undefined,
      week: filterForm.week || undefined,
      external_routecode: filterForm.external_routecode || undefined,
      customer_search: filterForm.customer_search || undefined,
      plan_id: props.plan?.id || props.filters.plan_id || undefined,
      recent_rows: recentPlansPerPage.value || undefined,
      recent_page: targetPage > 1 ? targetPage : undefined,
    },
    {
      preserveScroll: true,
      preserveState: true,
      only: ["recentPlans"],
    },
  );
}

function changeRecentPlansPerPage() {
  router.get(
    "/account/auto-jp-management",
    {
      routecode: filterForm.routecode || undefined,
      week: filterForm.week || undefined,
      external_routecode: filterForm.external_routecode || undefined,
      customer_search: filterForm.customer_search || undefined,
      plan_id: props.plan?.id || props.filters.plan_id || undefined,
      recent_rows: recentPlansPerPage.value || undefined,
    },
    {
      preserveScroll: true,
      preserveState: true,
      only: ["recentPlans", "filters"],
    },
  );
}

function weekdayLabel(day) {
  const labels = {
    1: t.monday,
    2: t.tuesday,
    3: t.wednesday,
    4: t.thursday,
    5: t.friday,
    6: t.saturday,
    7: t.sunday,
  };

  return labels[Number(day)] || t.unknown;
}

function buildGoogleMapsJourneyUrl(items) {
  if (!items.length) {
    return "";
  }

  if (items.length === 1) {
    return `https://www.google.com/maps/search/?api=1&query=${encodeURIComponent(
      `${items[0].fixedlatitude},${items[0].fixedlongitude}`,
    )}`;
  }

  const origin = `${items[0].fixedlatitude},${items[0].fixedlongitude}`;
  const destination = `${items[items.length - 1].fixedlatitude},${items[items.length - 1].fixedlongitude}`;
  const waypoints = items
    .slice(1, -1)
    .map((item) => `${item.fixedlatitude},${item.fixedlongitude}`)
    .join("|");

  const params = new URLSearchParams({
    api: "1",
    origin,
    destination,
    travelmode: "driving",
  });

  if (waypoints) {
    params.set("waypoints", waypoints);
  }

  return `https://www.google.com/maps/dir/?${params.toString()}`;
}

function clearJourneyPlanMap() {
  mapMarkers.forEach((marker) => marker.setMap?.(null));
  mapMarkers = [];

  if (mapDirectionsRenderer) {
    mapDirectionsRenderer.setMap(null);
    mapDirectionsRenderer = null;
  }
}

function loadGoogleMapsApi() {
  const apiKey = props.mapConfig?.googleMapsApiKey || "";

  if (!apiKey) {
    return Promise.resolve(null);
  }

  if (window.google?.maps) {
    return Promise.resolve(window.google.maps);
  }

  if (googleMapsApiPromise) {
    return googleMapsApiPromise;
  }

  googleMapsApiPromise = new Promise((resolve, reject) => {
    const callbackName = `autoJpGoogleMapsInit_${Date.now()}`;
    window[callbackName] = () => {
      resolve(window.google.maps);
      delete window[callbackName];
    };

    const script = document.createElement("script");
    script.src = `https://maps.googleapis.com/maps/api/js?key=${encodeURIComponent(apiKey)}&callback=${callbackName}`;
    script.async = true;
    script.defer = true;
    script.onerror = () => {
      reject(new Error("Failed to load Google Maps API"));
      delete window[callbackName];
    };
    document.head.appendChild(script);
  });

  return googleMapsApiPromise;
}

async function renderJourneyPlanMap() {
  clearJourneyPlanMap();

  if (!mapContainer.value) {
    return;
  }

  if (!props.plan) {
    mapStatus.value = "";
    return;
  }

  if (!filteredMapItems.value.length) {
    mapStatus.value =
      t.no_sequenced_customers_for_selected_day;
    return;
  }

  const mapsApi = await loadGoogleMapsApi().catch(() => null);

  if (!mapsApi) {
    mapStatus.value = props.mapConfig?.googleMapsApiKey
      ? t.google_maps_api_load_failed
      : t.google_maps_api_key_missing;
    return;
  }

  mapStatus.value = "";

  const points = filteredMapItems.value.map((item) => ({
    lat: Number(item.fixedlatitude),
    lng: Number(item.fixedlongitude),
    label: `${item.assigned_sequence}. ${item.customername}`,
    meta: `${item.customercode} | ${item.planned_start_time || "--:--"} - ${item.planned_end_time || "--:--"}`,
  }));

  mapInstance = new mapsApi.Map(mapContainer.value, {
    center: points[0],
    zoom: 11,
    mapTypeControl: false,
    streetViewControl: false,
    fullscreenControl: true,
  });

  const bounds = new mapsApi.LatLngBounds();
  const infoWindow = new mapsApi.InfoWindow();

  mapMarkers = points.map((point, index) => {
    const marker = new mapsApi.Marker({
      position: point,
      map: mapInstance,
      label: String(index + 1),
      title: point.label,
    });

    marker.addListener("click", () => {
      infoWindow.setContent(
        `<div style="min-width:160px"><strong>${point.label}</strong><div>${point.meta}</div></div>`,
      );
      infoWindow.open({
        anchor: marker,
        map: mapInstance,
      });
    });

    bounds.extend(point);
    return marker;
  });

  if (points.length === 1) {
    mapInstance.setCenter(points[0]);
    mapInstance.setZoom(14);
    return;
  }

  mapDirectionsRenderer = new mapsApi.DirectionsRenderer({
    map: mapInstance,
    suppressMarkers: true,
    preserveViewport: false,
    polylineOptions: {
      strokeColor: "#0d6efd",
      strokeOpacity: 0.9,
      strokeWeight: 5,
    },
  });

  const directionsService = new mapsApi.DirectionsService();
  const waypoints = points.slice(1, -1).map((point) => ({
    location: { lat: point.lat, lng: point.lng },
    stopover: true,
  }));

  directionsService.route(
    {
      origin: { lat: points[0].lat, lng: points[0].lng },
      destination: {
        lat: points[points.length - 1].lat,
        lng: points[points.length - 1].lng,
      },
      waypoints,
      optimizeWaypoints: false,
      travelMode: mapsApi.TravelMode.DRIVING,
    },
    (response, status) => {
      if (status === mapsApi.DirectionsStatus.OK && response) {
        mapDirectionsRenderer?.setDirections(response);
        return;
      }

      mapStatus.value =
        t.road_routing_draw_failed;
      mapInstance.fitBounds(bounds);
    },
  );
}
</script>

<template>
  <Head :title="t.auto_jp_management" />

  <BasePageHeading
    :title="t.auto_jp_management"
    :subtitle="t.auto_jp_management_note"
  />

  <div class="content">
    <BaseBlock :title="t.planner_filters">
      <div class="row g-3 align-items-end">
        <div class="col-md-4">
          <label class="form-label"
            >{{ t.route }} <span class="text-danger">*</span></label
          >
          <select v-model="filterForm.routecode" class="form-select">
            <option value="">{{ t.select_route }}</option>
            <option
              v-for="option in optionSets.routeOptions"
              :key="option.id"
              :value="option.id"
            >
              {{ option.label }}
            </option>
          </select>
        </div>
        <div class="col-md-2">
          <label class="form-label">{{ t.week }}</label>
          <select v-model="filterForm.week" class="form-select">
            <option
              v-for="option in optionSets.weekOptions"
              :key="option.id"
              :value="option.id"
            >
              {{ option.label }}
            </option>
          </select>
        </div>
        <div class="col-md-3">
          <label class="form-label">{{ t.external_route }}</label>
          <select v-model="filterForm.external_routecode" class="form-select">
            <option value="">{{ t.all_routes_label }}</option>
            <option
              v-for="option in optionSets.externalRouteOptions"
              :key="option.id"
              :value="option.id"
            >
              {{ option.label }}
            </option>
          </select>
        </div>
        <div class="col-md-3">
          <label class="form-label">{{ t.search_external_customer }}</label>
          <input
            v-model="filterForm.customer_search"
            class="form-control"
            :placeholder="t.external_customer_search_placeholder"
          />
        </div>
        <div class="col-12 d-flex gap-2 mb-3">
          <button
            class="btn btn-primary"
            :disabled="!filterForm.routecode"
            @click="loadRouteData"
          >
            <i class="fa fa-filter me-1"></i> {{ t.load_route_data }}
          </button>
          <button
            class="btn btn-alt-secondary"
            :disabled="!homeCustomers.length"
            @click="selectAllHome"
          >
            {{ t.select_all_geo_valid_route_customers }}
          </button>
          <button
            class="btn btn-alt-secondary"
            :disabled="!generateForm.customer_codes.length"
            @click="clearExternalSelections"
          >
            {{ t.keep_home_customers_only }}
          </button>
        </div>
      </div>
    </BaseBlock>

    <BaseBlock v-if="selectedRoute" :title="t.route_planning_setup" class="mt-4">
      <div class="row g-3">
        <div class="col-md-3">
          <strong>{{ t.route }}:</strong> {{ selectedRoute.routename }}
        </div>
        <div class="col-md-2">
          <strong>{{ t.type }}:</strong> {{ selectedRoute.routetype }}
        </div>
        <div class="col-md-2">
          <strong>{{ t.auto_jp }}:</strong>
          {{ Number(selectedRoute.autojp_enabled) ? t.enabled : t.disabled }}
        </div>
        <div class="col-md-2">
          <strong>{{ t.start }}:</strong> {{ selectedRoute.autojp_work_start_time }}
        </div>
        <div class="col-md-2">
          <strong>{{ t.end }}:</strong> {{ selectedRoute.autojp_work_end_time }}
        </div>
        <div class="col-md-12">
          <strong>{{ t.working_days }}:</strong>
          {{ selectedRoute.autojp_working_day_labels.join(", ") }}
        </div>
      </div>
    </BaseBlock>

    <div v-if="selectedRoute" class="row g-4 mt-1">
      <div class="col-xl-7">
        <BaseBlock :title="`${t.selected_customers} (${selectedCustomerCount})`">
          <div
            class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-3"
          >
            <div class="fs-sm text-muted">
              {{ t.showing }} {{ selectedCustomersRange.from }}-{{
                selectedCustomersRange.to
              }}
              {{ t.of }} {{ homeCustomers.length }} {{ t.geo_valid_route_customers }}
            </div>
            <div class="d-flex align-items-center gap-2">
              <label class="form-label mb-0">{{ t.rows }}</label>
              <select
                v-model="selectedCustomersPerPage"
                class="form-select form-select-sm"
                style="width: auto"
              >
                <option
                  v-for="option in selectedCustomersPerPageOptions"
                  :key="option"
                  :value="option"
                >
                  {{ option }}
                </option>
              </select>
            </div>
          </div>
          <div class="table-responsive">
            <table class="table table-sm table-striped align-middle">
              <thead>
                <tr>
                  <th style="width: 42px">{{ t.use }}</th>
                  <th>{{ t.customer }}</th>
                  <th>{{ t.home_route }}</th>
                  <th>{{ t.slot }}</th>
                  <th>{{ t.visits }}</th>
                  <th>{{ t.avg_time }}</th>
                  <th>{{ t.score }}</th>
                </tr>
              </thead>
              <tbody>
                <tr
                  v-for="customer in paginatedHomeCustomers"
                  :key="`home-${customer.customercode}`"
                >
                  <td>
                    <input
                      class="form-check-input"
                      type="checkbox"
                      :checked="isSelected(customer.customercode)"
                      @change="
                        toggleCustomer(
                          customer.customercode,
                          $event.target.checked,
                        )
                      "
                    />
                  </td>
                  <td>
                    <div class="fw-semibold">{{ customer.customername }}</div>
                    <div class="fs-sm text-muted">
                      {{ customer.customercode }} /
                      {{ customer.alternatecode || "-" }}
                    </div>
                  </td>
                  <td>{{ customer.routename }}</td>
                  <td>
                    {{ customer.delivery_slot_from || "--:--" }} -
                    {{ customer.delivery_slot_to || "--:--" }}
                  </td>
                  <td>
                    {{ customer.serviced_visits }} {{ t.serviced }} /
                    {{ customer.scheduled_visits }} {{ t.scheduled }}
                  </td>
                  <td>
                    {{ customer.avg_visit_start_time || "--:--" }} /
                    {{ customer.avg_visit_duration_minutes }} {{ t.min }}
                  </td>
                  <td>{{ customer.score }}</td>
                </tr>
                <tr v-if="!homeCustomers.length">
                  <td colspan="7" class="text-center text-muted py-4">
                    {{ t.load_route_to_view_geo_customers }}
                  </td>
                </tr>
              </tbody>
            </table>
          </div>
          <div
            v-if="homeCustomers.length"
            class="d-flex flex-wrap justify-content-between align-items-center gap-3 mt-3"
          >
            <div class="fs-sm text-muted">
              {{ t.page }} {{ selectedCustomersPage }} {{ t.of }}
              {{ selectedCustomersTotalPages }}
            </div>
            <div class="btn-group">
              <button
                class="btn btn-sm btn-alt-secondary"
                :disabled="selectedCustomersPage <= 1"
                @click="goToSelectedCustomersPage(selectedCustomersPage - 1)"
              >
                {{ t.previous }}
              </button>
              <button
                class="btn btn-sm btn-alt-secondary"
                :disabled="selectedCustomersPage >= selectedCustomersTotalPages"
                @click="goToSelectedCustomersPage(selectedCustomersPage + 1)"
              >
                {{ t.next }}
              </button>
            </div>
          </div>
        </BaseBlock>
      </div>

      <div class="col-xl-5">
        <BaseBlock
          :title="`${t.external_route_candidates} (${candidateCustomers.length})`"
        >
          <div
            v-if="candidateCustomers.length"
            class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-3"
          >
            <div class="fs-sm text-muted">
              {{ t.showing }} {{ candidateCustomersRange.from }}-{{
                candidateCustomersRange.to
              }}
              {{ t.of }} {{ candidateCustomers.length }} {{ t.external_candidates }}
            </div>
            <div class="d-flex align-items-center gap-2">
              <label class="form-label mb-0">{{ t.rows }}</label>
              <select
                v-model="candidateCustomersPerPage"
                class="form-select form-select-sm"
                style="width: auto"
              >
                <option
                  v-for="option in candidateCustomersPerPageOptions"
                  :key="option"
                  :value="option"
                >
                  {{ option }}
                </option>
              </select>
            </div>
          </div>
          <div class="table-responsive">
            <table class="table table-sm table-striped align-middle">
              <thead>
                <tr>
                  <th style="width: 42px">{{ t.add }}</th>
                  <th>{{ t.customer }}</th>
                  <th>{{ t.route }}</th>
                  <th>{{ t.slot }}</th>
                </tr>
              </thead>
              <tbody>
                <tr
                  v-for="customer in paginatedCandidateCustomers"
                  :key="`external-${customer.customercode}`"
                >
                  <td>
                    <input
                      class="form-check-input"
                      type="checkbox"
                      :checked="isSelected(customer.customercode)"
                      @change="
                        toggleCustomer(
                          customer.customercode,
                          $event.target.checked,
                        )
                      "
                    />
                  </td>
                  <td>
                    <div class="fw-semibold">{{ customer.customername }}</div>
                    <div class="fs-sm text-muted">
                      {{ customer.customercode }} /
                      {{ customer.alternatecode || "-" }}
                    </div>
                  </td>
                  <td>{{ customer.routename }}</td>
                  <td>
                    {{ customer.delivery_slot_from || "--:--" }} -
                    {{ customer.delivery_slot_to || "--:--" }}
                  </td>
                </tr>
                <tr v-if="!candidateCustomers.length">
                  <td colspan="4" class="text-center text-muted py-4">
                    {{ t.use_external_route_filters_help }}
                  </td>
                </tr>
              </tbody>
            </table>
          </div>

          <div
            v-if="candidateCustomers.length"
            class="d-flex flex-wrap justify-content-between align-items-center gap-3 mt-3"
          >
            <div class="fs-sm text-muted">
              {{ t.page }} {{ candidateCustomersPage }} {{ t.of }}
              {{ candidateCustomersTotalPages }}
            </div>
            <div class="btn-group">
              <button
                class="btn btn-sm btn-alt-secondary"
                :disabled="candidateCustomersPage <= 1"
                @click="goToCandidateCustomersPage(candidateCustomersPage - 1)"
              >
                {{ t.previous }}
              </button>
              <button
                class="btn btn-sm btn-alt-secondary"
                :disabled="
                  candidateCustomersPage >= candidateCustomersTotalPages
                "
                @click="goToCandidateCustomersPage(candidateCustomersPage + 1)"
              >
                {{ t.next }}
              </button>
            </div>
          </div>

          <div
            class="border-top pt-3 mt-3 d-flex justify-content-between align-items-center mb-3"
          >
            <div class="text-muted fs-sm">
              {{ t.selected }} {{ selectedCustomerCount }} {{ t.customers_for_route }}
              {{ filterForm.routecode || "-" }} {{ t.and_week }}
              {{ filterForm.week || "-" }}.
            </div>
            <button
              class="btn btn-primary"
              :disabled="
                !generateForm.routecode ||
                !generateForm.customer_codes.length ||
                generateForm.processing
              "
              @click="generateDraft"
            >
              <i class="fa fa-wand-magic-sparkles me-1"></i>
              {{ generateForm.processing ? t.generating : t.generate_draft }}
            </button>
          </div>
        </BaseBlock>
      </div>
    </div>

    <div class="row g-4 mt-1">
      <div class="col-xl-8">
        <BaseBlock :title="t.draft_preview">
          <div v-if="plan">
            <div class="d-flex justify-content-between align-items-center mb-3">
              <div class="fs-sm text-muted">
                {{ t.draft }} #{{ plan.id }} | {{ t.status }}:
                <span class="fw-semibold text-uppercase">{{
                  plan.status
                }}</span>
                <span v-if="plan.generated_at">
                  | {{ t.generated }} {{ plan.generated_at }}</span
                >
                <span v-if="plan.published_at">
                  | {{ t.published }} {{ plan.published_at }}</span
                >
              </div>
              <button
                class="btn btn-success"
                :disabled="plan.status === 'published'"
                @click="publishPlan"
              >
                <i class="fa fa-upload me-1"></i> {{ t.publish_to_route_sequence }}
              </button>
            </div>
            <div
              class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-3"
            >
              <div class="fs-sm text-muted">
                {{ t.showing }} {{ draftPreviewRange.from }}-{{
                  draftPreviewRange.to
                }}
                {{ t.of }} {{ draftPreviewItems.length }} {{ t.draft_rows }}
              </div>
              <div class="d-flex align-items-center gap-2">
                <label class="form-label mb-0">{{ t.rows }}</label>
                <select
                  v-model="draftPreviewPerPage"
                  class="form-select form-select-sm"
                  style="width: auto"
                >
                  <option
                    v-for="option in draftPreviewPerPageOptions"
                    :key="option"
                    :value="option"
                  >
                    {{ option }}
                  </option>
                </select>
              </div>
            </div>
            <div class="table-responsive">
              <table class="table table-sm table-striped align-middle">
                <thead>
                  <tr>
                    <th>{{ t.day }}</th>
                    <th>{{ t.seq }}</th>
                    <th>{{ t.customer }}</th>
                    <th>{{ t.home_route }}</th>
                    <th>{{ t.planned }}</th>
                    <th>{{ t.history }}</th>
                    <th>{{ t.score }}</th>
                  </tr>
                </thead>
                <tbody>
                  <tr
                    v-for="item in paginatedDraftPreviewItems"
                    :key="`${item.customercode}-${item.assigned_weekday}`"
                  >
                    <td>{{ item.assigned_weekday_label }}</td>
                    <td>{{ item.assigned_sequence }}</td>
                    <td>
                      <div class="fw-semibold">{{ item.customername }}</div>
                      <div class="fs-sm text-muted">
                        {{ item.customercode }} /
                        {{ item.alternatecode || "-" }} / {{ item.source }}
                      </div>
                    </td>
                    <td>{{ item.home_routename || "-" }}</td>
                    <td>
                      <div>
                        {{ item.planned_start_time || "--:--" }} -
                        {{ item.planned_end_time || "--:--" }}
                      </div>
                      <div class="fs-sm text-muted">
                        {{ t.slot }} {{ item.delivery_slot_from || "--:--" }} -
                        {{ item.delivery_slot_to || "--:--" }}
                      </div>
                    </td>
                    <td>
                      <div>{{ t.invoice }}: {{ item.last_invoice_date || "-" }}</div>
                      <div>{{ t.order }}: {{ item.last_order_date || "-" }}</div>
                      <div class="fs-sm text-muted">
                        {{ item.serviced_visits }} {{ t.serviced }} /
                        {{ item.scheduled_visits }} {{ t.scheduled }} /
                        {{ item.avg_visit_duration_minutes }} {{ t.min }}
                      </div>
                    </td>
                    <td>{{ item.score }}</td>
                  </tr>
                </tbody>
              </table>
            </div>
            <div
              v-if="draftPreviewItems.length"
              class="d-flex flex-wrap justify-content-between align-items-center gap-3 mt-3"
            >
              <div class="fs-sm text-muted">
                {{ t.page }} {{ draftPreviewPage }} {{ t.of }} {{ draftPreviewTotalPages }}
              </div>
              <div class="btn-group">
                <button
                  class="btn btn-sm btn-alt-secondary"
                  :disabled="draftPreviewPage <= 1"
                  @click="goToDraftPreviewPage(draftPreviewPage - 1)"
                >
                  {{ t.previous }}
                </button>
                <button
                  class="btn btn-sm btn-alt-secondary"
                  :disabled="draftPreviewPage >= draftPreviewTotalPages"
                  @click="goToDraftPreviewPage(draftPreviewPage + 1)"
                >
                  {{ t.next }}
                </button>
              </div>
            </div>

            <div id="journey-plan-map" class="border-top pt-4 mt-4">
              <div
                class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-3"
              >
                <div>
                  <h5 class="mb-1">{{ t.journey_plan_map }}</h5>
                  <div class="fs-sm text-muted">
                    {{ t.journey_plan_map_note }}
                  </div>
                </div>
                <div class="d-flex flex-wrap align-items-center gap-2">
                  <label class="form-label mb-0">{{ t.scheduled_day }}</label>
                  <select
                    v-model="selectedMapDay"
                    class="form-select form-select-sm"
                    style="width: auto; min-width: 160px"
                  >
                    <option
                      v-for="option in mapDayOptions"
                      :key="option.id"
                      :value="option.id"
                    >
                      {{ option.label }}
                    </option>
                  </select>
                  <a
                    v-if="mapJourneyUrl"
                    :href="mapJourneyUrl"
                    class="btn btn-sm btn-alt-primary"
                    target="_blank"
                    rel="noopener noreferrer"
                  >
                    <i class="fa fa-location-arrow me-1"></i> {{ t.open_in_google_maps }}
                  </a>
                </div>
              </div>

              <div v-if="mapDayOptions.length">
                <div
                  ref="mapContainer"
                  style="
                    width: 100%;
                    min-height: 420px;
                    border-radius: 0.5rem;
                    overflow: hidden;
                    background: #f8f9fa;
                    border: 1px solid rgba(0, 0, 0, 0.08);
                  "
                ></div>
                <div class="fs-sm text-muted mt-2">
                  {{
                    mapStatus ||
                    `${t.showing} ${filteredMapItems.length} ${t.sequenced_customers_for} ${mapDayOptions.find((option) => option.id === selectedMapDay)?.label || t.selected_day}.`
                  }}
                </div>

                <div class="table-responsive mt-3">
                  <table class="table table-sm table-striped align-middle">
                    <thead>
                      <tr>
                        <th>{{ t.seq }}</th>
                        <th>{{ t.customer }}</th>
                        <th>{{ t.planned }}</th>
                        <th>{{ t.coordinates }}</th>
                      </tr>
                    </thead>
                    <tbody>
                      <tr
                        v-for="item in filteredMapItems"
                        :key="`map-${item.assigned_weekday}-${item.assigned_sequence}-${item.customercode}`"
                      >
                        <td>{{ item.assigned_sequence }}</td>
                        <td>
                          <div class="fw-semibold">{{ item.customername }}</div>
                          <div class="fs-sm text-muted">
                            {{ item.customercode }} /
                            {{ item.alternatecode || "-" }}
                          </div>
                        </td>
                        <td>
                          {{ item.planned_start_time || "--:--" }} -
                          {{ item.planned_end_time || "--:--" }}
                        </td>
                        <td>
                          {{ item.fixedlatitude }}, {{ item.fixedlongitude }}
                        </td>
                      </tr>
                    </tbody>
                  </table>
                </div>
              </div>
              <div v-else class="text-muted py-4">
                {{ t.no_map_rows_available }}
              </div>
            </div>
          </div>
          <div v-else class="text-muted py-4">
            {{ t.generate_draft_preview_help }}
          </div>
        </BaseBlock>
      </div>

      <div class="col-xl-4">
        <BaseBlock :title="t.recent_drafts">
          <div
            v-if="(recentPlans.data || []).length"
            class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-3"
          >
            <div class="fs-sm text-muted">
              {{ t.showing }} {{ recentPlans.from || 0 }}-{{ recentPlans.to || 0 }} {{ t.of }}
              {{ recentPlans.total || 0 }} {{ t.drafts }} | {{ t.page }}
              {{ recentPlans.current_page || 1 }} {{ t.of }}
              {{ recentPlans.last_page || 1 }}
            </div>
            <div class="d-flex align-items-center gap-2">
              <label class="form-label mb-0">{{ t.rows }}</label>
              <select
                v-model="recentPlansPerPage"
                class="form-select form-select-sm"
                style="width: auto"
                @change="changeRecentPlansPerPage"
              >
                <option
                  v-for="option in recentPlansPerPageOptions"
                  :key="option"
                  :value="option"
                >
                  {{ option }}
                </option>
              </select>
            </div>
          </div>
          <div class="list-group">
            <div
              v-for="planItem in recentPlans.data || []"
              :key="planItem.id"
              class="list-group-item"
            >
              <div class="d-flex justify-content-between">
                <span class="fw-semibold">{{ t.draft }} #{{ planItem.id }}</span>
                <span class="badge bg-primary">{{ planItem.status }}</span>
              </div>
              <div class="fs-sm text-muted">
                {{ t.route }} {{ planItem.routecode }} | {{ t.week }} {{ planItem.week_number }}
              </div>
              <div class="fs-sm text-muted">
                {{ t.generated }} {{ planItem.generated_at || "-" }}
              </div>
              <div class="d-flex gap-2 mt-2">
                <button
                  class="btn btn-sm btn-alt-secondary"
                  type="button"
                  @click="openPlan(planItem.id)"
                >
                  {{ t.open_draft }}
                </button>
                <button
                  class="btn btn-sm btn-alt-primary"
                  type="button"
                  @click="openPlanMap(planItem.id)"
                >
                  {{ t.view_map }}
                </button>
              </div>
            </div>
            <div v-if="!(recentPlans.data || []).length" class="text-muted">
              {{ t.no_drafts_generated_yet }}
            </div>
          </div>
          <div
            v-if="(recentPlans.data || []).length"
            class="d-flex justify-content-end mt-3"
          >
            <div class="btn-group">
              <button
                class="btn btn-sm btn-alt-secondary"
                :disabled="(recentPlans.current_page || 1) <= 1"
                @click="
                  goToRecentPlansPage((recentPlans.current_page || 1) - 1)
                "
              >
                {{ t.previous }}
              </button>
              <button
                class="btn btn-sm btn-alt-secondary"
                :disabled="
                  (recentPlans.current_page || 1) >=
                  (recentPlans.last_page || 1)
                "
                @click="
                  goToRecentPlansPage((recentPlans.current_page || 1) + 1)
                "
              >
                {{ t.next }}
              </button>
            </div>
          </div>
        </BaseBlock>
      </div>
    </div>
  </div>
</template>
