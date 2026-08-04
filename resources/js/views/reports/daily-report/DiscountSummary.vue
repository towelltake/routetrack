<script setup>
import { computed, ref, watch } from "vue";
import { Head, router, usePage } from "@inertiajs/vue3";
import VueSelect from "vue-select";
import { useAmountFormatter } from "@/composables/useAmountFormatter";

const props = defineProps({
  filters: { type: Object, required: true },
  sort: { type: Object, required: true },
  filterScopeRows: { type: Array, required: true },
  companyOptions: { type: Array, required: true },
  regionOptions: { type: Array, required: true },
  depotOptions: { type: Array, required: true },
  areaOptions: { type: Array, required: true },
  subAreaOptions: { type: Array, required: true },
  routeOptions: { type: Array, required: true },
  rows: { type: Array, required: true },
  totals: { type: Object, required: true },
  pagination: { type: Object, required: true },
});

const t = usePage().props.translations.ui;
const locale = usePage().props.locale ?? "en";

const month = ref(Number(props.filters.month ?? new Date().getMonth() + 1));
const year = ref(Number(props.filters.year ?? new Date().getFullYear()));
const companyCode = ref(props.filters.cmpycode ? String(props.filters.cmpycode) : "");
const regionCode = ref(props.filters.regionmstcode ? String(props.filters.regionmstcode) : "");
const depotCode = ref(props.filters.depotcode ? String(props.filters.depotcode) : "");
const areaCode = ref(props.filters.areacode ? String(props.filters.areacode) : "");
const subAreaCode = ref(props.filters.subareacode ? String(props.filters.subareacode) : "");
const routeCode = ref(props.filters.routecode ? String(props.filters.routecode) : "");
const perPage = ref(props.pagination.per_page ?? props.filters.per_page ?? 25);
const sortBy = ref(props.sort.by ?? "trandate_sort");
const sortDir = ref(props.sort.dir ?? "asc");
const { amountDecimalPlaces } = useAmountFormatter();

const columnCount = 12;
const scopedRows = computed(() => props.filterScopeRows ?? []);
const monthFormatter = new Intl.DateTimeFormat(locale, { month: "long" });
const monthOptions = Array.from({ length: 12 }, (_, index) => ({
  id: index + 1,
  label: monthFormatter.format(new Date(2000, index, 1)),
}));

const paginationLabel = computed(() => {
  if (!props.pagination.total) return t.no_records_found;
  return `${t.showing} ${props.pagination.from ?? 0} ${t.to} ${props.pagination.to ?? 0} ${t.of} ${props.pagination.total}`;
});

function optionListFor(targetKey, labelKey) {
  const rows = scopedRows.value.filter((row) => {
    if (targetKey !== "cmpycode" && companyCode.value && String(row.cmpycode) !== companyCode.value) return false;
    if (targetKey !== "regionmstcode" && regionCode.value && String(row.regionmstcode) !== regionCode.value) return false;
    if (targetKey !== "depotcode" && depotCode.value && String(row.depotcode) !== depotCode.value) return false;
    if (targetKey !== "areacode" && areaCode.value && String(row.areacode) !== areaCode.value) return false;
    if (targetKey !== "subareacode" && subAreaCode.value && String(row.subareacode) !== subAreaCode.value) return false;
    if (targetKey !== "routecode" && routeCode.value && String(row.routecode) !== routeCode.value) return false;
    return true;
  });

  return rows
    .filter((row) => Number(row[targetKey] || 0) > 0 && row[labelKey])
    .reduce((carry, row) => {
      const id = Number(row[targetKey]);
      if (!carry.some((option) => option.id === id)) {
        carry.push({ id, label: row[labelKey] });
      }
      return carry;
    }, [])
    .sort((left, right) => left.id - right.id);
}

const filteredCompanyOptions = computed(() => optionListFor("cmpycode", "company_label"));
const filteredRegionOptions = computed(() => optionListFor("regionmstcode", "region_label"));
const filteredDepotOptions = computed(() => optionListFor("depotcode", "depot_label"));
const filteredAreaOptions = computed(() => optionListFor("areacode", "area_label"));
const filteredSubAreaOptions = computed(() => optionListFor("subareacode", "subarea_label"));
const filteredRouteOptions = computed(() => optionListFor("routecode", "route_label"));

function findOption(options, value) {
  if (!value) return null;
  return options.find((option) => String(option.id) === String(value)) ?? null;
}

