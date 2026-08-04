<script setup>
import axios from "axios";
import { computed, reactive, ref } from "vue";
import { Head, router, usePage } from "@inertiajs/vue3";
import VueSelect from "vue-select";
import { useAmountFormatter } from "@/composables/useAmountFormatter";

const props = defineProps({
  loadData: { type: Object, required: true },
  lookupOptions: { type: Object, required: true },
  formMeta: { type: Object, required: true },
  useAlternateCode: { type: Boolean, default: false },
});

const page = usePage();
const t = page.props.translations.ui;
const { formatAmount } = useAmountFormatter();

const loadingMeta = ref(false);
const loadingItem = ref(false);
const populating = ref(false);
const addingLine = ref(false);
const pageError = ref("");

const form = reactive({
  load_date: props.loadData.header?.ddate ?? "",
  routecode: "",
  salesmancode: "",
  salesmanname: "",
  loadperiodnumber: "",
  erpreferencenumber: "",
  itemcode: "",
  upc: "",
  caseprice: "",
  salesprice: "",
  returnprice: "",
  returncaseprice: "",
  cases: "",
  units: "",
  batchnumber: "",
  expirydate: "",
  allowbatchentry: 0,
});

const itemOptions = ref([]);
const lines = ref([]);

const selectedRouteLabel = computed(
  () => props.lookupOptions.routes?.find((option) => String(option.id) === String(form.routecode))?.label ?? "",
);
const routeValue = computed({
  get: () => findOption(props.lookupOptions.routes, form.routecode),
  set: (option) => {
    form.routecode = option ? option.id : "";
  },
});
const itemValue = computed({
  get: () => findOption(itemOptions.value, form.itemcode),
  set: (option) => {
    form.itemcode = option ? option.id : "";
  },
});
const hasLines = computed(() => lines.value.length > 0);
const routeLocked = computed(() => hasLines.value || props.formMeta.loadFromErp);
const batchVisible = computed(() => Number(props.formMeta.batchStatus) === 1);
const requiresBatchEntry = computed(() => batchVisible.value && Number(form.allowbatchentry) === 1);
const methodLabel = computed(() => props.formMeta.loadMethodLabel || t.create_new_load);
const populateButtonLabel = computed(() => {
  switch (Number(props.formMeta.loadMethod)) {
    case 2:
      return t.convert_load_request;
    case 3:
      return t.convert_sales_orders;
    case 4:
      return t.populate_previous_day;
    case 5:
      return t.populate_suggested_load;
    default:
      return t.populate;
  }
});

async function loadRouteMeta() {
  resetItemEntry();
  pageError.value = "";
  lines.value = [];
  itemOptions.value = [];
  form.salesmancode = "";
  form.salesmanname = "";
  form.loadperiodnumber = "";

  if (!form.routecode || !form.load_date) {
    return;
  }

  loadingMeta.value = true;

  try {
    const { data } = await axios.get(props.formMeta.creationMetaUrl, {
      params: {
        routecode: form.routecode,
        load_date: form.load_date,
      },
    });

    form.salesmancode = data.route?.salesmancode ?? "";
    form.salesmanname = data.route?.salesmanname ?? "";
    form.loadperiodnumber = data.loadperiodnumber ?? "";
    itemOptions.value = data.itemOptions ?? [];
    lines.value = data.lines ?? [];
  } catch (error) {
    pageError.value = error?.response?.data?.message ?? t.failed_load_route_details;
  } finally {
    loadingMeta.value = false;
  }
}

async function loadItemMeta() {
  resetItemEntry(false);

  if (!form.routecode || !form.itemcode || !form.load_date) {
    return;
  }

  loadingItem.value = true;

  try {
    const { data } = await axios.get(props.formMeta.itemMetaUrl, {
      params: {
        routecode: form.routecode,
        itemcode: form.itemcode,
        load_date: form.load_date,
      },
    });

    form.upc = data.upc ?? "";
    form.caseprice = formatAmount(data.caseprice);
    form.salesprice = formatAmount(data.salesprice);
    form.returnprice = data.returnprice ?? 0;
    form.returncaseprice = data.returncaseprice ?? 0;
    form.allowbatchentry = data.allowbatchentry ?? 0;
    form.cases = data.prefill_cases > 0 ? data.prefill_cases : "";
    form.units = data.prefill_units > 0 ? data.prefill_units : "";

    if (!requiresBatchEntry.value) {
      form.batchnumber = "NONE";
      form.expirydate = "2099-12-31";
    }
  } catch (error) {
    pageError.value = error?.response?.data?.message ?? t.failed_load_item_details;
  } finally {
    loadingItem.value = false;
  }
}

async function populateLines() {
  if (!form.routecode || !form.salesmancode || !form.loadperiodnumber) {
    pageError.value = t.select_route_and_date_first;
    return;
  }

  populating.value = true;
  pageError.value = "";

  try {
    const { data } = await axios.post(props.formMeta.populateUrl, {
      load_date: form.load_date,
      routecode: form.routecode,
      salesmancode: form.salesmancode,
      loadperiodnumber: form.loadperiodnumber,
    });

    lines.value = data.lines ?? [];
    if ((data.inserted ?? 0) === 0) {
      pageError.value = t.no_source_rows_found;
    }
  } catch (error) {
    pageError.value = error?.response?.data?.message ?? t.failed_populate_load_lines;
  } finally {
    populating.value = false;
  }
}

