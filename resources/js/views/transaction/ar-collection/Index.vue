<script setup>
import { computed, ref, watch } from "vue";
import { Head, Link, router, usePage } from "@inertiajs/vue3";
import { useAmountFormatter } from "@/composables/useAmountFormatter";

const props = defineProps({
  documents: { type: Object, required: true },
  routeOptions: { type: Array, required: true },
  filters: { type: Object, required: true },
});

const page = usePage();
const t = page.props.translations.ui;
const locale = page.props.locale;
const { formatAmount } = useAmountFormatter();

const rows = computed(() => props.documents?.data ?? []);
const search = ref(props.filters?.search ?? "");
const date = ref(props.filters?.date ?? "");
const routecode = ref(String(props.filters?.routecode ?? 0));
const perPage = ref(props.filters?.per_page ?? 10);
const sortBy = ref(props.filters?.sort_by ?? "invoicenumber");
const sortDir = ref(props.filters?.sort_dir ?? "desc");

let searchDebounce = null;
watch(search, () => {
  clearTimeout(searchDebounce);
  searchDebounce = setTimeout(() => reloadList(), 300);
});
watch(date, () => reloadList());
watch(routecode, () => reloadList());
watch(perPage, () => reloadList());

function reloadList(pageNumber = 1) {
  router.get("/transaction/ar-collection", {
    date: date.value || undefined,
    routecode: Number(routecode.value || 0) || undefined,
    search: search.value || undefined,
    per_page: perPage.value,
    sort_by: sortBy.value,
    sort_dir: sortDir.value,
    page: pageNumber,
  }, {
    preserveScroll: true,
    preserveState: true,
    replace: true,
    only: ["documents", "routeOptions", "filters"],
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

function salesmanLabel(record) {
  return locale === "ar"
    ? (record.arbsalesmanname1 || record.salesmanname1 || "")
    : (record.salesmanname1 || record.arbsalesmanname1 || "");
}

function customerLabel(record) {
  return locale === "ar"
    ? (record.arbcustomername || record.customername || "")
    : (record.customername || record.arbcustomername || "");
}

function detailUrl(transactionkey) {
  const params = new URLSearchParams();

  if (date.value) params.set("date", date.value);
  if (Number(routecode.value || 0) > 0) params.set("routecode", String(Number(routecode.value)));
  if (search.value) params.set("search", search.value);
  params.set("page", String(props.documents?.current_page || 1));
  params.set("per_page", String(perPage.value));
  params.set("sort_by", sortBy.value);
  params.set("sort_dir", sortDir.value);

  return `/transaction/ar-collection/${transactionkey}?${params.toString()}`;
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
  />

  <div class="content">
    <BaseBlock :title="t.ar_collection_list ?? 'AR Collection List'">
      <template #options>
        <div class="d-flex gap-2">
          <select v-model="routecode" class="form-select form-select-sm" style="width: 220px">
            <option value="0">{{ t.all_routes ?? '--- ALL ---' }}</option>
            <option v-for="option in routeOptions" :key="option.id" :value="String(option.id)">
              {{ option.label }}
            </option>
          </select>
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
              <th class="cursor-pointer" @click="sort('customercode')">
                {{ t.customer_code ?? "Customer Code" }}<i class="fa ms-1" :class="sortIcon('customercode')"></i>
              </th>
              <th class="cursor-pointer" @click="sort('customername')">
                {{ t.customer_name ?? "Customer" }}<i class="fa ms-1" :class="sortIcon('customername')"></i>
              </th>
              <th class="cursor-pointer" @click="sort('invoicenumber')">
                {{ t.receipt_no ?? "Receipt No." }}<i class="fa ms-1" :class="sortIcon('invoicenumber')"></i>
              </th>
              <th class="cursor-pointer" @click="sort('routecode')">
                {{ t.route ?? "Route" }}<i class="fa ms-1" :class="sortIcon('routecode')"></i>
              </th>
              <th class="cursor-pointer" @click="sort('salesmanname1')">
                {{ t.salesman ?? "Salesman" }}<i class="fa ms-1" :class="sortIcon('salesmanname1')"></i>
              </th>
              <th class="cursor-pointer text-end" @click="sort('amountpaid')">
                {{ t.receipt_amount ?? "Receipt Amount" }}<i class="fa ms-1" :class="sortIcon('amountpaid')"></i>
              </th>
              <th class="text-center" style="width: 100px">{{ t.actions ?? "Actions" }}</th>
            </tr>
          </thead>
          <tbody>
            <tr v-if="!rows.length">
              <td colspan="7" class="text-center text-muted py-4">{{ t.no_records ?? "No records found." }}</td>
            </tr>
            <tr v-for="record in rows" :key="record.transactionkey">
              <td>
                {{ record.customercode || "-" }}<span v-if="record.alternatecode"> / {{ record.alternatecode }}</span>
              </td>
              <td>{{ customerLabel(record) || "-" }}</td>
              <td>{{ record.invoicenumber || "-" }}</td>
              <td>{{ record.routecode || "-" }} - {{ routeLabel(record) || "-" }}</td>
              <td>{{ salesmanLabel(record) || "-" }}</td>
              <td class="text-end">{{ amount(record.amountpaid) }}</td>
              <td class="text-center">
                <Link class="btn btn-sm btn-alt-info" :href="detailUrl(record.transactionkey)">
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
