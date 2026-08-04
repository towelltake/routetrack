<script setup>
import { computed, ref, watch } from "vue";
import { Head, router, usePage } from "@inertiajs/vue3";
import { usePermissions } from "@/composables/usePermissions";

const props = defineProps({
  groups: { type: Object, required: true },
  filters: { type: Object, default: () => ({}) },
  groupMeta: { type: Object, required: true },
});

const search = ref(props.filters?.search ?? "");
const perPage = ref(props.filters?.per_page ?? 10);
const confirmingDelete = ref(null);
const rows = computed(() => props.groups?.data ?? []);
const { can } = usePermissions();
const perm = computed(() => props.groupMeta.permission ?? 'promotion');
const t = usePage().props.translations.ui;
const pageTitle = computed(() => props.groupMeta.permission === "assignment group" ? t.assignment_group : t.qualification_group);
const pageSubtitle = computed(() => props.groupMeta.permission === "assignment group" ? t.assignment_group_note : t.qualification_group_note);
const pageOverview = computed(() => props.groupMeta.permission === "assignment group" ? t.assignment_group_overview : t.qualification_group_overview);
const usedByLabel = computed(() => props.groupMeta.permission === "assignment group" ? t.assigned_items : t.qualified_items);
const createLabel = computed(() => props.groupMeta.permission === "assignment group" ? t.create_assignment_group : t.create_qualification_group);

let searchDebounce = null;
watch(search, () => {
  clearTimeout(searchDebounce);
  searchDebounce = setTimeout(() => reloadList(), 300);
});

watch(perPage, () => {
  reloadList();
});

function reloadList(pageNumber = 1) {
  router.get(
    props.groupMeta.indexUrl,
    {
      search: search.value || undefined,
      per_page: perPage.value,
      page: pageNumber,
    },
    {
      preserveScroll: true,
      preserveState: true,
      replace: true,
      only: ["groups", "filters"],
    },
  );
}

function deleteRow() {
  router.delete(`${props.groupMeta.baseUrl}/${confirmingDelete.value.groupnumber}`, {
    preserveScroll: true,
    onSuccess: () => {
      confirmingDelete.value = null;
    },
  });
}
</script>

<template>
  <Head :title="pageTitle" />

  <BasePageHeading :title="pageTitle" :subtitle="pageSubtitle">
    <template #extra>
      <button
        v-if="can(perm, 'create')"
        class="btn btn-primary"
        @click="router.get(groupMeta.createUrl)"
      >
        <i class="fa fa-plus me-1"></i> {{ createLabel }}
      </button>
    </template>
  </BasePageHeading>

  <div class="content">
    <BaseBlock :title="pageOverview">
      <template #options>
        <div class="d-flex gap-2">
          <input v-model="search" type="text" class="form-control form-control-sm" :placeholder="t.search" style="width: 220px" />
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
              <th>{{ t.group_number }}</th>
              <th>{{ t.group_description }}</th>
              <th>{{ t.arabic_description }}</th>
              <th>{{ usedByLabel }}</th>
              <th class="text-center" style="width: 140px">{{ t.actions }}</th>
            </tr>
          </thead>
          <tbody>
            <tr v-if="!rows.length">
              <td colspan="6" class="text-center text-muted py-4">{{ t.no_records_found }}</td>
            </tr>
            <tr v-for="(record, index) in rows" :key="record.groupnumber">
              <td class="text-muted">{{ (groups.from ?? 1) + index }}</td>
              <td>{{ record.groupnumber }}</td>
              <td class="fw-semibold">{{ record.groupdescription }}</td>
              <td>{{ record.arbgroupdescription || "-" }}</td>
              <td>{{ record.item_count ?? 0 }}</td>
              <td class="text-center text-nowrap">
                <button
                  class="btn btn-sm btn-alt-info me-1"
                  :title="t.view"
                  @click="router.get(`${groupMeta.baseUrl}/${record.groupnumber}`)"
                >
                  <i class="fa fa-eye"></i>
                </button>
                <button
                  v-if="can(perm, 'edit')"
                  class="btn btn-sm btn-alt-secondary me-1"
                  :title="t.edit"
                  @click="router.get(`${groupMeta.baseUrl}/${record.groupnumber}/edit`)"
                >
                  <i class="fa fa-pen"></i>
                </button>
                <button
                  v-if="can(perm, 'delete')"
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
          {{ t.showing }} {{ groups.from ?? 0 }} {{ t.to }} {{ groups.to ?? 0 }} {{ t.of }} {{ groups.total ?? 0 }}
        </div>
        <div class="btn-group">
          <button class="btn btn-sm btn-alt-secondary" :disabled="!groups.prev_page_url" @click="reloadList((groups.current_page || 1) - 1)">{{ t.previous }}</button>
          <button class="btn btn-sm btn-alt-secondary" disabled>{{ groups.current_page || 1 }} / {{ groups.last_page || 1 }}</button>
          <button class="btn btn-sm btn-alt-secondary" :disabled="!groups.next_page_url" @click="reloadList((groups.current_page || 1) + 1)">{{ t.next }}</button>
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
        <div class="modal-body">{{ t.delete }} <strong>{{ confirmingDelete.groupdescription }}</strong>?</div>
        <div class="modal-footer">
          <button class="btn btn-alt-secondary" @click="confirmingDelete = null">{{ t.cancel }}</button>
          <button class="btn btn-danger" @click="deleteRow">{{ t.delete }}</button>
        </div>
      </div>
    </div>
  </div>
</template>
