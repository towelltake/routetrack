<script setup>
import { computed } from "vue";
import { Head, Link, usePage } from "@inertiajs/vue3";
import { useAmountFormatter } from "@/composables/useAmountFormatter";

const props = defineProps({
  header: { type: Object, required: true },
  details: { type: Array, required: true },
  payments: { type: Array, required: true },
  filters: { type: Object, required: true },
});

const page = usePage();
const t = page.props.translations.ui;
const locale = page.props.locale;
const { formatAmount } = useAmountFormatter();

const backUrl = computed(() => {
  const params = new URLSearchParams();

  if (props.filters?.date) params.set("date", props.filters.date);
  if (Number(props.filters?.routecode || 0) > 0) params.set("routecode", String(props.filters.routecode));
  if (props.filters?.search) params.set("search", props.filters.search);
  if (props.filters?.page) params.set("page", String(props.filters.page));
  if (props.filters?.per_page) params.set("per_page", String(props.filters.per_page));
  if (props.filters?.sort_by) params.set("sort_by", props.filters.sort_by);
  if (props.filters?.sort_dir) params.set("sort_dir", props.filters.sort_dir);

  return `/transaction/ar-collection?${params.toString()}`;
});

function routeLabel() {
  return locale === "ar"
    ? (props.header.arbroutename || props.header.routename || "")
    : (props.header.routename || props.header.arbroutename || "");
}

function salesmanLabel() {
  return locale === "ar"
    ? (props.header.arbsalesmanname1 || props.header.salesmanname1 || "")
    : (props.header.salesmanname1 || props.header.arbsalesmanname1 || "");
}

function customerLabel() {
  return locale === "ar"
    ? (props.header.arbcustomername || props.header.customername || "")
    : (props.header.customername || props.header.arbcustomername || "");
}

function amount(value) {
  return formatAmount(value);
}
</script>

