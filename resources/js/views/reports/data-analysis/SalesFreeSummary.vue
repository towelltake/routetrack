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
  itemOptions: { type: Array, required: true },
  majorCategoryOptions: { type: Array, required: true },
  rows: { type: Array, required: true },
  totals: { type: Object, required: true },
  pagination: { type: Object, required: true },
});

const transactionDateFrom = ref(props.filters.transaction_date_from ?? "");
const transactionDateTo = ref(props.filters.transaction_date_to ?? "");
const companyCode = ref(props.filters.cmpycode ? String(props.filters.cmpycode) : "");
const regionCode = ref(props.filters.regionmstcode ? String(props.filters.regionmstcode) : "");
const depotCode = ref(props.filters.depotcode ? String(props.filters.depotcode) : "");
const areaCode = ref(props.filters.areacode ? String(props.filters.areacode) : "");
const subAreaCode = ref(props.filters.subareacode ? String(props.filters.subareacode) : "");
const routeCode = ref(props.filters.routecode ? String(props.filters.routecode) : "");
const itemCode = ref(props.filters.itemcode ? String(props.filters.itemcode) : "");
const majorCategoryCode = ref(props.filters.majorcategorycode ? String(props.filters.majorcategorycode) : "");
const perPage = ref(props.pagination.per_page ?? props.filters.per_page ?? 25);
const sortBy = ref(props.sort.by ?? "routecode_sort");
const sortDir = ref(props.sort.dir ?? "asc");
const { amountDecimalPlaces } = useAmountFormatter();

const scopedRows = computed(() => props.filterScopeRows ?? []);
const columnCount = 23;
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
const itemValue = computed({ get: () => findOption(props.itemOptions, itemCode.value), set: (option) => { itemCode.value = option ? String(option.id) : ""; } });
const majorCategoryValue = computed({ get: () => findOption(props.majorCategoryOptions, majorCategoryCode.value), set: (option) => { majorCategoryCode.value = option ? String(option.id) : ""; } });

watch(filteredCompanyOptions, (options) => ensureValidSelection(companyCode, options));
watch(filteredRegionOptions, (options) => ensureValidSelection(regionCode, options));
watch(filteredDepotOptions, (options) => ensureValidSelection(depotCode, options));
watch(filteredAreaOptions, (options) => ensureValidSelection(areaCode, options));
watch(filteredSubAreaOptions, (options) => ensureValidSelection(subAreaCode, options));
watch(filteredRouteOptions, (options) => ensureValidSelection(routeCode, options));
watch(() => props.itemOptions, (options) => ensureValidSelection(itemCode, options), { deep: true });
watch(() => props.majorCategoryOptions, (options) => ensureValidSelection(majorCategoryCode, options), { deep: true });

function currentParams(page = 1) {
  return {
    transaction_date_from: transactionDateFrom.value || undefined,
    transaction_date_to: transactionDateTo.value || undefined,
    cmpycode: companyCode.value || undefined,
    regionmstcode: regionCode.value || undefined,
    depotcode: depotCode.value || undefined,
    areacode: areaCode.value || undefined,
    subareacode: subAreaCode.value || undefined,
    routecode: routeCode.value || undefined,
    itemcode: itemCode.value || undefined,
    majorcategorycode: majorCategoryCode.value || undefined,
    per_page: perPage.value,
    sort_by: sortBy.value,
    sort_dir: sortDir.value,
    page,
  };
}

function reload(page = 1) {
  router.get("/reports/data-analysis/sales-free-summary", currentParams(page), {
    preserveScroll: true,
    preserveState: true,
    replace: true,
    only: ["filters", "sort", "rows", "totals", "pagination", "filterScopeRows", "companyOptions", "regionOptions", "depotOptions", "areaOptions", "subAreaOptions", "routeOptions", "itemOptions", "majorCategoryOptions"],
  });
}

function sort(column) {
  if (sortBy.value === column) sortDir.value = sortDir.value === "asc" ? "desc" : "asc";
  else {
    sortBy.value = column;
    sortDir.value = ["transactiondate_sort", "routecode_sort", "majorcategorycode_sort", "itemcode_sort"].includes(column) ? "asc" : "desc";
  }
  reload();
}

