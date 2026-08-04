<script setup>
import axios from "axios";
import { computed, ref, watch } from "vue";
import { Head, router, useForm, usePage } from "@inertiajs/vue3";
import { useAmountFormatter } from "@/composables/useAmountFormatter";

const props = defineProps({
  mode: { type: String, required: true },
  filters: { type: Object, required: true },
  routeOptions: { type: Array, required: true },
  bankOptions: { type: Array, required: true },
  hoCollectionData: { type: Object, required: true },
  initialMeta: { type: Object, required: true },
  invoiceRows: { type: Array, required: true },
  paymentDetails: { type: Object, default: null },
});

const page = usePage();
const t = page.props.translations.ui;
const { formatAmount, roundAmount } = useAmountFormatter();
const permission = computed(
  () => page.props.auth?.formPermissions?.["account transaction"] ?? {},
);
const canDelete = computed(() => !!(permission.value.all || permission.value.delete));
const isView = computed(() => props.mode === "view");
const pageTitle = computed(() => (isView.value ? t.view_ho_collection : t.create_ho_collection));

const routeMeta = ref({
  salesmancode: props.initialMeta.salesmancode ?? "",
  salesmanname: props.initialMeta.salesmanname ?? "",
  documentnumber: props.initialMeta.documentnumber ?? props.hoCollectionData.documentnumber ?? "",
  customerOptions: props.initialMeta.customerOptions ?? [],
});

const invoices = ref(
  (props.invoiceRows ?? []).map((row) => ({
    ...row,
    allocatedamount: row.allocatedamount ?? row.invoicebalance ?? 0,
    selected: !!row.selected,
  })),
);

const invoiceTotals = ref({
  invoice_total: props.hoCollectionData.invoiceamount ?? 0,
  balance_total: props.hoCollectionData.balanceamount ?? 0,
});

const form = useForm({
  date: props.filters.date,
  routecode: props.hoCollectionData.routecode ?? "",
  salesmancode: props.hoCollectionData.salesmancode ?? "",
  customercode: props.hoCollectionData.customercode ?? "",
  paymentmode: Number(props.hoCollectionData.paymentmode ?? 0),
  amount: props.hoCollectionData.amount ?? "",
  invoice_total: props.hoCollectionData.invoiceamount ?? 0,
  balance_total: props.hoCollectionData.balanceamount ?? 0,
  erpreferencenumber: props.hoCollectionData.erpreferencenumber ?? "",
  checknumber: props.hoCollectionData.checknumber ?? "",
  checkdate: props.hoCollectionData.checkdate ?? "",
  bankcode: props.hoCollectionData.bankcode ?? "",
  firstoutstanding: !!props.hoCollectionData.firstoutstanding,
  invoice_ids: [],
  invoice_amounts: [],
});

const routeLabel = computed(() => {
  if (isView.value) {
    return props.hoCollectionData.routeLabel || "-";
  }

  return props.routeOptions.find((option) => String(option.id) === String(form.routecode))?.label || "-";
});

const customerLabel = computed(() => {
  if (isView.value) {
    return props.hoCollectionData.customerLabel || "-";
  }

  return routeMeta.value.customerOptions.find((option) => String(option.id) === String(form.customercode))?.label || "-";
});

const salesmanLabel = computed(() => {
  if (isView.value) {
    return props.hoCollectionData.salesmancode
      ? `${props.hoCollectionData.salesmancode} - ${props.hoCollectionData.salesmanname || "-"}`
      : "-";
  }

  return routeMeta.value.salesmancode
    ? `${routeMeta.value.salesmancode} - ${routeMeta.value.salesmanname || "-"}`
    : "";
});

const selectedInvoices = computed(() => invoices.value.filter((row) => row.selected));
const selectedInvoiceTotal = computed(() =>
  selectedInvoices.value.reduce((sum, row) => sum + Number(row.totalinvoiceamount || 0), 0),
);
const selectedBalanceTotal = computed(() =>
  selectedInvoices.value.reduce((sum, row) => sum + Number(row.allocatedamount || 0), 0),
);
const isChequeMode = computed(() => Number(form.paymentmode) === 1);