<template>
  <Head :title="t.ar_collection ?? 'AR Collection'" />

  <BasePageHeading
    :title="t.ar_collection ?? 'AR Collection'"
    :subtitle="t.ar_collection_note ?? 'Review customer receipt collection records by transaction date.'"
  >
    <template #extra>
      <Link class="btn btn-alt-secondary" :href="backUrl">
        <i class="fa fa-arrow-left me-1"></i> {{ t.back ?? "Back" }}
      </Link>
    </template>
  </BasePageHeading>

  <div class="content">
    <BaseBlock :title="t.details ?? 'Details'">
      <div class="row g-3 fs-sm mb-4">
        <div class="col-md-3">
          <label class="form-label">{{ t.document_no ?? "Document No." }}</label>
          <div class="form-control-plaintext">{{ header.documentnumber || "-" }}</div>
        </div>
        <div class="col-md-3">
          <label class="form-label">{{ t.receipt_no ?? "Receipt No." }}</label>
          <div class="form-control-plaintext">{{ header.invoicenumber || "-" }}</div>
        </div>
        <div class="col-md-3">
          <label class="form-label">{{ t.receipt_date ?? "Receipt Date" }}</label>
          <div class="form-control-plaintext">{{ header.transactiondate || "-" }}</div>
        </div>
        <div class="col-md-3">
          <label class="form-label">{{ t.receipt_time ?? "Receipt Time" }}</label>
          <div class="form-control-plaintext">{{ header.transactiontime || "-" }}</div>
        </div>
        <div class="col-md-4">
          <label class="form-label">{{ t.route ?? "Route" }}</label>
          <div class="form-control-plaintext">{{ header.routecode || "-" }} - {{ routeLabel() || "-" }}</div>
        </div>
        <div class="col-md-4">
          <label class="form-label">{{ t.route_start_date ?? "Route Start Date" }}</label>
          <div class="form-control-plaintext">{{ header.routestartdate || "-" }}</div>
        </div>
        <div class="col-md-4">
          <label class="form-label">{{ t.receipt_status ?? "Receipt Status" }}</label>
          <div class="form-control-plaintext">{{ header.documentvalid || "-" }}</div>
        </div>
        <div class="col-md-4">
          <label class="form-label">{{ t.salesman ?? "Salesman" }}</label>
          <div class="form-control-plaintext">{{ header.salesmancode || "-" }} - {{ salesmanLabel() || "-" }}</div>
        </div>
        <div class="col-md-4">
          <label class="form-label">{{ t.customer_code ?? "Customer Code" }}</label>
          <div class="form-control-plaintext">
            {{ header.customercode || "-" }}<span v-if="header.alternatecode"> / {{ header.alternatecode }}</span>
          </div>
        </div>
        <div class="col-md-4">
          <label class="form-label">{{ t.customer_name ?? "Customer Name" }}</label>
          <div class="form-control-plaintext">{{ customerLabel() || "-" }}</div>
        </div>
        <div class="col-md-6">
          <label class="form-label">{{ t.address ?? "Address" }}</label>
          <div class="form-control-plaintext">{{ header.customeraddress1 || "-" }}</div>
        </div>
        <div class="col-md-6">
          <label class="form-label">{{ t.customer_payment_term ?? "Customer Payment Term" }}</label>
          <div class="form-control-plaintext">{{ header.paymentterm || "-" }}</div>
        </div>
        <div class="col-md-6">
          <label class="form-label">{{ t.collected_amount ?? "Collected Amount" }}</label>
          <div class="form-control-plaintext">{{ amount(header.amountpaid) }}</div>
        </div>
        <div class="col-md-6">
          <label class="form-label">{{ t.excess_amount ?? "Excess Amount" }}</label>
          <div class="form-control-plaintext">{{ amount(header.excesspayment) }}</div>
        </div>
      </div>
    </BaseBlock>

    <BaseBlock :title="t.detail ?? 'Detail'">
      <div class="table-responsive">
        <table class="table table-hover table-vcenter fs-sm">
          <thead>
            <tr>
              <th>{{ t.invoice_no ?? "Invoice No." }}</th>
              <th>{{ t.invoice_date ?? "Invoice Date" }}</th>
              <th class="text-end">{{ t.invoice_amount ?? "Invoice Amount" }}</th>
              <th class="text-end">{{ t.amount_paid ?? "Amount Paid" }}</th>
            </tr>
          </thead>
          <tbody>
            <tr v-if="!details.length">
              <td colspan="4" class="text-center text-muted py-4">{{ t.no_records ?? "No records found." }}</td>
            </tr>
            <tr v-for="(detail, index) in details" :key="`${detail.invoicenumber}-${index}`">
              <td>{{ detail.invoicenumber || "-" }}</td>
              <td>{{ detail.invoicedate || "-" }}</td>
              <td class="text-end">{{ amount(detail.totalinvoiceamount) }}</td>
              <td class="text-end">{{ amount(detail.amountpaid) }}</td>
            </tr>
          </tbody>
        </table>
      </div>
    </BaseBlock>

    <BaseBlock :title="t.payment_mode ?? 'Payment Mode'">
      <div class="table-responsive">
        <table class="table table-hover table-vcenter fs-sm">
          <thead>
            <tr>
              <th>{{ t.payment_mode ?? "Payment Mode" }}</th>
              <th class="text-end">{{ t.amount ?? "Amount" }}</th>
              <th>{{ t.cheque_no ?? "Cheque No." }}</th>
              <th>{{ t.cheque_date ?? "Cheque Date" }}</th>
              <th>{{ t.bank_name ?? "Bank Name" }}</th>
            </tr>
          </thead>
          <tbody>
            <tr v-if="!payments.length">
              <td colspan="5" class="text-center text-muted py-4">{{ t.no_records ?? "No records found." }}</td>
            </tr>
            <tr v-for="(payment, index) in payments" :key="index">
              <td>{{ payment.mode || "-" }}</td>
              <td class="text-end">{{ amount(payment.amount) }}</td>
              <td>{{ payment.checknumber || "-" }}</td>
              <td>{{ payment.checkdate || "-" }}</td>
              <td>{{ payment.bankname || "-" }}</td>
            </tr>
          </tbody>
        </table>
      </div>
    </BaseBlock>
  </div>
</template>
