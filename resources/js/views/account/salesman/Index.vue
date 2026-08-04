<script setup>
import { computed, ref, watch } from "vue";
import { Head, router, useForm, usePage } from "@inertiajs/vue3";
import { usePermissions } from "@/composables/usePermissions";

const props = defineProps({
  salesmen: Object,
  filters: Object,
});

const search = ref(props.filters?.search ?? "");
const perPage = ref(props.filters?.per_page ?? 10);
const rows = computed(() => props.salesmen?.data ?? []);
const confirmingDelete = ref(null);
const showingImportModal = ref(false);
const importInput = ref(null);
const importForm = useForm({
  file: null,
});
const { can } = usePermissions();
const page = usePage();
const t = page.props.translations.ui;

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
    "/account/salesman",
    {
      search: search.value || undefined,
      per_page: perPage.value,
      page: pageNumber,
    },
    {
      preserveScroll: true,
      preserveState: true,
      replace: true,
      only: ["salesmen", "filters"],
    },
  );
}

function deleteRow() {
  router.delete(`/account/salesman/${confirmingDelete.value.salesmancode}`, {
    preserveScroll: true,
    onSuccess: () => {
      confirmingDelete.value = null;
    },
  });
}

function downloadTemplate() {
  window.location.href = "/account/salesman/bulk-import/template";
}

function onImportFileChange(event) {
  importForm.file = event.target.files?.[0] ?? null;
}

function submitImport() {
  importForm.post("/account/salesman/bulk-import", {
    preserveScroll: true,
    forceFormData: true,
    onSuccess: () => {
      showingImportModal.value = false;
      importForm.reset();
      importForm.clearErrors();
      if (importInput.value) {
        importInput.value.value = "";
      }
    },
  });
}
</script>

