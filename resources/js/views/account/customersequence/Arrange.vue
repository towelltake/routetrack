<script setup>
import { computed, ref } from "vue";
import { Head, router, useForm, usePage } from "@inertiajs/vue3";
import { usePermissions } from "@/composables/usePermissions";

const props = defineProps({
  filters: { type: Object, required: true },
  customers: { type: Array, required: true },
  optionSets: { type: Object, required: true },
});
const page = usePage();
const t = page.props.translations.ui;
const { can } = usePermissions();

const form = useForm({
  routecode: props.filters.routecode,
  week: props.filters.week,
  rows: props.customers.map((row) => ({ ...row })),
  remove_customers: [],
});
const showingImportModal = ref(false);
const importInput = ref(null);
const importForm = useForm({
  routecode: props.filters.routecode,
  week: props.filters.week,
  file: null,
});

const dayColumns = computed(() => props.optionSets.dayOptions ?? []);

function reload() {
  router.get("/account/customer-sequence/arrange", {
    routecode: form.routecode,
    week: form.week,
  });
}

function openAddPage() {
  router.get("/account/customer-sequence/add", {
    routecode: form.routecode,
    week: form.week,
    source_routecode: form.routecode,
  });
}

function downloadTemplate() {
  window.location.href = `/account/customer-sequence/arrange/bulk-import/template?routecode=${form.routecode}&week=${form.week}`;
}

function onImportFileChange(event) {
  importForm.file = event.target.files?.[0] ?? null;
}

function submitImport() {
  importForm.routecode = form.routecode;
  importForm.week = form.week;
  importForm.post("/account/customer-sequence/arrange/bulk-import", {
    preserveScroll: true,
    forceFormData: true,
    onSuccess: () => {
      showingImportModal.value = false;
      importForm.file = null;
      importForm.clearErrors();
      if (importInput.value) {
        importInput.value.value = "";
      }
    },
  });
}

function submit() {
  form.post("/account/customer-sequence/arrange");
}

function toggleRemove(customerCode, checked) {
  if (checked) {
    if (!form.remove_customers.includes(customerCode)) {
      form.remove_customers.push(customerCode);
    }
    return;
  }

  form.remove_customers = form.remove_customers.filter((value) => value !== customerCode);
}
</script>

<template>
  <Head :title="t.arrange_customer" />

  <BasePageHeading
    :title="t.arrange_customer"
    :subtitle="t.arrange_customer_note"
  >
    <template #extra>
      <div class="d-flex gap-2">
        <button class="btn btn-alt-secondary" @click="router.get('/account/customer-sequence')">
          <i class="fa fa-arrow-left me-1"></i> {{ t.back }}
        </button>
        <button v-if="can('arrange customer bulk import', 'read')" class="btn btn-alt-info" @click="downloadTemplate">
          <i class="fa fa-file-excel me-1"></i> {{ t.download_format }}
        </button>
        <button v-if="can('arrange customer bulk import', 'create')" class="btn btn-alt-success" @click="showingImportModal = true">
          <i class="fa fa-upload me-1"></i> {{ t.bulk_import }}
        </button>
        <button class="btn btn-alt-primary" @click="openAddPage">
          <i class="fa fa-plus me-1"></i> {{ t.add_customer }}
        </button>
        <button class="btn btn-primary" :disabled="form.processing" @click="submit">
          <i class="fa fa-floppy-disk me-1"></i> {{ t.save }}
        </button>
      </div>
    </template>
  </BasePageHeading>

  <div class="content">
    <BaseBlock>
      <div class="row g-4 mb-4">
        <div class="col-md-5">
          <label class="form-label">{{ t.route }}</label>
          <select v-model="form.routecode" class="form-select" @change="reload">
            <option v-for="option in optionSets.routeOptions" :key="option.id" :value="option.id">
              {{ option.label }}
            </option>
          </select>
        </div>
        <div class="col-md-3">
          <label class="form-label">{{ t.week }}</label>
          <select v-model="form.week" class="form-select" @change="reload">
            <option v-for="option in optionSets.weekOptions" :key="option.id" :value="option.id">
              {{ option.label }}
            </option>
          </select>
        </div>
      </div>

      <div class="table-responsive">
        <table class="table table-hover table-vcenter fs-sm">
          <thead>
            <tr>
              <th>{{ t.remove_label }}</th>
              <th>{{ t.code }}</th>
              <th>{{ t.alternate_code }}</th>
              <th>{{ t.name }}</th>
              <th v-for="day in dayColumns" :key="day.id" class="text-center">{{ day.label.slice(0, 3) }}</th>
            </tr>
          </thead>
          <tbody>
            <tr v-if="!form.rows.length">
              <td :colspan="4 + dayColumns.length" class="text-center text-muted py-4">{{ t.no_customers_found }}</td>
            </tr>
            <tr v-for="row in form.rows" :key="row.customercode">
              <td>
                <input type="checkbox" class="form-check-input" @change="toggleRemove(row.customercode, $event.target.checked)" />
              </td>
              <td>{{ row.customercode }}</td>
              <td>{{ row.alternatecode || "-" }}</td>
              <td>{{ row.customername }}</td>
              <td v-for="day in dayColumns" :key="day.id" class="text-center">
                <input
                  v-model="row[`callrestrictiondays${day.id}`]"
                  type="checkbox"
                  class="form-check-input"
                  :true-value="1"
                  :false-value="0"
                />
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </BaseBlock>
  </div>

  <div v-if="showingImportModal" class="modal fade show d-block" style="background: rgba(0,0,0,.45)">
    <div class="modal-dialog modal-dialog-centered">
      <div class="modal-content">
        <div class="modal-header">
          <h5 class="modal-title"><i class="fa fa-upload me-1 text-success"></i> {{ t.bulk_import_arrangement }}</h5>
          <button class="btn-close" @click="showingImportModal = false"></button>
        </div>
        <div class="modal-body">
          <p class="mb-3 text-muted">
            {{ t.arrangement_import_help }}
          </p>
          <div class="mb-3">
            <button v-if="can('arrange customer bulk import', 'read')" class="btn btn-sm btn-alt-info" @click="downloadTemplate">
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
          <div v-if="importForm.errors.file" class="text-danger fs-sm mt-2">{{ importForm.errors.file }}</div>
        </div>
        <div class="modal-footer">
          <button class="btn btn-alt-secondary" @click="showingImportModal = false">{{ t.cancel }}</button>
          <button class="btn btn-success" :disabled="importForm.processing || !importForm.file" @click="submitImport">
            <i class="fa fa-upload me-1"></i>{{ importForm.processing ? t.importing : t.import_arrangement }}
          </button>
        </div>
      </div>
    </div>
  </div>
</template>