function sortIcon(column) {
  if (sortBy.value !== column) return "fa-sort text-muted";
  return sortDir.value === "asc" ? "fa-sort-up" : "fa-sort-down";
}

function resetFilters() {
  transactionDateFrom.value = props.filters.transaction_date_from ?? "";
  transactionDateTo.value = props.filters.transaction_date_to ?? "";
  companyCode.value = "";
  regionCode.value = "";
  depotCode.value = "";
  areaCode.value = "";
  subAreaCode.value = "";
  routeCode.value = "";
  itemCode.value = "";
  majorCategoryCode.value = "";
  perPage.value = 25;
  sortBy.value = "routecode_sort";
  sortDir.value = "asc";
  reload();
}

function exportUrl(type) {
  const params = new URLSearchParams();
  Object.entries(currentParams()).forEach(([key, value]) => {
    if (value !== undefined && value !== null && value !== "") params.set(key, String(value));
  });
  return `/reports/data-analysis/sales-free-summary/export/${type}?${params.toString()}`;
}

function formatAmount(value, digits = amountDecimalPlaces.value) {
  return Number(value || 0).toLocaleString(undefined, { minimumFractionDigits: digits, maximumFractionDigits: digits });
}
</script>

<template>
  <Head title="Sales Free Summary" />
  <BasePageHeading title="Sales Free Summary" subtitle="Review sales, free, return, and discount values by route and item." />
  <div class="content">
    <BaseBlock title="Global Report Filters">
      <div class="row g-3 align-items-end mb-3">
        <div class="col-md-3"><label class="form-label">Start Date</label><input v-model="transactionDateFrom" type="date" class="form-control"></div>
        <div class="col-md-3"><label class="form-label">End Date</label><input v-model="transactionDateTo" type="date" class="form-control"></div>
        <div class="col-md-3"><label class="form-label">Company</label><VueSelect v-model="companyValue" class="report-filter-select" :options="filteredCompanyOptions" label="label" placeholder="All Companies" :clearable="true" /></div>
        <div class="col-md-3"><label class="form-label">Region</label><VueSelect v-model="regionValue" class="report-filter-select" :options="filteredRegionOptions" label="label" placeholder="All Regions" :clearable="true" /></div>
        <div class="col-md-3"><label class="form-label">Branch / Depot</label><VueSelect v-model="depotValue" class="report-filter-select" :options="filteredDepotOptions" label="label" placeholder="All Branches / Depots" :clearable="true" /></div>
        <div class="col-md-3"><label class="form-label">Area</label><VueSelect v-model="areaValue" class="report-filter-select" :options="filteredAreaOptions" label="label" placeholder="All Areas" :clearable="true" /></div>
        <div class="col-md-3"><label class="form-label">Sub Area</label><VueSelect v-model="subAreaValue" class="report-filter-select" :options="filteredSubAreaOptions" label="label" placeholder="All Sub Areas" :clearable="true" /></div>
        <div class="col-md-3"><label class="form-label">Route</label><VueSelect v-model="routeValue" class="report-filter-select" :options="filteredRouteOptions" label="label" placeholder="All Routes" :clearable="true" /></div>
        <div class="col-md-3"><label class="form-label">Items</label><VueSelect v-model="itemValue" class="report-filter-select" :options="itemOptions" label="label" placeholder="All Items" :clearable="true" /></div>
        <div class="col-md-3"><label class="form-label">Major Category</label><VueSelect v-model="majorCategoryValue" class="report-filter-select" :options="majorCategoryOptions" label="label" placeholder="All Categories" :clearable="true" /></div>
        <div class="col-md-2 d-grid gap-2">
          <button class="btn btn-primary" @click="reload()"><i class="fa fa-magnifying-glass me-1"></i> Load Report</button>
          <button class="btn btn-alt-secondary" @click="resetFilters"><i class="fa fa-rotate-left me-1"></i> Reset</button>
        </div>
      </div>
    </BaseBlock>

    <BaseBlock title="Sales Free Summary Report">
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
              <th class="cursor-pointer text-nowrap" @click="sort('transactiondate_sort')">Transaction Date <i class="fa ms-1" :class="sortIcon('transactiondate_sort')"></i></th>
              <th class="cursor-pointer text-nowrap" @click="sort('routecode_sort')">Route <i class="fa ms-1" :class="sortIcon('routecode_sort')"></i></th>
              <th class="cursor-pointer text-nowrap" @click="sort('majorcategorycode_sort')">Item Group <i class="fa ms-1" :class="sortIcon('majorcategorycode_sort')"></i></th>
              <th class="cursor-pointer text-nowrap" @click="sort('itemcode_sort')">Item Code <i class="fa ms-1" :class="sortIcon('itemcode_sort')"></i></th>
              <th class="text-nowrap">Item Description</th>
              <th class="cursor-pointer text-end text-nowrap" @click="sort('upc')">UPC <i class="fa ms-1" :class="sortIcon('upc')"></i></th>
              <th class="cursor-pointer text-end text-nowrap" @click="sort('salesqty')">Sales Qty <i class="fa ms-1" :class="sortIcon('salesqty')"></i></th>
              <th class="cursor-pointer text-end text-nowrap" @click="sort('freeqty')">Free Qty <i class="fa ms-1" :class="sortIcon('freeqty')"></i></th>
              <th class="cursor-pointer text-end text-nowrap" @click="sort('damagedqty')">Bad Ret. Qty <i class="fa ms-1" :class="sortIcon('damagedqty')"></i></th>
              <th class="cursor-pointer text-end text-nowrap" @click="sort('returnqty')">Good Ret. Qty <i class="fa ms-1" :class="sortIcon('returnqty')"></i></th>
              <th class="cursor-pointer text-end text-nowrap" @click="sort('sales_std_price')">Sales @ Std. Price <i class="fa ms-1" :class="sortIcon('sales_std_price')"></i></th>
              <th class="cursor-pointer text-end text-nowrap" @click="sort('sales_inv_price')">Sales @ Inv. Price <i class="fa ms-1" :class="sortIcon('sales_inv_price')"></i></th>
              <th class="cursor-pointer text-end text-nowrap" @click="sort('bad_ret_std_price')">Bad Ret @ Std. Price <i class="fa ms-1" :class="sortIcon('bad_ret_std_price')"></i></th>
              <th class="cursor-pointer text-end text-nowrap" @click="sort('bad_ret_inv_price')">Bad Ret @ Inv. Price <i class="fa ms-1" :class="sortIcon('bad_ret_inv_price')"></i></th>
              <th class="cursor-pointer text-end text-nowrap" @click="sort('good_ret_std_price')">Good Return @ Std. Price <i class="fa ms-1" :class="sortIcon('good_ret_std_price')"></i></th>
              <th class="cursor-pointer text-end text-nowrap" @click="sort('good_ret_inv_price')">Good Return @ Inv. Price <i class="fa ms-1" :class="sortIcon('good_ret_inv_price')"></i></th>
              <th class="cursor-pointer text-end text-nowrap" @click="sort('inv_discount_breakup')">Inv. Discount Break Up <i class="fa ms-1" :class="sortIcon('inv_discount_breakup')"></i></th>
              <th class="cursor-pointer text-end text-nowrap" @click="sort('item_discount_breakup')">Item Discount Break Up <i class="fa ms-1" :class="sortIcon('item_discount_breakup')"></i></th>
              <th class="cursor-pointer text-end text-nowrap" @click="sort('free_goods_std_price')">Free Goods @ Std. Price <i class="fa ms-1" :class="sortIcon('free_goods_std_price')"></i></th>
              <th class="cursor-pointer text-end text-nowrap" @click="sort('price_difference')">Price Difference <i class="fa ms-1" :class="sortIcon('price_difference')"></i></th>
              <th class="cursor-pointer text-end text-nowrap" @click="sort('total_foc')">Total FOC <i class="fa ms-1" :class="sortIcon('total_foc')"></i></th>
              <th class="cursor-pointer text-end text-nowrap" @click="sort('netqty')">Net Sales Qty <i class="fa ms-1" :class="sortIcon('netqty')"></i></th>
              <th class="cursor-pointer text-end text-nowrap" @click="sort('netamount')">Net Sales Amount <i class="fa ms-1" :class="sortIcon('netamount')"></i></th>
            </tr>
          </thead>
          <tbody>
            <tr v-if="!rows.length"><td :colspan="columnCount" class="text-center text-muted py-4">No records found.</td></tr>
            <tr v-for="row in rows" :key="`${row.transactiondate_sort}-${row.routecode_sort}-${row.majorcategorycode_sort}-${row.itemcode_sort}`">
              <td>{{ row.transactiondate || "-" }}</td>
              <td>{{ row.route_label || "-" }}</td>
              <td>{{ row.majorcategory_label || "-" }}</td>
              <td>{{ row.itemcode || "-" }}</td>
              <td>{{ row.itemdescription || "-" }}</td>
              <td class="text-end">{{ formatAmount(row.upc, 0) }}</td>
              <td class="text-end">{{ formatAmount(row.salesqty) }}</td>
              <td class="text-end">{{ formatAmount(row.freeqty) }}</td>
              <td class="text-end">{{ formatAmount(row.damagedqty) }}</td>
              <td class="text-end">{{ formatAmount(row.returnqty) }}</td>
              <td class="text-end">{{ formatAmount(row.sales_std_price) }}</td>
              <td class="text-end">{{ formatAmount(row.sales_inv_price) }}</td>
              <td class="text-end">{{ formatAmount(row.bad_ret_std_price) }}</td>
              <td class="text-end">{{ formatAmount(row.bad_ret_inv_price) }}</td>
              <td class="text-end">{{ formatAmount(row.good_ret_std_price) }}</td>
              <td class="text-end">{{ formatAmount(row.good_ret_inv_price) }}</td>
              <td class="text-end">{{ formatAmount(row.inv_discount_breakup) }}</td>
              <td class="text-end">{{ formatAmount(row.item_discount_breakup) }}</td>
              <td class="text-end">{{ formatAmount(row.free_goods_std_price) }}</td>
              <td class="text-end">{{ formatAmount(row.price_difference) }}</td>
              <td class="text-end">{{ formatAmount(row.total_foc) }}</td>
              <td class="text-end">{{ formatAmount(row.netqty) }}</td>
              <td class="text-end">{{ formatAmount(row.netamount) }}</td>
            </tr>
          </tbody>
          <tfoot>
            <tr>
              <td colspan="5" class="text-end fw-semibold">Total</td>
              <td class="text-end fw-semibold">{{ formatAmount(totals.upc, 0) }}</td>
              <td class="text-end fw-semibold">{{ formatAmount(totals.salesqty) }}</td>
              <td class="text-end fw-semibold">{{ formatAmount(totals.freeqty) }}</td>
              <td class="text-end fw-semibold">{{ formatAmount(totals.damagedqty) }}</td>
              <td class="text-end fw-semibold">{{ formatAmount(totals.returnqty) }}</td>
              <td class="text-end fw-semibold">{{ formatAmount(totals.sales_std_price) }}</td>
              <td class="text-end fw-semibold">{{ formatAmount(totals.sales_inv_price) }}</td>
              <td class="text-end fw-semibold">{{ formatAmount(totals.bad_ret_std_price) }}</td>
              <td class="text-end fw-semibold">{{ formatAmount(totals.bad_ret_inv_price) }}</td>
              <td class="text-end fw-semibold">{{ formatAmount(totals.good_ret_std_price) }}</td>
              <td class="text-end fw-semibold">{{ formatAmount(totals.good_ret_inv_price) }}</td>
              <td class="text-end fw-semibold">{{ formatAmount(totals.inv_discount_breakup) }}</td>
              <td class="text-end fw-semibold">{{ formatAmount(totals.item_discount_breakup) }}</td>
              <td class="text-end fw-semibold">{{ formatAmount(totals.free_goods_std_price) }}</td>
              <td class="text-end fw-semibold">{{ formatAmount(totals.price_difference) }}</td>
              <td class="text-end fw-semibold">{{ formatAmount(totals.total_foc) }}</td>
              <td class="text-end fw-semibold">{{ formatAmount(totals.netqty) }}</td>
              <td class="text-end fw-semibold">{{ formatAmount(totals.netamount) }}</td>
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
