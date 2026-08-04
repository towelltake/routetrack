<script setup>
import { computed, ref, watch } from "vue";
import { Head, router, useForm, usePage } from "@inertiajs/vue3";
import { usePermissions } from "@/composables/usePermissions";
import CreateDepot from "./Create.vue";
import EditDepot from "./Edit.vue";
import ViewDepot from "./View.vue";

const props = defineProps({
  depots: Object,
  filters: Object,
  branchManagers: Array,
  companies: Array,
  regions: Array,
  pricingKeys: Array,
});

const search = ref(props.filters?.search ?? "");
const perPage = ref(props.filters?.per_page ?? 10);
const rows = computed(() => props.depots?.data ?? []);
const activeModal = ref(null);
const editingId = ref(null);
const confirmingDelete = ref(null);
const { can } = usePermissions();
const page = usePage();
const t = page.props.translations.ui;

const form = useForm({
  alternatedepotcode: "",
  depotname: "",
  arbdepotname: "",
  branchmanagercode: null,
  cmpycode: null,
  regionmstcode: null,
  pricingkey: null,
  depotprefix: 0,
  centralwh: 0,
  activestatus: 1,
});

function canViewAction(permission) {
  const details = page.props.auth?.formPermissions?.[permission];
  return !!(details?.all || details?.view);
}

let searchDebounce = null;
watch(search, () => {
  clearTimeout(searchDebounce);
  searchDebounce = setTimeout(() => {
    reloadList();
  }, 300);
});

watch(perPage, () => {
  reloadList();
});

function reloadList(pageNumber = 1) {
  router.get(
    "/organisation/depot",
    {
      search: search.value || undefined,
      per_page: perPage.value,
      page: pageNumber,
    },
    {
      preserveScroll: true,
      preserveState: true,
      replace: true,
      only: ["depots", "filters"],
    },
  );
}

function fillForm(record) {
  form.alternatedepotcode = record.alternatedepotcode ?? "";
  form.depotname = record.depotname ?? "";
  form.arbdepotname = record.arbdepotname ?? "";
  form.branchmanagercode = record.branchmanagercode ?? null;
  form.cmpycode = record.cmpycode ?? null;
  form.regionmstcode = record.regionmstcode ?? null;
  form.pricingkey = record.pricingkey ?? null;
  form.depotprefix = record.depotprefix ?? 0;
  form.centralwh = record.centralwh ?? 0;
  form.activestatus = record.activestatus ?? 1;
}

function resetForm() {
  form.reset();
  form.depotprefix = 0;
  form.centralwh = 0;
  form.activestatus = 1;
  form.clearErrors();
}

function openCreate() {
  editingId.value = null;
  resetForm();
  activeModal.value = "create";
}

function openView(record) {
  editingId.value = record.depotcode;
  resetForm();
  fillForm(record);
  activeModal.value = "view";
}

function openEdit(record) {
  editingId.value = record.depotcode;
  resetForm();
  fillForm(record);
  activeModal.value = "edit";
}

function closeModal() {
  activeModal.value = null;
  editingId.value = null;
  resetForm();
}

function submitCreate() {
  form.post("/organisation/depot", {
    preserveScroll: true,
    onSuccess: closeModal,
  });
}

function submitEdit() {
  form.put(`/organisation/depot/${editingId.value}`, {
    preserveScroll: true,
    onSuccess: closeModal,
  });
}

function deleteRow() {
  router.delete(`/organisation/depot/${confirmingDelete.value.depotcode}`, {
    preserveScroll: true,
    onSuccess: () => {
      confirmingDelete.value = null;
    },
  });
}
</script>

