<script setup>
import { computed, ref, watch } from "vue";
import { Head, router } from "@inertiajs/vue3";
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
  yearOptions: { type: Array, required: true },
  customerTypeOptions: { type: Array, required: true },
  customerOptions: { type: Array, required: true },
  periodColumns: { type: Array, required: true },
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
const year = ref(props.filters.year ? String(props.filters.year) : "");
const customerType = ref(props.filters.customer_type ? String(props.filters.customer_type) : "");
const customerCode = ref(props.filters.customer_code ? String(props.filters.customer_code) : "");
const perPage = ref(props.pagination.per_page ?? props.filters.per_page ?? 25);
const sortBy = ref(props.sort.by ?? "hocode_label");
const sortDir = ref(props.sort.dir ?? "asc");
const { amountDecimalPlaces } = useAmountFormatter();

const scopedRows = computed(() => props.filterScopeRows ?? []);
const columnCount = computed(() => props.periodColumns.length + 7);
const paginationLabel = computed(() => !props.pagination.total ? "No records found" : `Showing ${props.pagination.from ?? 0} to ${props.pagination.to ?? 0} of ${props.pagination.total}`);

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
const yearValue = computed({ get: () => findOption(props.yearOptions, year.value), set: (option) => { year.value = option ? String(option.id) : ""; } });
const customerTypeValue = computed({ get: () => findOption(props.customerTypeOptions, customerType.value), set: (option) => { customerType.value = option ? String(option.id) : ""; } });
const customerValue = computed({ get: () => findOption(props.customerOptions, customerCode.value), set: (option) => { customerCode.value = option ? String(option.id) : ""; } });

watch(filteredCompanyOptions, (options) => ensureValidSelection(companyCode, options));
watch(filteredRegionOptions, (options) => ensureValidSelection(regionCode, options));
watch(filteredDepotOptions, (options) => ensureValidSelection(depotCode, options));
watch(filteredAreaOptions, (options) => ensureValidSelection(areaCode, options));
watch(filteredSubAreaOptions, (options) => ensureValidSelection(subAreaCode, options));
watch(filteredRouteOptions, (options) => ensureValidSelection(routeCode, options));
watch(() => props.yearOptions, (options) => ensureValidSelection(year, options), { deep: true });
watch(() => props.customerOptions, (options) => ensureValidSelection(customerCode, options), { deep: true });

function currentParams(page = 1) {
  return {
    cmpycode: companyCode.value || undefined,
    regionmstcode: regionCode.value || undefined,
    depotcode: depotCode.value || undefined,
    areacode: areaCode.value || undefined,
    subareacode: subAreaCode.value || undefined,
    routecode: routeCode.value || undefined,
    year: year.value || undefined,
    customer_type: customerType.value || undefined,
    customer_code: customerCode.value || undefined,
    per_page: perPage.value,
    sort_by: sortBy.value,
    sort_dir: sortDir.value,
    page,
  };
}

function reload(page = 1) {
  router.get("/reports/accounts-report/customer-pending-balance", currentParams(page), {
    preserveScroll: true,
    preserveState: true,
    replace: true,
    only: ["filters", "sort", "rows", "totals", "pagination", "filterScopeRows", "companyOptions", "regionOptions", "depotOptions", "areaOptions", "subAreaOptions", "routeOptions", "yearOptions", "customerTypeOptions", "customerOptions", "periodColumns"],
  });
}

