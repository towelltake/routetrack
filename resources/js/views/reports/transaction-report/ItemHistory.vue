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

const routeEndDateFrom = ref(props.filters.route_end_date_from ?? "");
const routeEndDateTo = ref(props.filters.route_end_date_to ?? "");
const companyCode = ref(props.filters.cmpycode ? String(props.filters.cmpycode) : "");
const regionCode = ref(props.filters.regionmstcode ? String(props.filters.regionmstcode) : "");
const depotCode = ref(props.filters.depotcode ? String(props.filters.depotcode) : "");
const areaCode = ref(props.filters.areacode ? String(props.filters.areacode) : "");
const subAreaCode = ref(props.filters.subareacode ? String(props.filters.subareacode) : "");
const routeCode = ref(props.filters.routecode ? String(props.filters.routecode) : "");
const itemCode = ref(props.filters.itemcode ? String(props.filters.itemcode) : "");
const majorCategoryCode = ref(props.filters.majorcategorycode ? String(props.filters.majorcategorycode) : "");
const perPage = ref(props.pagination.per_page ?? props.filters.per_page ?? 25);
const sortBy = ref(props.sort.by ?? "trip_start_sort");
const sortDir = ref(props.sort.dir ?? "desc");
const { amountDecimalPlaces } = useAmountFormatter();

const columnCount = 20;
const scopedRows = computed(() => props.filterScopeRows ?? []);

