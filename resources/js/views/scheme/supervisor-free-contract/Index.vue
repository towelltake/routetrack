<script setup>
import { computed, ref, watch } from "vue";
import { Head, router, usePage } from "@inertiajs/vue3";
import { usePermissions } from "@/composables/usePermissions";

const props = defineProps({
  available: { type: Boolean, default: false },
  contracts: { type: Object, required: true },
  filters: { type: Object, default: () => ({}) },
});

const search = ref(props.filters?.search ?? "");
const perPage = ref(props.filters?.per_page ?? 10);
const confirmingDelete = ref(null);
const rows = computed(() => props.contracts?.data ?? []);
const { can } = usePermissions();
const t = usePage().props.translations.ui;

let searchDebounce = null;
watch(search, () => {
  clearTimeout(searchDebounce);
  searchDebounce = setTimeout(() => reloadList(), 300);
});
watch(perPage, () => reloadList());

function reloadList(pageNumber = 1) {
  router.get(
    "/scheme/supervisor-free-contract",
    { search: search.value || undefined, per_page: perPage.value, page: pageNumber },
    { preserveScroll: true, preserveState: true, replace: true, only: ["available", "contracts", "filters"] },
  );
}

function deleteRow() {
  router.delete(`/scheme/supervisor-free-contract/${confirmingDelete.value.contractid}`, {
    preserveScroll: true,
    onSuccess: () => { confirmingDelete.value = null; },
  });
}

function formatDate(value) {
  if (!value) return "-";
  const d = new Date(value);
  return isNaN(d.getTime()) ? value : d.toLocaleDateString("en-GB");
}

function statusClass(s) {
  if (s === "Running") return "bg-success-subtle text-success";
  if (s === "Pending") return "bg-warning-subtle text-warning";
  if (s === "Ended")   return "bg-secondary-subtle text-secondary";
  return "bg-light text-muted";
}
</script>

<template>
  <Head :title="t.supervisor_free_contract" />

  <BasePageHeading
    :title="t.supervisor_free_contract"
    :subtitle="t.supervisor_free_contract_note"
  >
    <template #extra>
      <button v-if="available && can('supervisor free contract', 'create')" class="btn btn-primary" @click="router.get('/scheme/supervisor-free-contract/create')">
        <i class="fa fa-plus me-1"></i> {{ t.create_supervisor_free_contract }}
      </button>
    </template>
  </BasePageHeading>

  <div class="content">
    <div v-if="!available" class="alert alert-warning d-flex align-items-center" role="alert">
      <i class="fa fa-triangle-exclamation me-2"></i>
      <div>
        Legacy tables <code>supervisor_foc</code>, <code>supervisor_foc_detail</code>, and
        <code>supervisor_foc_balance</code> are required. Run <code>php artisan migrate</code> to create them.
      </div>
    </div>

    <BaseBlock :title="t.supervisor_free_contract_overview">
      <template #options>
        <div class="d-flex gap-2">
          <input v-model="search" type="text" class="form-control form-control-sm" :placeholder="t.search" style="width:220px" />
          <select v-model="perPage" class="form-select form-select-sm" style="width:90px">
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
              <th>#</th>
              <th>{{ t.contract_id }}</th>
              <th>{{ t.supervisor }}</th>
              <th>{{ t.start_date }}</th>
              <th>{{ t.end_date }}</th>
              <th>{{ t.active }}</th>
              <th>{{ t.status }}</th>
              <th class="text-center" style="width:130px">{{ t.actions }}</th>
            </tr>
          </thead>
          <tbody>
            <tr v-if="!rows.length">
              <td colspan="8" class="text-center text-muted py-4">{{ t.no_records_found }}</td>
            </tr>
            <tr v-for="(r, i) in rows" :key="r.contractid">
              <td class="text-muted">{{ (contracts.from ?? 1) + i }}</td>
              <td class="fw-semibold">{{ r.contractid }}</td>
              <td>
                <div class="fw-semibold">{{ r.supervisorname || "-" }}</div>
                <div class="text-muted fs-xs">{{ r.supervisorcode }}</div>
              </td>
              <td>{{ formatDate(r.startdate) }}</td>
              <td>{{ formatDate(r.enddate) }}</td>
              <td>
                <span class="badge" :class="r.active ? 'bg-success-subtle text-success' : 'bg-danger-subtle text-danger'">
                  {{ r.active ? t.status_active : t.status_inactive }}
                </span>
              </td>
              <td>
                <span class="badge" :class="statusClass(r.contract_status)">{{ r.contract_status || "-" }}</span>
              </td>
              <td class="text-center text-nowrap">
                <button v-if="can('supervisor free contract', 'view')" class="btn btn-sm btn-alt-info me-1" @click="router.get(`/scheme/supervisor-free-contract/${r.contractid}`)">
                  <i class="fa fa-eye"></i>
                </button>
                <button v-if="can('supervisor free contract', 'edit')" class="btn btn-sm btn-alt-secondary me-1" @click="router.get(`/scheme/supervisor-free-contract/${r.contractid}/edit`)">
                  <i class="fa fa-pen"></i>
                </button>
                <button v-if="can('supervisor free contract', 'delete')" class="btn btn-sm btn-alt-danger" @click="confirmingDelete = r">
                  <i class="fa fa-trash"></i>
                </button>
              </td>
            </tr>
          </tbody>
        </table>
      </div>

      <div class="d-flex justify-content-between align-items-center pt-3">
        <div class="text-muted fs-sm">Showing {{ contracts.from ?? 0 }} to {{ contracts.to ?? 0 }} of {{ contracts.total ?? 0 }}</div>
        <div class="btn-group">
          <button class="btn btn-sm btn-alt-secondary" :disabled="!contracts.prev_page_url" @click="reloadList((contracts.current_page || 1) - 1)">Previous</button>
          <button class="btn btn-sm btn-alt-secondary" disabled>{{ contracts.current_page || 1 }} / {{ contracts.last_page || 1 }}</button>
          <button class="btn btn-sm btn-alt-secondary" :disabled="!contracts.next_page_url" @click="reloadList((contracts.current_page || 1) + 1)">Next</button>
        </div>
      </div>
    </BaseBlock>
  </div>

  <!-- Delete Confirm Modal -->
  <div v-if="confirmingDelete" class="modal fade show d-block" style="background:rgba(0,0,0,.45)">
    <div class="modal-dialog modal-dialog-centered modal-sm">
      <div class="modal-content">
        <div class="modal-header">
          <h5 class="modal-title text-danger"><i class="fa fa-triangle-exclamation me-1"></i> Delete</h5>
          <button class="btn-close" @click="confirmingDelete = null"></button>
        </div>
        <div class="modal-body">Delete contract <strong>#{{ confirmingDelete.contractid }}</strong>?</div>
        <div class="modal-footer">
          <button class="btn btn-alt-secondary" @click="confirmingDelete = null">{{ t.cancel }}</button>
          <button class="btn btn-danger" @click="deleteRow">{{ t.delete }}</button>
        </div>
      </div>
    </div>
  </div>
</template>