<template>
  <Head :title="t.salesman" />

  <BasePageHeading :title="t.salesman" :subtitle="t.salesman_note">
    <template #extra>
      <div class="d-flex gap-2">
        <button
          v-if="can('account salesman bulk import', 'read')"
          class="btn btn-alt-info"
          @click="downloadTemplate"
        >
          <i class="fa fa-file-excel me-1"></i> {{ t.download_format }}
        </button>
        <button
          v-if="can('account salesman bulk import', 'create')"
          class="btn btn-alt-success"
          @click="showingImportModal = true"
        >
          <i class="fa fa-upload me-1"></i> {{ t.bulk_import }}
        </button>
        <button
          v-if="can('account salesman', 'create')"
          class="btn btn-primary"
          @click="router.get('/account/salesman/create')"
        >
          <i class="fa fa-plus me-1"></i> {{ t.add }}
        </button>
      </div>
    </template>
  </BasePageHeading>

  <div class="content">
    <BaseBlock :title="t.salesman_overview">
      <template #options>
        <div class="d-flex gap-2">
          <input
            v-model="search"
            type="text"
            class="form-control form-control-sm"
            :placeholder="`${t.search}...`"
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
              <th>{{ t.name }}</th>
              <th>{{ t.type }}</th>
              <th>{{ t.parent_company }}</th>
              <th>{{ t.status }}</th>
              <th class="text-center" style="width: 140px">{{ t.actions }}</th>
            </tr>
          </thead>
          <tbody>
            <tr v-if="!rows.length">
              <td colspan="8" class="text-center text-muted py-4">{{ t.no_records_found }}</td>
            </tr>
            <tr v-for="(record, index) in rows" :key="record.salesmancode">
              <td class="text-muted">{{ (salesmen.from ?? 1) + index }}</td>
              <td>{{ record.salesmancode }}</td>
              <td>{{ record.alternatesalesmancode }}</td>
              <td class="fw-semibold">{{ record.salesmanname1 }}</td>
              <td>{{ record.type_label }}</td>
              <td>{{ record.parentcompanyname || "-" }}</td>
              <td>
                <span
                  class="badge"
                  :class="record.activestatus ? 'bg-success-subtle text-success' : 'bg-danger-subtle text-danger'"
                >
                  {{ record.activestatus ? t.active : t.inactive }}
                </span>
              </td>
              <td class="text-center text-nowrap">
                <button
                  v-if="canViewAction('account salesman')"
                  class="btn btn-sm btn-alt-info me-1"
                  :title="t.view"
                  @click="router.get(`/account/salesman/${record.salesmancode}`)"
                >
                  <i class="fa fa-eye"></i>
                </button>
                <button
                  v-if="can('account salesman', 'edit')"
                  class="btn btn-sm btn-alt-secondary me-1"
                  :title="t.edit"
                  @click="router.get(`/account/salesman/${record.salesmancode}/edit`)"
                >
                  <i class="fa fa-pen"></i>
                </button>
                <button
                  v-if="can('account salesman', 'delete')"
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
          {{ t.showing }} {{ salesmen.from ?? 0 }} {{ t.to }} {{ salesmen.to ?? 0 }} {{ t.of }}
          {{ salesmen.total ?? 0 }}
        </div>
        <div class="btn-group">
          <button
            class="btn btn-sm btn-alt-secondary"
            :disabled="!salesmen.prev_page_url"
            @click="reloadList((salesmen.current_page || 1) - 1)"
          >
            {{ t.previous }}
          </button>
          <button class="btn btn-sm btn-alt-secondary" disabled>
            {{ salesmen.current_page || 1 }} / {{ salesmen.last_page || 1 }}
          </button>
          <button
            class="btn btn-sm btn-alt-secondary"
            :disabled="!salesmen.next_page_url"
            @click="reloadList((salesmen.current_page || 1) + 1)"
          >
            {{ t.next }}
          </button>
        </div>
      </div>
    </BaseBlock>
  </div>

  <div
    v-if="showingImportModal"
    class="modal fade show d-block"
    style="background: rgba(0,0,0,.45)"
  >
    <div class="modal-dialog modal-dialog-centered">
      <div class="modal-content">
        <div class="modal-header">
          <h5 class="modal-title">
            <i class="fa fa-upload me-1 text-success"></i> {{ t.bulk_import_salesman }}
          </h5>
          <button class="btn-close" @click="showingImportModal = false"></button>
        </div>
        <div class="modal-body">
          <p class="mb-3 text-muted">
            {{ t.bulk_import_salesman_help }}
          </p>
          <div class="mb-3">
            <button
              v-if="can('account salesman bulk import', 'read')"
              class="btn btn-sm btn-alt-info"
              @click="downloadTemplate"
            >
              <i class="fa fa-download me-1"></i> {{ t.download_format }}
            </button>
          </div>
          <label class="form-label">{{ t.excel_file }}</label>
          <input
            ref="importInput"
            type="file"
            class="form-control"
            accept=".xls,.xml"
            @change="onImportFileChange"
          />
          <div v-if="importForm.errors.file" class="text-danger fs-sm mt-2">
            {{ importForm.errors.file }}
          </div>
        </div>
        <div class="modal-footer">
          <button class="btn btn-alt-secondary" @click="showingImportModal = false">{{ t.cancel }}</button>
          <button
            class="btn btn-success"
            :disabled="importForm.processing || !importForm.file"
            @click="submitImport"
          >
            <i class="fa fa-upload me-1"></i>
            {{ importForm.processing ? t.importing : t.import_salesman }}
          </button>
        </div>
      </div>
    </div>
  </div>

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
        <div class="modal-body">
          {{ t.delete }} <strong>{{ confirmingDelete.salesmanname1 }}</strong>?
        </div>
        <div class="modal-footer">
          <button class="btn btn-alt-secondary" @click="confirmingDelete = null">{{ t.cancel }}</button>
          <button class="btn btn-danger" @click="deleteRow">{{ t.delete }}</button>
        </div>
      </div>
    </div>
  </div>
</template>