function sort(column) {
  if (sortBy.value === column) sortDir.value = sortDir.value === "asc" ? "desc" : "asc";
  else {
    sortBy.value = column;
    sortDir.value = ["hocode_label", "customer_label", "transactiondate_sort", "salesmancode_sort", "invoicenumber_sort", "comments"].includes(column) ? "asc" : "desc";
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
  year.value = "";
  customerType.value = "";
  customerCode.value = "";
  perPage.value = 25;
  sortBy.value = "hocode_label";
  sortDir.value = "asc";
  reload();
}

function onCustomerTypeChange() {
  customerCode.value = "";
  reload();
}

function exportUrl(type) {
  const params = new URLSearchParams();
  Object.entries(currentParams()).forEach(([key, value]) => {
    if (value !== undefined && value !== null && value !== "") params.set(key, String(value));
  });
  return `/reports/accounts-report/customer-pending-balance/export/${type}?${params.toString()}`;
}

function formatAmount(value, digits = amountDecimalPlaces.value) {
  return Number(value || 0).toLocaleString(undefined, { minimumFractionDigits: digits, maximumFractionDigits: digits });
}
</script>

<template>
  <Head title="Customer Pending Balance" />
  <BasePageHeading title="Customer Pending Balance" subtitle="Review pending invoice balances by head office, customer, invoice, and month." />
  <div class="content">
    <BaseBlock title="Global Report Filters">
      <div class="row g-3 align-items-end mb-3">
        <div class="col-md-3"><label class="form-label">Company</label><VueSelect v-model="companyValue" class="report-filter-select" :options="filteredCompanyOptions" label="label" placeholder="All Companies" :clearable="true" /></div>
        <div class="col-md-3"><label class="form-label">Region</label><VueSelect v-model="regionValue" class="report-filter-select" :options="filteredRegionOptions" label="label" placeholder="All Regions" :clearable="true" /></div>
        <div class="col-md-3"><label class="form-label">Branch / Depot</label><VueSelect v-model="depotValue" class="report-filter-select" :options="filteredDepotOptions" label="label" placeholder="All Branches / Depots" :clearable="true" /></div>
        <div class="col-md-3"><label class="form-label">Area</label><VueSelect v-model="areaValue" class="report-filter-select" :options="filteredAreaOptions" label="label" placeholder="All Areas" :clearable="true" /></div>
        <div class="col-md-3"><label class="form-label">Sub Area</label><VueSelect v-model="subAreaValue" class="report-filter-select" :options="filteredSubAreaOptions" label="label" placeholder="All Sub Areas" :clearable="true" /></div>
        <div class="col-md-3"><label class="form-label">Route</label><VueSelect v-model="routeValue" class="report-filter-select" :options="filteredRouteOptions" label="label" placeholder="All Routes" :clearable="true" /></div>
        <div class="col-md-3"><label class="form-label">Year</label><VueSelect v-model="yearValue" class="report-filter-select" :options="yearOptions" label="label" placeholder="All Years" :clearable="true" /></div>
        <div class="col-md-3"><label class="form-label">Customer Type</label><VueSelect v-model="customerTypeValue" class="report-filter-select" :options="customerTypeOptions" label="label" placeholder="All Customer Types" :clearable="true" @update:model-value="onCustomerTypeChange" /></div>
        <div class="col-md-3"><label class="form-label">Customer</label><VueSelect v-model="customerValue" class="report-filter-select" :options="customerOptions" label="label" placeholder="All Customers" :clearable="true" :disabled="!customerType" /></div>
        <div class="col-md-2 d-grid gap-2">
          <button class="btn btn-primary" @click="reload()"><i class="fa fa-magnifying-glass me-1"></i> Load Report</button>
          <button class="btn btn-alt-secondary" @click="resetFilters"><i class="fa fa-rotate-left me-1"></i> Reset</button>
        </div>
      </div>
    </BaseBlock>

    <BaseBlock title="Customer Pending Balance Report">
      <template #options>
        <div class="d-flex align-items-center gap-2">
          <button type="button" class="btn btn-sm btn-alt-success" @click="window.location.href = exportUrl('excel')"><i class="fa fa-file-excel me-1"></i> Excel</button>
          <button type="button" class="btn btn-sm btn-alt-danger" @click="window.open(exportUrl('pdf'), '_blank', 'noopener')"><i class="fa fa-file-pdf me-1"></i> PDF</button>
          <div class="text-muted fs-sm">{{ paginationLabel }}</div>
        </div>
      </template>

      <div class="table-responsive">
        <table class="table table-bordered table-striped table-vcenter fs-sm">
          <thead>
            <tr>
              <th class="cursor-pointer text-nowrap" @click="sort('hocode_label')">HO Code <i class="fa ms-1" :class="sortIcon('hocode_label')"></i></th>
              <th class="cursor-pointer text-nowrap" @click="sort('customer_label')">Customer <i class="fa ms-1" :class="sortIcon('customer_label')"></i></th>
              <th class="cursor-pointer text-nowrap" @click="sort('transactiondate_sort')">Transaction Date <i class="fa ms-1" :class="sortIcon('transactiondate_sort')"></i></th>
              <th class="cursor-pointer text-nowrap" @click="sort('salesmancode_sort')">Salesman Code <i class="fa ms-1" :class="sortIcon('salesmancode_sort')"></i></th>
              <th class="cursor-pointer text-nowrap" @click="sort('invoicenumber_sort')">Invoice Number <i class="fa ms-1" :class="sortIcon('invoicenumber_sort')"></i></th>
              <th class="cursor-pointer text-nowrap" @click="sort('comments')">Comments <i class="fa ms-1" :class="sortIcon('comments')"></i></th>
              <th v-for="period in periodColumns" :key="period" class="cursor-pointer text-end text-nowrap" @click="sort(`period:${period}`)">{{ period }} <i class="fa ms-1" :class="sortIcon(`period:${period}`)"></i></th>
              <th class="cursor-pointer text-end text-nowrap" @click="sort('total')">Total <i class="fa ms-1" :class="sortIcon('total')"></i></th>
            </tr>
          </thead>
          <tbody>
            <tr v-if="!rows.length"><td :colspan="columnCount" class="text-center text-muted py-4">No records found.</td></tr>
            <tr v-for="row in rows" :key="`${row.hocode_label}-${row.customer_label}-${row.transactiondate_sort}-${row.invoicenumber_sort}`">
              <td>{{ row.hocode_label || "-" }}</td>
              <td>{{ row.customer_label || "-" }}</td>
              <td>{{ row.transactiondate || "-" }}</td>
              <td>{{ row.salesmancode_display || "-" }}</td>
              <td>{{ row.invoicenumber || "-" }}</td>
              <td>{{ row.comments || "-" }}</td>
              <td v-for="period in periodColumns" :key="period" class="text-end">{{ formatAmount(row[`period:${period}`]) }}</td>
              <td class="text-end">{{ formatAmount(row.total) }}</td>
            </tr>
          </tbody>
          <tfoot>
            <tr>
              <td colspan="6" class="text-end fw-semibold">Total</td>
              <td v-for="period in periodColumns" :key="period" class="text-end fw-semibold">{{ formatAmount(totals[`period:${period}`]) }}</td>
              <td class="text-end fw-semibold">{{ formatAmount(totals.total) }}</td>
            </tr>
          </tfoot>
        </table>
      </div>

      <div class="d-flex justify-content-between align-items-center pt-3">
        <div class="text-muted fs-sm">{{ paginationLabel }}</div>
        <div class="btn-group">
          <button class="btn btn-sm btn-alt-secondary" :disabled="pagination.current_page <= 1" @click="reload(pagination.current_page - 1)">Previous</button>
          <button class="btn btn-sm btn-alt-secondary" disabled>{{ pagination.current_page || 1 }} / {{ pagination.last_page || 1 }}</button>
          <button class="btn btn-sm btn-alt-secondary" :disabled="pagination.current_page >= pagination.last_page" @click="reload(pagination.current_page + 1)">Next</button>
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
