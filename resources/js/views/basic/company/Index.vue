<script setup>
import { computed, ref, watch } from "vue";
import { Head, router, useForm, usePage } from "@inertiajs/vue3";
import { usePermissions } from "@/composables/usePermissions";
import CreateCompany from "./Create.vue";
import EditCompany from "./Edit.vue";
import ViewCompany from "./View.vue";

const props = defineProps({
  companies: Object,
  filters: Object,
  companyOptions: Array,
  countries: Array,
});

const search = ref(props.filters?.search ?? "");
const perPage = ref(props.filters?.per_page ?? 10);
const rows = computed(() => props.companies?.data ?? []);
const activeModal = ref(null);
const editingId = ref(null);
const confirmingDelete = ref(null);
const { can } = usePermissions();
const page = usePage();
const t = page.props.translations.ui;

const form = useForm({
  alternatecmpycode: "",
  name: "",
  arbcompanyname: "",
  parentcompany: null,
  contactname: "",
  address: "",
  telephone: "",
  fax: "",
  zipcode: "",
  countrycode: null,
  taxregistrationnumber: "",
  distributorcode: "",
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
    "/basic/company",
    {
      search: search.value || undefined,
      per_page: perPage.value,
      page: pageNumber,
    },
    {
      preserveScroll: true,
      preserveState: true,
      replace: true,
      only: ["companies", "filters"],
    },
  );
}

function fillForm(record) {
  form.alternatecmpycode = record.alternatecmpycode ?? "";
  form.name = record.name ?? "";
  form.arbcompanyname = record.arbcompanyname ?? "";
  form.parentcompany = record.parentcompany ?? null;
  form.contactname = record.contactname ?? "";
  form.address = record.address ?? "";
  form.telephone = record.telephone ?? "";
  form.fax = record.fax ?? "";
  form.zipcode = record.zipcode ?? "";
  form.countrycode = record.countrycode ?? null;
  form.taxregistrationnumber = record.taxregistrationnumber ?? "";
  form.distributorcode = record.distributorcode ?? "";
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
  editingId.value = record.cmpycode;
  form.reset();
  fillForm(record);
  form.clearErrors();
  activeModal.value = "view";
}

function openEdit(record) {
  editingId.value = record.cmpycode;
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
  form.post("/basic/company", {
    preserveScroll: true,
    onSuccess: closeModal,
  });
}

function submitEdit() {
  form.put(`/basic/company/${editingId.value}`, {
    preserveScroll: true,
    onSuccess: closeModal,
  });
}

function deleteRow() {
  router.delete(`/basic/company/${confirmingDelete.value.cmpycode}`, {
    preserveScroll: true,
    onSuccess: () => {
      confirmingDelete.value = null;
    },
  });
}
</script>

<template>
  <Head :title="t.company_master" />

  <BasePageHeading :title="t.company_master" :subtitle="t.company_note">
    <template #extra>
      <button v-if="can('company', 'create')" class="btn btn-primary" @click="openCreate">
        <i class="fa fa-plus me-1"></i> {{ t.add_company }}
      </button>
    </template>
  </BasePageHeading>

  <div class="content">
    <BaseBlock :title="t.company_list">
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
              <th>{{ t.company_name }}</th>
              <th>{{ t.company_type }}</th>
              <th>{{ t.country }}</th>
              <th>{{ t.telephone }}</th>
              <th>{{ t.status }}</th>
              <th class="text-center" style="width: 140px">{{ t.actions }}</th>
            </tr>
          </thead>
          <tbody>
            <tr v-if="!rows.length">
              <td colspan="7" class="text-center text-muted py-4">{{ t.no_records_found }}</td>
            </tr>
            <tr v-for="(record, index) in rows" :key="record.cmpycode">
              <td class="text-muted">{{ (companies.from ?? 1) + index }}</td>
              <td class="fw-semibold">{{ record.name }}</td>
              <td>
                <span class="badge" :class="record.parentcompany ? 'bg-info' : 'bg-primary'">
                  {{ record.parentcompany ? t.sub_company : t.parent_company_label }}
                </span>
              </td>
              <td>{{ record.countryname }}</td>
              <td>{{ record.telephone }}</td>
              <td>
                <span class="badge" :class="record.activestatus ? 'bg-success' : 'bg-secondary'">
                  {{ record.activestatus ? t.status_active : t.status_inactive }}
                </span>
              </td>
              <td class="text-center text-nowrap">
                <button
                  v-if="canViewAction('company')"
                  class="btn btn-sm btn-alt-info me-1"
                  :title="t.view"
                  @click="openView(record)"
                >
                  <i class="fa fa-eye"></i>
                </button>
                <button
                  v-if="can('company', 'edit')"
                  class="btn btn-sm btn-alt-secondary me-1"
                  :title="t.edit"
                  @click="openEdit(record)"
                >
                  <i class="fa fa-pen"></i>
                </button>
                <button
                  v-if="can('company', 'delete')"
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
          {{ t.showing }} {{ companies.from ?? 0 }} {{ t.to }} {{ companies.to ?? 0 }} {{ t.of }} {{ companies.total ?? 0 }}
        </div>
        <div class="btn-group">
          <button
            class="btn btn-sm btn-alt-secondary"
            :disabled="!companies.prev_page_url"
            @click="reloadList((companies.current_page || 1) - 1)"
          >
            {{ t.previous }}
          </button>
          <button class="btn btn-sm btn-alt-secondary" disabled>
            {{ companies.current_page || 1 }} / {{ companies.last_page || 1 }}
          </button>
          <button
            class="btn btn-sm btn-alt-secondary"
            :disabled="!companies.next_page_url"
            @click="reloadList((companies.current_page || 1) + 1)"
          >
            {{ t.next }}
          </button>
        </div>
      </div>
    </BaseBlock>
  </div>

  <CreateCompany
    v-if="activeModal === 'create'"
    :form="form"
    :countries="countries"
    :company-options="companyOptions"
    @close="closeModal"
    @submit="submitCreate"
  />

  <ViewCompany
    v-if="activeModal === 'view'"
    :form="form"
    :countries="countries"
    :company-options="companyOptions"
    :editing-id="editingId"
    @close="closeModal"
  />

  <EditCompany
    v-if="activeModal === 'edit'"
    :form="form"
    :countries="countries"
    :company-options="companyOptions"
    :editing-id="editingId"
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
        <div class="modal-body">{{ t.delete_company_label }} <strong>{{ confirmingDelete.name }}</strong>?</div>
        <div class="modal-footer">
          <button class="btn btn-alt-secondary" @click="confirmingDelete = null">{{ t.cancel }}</button>
          <button class="btn btn-danger" @click="deleteRow">{{ t.delete }}</button>
        </div>
      </div>
    </div>
  </div>
</template>
