<script setup>
import { computed, ref, watch } from "vue";
import { Head, router, useForm, usePage } from "@inertiajs/vue3";
import { usePermissions } from "@/composables/usePermissions";
import CreateBranchManager from "./Create.vue";
import EditBranchManager from "./Edit.vue";
import ViewBranchManager from "./View.vue";

const props = defineProps({
  managers: Object,
  companies: Array,
  filters: Object,
});

const search = ref(props.filters?.search ?? "");
const perPage = ref(props.filters?.per_page ?? 10);
const rows = computed(() => props.managers?.data ?? []);
const activeModal = ref(null);
const editingId = ref(null);
const currentRecord = ref(null);
const confirmingDelete = ref(null);
const { can } = usePermissions();
const page = usePage();
const t = page.props.translations.ui;

const form = useForm({
  alternatebranchmanagercode: "",
  parentcompany: null,
  branchmanagername: "",
  arbbranchmanagername: "",
  activestatus: 1,
});

function canViewAction(permission) {
  const details = page.props.auth?.formPermissions?.[permission];
  return !!(details?.all || details?.view);
}

function companyName(id, fallback = "-") {
  return props.companies.find((company) => company.cmpycode === id)?.name ?? fallback;
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
    "/basic/branchmanager",
    {
      search: search.value || undefined,
      per_page: perPage.value,
      page: pageNumber,
    },
    {
      preserveScroll: true,
      preserveState: true,
      replace: true,
      only: ["managers", "filters"],
    },
  );
}

function fillForm(record) {
  form.alternatebranchmanagercode = record.alternatebranchmanagercode ?? "";
  form.parentcompany = record.parentcompany ?? null;
  form.branchmanagername = record.branchmanagername ?? "";
  form.arbbranchmanagername = record.arbbranchmanagername ?? "";
  form.activestatus = record.activestatus ?? 1;
}

function resetForm() {
  form.reset();
  form.parentcompany = null;
  form.activestatus = 1;
  form.clearErrors();
}

function openCreate() {
  editingId.value = null;
  currentRecord.value = null;
  resetForm();
  activeModal.value = "create";
}

function openView(record) {
  editingId.value = record.branchmanagercode;
  currentRecord.value = record;
  resetForm();
  fillForm(record);
  activeModal.value = "view";
}

function openEdit(record) {
  editingId.value = record.branchmanagercode;
  currentRecord.value = record;
  resetForm();
  fillForm(record);
  activeModal.value = "edit";
}

function closeModal() {
  activeModal.value = null;
  editingId.value = null;
  currentRecord.value = null;
  resetForm();
}

function submitCreate() {
  form.post("/basic/branchmanager", {
    preserveScroll: true,
    onSuccess: closeModal,
  });
}

function submitEdit() {
  form.put(`/basic/branchmanager/${editingId.value}`, {
    preserveScroll: true,
    onSuccess: closeModal,
  });
}

function deleteRow() {
  router.delete(`/basic/branchmanager/${confirmingDelete.value.branchmanagercode}`, {
    preserveScroll: true,
    onSuccess: () => {
      confirmingDelete.value = null;
    },
  });
}
</script>

