<script setup>
import { computed, ref, watch } from "vue";
import { Head, Link, router, useForm, usePage } from "@inertiajs/vue3";
import { usePermissions } from "@/composables/usePermissions";

const props = defineProps({
  items: { type: Object, required: true },
  filters: { type: Object, default: () => ({}) },
  useAlternateCode: { type: Boolean, default: false },
});

const page = usePage();
const t = page.props.translations.ui;
const locale = page.props.locale;
const { can } = usePermissions();

const rows = computed(() => props.items?.data ?? []);
const search = ref(props.filters?.search ?? "");
const perPage = ref(props.filters?.per_page ?? 10);
const sortBy = ref(props.filters?.sort_by ?? "actualitemcode");
const sortDir = ref(props.filters?.sort_dir ?? "asc");
const showingImportModal = ref(false);
const importInput = ref(null);
const importForm = useForm({
  file: null,
});

let searchDebounce = null;
watch(search, () => {
  clearTimeout(searchDebounce);
  searchDebounce = setTimeout(() => reloadList(), 300);
});

watch(perPage, () => reloadList());

function reloadList(pageNumber = 1) {
  router.get("/inventory/item", {
    search: search.value || undefined,
    per_page: perPage.value,
    sort_by: sortBy.value,
    sort_dir: sortDir.value,
    page: pageNumber,
  }, {
    preserveScroll: true,
    preserveState: true,
    replace: true,
    only: ["items", "filters", "useAlternateCode"],
  });
}

function sort(column) {
  if (sortBy.value === column) {
    sortDir.value = sortDir.value === "asc" ? "desc" : "asc";
  } else {
    sortBy.value = column;
    sortDir.value = "asc";
  }

  reloadList();
}

function sortIcon(column) {
  if (sortBy.value !== column) return "";
  return sortDir.value === "asc" ? "fa-sort-up" : "fa-sort-down";
}

function itemGroupLabel(record) {
  return locale === "ar"
    ? (record.arbitemgroup || record.itemgroupname || "")
    : (record.itemgroupname || record.arbitemgroup || "");
}

function canViewAction(permission) {
  const details = page.props.auth?.formPermissions?.[permission];
  return !!(details?.all || details?.view);
}

function downloadTemplate() {
  window.location.href = "/inventory/item/bulk-import/template";
}

function onImportFileChange(event) {
  importForm.file = event.target.files?.[0] ?? null;
}

