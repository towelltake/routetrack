<script setup>
import { computed, ref, watch } from "vue";
import { Head, router, useForm, usePage } from "@inertiajs/vue3";
import { usePermissions } from "@/composables/usePermissions";

const props = defineProps({
  companyGroups: { type: Object, required: true },
  filters: { type: Object, default: () => ({}) },
  companies: { type: Array, default: () => [] },
});

const page = usePage();
const t = page.props.translations.ui;
const locale = page.props.locale;
const { can } = usePermissions();

const rows = computed(() => props.companyGroups?.data ?? []);
const search = ref(props.filters?.search ?? "");
const perPage = ref(props.filters?.per_page ?? 10);
const sortBy = ref(props.filters?.sort_by ?? "companygroupcode");
const sortDir = ref(props.filters?.sort_dir ?? "asc");

const showModal = ref(false);
const isEditing = ref(false);
const isViewing = ref(false);
const editingId = ref(null);
const confirmingDelete = ref(null);

const form = useForm({
  alternatecode: "",
  description: "",
  arbdescription: "",
  parentcompany: "",
  activestatus: 1,
});

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
  router.get("/inventory/companygroup", {
    search: search.value || undefined,
    per_page: perPage.value,
    sort_by: sortBy.value,
    sort_dir: sortDir.value,
    page: pageNumber,
  }, {
    preserveScroll: true,
    preserveState: true,
    replace: true,
    only: ["companyGroups", "filters", "companies"],
  });
}

function openAdd() {
  isEditing.value = false;
  isViewing.value = false;
  editingId.value = null;
  form.reset();
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
  editingId.value = record.companygroupcode;
  form.alternatecode = record.alternatecode ?? "";
  form.description = record.description ?? "";
  form.arbdescription = record.arbdescription ?? "";
  form.parentcompany = record.parentcompany ?? "";
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
  if (isViewing.value) return;

  const options = {
    preserveScroll: true,
    onSuccess: closeModal,
  };

  if (isEditing.value) {
    form.put(`/inventory/companygroup/${editingId.value}`, options);
    return;
  }

  form.post("/inventory/companygroup", options);
}

