<script setup>
import { computed, ref, watch } from "vue";
import { Head, router, useForm, usePage } from "@inertiajs/vue3";
import { usePermissions } from "@/composables/usePermissions";
import { useAmountFormatter } from "@/composables/useAmountFormatter";
import VueSelect from "vue-select";

const props = defineProps({
  mode: { type: String, required: true },
  loadData: { type: Object, required: true },
  lookupOptions: { type: Object, required: true },
  formMeta: { type: Object, required: true },
  useAlternateCode: { type: Boolean, default: false },
});

const page = usePage();
const t = page.props.translations.ui;
const { can } = usePermissions();
const { formatAmount } = useAmountFormatter();

const isView = computed(() => props.mode === "view");
const isCreate = computed(() => props.mode === "create");
const pageTitle = computed(() =>
  isCreate.value
    ? t.create_daily_salesman_load
    : isView.value
      ? t.daily_salesman_load
      : t.edit_daily_salesman_load,
);

const form = useForm({
  load_date: props.loadData.header?.ddate ?? "",
  routecode: props.loadData.header?.routecode ?? "",
  salesmancode: props.loadData.header?.salesmancode ?? "",
  loadperiodnumber: props.loadData.header?.loadperiodnumber ?? 0,
  lines: (props.loadData.lines ?? []).map((line) => ({
    itemcode: line.itemcode,
    display_code: line.display_code,
    description: line.description,
    upc: line.upc,
    caseprice: line.caseprice,
    salesprice: line.salesprice,
    cases: line.cases ?? 0,
    units: line.units ?? 0,
  })),
});

const loadingItems = ref(false);
const itemLoadError = ref("");

const documentKey = computed(() => {
  if (!form.load_date || !form.routecode || !form.salesmancode) return null;
  return `${form.load_date}_${form.routecode}_${form.salesmancode}_${form.loadperiodnumber}`;
});

const routeMap = computed(() => {
  const map = new Map();
  for (const route of props.lookupOptions.routes ?? []) {
    map.set(String(route.id), route);
  }
  return map;
});

const routeValue = computed({
  get: () => findOption(props.lookupOptions.routes, form.routecode),
  set: (option) => {
    form.routecode = option ? option.id : "";
  },
});

watch(
  () => form.routecode,
  async (value) => {
    if (!isCreate.value) return;

    const selectedRoute = routeMap.value.get(String(value));
    form.salesmancode = selectedRoute?.salesmancode ?? "";

    if (!value) {
      form.lines = [];
      return;
    }

    await loadRouteItems(value);
  },
);

async function loadRouteItems(routecode) {
  loadingItems.value = true;
  itemLoadError.value = "";

  try {
    const params = new URLSearchParams({ routecode: String(routecode) });
    const response = await fetch(
      `${props.formMeta.routeItemsUrl}?${params.toString()}`,
      {
        headers: {
          Accept: "application/json",
          "X-Requested-With": "XMLHttpRequest",
        },
        credentials: "same-origin",
      },
    );

    if (!response.ok) {
      throw new Error(t.failed_to_load_items);
    }

    const payload = await response.json();
    if (payload.route?.salesmancode) {
      form.salesmancode = payload.route.salesmancode;
    }

    form.lines = (payload.lines ?? []).map((line) => ({
      ...line,
      cases: line.cases ?? 0,
      units: line.units ?? 0,
    }));
  } catch (error) {
    itemLoadError.value =
      error instanceof Error
        ? error.message
        : t.failed_to_load_items;
  } finally {
    loadingItems.value = false;
  }
}

function totalUnits(line) {
  return (
    Number(line.cases || 0) * Number(line.upc || 1) + Number(line.units || 0)
  );
}

function findOption(options, value) {
  if (!value) {
    return null;
  }

  return options?.find((option) => String(option.id) === String(value)) ?? null;
}

function submit() {
  if (isView.value) {
    return;
  }

  if (isCreate.value) {
    form.post("/inventory/dailysalesmanload");
    return;
  }

  form.put(`/inventory/dailysalesmanload/${documentKey.value}`);
}

function backToIndex() {
  router.get("/inventory/dailysalesmanload");
}
</script>

