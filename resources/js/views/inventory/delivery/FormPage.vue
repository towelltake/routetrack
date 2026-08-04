<script setup>
import axios from "axios";
import { computed, reactive, ref } from "vue";
import { Head, router, usePage } from "@inertiajs/vue3";
import { useAmountFormatter } from "@/composables/useAmountFormatter";

const props = defineProps({
  mode: { type: String, required: true },
  deliveryData: { type: Object, required: true },
  lookupOptions: { type: Object, required: true },
  formMeta: { type: Object, required: true },
  useAlternateCode: { type: Boolean, default: false },
});

const page = usePage();
const t = page.props.translations.ui;
const { formatAmount } = useAmountFormatter();

const isView = computed(() => props.mode === "view");
const isCreate = computed(() => props.mode === "create");
const pageTitle = computed(() => {
  if (props.mode === "view") return t.delivery;
  return props.mode === "edit" ? t.edit_delivery : t.create_delivery;
});

const pageError = ref("");
const checkingDeliveryNo = ref(false);
const loadingRouteMeta = ref(false);
const loadingItemMeta = ref(false);
const savingLine = ref(false);
const customerOptions = ref(props.lookupOptions.customers ?? []);
const itemOptions = ref(props.lookupOptions.items ?? []);
const lines = ref((props.deliveryData.lines ?? []).map(normalizeLine));

const form = reactive({
  deliverydate: props.deliveryData.header?.deliverydate ?? "",
  deliveryroute: props.deliveryData.header?.deliveryroute ?? "",
  drivercode: props.deliveryData.header?.drivercode ?? "",
  salesmanname: props.deliveryData.header?.salesmanname ?? "",
  deliveryno: props.deliveryData.header?.deliveryno ?? "",
  customercode: props.deliveryData.header?.customercode ?? "",
  orderno: props.deliveryData.header?.orderno ?? "",
  referenceno: props.deliveryData.header?.referenceno ?? "",
  delivered: Number(props.deliveryData.header?.delivered ?? 0),
  statuslabel: props.deliveryData.header?.statuslabel ?? t.not_delivered,
  itemcode: "",
  upc: "",
  caseprice: "",
  salesprice: "",
  delivery_cases: "",
  delivery_units: "",
  free_cases: "",
  free_units: "",
});

const canEditLines = computed(() => !isView.value && Number(form.delivered) === 0);
const routeLocked = computed(() => !isCreate.value || lines.value.length > 0);
const deliveryNoLocked = computed(() => !isCreate.value || lines.value.length > 0);

async function checkDeliveryNumber() {
  if (!isCreate.value || !form.deliveryno) {
    return;
  }

  checkingDeliveryNo.value = true;
  pageError.value = "";

  try {
    const { data } = await axios.get(props.formMeta.deliveryNoStatusUrl, {
      params: { deliveryno: form.deliveryno },
    });

    if (data.duplicate) {
      pageError.value = t.delivery_number_assigned;
      form.deliveryno = "";
    }
  } catch (error) {
    pageError.value = error?.response?.data?.message ?? t.failed_validate_delivery_number;
  } finally {
    checkingDeliveryNo.value = false;
  }
}

async function loadRouteMeta() {
  resetItemEntry();
  form.drivercode = "";
  form.salesmanname = "";
  form.customercode = "";
  customerOptions.value = [];
  itemOptions.value = [];

  if (!form.deliveryroute) {
    return;
  }

  loadingRouteMeta.value = true;
  pageError.value = "";

  try {
    const { data } = await axios.get(props.formMeta.routeMetaUrl, {
      params: { routecode: form.deliveryroute },
    });

    form.drivercode = data.route?.salesmancode ?? "";
    form.salesmanname = data.route?.salesmanname ?? "";
    customerOptions.value = data.customerOptions ?? [];
    itemOptions.value = data.itemOptions ?? [];
  } catch (error) {
    pageError.value = error?.response?.data?.message ?? t.failed_load_route_details;
  } finally {
    loadingRouteMeta.value = false;
  }
}

