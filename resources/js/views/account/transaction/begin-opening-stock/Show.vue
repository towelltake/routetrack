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
  cases: carry.cases + Number(line.cases || 0),
  pieces: carry.pieces + Number(line.pieces || 0),
  amount: carry.amount + Number(line.total_amount || 0),
}), { cases: 0, pieces: 0, amount: 0 }));

const backUrl = computed(() => {
  const params = new URLSearchParams();

  if (props.filters?.date) params.set("date", props.filters.date);
  if (Number(props.filters?.routecode || 0) > 0) params.set("routecode", String(props.filters.routecode));
  if (props.filters?.search) params.set("search", props.filters.search);
  if (props.filters?.page) params.set("page", String(props.filters.page));
  if (props.filters?.per_page) params.set("per_page", String(props.filters.per_page));
  if (props.filters?.sort_by) params.set("sort_by", props.filters.sort_by);
  if (props.filters?.sort_dir) params.set("sort_dir", props.filters.sort_dir);

  return `/transaction/begin-opening-stock?${params.toString()}`;
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

function amount(value) {
  return formatAmount(value);
}
</script>

<template>
  <Head :title="t.begin_opening_stock" />

  <BasePageHeading
    :title="t.begin_opening_stock"
    :subtitle="t.begin_opening_stock_note"
  >
    <template #extra>
      <Link class="btn btn-alt-secondary" :href="backUrl">
        <i class="fa fa-arrow-left me-1"></i> {{ t.back }}
      </Link>
    </template>
  </BasePageHeading>

  <div class="content">
    <BaseBlock :title="t.details">
      <div class="row g-3 fs-sm mb-4">
        <div class="col-md-4">
          <label class="form-label">{{ t.transaction_date }}</label>
          <div class="form-control-plaintext">{{ header.transactiondate || "-" }}</div>
        </div>
        <div class="col-md-4">
          <label class="form-label">{{ t.transaction_time }}</label>
          <div class="form-control-plaintext">{{ header.transactiontime || "-" }}</div>
        </div>
        <div class="col-md-4">
          <label class="form-label">{{ t.route_start_date }}</label>
          <div class="form-control-plaintext">{{ header.routestartdate || "-" }}</div>
        </div>
        <div class="col-md-4">
          <label class="form-label">{{ t.document_no }}</label>
          <div class="form-control-plaintext">{{ header.documentnumber || "-" }}</div>
        </div>
        <div class="col-md-4">
          <label class="form-label">{{ t.document_valid }}</label>
          <div class="form-control-plaintext">{{ header.documentvalid || "-" }}</div>
        </div>
        <div class="col-md-4">
          <label class="form-label">{{ t.acc_period }}</label>
          <div class="form-control-plaintext">{{ header.accperiod || "-" }}</div>
        </div>
        <div class="col-md-6">
          <label class="form-label">{{ t.route }}</label>
          <div class="form-control-plaintext">{{ header.routecode }} - {{ routeLabel() || "-" }}</div>
        </div>
        <div class="col-md-6">
          <label class="form-label">{{ t.salesman }}</label>
          <div class="form-control-plaintext">{{ salesmanLabel() || "-" }}</div>
        </div>
      </div>

      <div class="table-responsive">
        <table class="table table-hover table-vcenter fs-sm">
          <thead>
            <tr>
              <th>{{ t.item_code }}</th>
              <th>{{ t.item_name }}</th>
              <th class="text-center">{{ t.upc }}</th>
              <th class="text-end">{{ t.case_price }}</th>
              <th class="text-end">{{ t.pcs_price }}</th>
              <th class="text-end">{{ t.case }}</th>
              <th class="text-end">{{ t.pcs }}</th>
              <th class="text-end">{{ t.total_amount }}</th>
            </tr>
          </thead>
          <tbody>
            <tr v-if="!lines.length">
              <td colspan="8" class="text-center text-muted py-4">{{ t.no_records_found }}</td>
            </tr>
            <tr v-for="line in lines" :key="`${line.itemcode}-${line.display_code}`">
              <td>{{ line.display_code }}</td>
              <td>{{ line.description || "-" }}</td>
              <td class="text-center">{{ line.upc }}</td>
              <td class="text-end">{{ amount(line.itemcaseprice) }}</td>
              <td class="text-end">{{ amount(line.itemprice) }}</td>
              <td class="text-end">{{ line.cases }}</td>
              <td class="text-end">{{ line.pieces }}</td>
              <td class="text-end">{{ amount(line.total_amount) }}</td>
            </tr>
          </tbody>
          <tfoot v-if="lines.length">
            <tr class="fw-semibold">
              <td colspan="5" class="text-end">{{ t.total }}</td>
              <td class="text-end">{{ totals.cases }}</td>
              <td class="text-end">{{ totals.pieces }}</td>
              <td class="text-end">{{ amount(totals.amount) }}</td>
            </tr>
          </tfoot>
        </table>
      </div>
    </BaseBlock>
  </div>
</template>