watch(
  selectedInvoices,
  (rows) => {
    form.invoice_ids = rows.map((row) => row.transactionkey);
    form.invoice_amounts = rows.map((row) => Number(row.allocatedamount || 0));
    form.invoice_total = roundAmount(selectedInvoiceTotal.value);
    form.balance_total = roundAmount(selectedBalanceTotal.value);
  },
  { deep: true, immediate: true },
);

watch(
  () => form.paymentmode,
  (value) => {
    if (Number(value) === 1) {
      return;
    }

    form.checknumber = "";
    form.checkdate = "";
    form.bankcode = "";
  },
);

function formatNumber(value) {
  return formatAmount(value);
}

function resetInvoices() {
  invoices.value = [];
  invoiceTotals.value = { invoice_total: 0, balance_total: 0 };
  form.invoice_ids = [];
  form.invoice_amounts = [];
  form.invoice_total = 0;
  form.balance_total = 0;
  form.amount = "";
}

async function loadRouteMeta(resetCustomer = true) {
  if (!form.routecode || isView.value) {
    routeMeta.value = {
      salesmancode: "",
      salesmanname: "",
      documentnumber: "",
      customerOptions: [],
    };
    form.salesmancode = "";
    if (resetCustomer) {
      form.customercode = "";
    }
    resetInvoices();
    return;
  }

  const { data } = await axios.get("/account/transaction/ho-collection/route-meta", {
    params: { routecode: form.routecode },
  });

  routeMeta.value = {
    salesmancode: data.salesmancode ?? "",
    salesmanname: data.salesmanname ?? "",
    documentnumber: data.documentnumber ?? "",
    customerOptions: data.customerOptions ?? [],
  };

  form.salesmancode = data.salesmancode ?? "";

  if (resetCustomer) {
    form.customercode = "";
  }

  resetInvoices();
}

async function loadInvoices() {
  if (!form.routecode || !form.customercode || isView.value) {
    resetInvoices();
    return;
  }

  const { data } = await axios.get("/account/transaction/ho-collection/invoices", {
    params: {
      routecode: form.routecode,
      customercode: form.customercode,
    },
  });

  invoiceTotals.value = {
    invoice_total: Number(data.totals?.invoice_total ?? 0),
    balance_total: Number(data.totals?.balance_total ?? 0),
  };

  invoices.value = (data.rows ?? []).map((row) => ({
    ...row,
    allocatedamount: Number(row.invoicebalance || 0),
    selected: false,
  }));
}

function submit() {
  if (isView.value) {
    return;
  }

  form.post("/account/transaction/ho-collection");
}

function backToOverview() {
  router.get("/account/transaction/ho-collection", { date: props.filters.date });
}

function removeRecord() {
  if (!props.hoCollectionData.transactionkey || !window.confirm(t.ho_collection_delete_confirm)) {
    return;
  }

  router.delete(`/account/transaction/ho-collection/${props.hoCollectionData.transactionkey}`, {
    data: { date: props.filters.date },
  });
}
</script>