function submitImport() {
  importForm.post("/inventory/item/bulk-import", {
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
  <Head :title="t.items" />

  <BasePageHeading :title="t.items" :subtitle="t.item_note">
    <template #extra>
      <div class="d-flex gap-2">
        <button
          v-if="can('items bulk import', 'read')"
          class="btn btn-alt-info"
          @click="downloadTemplate"
        >
          <i class="fa fa-file-excel me-1"></i> {{ t.download_format }}
        </button>
        <button
          v-if="can('items bulk import', 'create')"
          class="btn btn-alt-success"
          @click="showingImportModal = true"
        >
          <i class="fa fa-upload me-1"></i> {{ t.bulk_import }}
        </button>
        <Link v-if="can('items', 'create')" href="/inventory/item/create" class="btn btn-primary">
          <i class="fa fa-plus me-1"></i> {{ t.add_item }}
        </Link>
      </div>
    </template>
  </BasePageHeading>

  <div class="content">
    <BaseBlock :title="t.item_list">
      <template #options>
        <div class="d-flex gap-2">
          <input
            v-model="search"
            type="text"
            class="form-control form-control-sm"
            :placeholder="`${t.search}...`"
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
              <th class="cursor-pointer" @click="sort('actualitemcode')">
                {{ t.code }}
                <i class="fa ms-1" :class="sortIcon('actualitemcode')"></i>
              </th>
              <th class="cursor-pointer" @click="sort('alternatecode')">
                {{ t.alternate_code }}
                <i class="fa ms-1" :class="sortIcon('alternatecode')"></i>
              </th>
              <th class="cursor-pointer" @click="sort('itemshortdescription')">
                {{ t.short_description }}
                <i class="fa ms-1" :class="sortIcon('itemshortdescription')"></i>
              </th>
              <th>{{ t.item_group }}</th>
              <th class="cursor-pointer text-center" @click="sort('unitspercase')">
                {{ t.upc }}
                <i class="fa ms-1" :class="sortIcon('unitspercase')"></i>
              </th>
              <th class="cursor-pointer text-center" @click="sort('activeitem')">
                {{ t.status }}
                <i class="fa ms-1" :class="sortIcon('activeitem')"></i>
              </th>
              <th class="text-center" style="width: 120px">{{ t.actions }}</th>
            </tr>
          </thead>
          <tbody>
            <tr v-if="!rows.length">
              <td colspan="7" class="text-center text-muted py-4">
                {{ t.no_records_found }}
              </td>
            </tr>
            <tr v-for="record in rows" :key="record.actualitemcode">
              <td class="fw-semibold">{{ record.actualitemcode }}</td>
              <td>{{ record.alternatecode || "-" }}</td>
              <td>
                {{ locale === "ar" ? (record.arbitemshortdescription || record.itemshortdescription) : record.itemshortdescription }}
              </td>
              <td>{{ itemGroupLabel(record) }}</td>
              <td class="text-center">{{ record.unitspercase ?? "-" }}</td>
              <td class="text-center">
                <span
                  class="fs-xs fw-semibold d-inline-block py-1 px-3 rounded-pill"
                  :class="record.activeitem === 1 ? 'bg-success-light text-success' : 'bg-danger-light text-danger'"
                >
                  {{ record.activeitem === 1 ? t.status_active : t.status_inactive }}
                </span>
              </td>
              <td class="text-center text-nowrap">
                <Link
                  v-if="canViewAction('items')"
                  class="btn btn-sm btn-alt-info me-1"
                  :href="`/inventory/item/${record.actualitemcode}`"
                >
                  <i class="fa fa-eye"></i>
                </Link>
                <Link
                  v-if="can('items', 'edit')"
                  class="btn btn-sm btn-alt-secondary"
                  :href="`/inventory/item/${record.actualitemcode}/edit`"
                >
                  <i class="fa fa-pen"></i>
                </Link>
              </td>
            </tr>
          </tbody>
        </table>
      </div>

      <div class="d-flex justify-content-between align-items-center pt-3">
        <div class="text-muted fs-sm">
          {{ t.showing }} {{ items.from ?? 0 }} {{ t.to }} {{ items.to ?? 0 }} {{ t.of }} {{ items.total ?? 0 }}
        </div>
        <div class="btn-group">
          <button class="btn btn-sm btn-alt-secondary" :disabled="!items.prev_page_url" @click="reloadList((items.current_page || 1) - 1)">{{ t.previous }}</button>
          <button class="btn btn-sm btn-alt-secondary" disabled>{{ items.current_page || 1 }} / {{ items.last_page || 1 }}</button>
          <button class="btn btn-sm btn-alt-secondary" :disabled="!items.next_page_url" @click="reloadList((items.current_page || 1) + 1)">{{ t.next }}</button>
        </div>
      </div>
    </BaseBlock>
  </div>

  <div v-if="showingImportModal" class="modal fade show d-block" style="background: rgba(0,0,0,.45)">
    <div class="modal-dialog modal-dialog-centered">
      <div class="modal-content">
        <div class="modal-header">
          <h5 class="modal-title"><i class="fa fa-upload me-1 text-success"></i> {{ t.bulk_import_items }}</h5>
          <button class="btn-close" @click="showingImportModal = false"></button>
        </div>
        <div class="modal-body">
          <p class="mb-3 text-muted">
            {{ t.bulk_import_items_help }}
          </p>
          <div class="mb-3">
            <button
              v-if="can('items bulk import', 'read')"
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
          <button class="btn btn-success" :disabled="importForm.processing || !importForm.file" @click="submitImport">
            <i class="fa fa-upload me-1"></i>{{ importForm.processing ? t.importing : t.import_items }}
          </button>
        </div>
      </div>
    </div>
  </div>
</template>
