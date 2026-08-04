<script setup>
import { computed, ref, watch } from "vue";
import { Head, router, useForm, usePage } from "@inertiajs/vue3";
import { usePermissions } from "@/composables/usePermissions";

const props = defineProps({
  itemGroups: { type: Object, required: true },
  filters: { type: Object, default: () => ({}) },
  subMajorCategories: { type: Array, default: () => [] },
  nextItemGroupCode: { type: Number, default: 1 },
});

const page = usePage();
const t = page.props.translations.ui;
const locale = page.props.locale;
const { can } = usePermissions();

const rows = computed(() => props.itemGroups?.data ?? []);
const search = ref(props.filters?.search ?? "");
const perPage = ref(props.filters?.per_page ?? 10);
const sortBy = ref(props.filters?.sort_by ?? "itemgroupcode");
const sortDir = ref(props.filters?.sort_dir ?? "asc");

const showModal = ref(false);
const isEditing = ref(false);
const isViewing = ref(false);
const editingId = ref(null);
const confirmingDelete = ref(null);

const form = useForm({
  itemgroupcode: "",
  alternateitemgroupcode: "",
  itemgroupname: "",
  arbitemgroup: "",
  submajorcategorycode: "",
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
  router.get("/inventory/itemgroup", {
    search: search.value || undefined,
    per_page: perPage.value,
    sort_by: sortBy.value,
    sort_dir: sortDir.value,
    page: pageNumber,
  }, {
    preserveScroll: true,
    preserveState: true,
    replace: true,
    only: ["itemGroups", "filters", "subMajorCategories", "nextItemGroupCode"],
  });
}

function openAdd() {
  isEditing.value = false;
  isViewing.value = false;
  editingId.value = null;
  form.reset();
  form.itemgroupcode = props.nextItemGroupCode;
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
  editingId.value = record.itemgroupcode;
  form.itemgroupcode = record.itemgroupcode;
  form.alternateitemgroupcode = record.alternateitemgroupcode ?? "";
  form.itemgroupname = record.itemgroupname ?? "";
  form.arbitemgroup = record.arbitemgroup ?? "";
  form.submajorcategorycode = record.submajorcategorycode ?? "";
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
    form.put(`/inventory/itemgroup/${editingId.value}`, options);
    return;
  }

  form.post("/inventory/itemgroup", options);
}