<template>
  <Head :title="pageTitle" />

  <BasePageHeading
    :title="pageTitle"
    :subtitle="t.ho_collection_note"
  >
    <template #extra>
      <div class="d-flex gap-2">
        <button class="btn btn-alt-secondary" @click="backToOverview">
          <i class="fa fa-arrow-left me-1"></i> {{ t.back }}
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
    <BaseBlock :title="t.ho_collection_details">
      <div class="row g-4 mb-3">
        <div class="col-md-3">
          <label class="form-label">{{ t.selected_date }}</label>
          <input v-model="form.date" type="date" class="form-control" :readonly="isView" />
          <div v-if="form.errors.date" class="text-danger fs-sm mt-1">{{ form.errors.date }}</div>
        </div>
        <div class="col-md-3">
          <label class="form-label">{{ t.document_no }}</label>
          <input
            :value="isView ? hoCollectionData.documentnumber : routeMeta.documentnumber"
            class="form-control"
            readonly
          />
        </div>
        <div class="col-md-3">
          <label class="form-label">{{ t.erp_reference_no }}</label>
          <input v-model="form.erpreferencenumber" class="form-control" :readonly="isView" />
          <div v-if="form.errors.erpreferencenumber" class="text-danger fs-sm mt-1">
            {{ form.errors.erpreferencenumber }}
          </div>
        </div>
        <div class="col-md-3 d-flex align-items-end">
          <div class="form-check mb-2">
            <input
              id="first-outstanding"
              v-model="form.firstoutstanding"
              class="form-check-input"
              type="checkbox"
              :disabled="isView"
            />
            <label class="form-check-label" for="first-outstanding">{{ t.first_outstanding }}</label>
          </div>
        </div>

        <div class="col-md-4">
          <label class="form-label">{{ t.route }} <span v-if="!isView" class="text-danger">*</span></label>
          <select v-if="!isView" v-model="form.routecode" class="form-select" @change="loadRouteMeta()">
            <option value="">{{ t.select_route }}</option>
            <option v-for="option in routeOptions" :key="option.id" :value="option.id">
              {{ option.label }}
            </option>
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
          <label class="form-label">{{ t.customer }} <span v-if="!isView" class="text-danger">*</span></label>
          <select v-if="!isView" v-model="form.customercode" class="form-select" @change="loadInvoices">
            <option value="">{{ t.select_customer }}</option>
            <option v-for="option in routeMeta.customerOptions" :key="option.id" :value="option.id">
              {{ option.label }}
            </option>
          </select>
          <input v-else :value="customerLabel" class="form-control" readonly />
          <div v-if="form.errors.customercode" class="text-danger fs-sm mt-1">{{ form.errors.customercode }}</div>
        </div>
      </div>
    </BaseBlock>

    <BaseBlock :title="t.outstanding_invoices" class="mt-4">
      <div class="row g-3 mb-3">
        <div class="col-md-3">
          <label class="form-label">{{ t.selected_invoice_total }}</label>
          <input :value="formatNumber(form.invoice_total)" class="form-control" readonly />
        </div>
        <div class="col-md-3">
          <label class="form-label">{{ t.selected_balance_total }}</label>
          <input :value="formatNumber(form.balance_total)" class="form-control" readonly />
        </div>
        <div class="col-md-3">
          <label class="form-label">{{ t.all_invoice_total }}</label>
          <input :value="formatNumber(invoiceTotals.invoice_total)" class="form-control" readonly />
        </div>
        <div class="col-md-3">
          <label class="form-label">{{ t.all_balance_total }}</label>
          <input :value="formatNumber(invoiceTotals.balance_total)" class="form-control" readonly />
        </div>
      </div>

      <div class="table-responsive">
        <table class="table table-hover table-vcenter fs-sm">
          <thead>
            <tr>
              <th style="width: 48px">{{ t.select_row }}</th>
              <th>{{ t.invoice_no_short }}</th>
              <th>{{ t.date }}</th>
              <th>{{ t.customer }}</th>
              <th class="text-end">{{ t.invoice_amount }}</th>
              <th class="text-end">{{ t.amount_paid }}</th>
              <th class="text-end">{{ t.balance }}</th>
              <th class="text-end">{{ t.pdc_balance }}</th>
              <th class="text-end">{{ t.allocate_amount }}</th>
            </tr>
          </thead>
          <tbody>
            <tr v-if="!invoices.length">
              <td colspan="9" class="text-center text-muted py-4">{{ t.no_outstanding_invoices_found }}</td>
            </tr>
            <tr v-for="row in invoices" :key="row.transactionkey">
              <td>
                <input v-model="row.selected" class="form-check-input" type="checkbox" :disabled="isView" />
              </td>
              <td>{{ row.invoicenumber }}</td>
              <td>{{ row.transactiondate }}</td>
              <td>{{ row.customercode }}</td>
              <td class="text-end">{{ formatNumber(row.totalinvoiceamount) }}</td>
              <td class="text-end">{{ formatNumber(row.amountpaid) }}</td>
              <td class="text-end">{{ formatNumber(row.invoicebalance) }}</td>
              <td class="text-end">{{ formatNumber(row.pdcbalance) }}</td>
              <td class="text-end">
                <input
                  v-model="row.allocatedamount"
                  type="number"
                  step="0.01"
                  min="0"
                  :max="row.invoicebalance"
                  class="form-control text-end"
                  :readonly="isView || !row.selected"
                />
              </td>
            </tr>
          </tbody>
        </table>
      </div>
      <div v-if="form.errors.invoice_ids" class="text-danger fs-sm mt-2">{{ form.errors.invoice_ids }}</div>
    </BaseBlock>

    <BaseBlock :title="t.mode_of_payment" class="mt-4">
      <div class="row g-4 mb-3">
        <div class="col-md-3">
          <label class="form-label">{{ t.payment_mode }} <span v-if="!isView" class="text-danger">*</span></label>
          <select v-model="form.paymentmode" class="form-select" :disabled="isView">
            <option :value="0">{{ t.cash }}</option>
            <option :value="1">{{ t.cheque }}</option>
          </select>
          <div v-if="form.errors.paymentmode" class="text-danger fs-sm mt-1">{{ form.errors.paymentmode }}</div>
        </div>
        <div class="col-md-3">
          <label class="form-label">{{ t.amount_paid }} <span v-if="!isView" class="text-danger">*</span></label>
          <input
            v-model="form.amount"
            type="number"
            step="0.01"
            class="form-control"
            :readonly="isView"
          />
          <div v-if="form.errors.amount" class="text-danger fs-sm mt-1">{{ form.errors.amount }}</div>
        </div>
        <div class="col-md-3">
          <label class="form-label">{{ t.cheque_no }}</label>
          <input v-model="form.checknumber" class="form-control" :readonly="isView || !isChequeMode" />
          <div v-if="form.errors.checknumber" class="text-danger fs-sm mt-1">{{ form.errors.checknumber }}</div>
        </div>
        <div class="col-md-3">
          <label class="form-label">{{ t.cheque_date }}</label>
          <input v-model="form.checkdate" type="date" class="form-control" :readonly="isView || !isChequeMode" />
          <div v-if="form.errors.checkdate" class="text-danger fs-sm mt-1">{{ form.errors.checkdate }}</div>
        </div>
        <div class="col-md-4">
          <label class="form-label">{{ t.bank_master }}</label>
          <select v-model="form.bankcode" class="form-select" :disabled="isView || !isChequeMode">
            <option value="">{{ t.select_bank ?? t.bank_master }}</option>
            <option v-for="option in bankOptions" :key="option.id" :value="option.id">
              {{ option.label }}
            </option>
          </select>
          <div v-if="form.errors.bankcode" class="text-danger fs-sm mt-1">{{ form.errors.bankcode }}</div>
        </div>
      </div>
    </BaseBlock>

    <BaseBlock v-if="isView && paymentDetails" :title="t.saved_payment_details" class="mt-4">
      <div class="row g-4">
        <div class="col-md-3">
          <label class="form-label">{{ t.type }}</label>
          <input :value="paymentDetails.typecode === 1 ? t.cheque : t.cash" class="form-control" readonly />
        </div>
        <div class="col-md-3">
          <label class="form-label">{{ t.amount }}</label>
          <input :value="formatNumber(paymentDetails.amount)" class="form-control" readonly />
        </div>
        <div class="col-md-3">
          <label class="form-label">{{ t.cheque_no }}</label>
          <input :value="paymentDetails.checknumber || '-'" class="form-control" readonly />
        </div>
        <div class="col-md-3">
          <label class="form-label">{{ t.cheque_date }}</label>
          <input :value="paymentDetails.checkdate || '-'" class="form-control" readonly />
        </div>
        <div class="col-md-4">
          <label class="form-label">{{ t.bank_master }}</label>
          <input :value="paymentDetails.bankname || '-'" class="form-control" readonly />
        </div>
      </div>
    </BaseBlock>
  </div>
</template>