function ensureValidSelection(model, options) {
  if (!model.value) return;
  if (!options.some((option) => String(option.id) === String(model.value))) {
    model.value = "";
  }
}

const companyValue = computed({
  get: () => findOption(filteredCompanyOptions.value, companyCode.value),
  set: (option) => { companyCode.value = option ? String(option.id) : ""; },
});
const regionValue = computed({
  get: () => findOption(filteredRegionOptions.value, regionCode.value),
  set: (option) => { regionCode.value = option ? String(option.id) : ""; },
});
const depotValue = computed({
  get: () => findOption(filteredDepotOptions.value, depotCode.value),
  set: (option) => { depotCode.value = option ? String(option.id) : ""; },
});
const areaValue = computed({
  get: () => findOption(filteredAreaOptions.value, areaCode.value),
  set: (option) => { areaCode.value = option ? String(option.id) : ""; },
});
const subAreaValue = computed({
  get: () => findOption(filteredSubAreaOptions.value, subAreaCode.value),
  set: (option) => { subAreaCode.value = option ? String(option.id) : ""; },
});
const routeValue = computed({
  get: () => findOption(filteredRouteOptions.value, routeCode.value),
  set: (option) => { routeCode.value = option ? String(option.id) : ""; },
});

watch(filteredCompanyOptions, (options) => ensureValidSelection(companyCode, options));
watch(filteredRegionOptions, (options) => ensureValidSelection(regionCode, options));
watch(filteredDepotOptions, (options) => ensureValidSelection(depotCode, options));
watch(filteredAreaOptions, (options) => ensureValidSelection(areaCode, options));
watch(filteredSubAreaOptions, (options) => ensureValidSelection(subAreaCode, options));
watch(filteredRouteOptions, (options) => ensureValidSelection(routeCode, options));

function currentParams(page = 1) {
  return {
    month: month.value,
    year: year.value,
    cmpycode: companyCode.value || undefined,
    regionmstcode: regionCode.value || undefined,
    depotcode: depotCode.value || undefined,
    areacode: areaCode.value || undefined,
    subareacode: subAreaCode.value || undefined,
    routecode: routeCode.value || undefined,
    per_page: perPage.value,
    sort_by: sortBy.value,
    sort_dir: sortDir.value,
    page,
  };
}

function reload(page = 1) {
  router.get("/reports/daily-report/discount-summary", currentParams(page), {
    preserveScroll: true,
    preserveState: true,
    replace: true,
    only: ["filters", "sort", "rows", "totals", "pagination", "filterScopeRows", "companyOptions", "regionOptions", "depotOptions", "areaOptions", "subAreaOptions", "routeOptions"],
  });
}

function sort(column) {
  if (sortBy.value === column) {
    sortDir.value = sortDir.value === "asc" ? "desc" : "asc";
  } else {
    sortBy.value = column;
    sortDir.value = column === "trandate_sort" ? "asc" : "desc";
  }
  reload();
}

function sortIcon(column) {
  if (sortBy.value !== column) return "fa-sort text-muted";
  return sortDir.value === "asc" ? "fa-sort-up" : "fa-sort-down";
}

function resetFilters() {
  const today = new Date();
  month.value = today.getMonth() + 1;
  year.value = today.getFullYear();
  companyCode.value = "";
  regionCode.value = "";
  depotCode.value = "";
  areaCode.value = "";
  subAreaCode.value = "";
  routeCode.value = "";
  perPage.value = 25;
  sortBy.value = "trandate_sort";
  sortDir.value = "asc";
  reload();
}

function exportUrl(type) {
  const params = new URLSearchParams();
  Object.entries(currentParams()).forEach(([key, value]) => {
    if (value !== undefined && value !== null && value !== "") {
      params.set(key, String(value));
    }
  });
  return `/reports/daily-report/discount-summary/export/${type}?${params.toString()}`;
}

function exportExcel() {
  window.location.href = exportUrl("excel");
}

function openPdfExport() {
  window.open(exportUrl("pdf"), "_blank", "noopener");
}

function formatAmount(value, digits = amountDecimalPlaces.value) {
  return Number(value || 0).toLocaleString(undefined, {
    minimumFractionDigits: digits,
    maximumFractionDigits: digits,
  });
}
</script>

