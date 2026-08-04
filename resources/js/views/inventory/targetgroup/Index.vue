<script setup>
import { computed, ref, watch } from "vue";
import { Head, router, useForm, usePage } from "@inertiajs/vue3";
import { usePermissions } from "@/composables/usePermissions";

const props = defineProps({
  records: { type: Object, required: true },
  filters: { type: Object, default: () => ({}) },
  workflowMeta: { type: Object, required: true },
});

const page = usePage();
const t = page.props.translations.ui;
const { can } = usePermissions();

const rows = computed(() => props.records?.data ?? []);
const search = ref(props.filters?.search ?? "");
const perPage = ref(props.filters?.per_page ?? 10);

const showModal = ref(false);
const isEditing = ref(false);
const isViewing = ref(false);
const editingId = ref(null);
const confirmingDelete = ref(null);

const form = useForm({
  packagecode: "",
  alternatecode: "",
  packagedescription: "",
  arbpackagedescription: "",
  activestatus: 1,
});

let searchDebounce = null;
watch(search, () => {
  clearTimeout(searchDebounce);
  searchDebounce = setTimeout(() => reloadList(), 300);
});

watch(perPage, () => reloadList());

function reloadList(pageNumber = 1) {
  router.get(props.workflowMeta.indexUrl, {
    search: search.value || undefined,
    per_page: perPage.value,
    page: pageNumber,
  }, {
    preserveScroll: true,
    preserveState: true,
    replace: true,
    only: ["records", "filters", "workflowMeta"],
  });
}

function openAdd() {
  isEditing.value = false;
  isViewing.value = false;
  editingId.value = null;
  form.reset();
  form.packagecode = "";
  form.activestatus = 1;
  form.clearErrors();
  showModal.value = true;
}

function openView(record) {
  populateForm(record);
  isEditing.value = false;
  isViewing.value = true;
  showModal.value = true;
}

function openEdit(record) {
  populateForm(record);
  isEditing.value = true;
  isViewing.value = false;
  showModal.value = true;
}

function populateForm(record) {
  editingId.value = record.packagecode;
  form.packagecode = record.packagecode ?? "";
  form.alternatecode = record.alternatecode ?? "";
  form.packagedescription = record.packagedescription ?? "";
  form.arbpackagedescription = record.arbpackagedescription ?? "";
  form.activestatus = record.activestatus ?? 1;
  form.clearErrors();
}

function closeModal() {
  showModal.value = false;
  isEditing.value = false;
  isViewing.value = false;
  editingId.value = null;
  form.reset();
  form.clearErrors();
}

function submit() {
  if (isViewing.value) {
    return;
  }

  const options = {
    preserveScroll: true,
    onSuccess: closeModal,
  };

  if (isEditing.value) {
    form.put(`${props.workflowMeta.baseUrl}/${editingId.value}`, options);
    return;
  }

  form.post(props.workflowMeta.baseUrl, options);
}

function deleteRow() {
  router.delete(`${props.workflowMeta.baseUrl}/${confirmingDelete.value.packagecode}`, {
    preserveScroll: true,
    onSuccess: () => {
      confirmingDelete.value = null;
    },
  });
}

function canViewAction(permission) {
  const details = page.props.auth?.formPermissions?.[permission];
  return !!(details?.all || details?.view);
}
</script>

