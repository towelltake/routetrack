<script setup>
import { computed, ref, watch } from "vue";
import { Head, router, useForm, usePage } from "@inertiajs/vue3";
import { usePermissions } from "@/composables/usePermissions";

const props = defineProps({
  available: { type: Boolean, default: false },
  records: { type: Object, required: true },
  filters: { type: Object, default: () => ({}) },
  formMeta: { type: Object, required: true },
  initialInstructionData: { type: Object, required: true },
});

const search = ref(props.filters?.search ?? "");
const perPage = ref(props.filters?.per_page ?? 10);
const activeModal = ref(null);
const editingId = ref(null);
const confirmingDelete = ref(null);
const rows = computed(() => props.records?.data ?? []);
const { can } = usePermissions();
const page = usePage();
const t = usePage().props.translations.ui;

const form = useForm({
  posinstructioncode: props.initialInstructionData.posinstructioncode ?? "",
  alternatecode: "",
  posinstructionname: "",
  arbposinstructionname: "",
});

function canViewAction(permission) {
  const details = page.props.auth?.formPermissions?.[permission];
  return !!(details?.all || details?.view);
}

let searchDebounce = null;
watch(search, () => {
  clearTimeout(searchDebounce);
  searchDebounce = setTimeout(() => reloadList(), 300);
});
watch(perPage, () => reloadList());

function reloadList(pageNumber = 1) {
  router.get(
    props.formMeta.indexUrl,
    { search: search.value || undefined, per_page: perPage.value, page: pageNumber },
    { preserveScroll: true, preserveState: true, replace: true, only: ["records", "filters", "available", "initialInstructionData"] },
  );
}

function fillForm(record) {
  form.posinstructioncode = record.posinstructioncode ?? "";
  form.alternatecode = record.alternatecode ?? "";
  form.posinstructionname = record.posinstructionname ?? "";
  form.arbposinstructionname = record.arbposinstructionname ?? "";
}

function resetForm() {
  form.reset();
  form.posinstructioncode = props.initialInstructionData.posinstructioncode ?? "";
  form.clearErrors();
}

function openCreate() {
  editingId.value = null;
  resetForm();
  activeModal.value = "create";
}

function openView(record) {
  editingId.value = record.posinstructioncode;
  form.reset();
  fillForm(record);
  form.clearErrors();
  activeModal.value = "view";
}

function openEdit(record) {
  editingId.value = record.posinstructioncode;
  form.reset();
  fillForm(record);
  form.clearErrors();
  activeModal.value = "edit";
}

function closeModal() {
  activeModal.value = null;
  editingId.value = null;
  form.reset();
  form.clearErrors();
}

function submitCreate() {
  form.post(props.formMeta.baseUrl, {
    preserveScroll: true,
    onSuccess: () => {
      closeModal();
      reloadList();
    },
  });
}

function submitEdit() {
  form.put(`${props.formMeta.baseUrl}/${editingId.value}`, {
    preserveScroll: true,
    onSuccess: () => {
      closeModal();
      reloadList(props.records.current_page || 1);
    },
  });
}

function deleteRow() {
  router.delete(`${props.formMeta.baseUrl}/${confirmingDelete.value.posinstructioncode}`, {
    preserveScroll: true,
    onSuccess: () => {
      confirmingDelete.value = null;
    },
  });
}
</script>

