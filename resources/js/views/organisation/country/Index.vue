<script setup>
import { computed, ref, watch } from "vue";
import { Head, router, useForm, usePage } from "@inertiajs/vue3";
import { usePermissions } from "@/composables/usePermissions";
import CreateCountry from "./Create.vue";
import EditCountry from "./Edit.vue";
import ViewCountry from "./View.vue";

const props = defineProps({
  countries: Object,
  filters: Object,
  currencies: Array,
  companies: Array,
  nationalSalesManagers: Array,
});

const search = ref(props.filters?.search ?? "");
const perPage = ref(props.filters?.per_page ?? 10);
const rows = computed(() => props.countries?.data ?? []);
const activeModal = ref(null);
const editingId = ref(null);
const confirmingDelete = ref(null);
const { can } = usePermissions();
const page = usePage();
const t = page.props.translations.ui;

const form = useForm({
  alternatecode: "",
  countryname: "",
  arbcountryname: "",
  currencycode: null,
  cmpycode: null,
  nationalsalesmanagercode: null,
  pricechangevariance: 0,
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
    "/organisation/country",
    {
      search: search.value || undefined,
      per_page: perPage.value,
      page: pageNumber,
    },
    {
      preserveScroll: true,
      preserveState: true,
      replace: true,
      only: ["countries", "filters"],
    },
  );
}

function fillForm(record) {
  form.alternatecode = record.alternatecode ?? "";
  form.countryname = record.countryname ?? "";
  form.arbcountryname = record.arbcountryname ?? "";
  form.currencycode = record.currencycode ?? null;
  form.cmpycode = record.cmpycode ?? null;
  form.nationalsalesmanagercode = record.nationalsalesmanagercode ?? null;
  form.pricechangevariance = record.pricechangevariance ?? 0;
}

function resetForm() {
  form.reset();
  form.pricechangevariance = 0;
  form.clearErrors();
}

function openCreate() {
  editingId.value = null;
  resetForm();
  activeModal.value = "create";
}

function openView(record) {
  editingId.value = record.countrycode;
  resetForm();
  fillForm(record);
  activeModal.value = "view";
}

function openEdit(record) {
  editingId.value = record.countrycode;
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
  form.post("/organisation/country", {
    preserveScroll: true,
    onSuccess: closeModal,
  });
}

function submitEdit() {
  form.put(`/organisation/country/${editingId.value}`, {
    preserveScroll: true,
    onSuccess: closeModal,
  });
}

function deleteRow() {
  router.delete(`/organisation/country/${confirmingDelete.value.countrycode}`, {
    preserveScroll: true,
    onSuccess: () => {
      confirmingDelete.value = null;
    },
  });
}
</script>

<template>
  <Head :title="t.country_master" />

  <BasePageHeading :title="t.country_master" :subtitle="t.country_note">
    <template #extra>
      <button v-if="can('country', 'create')" class="btn btn-primary" @click="openCreate">
        <i class="fa fa-plus me-1"></i> {{ t.add_country }}
      </button>
    </template>
  </BasePageHeading>

  <div class="content">
    <BaseBlock :title="t.country_list">
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
              <th>{{ t.country_name }}</th>
              <th>{{ t.currency }}</th>
              <th>{{ t.company }}</th>
              <th>{{ t.price_change_variance }}</th>
              <th class="text-center" style="width: 140px">{{ t.actions }}</th>
            </tr>
          </thead>
          <tbody>
            <tr v-if="!rows.length">
              <td colspan="7" class="text-center text-muted py-4">{{ t.no_records_found }}</td>
            </tr>
            <tr v-for="(record, index) in rows" :key="record.countrycode">
              <td class="text-muted">{{ (countries.from ?? 1) + index }}</td>
              <td>{{ record.alternatecode || record.countrycode }}</td>
              <td class="fw-semibold">{{ record.countryname }}</td>
              <td>{{ record.currencyname || "-" }}</td>
              <td>{{ record.companyname || "-" }}</td>
              <td>{{ record.pricechangevariance ?? 0 }}</td>
              <td class="text-center text-nowrap">
                <button
                  v-if="canViewAction('country')"
                  class="btn btn-sm btn-alt-info me-1"
                  :title="t.view"
                  @click="openView(record)"
                >
                  <i class="fa fa-eye"></i>
                </button>
                <button
                  v-if="can('country', 'edit')"
                  class="btn btn-sm btn-alt-secondary me-1"
                  :title="t.edit"
                  @click="openEdit(record)"
                >
                  <i class="fa fa-pen"></i>
                </button>
                <button
                  v-if="can('country', 'delete')"
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
          {{ t.showing }} {{ countries.from ?? 0 }} {{ t.to }} {{ countries.to ?? 0 }} {{ t.of }} {{ countries.total ?? 0 }}
        </div>
        <div class="btn-group">
          <button
            class="btn btn-sm btn-alt-secondary"
            :disabled="!countries.prev_page_url"
            @click="reloadList((countries.current_page || 1) - 1)"
          >
            {{ t.previous }}
          </button>
          <button class="btn btn-sm btn-alt-secondary" disabled>
            {{ countries.current_page || 1 }} / {{ countries.last_page || 1 }}
          </button>
          <button
            class="btn btn-sm btn-alt-secondary"
            :disabled="!countries.next_page_url"
            @click="reloadList((countries.current_page || 1) + 1)"
          >
            {{ t.next }}
          </button>
        </div>
      </div>
    </BaseBlock>
  </div>

  <CreateCountry
    v-if="activeModal === 'create'"
    :form="form"
    :currencies="currencies"
    :companies="companies"
    :national-sales-managers="nationalSalesManagers"
    @close="closeModal"
    @submit="submitCreate"
  />

  <ViewCountry
    v-if="activeModal === 'view'"
    :form="form"
    :currencies="currencies"
    :companies="companies"
    :national-sales-managers="nationalSalesManagers"
    @close="closeModal"
  />

  <EditCountry
    v-if="activeModal === 'edit'"
    :form="form"
    :currencies="currencies"
    :companies="companies"
    :national-sales-managers="nationalSalesManagers"
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
        <div class="modal-body">{{ t.delete_country_label }} <strong>{{ confirmingDelete.countryname }}</strong>?</div>
        <div class="modal-footer">
          <button class="btn btn-alt-secondary" @click="confirmingDelete = null">{{ t.cancel }}</button>
          <button class="btn btn-danger" @click="deleteRow">{{ t.delete }}</button>
        </div>
      </div>
    </div>
  </div>
</template>