function deleteRow() {
  router.delete(`/inventory/itemgroup/${confirmingDelete.value.itemgroupcode}`, {
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

function subMajorCategoryLabel(record) {
  return locale === "ar"
    ? (record.sub_major_category_name_ar || record.sub_major_category_name || "")
    : (record.sub_major_category_name || record.sub_major_category_name_ar || "");
}

function canViewAction(permission) {
  const details = page.props.auth?.formPermissions?.[permission];
  return !!(details?.all || details?.view);
}
</script>

<template>
  <Head :title="t.item_group" />

  <BasePageHeading :title="t.item_group" :subtitle="t.item_group_note">
    <template #extra>
      <button v-if="can('item group', 'create')" class="btn btn-primary" @click="openAdd">
        <i class="fa fa-plus me-1"></i> {{ t.add_item_group }}
      </button>
    </template>
  </BasePageHeading>

  <div class="content">
    <BaseBlock :title="t.item_group_list">
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
              <th class="cursor-pointer" @click="sort('itemgroupcode')">
                {{ t.code }}
                <i class="fa ms-1" :class="sortIcon('itemgroupcode')"></i>
              </th>
              <th class="cursor-pointer" @click="sort('alternateitemgroupcode')">
                {{ t.alternate_code }}
                <i class="fa ms-1" :class="sortIcon('alternateitemgroupcode')"></i>
              </th>
              <th class="cursor-pointer" @click="sort('itemgroupname')">
                {{ t.descriptions }}
                <i class="fa ms-1" :class="sortIcon('itemgroupname')"></i>
              </th>
              <th class="cursor-pointer" @click="sort('arbitemgroup')">
                {{ t.arab_description }}
                <i class="fa ms-1" :class="sortIcon('arbitemgroup')"></i>
              </th>
              <th>{{ t.sub_major_category }}</th>
              <th class="cursor-pointer text-center" @click="sort('activestatus')">
                {{ t.status }}
                <i class="fa ms-1" :class="sortIcon('activestatus')"></i>
              </th>
              <th class="text-center" style="width: 120px">{{ t.actions }}</th>
            </tr>
          </thead>
          <tbody>
            <tr v-if="!rows.length">
              <td colspan="7" class="text-center text-muted py-4">
                {{ t.no_records_found }}
              </td>
            </tr>
            <tr v-for="record in rows" :key="record.itemgroupcode">
              <td class="fw-semibold">{{ record.itemgroupcode }}</td>
              <td>{{ record.alternateitemgroupcode || "-" }}</td>
              <td>{{ record.itemgroupname }}</td>
              <td>{{ record.arbitemgroup || "-" }}</td>
              <td>{{ subMajorCategoryLabel(record) }}</td>
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
                  v-if="canViewAction('item group')"
                  class="btn btn-sm btn-alt-info me-1"
                  @click="openView(record)"
                >
                  <i class="fa fa-eye"></i>
                </button>
                <button
                  v-if="can('item group', 'edit')"
                  class="btn btn-sm btn-alt-secondary me-1"
                  @click="openEdit(record)"
                >
                  <i class="fa fa-pen"></i>
                </button>
                <button
                  v-if="can('item group', 'delete')"
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
          {{ t.showing }} {{ itemGroups.from ?? 0 }} {{ t.to }} {{ itemGroups.to ?? 0 }} {{ t.of }} {{ itemGroups.total ?? 0 }}
        </div>
        <div class="btn-group">
          <button class="btn btn-sm btn-alt-secondary" :disabled="!itemGroups.prev_page_url" @click="reloadList((itemGroups.current_page || 1) - 1)">{{ t.previous }}</button>
          <button class="btn btn-sm btn-alt-secondary" disabled>{{ itemGroups.current_page || 1 }} / {{ itemGroups.last_page || 1 }}</button>
          <button class="btn btn-sm btn-alt-secondary" :disabled="!itemGroups.next_page_url" @click="reloadList((itemGroups.current_page || 1) + 1)">{{ t.next }}</button>
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
            {{ isViewing ? t.item_group : (isEditing ? t.edit_item_group : t.create_item_group) }}
          </h5>
          <button class="btn-close" @click="closeModal"></button>
        </div>
        <form @submit.prevent="submit">
          <div class="modal-body">
            <div class="mb-3">
              <label class="form-label">{{ t.code }}</label>
              <input v-model="form.itemgroupcode" class="form-control" disabled />
            </div>

            <div class="mb-3">
              <label class="form-label">{{ t.alternate_code }}</label>
              <input v-model="form.alternateitemgroupcode" class="form-control" :disabled="isViewing" />
              <div v-if="form.errors.alternateitemgroupcode" class="text-danger fs-sm mt-1">
                {{ form.errors.alternateitemgroupcode }}
              </div>
            </div>

            <div class="mb-3">
              <label class="form-label">{{ t.descriptions }} <span class="text-danger">*</span></label>
              <input v-model="form.itemgroupname" class="form-control" :disabled="isViewing" />
              <div v-if="form.errors.itemgroupname" class="text-danger fs-sm mt-1">
                {{ form.errors.itemgroupname }}
              </div>
            </div>

            <div class="mb-3">
              <label class="form-label">{{ t.arab_description }}</label>
              <input v-model="form.arbitemgroup" class="form-control" dir="rtl" :disabled="isViewing" />
              <div v-if="form.errors.arbitemgroup" class="text-danger fs-sm mt-1">
                {{ form.errors.arbitemgroup }}
              </div>
            </div>

            <div class="mb-3">
              <label class="form-label">{{ t.sub_major_category }} <span class="text-danger">*</span></label>
              <select v-model="form.submajorcategorycode" class="form-select" :disabled="isViewing">
                <option value="">{{ t.select_sub_major_category }}</option>
                <option
                  v-for="category in subMajorCategories"
                  :key="category.submajorcategorycode"
                  :value="category.submajorcategorycode"
                >
                  {{ locale === "ar" ? (category.arbdescription || category.description) : (category.description || category.arbdescription) }}
                </option>
              </select>
              <div v-if="form.errors.submajorcategorycode" class="text-danger fs-sm mt-1">
                {{ form.errors.submajorcategorycode }}
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
          {{ t.item_group_delete_confirm }}
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