<template>
  <Head :title="t.pos_instruction" />

  <BasePageHeading :title="t.pos_instruction" :subtitle="t.pos_instruction_note">
    <template #extra>
      <button
        v-if="available && can('pos instruction', 'create')"
        class="btn btn-primary"
        @click="openCreate"
      >
        <i class="fa fa-plus me-1"></i> {{ t.add }}
      </button>
    </template>
  </BasePageHeading>

  <div class="content">
    <div v-if="!available" class="alert alert-warning d-flex align-items-center" role="alert">
      <i class="fa fa-triangle-exclamation me-2"></i>
      <div>{{ t.legacy_pos_instruction_table_required }}</div>
    </div>

    <BaseBlock :title="t.pos_instruction_overview">
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
              <th>{{ t.code }}</th>
              <th>{{ t.name }}</th>
              <th>{{ t.arabic_name }}</th>
              <th class="text-center" style="width:140px">{{ t.actions }}</th>
            </tr>
          </thead>
          <tbody>
            <tr v-if="!rows.length">
              <td colspan="5" class="text-center text-muted py-4">{{ t.no_pos_instructions_found }}</td>
            </tr>
            <tr v-for="(record, index) in rows" :key="record.posinstructioncode">
              <td class="text-muted">{{ (records.from ?? 1) + index }}</td>
              <td>{{ record.posinstructioncode }}</td>
              <td class="fw-semibold">{{ record.posinstructionname }}</td>
              <td>{{ record.arbposinstructionname || "-" }}</td>
              <td class="text-center text-nowrap">
                <button v-if="canViewAction('pos instruction')" class="btn btn-sm btn-alt-info me-1" @click="openView(record)">
                  <i class="fa fa-eye"></i>
                </button>
                <button v-if="can('pos instruction', 'edit')" class="btn btn-sm btn-alt-secondary me-1" @click="openEdit(record)">
                  <i class="fa fa-pen"></i>
                </button>
                <button v-if="can('pos instruction', 'delete')" class="btn btn-sm btn-alt-danger" @click="confirmingDelete = record">
                  <i class="fa fa-trash"></i>
                </button>
              </td>
            </tr>
          </tbody>
        </table>
      </div>

      <div class="d-flex justify-content-between align-items-center pt-3">
        <div class="text-muted fs-sm">{{ t.showing }} {{ records.from ?? 0 }} {{ t.to }} {{ records.to ?? 0 }} {{ t.of }} {{ records.total ?? 0 }}</div>
        <div class="btn-group">
          <button class="btn btn-sm btn-alt-secondary" :disabled="!records.prev_page_url" @click="reloadList((records.current_page || 1) - 1)">{{ t.previous }}</button>
          <button class="btn btn-sm btn-alt-secondary" disabled>{{ records.current_page || 1 }} / {{ records.last_page || 1 }}</button>
          <button class="btn btn-sm btn-alt-secondary" :disabled="!records.next_page_url" @click="reloadList((records.current_page || 1) + 1)">{{ t.next }}</button>
        </div>
      </div>
    </BaseBlock>
  </div>

  <div v-if="activeModal" class="modal fade show d-block" style="background: rgba(0,0,0,.45)">
    <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
      <div class="modal-content">
        <div class="modal-header">
          <h5 class="modal-title">
            {{
              activeModal === "create"
                ? t.create_pos_instruction
                : activeModal === "view"
                  ? t.view_pos_instruction
                  : t.edit_pos_instruction
            }}
          </h5>
          <button class="btn-close" @click="closeModal"></button>
        </div>
        <div class="modal-body">
          <div class="row g-4 mb-4">
            <div class="col-md-4">
              <label class="form-label">{{ t.code }}</label>
              <input v-model="form.posinstructioncode" class="form-control" readonly />
            </div>
            <div class="col-md-8">
              <label class="form-label">{{ t.alternate_code }}</label>
              <input v-model="form.alternatecode" maxlength="50" class="form-control" :readonly="activeModal === 'view'" />
              <div v-if="form.errors.alternatecode" class="text-danger fs-sm mt-1">{{ form.errors.alternatecode }}</div>
            </div>
          </div>

          <div class="row g-4 mb-3">
            <div class="col-md-6">
              <label class="form-label">{{ t.name }} <span class="text-danger">*</span></label>
              <input v-model="form.posinstructionname" maxlength="50" class="form-control" :readonly="activeModal === 'view'" />
              <div v-if="form.errors.posinstructionname" class="text-danger fs-sm mt-1">{{ form.errors.posinstructionname }}</div>
            </div>
            <div class="col-md-6">
              <label class="form-label">{{ t.arabic_name }}</label>
              <input v-model="form.arbposinstructionname" maxlength="50" class="form-control" dir="rtl" :readonly="activeModal === 'view'" />
              <div v-if="form.errors.arbposinstructionname" class="text-danger fs-sm mt-1">{{ form.errors.arbposinstructionname }}</div>
            </div>
          </div>

        </div>
        <div class="modal-footer">
          <button class="btn btn-alt-secondary" @click="closeModal">{{ t.close }}</button>
          <button
            v-if="activeModal === 'create'"
            class="btn btn-primary"
            :disabled="form.processing"
            @click="submitCreate"
          >
            <i class="fa fa-floppy-disk me-1"></i> {{ form.processing ? t.saving : t.save }}
          </button>
          <button
            v-else-if="activeModal === 'edit'"
            class="btn btn-primary"
            :disabled="form.processing"
            @click="submitEdit"
          >
            <i class="fa fa-floppy-disk me-1"></i> {{ form.processing ? t.saving : t.save }}
          </button>
        </div>
      </div>
    </div>
  </div>

  <div v-if="confirmingDelete" class="modal fade show d-block" style="background: rgba(0,0,0,.45)">
    <div class="modal-dialog modal-dialog-centered modal-sm">
      <div class="modal-content">
        <div class="modal-header">
          <h5 class="modal-title text-danger"><i class="fa fa-triangle-exclamation me-1"></i> {{ t.delete }}</h5>
          <button class="btn-close" @click="confirmingDelete = null"></button>
        </div>
        <div class="modal-body">{{ t.delete_pos_instruction_confirm.replace(':name', confirmingDelete.posinstructionname) }}</div>
        <div class="modal-footer">
          <button class="btn btn-alt-secondary" @click="confirmingDelete = null">{{ t.cancel }}</button>
          <button class="btn btn-danger" @click="deleteRow">{{ t.delete }}</button>
        </div>
      </div>
    </div>
  </div>
</template>
