<script setup>
import axios from "axios";
import { computed, reactive, ref } from "vue";
import { Head, Link, usePage } from "@inertiajs/vue3";
import { usePermissions } from "@/composables/usePermissions";
import { useAmountFormatter } from "@/composables/useAmountFormatter";

const props = defineProps({
  mode: { type: String, required: true },
  loadRequestData: { type: Object, required: true },
  lookupOptions: { type: Object, required: true },
  formMeta: { type: Object, required: true },
});

const page = usePage();
const t = page.props.translations.ui;
const locale = page.props.locale;
const { can } = usePermissions();
const { formatAmount } = useAmountFormatter();

const isCreate = computed(() => props.mode === "create");
const canMaintain = computed(() => (isCreate.value ? can("load request", "create") : can("load request", "edit")));

const form = reactive({
  detailkey: props.loadRequestData.header?.detailkey ?? null,
  routecode: props.loadRequestData.header?.routecode ?? "",
  routename: props.loadRequestData.header?.routename ?? "",
  salesmancode: props.loadRequestData.header?.salesmancode ?? "",
  salesmanname1: props.loadRequestData.header?.salesmanname1 ?? "",
  documentnumber: props.loadRequestData.header?.documentnumber ?? "",
  transactiondate: props.loadRequestData.header?.transactiondate ?? "",
  transactiontime: props.loadRequestData.header?.transactiontime ?? "",
  requestdate: props.loadRequestData.header?.requestdate ?? "",
  depotroute: props.loadRequestData.header?.depotroute ?? "",
  isurgent: Number(props.loadRequestData.header?.isurgent ?? 0),
  transmitindicator: Number(props.loadRequestData.header?.transmitindicator ?? 0),
  receivedtime: props.loadRequestData.header?.receivedtime ?? "",
  itemcode: "",
  upc: "",
  itemcaseprice: "",
  itemprice: "",
  cases: "",
  pieces: "",
});

const lines = ref((props.loadRequestData.lines ?? []).map((line) => ({
  ...line,
  editCases: line.cases,
  editPieces: line.pieces,
})));
const items = ref(props.lookupOptions.items ?? []);
const loadingRoute = ref(false);
const addingLine = ref(false);
const savingHeader = ref(false);
const updatingLineId = ref(null);
const pageError = ref("");

const headerLocked = computed(() => isCreate.value && !!form.detailkey);
const transmitLocked = computed(() => Number(form.transmitindicator) === 1);
const editable = computed(() => canMaintain.value && !transmitLocked.value);

function salesmanLabel() {
  return locale === "ar"
    ? (props.loadRequestData.header?.arbsalesmanname1 || form.salesmanname1 || "")
    : (form.salesmanname1 || props.loadRequestData.header?.arbsalesmanname1 || "");
}

function totalAmount(line) {
  return formatAmount(line.total_amount);
}

async function loadRouteMeta() {
  if (!form.routecode) {
    form.salesmancode = "";
    form.salesmanname1 = "";
    items.value = [];
    return;
  }

  pageError.value = "";
  loadingRoute.value = true;

  try {
    const { data } = await axios.get(props.formMeta.routeMetaUrl, {
      params: { routecode: form.routecode },
    });

    form.salesmancode = data.route?.salesmancode ?? "";
    form.salesmanname1 = data.route?.salesmanname ?? "";
    form.routename = data.route?.routename ?? "";
    items.value = data.items ?? [];
    pageError.value = data.warning ?? "";
  } catch (error) {
    pageError.value = error?.response?.data?.message ?? (t.failed_to_load_route_items ?? "Failed to load route items.");
  } finally {
    loadingRoute.value = false;
  }
}

async function loadItemMeta() {
  form.upc = "";
  form.itemcaseprice = "";
  form.itemprice = "";

  if (!form.routecode || !form.itemcode) {
    return;
  }

  pageError.value = "";

  try {
    const { data } = await axios.get(props.formMeta.itemMetaUrl, {
      params: {
        routecode: form.routecode,
        itemcode: form.itemcode,
      },
    });

    form.upc = data.upc ?? "";
    form.itemcaseprice = formatAmount(data.caseprice);
    form.itemprice = formatAmount(data.salesprice);
  } catch (error) {
    pageError.value = error?.response?.data?.message ?? (t.failed_to_load_item_details ?? "Failed to load item details.");
  }
}

