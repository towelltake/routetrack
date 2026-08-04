<script setup>
import axios from "axios";
import { computed, ref } from "vue";
import { Head, router, useForm, usePage } from "@inertiajs/vue3";

const props = defineProps({
  mode: { type: String, required: true },
  filters: { type: Object, required: true },
  routeOptions: { type: Array, required: true },
  bankOptions: { type: Array, required: true },
  cashReceiptData: { type: Object, required: true },
  initialMeta: { type: Object, required: true },
  detailRows: { type: Array, required: true },
});

const page = usePage();
const t = page.props.translations.ui;
const permission = computed(
  () => page.props.auth?.formPermissions?.["account transaction"] ?? {},
);
const canDelete = computed(() => !!(permission.value.all || permission.value.delete));
const isView = computed(() => props.mode === "view");
const pageTitle = computed(() =>
  isView.value ? t.view_cashier_receipt : t.create_cashier_receipt,
);

const routeMeta = ref({
  salesmancode: props.initialMeta.salesmancode ?? "",
  salesmanname: props.initialMeta.salesmanname ?? "",
});

const rows = ref(props.detailRows ?? []);

const totals = ref({
  cashamount: Number(props.cashReceiptData.cashamount ?? 0),
  receiptamount: Number(props.cashReceiptData.receiptamount ?? 0),
  chequeamount: Number(props.cashReceiptData.chequeamount ?? 0),
  total: Number(props.cashReceiptData.total ?? 0),
});

const form = useForm({
  date: props.filters.date,
  routecode: props.cashReceiptData.routecode ?? "",
  salesmancode: props.cashReceiptData.salesmancode ?? "",
  bankcode: props.cashReceiptData.bankcode ?? "",
  slipno: props.cashReceiptData.slipno ?? "",
  cashamount: props.cashReceiptData.cashamount ?? 0,
  receiptamount: props.cashReceiptData.receiptamount ?? 0,
  chequeamount: props.cashReceiptData.chequeamount ?? 0,
  total: props.cashReceiptData.total ?? 0,
});

const routeLabel = computed(() => {
  if (isView.value) {
    return props.cashReceiptData.routeLabel || "-";
  }

  return props.routeOptions.find((option) => String(option.id) === String(form.routecode))?.label || "-";
});

const salesmanLabel = computed(() => {
  if (isView.value) {
    return props.cashReceiptData.salesmancode
      ? `${props.cashReceiptData.salesmancode} - ${props.cashReceiptData.salesmanname || "-"}`
      : "-";
  }

  return routeMeta.value.salesmancode
    ? `${routeMeta.value.salesmancode} - ${routeMeta.value.salesmanname || "-"}`
    : "";
});

async function loadRouteMeta() {
  if (!form.routecode || isView.value) {
    routeMeta.value = { salesmancode: "", salesmanname: "" };
    form.salesmancode = "";
    return;
  }

  const { data } = await axios.get("/account/settlement/cash-receipt/route-meta", {
    params: { routecode: form.routecode },
  });

  routeMeta.value = {
    salesmancode: data.salesmancode ?? "",
    salesmanname: data.salesmanname ?? "",
  };
  form.salesmancode = data.salesmancode ?? "";
}

async function populateRows() {
  const { data } = await axios.get("/account/settlement/cash-receipt/populate", {
    params: {
      routecode: form.routecode,
      salesmancode: form.salesmancode,
      date: form.date,
    },
  });

  rows.value = data.rows ?? [];
  totals.value = {
    cashamount: Number(data.totals?.cashamount ?? 0),
    receiptamount: Number(data.totals?.receiptamount ?? 0),
    chequeamount: Number(data.totals?.chequeamount ?? 0),
    total: Number(data.totals?.total ?? 0),
  };

  form.cashamount = totals.value.cashamount;
  form.receiptamount = totals.value.receiptamount;
  form.chequeamount = totals.value.chequeamount;
  form.total = totals.value.total;
}

function submit() {
  if (isView.value) {
    return;
  }

  form.post("/account/settlement/cash-receipt");
}

function backToOverview() {
  router.get("/account/settlement/cash-receipt", { date: props.filters.date });
}

function removeRecord() {
  if (!props.cashReceiptData.documentnumber || !window.confirm(t.cashier_receipt_delete_confirm)) {
    return;
  }

  router.delete(`/account/settlement/cash-receipt/${props.cashReceiptData.documentnumber}`, {
    data: { date: props.filters.date },
  });
}
</script>