function deleteRow() {
  router.delete(`/inventory/companygroup/${confirmingDelete.value.companygroupcode}`, {
    preserveScroll: true,
    onSuccess: () => {
      confirmingDelete.value = null;
    },
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

function companyLabel(record) {
  return locale === "ar"
    ? (record.company_name_ar || record.company_name || "")
    : (record.company_name || record.company_name_ar || "");
}

function canViewAction(permission) {
  const details = page.props.auth?.formPermissions?.[permission];
  return !!(details?.all || details?.view);
}
</script>

<template>
  <Head :title="t.company_group" />

  <BasePageHeading :title="t.company_group" :subtitle="t.company_group_note">
    <template #extra>
      <button v-if="can('company group', 'create')" class="btn btn-primary" @click="openAdd">
        <i class="fa fa-plus me-1"></i> {{ t.add_company_group }}
      </button>
    </template>
  </BasePageHeading>

  <div class="content">
    <BaseBlock :title="t.company_group_list">
      <template #options>
        <div class="d-flex gap-2">
          <input
            v-model="search"
            type="text"
            class="form-control form-control-sm"
            :placeholder="`${t.search}...`"
            style="width: 180px"
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
              <th class="cursor-pointer" @click="sort('alternatecode')">
                {{ t.code }}
                <i class="fa ms-1" :class="sortIcon('alternatecode')"></i>
              </th>
              <th class="cursor-pointer" @click="sort('description')">
                {{ t.descriptions }}
                <i class="fa ms-1" :class="sortIcon('description')"></i>
              </th>
              <th class="cursor-pointer" @click="sort('arbdescription')">
                {{ t.arab_description }}
                <i class="fa ms-1" :class="sortIcon('arbdescription')"></i>
              </th>
              <th>{{ t.company_master }}</th>
              <th class="cursor-pointer text-center" @click="sort('activestatus')">
                {{ t.status }}
                <i class="fa ms-1" :class="sortIcon('activestatus')"></i>
              </th>
              <th class="text-center" style="width: 120px">{{ t.actions }}</th>
            </tr>
          </thead>
          <tbody>
            <tr v-if="!rows.length">
              <td colspan="6" class="text-center text-muted py-4">
                {{ t.no_records_found }}
              </td>
            </tr>
            <tr v-for="record in rows" :key="record.companygroupcode">
              <td class="fw-semibold">{{ record.alternatecode }}</td>
              <td>{{ record.description }}</td>
              <td>{{ record.arbdescription }}</td>
              <td>{{ companyLabel(record) }}</td>
              <td class="text-center">
                <span
                  class="fs-xs fw-semibold d-inline-block py-1 px-3 rounded-pill"
                  :class="record.activestatus === 1 ? 'bg-success-light text-success' : 'bg-danger-light text-danger'"
                >
                  {{ record.activestatus === 1 ? t.status_active : t.status_inactive }}
                </span>
              </td>
              <td class="text-center text-nowrap">
                <button
                  v-if="canViewAction('company group')"
                  class="btn btn-sm btn-alt-info me-1"
                  @click="openView(record)"
                >
                  <i class="fa fa-eye"></i>
                </button>
                <button
                  v-if="can('company group', 'edit')"
                  class="btn btn-sm btn-alt-secondary me-1"
                  @click="openEdit(record)"
                >
                  <i class="fa fa-pen"></i>
                </button>
                <button
                  v-if="can('company group', 'delete')"
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
          {{ t.showing }} {{ companyGroups.from ?? 0 }} {{ t.to }} {{ companyGroups.to ?? 0 }} {{ t.of }} {{ companyGroups.total ?? 0 }}
        </div>
        <div class="btn-group">
          <button class="btn btn-sm btn-alt-secondary" :disabled="!companyGroups.prev_page_url" @click="reloadList((companyGroups.current_page || 1) - 1)">{{ t.previous }}</button>
          <button class="btn btn-sm btn-alt-secondary" disabled>{{ companyGroups.current_page || 1 }} / {{ companyGroups.last_page || 1 }}</button>
          <button class="btn btn-sm btn-alt-secondary" :disabled="!companyGroups.next_page_url" @click="reloadList((companyGroups.current_page || 1) + 1)">{{ t.next }}</button>
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
            {{ isViewing ? t.company_group : (isEditing ? t.edit_company_group : t.create_company_group) }}
          </h5>
          <button class="btn-close" @click="closeModal"></button>
        </div>
        <form @submit.prevent="submit">
          <div class="modal-body">
            <div class="mb-3">
              <label class="form-label">{{ t.code }} <span class="text-danger">*</span></label>
              <input v-model="form.alternatecode" class="form-control" :disabled="isViewing" />
              <div v-if="form.errors.alternatecode" class="text-danger fs-sm mt-1">
                {{ form.errors.alternatecode }}
              </div>
            </div>

            <div class="mb-3">
              <label class="form-label">{{ t.descriptions }} <span class="text-danger">*</span></label>
              <input v-model="form.description" class="form-control" :disabled="isViewing" />
              <div v-if="form.errors.description" class="text-danger fs-sm mt-1">
                {{ form.errors.description }}
              </div>
            </div>

            <div class="mb-3">
              <label class="form-label">{{ t.arab_description }} <span class="text-danger">*</span></label>
              <input v-model="form.arbdescription" class="form-control" dir="rtl" :disabled="isViewing" />
              <div v-if="form.errors.arbdescription" class="text-danger fs-sm mt-1">
                {{ form.errors.arbdescription }}
              </div>
            </div>

            <div class="mb-3">
              <label class="form-label">{{ t.company_master }} <span class="text-danger">*</span></label>
              <select v-model="form.parentcompany" class="form-select" :disabled="isViewing">
                <option value="">{{ t.select_company }}</option>
                <option v-for="company in companies" :key="company.cmpycode" :value="company.cmpycode">
                  {{ locale === "ar" ? (company.arbcompanyname || company.name) : (company.name || company.arbcompanyname) }}
                </option>
              </select>
              <div v-if="form.errors.parentcompany" class="text-danger fs-sm mt-1">
                {{ form.errors.parentcompany }}
              </div>
            </div>

            <div v-if="isEditing" class="mb-1">
              <label class="form-label">{{ t.status }}</label>
              <select v-model="form.activestatus" class="form-select">
                <option :value="1">{{ t.status_active }}</option>
                <option :value="0">{{ t.status_inactive }}</option>
              </select>
              <div v-if="form.errors.activestatus" class="text-danger fs-sm mt-1">
                {{ form.errors.activestatus }}
              </div>
            </div>
          </div>

          <div class="modal-footer">
            <button type="button" class="btn btn-alt-secondary" @click="closeModal">
              {{ t.cancel }}
            </button>
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
            <i class="fa fa-triangle-exclamation me-1"></i> {{ t.delete }}
          </h5>
          <button class="btn-close" @click="confirmingDelete = null"></button>
        </div>
        <div class="modal-body">
          {{ t.company_group_delete_confirm }}
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
