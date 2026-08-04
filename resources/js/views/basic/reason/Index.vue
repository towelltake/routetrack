<script setup>
import { computed, ref, watch } from "vue";
import { Head, router, useForm, usePage } from "@inertiajs/vue3";
import { usePermissions } from "@/composables/usePermissions";
import CreateReason from "./Create.vue";
import EditReason from "./Edit.vue";
import ViewReason from "./View.vue";

const props = defineProps({
  reasons: Object,
  filters: Object,
  types: Object,
});

const search = ref(props.filters?.search ?? "");
const selectedType = ref(props.filters?.type ?? "goodreturn");
const perPage = ref(props.filters?.per_page ?? 10);
const rows = computed(() => props.reasons?.data ?? []);
const activeModal = ref(null);
const editingId = ref(null);
const currentRecord = ref(null);
const confirmingDelete = ref(null);
const { can } = usePermissions();
const page = usePage();
const t = page.props.translations.ui;

const form = useForm({
  type: props.filters?.type ?? "goodreturn",
  alternatecode: "",
  description: "",
  arbdescription: "",
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

watch([selectedType, perPage], () => {
  reloadList();
});

function reloadList(pageNumber = 1) {
  router.get(
    "/basic/reason",
    {
      type: selectedType.value,
      search: search.value || undefined,
      per_page: perPage.value,
      page: pageNumber,
    },
    {
      preserveScroll: true,
      preserveState: true,
      replace: true,
      only: ["reasons", "filters"],
    },
  );
}

function fillForm(record) {
  form.type = record.type ?? selectedType.value;
  form.alternatecode = record.alternatecode ?? "";
  form.description = record.description ?? "";
  form.arbdescription = record.arbdescription ?? "";
}

function resetForm() {
  form.reset();
  form.type = selectedType.value;
  form.clearErrors();
}

function openCreate() {
  editingId.value = null;
  currentRecord.value = null;
  resetForm();
  activeModal.value = "create";
}

function openView(record) {
  editingId.value = record.id;
  currentRecord.value = record;
  resetForm();
  fillForm(record);
  activeModal.value = "view";
}

function openEdit(record) {
  editingId.value = record.id;
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
  form.post("/basic/reason", {
    preserveScroll: true,
    onSuccess: closeModal,
  });
}

function submitEdit() {
  form.put(`/basic/reason/${editingId.value}`, {
    preserveScroll: true,
    onSuccess: closeModal,
  });
}

function deleteRow() {
  router.delete(`/basic/reason/${confirmingDelete.value.id}`, {
    preserveScroll: true,
    data: { type: confirmingDelete.value.type },
    onSuccess: () => {
      confirmingDelete.value = null;
    },
  });
}
</script>

<template>
  <Head :title="t.reason_master" />

  <BasePageHeading :title="t.reason_master" :subtitle="t.reason_note">
    <template #extra>
      <button v-if="can('reason', 'create')" class="btn btn-primary" @click="openCreate">
        <i class="fa fa-plus me-1"></i>
        {{ t.add_reason }}
      </button>
    </template>
  </BasePageHeading>

  <div class="content">
    <BaseBlock :title="t.reason_list">
      <template #options>
        <div class="d-flex gap-2">
          <select v-model="selectedType" class="form-select form-select-sm" style="width: 190px">
            <option v-for="(label, key) in types" :key="key" :value="key">{{ label }}</option>
          </select>
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
              <th>{{ t.alternate_code }}</th>
              <th>{{ t.description }}</th>
              <th>{{ t.reason_type }}</th>
              <th class="text-center" style="width: 140px">{{ t.actions }}</th>
            </tr>
          </thead>
          <tbody>
            <tr v-if="!rows.length">
              <td colspan="6" class="text-center text-muted py-4">{{ t.no_records_found }}</td>
            </tr>
            <tr v-for="(record, index) in rows" :key="`${record.type}-${record.id}`">
              <td class="text-muted">{{ (reasons.from ?? 1) + index }}</td>
              <td>{{ record.code }}</td>
              <td>{{ record.alternatecode }}</td>
              <td class="fw-semibold">{{ record.description }}</td>
              <td>{{ types[record.type] ?? record.type }}</td>
              <td class="text-center text-nowrap">
                <button
                  v-if="canViewAction('reason')"
                  class="btn btn-sm btn-alt-info me-1"
                  :title="t.view"
                  @click="openView(record)"
                >
                  <i class="fa fa-eye"></i>
                </button>
                <button
                  v-if="can('reason', 'edit')"
                  class="btn btn-sm btn-alt-secondary me-1"
                  :title="t.edit"
                  @click="openEdit(record)"
                >
                  <i class="fa fa-pen"></i>
                </button>
                <button
                  v-if="can('reason', 'delete')"
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
          {{ t.showing }} {{ reasons.from ?? 0 }} {{ t.to }} {{ reasons.to ?? 0 }} {{ t.of }} {{ reasons.total ?? 0 }}
        </div>
        <div class="btn-group">
          <button
            class="btn btn-sm btn-alt-secondary"
            :disabled="!reasons.prev_page_url"
            @click="reloadList((reasons.current_page || 1) - 1)"
          >
            {{ t.previous }}
          </button>
          <button class="btn btn-sm btn-alt-secondary" disabled>
            {{ reasons.current_page || 1 }} / {{ reasons.last_page || 1 }}
          </button>
          <button
            class="btn btn-sm btn-alt-secondary"
            :disabled="!reasons.next_page_url"
            @click="reloadList((reasons.current_page || 1) + 1)"
          >
            {{ t.next }}
          </button>
        </div>
      </div>
    </BaseBlock>
  </div>

  <CreateReason
    v-if="activeModal === 'create'"
    :form="form"
    :types="types"
    @close="closeModal"
    @submit="submitCreate"
  />

  <ViewReason
    v-if="activeModal === 'view'"
    :form="form"
    :types="types"
    :record="currentRecord"
    @close="closeModal"
  />

  <EditReason
    v-if="activeModal === 'edit'"
    :form="form"
    :types="types"
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
          {{ t.delete_reason_label }} <strong>{{ confirmingDelete.description }}</strong>?
        </div>
        <div class="modal-footer">
          <button class="btn btn-alt-secondary" @click="confirmingDelete = null">{{ t.cancel }}</button>
          <button class="btn btn-danger" @click="deleteRow">{{ t.delete }}</button>
        </div>
      </div>
    </div>
  </div>
</template>
