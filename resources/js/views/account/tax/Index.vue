<script setup>
import { computed, ref, watch } from "vue";
import { Head, router, useForm, usePage } from "@inertiajs/vue3";
import { usePermissions } from "@/composables/usePermissions";

const props = defineProps({
  taxes: { type: Object, required: true },
  filters: { type: Object, default: () => ({}) },
  nextCode: { type: Number, default: null },
  optionSets: { type: Object, required: true },
});

const search = ref(props.filters?.search ?? "");
const perPage = ref(props.filters?.per_page ?? 10);
const rows = computed(() => props.taxes?.data ?? []);
const confirmingDelete = ref(null);
const modalMode = ref(null);
const { can } = usePermissions();
const page = usePage();
const t = page.props.translations.ui;
const form = useForm(defaultForm());

function canViewAction(permission) {
  const details = page.props.auth?.formPermissions?.[permission];
  return !!(details?.all || details?.view);
}
const taxTypeLabel = Object.fromEntries((props.optionSets.taxTypeOptions ?? []).map((option) => [option.id, option.label]));
const taxBaseLabel = Object.fromEntries((props.optionSets.taxBaseOptions ?? []).map((option) => [option.id, option.label]));
const isModalOpen = computed(() => modalMode.value !== null);
const isView = computed(() => modalMode.value === "view");
const isCreate = computed(() => modalMode.value === "create");
const modalTitle = computed(() => {
  if (modalMode.value === "create") return t.create_tax;
  if (modalMode.value === "view") return t.view_tax ?? t.tax;
  return t.edit_tax;
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
  router.get(
    "/account/tax",
    {
      search: search.value || undefined,
      per_page: perPage.value,
      page: pageNumber,
    },
    {
      preserveScroll: true,
      preserveState: true,
      replace: true,
      only: ["taxes", "filters"],
    },
  );
}

function defaultForm() {
  return {
    taxcode: props.nextCode ?? null,
    taxdescription: "",
    arbtaxdescription: "",
    taxtype: 1,
    taxpercentage: 0,
    taxbase: 1,
  };
}

function openCreate() {
  modalMode.value = "create";
  form.defaults(defaultForm());
  form.reset();
  form.clearErrors();
}

function openView(record) {
  modalMode.value = "view";
  hydrateForm(record);
}

function openEdit(record) {
  modalMode.value = "edit";
  hydrateForm(record);
}

function hydrateForm(record) {
  form.defaults({
    taxcode: record.taxcode ?? null,
    taxdescription: record.taxdescription ?? "",
    arbtaxdescription: record.arbtaxdescription ?? "",
    taxtype: record.taxtype ?? 1,
    taxpercentage: record.taxpercentage ?? 0,
    taxbase: record.taxbase ?? 1,
  });
  form.reset();
  form.clearErrors();
}

function closeModal() {
  modalMode.value = null;
  form.clearErrors();
}

function submit() {
  if (isView.value) {
    return;
  }

  const options = {
    preserveScroll: true,
    onSuccess: () => {
      closeModal();
      form.defaults(defaultForm());
      form.reset();
    },
  };

  if (isCreate.value) {
    form.post("/account/tax", options);
    return;
  }

  form.put(`/account/tax/${form.taxcode}`, options);
}

function deleteRow() {
  router.delete(`/account/tax/${confirmingDelete.value.taxcode}`, {
    preserveScroll: true,
    onSuccess: () => {
      confirmingDelete.value = null;
    },
  });
}
</script>

<template>
  <Head :title="t.tax" />

  <BasePageHeading :title="t.tax" :subtitle="t.tax_note">
    <template #extra>
      <button
        v-if="can('account tax', 'create')"
        class="btn btn-primary"
        @click="openCreate"
      >
        <i class="fa fa-plus me-1"></i> {{ t.add }}
      </button>
    </template>
  </BasePageHeading>

  <div class="content">
    <BaseBlock :title="t.tax_overview">
      <template #options>
        <div class="d-flex gap-2">
          <input v-model="search" type="text" class="form-control form-control-sm" :placeholder="`${t.search}...`" style="width: 220px" />
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
              <th>{{ t.description }}</th>
              <th>{{ t.arabic_description }}</th>
              <th>{{ t.tax_type }}</th>
              <th>Tax %</th>
              <th>{{ t.tax_base }}</th>
              <th class="text-center" style="width: 140px">{{ t.actions }}</th>
            </tr>
          </thead>
          <tbody>
            <tr v-if="!rows.length">
              <td colspan="8" class="text-center text-muted py-4">{{ t.no_records_found }}</td>
            </tr>
            <tr v-for="(record, index) in rows" :key="record.taxcode">
              <td class="text-muted">{{ (taxes.from ?? 1) + index }}</td>
              <td>{{ record.taxcode }}</td>
              <td class="fw-semibold">{{ record.taxdescription }}</td>
              <td>{{ record.arbtaxdescription || "-" }}</td>
              <td>{{ taxTypeLabel[record.taxtype] || "-" }}</td>
              <td>{{ record.taxpercentage }}</td>
              <td>{{ taxBaseLabel[record.taxbase] || "-" }}</td>
              <td class="text-center text-nowrap">
                <button v-if="canViewAction('account tax')" class="btn btn-sm btn-alt-info me-1" :title="t.view" @click="openView(record)">
                  <i class="fa fa-eye"></i>
                </button>
                <button v-if="can('account tax', 'edit')" class="btn btn-sm btn-alt-secondary me-1" :title="t.edit" @click="openEdit(record)">
                  <i class="fa fa-pen"></i>
                </button>
                <button v-if="can('account tax', 'delete')" class="btn btn-sm btn-alt-danger" :title="t.delete" @click="confirmingDelete = record">
                  <i class="fa fa-trash"></i>
                </button>
              </td>
            </tr>
          </tbody>
        </table>
      </div>

      <div class="d-flex justify-content-between align-items-center pt-3">
        <div class="text-muted fs-sm">
          {{ t.showing }} {{ taxes.from ?? 0 }} {{ t.to }} {{ taxes.to ?? 0 }} {{ t.of }} {{ taxes.total ?? 0 }}
        </div>
        <div class="btn-group">
          <button class="btn btn-sm btn-alt-secondary" :disabled="!taxes.prev_page_url" @click="reloadList((taxes.current_page || 1) - 1)">{{ t.previous }}</button>
          <button class="btn btn-sm btn-alt-secondary" disabled>{{ taxes.current_page || 1 }} / {{ taxes.last_page || 1 }}</button>
          <button class="btn btn-sm btn-alt-secondary" :disabled="!taxes.next_page_url" @click="reloadList((taxes.current_page || 1) + 1)">{{ t.next }}</button>
        </div>
      </div>
    </BaseBlock>
  </div>

  <div v-if="isModalOpen" class="modal fade show d-block" style="background: rgba(0,0,0,.45)">
    <div class="modal-dialog modal-dialog-centered">
      <div class="modal-content">
        <div class="modal-header">
          <h5 class="modal-title">{{ modalTitle }}</h5>
          <button class="btn-close" @click="closeModal"></button>
        </div>
        <form @submit.prevent="submit">
          <div class="modal-body">
            <div class="row g-3">
              <div class="col-md-3">
                <label class="form-label">{{ t.code }}</label>
                <input v-model="form.taxcode" class="form-control" readonly />
              </div>
              <div class="col-md-9">
                <label class="form-label">{{ t.description }} <span class="text-danger">*</span></label>
                <input v-model="form.taxdescription" class="form-control" :readonly="isView" />
                <div v-if="form.errors.taxdescription" class="text-danger fs-sm mt-1">{{ form.errors.taxdescription }}</div>
              </div>
              <div class="col-12">
                <label class="form-label">{{ t.arabic_description }}</label>
                <input v-model="form.arbtaxdescription" class="form-control" dir="rtl" :readonly="isView" />
                <div v-if="form.errors.arbtaxdescription" class="text-danger fs-sm mt-1">{{ form.errors.arbtaxdescription }}</div>
              </div>
              <div class="col-md-4">
                <label class="form-label">{{ t.tax_type }} <span class="text-danger">*</span></label>
                <select v-model="form.taxtype" class="form-select" :disabled="isView">
                  <option v-for="option in optionSets.taxTypeOptions" :key="option.id" :value="option.id">
                    {{ option.label }}
                  </option>
                </select>
                <div v-if="form.errors.taxtype" class="text-danger fs-sm mt-1">{{ form.errors.taxtype }}</div>
              </div>
              <div class="col-md-4">
                <label class="form-label">{{ t.tax_percentage }} <span class="text-danger">*</span></label>
                <input v-model="form.taxpercentage" type="number" step="0.0001" class="form-control" :readonly="isView" />
                <div v-if="form.errors.taxpercentage" class="text-danger fs-sm mt-1">{{ form.errors.taxpercentage }}</div>
              </div>
              <div class="col-md-4">
                <label class="form-label">{{ t.tax_base }} <span class="text-danger">*</span></label>
                <select v-model="form.taxbase" class="form-select" :disabled="isView">
                  <option v-for="option in optionSets.taxBaseOptions" :key="option.id" :value="option.id">
                    {{ option.label }}
                  </option>
                </select>
                <div v-if="form.errors.taxbase" class="text-danger fs-sm mt-1">{{ form.errors.taxbase }}</div>
              </div>
            </div>
          </div>
          <div class="modal-footer">
            <button type="button" class="btn btn-alt-secondary" @click="closeModal">
              {{ isView ? t.close : t.cancel }}
            </button>
            <button v-if="!isView" type="submit" class="btn btn-primary" :disabled="form.processing">
              {{ form.processing ? t.saving : t.save }}
            </button>
          </div>
        </form>
      </div>
    </div>
  </div>

  <div v-if="confirmingDelete" class="modal fade show d-block" style="background: rgba(0,0,0,.45)">
    <div class="modal-dialog modal-dialog-centered modal-sm">
      <div class="modal-content">
        <div class="modal-header">
          <h5 class="modal-title text-danger"><i class="fa fa-triangle-exclamation me-1"></i> {{ t.delete }}</h5>
          <button class="btn-close" @click="confirmingDelete = null"></button>
        </div>
        <div class="modal-body">{{ t.tax_delete_confirm }}</div>
        <div class="modal-footer">
          <button class="btn btn-alt-secondary" @click="confirmingDelete = null">{{ t.cancel }}</button>
          <button class="btn btn-danger" @click="deleteRow">{{ t.delete }}</button>
        </div>
      </div>
    </div>
  </div>
</template>
