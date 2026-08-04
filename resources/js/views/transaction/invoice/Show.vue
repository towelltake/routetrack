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

const backUrl = computed(() => {
  const params = new URLSearchParams();

  if (props.filters?.date) params.set("date", props.filters.date);
  if (Number(props.filters?.routecode || 0) > 0) params.set("routecode", String(props.filters.routecode));
  if (props.filters?.search) params.set("search", props.filters.search);
  if (props.filters?.page) params.set("page", String(props.filters.page));
  if (props.filters?.per_page) params.set("per_page", String(props.filters.per_page));
  if (props.filters?.sort_by) params.set("sort_by", props.filters.sort_by);
  if (props.filters?.sort_dir) params.set("sort_dir", props.filters.sort_dir);

  return `/transaction/invoice?${params.toString()}`;
});

const totals = computed(() => props.lines.reduce((carry, line) => ({
  salescases: carry.salescases + Number(line.salescases || 0),
  salespcs: carry.salespcs + Number(line.salespcs || 0),
  returncases: carry.returncases + Number(line.returncases || 0),
  returnpcs: carry.returnpcs + Number(line.returnpcs || 0),
  damagedcases: carry.damagedcases + Number(line.damagedcases || 0),
  damagedpcs: carry.damagedpcs + Number(line.damagedpcs || 0),
  freegoodcases: carry.freegoodcases + Number(line.freegoodcases || 0),
  freegoodpcs: carry.freegoodpcs + Number(line.freegoodpcs || 0),
  promotioncases: carry.promotioncases + Number(line.promotioncases || 0),
  promotionpcs: carry.promotionpcs + Number(line.promotionpcs || 0),
  sales_amount: carry.sales_amount + Number(line.sales_amount || 0),
  promoamount: carry.promoamount + Number(line.promoamount || 0),
  taxsales: carry.taxsales + Number(line.taxsales || 0),
  taxreturn: carry.taxreturn + Number(line.taxreturn || 0),
  total_amount: carry.total_amount + Number(line.total_amount || 0),
}), {
  salescases: 0,
  salespcs: 0,
  returncases: 0,
  returnpcs: 0,
  damagedcases: 0,
  damagedpcs: 0,
  freegoodcases: 0,
  freegoodpcs: 0,
  promotioncases: 0,
  promotionpcs: 0,
  sales_amount: 0,
  promoamount: 0,
  taxsales: 0,
  taxreturn: 0,
  total_amount: 0,
}));

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

function itemDescription(line) {
  return locale === "ar"
    ? (line.arbdescription || line.description || "")
    : (line.description || line.arbdescription || "");
}

function amount(value) {
  return formatAmount(value);
}

const printUrl = computed(() => `/transaction/invoice/${props.header.transactionkey}/print`);
</script>