async function addLine() {
  pageError.value = "";

  if (!form.routecode || !form.salesmancode || !form.loadperiodnumber) {
    pageError.value = t.select_route_and_date_first;
    return;
  }

  if (!form.itemcode) {
    pageError.value = t.select_an_item;
    return;
  }

  if (!Number(form.cases || 0) && !Number(form.units || 0)) {
    pageError.value = t.enter_case_or_pcs_qty;
    return;
  }

  if (requiresBatchEntry.value && (!form.batchnumber || !form.expirydate)) {
    pageError.value = t.batch_and_expiry_required;
    return;
  }

  addingLine.value = true;

  try {
    const { data } = await axios.post(props.formMeta.lineStoreUrl, {
      load_date: form.load_date,
      routecode: form.routecode,
      salesmancode: form.salesmancode,
      loadperiodnumber: form.loadperiodnumber,
      erpreferencenumber: form.erpreferencenumber,
      itemcode: form.itemcode,
      cases: Number(form.cases || 0),
      units: Number(form.units || 0),
      batchnumber: requiresBatchEntry.value ? form.batchnumber : "NONE",
      expirydate: requiresBatchEntry.value ? form.expirydate : "2099-12-31",
    });

    lines.value = data.lines ?? [];
    resetItemEntry();
  } catch (error) {
    pageError.value = error?.response?.data?.message ?? t.failed_add_item;
  } finally {
    addingLine.value = false;
  }
}

async function deleteLine(line) {
  if (!window.confirm(t.delete_load_line_confirm)) {
    return;
  }

  try {
    const { data } = await axios.delete(`${props.formMeta.lineDestroyBaseUrl}/${line.loaddetailcode}`);
    lines.value = data.lines ?? [];
  } catch (error) {
    pageError.value = error?.response?.data?.message ?? t.failed_delete_line;
  }
}

function resetItemEntry(clearItem = true) {
  if (clearItem) {
    form.itemcode = "";
  }
  form.upc = "";
  form.caseprice = "";
  form.salesprice = "";
  form.returnprice = "";
  form.returncaseprice = "";
  form.cases = "";
  form.units = "";
  form.batchnumber = "";
  form.expirydate = "";
  form.allowbatchentry = 0;
}

function findOption(options, value) {
  if (!value) {
    return null;
  }

  return options?.find((option) => String(option.id) === String(value)) ?? null;
}

function totalUnits(line) {
  return (Number(line.cases || 0) * Number(line.upc || 1)) + Number(line.units || 0);
}

function backToIndex() {
  router.get("/inventory/dailysalesmanload");
}
</script>

