<script setup>
import { computed, ref, watch } from "vue";
import { Head, router, useForm, usePage } from "@inertiajs/vue3";
import { usePermissions } from "@/composables/usePermissions";
import CreateArea from "./Create.vue";
import EditArea from "./Edit.vue";
import ViewArea from "./View.vue";

const props = defineProps({
  areas: Object,
  filters: Object,
  depots: Array,
  areaManagers: Array,
});

const search = ref(props.filters?.search ?? "");
const perPage = ref(props.filters?.per_page ?? 10);
const rows = computed(() => props.areas?.data ?? []);
const activeModal = ref(null);
const editingId = ref(null);
const confirmingDelete = ref(null);
const { can } = usePermissions();
const page = usePage();
const t = page.props.translations.ui;

const form = useForm({
  alternateareacode: "",
  areaname: "",
  arbareaname: "",
  depotcode: null,
  areamanagercode: null,
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
    "/organisation/area",
    {
      search: search.value || undefined,
      per_page: perPage.value,
      page: pageNumber,
    },
    {
      preserveScroll: true,
      preserveState: true,
      replace: true,
      only: ["areas", "filters"],
    },
  );
}

function fillForm(record) {
  form.alternateareacode = record.alternateareacode ?? "";
  form.areaname = record.areaname ?? "";
  form.arbareaname = record.arbareaname ?? "";
  form.depotcode = record.depotcode ?? null;
  form.areamanagercode = record.areamanagercode ?? null;
  form.activestatus = record.activestatus ?? 1;
}

function resetForm() {
  form.reset();
  form.activestatus = 1;
  form.clearErrors();
}

function openCreate() {
  editingId.value = null;
  resetForm();
  activeModal.value = "create";
}

function openView(record) {
  editingId.value = record.areacode;
  resetForm();
  fillForm(record);
  activeModal.value = "view";
}

function openEdit(record) {
  editingId.value = record.areacode;
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
  form.post("/organisation/area", {
    preserveScroll: true,
    onSuccess: closeModal,
  });
}

function submitEdit() {
  form.put(`/organisation/area/${editingId.value}`, {
    preserveScroll: true,
    onSuccess: closeModal,
  });
}

function deleteRow() {
  router.delete(`/organisation/area/${confirmingDelete.value.areacode}`, {
    preserveScroll: true,
    onSuccess: () => {
      confirmingDelete.value = null;
    },
  });
}
</script>

<template>
  <Head :title="t.area" />

  <BasePageHeading :title="t.area" :subtitle="t.area_note">
    <template #extra>
      <button v-if="can('area', 'create')" class="btn btn-primary" @click="openCreate">
        <i class="fa fa-plus me-1"></i> {{ t.add_area }}
      </button>
    </template>
  </BasePageHeading>

  <div class="content">
    <BaseBlock :title="t.area_list">
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
              <th>{{ t.area_name }}</th>
              <th>{{ t.area_manager }}</th>
              <th>{{ t.branch_depot }}</th>
              <th>{{ t.status }}</th>
              <th class="text-center" style="width: 140px">{{ t.actions }}</th>
            </tr>
          </thead>
          <tbody>
            <tr v-if="!rows.length">
              <td colspan="7" class="text-center text-muted py-4">{{ t.no_records_found }}</td>
            </tr>
            <tr v-for="(record, index) in rows" :key="record.areacode">
              <td class="text-muted">{{ (areas.from ?? 1) + index }}</td>
              <td>{{ record.alternateareacode || record.areacode }}</td>
              <td class="fw-semibold">{{ record.areaname }}</td>
              <td>{{ record.areamanagername || "-" }}</td>
              <td>{{ record.depotname || "-" }}</td>
              <td>
                <span class="badge" :class="record.activestatus ? 'bg-success' : 'bg-secondary'">
                  {{ record.activestatus ? t.status_active : t.status_inactive }}
                </span>
              </td>
              <td class="text-center text-nowrap">
                <button
                  v-if="canViewAction('area')"
                  class="btn btn-sm btn-alt-info me-1"
                  :title="t.view"
                  @click="openView(record)"
                >
                  <i class="fa fa-eye"></i>
                </button>
                <button
                  v-if="can('area', 'edit')"
                  class="btn btn-sm btn-alt-secondary me-1"
                  :title="t.edit"
                  @click="openEdit(record)"
                >
                  <i class="fa fa-pen"></i>
                </button>
                <button
                  v-if="can('area', 'delete')"
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
          {{ t.showing }} {{ areas.from ?? 0 }} {{ t.to }} {{ areas.to ?? 0 }} {{ t.of }} {{ areas.total ?? 0 }}
        </div>
        <div class="btn-group">
          <button
            class="btn btn-sm btn-alt-secondary"
            :disabled="!areas.prev_page_url"
            @click="reloadList((areas.current_page || 1) - 1)"
          >
            {{ t.previous }}
          </button>
          <button class="btn btn-sm btn-alt-secondary" disabled>
            {{ areas.current_page || 1 }} / {{ areas.last_page || 1 }}
          </button>
          <button
            class="btn btn-sm btn-alt-secondary"
            :disabled="!areas.next_page_url"
            @click="reloadList((areas.current_page || 1) + 1)"
          >
            {{ t.next }}
          </button>
        </div>
      </div>
    </BaseBlock>
  </div>

  <CreateArea
    v-if="activeModal === 'create'"
    :form="form"
    :depots="depots"
    :area-managers="areaManagers"
    @close="closeModal"
    @submit="submitCreate"
  />

  <ViewArea
    v-if="activeModal === 'view'"
    :form="form"
    :depots="depots"
    :area-managers="areaManagers"
    @close="closeModal"
  />

  <EditArea
    v-if="activeModal === 'edit'"
    :form="form"
    :depots="depots"
    :area-managers="areaManagers"
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
        <div class="modal-body">{{ t.delete_area_label }} <strong>{{ confirmingDelete.areaname }}</strong>?</div>
        <div class="modal-footer">
          <button class="btn btn-alt-secondary" @click="confirmingDelete = null">{{ t.cancel }}</button>
          <button class="btn btn-danger" @click="deleteRow">{{ t.delete }}</button>
        </div>
      </div>
    </div>
  </div>
</template>