async function loadItemMeta() {
  resetItemEntry(false);

  if (!form.itemcode || !form.deliveryroute) {
    return;
  }

  loadingItemMeta.value = true;
  pageError.value = "";

  try {
    const { data } = await axios.get(props.formMeta.itemMetaUrl, {
      params: {
        routecode: form.deliveryroute,
        itemcode: form.itemcode,
      },
    });

    form.upc = data.upc ?? "";
    form.caseprice = formatAmount(data.caseprice);
    form.salesprice = formatAmount(data.salesprice);
  } catch (error) {
    pageError.value = error?.response?.data?.message ?? t.failed_load_item_details;
  } finally {
    loadingItemMeta.value = false;
  }
}

async function addLine() {
  pageError.value = "";

  if (!form.deliverydate || !form.deliveryroute || !form.drivercode || !form.deliveryno || !form.customercode || !form.itemcode) {
    pageError.value = t.complete_delivery_header_first;
    return;
  }

  if (!Number(form.delivery_cases || 0) && !Number(form.delivery_units || 0)) {
    pageError.value = t.enter_delivery_qty;
    return;
  }

  savingLine.value = true;

  try {
    const { data } = await axios.post(props.formMeta.lineStoreUrl, {
      deliverydate: form.deliverydate,
      routecode: form.deliveryroute,
      salesmancode: form.drivercode,
      deliveryno: form.deliveryno,
      customercode: form.customercode,
      orderno: form.orderno,
      referenceno: form.referenceno,
      itemcode: form.itemcode,
      upc: form.upc,
      caseprice: Number(form.caseprice || 0),
      salesprice: Number(form.salesprice || 0),
      delivery_cases: Number(form.delivery_cases || 0),
      delivery_units: Number(form.delivery_units || 0),
      free_cases: Number(form.free_cases || 0),
      free_units: Number(form.free_units || 0),
    });

    applyPayload(data);
    resetItemEntry();
  } catch (error) {
    pageError.value = error?.response?.data?.message ?? t.failed_add_line;
  } finally {
    savingLine.value = false;
  }
}

async function saveLine(line) {
  pageError.value = "";

  try {
    const { data } = await axios.put(`${props.formMeta.lineUpdateBaseUrl}/${line.deliveryindex}`, {
      caseprice: Number(line.caseprice || 0),
      salesprice: Number(line.salesprice || 0),
      delivery_cases: Number(line.delivery_cases || 0),
      delivery_units: Number(line.delivery_units || 0),
      free_cases: Number(line.free_cases || 0),
      free_units: Number(line.free_units || 0),
    });

    applyPayload(data);
  } catch (error) {
    pageError.value = error?.response?.data?.message ?? t.failed_update_line;
  }
}

async function deleteLine(line) {
  if (!window.confirm(t.delete_delivery_line_confirm)) {
    return;
  }

  try {
    const { data } = await axios.delete(`${props.formMeta.lineDestroyBaseUrl}/${line.deliveryindex}`);
    applyPayload(data);
  } catch (error) {
    pageError.value = error?.response?.data?.message ?? t.failed_delete_line;
  }
}

function applyPayload(payload) {
  form.deliveryno = payload.header?.deliveryno ?? form.deliveryno;
  form.deliverydate = payload.header?.deliverydate ?? form.deliverydate;
  form.deliveryroute = payload.header?.deliveryroute ?? form.deliveryroute;
  form.drivercode = payload.header?.drivercode ?? form.drivercode;
  form.salesmanname = payload.header?.salesmanname ?? form.salesmanname;
  form.customercode = payload.header?.customercode ?? form.customercode;
  form.orderno = payload.header?.orderno ?? form.orderno;
  form.referenceno = payload.header?.referenceno ?? form.referenceno;
  form.delivered = Number(payload.header?.delivered ?? form.delivered);
  form.statuslabel = payload.header?.statuslabel ?? form.statuslabel;
  lines.value = (payload.lines ?? []).map(normalizeLine);
}

function normalizeLine(line) {
  return {
    ...line,
    caseprice: Number(line.caseprice ?? 0),
    salesprice: Number(line.salesprice ?? 0),
    delivery_cases: Number(line.delivery_cases ?? 0),
    delivery_units: Number(line.delivery_units ?? 0),
    free_cases: Number(line.free_cases ?? 0),
    free_units: Number(line.free_units ?? 0),
  };
}

function resetItemEntry(clearItem = true) {
  if (clearItem) {
    form.itemcode = "";
  }
  form.upc = "";
  form.caseprice = "";
  form.salesprice = "";
  form.delivery_cases = "";
  form.delivery_units = "";
  form.free_cases = "";
  form.free_units = "";
}

