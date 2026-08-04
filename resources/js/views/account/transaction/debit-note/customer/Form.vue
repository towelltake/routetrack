<script setup>
import axios from "axios";
import { computed, ref } from "vue";
import { Head, router, useForm, usePage } from "@inertiajs/vue3";

const props = defineProps({
  mode: { type: String, required: true },
  filters: { type: Object, required: true },
  routeOptions: { type: Array, required: true },
  debitNoteCustomerData: { type: Object, required: true },
  initialMeta: { type: Object, required: true },
});

const page = usePage();
const t = page.props.translations.ui;
const permission = computed(
  () => page.props.auth?.formPermissions?.["account transaction"] ?? {},
);
const canDelete = computed(() => !!(permission.value.all || permission.value.delete));
const isView = computed(() => props.mode === "view");
const pageTitle = computed(() =>
  isView.value ? t.view_customer_debit_note : t.create_customer_debit_note,
);

const routeMeta = ref({
  salesmancode: props.initialMeta.salesmancode ?? "",
  salesmanname: props.initialMeta.salesmanname ?? "",
  documentnumber: props.initialMeta.documentnumber ?? props.debitNoteCustomerData.documentnumber ?? "",
  invoicenumber: props.initialMeta.invoicenumber ?? props.debitNoteCustomerData.invoicenumber ?? "",
  customerOptions: props.initialMeta.customerOptions ?? [],
  invoiceOptions: props.initialMeta.invoiceOptions ?? [],
});

const invoiceDetail = ref({
  totalinvoiceamount: props.debitNoteCustomerData.invoiceamount ?? 0,
  invoicebalance: props.debitNoteCustomerData.invoicebalance ?? 0,
});

const form = useForm({
  date: props.filters.date,
  routecode: props.debitNoteCustomerData.routecode ?? "",
  salesmancode: props.debitNoteCustomerData.salesmancode ?? "",
  customercode: props.debitNoteCustomerData.customercode ?? "",
  sourceinvoice: props.debitNoteCustomerData.sourceinvoice ?? "",
  amount: props.debitNoteCustomerData.amount ?? "",
  remarks1: props.debitNoteCustomerData.remarks1 ?? "",
  remarks2: props.debitNoteCustomerData.remarks2 ?? "",
  erpreferencenumber: props.debitNoteCustomerData.erpreferencenumber ?? "",
});

const routeLabel = computed(() => {
  if (isView.value) {
    return props.debitNoteCustomerData.routeLabel || "-";
  }

  return props.routeOptions.find((option) => String(option.id) === String(form.routecode))?.label || "-";
});

const customerLabel = computed(() => {
  if (isView.value) {
    return props.debitNoteCustomerData.customerLabel || "-";
  }

  return routeMeta.value.customerOptions.find((option) => String(option.id) === String(form.customercode))?.label || "-";
});

async function loadRouteMeta(resetCustomer = true) {
  if (!form.routecode || isView.value) {
    routeMeta.value = {
      salesmancode: "",
      salesmanname: "",
      documentnumber: "",
      invoicenumber: "",
      customerOptions: [],
      invoiceOptions: [],
    };
    invoiceDetail.value = { totalinvoiceamount: 0, invoicebalance: 0 };
    form.salesmancode = "";
    form.sourceinvoice = "";
    if (resetCustomer) {
      form.customercode = "";
    }
    return;
  }

  const { data } = await axios.get("/account/transaction/debit-note/customer/route-meta", {
    params: { routecode: form.routecode },
  });

  routeMeta.value = {
    salesmancode: data.salesmancode ?? "",
    salesmanname: data.salesmanname ?? "",
    documentnumber: data.documentnumber ?? "",
    invoicenumber: data.invoicenumber ?? "",
    customerOptions: data.customerOptions ?? [],
    invoiceOptions: [],
  };

  form.salesmancode = data.salesmancode ?? "";
  form.sourceinvoice = "";
  invoiceDetail.value = { totalinvoiceamount: 0, invoicebalance: 0 };

  if (resetCustomer) {
    form.customercode = "";
  }
}

async function loadInvoices(resetInvoice = true) {
  if (!form.customercode || isView.value) {
    routeMeta.value.invoiceOptions = [];
    if (resetInvoice) {
      form.sourceinvoice = "";
    }
    invoiceDetail.value = { totalinvoiceamount: 0, invoicebalance: 0 };
    return;
  }

  const { data } = await axios.get("/account/transaction/debit-note/customer/invoices", {
    params: {
      customercode: form.customercode,
      date: form.date,
    },
  });

  routeMeta.value.invoiceOptions = data.invoiceOptions ?? [];

  if (resetInvoice) {
    form.sourceinvoice = "";
  }

  invoiceDetail.value = { totalinvoiceamount: 0, invoicebalance: 0 };
}

async function loadInvoiceDetail() {
  if (!form.sourceinvoice || isView.value) {
    invoiceDetail.value = { totalinvoiceamount: 0, invoicebalance: 0 };
    return;
  }

  const { data } = await axios.get("/account/transaction/debit-note/customer/invoice-detail", {
    params: { invoiceid: form.sourceinvoice },
  });

  invoiceDetail.value = {
    totalinvoiceamount: data.totalinvoiceamount ?? 0,
    invoicebalance: data.invoicebalance ?? 0,
  };
}