async function addLine() {
  if (!form.routecode || !form.requestdate) {
    pageError.value = t.select_route_and_request_date_first ?? "Select route and request date first.";
    return;
  }

  if (!form.salesmancode) {
    pageError.value = t.selected_route_has_no_salesman_assigned ?? "Selected route has no salesman assigned.";
    return;
  }

  if (!form.itemcode) {
    pageError.value = t.select_item ?? "Select an item.";
    return;
  }

  if (!Number(form.cases || 0) && !Number(form.pieces || 0)) {
    pageError.value = t.enter_cases_or_pieces ?? "Please enter cases or pieces.";
    return;
  }

  addingLine.value = true;
  pageError.value = "";

  try {
    const { data } = await axios.post(props.formMeta.lineStoreUrl, {
      detailkey: form.detailkey,
      routecode: Number(form.routecode),
      salesmancode: Number(form.salesmancode),
      requestdate: form.requestdate,
      depotroute: form.depotroute ? Number(form.depotroute) : null,
      isurgent: Number(form.isurgent) === 1,
      itemcode: Number(form.itemcode),
      cases: Number(form.cases || 0),
      pieces: Number(form.pieces || 0),
    });

    hydratePayload(data);
    resetItemEntry();
  } catch (error) {
    pageError.value = error?.response?.data?.message ?? (t.failed_to_add_item ?? "Failed to add item.");
  } finally {
    addingLine.value = false;
  }
}

async function saveHeader() {
  if (!form.detailkey) {
    return;
  }

  savingHeader.value = true;
  pageError.value = "";

  try {
    const { data } = await axios.patch(`${props.formMeta.headerUpdateBaseUrl}/${form.detailkey}`, {
      requestdate: form.requestdate,
      depotroute: form.depotroute ? Number(form.depotroute) : null,
      isurgent: Number(form.isurgent) === 1,
    });

    hydratePayload(data);
  } catch (error) {
    pageError.value = error?.response?.data?.message ?? (t.failed_to_update_request_header ?? "Failed to update request header.");
  } finally {
    savingHeader.value = false;
  }
}

async function updateLine(line) {
  updatingLineId.value = line.primary_key;
  pageError.value = "";

  try {
    const { data } = await axios.put(`${props.formMeta.lineUpdateBaseUrl}/${line.primary_key}`, {
      cases: Number(line.editCases || 0),
      pieces: Number(line.editPieces || 0),
    });

    hydratePayload(data);
  } catch (error) {
    pageError.value = error?.response?.data?.message ?? (t.failed_to_update_line ?? "Failed to update line.");
  } finally {
    updatingLineId.value = null;
  }
}

function hydratePayload(payload) {
  const header = payload.header ?? {};

  form.detailkey = header.detailkey ?? form.detailkey;
  form.routecode = header.routecode ?? form.routecode;
  form.routename = header.routename ?? form.routename;
  form.salesmancode = header.salesmancode ?? form.salesmancode;
  form.salesmanname1 = header.salesmanname1 ?? form.salesmanname1;
  form.documentnumber = header.documentnumber ?? form.documentnumber;
  form.transactiondate = header.transactiondate ?? form.transactiondate;
  form.transactiontime = header.transactiontime ?? form.transactiontime;
  form.requestdate = header.requestdate ?? form.requestdate;
  form.depotroute = header.depotroute ?? form.depotroute;
  form.isurgent = Number(header.isurgent ?? form.isurgent);
  form.transmitindicator = Number(header.transmitindicator ?? form.transmitindicator);
  form.receivedtime = header.receivedtime ?? form.receivedtime;

  lines.value = (payload.lines ?? []).map((line) => ({
    ...line,
    editCases: line.cases,
    editPieces: line.pieces,
  }));
}

function resetItemEntry() {
  form.itemcode = "";
  form.upc = "";
  form.itemcaseprice = "";
  form.itemprice = "";
  form.cases = "";
  form.pieces = "";
}
</script>

