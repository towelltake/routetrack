<script setup>
import { computed, ref, watch } from "vue";
import { Head, router, useForm, usePage } from "@inertiajs/vue3";
import { usePermissions } from "@/composables/usePermissions";
import CreateSupervisor from "./Create.vue";
import EditSupervisor from "./Edit.vue";
import ViewSupervisor from "./View.vue";

const props = defineProps({
  supervisors: Object,
  companies: Array,
  filters: Object,
});

const search = ref(props.filters?.search ?? "");
const perPage = ref(props.filters?.per_page ?? 10);
const rows = computed(() => props.supervisors?.data ?? []);
const activeModal = ref(null);
const editingId = ref(null);
const currentRecord = ref(null);
const confirmingDelete = ref(null);
const { can } = usePermissions();
const page = usePage();
const t = page.props.translations.ui;

const form = useForm({
  alternatesupervisorcode: "",
  parentcompany: null,
  supervisorname: "",
  arbsupervisorname: "",
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
    "/basic/supervisor",
    {
      search: search.value || undefined,
      per_page: perPage.value,
      page: pageNumber,
    },
    {
      preserveScroll: true,
      preserveState: true,
      replace: true,
      only: ["supervisors", "filters"],
    },
  );
}

function fillForm(record) {
  form.alternatesupervisorcode = record.alternatesupervisorcode ?? "";
  form.parentcompany = record.parentcompany ?? null;
  form.supervisorname = record.supervisorname ?? "";
  form.arbsupervisorname = record.arbsupervisorname ?? "";
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
  editingId.value = record.supervisorcode;
  currentRecord.value = record;
  resetForm();
  fillForm(record);
  activeModal.value = "view";
}

function openEdit(record) {
  editingId.value = record.supervisorcode;
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
  form.post("/basic/supervisor", {
    preserveScroll: true,
    onSuccess: closeModal,
  });
}

function submitEdit() {
  form.put(`/basic/supervisor/${editingId.value}`, {
    preserveScroll: true,
    onSuccess: closeModal,
  });
}

function deleteRow() {
  router.delete(`/basic/supervisor/${confirmingDelete.value.supervisorcode}`, {
    preserveScroll: true,
    onSuccess: () => {
      confirmingDelete.value = null;
    },
  });
}
</script>

<template>
  <Head :title="t.supervisor" />

  <BasePageHeading :title="t.supervisor" :subtitle="t.supervisor_note">
    <template #extra>
      <button
        v-if="can('supervisor', 'create')"
        class="btn btn-primary"
        @click="openCreate"
      >
        <i class="fa fa-plus me-1"></i>
        {{ t.add_supervisor }}
      </button>
    </template>
  </BasePageHeading>

  <div class="content">
    <BaseBlock :title="t.supervisor_list">
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
              <th>{{ t.supervisor }}</th>
              <th>{{ t.parent_company_label }}</th>
              <th>{{ t.status }}</th>
              <th class="text-center" style="width: 140px">{{ t.actions }}</th>
            </tr>
          </thead>
          <tbody>
            <tr v-if="!rows.length">
              <td colspan="6" class="text-center text-muted py-4">{{ t.no_records_found }}</td>
            </tr>
            <tr v-for="(record, index) in rows" :key="record.supervisorcode">
              <td class="text-muted">{{ (supervisors.from ?? 1) + index }}</td>
              <td>{{ record.supervisorcode }}</td>
              <td class="fw-semibold">{{ record.supervisorname }}</td>
              <td>{{ record.parentcompanyname || companyName(record.parentcompany) }}</td>
              <td>
                <span class="badge" :class="record.activestatus ? 'bg-success' : 'bg-secondary'">
                  {{ record.activestatus ? t.status_active : t.status_inactive }}
                </span>
              </td>
              <td class="text-center text-nowrap">
                <button
                  v-if="canViewAction('supervisor')"
                  class="btn btn-sm btn-alt-info me-1"
                  :title="t.view"
                  @click="openView(record)"
                >
                  <i class="fa fa-eye"></i>
                </button>
                <button
                  v-if="can('supervisor', 'edit')"
                  class="btn btn-sm btn-alt-secondary me-1"
                  :title="t.edit"
                  @click="openEdit(record)"
                >
                  <i class="fa fa-pen"></i>
                </button>
                <button
                  v-if="can('supervisor', 'delete')"
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
          {{ t.showing }} {{ supervisors.from ?? 0 }} {{ t.to }} {{ supervisors.to ?? 0 }} {{ t.of }} {{ supervisors.total ?? 0 }}
        </div>
        <div class="btn-group">
          <button
            class="btn btn-sm btn-alt-secondary"
            :disabled="!supervisors.prev_page_url"
            @click="reloadList((supervisors.current_page || 1) - 1)"
          >
            {{ t.previous }}
          </button>
          <button class="btn btn-sm btn-alt-secondary" disabled>
            {{ supervisors.current_page || 1 }} / {{ supervisors.last_page || 1 }}
          </button>
          <button
            class="btn btn-sm btn-alt-secondary"
            :disabled="!supervisors.next_page_url"
            @click="reloadList((supervisors.current_page || 1) + 1)"
          >
            {{ t.next }}
          </button>
        </div>
      </div>
    </BaseBlock>
  </div>

  <CreateSupervisor
    v-if="activeModal === 'create'"
    :form="form"
    :companies="companies"
    @close="closeModal"
    @submit="submitCreate"
  />

  <ViewSupervisor
    v-if="activeModal === 'view'"
    :form="form"
    :companies="companies"
    :record="currentRecord"
    @close="closeModal"
  />

  <EditSupervisor
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
          {{ t.delete_supervisor_label }} <strong>{{ confirmingDelete.supervisorname }}</strong>?
        </div>
        <div class="modal-footer">
          <button class="btn btn-alt-secondary" @click="confirmingDelete = null">{{ t.cancel }}</button>
          <button class="btn btn-danger" @click="deleteRow">{{ t.delete }}</button>
        </div>
      </div>
    </div>
  </div>
</template>
