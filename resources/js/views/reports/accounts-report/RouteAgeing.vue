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

const companyCode = ref(props.filters.cmpycode ? String(props.filters.cmpycode) : "");
const regionCode = ref(props.filters.regionmstcode ? String(props.filters.regionmstcode) : "");
const depotCode = ref(props.filters.depotcode ? String(props.filters.depotcode) : "");
const areaCode = ref(props.filters.areacode ? String(props.filters.areacode) : "");
const subAreaCode = ref(props.filters.subareacode ? String(props.filters.subareacode) : "");
const routeCode = ref(props.filters.routecode ? String(props.filters.routecode) : "");
const perPage = ref(props.pagination.per_page ?? props.filters.per_page ?? 25);
const sortBy = ref(props.sort.by ?? "route_label");
const sortDir = ref(props.sort.dir ?? "asc");
const { amountDecimalPlaces } = useAmountFormatter();
const t = usePage().props.translations.ui;

const columnCount = 15;
const scopedRows = computed(() => props.filterScopeRows ?? []);
const paginationLabel = computed(() => {
  if (!props.rows.length) return t.no_records_found;
  if (props.pagination.total == null) return `${t.showing} ${props.pagination.from ?? 0} ${t.to} ${props.pagination.to ?? 0}`;
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
      if (!carry.some((option) => option.id === id)) carry.push({ id, label: row[labelKey] });
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

function findOption(options, value) { return !value ? null : options.find((option) => String(option.id) === String(value)) ?? null; }
function ensureValidSelection(model, options) { if (model.value && !options.some((option) => String(option.id) === String(model.value))) model.value = ""; }

const companyValue = computed({ get: () => findOption(filteredCompanyOptions.value, companyCode.value), set: (option) => { companyCode.value = option ? String(option.id) : ""; } });
const regionValue = computed({ get: () => findOption(filteredRegionOptions.value, regionCode.value), set: (option) => { regionCode.value = option ? String(option.id) : ""; } });
const depotValue = computed({ get: () => findOption(filteredDepotOptions.value, depotCode.value), set: (option) => { depotCode.value = option ? String(option.id) : ""; } });
const areaValue = computed({ get: () => findOption(filteredAreaOptions.value, areaCode.value), set: (option) => { areaCode.value = option ? String(option.id) : ""; } });
const subAreaValue = computed({ get: () => findOption(filteredSubAreaOptions.value, subAreaCode.value), set: (option) => { subAreaCode.value = option ? String(option.id) : ""; } });
const routeValue = computed({ get: () => findOption(filteredRouteOptions.value, routeCode.value), set: (option) => { routeCode.value = option ? String(option.id) : ""; } });

watch(filteredCompanyOptions, (options) => ensureValidSelection(companyCode, options));
watch(filteredRegionOptions, (options) => ensureValidSelection(regionCode, options));
watch(filteredDepotOptions, (options) => ensureValidSelection(depotCode, options));
watch(filteredAreaOptions, (options) => ensureValidSelection(areaCode, options));
watch(filteredSubAreaOptions, (options) => ensureValidSelection(subAreaCode, options));
watch(filteredRouteOptions, (options) => ensureValidSelection(routeCode, options));

function currentParams(page = 1) {
  return { cmpycode: companyCode.value || undefined, regionmstcode: regionCode.value || undefined, depotcode: depotCode.value || undefined, areacode: areaCode.value || undefined, subareacode: subAreaCode.value || undefined, routecode: routeCode.value || undefined, per_page: perPage.value, sort_by: sortBy.value, sort_dir: sortDir.value, page };
}

function reload(page = 1) {
  router.get("/reports/accounts-report/route-ageing", currentParams(page), { preserveScroll: true, preserveState: true, replace: true, only: ["filters", "sort", "rows", "totals", "pagination", "filterScopeRows", "companyOptions", "regionOptions", "depotOptions", "areaOptions", "subAreaOptions", "routeOptions"] });
}

function sort(column) {
  if (sortBy.value === column) sortDir.value = sortDir.value === "asc" ? "desc" : "asc";
  else {
    sortBy.value = column;
    sortDir.value = ["route_label", "salesman_label", "transactiondate_sort", "invoicenumber_sort", "customercode_sort", "customername_sort", "pdcdate_sort"].includes(column) ? "asc" : "desc";
  }
  reload();
}

function sortIcon(column) {
  if (sortBy.value !== column) return "fa-sort text-muted";
  return sortDir.value === "asc" ? "fa-sort-up" : "fa-sort-down";
}

function resetFilters() {
  companyCode.value = "";
  regionCode.value = "";
  depotCode.value = "";
  areaCode.value = "";
  subAreaCode.value = "";
  routeCode.value = "";
  perPage.value = 25;
  sortBy.value = "route_label";
  sortDir.value = "asc";
  reload();
}

function exportUrl(type) {
  const params = new URLSearchParams();
  Object.entries(currentParams()).forEach(([key, value]) => {
    if (value !== undefined && value !== null && value !== "") params.set(key, String(value));
  });
  return `/reports/accounts-report/route-ageing/export/${type}?${params.toString()}`;
}

function formatAmount(value, digits = amountDecimalPlaces.value) {
  return Number(value || 0).toLocaleString(undefined, { minimumFractionDigits: digits, maximumFractionDigits: digits });
}
</script>

<template>
  <Head :title="t.route_ageing" />
  <BasePageHeading :title="t.route_ageing" :subtitle="t.route_ageing_report_note" />
  <div class="content">
    <BaseBlock :title="t.global_report_filters ?? 'Global Report Filters'">
      <div class="row g-3 align-items-end mb-3">
        <div class="col-md-3"><label class="form-label">{{ t.company }}</label><VueSelect v-model="companyValue" class="report-filter-select" :options="filteredCompanyOptions" label="label" :placeholder="t.all_companies" :clearable="true" /></div>
        <div class="col-md-3"><label class="form-label">{{ t.region }}</label><VueSelect v-model="regionValue" class="report-filter-select" :options="filteredRegionOptions" label="label" :placeholder="t.all_regions" :clearable="true" /></div>
        <div class="col-md-3"><label class="form-label">{{ t.branch_depot }}</label><VueSelect v-model="depotValue" class="report-filter-select" :options="filteredDepotOptions" label="label" :placeholder="t.all_branches_depots" :clearable="true" /></div>
        <div class="col-md-3"><label class="form-label">{{ t.area }}</label><VueSelect v-model="areaValue" class="report-filter-select" :options="filteredAreaOptions" label="label" :placeholder="t.all_areas" :clearable="true" /></div>
        <div class="col-md-3"><label class="form-label">{{ t.sub_area }}</label><VueSelect v-model="subAreaValue" class="report-filter-select" :options="filteredSubAreaOptions" label="label" :placeholder="t.all_sub_areas" :clearable="true" /></div>
        <div class="col-md-3"><label class="form-label">{{ t.route }}</label><VueSelect v-model="routeValue" class="report-filter-select" :options="filteredRouteOptions" label="label" :placeholder="t.all_routes" :clearable="true" /></div>
        <div class="col-md-2 d-grid gap-2">
          <button class="btn btn-primary" @click="reload()"><i class="fa fa-magnifying-glass me-1"></i> {{ t.load_report }}</button>
          <button class="btn btn-alt-secondary" @click="resetFilters"><i class="fa fa-rotate-left me-1"></i> {{ t.reset }}</button>
        </div>
      </div>
    </BaseBlock>

    <BaseBlock :title="t.route_ageing">
      <template #options>
        <div class="d-flex align-items-center gap-2">
          <button type="button" class="btn btn-sm btn-alt-success" @click="window.location.href = exportUrl('excel')"><i class="fa fa-file-excel me-1"></i> {{ t.excel }}</button>
          <button type="button" class="btn btn-sm btn-alt-danger" @click="window.open(exportUrl('pdf'), '_blank', 'noopener')"><i class="fa fa-file-pdf me-1"></i> {{ t.pdf }}</button>
          <div class="text-muted fs-sm">{{ paginationLabel }}</div>
        </div>
      </template>

      <div class="table-responsive">
        <table class="table table-bordered table-striped table-vcenter fs-sm">
          <thead>
            <tr>
              <th class="cursor-pointer text-nowrap" @click="sort('route_label')">Route <i class="fa ms-1" :class="sortIcon('route_label')"></i></th>
              <th class="cursor-pointer text-nowrap" @click="sort('salesman_label')">Salesman <i class="fa ms-1" :class="sortIcon('salesman_label')"></i></th>
              <th class="cursor-pointer text-nowrap" @click="sort('transactiondate_sort')">Transaction Date <i class="fa ms-1" :class="sortIcon('transactiondate_sort')"></i></th>
              <th class="cursor-pointer text-nowrap" @click="sort('invoicenumber_sort')">Invoice No <i class="fa ms-1" :class="sortIcon('invoicenumber_sort')"></i></th>
              <th class="cursor-pointer text-nowrap" @click="sort('customercode_sort')">Customer Code <i class="fa ms-1" :class="sortIcon('customercode_sort')"></i></th>
              <th class="cursor-pointer text-nowrap" @click="sort('customername_sort')">Customer Name <i class="fa ms-1" :class="sortIcon('customername_sort')"></i></th>
              <th class="cursor-pointer text-end text-nowrap" @click="sort('creditlimitdays')">Credit Days <i class="fa ms-1" :class="sortIcon('creditlimitdays')"></i></th>
              <th class="cursor-pointer text-end text-nowrap" @click="sort('age')">1-30 <i class="fa ms-1" :class="sortIcon('age')"></i></th>
              <th class="cursor-pointer text-end text-nowrap" @click="sort('age31')">31-60 <i class="fa ms-1" :class="sortIcon('age31')"></i></th>
              <th class="cursor-pointer text-end text-nowrap" @click="sort('age61')">61-90 <i class="fa ms-1" :class="sortIcon('age61')"></i></th>
              <th class="cursor-pointer text-end text-nowrap" @click="sort('age91')">91-120 <i class="fa ms-1" :class="sortIcon('age91')"></i></th>
              <th class="cursor-pointer text-end text-nowrap" @click="sort('age121')">Above 120 <i class="fa ms-1" :class="sortIcon('age121')"></i></th>
              <th class="cursor-pointer text-end text-nowrap" @click="sort('pdcamount')">PDC Amount <i class="fa ms-1" :class="sortIcon('pdcamount')"></i></th>
              <th class="cursor-pointer text-nowrap" @click="sort('pdcdate_sort')">PDC Date <i class="fa ms-1" :class="sortIcon('pdcdate_sort')"></i></th>
              <th class="cursor-pointer text-end text-nowrap" @click="sort('invoicebalance')">Total <i class="fa ms-1" :class="sortIcon('invoicebalance')"></i></th>
            </tr>
          </thead>
          <tbody>
            <tr v-if="!rows.length"><td :colspan="columnCount" class="text-center text-muted py-4">{{ t.no_records_found }}</td></tr>
            <tr v-for="row in rows" :key="row.transactionkey">
              <td>{{ row.route_label || "-" }}</td>
              <td>{{ row.salesman_label || "-" }}</td>
              <td>{{ row.transactiondate || "-" }}</td>
              <td>{{ row.invoicenumber || "-" }}</td>
              <td>{{ row.customercode || "-" }}</td>
              <td>{{ row.customername || "-" }}</td>
              <td class="text-end">{{ row.creditlimitdays }}</td>
              <td class="text-end">{{ formatAmount(row.age) }}</td>
              <td class="text-end">{{ formatAmount(row.age31) }}</td>
              <td class="text-end">{{ formatAmount(row.age61) }}</td>
              <td class="text-end">{{ formatAmount(row.age91) }}</td>
              <td class="text-end">{{ formatAmount(row.age121) }}</td>
              <td class="text-end">{{ formatAmount(row.pdcamount) }}</td>
              <td>{{ row.pdcdate || "-" }}</td>
              <td class="text-end">{{ formatAmount(row.invoicebalance) }}</td>
            </tr>
          </tbody>
          <tfoot>
            <tr>
              <td colspan="7" class="text-end fw-semibold">{{ t.total }}</td>
              <td class="text-end fw-semibold">{{ formatAmount(totals.age) }}</td>
              <td class="text-end fw-semibold">{{ formatAmount(totals.age31) }}</td>
              <td class="text-end fw-semibold">{{ formatAmount(totals.age61) }}</td>
              <td class="text-end fw-semibold">{{ formatAmount(totals.age91) }}</td>
              <td class="text-end fw-semibold">{{ formatAmount(totals.age121) }}</td>
              <td class="text-end fw-semibold">{{ formatAmount(totals.pdcamount) }}</td>
              <td></td>
              <td class="text-end fw-semibold">{{ formatAmount(totals.invoicebalance) }}</td>
            </tr>
          </tfoot>
        </table>
      </div>

      <div class="d-flex justify-content-between align-items-center pt-3">
        <div class="text-muted fs-sm">{{ paginationLabel }}</div>
        <div class="btn-group">
          <button class="btn btn-sm btn-alt-secondary" :disabled="pagination.current_page <= 1" @click="reload(pagination.current_page - 1)">{{ t.previous }}</button>
          <button class="btn btn-sm btn-alt-secondary" disabled>{{ pagination.current_page || 1 }} / {{ pagination.last_page || 1 }}</button>
          <button class="btn btn-sm btn-alt-secondary" :disabled="!pagination.has_more_pages" @click="reload(pagination.current_page + 1)">{{ t.next }}</button>
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