<template>
  <Head :title="isCreate ? (t.create_load_request ?? 'Create Load Request') : (t.load_request ?? 'Load Request')" />

  <BasePageHeading
    :title="isCreate ? (t.create_load_request ?? 'Create Load Request') : (t.load_request ?? 'Load Request')"
    :subtitle="t.load_request_note ?? 'Review route-wise load requests using the legacy overview workflow'"
  >
    <template #extra>
      <div class="d-flex gap-2">
        <Link :href="props.formMeta.backUrl || '/transaction/load-request'" class="btn btn-alt-secondary">
          <i class="fa fa-arrow-left me-1"></i> {{ t.back ?? "Back" }}
        </Link>
        <button v-if="!isCreate && editable" class="btn btn-primary" :disabled="savingHeader" @click="saveHeader">
          <i class="fa fa-floppy-disk me-1"></i> {{ savingHeader ? (t.saving ?? "Saving...") : (t.save ?? "Save") }}
        </button>
      </div>
    </template>
  </BasePageHeading>

  <div class="content">
    <BaseBlock :title="t.load_request ?? 'Load Request'">
      <div class="row g-4 mb-3">
        <div v-if="!isCreate" class="col-md-4">
          <label class="form-label">{{ t.transaction_date ?? "Transaction Date" }}</label>
          <input :value="form.transactiondate" class="form-control" readonly />
        </div>
        <div v-if="!isCreate" class="col-md-4">
          <label class="form-label">{{ t.transaction_time ?? "Transaction Time" }}</label>
          <input :value="form.transactiontime" class="form-control" readonly />
        </div>
        <div v-if="!isCreate" class="col-md-4">
          <label class="form-label">{{ t.document_no ?? "Document No." }}</label>
          <input :value="form.documentnumber" class="form-control" readonly />
        </div>

        <div class="col-md-4">
          <label class="form-label">{{ t.route ?? "Route" }}</label>
          <select v-model="form.routecode" class="form-select" :disabled="!isCreate || headerLocked || !editable || loadingRoute" @change="loadRouteMeta">
            <option value="">{{ t.select_route ?? "Select Route" }}</option>
            <option v-for="route in lookupOptions.routes" :key="route.id" :value="route.id">{{ route.label }}</option>
          </select>
        </div>
        <div class="col-md-4">
          <label class="form-label">{{ t.salesman ?? "Salesman" }}</label>
          <input :value="form.salesmancode ? `${form.salesmancode} - ${salesmanLabel()}` : ''" class="form-control" readonly />
        </div>
        <div v-if="!isCreate" class="col-md-4">
          <label class="form-label">{{ t.document_valid ?? "Document Valid" }}</label>
          <input :value="props.loadRequestData.header?.documentvalid ?? (t.valid ?? 'Valid')" class="form-control" readonly />
        </div>

        <div class="col-md-4">
          <label class="form-label">{{ t.request_date ?? "Request Date" }}</label>
          <input v-model="form.requestdate" type="date" class="form-control" :disabled="headerLocked || !editable" />
        </div>
        <div class="col-md-4">
          <label class="form-label">{{ t.depot_warehouse ?? "Depot / Warehouse" }}</label>
          <select v-model="form.depotroute" class="form-select" :disabled="headerLocked || !editable">
            <option value="">{{ t.select_depot_warehouse ?? "Select Depot / Warehouse" }}</option>
            <option v-for="route in lookupOptions.depotRoutes" :key="route.id" :value="route.id">{{ route.label }}</option>
          </select>
        </div>
        <div class="col-md-4 d-flex align-items-end">
          <div class="form-check mb-2">
            <input id="load-request-urgent" v-model="form.isurgent" class="form-check-input" type="checkbox" :true-value="1" :false-value="0" :disabled="headerLocked || !editable" />
            <label class="form-check-label" for="load-request-urgent">{{ t.urgent_request ?? "Urgent Request" }}</label>
          </div>
        </div>

        <div v-if="!isCreate && form.receivedtime" class="col-md-4">
          <label class="form-label">{{ t.received_time ?? "Received Time" }}</label>
          <input :value="form.receivedtime" class="form-control" readonly />
        </div>

        <div v-if="transmitLocked" class="col-12">
          <div class="alert alert-warning mb-0">{{ t.warehouse_processed_request_locked ?? "Request Process By Warehouse Can't Edit" }}</div>
        </div>

        <div v-if="pageError" class="col-12">
          <div class="alert alert-danger mb-0">{{ pageError }}</div>
        </div>
      </div>
    </BaseBlock>

    <BaseBlock :title="t.add_item ?? 'Add Item'" class="mt-4">
      <div class="row g-4 align-items-end mb-3">
        <div class="col-md-4">
          <label class="form-label">{{ t.item ?? "Item" }}</label>
          <select v-model="form.itemcode" class="form-select" :disabled="!editable || loadingRoute" @change="loadItemMeta">
            <option value="">{{ t.select_item ?? "Select Item" }}</option>
            <option v-for="item in items" :key="item.id" :value="item.id">{{ item.label }}</option>
          </select>
        </div>
        <div class="col-md-2">
          <label class="form-label">{{ t.upc ?? "UPC" }}</label>
          <input v-model="form.upc" class="form-control" readonly />
        </div>
        <div class="col-md-2">
          <label class="form-label">{{ t.case_price ?? "Case Price" }}</label>
          <input v-model="form.itemcaseprice" class="form-control" readonly />
        </div>
        <div class="col-md-2">
          <label class="form-label">{{ t.pcs_price ?? "Pcs Price" }}</label>
          <input v-model="form.itemprice" class="form-control" readonly />
        </div>
        <div class="col-md-2">
          <button class="btn btn-primary w-100" :disabled="!editable || addingLine" @click="addLine">
            <i class="fa fa-plus me-1"></i> {{ addingLine ? (t.adding ?? "Adding...") : (t.add ?? "Add") }}
          </button>
        </div>

        <div class="col-md-3">
          <label class="form-label">{{ t.case ?? "Case" }}</label>
          <input v-model="form.cases" type="number" min="0" class="form-control" :disabled="!editable" />
        </div>
        <div class="col-md-3">
          <label class="form-label">{{ t.pcs ?? "Pcs" }}</label>
          <input v-model="form.pieces" type="number" min="0" class="form-control" :disabled="!editable" />
        </div>
      </div>
    </BaseBlock>

    <BaseBlock :title="t.load_request_lines ?? 'Load Request Lines'" class="mt-4">
      <div class="table-responsive">
        <table class="table table-hover table-vcenter fs-sm">
          <thead>
            <tr>
              <th>{{ t.item_code ?? "Item Code" }}</th>
              <th>{{ t.item_name ?? "Item Name" }}</th>
              <th class="text-center">{{ t.upc ?? "UPC" }}</th>
              <th class="text-end">{{ t.case_price ?? "Case Price" }}</th>
              <th class="text-end">{{ t.pcs_price ?? "Pcs Price" }}</th>
              <th class="text-end">{{ t.case ?? "Case" }}</th>
              <th class="text-end">{{ t.pcs ?? "Pcs" }}</th>
              <th class="text-end">{{ t.total_amount ?? "Total Amount" }}</th>
              <th class="text-center" style="width: 110px">{{ t.action ?? "Action" }}</th>
            </tr>
          </thead>
          <tbody>
            <tr v-if="!lines.length">
              <td colspan="9" class="text-center text-muted py-4">{{ t.no_records ?? "No records found." }}</td>
            </tr>
            <tr v-for="line in lines" :key="line.primary_key">
              <td>{{ line.display_code }}</td>
              <td>{{ line.description }}</td>
              <td class="text-center">{{ line.upc }}</td>
              <td class="text-end">{{ formatAmount(line.itemcaseprice) }}</td>
              <td class="text-end">{{ formatAmount(line.itemprice) }}</td>
              <td class="text-end">
                <input v-model="line.editCases" type="number" min="0" class="form-control form-control-sm text-end" :readonly="!editable" />
              </td>
              <td class="text-end">
                <input v-model="line.editPieces" type="number" min="0" class="form-control form-control-sm text-end" :readonly="!editable" />
              </td>
              <td class="text-end">{{ totalAmount(line) }}</td>
              <td class="text-center">
                <button class="btn btn-sm btn-alt-primary" :disabled="!editable || updatingLineId === line.primary_key" @click="updateLine(line)">
                  <i class="fa fa-floppy-disk"></i>
                </button>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </BaseBlock>
  </div>
</template>
