<script setup>
import { computed, ref, watch } from "vue";
import { Head, router, usePage } from "@inertiajs/vue3";
import { usePermissions } from "@/composables/usePermissions";

const props = defineProps({
  available: { type: Boolean, default: false },
  surveys: { type: Object, required: true },
  filters: { type: Object, default: () => ({}) },
  formMeta: { type: Object, required: true },
});

const search = ref(props.filters?.search ?? "");
const perPage = ref(props.filters?.per_page ?? 10);
const confirmingDelete = ref(null);
const rows = computed(() => props.surveys?.data ?? []);
const { can } = usePermissions();
const t = usePage().props.translations.ui;

let searchDebounce = null;
watch(search, () => {
  clearTimeout(searchDebounce);
  searchDebounce = setTimeout(() => reloadList(), 300);
});
watch(perPage, () => reloadList());

function reloadList(pageNumber = 1) {
  router.get(
    props.formMeta.indexUrl,
    { search: search.value || undefined, per_page: perPage.value, page: pageNumber },
    { preserveScroll: true, preserveState: true, replace: true, only: ["surveys", "filters", "available"] },
  );
}

function deleteRow() {
  router.delete(`${props.formMeta.baseUrl}/${confirmingDelete.value.surveydefkey}`, {
    preserveScroll: true,
    onSuccess: () => {
      confirmingDelete.value = null;
    },
  });
}
</script>

<template>
  <Head :title="t.survey_definition" />

  <BasePageHeading :title="t.survey_definition" :subtitle="t.survey_definition_note">
    <template #extra>
      <button
        v-if="available && can('survey', 'create')"
        class="btn btn-primary"
        @click="router.get('/merchandizing/survey/create')"
      >
        <i class="fa fa-plus me-1"></i> {{ t.add }}
      </button>
    </template>
  </BasePageHeading>

  <div class="content">
    <div v-if="!available" class="alert alert-warning d-flex align-items-center" role="alert">
      <i class="fa fa-triangle-exclamation me-2"></i>
      <div>{{ t.legacy_survey_tables_required }}</div>
    </div>

    <BaseBlock :title="t.survey_definition_overview">
      <template #options>
        <div class="d-flex gap-2">
          <input v-model="search" type="text" class="form-control form-control-sm" :placeholder="t.search" style="width:220px" />
          <select v-model="perPage" class="form-select form-select-sm" style="width:90px">
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
              <th>{{ t.survey_description }}</th>
              <th>{{ t.survey_index }}</th>
              <th>{{ t.survey_type }}</th>
              <th>{{ t.lookup_type }}</th>
              <th>{{ t.status }}</th>
              <th class="text-center" style="width:140px">{{ t.actions }}</th>
            </tr>
          </thead>
          <tbody>
            <tr v-if="!rows.length">
              <td colspan="7" class="text-center text-muted py-4">{{ t.no_survey_definitions_found }}</td>
            </tr>
            <tr v-for="(record, index) in rows" :key="record.surveydefkey">
              <td class="text-muted">{{ (surveys.from ?? 1) + index }}</td>
              <td class="fw-semibold">{{ record.surveyprompt }}</td>
              <td>{{ record.surveyindex }}</td>
              <td>{{ record.surveyrectype_label }}</td>
              <td>{{ record.lookuptype_label }}</td>
              <td>
                <span class="badge" :class="record.activestatus ? 'bg-success-subtle text-success' : 'bg-danger-subtle text-danger'">
                  {{ record.activestatus ? t.status_active : t.status_inactive }}
                </span>
              </td>
              <td class="text-center text-nowrap">
                <button v-if="can('survey', 'view')" class="btn btn-sm btn-alt-info me-1" @click="router.get(`/merchandizing/survey/${record.surveydefkey}`)">
                  <i class="fa fa-eye"></i>
                </button>
                <button v-if="can('survey', 'edit')" class="btn btn-sm btn-alt-secondary me-1" @click="router.get(`/merchandizing/survey/${record.surveydefkey}/edit`)">
                  <i class="fa fa-pen"></i>
                </button>
                <button v-if="can('survey', 'delete')" class="btn btn-sm btn-alt-danger" @click="confirmingDelete = record">
                  <i class="fa fa-trash"></i>
                </button>
              </td>
            </tr>
          </tbody>
        </table>
      </div>

      <div class="d-flex justify-content-between align-items-center pt-3">
        <div class="text-muted fs-sm">{{ t.showing }} {{ surveys.from ?? 0 }} {{ t.to }} {{ surveys.to ?? 0 }} {{ t.of }} {{ surveys.total ?? 0 }}</div>
        <div class="btn-group">
          <button class="btn btn-sm btn-alt-secondary" :disabled="!surveys.prev_page_url" @click="reloadList((surveys.current_page || 1) - 1)">{{ t.previous }}</button>
          <button class="btn btn-sm btn-alt-secondary" disabled>{{ surveys.current_page || 1 }} / {{ surveys.last_page || 1 }}</button>
          <button class="btn btn-sm btn-alt-secondary" :disabled="!surveys.next_page_url" @click="reloadList((surveys.current_page || 1) + 1)">{{ t.next }}</button>
        </div>
      </div>
    </BaseBlock>
  </div>

  <div v-if="confirmingDelete" class="modal fade show d-block" style="background: rgba(0,0,0,.45)">
    <div class="modal-dialog modal-dialog-centered modal-sm">
      <div class="modal-content">
        <div class="modal-header">
          <h5 class="modal-title text-danger"><i class="fa fa-triangle-exclamation me-1"></i> {{ t.delete }}</h5>
          <button class="btn-close" @click="confirmingDelete = null"></button>
        </div>
        <div class="modal-body">{{ t.delete }} {{ t.survey }} <strong>{{ confirmingDelete.surveyprompt }}</strong>?</div>
        <div class="modal-footer">
          <button class="btn btn-alt-secondary" @click="confirmingDelete = null">{{ t.cancel }}</button>
          <button class="btn btn-danger" @click="deleteRow">{{ t.delete }}</button>
        </div>
      </div>
    </div>
  </div>
</template>
