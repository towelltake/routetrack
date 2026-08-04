<script setup>
import { computed, ref, watch } from "vue";
import { Head, router, usePage } from "@inertiajs/vue3";
import { usePermissions } from "@/composables/usePermissions";

const props = defineProps({
  available: { type: Boolean, default: false },
  formMeta: { type: Object, required: true },
  optionSets: { type: Object, default: () => ({}) },
});

const { can } = usePermissions();
const t = usePage().props.translations.ui;

const form = ref({
  route_item_group: "",
  fill_by: "3",
  filter_value_region: "",
  filter_value_depot: "",
  filter_value_area: "",
});

const allRoutes = ref([]);
const selectedRoutes = ref([]);
const activeAvailable = ref([]);
const activeSelected = ref([]);
const loading = ref(false);
const errorMessage = ref("");

const routeItemGroupOptions = computed(() => props.optionSets?.routeItemGroupOptions ?? []);
const fillByOptions = computed(() => props.optionSets?.fillByOptions ?? []);
const regionOptions = computed(() => props.optionSets?.regionOptions ?? []);
const depotOptions = computed(() => props.optionSets?.depotOptions ?? []);
const areaOptions = computed(() => props.optionSets?.areaOptions ?? []);

const availableRoutes = computed(() => {
  const selectedIds = new Set(selectedRoutes.value.map((route) => route.id));
  return allRoutes.value.filter((route) => !selectedIds.has(route.id));
});

const canSave = computed(() => can(props.formMeta.permission, "edit"));

const activeScopeField = computed(() => {
  switch (String(form.value.fill_by)) {
    case "1":
      return "filter_value_region";
    case "2":
      return "filter_value_depot";
    case "3":
    default:
      return "filter_value_area";
  }
});

const activeScopeLabel = computed(() => {
  switch (String(form.value.fill_by)) {
    case "1":
      return t.region;
    case "2":
      return t.depot;
    case "3":
    default:
      return t.area;
  }
});

const activeScopeOptions = computed(() => {
  switch (String(form.value.fill_by)) {
    case "1":
      return regionOptions.value;
    case "2":
      return depotOptions.value;
    case "3":
    default:
      return areaOptions.value;
  }
});

const activeScopeValue = computed({
  get() {
    return form.value[activeScopeField.value];
  },
  set(value) {
    form.value[activeScopeField.value] = value;
  },
});

watch(
  () => form.value.fill_by,
  () => {
    form.value.filter_value_region = "";
    form.value.filter_value_depot = "";
    form.value.filter_value_area = "";
    allRoutes.value = [];
    selectedRoutes.value = [];
    activeAvailable.value = [];
    activeSelected.value = [];
    errorMessage.value = "";
  },
);

function validateForm() {
  if (!form.value.route_item_group) {
    return t.route_item_group_required;
  }

  if (!form.value.fill_by) {
    return t.fill_by_required;
  }

  if (activeScopeValue.value === "") {
    return t.field_required.replace(":field", activeScopeLabel.value);
  }

  return "";
}

async function loadData() {
  errorMessage.value = validateForm();
  if (errorMessage.value) return;

  loading.value = true;

  try {
    const params = new URLSearchParams({
      route_item_group: String(form.value.route_item_group),
      fill_by: String(form.value.fill_by),
      filter_value: String(activeScopeValue.value),
    });

    const response = await fetch(`${props.formMeta.loadUrl}?${params.toString()}`, {
      headers: {
        Accept: "application/json",
        "X-Requested-With": "XMLHttpRequest",
      },
      credentials: "same-origin",
    });

    if (!response.ok) {
      throw new Error(t.unable_to_load_route_data);
    }

    const payload = await response.json();
    allRoutes.value = payload.routes ?? [];
    selectedRoutes.value = payload.selectedRoutes ?? [];
    activeAvailable.value = [];
    activeSelected.value = [];
  } catch (error) {
    errorMessage.value = error instanceof Error ? error.message : t.unable_to_load_route_data;
  } finally {
    loading.value = false;
  }
}

function moveSelectedToRight() {
  const moving = availableRoutes.value.filter((route) => activeAvailable.value.includes(route.id));
  if (!moving.length) return;
  selectedRoutes.value = [...selectedRoutes.value, ...moving];
  activeAvailable.value = [];
}

function moveAllToRight() {
  selectedRoutes.value = [...allRoutes.value];
  activeAvailable.value = [];
}

function moveSelectedToLeft() {
  if (!activeSelected.value.length) return;
  const ids = new Set(activeSelected.value);
  selectedRoutes.value = selectedRoutes.value.filter((route) => !ids.has(route.id));
  activeSelected.value = [];
}

function moveAllToLeft() {
  selectedRoutes.value = [];
  activeSelected.value = [];
}

