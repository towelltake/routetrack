<script setup>
import { computed, ref, watch } from "vue";
import { Head, Link, router, usePage } from "@inertiajs/vue3";
import { usePermissions } from "@/composables/usePermissions";

function todayDate() {
  return new Date().toISOString().slice(0, 10);
}

const props = defineProps({
  documents: { type: Object, required: true },
  filters: { type: Object, default: () => ({}) },
});

const page = usePage();
const t = page.props.translations.ui;
const locale = page.props.locale;
const { can } = usePermissions();

const rows = computed(() => props.documents?.data ?? []);
const search = ref(props.filters?.search ?? "");
const loadDate = ref(props.filters?.load_date ?? todayDate());
const perPage = ref(props.filters?.per_page ?? 10);
const sortBy = ref(props.filters?.sort_by ?? "ddate");
const sortDir = ref(props.filters?.sort_dir ?? "desc");
const confirmingDelete = ref(null);

let searchDebounce = null;
watch(search, () => {
  clearTimeout(searchDebounce);
  searchDebounce = setTimeout(() => reloadList(), 300);
});

watch(loadDate, (value) => {
  if (!value) {
    loadDate.value = todayDate();
    return;
  }

  reloadList();
});
watch(perPage, () => reloadList());

function reloadList(pageNumber = 1) {
  router.get("/inventory/dailysalesmanload", {
    search: search.value || undefined,
    load_date: loadDate.value || todayDate(),
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

function salesmanLabel(record) {
  return locale === "ar"
    ? (record.arbsalesmanname1 || record.salesmanname1 || "")
    : (record.salesmanname1 || record.arbsalesmanname1 || "");
}

function canViewAction(permission) {
  const details = page.props.auth?.formPermissions?.[permission];
  return !!(details?.all || details?.view);
}

function deleteRow() {
  router.delete(`/inventory/dailysalesmanload/${confirmingDelete.value.documentKey}`, {
    preserveScroll: true,
    onSuccess: () => {
      confirmingDelete.value = null;
    },
  });
}
</script>

<template>
  <Head :title="t.daily_salesman_load" />

  <BasePageHeading :title="t.daily_salesman_load" :subtitle="t.daily_salesman_load_note">
    <template #extra>
      <Link v-if="can('daily salesman load', 'create')" href="/inventory/dailysalesmanload/create" class="btn btn-primary">
        <i class="fa fa-plus me-1"></i> {{ t.create_daily_salesman_load }}
      </Link>
    </template>
  </BasePageHeading>

  <div class="content">
    <BaseBlock :title="t.daily_salesman_load_list">
      <template #options>
        <div class="d-flex gap-2">
          <input v-model="loadDate" type="date" class="form-control form-control-sm" style="width: 155px" />
          <input
            v-model="search"
            type="text"
            class="form-control form-control-sm"
            :placeholder="`${t.search}...`"
            style="width: 220px"
          />
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
              <th class="cursor-pointer" @click="sort('ddate')">
                {{ t.load_date }}
                <i class="fa ms-1" :class="sortIcon('ddate')"></i>
              </th>
              <th class="cursor-pointer" @click="sort('routecode')">
                {{ t.route }}
                <i class="fa ms-1" :class="sortIcon('routecode')"></i>
              </th>
              <th class="cursor-pointer" @click="sort('salesmancode')">
                {{ t.salesman }}
                <i class="fa ms-1" :class="sortIcon('salesmancode')"></i>
              </th>
              <th class="cursor-pointer text-center" @click="sort('loadperiodnumber')">
                {{ t.load_period }}
                <i class="fa ms-1" :class="sortIcon('loadperiodnumber')"></i>
              </th>
              <th class="cursor-pointer text-center" @click="sort('status')">
                {{ t.status }}
                <i class="fa ms-1" :class="sortIcon('status')"></i>
              </th>
              <th class="cursor-pointer text-center" @click="sort('itemcount')">
                {{ t.items }}
                <i class="fa ms-1" :class="sortIcon('itemcount')"></i>
              </th>
              <th class="cursor-pointer text-center" @click="sort('totunits')">
                {{ t.total_units }}
                <i class="fa ms-1" :class="sortIcon('totunits')"></i>
              </th>
              <th class="text-center" style="width: 140px">{{ t.actions }}</th>
            </tr>
          </thead>
          <tbody>
            <tr v-if="!rows.length">
              <td colspan="8" class="text-center text-muted py-4">
                {{ t.no_records_found }}
              </td>
            </tr>
            <tr v-for="record in rows" :key="record.documentKey">
              <td class="fw-semibold">{{ record.ddate }}</td>
              <td>{{ record.routecode }} -- {{ routeLabel(record) }}</td>
              <td>{{ record.salesmancode }} -- {{ salesmanLabel(record) }}</td>
              <td class="text-center">{{ record.loadperiodnumber }}</td>
              <td class="text-center">
                <span class="badge" :class="record.status ? 'bg-success-subtle text-success' : 'bg-secondary-subtle text-secondary'">
                  {{ record.statuslabel }}
                </span>
              </td>
              <td class="text-center">{{ record.itemcount }}</td>
              <td class="text-center">{{ record.totunits }}</td>
              <td class="text-center text-nowrap">
                <Link
                  v-if="canViewAction('daily salesman load')"
                  class="btn btn-sm btn-alt-info me-1"
                  :href="`/inventory/dailysalesmanload/${record.documentKey}`"
                >
                  <i class="fa fa-eye"></i>
                </Link>
                <Link
                  v-if="can('daily salesman load', 'edit')"
                  class="btn btn-sm btn-alt-secondary me-1"
                  :href="`/inventory/dailysalesmanload/${record.documentKey}/edit`"
                >
                  <i class="fa fa-pen"></i>
                </Link>
                <button
                  v-if="can('daily salesman load', 'delete')"
                  class="btn btn-sm btn-alt-danger"
                  @click="confirmingDelete = record"
                >
                  <i class="fa fa-trash"></i>
                </button>
              </td>
            </tr>
          </tbody>
        </table>
      </div>

      <div class="d-flex justify-content-between align-items-center pt-3">
        <div class="text-muted fs-sm">
          {{ t.showing }} {{ documents.from ?? 0 }} {{ t.to }} {{ documents.to ?? 0 }} {{ t.of }} {{ documents.total ?? 0 }}
        </div>
        <div class="btn-group">
          <button class="btn btn-sm btn-alt-secondary" :disabled="!documents.prev_page_url" @click="reloadList((documents.current_page || 1) - 1)">{{ t.previous }}</button>
          <button class="btn btn-sm btn-alt-secondary" disabled>{{ documents.current_page || 1 }} / {{ documents.last_page || 1 }}</button>
          <button class="btn btn-sm btn-alt-secondary" :disabled="!documents.next_page_url" @click="reloadList((documents.current_page || 1) + 1)">{{ t.next }}</button>
        </div>
      </div>
    </BaseBlock>
  </div>

  <div
    v-if="confirmingDelete"
    class="modal fade show d-block"
    style="background: rgba(0, 0, 0, 0.45)"
  >
    <div class="modal-dialog modal-dialog-centered modal-sm">
      <div class="modal-content">
        <div class="modal-header">
          <h5 class="modal-title text-danger">
            <i class="fa fa-triangle-exclamation me-1"></i> {{ t.delete }}
          </h5>
          <button class="btn-close" @click="confirmingDelete = null"></button>
        </div>
        <div class="modal-body">
          {{ t.daily_salesman_load_delete_confirm }}
        </div>
        <div class="modal-footer">
          <button class="btn btn-alt-secondary" @click="confirmingDelete = null">
            {{ t.cancel }}
          </button>
          <button class="btn btn-danger" @click="deleteRow">
            {{ t.delete }}
          </button>
        </div>
      </div>
    </div>
  </div>
</template>