<template>
  <Head :title="t.create_daily_salesman_load" />

  <BasePageHeading
    :title="t.create_daily_salesman_load"
    :subtitle="t.daily_salesman_load_note"
  >
    <template #extra>
      <div class="d-flex gap-2">
        <button class="btn btn-alt-secondary" @click="backToIndex">
          <i class="fa fa-arrow-left me-1"></i> {{ t.back }}
        </button>
        <button
          v-if="Number(formMeta.loadMethod) >= 2 && !formMeta.loadFromErp"
          class="btn btn-alt-primary"
          :disabled="!form.routecode || !form.loadperiodnumber || populating || loadingMeta"
          @click="populateLines"
        >
          <i class="fa fa-rotate me-1"></i> {{ populating ? t.loading : populateButtonLabel }}
        </button>
      </div>
    </template>
  </BasePageHeading>

  <div class="content">
    <BaseBlock :title="t.daily_salesman_load">
      <div class="row g-4 mb-3">
        <div class="col-md-3">
          <label class="form-label">{{ t.load_date }}</label>
          <input v-model="form.load_date" type="date" class="form-control" :disabled="routeLocked || loadingMeta" @change="loadRouteMeta" />
        </div>
        <div class="col-md-4">
          <label class="form-label">{{ t.route }}</label>
          <VueSelect
            v-model="routeValue"
            :options="lookupOptions.routes"
            label="label"
            :placeholder="t.select_route"
            :disabled="routeLocked || loadingMeta"
            @update:modelValue="loadRouteMeta"
          />
        </div>
        <div class="col-md-3">
          <label class="form-label">{{ t.salesman }}</label>
          <input :value="form.salesmancode ? `${form.salesmancode} -- ${form.salesmanname}` : ''" class="form-control" readonly />
        </div>
        <div class="col-md-2">
          <label class="form-label">{{ t.load_period }}</label>
          <input v-model="form.loadperiodnumber" class="form-control" readonly />
        </div>

        <div class="col-md-4">
          <label class="form-label">{{ t.transaction_type }}</label>
          <input :value="methodLabel" class="form-control" readonly />
        </div>
        <div class="col-md-4">
          <label class="form-label">{{ t.erp_reference_no }}</label>
          <input v-model="form.erpreferencenumber" class="form-control" :readonly="formMeta.loadFromErp" />
        </div>
        <div class="col-md-4">
          <label class="form-label">{{ t.route_selection }}</label>
          <input :value="selectedRouteLabel" class="form-control" readonly />
        </div>

        <div v-if="pageError" class="col-12">
          <div class="alert alert-danger mb-0">{{ pageError }}</div>
        </div>

        <div v-if="formMeta.loadFromErp" class="col-12">
          <div class="alert alert-warning mb-0">
            {{ t.load_creation_disabled_erp }}
          </div>
        </div>
      </div>
    </BaseBlock>

    <BaseBlock :title="t.add_line_item" class="mt-4">
      <div class="row g-4 align-items-end mb-3">
        <div class="col-md-4">
          <label class="form-label">{{ t.item }}</label>
          <VueSelect
            v-model="itemValue"
            :options="itemOptions"
            label="label"
            :placeholder="t.select_item"
            :disabled="formMeta.loadFromErp || loadingMeta || addingLine"
            @update:modelValue="loadItemMeta"
          />
        </div>
        <div class="col-md-2">
          <label class="form-label">{{ t.upc }}</label>
          <input v-model="form.upc" class="form-control" readonly />
        </div>
        <div class="col-md-2">
          <label class="form-label">{{ t.case_price }}</label>
          <input v-model="form.caseprice" class="form-control" readonly />
        </div>
        <div class="col-md-2">
          <label class="form-label">{{ t.sales_price }}</label>
          <input v-model="form.salesprice" class="form-control" readonly />
        </div>
        <div class="col-md-2">
          <button class="btn btn-primary w-100" :disabled="formMeta.loadFromErp || addingLine || loadingItem" @click="addLine">
            <i class="fa fa-plus me-1"></i> {{ addingLine ? t.adding : t.add }}
          </button>
        </div>

        <div class="col-md-2">
          <label class="form-label">{{ t.cases }}</label>
          <input v-model="form.cases" type="number" min="0" class="form-control" :disabled="formMeta.loadFromErp" />
        </div>
        <div class="col-md-2">
          <label class="form-label">{{ t.pcs }}</label>
          <input v-model="form.units" type="number" min="0" class="form-control" :disabled="formMeta.loadFromErp" />
        </div>
        <div v-if="batchVisible" class="col-md-4">
          <label class="form-label">{{ t.batch_number }}</label>
          <input v-model="form.batchnumber" class="form-control" :readonly="!requiresBatchEntry || formMeta.loadFromErp" />
        </div>
        <div v-if="batchVisible" class="col-md-4">
          <label class="form-label">{{ t.expiry_date }}</label>
          <input v-model="form.expirydate" type="date" class="form-control" :readonly="!requiresBatchEntry || formMeta.loadFromErp" />
        </div>
      </div>
    </BaseBlock>

    <BaseBlock :title="t.load_lines" class="mt-4">
      <div class="table-responsive">
        <table class="table table-hover table-vcenter fs-sm">
          <thead>
            <tr>
              <th style="width: 140px">{{ t.code }}</th>
              <th>{{ t.description }}</th>
              <th class="text-center" style="width: 80px">{{ t.upc }}</th>
              <th class="text-end" style="width: 100px">{{ t.cases }}</th>
              <th class="text-end" style="width: 100px">{{ t.pcs }}</th>
              <th class="text-end" style="width: 110px">{{ t.total_units }}</th>
              <th v-if="batchVisible" style="width: 140px">{{ t.batch_number }}</th>
              <th v-if="batchVisible" style="width: 140px">{{ t.expiry_date }}</th>
              <th class="text-center" style="width: 90px">{{ t.actions }}</th>
            </tr>
          </thead>
          <tbody>
            <tr v-if="!lines.length">
              <td :colspan="batchVisible ? 9 : 7" class="text-center text-muted py-4">
                {{ t.no_load_lines_created_yet }}
              </td>
            </tr>
            <tr v-for="line in lines" :key="line.loaddetailcode">
              <td class="fw-semibold">{{ line.display_code }}</td>
              <td>{{ line.description }}</td>
              <td class="text-center">{{ line.upc }}</td>
              <td class="text-end">{{ line.cases }}</td>
              <td class="text-end">{{ line.units }}</td>
              <td class="text-end">{{ totalUnits(line) }}</td>
              <td v-if="batchVisible">{{ line.batchnumber || "-" }}</td>
              <td v-if="batchVisible">{{ line.expirydate || "-" }}</td>
              <td class="text-center">
                <button class="btn btn-sm btn-alt-danger" :disabled="formMeta.loadFromErp" @click="deleteLine(line)">
                  <i class="fa fa-trash"></i>
                </button>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </BaseBlock>
  </div>
</template>

<style lang="scss">
@import "vue-select/dist/vue-select.css";
@import "@scss/vendor/vue-select";
</style>