function saveLinks() {
  errorMessage.value = validateForm();
  if (errorMessage.value) return;

  router.post(
    props.formMeta.saveUrl,
    {
      route_item_group: form.value.route_item_group,
      fill_by: form.value.fill_by,
      filter_value: activeScopeValue.value,
      routes: selectedRoutes.value.map((route) => route.id),
    },
    {
      preserveScroll: true,
    },
  );
}

function resetPage() {
  router.get(props.formMeta.indexUrl);
}
</script>

<template>
  <Head :title="t.route_item_group" />

  <BasePageHeading :title="t.route_item_group" :subtitle="t.route_item_group_link_note">
    <template #extra>
      <div class="d-flex gap-2">
        <button class="btn btn-alt-secondary" @click="resetPage">
          <i class="fa fa-rotate-left me-1"></i> {{ t.cancel }}
        </button>
        <button v-if="canSave" class="btn btn-primary" :disabled="loading" @click="saveLinks">
          <i class="fa fa-floppy-disk me-1"></i> {{ t.save }}
        </button>
      </div>
    </template>
  </BasePageHeading>

  <div class="content">
    <div v-if="!available" class="alert alert-warning d-flex align-items-center" role="alert">
      <i class="fa fa-triangle-exclamation me-2"></i>
      <div>
        {{ t.legacy_route_item_group_link_required }}
      </div>
    </div>

    <div v-if="errorMessage" class="alert alert-danger d-flex align-items-center" role="alert">
      <i class="fa fa-circle-exclamation me-2"></i>
      <div>{{ errorMessage }}</div>
    </div>

    <BaseBlock :title="t.route_item_group">
      <div class="row g-3 align-items-end">
        <div class="col-lg-4">
          <label class="form-label">{{ t.route_item_group }}</label>
          <select v-model="form.route_item_group" class="form-select" :disabled="!available || loading">
            <option value="">{{ t.select_placeholder }}</option>
            <option v-for="option in routeItemGroupOptions" :key="option.id" :value="option.id">{{ option.label }}</option>
          </select>
        </div>

        <div class="col-lg-4">
          <label class="form-label">{{ t.fill_by }}</label>
          <select v-model="form.fill_by" class="form-select" :disabled="!available || loading">
            <option value="">{{ t.select_placeholder }}</option>
            <option v-for="option in fillByOptions" :key="option.id" :value="String(option.id)">{{ option.label }}</option>
          </select>
        </div>

        <div class="col-lg-4">
          <label class="form-label">{{ activeScopeLabel }}</label>
          <select v-model="activeScopeValue" class="form-select" :disabled="!available || loading">
            <option value="">{{ t.select_placeholder }}</option>
            <option v-for="option in activeScopeOptions" :key="option.id" :value="String(option.id)">{{ option.label }}</option>
          </select>
        </div>

        <div class="col-lg-2 d-grid">
          <button class="btn btn-alt-primary" :disabled="!available || loading" @click="loadData">
            <span v-if="loading" class="spinner-border spinner-border-sm me-1"></span>
            {{ t.load_data }}
          </button>
        </div>
      </div>

      <div class="row g-3 pt-4 mb-3">
        <div class="col-lg-5">
          <label class="form-label">{{ t.route }}</label>
          <select
            v-model="activeAvailable"
            class="form-select"
            multiple
            size="15"
            :disabled="loading"
            @dblclick="moveSelectedToRight"
          >
            <option v-for="route in availableRoutes" :key="route.id" :value="route.id">
              {{ route.label }}
            </option>
          </select>
        </div>

        <div class="col-lg-2">
          <div class="d-grid gap-2 h-100 align-content-center pt-lg-4">
            <button class="btn btn-alt-secondary" :disabled="loading || !availableRoutes.length" @click="moveSelectedToRight">
              &gt;
            </button>
            <button class="btn btn-alt-secondary" :disabled="loading || !availableRoutes.length" @click="moveAllToRight">
              &gt;&gt;
            </button>
            <button class="btn btn-alt-secondary" :disabled="loading || !selectedRoutes.length" @click="moveSelectedToLeft">
              &lt;
            </button>
            <button class="btn btn-alt-secondary" :disabled="loading || !selectedRoutes.length" @click="moveAllToLeft">
              &lt;&lt;
            </button>
          </div>
        </div>

        <div class="col-lg-5">
          <label class="form-label">{{ t.selected_route }}</label>
          <select
            v-model="activeSelected"
            class="form-select"
            multiple
            size="15"
            :disabled="loading"
            @dblclick="moveSelectedToLeft"
          >
            <option v-for="route in selectedRoutes" :key="route.id" :value="route.id">
              {{ route.label }}
            </option>
          </select>
        </div>
      </div>
    </BaseBlock>
  </div>
</template>
