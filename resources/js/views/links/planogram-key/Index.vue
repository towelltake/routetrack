<script setup>
import { computed, ref } from "vue";
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
  planogram_id: "",
  route_id: "",
});

const allCustomers = ref([]);
const selectedCustomers = ref([]);
const activeAvailable = ref([]);
const activeSelected = ref([]);
const loading = ref(false);
const errorMessage = ref("");

const planogramOptions = computed(() => props.optionSets?.planogramOptions ?? []);
const routeOptions = computed(() => props.optionSets?.routeOptions ?? []);
const availableCustomers = computed(() => {
  const selectedIds = new Set(selectedCustomers.value.map((customer) => customer.id));
  return allCustomers.value.filter((customer) => !selectedIds.has(customer.id));
});
const canSave = computed(() => can(props.formMeta.permission, "edit"));

async function loadData() {
  errorMessage.value = "";

  if (!form.value.planogram_id || form.value.route_id === "") {
    errorMessage.value = t.planogram_key_and_route_required;
    return;
  }

  loading.value = true;

  try {
    const params = new URLSearchParams({
      planogram_id: String(form.value.planogram_id),
      route_id: String(form.value.route_id),
    });

    const response = await fetch(`${props.formMeta.loadUrl}?${params.toString()}`, {
      headers: {
        Accept: "application/json",
        "X-Requested-With": "XMLHttpRequest",
      },
      credentials: "same-origin",
    });

    if (!response.ok) {
      throw new Error(t.unable_to_load_customer_data);
    }

    const payload = await response.json();
    allCustomers.value = payload.customers ?? [];
    selectedCustomers.value = payload.selectedCustomers ?? [];
    activeAvailable.value = [];
    activeSelected.value = [];
  } catch (error) {
    errorMessage.value = error instanceof Error ? error.message : t.unable_to_load_customer_data;
  } finally {
    loading.value = false;
  }
}

function moveSelectedToRight() {
  const moving = availableCustomers.value.filter((customer) => activeAvailable.value.includes(customer.id));
  if (!moving.length) return;
  selectedCustomers.value = [...selectedCustomers.value, ...moving];
  activeAvailable.value = [];
}

function moveAllToRight() {
  selectedCustomers.value = [...allCustomers.value];
  activeAvailable.value = [];
}

function moveSelectedToLeft() {
  if (!activeSelected.value.length) return;
  const ids = new Set(activeSelected.value);
  selectedCustomers.value = selectedCustomers.value.filter((customer) => !ids.has(customer.id));
  activeSelected.value = [];
}

function moveAllToLeft() {
  selectedCustomers.value = [];
  activeSelected.value = [];
}

function saveLinks() {
  errorMessage.value = "";

  if (!form.value.planogram_id || form.value.route_id === "") {
    errorMessage.value = t.planogram_key_and_route_required;
    return;
  }

  router.post(
    props.formMeta.saveUrl,
    {
      planogram_id: form.value.planogram_id,
      route_id: form.value.route_id,
      customers: selectedCustomers.value.map((customer) => customer.id),
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
  <Head :title="t.planogram_key" />

  <BasePageHeading :title="t.planogram_key" :subtitle="t.planogram_key_note">
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
      <div>{{ t.legacy_planogram_key_link_required }}</div>
    </div>

    <div v-if="errorMessage" class="alert alert-danger d-flex align-items-center" role="alert">
      <i class="fa fa-circle-exclamation me-2"></i>
      <div>{{ errorMessage }}</div>
    </div>

    <BaseBlock :title="t.planogram_key">
      <div class="row g-3 align-items-end">
        <div class="col-md-5">
          <label class="form-label">{{ t.planogram_key }}</label>
          <select v-model="form.planogram_id" class="form-select" :disabled="!available || loading">
            <option value="">{{ t.select_placeholder }}</option>
            <option v-for="option in planogramOptions" :key="option.id" :value="option.id">{{ option.label }}</option>
          </select>
        </div>

        <div class="col-md-5">
          <label class="form-label">{{ t.route }}</label>
          <select v-model="form.route_id" class="form-select" :disabled="!available || loading">
            <option value="">{{ t.select_placeholder }}</option>
            <option v-for="option in routeOptions" :key="option.id" :value="option.id">{{ option.label }}</option>
          </select>
        </div>

        <div class="col-md-2 d-grid">
          <button class="btn btn-alt-primary" :disabled="!available || loading" @click="loadData">
            <span v-if="loading" class="spinner-border spinner-border-sm me-1"></span>
            {{ t.load_data }}
          </button>
        </div>
      </div>

      <div class="row g-3 pt-4 mb-3">
        <div class="col-lg-5">
          <label class="form-label">{{ t.customer }}</label>
          <select
            v-model="activeAvailable"
            class="form-select"
            multiple
            size="15"
            :disabled="loading"
            @dblclick="moveSelectedToRight"
          >
            <option v-for="customer in availableCustomers" :key="customer.id" :value="customer.id">
              {{ customer.label }}
            </option>
          </select>
        </div>

        <div class="col-lg-2">
          <div class="d-grid gap-2 h-100 align-content-center pt-lg-4">
            <button class="btn btn-alt-secondary" :disabled="loading || !availableCustomers.length" @click="moveSelectedToRight">
              &gt;
            </button>
            <button class="btn btn-alt-secondary" :disabled="loading || !availableCustomers.length" @click="moveAllToRight">
              &gt;&gt;
            </button>
            <button class="btn btn-alt-secondary" :disabled="loading || !selectedCustomers.length" @click="moveSelectedToLeft">
              &lt;
            </button>
            <button class="btn btn-alt-secondary" :disabled="loading || !selectedCustomers.length" @click="moveAllToLeft">
              &lt;&lt;
            </button>
          </div>
        </div>

        <div class="col-lg-5">
          <label class="form-label">{{ t.selected_customer }}</label>
          <select
            v-model="activeSelected"
            class="form-select"
            multiple
            size="15"
            :disabled="loading"
            @dblclick="moveSelectedToLeft"
          >
            <option v-for="customer in selectedCustomers" :key="customer.id" :value="customer.id">
              {{ customer.label }}
            </option>
          </select>
        </div>
      </div>
    </BaseBlock>
  </div>
</template>