function submit() {
  if (isView.value) {
    return;
  }

  form.post("/account/transaction/debit-note/customer");
}

function backToOverview() {
  router.get("/account/transaction/debit-note/customer", { date: props.filters.date });
}

function removeRecord() {
  if (!props.debitNoteCustomerData.transactionkey || !window.confirm(t.customer_debit_note_delete_confirm)) {
    return;
  }

  router.delete(`/account/transaction/debit-note/customer/${props.debitNoteCustomerData.transactionkey}`, {
    data: { date: props.filters.date },
  });
}
</script>

<template>
  <Head :title="pageTitle" />

  <BasePageHeading
    :title="pageTitle"
    :subtitle="t.debit_note_customer_note"
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
    <BaseBlock :title="t.debit_note_customer_details">
      <div class="row g-4 mb-3">
        <div class="col-md-3">
          <label class="form-label">{{ t.selected_date }}</label>
          <input v-model="form.date" type="date" class="form-control" :readonly="isView" />
        </div>
        <div class="col-md-3">
          <label class="form-label">{{ t.document_no }}</label>
          <input :value="isView ? debitNoteCustomerData.documentnumber : routeMeta.documentnumber" class="form-control" readonly />
        </div>
        <div class="col-md-3">
          <label class="form-label">{{ t.invoice_number }}</label>
          <input :value="isView ? debitNoteCustomerData.invoicenumber : routeMeta.invoicenumber" class="form-control" readonly />
        </div>

        <div class="col-md-4">
          <label class="form-label">{{ t.route }} <span v-if="!isView" class="text-danger">*</span></label>
          <select v-if="!isView" v-model="form.routecode" class="form-select" @change="loadRouteMeta()">
            <option value="">{{ t.select_route }}</option>
            <option v-for="option in routeOptions" :key="option.id" :value="option.id">{{ option.label }}</option>
          </select>
          <input v-else :value="routeLabel" class="form-control" readonly />
          <div v-if="form.errors.routecode" class="text-danger fs-sm mt-1">{{ form.errors.routecode }}</div>
        </div>
        <div class="col-md-4">
          <label class="form-label">{{ t.salesman }}</label>
          <input
            :value="isView ? `${debitNoteCustomerData.salesmancode} - ${debitNoteCustomerData.salesmanname || '-'}` : routeMeta.salesmanname ? `${routeMeta.salesmancode} - ${routeMeta.salesmanname}` : ''"
            class="form-control"
            readonly
          />
        </div>
        <div class="col-md-4">
          <label class="form-label">{{ t.customer }} <span v-if="!isView" class="text-danger">*</span></label>
          <select v-if="!isView" v-model="form.customercode" class="form-select" @change="loadInvoices()">
            <option value="">{{ t.select_customer }}</option>
            <option v-for="option in routeMeta.customerOptions" :key="option.id" :value="option.id">{{ option.label }}</option>
          </select>
          <input v-else :value="customerLabel" class="form-control" readonly />
          <div v-if="form.errors.customercode" class="text-danger fs-sm mt-1">{{ form.errors.customercode }}</div>
        </div>

        <div class="col-md-4">
          <label class="form-label">{{ t.source_invoice }} <span v-if="!isView" class="text-danger">*</span></label>
          <select v-if="!isView" v-model="form.sourceinvoice" class="form-select" @change="loadInvoiceDetail">
            <option value="">{{ t.select }}</option>
            <option v-for="option in routeMeta.invoiceOptions" :key="option.id" :value="option.id">{{ option.label }}</option>
          </select>
          <input v-else :value="debitNoteCustomerData.sourceInvoiceLabel || debitNoteCustomerData.sourceinvoice || '-'" class="form-control" readonly />
          <div v-if="form.errors.sourceinvoice" class="text-danger fs-sm mt-1">{{ form.errors.sourceinvoice }}</div>
        </div>
        <div class="col-md-4">
          <label class="form-label">{{ t.invoice_amount }}</label>
          <input :value="invoiceDetail.totalinvoiceamount" class="form-control" readonly />
        </div>
        <div class="col-md-4">
          <label class="form-label">{{ t.present_balance }}</label>
          <input :value="invoiceDetail.invoicebalance" class="form-control" readonly />
        </div>

        <div class="col-md-4">
          <label class="form-label">{{ t.amount }} <span v-if="!isView" class="text-danger">*</span></label>
          <input v-model="form.amount" type="number" step="0.01" class="form-control" :readonly="isView" />
          <div v-if="form.errors.amount" class="text-danger fs-sm mt-1">{{ form.errors.amount }}</div>
        </div>
        <div class="col-md-4">
          <label class="form-label">{{ t.erp_reference_no }}</label>
          <input v-model="form.erpreferencenumber" class="form-control" :readonly="isView" />
        </div>
        <div class="col-md-4">
          <label class="form-label">{{ t.remark_1 }}</label>
          <input v-model="form.remarks1" class="form-control" :readonly="isView" />
        </div>
        <div class="col-md-4">
          <label class="form-label">{{ t.remark_2 }}</label>
          <input v-model="form.remarks2" class="form-control" :readonly="isView" />
        </div>
      </div>
    </BaseBlock>
  </div>
</template>
