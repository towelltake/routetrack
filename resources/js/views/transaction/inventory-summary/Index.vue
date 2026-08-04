<script setup>
import { computed, ref, watch } from "vue";
import { Head, Link, router, usePage } from "@inertiajs/vue3";

const props = defineProps({
  documents: { type: Object, required: true },
  filters: { type: Object, required: true },
});

const page = usePage();
const t = page.props.translations.ui;
const locale = page.props.locale;

const rows = computed(() => props.documents?.data ?? []);
const search = ref(props.filters?.search ?? "");
const date = ref(props.filters?.date ?? "");
const perPage = ref(props.filters?.per_page ?? 10);
const sortBy = ref(props.filters?.sort_by ?? "routestartdate");
const sortDir = ref(props.filters?.sort_dir ?? "desc");

let searchDebounce = null;
watch(search, () => {
  clearTimeout(searchDebounce);
  searchDebounce = setTimeout(() => reloadList(), 300);
});
watch(date, () => reloadList());
watch(perPage, () => reloadList());

function reloadList(pageNumber = 1) {
  router.get("/transaction/inventory-summary", {
    date: date.value || undefined,
    search: search.value || undefined,
    per_page: perPage.value,
    sort_by: sortBy.value,
    sort_dir: sortDir.value,
    page: pageNumber,
  }, {
    preserveScroll: true,
    preserveState: true,
    replace: true,
    only: ["documents", "filters"],
  });
}

function sort(column) {
  if (sortBy.value === column) {
    sortDir.value = sortDir.value === "asc" ? "desc" : "asc";
  } else {
    sortBy.value = column;
    sortDir.value = "asc";
  }

  reloadList();
}

function sortIcon(column) {
  if (sortBy.value !== column) return "";
  return sortDir.value === "asc" ? "fa-sort-up" : "fa-sort-down";
}

function routeLabel(record) {
  return locale === "ar"
    ? (record.arbroutename || record.routename || "")
    : (record.routename || record.arbroutename || "");
}

function detailUrl(routekey) {
  const params = new URLSearchParams();

  if (date.value) params.set("date", date.value);
  if (search.value) params.set("search", search.value);
  params.set("page", String(props.documents?.current_page || 1));
  params.set("per_page", String(perPage.value));
  params.set("sort_by", sortBy.value);
  params.set("sort_dir", sortDir.value);

  return `/transaction/inventory-summary/${routekey}?${params.toString()}`;
}
</script>

<template>
  <Head :title="t.inventory_summary ?? 'Inventory Summary'" />

  <BasePageHeading
    :title="t.inventory_summary ?? 'Inventory Summary'"
    :subtitle="t.inventory_summary_note ?? 'Review route-wise inventory summary records by route start date.'"
  />

  <div class="content">
    <BaseBlock :title="t.inventory_summary_list ?? 'Inventory Summary List'">
      <template #options>
        <div class="d-flex gap-2">
          <input v-model="date" type="date" class="form-control form-control-sm" style="width: 160px" />
          <input v-model="search" type="text" class="form-control form-control-sm" :placeholder="t.search ?? 'Search...'" style="width: 240px" />
          <select v-model="perPage" class="form-select form-select-sm" style="width: 90px">
            <option :value="10">10</option>
            <option :value="25">25</option>
            <option :value="50">50</option>
            <option :value="100">100</option>
          </select>
        </div>
      </template>

      <div class="table-responsive">
        <table class="table table-hover table-vcenter fs-sm">
          <thead>
            <tr>
              <th class="cursor-pointer" @click="sort('routecode')">
                {{ t.route_code ?? "Route Code" }}<i class="fa ms-1" :class="sortIcon('routecode')"></i>
              </th>
              <th class="cursor-pointer" @click="sort('routename')">
                {{ t.route_name ?? "Route Name" }}<i class="fa ms-1" :class="sortIcon('routename')"></i>
              </th>
              <th class="cursor-pointer" @click="sort('salesmancode')">
                {{ t.salesman_code ?? "Salesman Code" }}<i class="fa ms-1" :class="sortIcon('salesmancode')"></i>
              </th>
              <th class="cursor-pointer" @click="sort('routestartdate')">
                {{ t.route_start_date ?? "Route Start Date" }}<i class="fa ms-1" :class="sortIcon('routestartdate')"></i>
              </th>
              <th class="cursor-pointer" @click="sort('routeenddate')">
                {{ t.route_end_date ?? "Route End Date" }}<i class="fa ms-1" :class="sortIcon('routeenddate')"></i>
              </th>
              <th class="cursor-pointer" @click="sort('routeclosed')">
                {{ t.route_close ?? "Route Close" }}<i class="fa ms-1" :class="sortIcon('routeclosed')"></i>
              </th>
              <th class="text-center" style="width: 100px">{{ t.actions ?? "Actions" }}</th>
            </tr>
          </thead>
          <tbody>
            <tr v-if="!rows.length">
              <td colspan="7" class="text-center text-muted py-4">{{ t.no_records ?? "No records found." }}</td>
            </tr>
            <tr v-for="record in rows" :key="record.routekey">
              <td>{{ record.routecode }}</td>
              <td>{{ routeLabel(record) || "-" }}</td>
              <td>{{ record.salesmancode || "-" }}</td>
              <td>{{ record.routestartdate || "-" }}</td>
              <td>{{ record.routeenddate || "-" }}</td>
              <td>{{ record.routeclosed || "-" }}</td>
              <td class="text-center">
                <Link class="btn btn-sm btn-alt-info" :href="detailUrl(record.routekey)">
                  <i class="fa fa-eye"></i>
                </Link>
              </td>
            </tr>
          </tbody>
        </table>
      </div>

      <div class="d-flex justify-content-between align-items-center pt-3">
        <div class="text-muted fs-sm">
          {{ t.showing ?? "Showing" }} {{ documents.from ?? 0 }} {{ t.to ?? "to" }} {{ documents.to ?? 0 }} {{ t.of ?? "of" }} {{ documents.total ?? 0 }}
        </div>
        <div class="btn-group">
          <button class="btn btn-sm btn-alt-secondary" :disabled="!documents.prev_page_url" @click="reloadList((documents.current_page || 1) - 1)">{{ t.previous ?? "Previous" }}</button>
          <button class="btn btn-sm btn-alt-secondary" disabled>{{ documents.current_page || 1 }} / {{ documents.last_page || 1 }}</button>
          <button class="btn btn-sm btn-alt-secondary" :disabled="!documents.next_page_url" @click="reloadList((documents.current_page || 1) + 1)">{{ t.next ?? "Next" }}</button>
        </div>
      </div>
    </BaseBlock>
  </div>
</template>