<template>
  <Head :title="t.branch_depot_manager" />

  <BasePageHeading :title="t.branch_depot_manager" :subtitle="t.branch_depot_manager_note">
    <template #extra>
      <button
        v-if="can('branch/depot manager', 'create')"
        class="btn btn-primary"
        @click="openCreate"
      >
        <i class="fa fa-plus me-1"></i>
        {{ t.add_branch_depot_manager }}
      </button>
    </template>
  </BasePageHeading>

  <div class="content">
    <BaseBlock :title="t.branch_depot_manager_list">
      <template #options>
        <div class="d-flex gap-2">
          <input
            v-model="search"
            type="text"
            class="form-control form-control-sm"
            :placeholder="t.search"
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
              <th>#</th>
              <th>{{ t.code }}</th>
              <th>{{ t.branch_depot_manager }}</th>
              <th>{{ t.parent_company_label }}</th>
              <th>{{ t.status }}</th>
              <th class="text-center" style="width: 140px">{{ t.actions }}</th>
            </tr>
          </thead>
          <tbody>
            <tr v-if="!rows.length">
              <td colspan="6" class="text-center text-muted py-4">{{ t.no_records_found }}</td>
            </tr>
            <tr v-for="(record, index) in rows" :key="record.branchmanagercode">
              <td class="text-muted">{{ (managers.from ?? 1) + index }}</td>
              <td>{{ record.branchmanagercode }}</td>
              <td class="fw-semibold">{{ record.branchmanagername }}</td>
              <td>{{ record.parentcompanyname || companyName(record.parentcompany) }}</td>
              <td>
                <span class="badge" :class="record.activestatus ? 'bg-success' : 'bg-secondary'">
                  {{ record.activestatus ? t.status_active : t.status_inactive }}
                </span>
              </td>
              <td class="text-center text-nowrap">
                <button
                  v-if="canViewAction('branch/depot manager')"
                  class="btn btn-sm btn-alt-info me-1"
                  :title="t.view"
                  @click="openView(record)"
                >
                  <i class="fa fa-eye"></i>
                </button>
                <button
                  v-if="can('branch/depot manager', 'edit')"
                  class="btn btn-sm btn-alt-secondary me-1"
                  :title="t.edit"
                  @click="openEdit(record)"
                >
                  <i class="fa fa-pen"></i>
                </button>
                <button
                  v-if="can('branch/depot manager', 'delete')"
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
          {{ t.showing }} {{ managers.from ?? 0 }} {{ t.to }} {{ managers.to ?? 0 }} {{ t.of }} {{ managers.total ?? 0 }}
        </div>
        <div class="btn-group">
          <button
            class="btn btn-sm btn-alt-secondary"
            :disabled="!managers.prev_page_url"
            @click="reloadList((managers.current_page || 1) - 1)"
          >
            {{ t.previous }}
          </button>
          <button class="btn btn-sm btn-alt-secondary" disabled>
            {{ managers.current_page || 1 }} / {{ managers.last_page || 1 }}
          </button>
          <button
            class="btn btn-sm btn-alt-secondary"
            :disabled="!managers.next_page_url"
            @click="reloadList((managers.current_page || 1) + 1)"
          >
            {{ t.next }}
          </button>
        </div>
      </div>
    </BaseBlock>
  </div>

  <CreateBranchManager
    v-if="activeModal === 'create'"
    :form="form"
    :companies="companies"
    @close="closeModal"
    @submit="submitCreate"
  />

  <ViewBranchManager
    v-if="activeModal === 'view'"
    :form="form"
    :companies="companies"
    :record="currentRecord"
    @close="closeModal"
  />

  <EditBranchManager
    v-if="activeModal === 'edit'"
    :form="form"
    :companies="companies"
    @close="closeModal"
    @submit="submitEdit"
  />

  <div
    v-if="confirmingDelete"
    class="modal fade show d-block"
    style="background: rgba(0, 0, 0, 0.45)"
  >
    <div class="modal-dialog modal-dialog-centered modal-sm">
      <div class="modal-content">
        <div class="modal-header">
          <h5 class="modal-title text-danger">
            <i class="fa fa-triangle-exclamation me-1"></i>
            {{ t.delete }}
          </h5>
          <button class="btn-close" @click="confirmingDelete = null"></button>
        </div>
        <div class="modal-body">
          {{ t.delete_branch_depot_manager_label }} <strong>{{ confirmingDelete.branchmanagername }}</strong>?
        </div>
        <div class="modal-footer">
          <button class="btn btn-alt-secondary" @click="confirmingDelete = null">{{ t.cancel }}</button>
          <button class="btn btn-danger" @click="deleteRow">{{ t.delete }}</button>
        </div>
      </div>
    </div>
  </div>
</template>