function customerLabel() {
  return customerOptions.value.find((option) => String(option.id) === String(form.customercode))?.label ?? "";
}

function backToIndex() {
  router.get("/inventory/delivery");
}

function totalQty(cases, units, upc) {
  return (Number(cases || 0) * Number(upc || 1)) + Number(units || 0);
}
</script>

<template>
  <Head :title="pageTitle" />

  <BasePageHeading :title="pageTitle" :subtitle="t.delivery_note">
    <template #extra>
      <div class="d-flex gap-2">
        <button class="btn btn-alt-secondary" @click="backToIndex">
          <i class="fa fa-arrow-left me-1"></i> {{ t.back }}
        </button>
      </div>
    </template>
  </BasePageHeading>

  <div class="content">
    <BaseBlock :title="t.delivery">
      <div class="row g-4 mb-3">
        <div class="col-md-3">
          <label class="form-label">{{ t.delivery_date }}</label>
          <input v-model="form.deliverydate" type="date" class="form-control" :disabled="routeLocked" />
        </div>
        <div class="col-md-3">
          <label class="form-label">{{ t.delivery_route }}</label>
          <select v-model="form.deliveryroute" class="form-select" :disabled="routeLocked || isView" @change="loadRouteMeta">
            <option value="">{{ t.select_route }}</option>
            <option v-for="route in lookupOptions.routes" :key="route.id" :value="route.id">
              {{ route.label }}
            </option>
          </select>
        </div>
        <div class="col-md-3">
          <label class="form-label">{{ t.salesman }}</label>
          <input :value="form.drivercode ? `${form.drivercode} -- ${form.salesmanname}` : ''" class="form-control" readonly />
        </div>
        <div class="col-md-3">
          <label class="form-label">{{ t.delivery_no }}</label>
          <input v-model="form.deliveryno" type="number" min="1" class="form-control" :readonly="deliveryNoLocked || isView" @blur="checkDeliveryNumber" />
        </div>

        <div class="col-md-4">
          <label class="form-label">{{ t.customer }}</label>
          <select v-if="!isView" v-model="form.customercode" class="form-select" :disabled="routeLocked || loadingRouteMeta">
            <option value="">{{ t.select_customer }}</option>
            <option v-for="customer in customerOptions" :key="customer.id" :value="customer.id">
              {{ customer.label }}
            </option>
          </select>
          <input v-else :value="customerLabel()" class="form-control" readonly />
        </div>
        <div class="col-md-4">
          <label class="form-label">{{ t.order_no }}</label>
          <input v-model="form.orderno" class="form-control" :readonly="routeLocked || isView" />
        </div>
        <div class="col-md-4">
          <label class="form-label">{{ t.reference_no }}</label>
          <input v-model="form.referenceno" class="form-control" :readonly="routeLocked || isView" />
        </div>

        <div class="col-md-4">
          <label class="form-label">{{ t.delivery_status }}</label>
          <input :value="form.statuslabel" class="form-control" readonly />
        </div>

        <div v-if="pageError" class="col-12">
          <div class="alert alert-danger mb-0">{{ pageError }}</div>
        </div>
      </div>
    </BaseBlock>

    <BaseBlock v-if="canEditLines" :title="t.add_line_item" class="mt-4">
      <div class="row g-4 align-items-end mb-3">
        <div class="col-md-4">
          <label class="form-label">{{ t.item }}</label>
          <select v-model="form.itemcode" class="form-select" :disabled="loadingRouteMeta || savingLine" @change="loadItemMeta">
            <option value="">{{ t.select_item }}</option>
            <option v-for="item in itemOptions" :key="item.id" :value="item.id">
              {{ item.label }}
            </option>
          </select>
        </div>
        <div class="col-md-2">
          <label class="form-label">{{ t.upc }}</label>
          <input v-model="form.upc" class="form-control" readonly />
        </div>
        <div class="col-md-2">
          <label class="form-label">{{ t.case_price }}</label>
          <input v-model="form.caseprice" class="form-control" />
        </div>
        <div class="col-md-2">
          <label class="form-label">{{ t.pcs_price }}</label>
          <input v-model="form.salesprice" class="form-control" />
        </div>
        <div class="col-md-2">
          <button class="btn btn-primary w-100" :disabled="savingLine || loadingItemMeta" @click="addLine">
            <i class="fa fa-plus me-1"></i> {{ savingLine ? t.adding : t.add }}
          </button>
        </div>

        <div class="col-md-3">
          <label class="form-label">{{ t.delivery_case }}</label>
          <input v-model="form.delivery_cases" type="number" min="0" class="form-control" />
        </div>
        <div class="col-md-3">
          <label class="form-label">{{ t.delivery_pcs }}</label>
          <input v-model="form.delivery_units" type="number" min="0" class="form-control" />
        </div>
        <div class="col-md-3">
          <label class="form-label">{{ t.free_case }}</label>
          <input v-model="form.free_cases" type="number" min="0" class="form-control" />
        </div>
        <div class="col-md-3">
          <label class="form-label">{{ t.free_pcs }}</label>
          <input v-model="form.free_units" type="number" min="0" class="form-control" />
        </div>
      </div>
    </BaseBlock>

    <BaseBlock :title="t.delivery_lines" class="mt-4">
      <div class="table-responsive">
        <table class="table table-hover table-vcenter fs-sm">
          <thead>
            <tr>
              <th style="width: 140px">{{ t.code }}</th>
              <th>{{ t.description }}</th>
              <th class="text-center" style="width: 70px">{{ t.upc }}</th>
              <th class="text-end" style="width: 110px">{{ t.case_price }}</th>
              <th class="text-end" style="width: 110px">{{ t.pcs_price }}</th>
              <th class="text-end" style="width: 90px">{{ t.delivery_case }}</th>
              <th class="text-end" style="width: 90px">{{ t.delivery_pcs }}</th>
              <th class="text-end" style="width: 90px">{{ t.free_case }}</th>
              <th class="text-end" style="width: 90px">{{ t.free_pcs }}</th>
              <th class="text-end" style="width: 90px">{{ t.total_units }}</th>
              <th v-if="canEditLines" class="text-center" style="width: 130px">{{ t.actions }}</th>
            </tr>
          </thead>
          <tbody>
            <tr v-if="!lines.length">
              <td :colspan="canEditLines ? 11 : 10" class="text-center text-muted py-4">
                {{ t.no_delivery_lines }}
              </td>
            </tr>
            <tr v-for="line in lines" :key="line.deliveryindex">
              <td class="fw-semibold">{{ line.display_code }}</td>
              <td>{{ line.description }}</td>
              <td class="text-center">{{ line.upc }}</td>
              <td v-if="canEditLines">
                <input v-model="line.caseprice" type="number" min="0" step="0.0001" class="form-control form-control-sm text-end" />
              </td>
              <td v-else class="text-end">{{ formatAmount(line.caseprice) }}</td>
              <td v-if="canEditLines">
                <input v-model="line.salesprice" type="number" min="0" step="0.0001" class="form-control form-control-sm text-end" />
              </td>
              <td v-else class="text-end">{{ formatAmount(line.salesprice) }}</td>
              <td v-if="canEditLines">
                <input v-model="line.delivery_cases" type="number" min="0" class="form-control form-control-sm text-end" />
              </td>
              <td v-else class="text-end">{{ line.delivery_cases }}</td>
              <td v-if="canEditLines">
                <input v-model="line.delivery_units" type="number" min="0" class="form-control form-control-sm text-end" />
              </td>
              <td v-else class="text-end">{{ line.delivery_units }}</td>
              <td v-if="canEditLines">
                <input v-model="line.free_cases" type="number" min="0" class="form-control form-control-sm text-end" />
              </td>
              <td v-else class="text-end">{{ line.free_cases }}</td>
              <td v-if="canEditLines">
                <input v-model="line.free_units" type="number" min="0" class="form-control form-control-sm text-end" />
              </td>
              <td v-else class="text-end">{{ line.free_units }}</td>
              <td class="text-end">{{ totalQty(line.delivery_cases, line.delivery_units, line.upc) }}</td>
              <td v-if="canEditLines" class="text-center text-nowrap">
                <button class="btn btn-sm btn-alt-primary me-1" @click="saveLine(line)">
                  <i class="fa fa-floppy-disk"></i>
                </button>
                <button class="btn btn-sm btn-alt-danger" @click="deleteLine(line)">
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