<template>
  <Head :title="t.target_group" />

  <BasePageHeading :title="t.target_group" :subtitle="t.target_group_note">
    <template #extra>
      <button v-if="can('target group', 'create')" class="btn btn-primary" @click="openAdd">
        <i class="fa fa-plus me-1"></i> {{ t.create_target_group }}
      </button>
    </template>
  </BasePageHeading>

  <div class="content">
    <BaseBlock :title="t.target_group_list">
      <template #options>
        <div class="d-flex gap-2">
          <input v-model="search" type="text" class="form-control form-control-sm" :placeholder="`${t.search}...`" style="width: 220px" />
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
              <th style="width: 90px">{{ t.group_id }}</th>
              <th v-if="workflowMeta.useAlternateCode" style="width: 140px">{{ t.alternate_code }}</th>
              <th>{{ t.description }}</th>
              <th>{{ t.status }}</th>
              <th class="text-center" style="width: 150px">{{ t.actions }}</th>
            </tr>
          </thead>
          <tbody>
            <tr v-if="!rows.length">
              <td :colspan="workflowMeta.useAlternateCode ? 5 : 4" class="text-center text-muted py-4">{{ t.no_records_found }}</td>
            </tr>
            <tr v-for="record in rows" :key="record.packagecode">
              <td class="fw-semibold">{{ record.packagecode }}</td>
              <td v-if="workflowMeta.useAlternateCode">{{ record.alternatecode || "-" }}</td>
              <td>
                <div>{{ record.packagedescription }}</div>
                <div class="text-muted">{{ record.arbpackagedescription || "-" }}</div>
              </td>
              <td>
                <span class="badge" :class="record.activestatus === 1 ? 'bg-success-light text-success' : 'bg-danger-light text-danger'">
                  {{ record.activestatus === 1 ? t.status_active : t.status_inactive }}
                </span>
              </td>
              <td class="text-center text-nowrap">
                <button
                  v-if="canViewAction('target group')"
                  class="btn btn-sm btn-alt-info me-1"
                  @click="openView(record)"
                >
                  <i class="fa fa-eye"></i>
                </button>
                <button
                  v-if="can('target group', 'edit')"
                  class="btn btn-sm btn-alt-secondary me-1"
                  @click="openEdit(record)"
                >
                  <i class="fa fa-pen"></i>
                </button>
                <button
                  v-if="can('target group', 'delete')"
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

  <div
    v-if="showModal"
    class="modal fade show d-block"
    style="background: rgba(0, 0, 0, 0.45)"
  >
    <div class="modal-dialog modal-dialog-centered modal-md">
      <div class="modal-content">
        <div class="modal-header">
          <h5 class="modal-title">
            {{ isViewing ? t.view_target_group : (isEditing ? t.edit_target_group : t.create_target_group) }}
          </h5>
          <button class="btn-close" @click="closeModal"></button>
        </div>
        <form @submit.prevent="submit">
          <div class="modal-body">
            <div v-if="isEditing || isViewing" class="mb-3">
              <label class="form-label">{{ t.code }}</label>
              <input v-model="form.packagecode" class="form-control" readonly />
            </div>

            <div class="mb-3">
              <label class="form-label">{{ t.alternate_code }}</label>
              <input v-model="form.alternatecode" class="form-control" :disabled="isViewing" />
              <div v-if="form.errors.alternatecode" class="text-danger fs-sm mt-1">{{ form.errors.alternatecode }}</div>
            </div>

            <div class="mb-3">
              <label class="form-label">{{ t.description }} <span class="text-danger">*</span></label>
              <input v-model="form.packagedescription" class="form-control" :disabled="isViewing" />
              <div v-if="form.errors.packagedescription" class="text-danger fs-sm mt-1">{{ form.errors.packagedescription }}</div>
            </div>

            <div class="mb-3">
              <label class="form-label">{{ t.arab_description }}</label>
              <input v-model="form.arbpackagedescription" class="form-control" dir="rtl" :disabled="isViewing" />
              <div v-if="form.errors.arbpackagedescription" class="text-danger fs-sm mt-1">{{ form.errors.arbpackagedescription }}</div>
            </div>

            <div v-if="isEditing" class="mb-3">
              <label class="form-label">{{ t.status }}</label>
              <select v-model="form.activestatus" class="form-select">
                <option :value="1">{{ t.status_active }}</option>
                <option :value="0">{{ t.status_inactive }}</option>
              </select>
              <div v-if="form.errors.activestatus" class="text-danger fs-sm mt-1">{{ form.errors.activestatus }}</div>
            </div>

          </div>

          <div class="modal-footer">
            <button type="button" class="btn btn-alt-secondary" @click="closeModal">{{ t.cancel }}</button>
            <button v-if="!isViewing" type="submit" class="btn btn-primary" :disabled="form.processing">
              {{ isEditing ? t.update : t.save }}
            </button>
          </div>
        </form>
      </div>
    </div>
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
            <i class="fa fa-triangle-exclamation me-1"></i> {{ t.delete_target_group_label }}
          </h5>
          <button class="btn-close" @click="confirmingDelete = null"></button>
        </div>
        <div class="modal-body">
          {{ t.target_group_delete_confirm }}
        </div>
        <div class="modal-footer">
          <button class="btn btn-alt-secondary" @click="confirmingDelete = null">{{ t.cancel }}</button>
          <button class="btn btn-danger" @click="deleteRow">{{ t.delete_target_group_label }}</button>
        </div>
      </div>
    </div>
  </div>
</template>