<template>
  <Head :title="t.discount_summary" />

  <BasePageHeading
    :title="t.discount_summary"
    :subtitle="t.discount_summary_report_note"
  />

  <div class="content">
    <BaseBlock :title="t.global_report_filters">
      <div class="row g-3 align-items-end mb-3">
        <div class="col-md-3"><label class="form-label">{{ t.company }}</label><VueSelect v-model="companyValue" class="report-filter-select" :options="filteredCompanyOptions" label="label" :placeholder="t.all_companies" :clearable="true" /></div>
        <div class="col-md-3"><label class="form-label">{{ t.region }}</label><VueSelect v-model="regionValue" class="report-filter-select" :options="filteredRegionOptions" label="label" :placeholder="t.all_regions" :clearable="true" /></div>
        <div class="col-md-3"><label class="form-label">{{ t.branch_depot }}</label><VueSelect v-model="depotValue" class="report-filter-select" :options="filteredDepotOptions" label="label" :placeholder="t.all_branches_depots" :clearable="true" /></div>
        <div class="col-md-3"><label class="form-label">{{ t.area }}</label><VueSelect v-model="areaValue" class="report-filter-select" :options="filteredAreaOptions" label="label" :placeholder="t.all_areas" :clearable="true" /></div>
        <div class="col-md-3"><label class="form-label">{{ t.sub_area }}</label><VueSelect v-model="subAreaValue" class="report-filter-select" :options="filteredSubAreaOptions" label="label" :placeholder="t.all_sub_areas" :clearable="true" /></div>
        <div class="col-md-3"><label class="form-label">{{ t.route }}</label><VueSelect v-model="routeValue" class="report-filter-select" :options="filteredRouteOptions" label="label" :placeholder="t.all_routes" :clearable="true" /></div>
        <div class="col-md-2">
          <label class="form-label">{{ t.month }}</label>
          <select v-model="month" class="form-select">
            <option v-for="option in monthOptions" :key="option.id" :value="option.id">{{ option.label }}</option>
          </select>
        </div>
        <div class="col-md-2"><label class="form-label">{{ t.year }}</label><input v-model="year" type="number" min="2000" max="2100" class="form-control" /></div>
        <div class="col-md-2 d-grid gap-2">
          <button class="btn btn-primary" @click="reload()"><i class="fa fa-magnifying-glass me-1"></i> {{ t.load_report }}</button>
          <button class="btn btn-alt-secondary" @click="resetFilters"><i class="fa fa-rotate-left me-1"></i> {{ t.reset }}</button>
        </div>
      </div>
    </BaseBlock>

    <BaseBlock :title="t.discount_summary">
      <template #options>
        <div class="d-flex align-items-center gap-2">
          <button type="button" class="btn btn-sm btn-alt-success" @click="exportExcel"><i class="fa fa-file-excel me-1"></i> {{ t.excel }}</button>
          <button type="button" class="btn btn-sm btn-alt-danger" @click="openPdfExport"><i class="fa fa-file-pdf me-1"></i> {{ t.pdf }}</button>
          <div class="text-muted fs-sm">{{ paginationLabel }}</div>
        </div>
      </template>

      <div class="table-responsive">
        <table class="table table-bordered table-striped table-vcenter fs-sm">
          <thead>
            <tr>
              <th class="cursor-pointer text-nowrap" @click="sort('trandate_sort')">{{ t.transaction_date }} <i class="fa ms-1" :class="sortIcon('trandate_sort')"></i></th>
              <th class="cursor-pointer text-nowrap" @click="sort('transactiontime_sort')">{{ t.transaction_time }} <i class="fa ms-1" :class="sortIcon('transactiontime_sort')"></i></th>
              <th class="cursor-pointer text-nowrap" @click="sort('invoicenumber_sort')">{{ t.invoice_number }} <i class="fa ms-1" :class="sortIcon('invoicenumber_sort')"></i></th>
              <th class="cursor-pointer text-nowrap" @click="sort('reportcustcode')">{{ t.customer_code }} <i class="fa ms-1" :class="sortIcon('reportcustcode')"></i></th>
              <th class="cursor-pointer text-nowrap" @click="sort('customername_sort')">{{ t.customer_name }} <i class="fa ms-1" :class="sortIcon('customername_sort')"></i></th>
              <th class="cursor-pointer text-nowrap text-end" @click="sort('salesamount')">{{ t.sales_amount }} <i class="fa ms-1" :class="sortIcon('salesamount')"></i></th>
              <th class="cursor-pointer text-nowrap text-end" @click="sort('goodreturnamount')">{{ t.good_return_amount }} <i class="fa ms-1" :class="sortIcon('goodreturnamount')"></i></th>
              <th class="cursor-pointer text-nowrap text-end" @click="sort('totaldamagedamount')">{{ t.bad_return_amount }} <i class="fa ms-1" :class="sortIcon('totaldamagedamount')"></i></th>
              <th class="cursor-pointer text-nowrap text-end" @click="sort('freeamount')">{{ t.free_amount }} <i class="fa ms-1" :class="sortIcon('freeamount')"></i></th>
              <th class="cursor-pointer text-nowrap text-end" @click="sort('invoiceamount')">{{ t.invoice_amount }} <i class="fa ms-1" :class="sortIcon('invoiceamount')"></i></th>
              <th class="cursor-pointer text-nowrap text-end" @click="sort('discountamount')">{{ t.discount_amount }} <i class="fa ms-1" :class="sortIcon('discountamount')"></i></th>
              <th class="cursor-pointer text-nowrap text-end" @click="sort('netamount')">{{ t.net_amount }} <i class="fa ms-1" :class="sortIcon('netamount')"></i></th>
            </tr>
          </thead>
          <tbody>
            <tr v-if="!rows.length"><td :colspan="columnCount" class="text-center text-muted py-4">{{ t.no_records_found }}</td></tr>
            <tr v-for="(row, index) in rows" :key="`${row.routekey}-${row.visitkey}-${row.invoicenumber}-${index}`">
              <td>{{ row.transactiondate || "-" }}</td>
              <td>{{ row.transactiontime || "-" }}</td>
              <td>{{ row.invoicenumber || "-" }}</td>
              <td>{{ row.reportcustcode || "-" }}</td>
              <td>{{ row.customer_label || "-" }}</td>
              <td class="text-end">{{ formatAmount(row.salesamount) }}</td>
              <td class="text-end">{{ formatAmount(row.goodreturnamount) }}</td>
              <td class="text-end">{{ formatAmount(row.totaldamagedamount) }}</td>
              <td class="text-end">{{ formatAmount(row.freeamount) }}</td>
              <td class="text-end">{{ formatAmount(row.invoiceamount) }}</td>
              <td class="text-end">{{ formatAmount(row.discountamount) }}</td>
              <td class="text-end">{{ formatAmount(row.netamount) }}</td>
            </tr>
          </tbody>
          <tfoot v-if="rows.length">
            <tr class="table-light fw-semibold">
              <td colspan="5" class="text-end">{{ t.total }}</td>
              <td class="text-end">{{ formatAmount(totals.salesamount) }}</td>
              <td class="text-end">{{ formatAmount(totals.goodreturnamount) }}</td>
              <td class="text-end">{{ formatAmount(totals.totaldamagedamount) }}</td>
              <td class="text-end">{{ formatAmount(totals.freeamount) }}</td>
              <td class="text-end">{{ formatAmount(totals.invoiceamount) }}</td>
              <td class="text-end">{{ formatAmount(totals.discountamount) }}</td>
              <td class="text-end">{{ formatAmount(totals.netamount) }}</td>
            </tr>
          </tfoot>
        </table>
      </div>

      <div class="d-flex justify-content-between align-items-center pt-3">
        <div class="text-muted fs-sm">{{ paginationLabel }}</div>
        <div class="btn-group">
          <button class="btn btn-sm btn-alt-secondary" :disabled="pagination.current_page <= 1" @click="reload(pagination.current_page - 1)">{{ t.previous }}</button>
          <button class="btn btn-sm btn-alt-secondary" disabled>{{ pagination.current_page || 1 }} / {{ pagination.last_page || 1 }}</button>
          <button class="btn btn-sm btn-alt-secondary" :disabled="pagination.current_page >= pagination.last_page" @click="reload(pagination.current_page + 1)">{{ t.next }}</button>
        </div>
      </div>
    </BaseBlock>
  </div>
</template>

<style lang="scss">
@import "vue-select/dist/vue-select.css";
@import "@scss/vendor/vue-select";
</style>

<style lang="scss" scoped>
.report-filter-select {
  :deep(.vs__dropdown-toggle) { min-height: 38px; height: 38px; flex-wrap: nowrap; overflow: hidden; }
  :deep(.vs__selected-options) { flex-wrap: nowrap; overflow: hidden; }
  :deep(.vs__selected) { display: block; max-width: 100%; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; margin-right: 0; }
  :deep(.vs__search) { min-width: 0; }
}
</style>
