<script setup>
import { computed, ref, watch } from "vue";
import { Head, Link, router, usePage } from "@inertiajs/vue3";
import { usePermissions } from "@/composables/usePermissions";

const props = defineProps({
  records: { type: Object, required: true },
  filters: { type: Object, default: () => ({}) },
});

const page = usePage();
const t = page.props.translations.ui;
const locale = page.props.locale;
const { can } = usePermissions();

const rows = computed(() => props.records?.data ?? []);
const search = ref(props.filters?.search ?? "");
const perPage = ref(props.filters?.per_page ?? 10);
const year = ref(props.filters?.year ?? new Date().getFullYear());
const confirmingDelete = ref(null);

let searchDebounce = null;
watch(search, () => {
  clearTimeout(searchDebounce);
  searchDebounce = setTimeout(() => reloadList(), 300);
});

watch(perPage, () => reloadList());
watch(year, () => reloadList());

function reloadList(pageNumber = 1) {
  router.get("/inventory/targetcommission", {
    search: search.value || undefined,
    per_page: perPage.value,
    year: year.value,
    page: pageNumber,
  }, {
    preserveScroll: true,
    preserveState: true,
    replace: true,
    only: ["records", "filters"],
  });
}

function deleteGroup() {
  router.delete(`/inventory/targetcommission/${confirmingDelete.value.primary_key}`, {
    preserveScroll: true,
    onSuccess: () => {
      confirmingDelete.value = null;
    },
  });
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
</script>

<template>
  <Head :title="t.target_commission" />

  <BasePageHeading :title="t.target_commission" :subtitle="t.target_commission_note">
    <template #extra>
      <Link v-if="can('target & commission', 'create')" href="/inventory/targetcommission/create" class="btn btn-primary">
        <i class="fa fa-plus me-1"></i> {{ t.create_target_commission }}
      </Link>
    </template>
  </BasePageHeading>

  <div class="content">
    <BaseBlock :title="t.target_commission_list">
      <template #options>
        <div class="d-flex gap-2">
          <input v-model="search" type="text" class="form-control form-control-sm" :placeholder="`${t.search}...`" style="width: 220px" />
          <input v-model="year" type="number" min="2000" max="2100" class="form-control form-control-sm" style="width: 100px" />
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
              <th style="width: 140px">{{ t.salesman_code }}</th>
              <th>{{ t.salesman_name }}</th>
              <th style="width: 120px">{{ t.route_code }}</th>
              <th>{{ t.route_name }}</th>
              <th class="text-center" style="width: 110px">{{ t.targets }}</th>
              <th class="text-center" style="width: 150px">{{ t.actions }}</th>
            </tr>
          </thead>
          <tbody>
            <tr v-if="!rows.length">
              <td colspan="6" class="text-center text-muted py-4">{{ t.no_records_found }}</td>
            </tr>
            <tr v-for="record in rows" :key="record.primary_key">
              <td class="fw-semibold">{{ record.salesmancode }}</td>
              <td>{{ salesmanLabel(record) }}</td>
              <td>{{ record.routecode }}</td>
              <td>{{ routeLabel(record) }}</td>
              <td class="text-center">{{ record.target_count }}</td>
              <td class="text-center text-nowrap">
                <Link class="btn btn-sm btn-alt-info me-1" :href="`/inventory/targetcommission/${record.primary_key}`">
                  <i class="fa fa-eye"></i>
                </Link>
                <Link v-if="can('target & commission', 'edit')" class="btn btn-sm btn-alt-secondary me-1" :href="`/inventory/targetcommission/${record.primary_key}/edit`">
                  <i class="fa fa-pen"></i>
                </Link>
                <button v-if="can('target & commission', 'delete')" class="btn btn-sm btn-alt-danger" @click="confirmingDelete = record">
                  <i class="fa fa-trash"></i>
                </button>
              </td>
            </tr>
          </tbody>
        </table>
      </div>

      <div class="d-flex justify-content-between align-items-center pt-3">
        <div class="text-muted fs-sm">
          {{ t.showing }} {{ records.from ?? 0 }} {{ t.to }} {{ records.to ?? 0 }} {{ t.of }} {{ records.total ?? 0 }}
        </div>
        <div class="btn-group">
          <button class="btn btn-sm btn-alt-secondary" :disabled="!records.prev_page_url" @click="reloadList((records.current_page || 1) - 1)">{{ t.previous }}</button>
          <button class="btn btn-sm btn-alt-secondary" disabled>{{ records.current_page || 1 }} / {{ records.last_page || 1 }}</button>
          <button class="btn btn-sm btn-alt-secondary" :disabled="!records.next_page_url" @click="reloadList((records.current_page || 1) + 1)">{{ t.next }}</button>
        </div>
      </div>
    </BaseBlock>
  </div>

  <div v-if="confirmingDelete" class="modal fade show d-block" style="background: rgba(0, 0, 0, 0.45)">
    <div class="modal-dialog modal-dialog-centered modal-sm">
      <div class="modal-content">
        <div class="modal-header">
          <h5 class="modal-title text-danger">
            <i class="fa fa-triangle-exclamation me-1"></i> {{ t.delete }}
          </h5>
          <button class="btn-close" @click="confirmingDelete = null"></button>
        </div>
        <div class="modal-body">
          {{ t.target_commission_delete_confirm }}
        </div>
        <div class="modal-footer">
          <button class="btn btn-alt-secondary" @click="confirmingDelete = null">{{ t.cancel }}</button>
          <button class="btn btn-danger" @click="deleteGroup">{{ t.delete }}</button>
        </div>
      </div>
    </div>
  </div>
</template>
