<script setup>
import { computed, ref, watch } from "vue";
import { Head, router, useForm, usePage } from "@inertiajs/vue3";
import { usePermissions } from "@/composables/usePermissions";

const props = defineProps({
  categories: Object,
  filters: Object,
  nextCode: Number,
});

const search = ref(props.filters?.search ?? "");
const perPage = ref(props.filters?.per_page ?? 10);
const rows = computed(() => props.categories?.data ?? []);
const confirmingDelete = ref(null);
const modalMode = ref(null);
const { can } = usePermissions();
const page = usePage();
const t = page.props.translations.ui;
const statusOptions = [
  { id: 1, label: t.active },
  { id: 0, label: t.inactive },
];

const form = useForm(defaultForm());

function canViewAction(permission) {
  const details = page.props.auth?.formPermissions?.[permission];
  return !!(details?.all || details?.view);
}

const isModalOpen = computed(() => modalMode.value !== null);
const isView = computed(() => modalMode.value === "view");
const isCreate = computed(() => modalMode.value === "create");
const modalTitle = computed(() => {
  if (modalMode.value === "create") return `${t.create} ${t.customer_category}`;
  if (modalMode.value === "view") return `${t.view} ${t.customer_category}`;
  return `${t.edit} ${t.customer_category}`;
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
    "/account/customer-category",
    {
      search: search.value || undefined,
      per_page: perPage.value,
      page: pageNumber,
    },
    {
      preserveScroll: true,
      preserveState: true,
      replace: true,
      only: ["categories", "filters", "nextCode"],
    },
  );
}

function defaultForm() {
  return {
    categoryid: props.nextCode ?? null,
    alternatecode: "",
    categoryname: "",
    arbcategoryname: "",
    activestatus: 1,
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
    categoryid: record.categoryid ?? null,
    alternatecode: record.alternatecode ?? "",
    categoryname: record.categoryname ?? "",
    arbcategoryname: record.arbcategoryname ?? "",
    activestatus: record.activestatus ?? 1,
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
    form.post("/account/customer-category", options);
    return;
  }

  form.put(`/account/customer-category/${form.categoryid}`, options);
}

function deleteRow() {
  router.delete(`/account/customer-category/${confirmingDelete.value.categoryid}`, {
    preserveScroll: true,
    onSuccess: () => {
      confirmingDelete.value = null;
    },
  });
}
</script>

<template>
  <Head :title="t.customer_category" />

  <BasePageHeading :title="t.customer_category" :subtitle="t.customer_category_note">
    <template #extra>
      <button
        v-if="can('account customer category', 'create')"
        class="btn btn-primary"
        @click="openCreate"
      >
        <i class="fa fa-plus me-1"></i> {{ t.add }}
      </button>
    </template>
  </BasePageHeading>

  <div class="content">
    <BaseBlock :title="t.customer_category_overview">
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
              <th>{{ t.status }}</th>
              <th class="text-center" style="width: 140px">{{ t.actions }}</th>
            </tr>
          </thead>
          <tbody>
            <tr v-if="!rows.length">
              <td colspan="6" class="text-center text-muted py-4">{{ t.no_records_found }}</td>
            </tr>
            <tr v-for="(record, index) in rows" :key="record.categoryid">
              <td class="text-muted">{{ (categories.from ?? 1) + index }}</td>
              <td>{{ record.categoryid }}</td>
              <td>{{ record.alternatecode || "-" }}</td>
              <td class="fw-semibold">{{ record.categoryname }}</td>
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
                  v-if="canViewAction('account customer category')"
                  class="btn btn-sm btn-alt-info me-1"
                  :title="t.view"
                  @click="openView(record)"
                >
                  <i class="fa fa-eye"></i>
                </button>
                <button
                  v-if="can('account customer category', 'edit')"
                  class="btn btn-sm btn-alt-secondary me-1"
                  :title="t.edit"
                  @click="openEdit(record)"
                >
                  <i class="fa fa-pen"></i>
                </button>
                <button
                  v-if="can('account customer category', 'delete')"
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
          {{ t.showing }} {{ categories.from ?? 0 }} {{ t.to }} {{ categories.to ?? 0 }} {{ t.of }}
          {{ categories.total ?? 0 }}
        </div>
        <div class="btn-group">
          <button
            class="btn btn-sm btn-alt-secondary"
            :disabled="!categories.prev_page_url"
            @click="reloadList((categories.current_page || 1) - 1)"
          >
            {{ t.previous }}
          </button>
          <button class="btn btn-sm btn-alt-secondary" disabled>
            {{ categories.current_page || 1 }} / {{ categories.last_page || 1 }}
          </button>
          <button
            class="btn btn-sm btn-alt-secondary"
            :disabled="!categories.next_page_url"
            @click="reloadList((categories.current_page || 1) + 1)"
          >
            {{ t.next }}
          </button>
        </div>
      </div>
    </BaseBlock>
  </div>

  <div
    v-if="isModalOpen"
    class="modal fade show d-block"
    style="background: rgba(0,0,0,.45)"
  >
    <div class="modal-dialog modal-dialog-centered">
      <div class="modal-content">
        <div class="modal-header">
          <h5 class="modal-title">{{ modalTitle }}</h5>
          <button class="btn-close" @click="closeModal"></button>
        </div>
        <form @submit.prevent="submit">
          <div class="modal-body">
            <div class="row g-3">
              <div class="col-md-4">
                <label class="form-label">{{ t.code }}</label>
                <input v-model="form.categoryid" class="form-control" readonly />
              </div>

              <div class="col-md-8">
                <label class="form-label">{{ t.alternate_code }}</label>
                <input v-model="form.alternatecode" class="form-control" :readonly="isView" />
                <div v-if="form.errors.alternatecode" class="text-danger fs-sm mt-1">
                  {{ form.errors.alternatecode }}
                </div>
              </div>

              <div class="col-md-8">
                <label class="form-label">{{ t.name }} <span class="text-danger">*</span></label>
                <input v-model="form.categoryname" class="form-control" :readonly="isView" />
                <div v-if="form.errors.categoryname" class="text-danger fs-sm mt-1">
                  {{ form.errors.categoryname }}
                </div>
              </div>

              <div class="col-md-4">
                <label class="form-label">{{ t.status }} <span class="text-danger">*</span></label>
                <select v-model="form.activestatus" class="form-select" :disabled="isView">
                  <option
                    v-for="option in statusOptions"
                    :key="option.id"
                    :value="option.id"
                  >
                    {{ option.label }}
                  </option>
                </select>
              </div>

              <div class="col-12">
                <label class="form-label">{{ t.arabic_name }}</label>
                <input
                  v-model="form.arbcategoryname"
                  class="form-control"
                  dir="rtl"
                  :readonly="isView"
                />
                <div v-if="form.errors.arbcategoryname" class="text-danger fs-sm mt-1">
                  {{ form.errors.arbcategoryname }}
                </div>
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
          {{ t.delete }} <strong>{{ confirmingDelete.categoryname }}</strong>?
        </div>
        <div class="modal-footer">
          <button class="btn btn-alt-secondary" @click="confirmingDelete = null">{{ t.cancel }}</button>
          <button class="btn btn-danger" @click="deleteRow">{{ t.delete }}</button>
        </div>
      </div>
    </div>
  </div>
</template>
