<script setup>
import { computed, ref, watch } from "vue";
import { Head, Link, router, usePage } from "@inertiajs/vue3";
import { usePermissions } from "@/composables/usePermissions";

const props = defineProps({
  routeItemGroups: { type: Object, required: true },
  filters: { type: Object, default: () => ({}) },
});

const page = usePage();
const t = page.props.translations.ui;
const locale = page.props.locale;
const { can } = usePermissions();

const rows = computed(() => props.routeItemGroups?.data ?? []);
const search = ref(props.filters?.search ?? "");
const perPage = ref(props.filters?.per_page ?? 10);
const sortBy = ref(props.filters?.sort_by ?? "routeitemgrpcode");
const sortDir = ref(props.filters?.sort_dir ?? "asc");
const confirmingDelete = ref(null);

let searchDebounce = null;
watch(search, () => {
  clearTimeout(searchDebounce);
  searchDebounce = setTimeout(() => reloadList(), 300);
});

watch(perPage, () => reloadList());

function reloadList(pageNumber = 1) {
  router.get("/inventory/routeitemgroup", {
    search: search.value || undefined,
    per_page: perPage.value,
    sort_by: sortBy.value,
    sort_dir: sortDir.value,
    page: pageNumber,
  }, {
    preserveScroll: true,
    preserveState: true,
    replace: true,
    only: ["routeItemGroups", "filters"],
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

function itemGroupLabel(record) {
  if (!record.itemgroupcode) return t.all_items;
  return locale === "ar"
    ? (record.arbitemgroup || record.itemgroupname || "")
    : (record.itemgroupname || record.arbitemgroup || "");
}

function canViewAction(permission) {
  const details = page.props.auth?.formPermissions?.[permission];
  return !!(details?.all || details?.view);
}

function deleteRow() {
  router.delete(`/inventory/routeitemgroup/${confirmingDelete.value.routeitemgrpcode}`, {
    preserveScroll: true,
    onSuccess: () => {
      confirmingDelete.value = null;
    },
  });
}
</script>

<template>
  <Head :title="t.route_item_group" />

  <BasePageHeading :title="t.route_item_group" :subtitle="t.route_item_group_note">
    <template #extra>
      <Link v-if="can('route item group', 'create')" href="/inventory/routeitemgroup/create" class="btn btn-primary">
        <i class="fa fa-plus me-1"></i> {{ t.create_route_item_group }}
      </Link>
    </template>
  </BasePageHeading>

  <div class="content">
    <BaseBlock :title="t.route_item_group_list">
      <template #options>
        <div class="d-flex gap-2">
          <input
            v-model="search"
            type="text"
            class="form-control form-control-sm"
            :placeholder="`${t.search}...`"
            style="width: 200px"
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
              <th class="cursor-pointer" @click="sort('routeitemgrpcode')">
                {{ t.code }}
                <i class="fa ms-1" :class="sortIcon('routeitemgrpcode')"></i>
              </th>
              <th class="cursor-pointer" @click="sort('description')">
                {{ t.descriptions }}
                <i class="fa ms-1" :class="sortIcon('description')"></i>
              </th>
              <th class="cursor-pointer" @click="sort('itemgroupname')">
                {{ t.item_group }}
                <i class="fa ms-1" :class="sortIcon('itemgroupname')"></i>
              </th>
              <th class="cursor-pointer text-center" @click="sort('transferstatus')">
                {{ t.status }}
                <i class="fa ms-1" :class="sortIcon('transferstatus')"></i>
              </th>
              <th class="text-center" style="width: 140px">{{ t.actions }}</th>
            </tr>
          </thead>
          <tbody>
            <tr v-if="!rows.length">
              <td colspan="5" class="text-center text-muted py-4">
                {{ t.no_records_found }}
              </td>
            </tr>
            <tr v-for="record in rows" :key="record.routeitemgrpcode">
              <td class="fw-semibold">{{ record.routeitemgrpcode }}</td>
              <td>{{ record.description }}</td>
              <td>{{ itemGroupLabel(record) }}</td>
              <td class="text-center">
                <span
                  class="fs-xs fw-semibold d-inline-block py-1 px-3 rounded-pill"
                  :class="record.transferstatus === 1 ? 'bg-success-light text-success' : 'bg-danger-light text-danger'"
                >
                  {{ record.transferstatus === 1 ? t.status_active : t.status_inactive }}
                </span>
              </td>
              <td class="text-center text-nowrap">
                <Link
                  v-if="canViewAction('route item group')"
                  class="btn btn-sm btn-alt-info me-1"
                  :href="`/inventory/routeitemgroup/${record.routeitemgrpcode}`"
                >
                  <i class="fa fa-eye"></i>
                </Link>
                <Link
                  v-if="can('route item group', 'edit')"
                  class="btn btn-sm btn-alt-secondary me-1"
                  :href="`/inventory/routeitemgroup/${record.routeitemgrpcode}/edit`"
                >
                  <i class="fa fa-pen"></i>
                </Link>
                <button
                  v-if="can('route item group', 'delete')"
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
          {{ t.showing }} {{ routeItemGroups.from ?? 0 }} {{ t.to }} {{ routeItemGroups.to ?? 0 }} {{ t.of }} {{ routeItemGroups.total ?? 0 }}
        </div>
        <div class="btn-group">
          <button class="btn btn-sm btn-alt-secondary" :disabled="!routeItemGroups.prev_page_url" @click="reloadList((routeItemGroups.current_page || 1) - 1)">{{ t.previous }}</button>
          <button class="btn btn-sm btn-alt-secondary" disabled>{{ routeItemGroups.current_page || 1 }} / {{ routeItemGroups.last_page || 1 }}</button>
          <button class="btn btn-sm btn-alt-secondary" :disabled="!routeItemGroups.next_page_url" @click="reloadList((routeItemGroups.current_page || 1) + 1)">{{ t.next }}</button>
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
          {{ t.route_item_group_delete_confirm }}
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