<template>
  <Head :title="t.depot_master" />

  <BasePageHeading :title="t.depot_master" :subtitle="t.depot_note">
    <template #extra>
      <button v-if="can('depot', 'create')" class="btn btn-primary" @click="openCreate">
        <i class="fa fa-plus me-1"></i> {{ t.add_depot }}
      </button>
    </template>
  </BasePageHeading>

  <div class="content">
    <BaseBlock :title="t.branch_depot_list">
      <template #options>
        <div class="d-flex gap-2">
          <input
            v-model="search"
            type="text"
            class="form-control form-control-sm"
            :placeholder="t.search"
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
              <th>#</th>
              <th>{{ t.code }}</th>
              <th>{{ t.branch_depot_name }}</th>
              <th>{{ t.manager }}</th>
              <th>{{ t.company }}</th>
              <th>{{ t.status }}</th>
              <th class="text-center" style="width: 140px">{{ t.actions }}</th>
            </tr>
          </thead>
          <tbody>
            <tr v-if="!rows.length">
              <td colspan="7" class="text-center text-muted py-4">{{ t.no_records_found }}</td>
            </tr>
            <tr v-for="(record, index) in rows" :key="record.depotcode">
              <td class="text-muted">{{ (depots.from ?? 1) + index }}</td>
              <td>{{ record.alternatedepotcode || record.depotcode }}</td>
              <td class="fw-semibold">{{ record.depotname }}</td>
              <td>{{ record.branchmanagername || "-" }}</td>
              <td>{{ record.companyname || "-" }}</td>
              <td>
                <span class="badge" :class="record.activestatus ? 'bg-success' : 'bg-secondary'">
                  {{ record.activestatus ? t.status_active : t.status_inactive }}
                </span>
              </td>
              <td class="text-center text-nowrap">
                <button
                  v-if="canViewAction('depot')"
                  class="btn btn-sm btn-alt-info me-1"
                  :title="t.view"
                  @click="openView(record)"
                >
                  <i class="fa fa-eye"></i>
                </button>
                <button
                  v-if="can('depot', 'edit')"
                  class="btn btn-sm btn-alt-secondary me-1"
                  :title="t.edit"
                  @click="openEdit(record)"
                >
                  <i class="fa fa-pen"></i>
                </button>
                <button
                  v-if="can('depot', 'delete')"
                  class="btn btn-sm btn-alt-danger"
                  :title="t.delete"
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
          {{ t.showing }} {{ depots.from ?? 0 }} {{ t.to }} {{ depots.to ?? 0 }} {{ t.of }} {{ depots.total ?? 0 }}
        </div>
        <div class="btn-group">
          <button
            class="btn btn-sm btn-alt-secondary"
            :disabled="!depots.prev_page_url"
            @click="reloadList((depots.current_page || 1) - 1)"
          >
            {{ t.previous }}
          </button>
          <button class="btn btn-sm btn-alt-secondary" disabled>
            {{ depots.current_page || 1 }} / {{ depots.last_page || 1 }}
          </button>
          <button
            class="btn btn-sm btn-alt-secondary"
            :disabled="!depots.next_page_url"
            @click="reloadList((depots.current_page || 1) + 1)"
          >
            {{ t.next }}
          </button>
        </div>
      </div>
    </BaseBlock>
  </div>

  <CreateDepot
    v-if="activeModal === 'create'"
    :form="form"
    :branch-managers="branchManagers"
    :companies="companies"
    :regions="regions"
    :pricing-keys="pricingKeys"
    @close="closeModal"
    @submit="submitCreate"
  />

  <ViewDepot
    v-if="activeModal === 'view'"
    :form="form"
    :branch-managers="branchManagers"
    :companies="companies"
    :regions="regions"
    :pricing-keys="pricingKeys"
    @close="closeModal"
  />

  <EditDepot
    v-if="activeModal === 'edit'"
    :form="form"
    :branch-managers="branchManagers"
    :companies="companies"
    :regions="regions"
    :pricing-keys="pricingKeys"
    @close="closeModal"
    @submit="submitEdit"
  />

  <div
    v-if="confirmingDelete"
    class="modal fade show d-block"
    style="background: rgba(0,0,0,.45)"
  >
    <div class="modal-dialog modal-dialog-centered modal-sm">
      <div class="modal-content">
        <div class="modal-header">
          <h5 class="modal-title text-danger">
            <i class="fa fa-triangle-exclamation me-1"></i> {{ t.delete }}
          </h5>
          <button class="btn-close" @click="confirmingDelete = null"></button>
        </div>
        <div class="modal-body">{{ t.delete_depot_label }} <strong>{{ confirmingDelete.depotname }}</strong>?</div>
        <div class="modal-footer">
          <button class="btn btn-alt-secondary" @click="confirmingDelete = null">{{ t.cancel }}</button>
          <button class="btn btn-danger" @click="deleteRow">{{ t.delete }}</button>
        </div>
      </div>
    </div>
  </div>
</template>