<template>
  <Head :title="pageTitle" />

  <BasePageHeading
    :title="pageTitle"
    :subtitle="t.cashier_receipt_note"
  >
    <template #extra>
      <div class="d-flex gap-2">
        <button class="btn btn-alt-secondary" @click="backToOverview">
          <i class="fa fa-arrow-left me-1"></i> {{ t.back }}
        </button>
        <button v-if="!isView" class="btn btn-alt-primary" :disabled="!form.routecode || form.processing" @click="populateRows">
          <i class="fa fa-rotate me-1"></i> {{ t.populate }}
        </button>
        <button v-if="!isView" class="btn btn-primary" :disabled="form.processing" @click="submit">
          <i class="fa fa-floppy-disk me-1"></i> {{ form.processing ? t.saving : t.save }}
        </button>
        <button v-else-if="canDelete" class="btn btn-alt-danger" @click="removeRecord">
          <i class="fa fa-trash me-1"></i> {{ t.delete }}
        </button>
      </div>
    </template>
  </BasePageHeading>

  <div class="content">
    <BaseBlock :title="t.cashier_receipt_details">
      <div class="row g-4 mb-3">
        <div class="col-md-4">
          <label class="form-label">{{ t.route }} <span v-if="!isView" class="text-danger">*</span></label>
          <select v-if="!isView" v-model="form.routecode" class="form-select" @change="loadRouteMeta">
            <option value="">{{ t.select_route }}</option>
            <option v-for="option in routeOptions" :key="option.id" :value="option.id">{{ option.label }}</option>
          </select>
          <input v-else :value="routeLabel" class="form-control" readonly />
          <div v-if="form.errors.routecode" class="text-danger fs-sm mt-1">{{ form.errors.routecode }}</div>
        </div>
        <div class="col-md-4">
          <label class="form-label">{{ t.salesman }}</label>
          <input :value="salesmanLabel" class="form-control" readonly />
          <div v-if="form.errors.salesmancode" class="text-danger fs-sm mt-1">{{ form.errors.salesmancode }}</div>
        </div>
        <div class="col-md-4">
          <label class="form-label">{{ t.route_end_date }}</label>
          <input v-model="form.date" type="date" class="form-control" readonly />
        </div>
      </div>
    </BaseBlock>

    <BaseBlock :title="t.settlement_rows" class="mt-4">
      <div class="table-responsive">
        <table class="table table-hover table-vcenter fs-sm">
          <thead>
            <tr>
              <th>{{ t.trxn_no }}</th>
              <th>{{ t.trxn_date }}</th>
              <th>{{ t.type }}</th>
              <th>{{ t.cheque_no }}</th>
              <th>{{ t.cheque_date }}</th>
              <th>{{ t.bank_master }}</th>
              <th class="text-end">{{ t.inv_amt }}</th>
              <th class="text-end">{{ t.amt_paid_short }}</th>
              <th class="text-end">{{ t.bal_amt }}</th>
            </tr>
          </thead>
          <tbody>
            <tr v-if="!rows.length">
              <td colspan="9" class="text-center text-muted py-4">{{ t.no_settlement_rows_loaded }}</td>
            </tr>
            <tr v-for="row in rows" :key="row.id || row.transactionno">
              <td>{{ row.transactionno }}</td>
              <td>{{ row.transactiondate }}</td>
              <td>{{ row.type }}</td>
              <td>{{ row.checknumber || "-" }}</td>
              <td>{{ row.checkdate || "-" }}</td>
              <td>{{ row.bankname || "-" }}</td>
              <td class="text-end">{{ row.totalinvoiceamount }}</td>
              <td class="text-end">{{ row.paid }}</td>
              <td class="text-end">{{ row.balance }}</td>
            </tr>
          </tbody>
        </table>
      </div>
    </BaseBlock>

    <BaseBlock :title="t.summary" class="mt-4">
      <div class="row g-4 mb-3">
        <div class="col-md-3">
          <label class="form-label">{{ t.cash_amount }}</label>
          <input :value="totals.cashamount" class="form-control" readonly />
        </div>
        <div class="col-md-3">
          <label class="form-label">{{ t.receipt_amount }}</label>
          <input :value="totals.receiptamount" class="form-control" readonly />
        </div>
        <div class="col-md-3">
          <label class="form-label">{{ t.cheque_amount }}</label>
          <input :value="totals.chequeamount" class="form-control" readonly />
          <div v-if="form.errors.chequeamount" class="text-danger fs-sm mt-1">{{ form.errors.chequeamount }}</div>
        </div>
        <div class="col-md-3">
          <label class="form-label">Total</label>
          <input :value="totals.total" class="form-control" readonly />
        </div>
        <div class="col-md-4">
          <label class="form-label">{{ t.bank_master }} <span v-if="!isView" class="text-danger">*</span></label>
          <select v-model="form.bankcode" class="form-select" :disabled="isView">
            <option value="">{{ t.select_bank }}</option>
            <option v-for="option in bankOptions" :key="option.id" :value="option.id">{{ option.label }}</option>
          </select>
          <div v-if="form.errors.bankcode" class="text-danger fs-sm mt-1">{{ form.errors.bankcode }}</div>
        </div>
        <div class="col-md-4">
          <label class="form-label">{{ t.slip_no }} <span v-if="!isView" class="text-danger">*</span></label>
          <input v-model="form.slipno" class="form-control" :readonly="isView" />
          <div v-if="form.errors.slipno" class="text-danger fs-sm mt-1">{{ form.errors.slipno }}</div>
        </div>
      </div>
    </BaseBlock>
  </div>
</template>