<template>
  <Head :title="t.invoice ?? 'Invoice'" />

  <BasePageHeading
    :title="t.invoice ?? 'Invoice'"
    :subtitle="t.invoice_note ?? 'Review route-wise invoice records by transaction date.'"
  >
    <template #extra>
      <div class="d-flex gap-2">
        <a class="btn btn-alt-info" :href="printUrl" target="_blank" rel="noopener">
          <i class="fa fa-print me-1"></i> {{ t.print ?? "Print" }}
        </a>
        <Link class="btn btn-alt-secondary" :href="backUrl">
          <i class="fa fa-arrow-left me-1"></i> {{ t.back ?? "Back" }}
        </Link>
      </div>
    </template>
  </BasePageHeading>

  <div class="content">
    <BaseBlock :title="t.details ?? 'Details'">
      <div class="row g-3 fs-sm mb-4">
        <div class="col-md-3">
          <label class="form-label">{{ t.transaction_date ?? "Transaction Date" }}</label>
          <div class="form-control-plaintext">{{ header.transactiondate || "-" }}</div>
        </div>
        <div class="col-md-3">
          <label class="form-label">{{ t.transaction_time ?? "Transaction Time" }}</label>
          <div class="form-control-plaintext">{{ header.transactiontime || "-" }}</div>
        </div>
        <div class="col-md-3">
          <label class="form-label">{{ t.document_no ?? "Document No." }}</label>
          <div class="form-control-plaintext">{{ header.documentnumber || "-" }}</div>
        </div>
        <div class="col-md-3">
          <label class="form-label">{{ t.invoice_no ?? "Invoice No." }}</label>
          <div class="form-control-plaintext">{{ header.invoicenumber || "-" }}</div>
        </div>
        <div class="col-md-3">
          <label class="form-label">{{ t.dsd_no ?? "DSD No." }}</label>
          <div class="form-control-plaintext">{{ header.dsdnumber || "-" }}</div>
        </div>
        <div class="col-md-3">
          <label class="form-label">{{ t.po_no ?? "PO No." }}</label>
          <div class="form-control-plaintext">{{ header.ponumber || "-" }}</div>
        </div>
        <div class="col-md-3">
          <label class="form-label">{{ t.document_valid ?? "Document Valid" }}</label>
          <div class="form-control-plaintext">{{ header.documentvalid || "-" }}</div>
        </div>
        <div class="col-md-3">
          <label class="form-label">{{ t.status ?? "Status" }}</label>
          <div class="form-control-plaintext">{{ header.status || "-" }}</div>
        </div>
        <div class="col-md-4">
          <label class="form-label">{{ t.route ?? "Route" }}</label>
          <div class="form-control-plaintext">{{ header.routecode || "-" }} - {{ routeLabel() || "-" }}</div>
        </div>
        <div class="col-md-4">
          <label class="form-label">{{ t.salesman ?? "Salesman" }}</label>
          <div class="form-control-plaintext">{{ header.salesmancode || "-" }} - {{ salesmanLabel() || "-" }}</div>
        </div>
        <div class="col-md-4">
          <label class="form-label">{{ t.customer ?? "Customer" }}</label>
          <div class="form-control-plaintext">
            {{ header.customercode || "-" }}<span v-if="header.alternatecode"> / {{ header.alternatecode }}</span> - {{ customerLabel() || "-" }}
          </div>
        </div>
        <div class="col-md-4">
          <label class="form-label">{{ t.payment_type ?? "Payment Type" }}</label>
          <div class="form-control-plaintext">{{ header.paymenttype || "-" }}</div>
        </div>
        <div class="col-md-4">
          <label class="form-label">{{ t.comments ?? "Comments" }}</label>
          <div class="form-control-plaintext">{{ header.comments || "-" }}</div>
        </div>
        <div class="col-md-4">
          <label class="form-label">{{ t.discount_amount ?? "Discount Amount" }}</label>
          <div class="form-control-plaintext">{{ amount(header.totaldiscountamount) }}</div>
        </div>
        <div class="col-md-3">
          <label class="form-label">{{ t.total_amount ?? "Total Amount" }}</label>
          <div class="form-control-plaintext">{{ amount(header.totalinvoiceamount) }}</div>
        </div>
        <div class="col-md-3">
          <label class="form-label">{{ t.sales_amount ?? "Sales Amount" }}</label>
          <div class="form-control-plaintext">{{ amount(header.totalsalesamount) }}</div>
        </div>
        <div class="col-md-3">
          <label class="form-label">{{ t.promo_amount ?? "Promo Amount" }}</label>
          <div class="form-control-plaintext">{{ amount(header.totalpromoamount) }}</div>
        </div>
        <div class="col-md-3">
          <label class="form-label">{{ t.vat ?? "VAT" }}</label>
          <div class="form-control-plaintext">{{ amount(header.totalvat) }}</div>
        </div>
      </div>

      <div class="table-responsive">
        <table class="table table-hover table-vcenter fs-sm">
          <thead>
            <tr>
              <th rowspan="2">{{ t.alternate_code ?? "Alternate Code" }}</th>
              <th rowspan="2">{{ t.item_description ?? "Item Description" }}</th>
              <th rowspan="2" class="text-center">{{ t.upc ?? "UPC" }}</th>
              <th colspan="4" class="text-center">Sales</th>
              <th colspan="4" class="text-center">Return</th>
              <th colspan="2" class="text-center">Damage</th>
              <th colspan="2" class="text-center">Free</th>
              <th colspan="3" class="text-center">Promotion</th>
              <th colspan="2" class="text-center">Tax</th>
              <th rowspan="2" class="text-end">{{ t.total_amount ?? "Total Amount" }}</th>
            </tr>
            <tr>
              <th class="text-end">{{ t.case ?? "CAS" }}</th>
              <th class="text-end">{{ t.pcs ?? "PCS" }}</th>
              <th class="text-end">{{ t.case_price ?? "Case Price" }}</th>
              <th class="text-end">{{ t.unit_price ?? "Unit Price" }}</th>
              <th class="text-end">{{ t.case ?? "CAS" }}</th>
              <th class="text-end">{{ t.pcs ?? "PCS" }}</th>
              <th class="text-end">{{ t.case_price ?? "Case Price" }}</th>
              <th class="text-end">{{ t.unit_price ?? "Unit Price" }}</th>
              <th class="text-end">{{ t.case ?? "CAS" }}</th>
              <th class="text-end">{{ t.pcs ?? "PCS" }}</th>
              <th class="text-end">{{ t.case ?? "CAS" }}</th>
              <th class="text-end">{{ t.pcs ?? "PCS" }}</th>
              <th class="text-end">{{ t.case ?? "CAS" }}</th>
              <th class="text-end">{{ t.pcs ?? "PCS" }}</th>
              <th class="text-end">{{ t.discount ?? "Discount" }}</th>
              <th class="text-end">{{ t.sales ?? "Sales" }}</th>
              <th class="text-end">{{ t.return_label ?? "Return" }}</th>
            </tr>
          </thead>
          <tbody>
            <tr v-if="!lines.length">
              <td colspan="21" class="text-center text-muted py-4">{{ t.no_records ?? "No records found." }}</td>
            </tr>
            <tr v-for="line in lines" :key="`${line.primary_key}-${line.itemcode}`">
              <td>{{ line.display_code }}</td>
              <td>{{ itemDescription(line) || "-" }}</td>
              <td class="text-center">{{ line.upc }}</td>
              <td class="text-end">{{ line.salescases }}</td>
              <td class="text-end">{{ line.salespcs }}</td>
              <td class="text-end">{{ amount(line.salescaseprice) }}</td>
              <td class="text-end">{{ amount(line.salesprice) }}</td>
              <td class="text-end">{{ line.returncases }}</td>
              <td class="text-end">{{ line.returnpcs }}</td>
              <td class="text-end">{{ amount(line.returncaseprice) }}</td>
              <td class="text-end">{{ amount(line.returnprice) }}</td>
              <td class="text-end">{{ line.damagedcases }}</td>
              <td class="text-end">{{ line.damagedpcs }}</td>
              <td class="text-end">{{ line.freegoodcases }}</td>
              <td class="text-end">{{ line.freegoodpcs }}</td>
              <td class="text-end">{{ line.promotioncases }}</td>
              <td class="text-end">{{ line.promotionpcs }}</td>
              <td class="text-end">{{ amount(line.promoamount) }}</td>
              <td class="text-end">{{ amount(line.taxsales) }}</td>
              <td class="text-end">{{ amount(line.taxreturn) }}</td>
              <td class="text-end">{{ amount(line.total_amount) }}</td>
            </tr>
          </tbody>
          <tfoot v-if="lines.length">
            <tr class="fw-semibold">
              <td colspan="3" class="text-end">{{ t.total ?? "Total" }}</td>
              <td class="text-end">{{ totals.salescases }}</td>
              <td class="text-end">{{ totals.salespcs }}</td>
              <td></td>
              <td></td>
              <td class="text-end">{{ totals.returncases }}</td>
              <td class="text-end">{{ totals.returnpcs }}</td>
              <td></td>
              <td></td>
              <td class="text-end">{{ totals.damagedcases }}</td>
              <td class="text-end">{{ totals.damagedpcs }}</td>
              <td class="text-end">{{ totals.freegoodcases }}</td>
              <td class="text-end">{{ totals.freegoodpcs }}</td>
              <td class="text-end">{{ totals.promotioncases }}</td>
              <td class="text-end">{{ totals.promotionpcs }}</td>
              <td class="text-end">{{ amount(totals.promoamount) }}</td>
              <td class="text-end">{{ amount(totals.taxsales) }}</td>
              <td class="text-end">{{ amount(totals.taxreturn) }}</td>
              <td class="text-end">{{ amount(totals.total_amount) }}</td>
            </tr>
          </tfoot>
        </table>
      </div>
    </BaseBlock>
  </div>
</template>
