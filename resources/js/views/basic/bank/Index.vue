<script setup>
import { computed, ref, watch } from "vue";
import { Head, router, useForm, usePage } from "@inertiajs/vue3";
import { usePermissions } from "@/composables/usePermissions";
import CreateBank from "./Create.vue";
import EditBank from "./Edit.vue";
import ViewBank from "./View.vue";

const props = defineProps({
  banks: Object,
  filters: Object,
});

const search = ref(props.filters?.search ?? "");
const perPage = ref(props.filters?.per_page ?? 10);
const rows = computed(() => props.banks?.data ?? []);
const activeModal = ref(null);
const editingId = ref(null);
const confirmingDelete = ref(null);
const { can } = usePermissions();
const page = usePage();
const t = page.props.translations.ui;

const form = useForm({
  alternatecode: "",
  bankname: "",
  arbbankname: "",
  type: null,
  acnumber: "",
  activestatus: 1,
});

function canViewAction(permission) {
  const details = page.props.auth?.formPermissions?.[permission];
  return !!(details?.all || details?.view);
}

function typeLabel(value) {
  if (Number(value) === 1) return t.deposit;
  if (Number(value) === 2) return t.customer;
  return "";
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
    "/basic/bank",
    {
      search: search.value || undefined,
      per_page: perPage.value,
      page: pageNumber,
    },
    {
      preserveScroll: true,
      preserveState: true,
      replace: true,
      only: ["banks", "filters"],
    },
  );
}

function fillForm(record) {
  form.alternatecode = record.alternatecode ?? "";
  form.bankname = record.bankname ?? "";
  form.arbbankname = record.arbbankname ?? "";
  form.type = record.type ?? null;
  form.acnumber = record.acnumber ?? "";
  form.activestatus = record.activestatus ?? 1;
}

function resetForm() {
  form.reset();
  form.type = null;
  form.acnumber = "";
  form.activestatus = 1;
  form.clearErrors();
}

function openCreate() {
  editingId.value = null;
  resetForm();
  activeModal.value = "create";
}

function openView(record) {
  editingId.value = record.bankcode;
  resetForm();
  fillForm(record);
  activeModal.value = "view";
}

function openEdit(record) {
  editingId.value = record.bankcode;
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
  form.post("/basic/bank", {
    preserveScroll: true,
    onSuccess: closeModal,
  });
}

function submitEdit() {
  form.put(`/basic/bank/${editingId.value}`, {
    preserveScroll: true,
    onSuccess: closeModal,
  });
}

function deleteRow() {
  router.delete(`/basic/bank/${confirmingDelete.value.bankcode}`, {
    preserveScroll: true,
    onSuccess: () => {
      confirmingDelete.value = null;
    },
  });
}
</script>

<template>
  <Head :title="t.bank_master" />

  <BasePageHeading :title="t.bank_master" :subtitle="t.bank_note">
    <template #extra>
      <button v-if="can('bank', 'create')" class="btn btn-primary" @click="openCreate">
        <i class="fa fa-plus me-1"></i> {{ t.add_bank }}
      </button>
    </template>
  </BasePageHeading>

  <div class="content">
    <BaseBlock :title="t.bank_list">
      <template #options>
        <div class="d-flex gap-2">
          <input
            v-model="search"
            type="text"
            class="form-control form-control-sm"
            :placeholder="t.search"
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
              <th>#</th>
              <th>{{ t.alternate_code }}</th>
              <th>{{ t.bank_name }}</th>
              <th>{{ t.bank_type }}</th>
              <th>{{ t.account_number }}</th>
              <th>{{ t.status }}</th>
              <th class="text-center" style="width: 140px">{{ t.actions }}</th>
            </tr>
          </thead>
          <tbody>
            <tr v-if="!rows.length">
              <td colspan="7" class="text-center text-muted py-4">{{ t.no_records_found }}</td>
            </tr>
            <tr v-for="(record, index) in rows" :key="record.bankcode">
              <td class="text-muted">{{ (banks.from ?? 1) + index }}</td>
              <td>{{ record.alternatecode }}</td>
              <td class="fw-semibold">{{ record.bankname }}</td>
              <td>{{ typeLabel(record.type) }}</td>
              <td>{{ record.acnumber }}</td>
              <td>
                <span class="badge" :class="record.activestatus ? 'bg-success' : 'bg-secondary'">
                  {{ record.activestatus ? t.status_active : t.status_inactive }}
                </span>
              </td>
              <td class="text-center text-nowrap">
                <button
                  v-if="canViewAction('bank')"
                  class="btn btn-sm btn-alt-info me-1"
                  :title="t.view"
                  @click="openView(record)"
                >
                  <i class="fa fa-eye"></i>
                </button>
                <button
                  v-if="can('bank', 'edit')"
                  class="btn btn-sm btn-alt-secondary me-1"
                  :title="t.edit"
                  @click="openEdit(record)"
                >
                  <i class="fa fa-pen"></i>
                </button>
                <button
                  v-if="can('bank', 'delete')"
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
          {{ t.showing }} {{ banks.from ?? 0 }} {{ t.to }} {{ banks.to ?? 0 }} {{ t.of }} {{ banks.total ?? 0 }}
        </div>
        <div class="btn-group">
          <button
            class="btn btn-sm btn-alt-secondary"
            :disabled="!banks.prev_page_url"
            @click="reloadList((banks.current_page || 1) - 1)"
          >
            {{ t.previous }}
          </button>
          <button class="btn btn-sm btn-alt-secondary" disabled>
            {{ banks.current_page || 1 }} / {{ banks.last_page || 1 }}
          </button>
          <button
            class="btn btn-sm btn-alt-secondary"
            :disabled="!banks.next_page_url"
            @click="reloadList((banks.current_page || 1) + 1)"
          >
            {{ t.next }}
          </button>
        </div>
      </div>
    </BaseBlock>
  </div>

  <CreateBank v-if="activeModal === 'create'" :form="form" @close="closeModal" @submit="submitCreate" />
  <ViewBank v-if="activeModal === 'view'" :form="form" @close="closeModal" />
  <EditBank v-if="activeModal === 'edit'" :form="form" @close="closeModal" @submit="submitEdit" />

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
        <div class="modal-body">{{ t.delete_bank_confirm }} <strong>{{ confirmingDelete.bankname }}</strong>?</div>
        <div class="modal-footer">
          <button class="btn btn-alt-secondary" @click="confirmingDelete = null">{{ t.cancel }}</button>
          <button class="btn btn-danger" @click="deleteRow">{{ t.delete }}</button>
        </div>
      </div>
    </div>
  </div>
</template>