<template>
  <Head :title="pageTitle" />

  <BasePageHeading :title="pageTitle" :subtitle="t.daily_salesman_load_note">
    <template #extra>
      <div class="d-flex gap-2">
        <button class="btn btn-alt-secondary" @click="backToIndex">
          <i class="fa fa-arrow-left me-1"></i> {{ t.back }}
        </button>
        <button
          v-if="isView && can('daily salesman load', 'edit')"
          class="btn btn-primary"
          @click="
            router.get(`/inventory/dailysalesmanload/${documentKey}/edit`)
          "
        >
          <i class="fa fa-pen me-1"></i> {{ t.edit }}
        </button>
        <button
          v-else-if="!isView"
          class="btn btn-primary"
          :disabled="form.processing || loadingItems"
          @click="submit"
        >
          <i class="fa fa-floppy-disk me-1"></i>
          {{ form.processing ? t.saving : t.save }}
        </button>
      </div>
    </template>
  </BasePageHeading>

  <div class="content">
    <BaseBlock :title="t.daily_salesman_load">
      <div class="row g-4">
        <div class="col-md-3">
          <label class="form-label"
            >{{ t.load_date }} <span class="text-danger">*</span></label
          >
          <input
            v-model="form.load_date"
            type="date"
            class="form-control"
            :readonly="isView || formMeta.headerLocked"
          />
          <div v-if="form.errors.load_date" class="text-danger fs-sm mt-1">
            {{ form.errors.load_date }}
          </div>
        </div>
        <div class="col-md-4">
          <label class="form-label"
            >{{ t.route }} <span class="text-danger">*</span></label
          >
          <VueSelect
            v-model="routeValue"
            :options="lookupOptions.routes"
            label="label"
            :placeholder="t.select_route"
            :disabled="isView || formMeta.headerLocked || loadingItems"
          >
          </VueSelect>
          <div v-if="form.errors.routecode" class="text-danger fs-sm mt-1">
            {{ form.errors.routecode }}
          </div>
        </div>
        <div class="col-md-3">
          <label class="form-label"
            >{{ t.salesman }} <span class="text-danger">*</span></label
          >
          <select
            v-model="form.salesmancode"
            class="form-select"
            :disabled="isView || formMeta.headerLocked"
          >
            <option value="">{{ t.select_salesman }}</option>
            <option
              v-for="salesman in lookupOptions.salesmen"
              :key="salesman.id"
              :value="salesman.id"
            >
              {{ salesman.label }}
            </option>
          </select>
          <div v-if="form.errors.salesmancode" class="text-danger fs-sm mt-1">
            {{ form.errors.salesmancode }}
          </div>
        </div>
        <div class="col-md-2">
          <label class="form-label">{{ t.load_period }}</label>
          <input
            v-model="form.loadperiodnumber"
            type="number"
            min="0"
            class="form-control"
            :readonly="isView || formMeta.headerLocked"
          />
          <div
            v-if="form.errors.loadperiodnumber"
            class="text-danger fs-sm mt-1"
          >
            {{ form.errors.loadperiodnumber }}
          </div>
        </div>
        <div class="col-md-2">
          <label class="form-label">{{ t.status }}</label>
          <div class="form-control-plaintext">
            <span
              class="badge"
              :class="(props.loadData.header?.status ?? 0) ? 'bg-success-subtle text-success' : 'bg-secondary-subtle text-secondary'"
            >
              {{ props.loadData.header?.statuslabel ?? t.not_used }}
            </span>
          </div>
        </div>

        <div class="col-12">
          <h5 class="mb-0">{{ t.load_lines }}</h5>
          <p class="text-muted fs-sm mb-0">{{ t.load_lines_note }}</p>
        </div>

        <div v-if="itemLoadError" class="col-12">
          <div class="alert alert-danger mb-0">{{ itemLoadError }}</div>
        </div>

        <div v-if="form.errors.lines" class="col-12">
          <div class="alert alert-danger mb-0">{{ form.errors.lines }}</div>
        </div>

        <div class="col-12">
          <div class="table-responsive">
            <table class="table table-sm table-bordered align-middle">
              <thead class="table-light">
                <tr>
                  <th style="width: 120px">{{ t.code }}</th>
                  <th>{{ t.item_description }}</th>
                  <th class="text-center" style="width: 80px">UPC</th>
                  <th class="text-end" style="width: 130px">{{ t.cases }}</th>
                  <th class="text-end" style="width: 130px">{{ t.units }}</th>
                  <th class="text-end" style="width: 130px">
                    {{ t.total_units }}
                  </th>
                  <th class="text-end" style="width: 130px">{{ t.case_price }}</th>
                  <th class="text-end" style="width: 130px">{{ t.unit_price }}</th>
                </tr>
              </thead>
              <tbody>
                <tr v-if="!form.lines.length">
                  <td colspan="8" class="text-center text-muted py-4">
                    {{ t.no_load_lines }}
                  </td>
                </tr>
                <tr v-for="(line, index) in form.lines" :key="line.itemcode">
                  <td class="fw-semibold">{{ line.display_code }}</td>
                  <td>{{ line.description }}</td>
                  <td class="text-center">{{ line.upc }}</td>
                  <td>
                    <input
                      v-model="form.lines[index].cases"
                      type="number"
                      min="0"
                      class="form-control form-control-sm text-end"
                      :readonly="isView"
                    />
                  </td>
                  <td>
                    <input
                      v-model="form.lines[index].units"
                      type="number"
                      min="0"
                      class="form-control form-control-sm text-end"
                      :readonly="isView"
                    />
                  </td>
                  <td class="text-end fw-semibold">{{ totalUnits(line) }}</td>
                  <td class="text-end">
                    {{ formatAmount(line.caseprice) }}
                  </td>
                  <td class="text-end">
                    {{ formatAmount(line.salesprice) }}
                  </td>
                </tr>
              </tbody>
            </table>
          </div>
        </div>
      </div>
    </BaseBlock>
  </div>
</template>

<style lang="scss">
@import "vue-select/dist/vue-select.css";
@import "@scss/vendor/vue-select";
</style>
