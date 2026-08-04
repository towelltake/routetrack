<script setup>
import { computed, ref, watch } from "vue";
import { Head, router, useForm, usePage } from "@inertiajs/vue3";
import { usePermissions } from "@/composables/usePermissions";
import CreateRegion from "./Create.vue";
import EditRegion from "./Edit.vue";
import ViewRegion from "./View.vue";

const props = defineProps({
  regions: Object,
  filters: Object,
  countries: Array,
  regionManagers: Array,
});

const search = ref(props.filters?.search ?? "");
const perPage = ref(props.filters?.per_page ?? 10);
const rows = computed(() => props.regions?.data ?? []);
const activeModal = ref(null);
const editingId = ref(null);
const confirmingDelete = ref(null);
const { can } = usePermissions();
const page = usePage();
const t = page.props.translations.ui;

const form = useForm({
  alternatecode: "",
  regionmstname: "",
  arbregionmstname: "",
  countrycode: null,
  regionmanagercode: null,
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
    "/organisation/region",
    {
      search: search.value || undefined,
      per_page: perPage.value,
      page: pageNumber,
    },
    {
      preserveScroll: true,
      preserveState: true,
      replace: true,
      only: ["regions", "filters"],
    },
  );
}

function fillForm(record) {
  form.alternatecode = record.alternatecode ?? "";
  form.regionmstname = record.regionmstname ?? "";
  form.arbregionmstname = record.arbregionmstname ?? "";
  form.countrycode = record.countrycode ?? null;
  form.regionmanagercode = record.regionmanagercode ?? null;
}

function resetForm() {
  form.reset();
  form.clearErrors();
}

function openCreate() {
  editingId.value = null;
  resetForm();
  activeModal.value = "create";
}

function openView(record) {
  editingId.value = record.regionmstcode;
  resetForm();
  fillForm(record);
  activeModal.value = "view";
}

function openEdit(record) {
  editingId.value = record.regionmstcode;
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
  form.post("/organisation/region", {
    preserveScroll: true,
    onSuccess: closeModal,
  });
}

function submitEdit() {
  form.put(`/organisation/region/${editingId.value}`, {
    preserveScroll: true,
    onSuccess: closeModal,
  });
}

function deleteRow() {
  router.delete(`/organisation/region/${confirmingDelete.value.regionmstcode}`, {
    preserveScroll: true,
    onSuccess: () => {
      confirmingDelete.value = null;
    },
  });
}
</script>

<template>
  <Head :title="t.region" />

  <BasePageHeading :title="t.region" :subtitle="t.region_note">
    <template #extra>
      <button v-if="can('region', 'create')" class="btn btn-primary" @click="openCreate">
        <i class="fa fa-plus me-1"></i> {{ t.add_region }}
      </button>
    </template>
  </BasePageHeading>

  <div class="content">
    <BaseBlock :title="t.region_list">
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
              <th>{{ t.region_name }}</th>
              <th>{{ t.country }}</th>
              <th>{{ t.region_manager }}</th>
              <th class="text-center" style="width: 140px">{{ t.actions }}</th>
            </tr>
          </thead>
          <tbody>
            <tr v-if="!rows.length">
              <td colspan="6" class="text-center text-muted py-4">{{ t.no_records_found }}</td>
            </tr>
            <tr v-for="(record, index) in rows" :key="record.regionmstcode">
              <td class="text-muted">{{ (regions.from ?? 1) + index }}</td>
              <td>{{ record.alternatecode || record.regionmstcode }}</td>
              <td class="fw-semibold">{{ record.regionmstname }}</td>
              <td>{{ record.countryname || "-" }}</td>
              <td>{{ record.regionmanagername || "-" }}</td>
              <td class="text-center text-nowrap">
                <button
                  v-if="canViewAction('region')"
                  class="btn btn-sm btn-alt-info me-1"
                  :title="t.view"
                  @click="openView(record)"
                >
                  <i class="fa fa-eye"></i>
                </button>
                <button
                  v-if="can('region', 'edit')"
                  class="btn btn-sm btn-alt-secondary me-1"
                  :title="t.edit"
                  @click="openEdit(record)"
                >
                  <i class="fa fa-pen"></i>
                </button>
                <button
                  v-if="can('region', 'delete')"
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
          {{ t.showing }} {{ regions.from ?? 0 }} {{ t.to }} {{ regions.to ?? 0 }} {{ t.of }} {{ regions.total ?? 0 }}
        </div>
        <div class="btn-group">
          <button
            class="btn btn-sm btn-alt-secondary"
            :disabled="!regions.prev_page_url"
            @click="reloadList((regions.current_page || 1) - 1)"
          >
            {{ t.previous }}
          </button>
          <button class="btn btn-sm btn-alt-secondary" disabled>
            {{ regions.current_page || 1 }} / {{ regions.last_page || 1 }}
          </button>
          <button
            class="btn btn-sm btn-alt-secondary"
            :disabled="!regions.next_page_url"
            @click="reloadList((regions.current_page || 1) + 1)"
          >
            {{ t.next }}
          </button>
        </div>
      </div>
    </BaseBlock>
  </div>

  <CreateRegion
    v-if="activeModal === 'create'"
    :form="form"
    :countries="countries"
    :region-managers="regionManagers"
    @close="closeModal"
    @submit="submitCreate"
  />

  <ViewRegion
    v-if="activeModal === 'view'"
    :form="form"
    :countries="countries"
    :region-managers="regionManagers"
    @close="closeModal"
  />

  <EditRegion
    v-if="activeModal === 'edit'"
    :form="form"
    :countries="countries"
    :region-managers="regionManagers"
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
        <div class="modal-body">{{ t.delete_region_label }} <strong>{{ confirmingDelete.regionmstname }}</strong>?</div>
        <div class="modal-footer">
          <button class="btn btn-alt-secondary" @click="confirmingDelete = null">{{ t.cancel }}</button>
          <button class="btn btn-danger" @click="deleteRow">{{ t.delete }}</button>
        </div>
      </div>
    </div>
  </div>
</template>