const paginationLabel = computed(() => {
  if (!props.pagination.total) return "No records found";
  return `Showing ${props.pagination.from ?? 0} to ${props.pagination.to ?? 0} of ${props.pagination.total}`;
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
const itemValue = computed({
  get: () => findOption(props.itemOptions, itemCode.value),
  set: (option) => { itemCode.value = option ? String(option.id) : ""; },
});
const majorCategoryValue = computed({
  get: () => findOption(props.majorCategoryOptions, majorCategoryCode.value),
  set: (option) => { majorCategoryCode.value = option ? String(option.id) : ""; },
});

watch(filteredCompanyOptions, (options) => ensureValidSelection(companyCode, options));
watch(filteredRegionOptions, (options) => ensureValidSelection(regionCode, options));
watch(filteredDepotOptions, (options) => ensureValidSelection(depotCode, options));
watch(filteredAreaOptions, (options) => ensureValidSelection(areaCode, options));
watch(filteredSubAreaOptions, (options) => ensureValidSelection(subAreaCode, options));
watch(filteredRouteOptions, (options) => ensureValidSelection(routeCode, options));
watch(() => props.itemOptions, (options) => ensureValidSelection(itemCode, options));
watch(() => props.majorCategoryOptions, (options) => ensureValidSelection(majorCategoryCode, options));

function currentParams(page = 1) {
  return {
    route_end_date_from: routeEndDateFrom.value || undefined,
    route_end_date_to: routeEndDateTo.value || undefined,
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
  router.get("/reports/transaction-report/item-history", currentParams(page), {
    preserveScroll: true,
    preserveState: true,
    replace: true,
    only: ["filters", "sort", "rows", "totals", "pagination", "filterScopeRows", "companyOptions", "regionOptions", "depotOptions", "areaOptions", "subAreaOptions", "routeOptions", "itemOptions", "majorCategoryOptions"],
  });
}

function sort(column) {
  if (sortBy.value === column) {
    sortDir.value = sortDir.value === "asc" ? "desc" : "asc";
  } else {
    sortBy.value = column;
    sortDir.value = column === "trip_start_sort" ? "desc" : "asc";
  }
  reload();
}

function sortIcon(column) {
  if (sortBy.value !== column) return "fa-sort text-muted";
  return sortDir.value === "asc" ? "fa-sort-up" : "fa-sort-down";
}

function resetFilters() {
  const today = new Date().toISOString().slice(0, 10);
  routeEndDateFrom.value = today;
  routeEndDateTo.value = today;
  companyCode.value = "";
  regionCode.value = "";
  depotCode.value = "";
  areaCode.value = "";
  subAreaCode.value = "";
  routeCode.value = "";
  itemCode.value = "";
  majorCategoryCode.value = "";
  perPage.value = 25;
  sortBy.value = "trip_start_sort";
  sortDir.value = "desc";
  reload();
}

function exportUrl(type) {
  const params = new URLSearchParams();
  Object.entries(currentParams()).forEach(([key, value]) => {
    if (value !== undefined && value !== null && value !== "") {
      params.set(key, String(value));
    }
  });
  return `/reports/transaction-report/item-history/export/${type}?${params.toString()}`;
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
  <Head title="Item History" />

  <BasePageHeading
    title="Item History"
    subtitle="Review route trip inventory movement, sales, returns, and closing stock by item and major category."
  />

  <div class="content">
    <BaseBlock title="Global Report Filters">
      <div class="row g-3 align-items-end mb-3">
        <div class="col-md-3"><label class="form-label">Company</label><VueSelect v-model="companyValue" class="report-filter-select" :options="filteredCompanyOptions" label="label" placeholder="All Companies" :clearable="true" /></div>
        <div class="col-md-3"><label class="form-label">Region</label><VueSelect v-model="regionValue" class="report-filter-select" :options="filteredRegionOptions" label="label" placeholder="All Regions" :clearable="true" /></div>
        <div class="col-md-3"><label class="form-label">Branch / Depot</label><VueSelect v-model="depotValue" class="report-filter-select" :options="filteredDepotOptions" label="label" placeholder="All Branches / Depots" :clearable="true" /></div>
        <div class="col-md-3"><label class="form-label">Area</label><VueSelect v-model="areaValue" class="report-filter-select" :options="filteredAreaOptions" label="label" placeholder="All Areas" :clearable="true" /></div>
        <div class="col-md-3"><label class="form-label">Sub Area</label><VueSelect v-model="subAreaValue" class="report-filter-select" :options="filteredSubAreaOptions" label="label" placeholder="All Sub Areas" :clearable="true" /></div>
        <div class="col-md-3"><label class="form-label">Route</label><VueSelect v-model="routeValue" class="report-filter-select" :options="filteredRouteOptions" label="label" placeholder="All Routes" :clearable="true" /></div>
        <div class="col-md-3"><label class="form-label">Items</label><VueSelect v-model="itemValue" class="report-filter-select" :options="props.itemOptions" label="label" placeholder="All Items" :clearable="true" /></div>
        <div class="col-md-3"><label class="form-label">Major Category</label><VueSelect v-model="majorCategoryValue" class="report-filter-select" :options="props.majorCategoryOptions" label="label" placeholder="All Major Categories" :clearable="true" /></div>
        <div class="col-md-2"><label class="form-label">Start Date</label><input v-model="routeEndDateFrom" type="date" class="form-control" /></div>
        <div class="col-md-2"><label class="form-label">End Date</label><input v-model="routeEndDateTo" type="date" class="form-control" /></div>
        <div class="col-md-2 d-grid gap-2">
          <button class="btn btn-primary" @click="reload()"><i class="fa fa-magnifying-glass me-1"></i> Load Report</button>
          <button class="btn btn-alt-secondary" @click="resetFilters"><i class="fa fa-rotate-left me-1"></i> Reset</button>
        </div>
      </div>
    </BaseBlock>

    <BaseBlock title="Item History Summary">
      <template #options>
        <div class="d-flex align-items-center gap-2">
          <button type="button" class="btn btn-sm btn-alt-success" @click="exportExcel"><i class="fa fa-file-excel me-1"></i> Excel</button>
          <button type="button" class="btn btn-sm btn-alt-danger" @click="openPdfExport"><i class="fa fa-file-pdf me-1"></i> PDF</button>
          <div class="text-muted fs-sm">{{ paginationLabel }}</div>
        </div>
      </template>

      <div class="table-responsive">
        <table class="table table-bordered table-striped table-vcenter fs-sm">
          <thead>
            <tr>
              <th class="cursor-pointer text-nowrap" @click="sort('trip_start_sort')">Trip Start Date - Trip End Date <i class="fa ms-1" :class="sortIcon('trip_start_sort')"></i></th>
              <th class="cursor-pointer text-nowrap" @click="sort('routecode')">Route <i class="fa ms-1" :class="sortIcon('routecode')"></i></th>
              <th class="cursor-pointer text-nowrap" @click="sort('majorcategorycode')">Group <i class="fa ms-1" :class="sortIcon('majorcategorycode')"></i></th>
              <th class="cursor-pointer text-nowrap" @click="sort('itemcode_sort')">Item Code <i class="fa ms-1" :class="sortIcon('itemcode_sort')"></i></th>
              <th class="cursor-pointer text-nowrap" @click="sort('itemdescription_sort')">Item Description <i class="fa ms-1" :class="sortIcon('itemdescription_sort')"></i></th>
              <th class="text-nowrap">Opening Case/Unit</th>
              <th class="text-nowrap">Load Case/Unit</th>
              <th class="text-nowrap">Transfer IN Case/Unit</th>
              <th class="text-nowrap">Transfer OUT Case/Unit</th>
              <th class="text-nowrap">Sales Case/Unit</th>
              <th class="text-nowrap">Good Return Case/Unit</th>
              <th class="text-nowrap">Bad Return Case/Unit</th>
              <th class="text-nowrap">Free Case/Unit</th>
              <th class="text-nowrap">Damage Variance Case/Unit</th>
              <th class="cursor-pointer text-nowrap text-end" @click="sort('damagevariancevalue')">Damage Variance Value <i class="fa ms-1" :class="sortIcon('damagevariancevalue')"></i></th>
              <th class="text-nowrap">Closing Case/Unit</th>
              <th class="cursor-pointer text-nowrap text-end" @click="sort('openingvalue')">Opening Stock Value <i class="fa ms-1" :class="sortIcon('openingvalue')"></i></th>
              <th class="cursor-pointer text-nowrap text-end" @click="sort('loadvalue')">Daily Loaded Value <i class="fa ms-1" :class="sortIcon('loadvalue')"></i></th>
              <th class="cursor-pointer text-nowrap text-end" @click="sort('truckstockvalue')">Truck Stock Value <i class="fa ms-1" :class="sortIcon('truckstockvalue')"></i></th>
              <th class="cursor-pointer text-nowrap text-end" @click="sort('endstockvalue')">Closing Value <i class="fa ms-1" :class="sortIcon('endstockvalue')"></i></th>
            </tr>
          </thead>
          <tbody>
            <tr v-if="!rows.length"><td :colspan="columnCount" class="text-center text-muted py-4">No records found.</td></tr>
            <tr v-for="(row, index) in rows" :key="`${row.routekey}-${row.trip_start_sort}-${row.itemcode}-${index}`">
              <td>{{ row.trip_label || "-" }}</td>
              <td>{{ row.route_label || "-" }}</td>
              <td>{{ row.group_label || "-" }}</td>
              <td>{{ row.itemcode || "-" }}</td>
              <td>{{ row.itemdescription || "-" }}</td>
              <td>{{ row.openingqty || "0/0" }}</td>
              <td>{{ row.loadqty || "0/0" }}</td>
              <td>{{ row.transferinqty || "0/0" }}</td>
              <td>{{ row.transferoutqty || "0/0" }}</td>
              <td>{{ row.saleqty || "0/0" }}</td>
              <td>{{ row.retqty || "0/0" }}</td>
              <td>{{ row.dmgqty || "0/0" }}</td>
              <td>{{ row.freeqty || "0/0" }}</td>
              <td>{{ row.damagevariancestock || "0/0" }}</td>
              <td class="text-end">{{ formatAmount(row.damagevariancevalue) }}</td>
              <td>{{ row.vanstockqty || "0/0" }}</td>
              <td class="text-end">{{ formatAmount(row.openingvalue) }}</td>
              <td class="text-end">{{ formatAmount(row.loadvalue) }}</td>
              <td class="text-end">{{ formatAmount(row.truckstockvalue) }}</td>
              <td class="text-end">{{ formatAmount(row.endstockvalue) }}</td>
            </tr>
          </tbody>
          <tfoot v-if="rows.length">
            <tr class="table-light fw-semibold">
              <td colspan="14" class="text-end">Total</td>
              <td class="text-end">{{ formatAmount(totals.damagevariancevalue) }}</td>
              <td></td>
              <td class="text-end">{{ formatAmount(totals.openingvalue) }}</td>
              <td class="text-end">{{ formatAmount(totals.loadvalue) }}</td>
              <td class="text-end">{{ formatAmount(totals.truckstockvalue) }}</td>
              <td class="text-end">{{ formatAmount(totals.endstockvalue) }}</td>
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
