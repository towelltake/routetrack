<script setup>
import { computed } from "vue";
import { Head, Link, usePage } from "@inertiajs/vue3";
import { useAmountFormatter } from "@/composables/useAmountFormatter";

const props = defineProps({
  header: { type: Object, required: true },
  lines: { type: Array, required: true },
  filters: { type: Object, required: true },
});

const page = usePage();
const t = page.props.translations.ui;
const locale = page.props.locale;
const { formatAmount } = useAmountFormatter();

const totals = computed(() => props.lines.reduce((carry, line) => ({
  quantity: carry.quantity + Number(line.quantity || 0),
  amount: carry.amount + Number(line.damaged_amount || 0),
}), { quantity: 0, amount: 0 }));

const backUrl = computed(() => {
  const params = new URLSearchParams();

  if (props.filters?.date) params.set("date", props.filters.date);
  if (Number(props.filters?.routecode || 0) > 0) params.set("routecode", String(props.filters.routecode));
  if (props.filters?.search) params.set("search", props.filters.search);
  if (props.filters?.page) params.set("page", String(props.filters.page));
  if (props.filters?.per_page) params.set("per_page", String(props.filters.per_page));
  if (props.filters?.sort_by) params.set("sort_by", props.filters.sort_by);
  if (props.filters?.sort_dir) params.set("sort_dir", props.filters.sort_dir);

  return `/transaction/damage-return?${params.toString()}`;
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
  <Head :title="t.damage_return ?? 'Damage Return'" />

  <BasePageHeading
    :title="t.damage_return ?? 'Damage Return'"
    :subtitle="t.damage_return_note ?? 'Review damage return records by transaction date.'"
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
        <div class="col-md-4">
          <label class="form-label">{{ t.transaction_date ?? "Transaction Date" }}</label>
          <div class="form-control-plaintext">{{ header.transactiondate || "-" }}</div>
        </div>
        <div class="col-md-4">
          <label class="form-label">{{ t.transaction_time ?? "Transaction Time" }}</label>
          <div class="form-control-plaintext">{{ header.transactiontime || "-" }}</div>
        </div>
        <div class="col-md-4">
          <label class="form-label">{{ t.route_start_date ?? "Route Start Date" }}</label>
          <div class="form-control-plaintext">{{ header.routestartdate || "-" }}</div>
        </div>
        <div class="col-md-4">
          <label class="form-label">{{ t.document_no ?? "Document No." }}</label>
          <div class="form-control-plaintext">{{ header.documentnumber || "-" }}</div>
        </div>
        <div class="col-md-4">
          <label class="form-label">{{ t.invoice_no ?? "Invoice No." }}</label>
          <div class="form-control-plaintext">{{ header.invoicenumber || "-" }}</div>
        </div>
        <div class="col-md-4">
          <label class="form-label">{{ t.document_valid ?? "Document Valid" }}</label>
          <div class="form-control-plaintext">{{ header.documentvalid || "-" }}</div>
        </div>
        <div class="col-md-6">
          <label class="form-label">{{ t.route ?? "Route" }}</label>
          <div class="form-control-plaintext">{{ header.routecode }} - {{ routeLabel() || "-" }}</div>
        </div>
        <div class="col-md-6">
          <label class="form-label">{{ t.acc_period ?? "Acc Period" }}</label>
          <div class="form-control-plaintext">{{ header.accperiod || "-" }}</div>
        </div>
        <div class="col-md-6">
          <label class="form-label">{{ t.salesman ?? "Salesman" }}</label>
          <div class="form-control-plaintext">{{ salesmanLabel() || "-" }}</div>
        </div>
        <div class="col-md-6">
          <label class="form-label">{{ t.customer_name ?? "Customer Name" }}</label>
          <div class="form-control-plaintext">{{ customerLabel() || "-" }}</div>
        </div>
      </div>

      <div class="table-responsive">
        <table class="table table-hover table-vcenter fs-sm">
          <thead>
            <tr>
              <th>{{ t.invoice_no ?? "Invoice No." }}</th>
              <th>{{ t.item_code ?? "Item Code" }}</th>
              <th>{{ t.item_name ?? "Item Name" }}</th>
              <th class="text-center">{{ t.upc ?? "UPC" }}</th>
              <th class="text-end">{{ t.case_price ?? "Case Price" }}</th>
              <th class="text-end">{{ t.pcs_price ?? "Pcs Price" }}</th>
              <th class="text-end">{{ t.quantity ?? "Quantity" }}</th>
              <th class="text-end">{{ t.total_amount ?? "Total Amount" }}</th>
            </tr>
          </thead>
          <tbody>
            <tr v-if="!lines.length">
              <td colspan="8" class="text-center text-muted py-4">{{ t.no_records ?? "No records found." }}</td>
            </tr>
            <tr v-for="line in lines" :key="`${line.itemcode}-${line.display_code}`">
              <td>{{ line.invoicenumber || "-" }}</td>
              <td>{{ line.display_code }}</td>
              <td>{{ line.description || "-" }}</td>
              <td class="text-center">{{ line.upc }}</td>
              <td class="text-end">{{ amount(line.returncaseprice) }}</td>
              <td class="text-end">{{ amount(line.returnprice) }}</td>
              <td class="text-end">{{ line.quantity }}</td>
              <td class="text-end">{{ amount(line.damaged_amount) }}</td>
            </tr>
          </tbody>
          <tfoot v-if="lines.length">
            <tr class="fw-semibold">
              <td colspan="6" class="text-end">{{ t.total ?? "Total" }}</td>
              <td class="text-end">{{ totals.quantity }}</td>
              <td class="text-end">{{ amount(totals.amount) }}</td>
            </tr>
          </tfoot>
        </table>
      </div>
    </BaseBlock>
  </div>
</template>
