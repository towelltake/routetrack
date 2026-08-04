<script setup>
import { ref, computed, watch } from "vue";
import { Head, useForm, router, usePage } from "@inertiajs/vue3";
import { usePermissions } from "@/composables/usePermissions";
import CreateCurrency from "./Create.vue";
import EditCurrency from "./Edit.vue";
import ViewCurrency from "./View.vue";

const props = defineProps({
  currencies: Object,
  filters: Object,
});

const search = ref(props.filters?.search ?? "");
const perPage = ref(props.filters?.per_page ?? 10);
const rows = computed(() => props.currencies?.data ?? []);
const activeModal = ref(null);
const editingId = ref(null);
const confirmingDelete = ref(null);
const { can } = usePermissions();
const page = usePage();
const t = page.props.translations.ui;

const form = useForm({
  alternatecode: "",
  currencyname: "",
  arbcurrencyname: "",
  currencysymbol: "",
  decimalplaces: 2,
  defaultcurrency: 0,
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
    "/basic/currency",
    {
      search: search.value || undefined,
      per_page: perPage.value,
      page: pageNumber,
    },
    {
      preserveScroll: true,
      preserveState: true,
      replace: true,
      only: ["currencies", "filters"],
    },
  );
}

function fillForm(record) {
  form.alternatecode = record.alternatecode ?? "";
  form.currencyname = record.currencyname ?? "";
  form.arbcurrencyname = record.arbcurrencyname ?? "";
  form.currencysymbol = record.currencysymbol ?? "";
  form.decimalplaces = record.decimalplaces ?? 2;
  form.defaultcurrency = record.defaultcurrency ?? 0;
}

function resetForm() {
  form.reset();
  form.decimalplaces = 2;
  form.defaultcurrency = 0;
  form.clearErrors();
}

function openCreate() {
  editingId.value = null;
  resetForm();
  activeModal.value = "create";
}

function openView(record) {
  editingId.value = record.currencycode;
  resetForm();
  fillForm(record);
  activeModal.value = "view";
}

function openEdit(record) {
  editingId.value = record.currencycode;
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
  form.post("/basic/currency", {
    preserveScroll: true,
    onSuccess: closeModal,
  });
}

function submitEdit() {
  form.put(`/basic/currency/${editingId.value}`, {
    preserveScroll: true,
    onSuccess: closeModal,
  });
}

function deleteRow() {
  router.delete(`/basic/currency/${confirmingDelete.value.currencycode}`, {
    preserveScroll: true,
    onSuccess: () => {
      confirmingDelete.value = null;
    },
  });
}
</script>

<template>
  <Head :title="t.currency_master" />

  <BasePageHeading :title="t.currency_master" :subtitle="t.currecy_note">
    <template #extra>
      <button v-if="can('currency', 'create')" class="btn btn-primary" @click="openCreate">
        <i class="fa fa-plus me-1"></i> {{ t.add_currency }}
      </button>
    </template>
  </BasePageHeading>

  <div class="content">
    <BaseBlock :title="t.currecy_list">
      <template #options>
        <div class="d-flex gap-2">
          <input
            v-model="search"
            type="text"
            class="form-control form-control-sm"
            :placeholder="t.search"
            style="width:180px"
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
              <th>{{ t.currency_name }}</th>
              <th>{{ t.symbol }}</th>
              <th>{{ t.decimal_places }}</th>
              <th>{{ t.default_currency }}</th>
              <th class="text-center" style="width:100px">{{ t.actions }}</th>
            </tr>
          </thead>
          <tbody>
            <tr v-if="!rows.length">
              <td colspan="7" class="text-center text-muted py-4">{{ t.no_records_found }}</td>
            </tr>
            <tr v-for="(record, index) in rows" :key="record.currencycode">
              <td class="text-muted">{{ (currencies.from ?? 1) + index }}</td>
              <td>{{ record.alternatecode }}</td>
              <td class="fw-semibold">{{ record.currencyname }}</td>
              <td>{{ record.currencysymbol }}</td>
              <td>{{ record.decimalplaces }}</td>
              <td><span v-if="record.defaultcurrency" class="badge bg-success">{{ t.yes }}</span></td>
              <td class="text-center text-nowrap">
                <button
                  v-if="canViewAction('currency')"
                  class="btn btn-sm btn-alt-info me-1"
                  :title="t.view"
                  @click="openView(record)"
                >
                  <i class="fa fa-eye"></i>
                </button>
                <button
                  v-if="can('currency', 'edit')"
                  class="btn btn-sm btn-alt-secondary me-1"
                  :title="t.edit"
                  @click="openEdit(record)"
                >
                  <i class="fa fa-pen"></i>
                </button>
                <button
                  v-if="can('currency', 'delete')"
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
          {{ t.showing }} {{ currencies.from ?? 0 }} {{ t.to }} {{ currencies.to ?? 0 }} {{ t.of }} {{ currencies.total ?? 0 }}
        </div>
        <div class="btn-group">
          <button
            class="btn btn-sm btn-alt-secondary"
            :disabled="!currencies.prev_page_url"
            @click="reloadList((currencies.current_page || 1) - 1)"
          >
            {{ t.previous }}
          </button>
          <button class="btn btn-sm btn-alt-secondary" disabled>
            {{ currencies.current_page || 1 }} / {{ currencies.last_page || 1 }}
          </button>
          <button
            class="btn btn-sm btn-alt-secondary"
            :disabled="!currencies.next_page_url"
            @click="reloadList((currencies.current_page || 1) + 1)"
          >
            {{ t.next }}
          </button>
        </div>
      </div>
    </BaseBlock>
  </div>

  <CreateCurrency
    v-if="activeModal === 'create'"
    :form="form"
    @close="closeModal"
    @submit="submitCreate"
  />

  <ViewCurrency
    v-if="activeModal === 'view'"
    :form="form"
    @close="closeModal"
  />

  <EditCurrency
    v-if="activeModal === 'edit'"
    :form="form"
    @close="closeModal"
    @submit="submitEdit"
  />

  <div
    v-if="confirmingDelete"
    class="modal fade show d-block"
    style="background:rgba(0,0,0,.45)"
  >
    <div class="modal-dialog modal-dialog-centered modal-sm">
      <div class="modal-content">
        <div class="modal-header">
          <h5 class="modal-title text-danger">
            <i class="fa fa-triangle-exclamation me-1"></i> {{ t.delete }}
          </h5>
          <button class="btn-close" @click="confirmingDelete = null"></button>
        </div>
        <div class="modal-body">{{ t.delete_currency_confirm }} <strong>{{ confirmingDelete.currencyname }}</strong>?</div>
        <div class="modal-footer">
          <button class="btn btn-alt-secondary" @click="confirmingDelete = null">{{ t.cancel }}</button>
          <button class="btn btn-danger" @click="deleteRow">{{ t.delete }}</button>
        </div>
      </div>
    </div>
  </div>
</template>
