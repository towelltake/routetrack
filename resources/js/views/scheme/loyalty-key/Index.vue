<script setup>
import { computed, ref, watch } from "vue";
import { Head, router, usePage } from "@inertiajs/vue3";
import { usePermissions } from "@/composables/usePermissions";

const props = defineProps({
  available: { type: Boolean, default: false },
  keys: { type: Object, required: true },
  filters: { type: Object, default: () => ({}) },
  optionSets: { type: Object, required: true },
});

const search = ref(props.filters?.search ?? "");
const perPage = ref(props.filters?.per_page ?? 10);
const confirmingDelete = ref(null);
const rows = computed(() => props.keys?.data ?? []);
const statusLabels = computed(() => props.optionSets?.statusLabels ?? {});
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
  searchDebounce = setTimeout(() => reloadList(), 300);
});

watch(perPage, () => reloadList());

function reloadList(pageNumber = 1) {
  router.get(
    "/scheme/loyalty/loyalty-key",
    {
      search: search.value || undefined,
      per_page: perPage.value,
      page: pageNumber,
    },
    {
      preserveScroll: true,
      preserveState: true,
      replace: true,
      only: ["available", "keys", "filters"],
    },
  );
}

function deleteRow() {
  router.delete(`/scheme/loyalty/loyalty-key/${confirmingDelete.value.loyaltykeyid}`, {
    preserveScroll: true,
    onSuccess: () => {
      confirmingDelete.value = null;
    },
  });
}
</script>

<template>
  <Head :title="t.loyalty_key" />

  <BasePageHeading :title="t.loyalty_key" :subtitle="t.loyalty_key_note">
    <template #extra>
      <button
        v-if="available && can('loyalty key', 'create')"
        class="btn btn-primary"
        @click="router.get('/scheme/loyalty/loyalty-key/create')"
      >
        <i class="fa fa-plus me-1"></i> {{ t.create_loyalty_key }}
      </button>
    </template>
  </BasePageHeading>

  <div class="content">
    <div v-if="!available" class="alert alert-warning d-flex align-items-center" role="alert">
      <i class="fa fa-triangle-exclamation me-2"></i>
      <div>Legacy tables <code>loyaltykeyheader</code> and <code>loyaltykeydetail</code> are required for the loyalty key workflow.</div>
    </div>

    <BaseBlock :title="t.loyalty_key_overview">
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
              <th>{{ t.loyalty_key }}</th>
              <th>{{ t.description }}</th>
              <th>{{ t.remarks }}</th>
              <th>{{ t.plans }}</th>
              <th>{{ t.start_date }}</th>
              <th>{{ t.end_date }}</th>
              <th>{{ t.status }}</th>
              <th class="text-center" style="width: 140px">{{ t.actions }}</th>
            </tr>
          </thead>
          <tbody>
            <tr v-if="!rows.length">
              <td colspan="9" class="text-center text-muted py-4">{{ t.no_loyalty_keys_found }}</td>
            </tr>
            <tr v-for="(record, index) in rows" :key="record.loyaltykeyid">
              <td class="text-muted">{{ (keys.from ?? 1) + index }}</td>
              <td>{{ record.loyaltykeyid }}</td>
              <td>
                <div class="fw-semibold">{{ record.description || "-" }}</div>
                <div class="text-muted">{{ record.arabicdescription || "-" }}</div>
              </td>
              <td>{{ record.remarks || "-" }}</td>
              <td>{{ record.plan_count ?? 0 }}</td>
              <td>{{ record.first_startdate || "-" }}</td>
              <td>{{ record.last_enddate || "-" }}</td>
              <td>
                <span class="badge" :class="record.active ? 'bg-success-subtle text-success' : 'bg-danger-subtle text-danger'">
                  {{ statusLabels[record.active] || "-" }}
                </span>
              </td>
              <td class="text-center text-nowrap">
                <button v-if="canViewAction('loyalty key')" class="btn btn-sm btn-alt-info me-1" @click="router.get(`/scheme/loyalty/loyalty-key/${record.loyaltykeyid}`)">
                  <i class="fa fa-eye"></i>
                </button>
                <button v-if="can('loyalty key', 'edit')" class="btn btn-sm btn-alt-secondary me-1" @click="router.get(`/scheme/loyalty/loyalty-key/${record.loyaltykeyid}/edit`)">
                  <i class="fa fa-pen"></i>
                </button>
                <button v-if="can('loyalty key', 'delete')" class="btn btn-sm btn-alt-danger" @click="confirmingDelete = record">
                  <i class="fa fa-trash"></i>
                </button>
              </td>
            </tr>
          </tbody>
        </table>
      </div>

      <div class="d-flex justify-content-between align-items-center pt-3">
        <div class="text-muted fs-sm">{{ t.showing }} {{ keys.from ?? 0 }} {{ t.to }} {{ keys.to ?? 0 }} {{ t.of }} {{ keys.total ?? 0 }}</div>
        <div class="btn-group">
          <button class="btn btn-sm btn-alt-secondary" :disabled="!keys.prev_page_url" @click="reloadList((keys.current_page || 1) - 1)">{{ t.previous }}</button>
          <button class="btn btn-sm btn-alt-secondary" disabled>{{ keys.current_page || 1 }} / {{ keys.last_page || 1 }}</button>
          <button class="btn btn-sm btn-alt-secondary" :disabled="!keys.next_page_url" @click="reloadList((keys.current_page || 1) + 1)">{{ t.next }}</button>
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
        <div class="modal-body">{{ t.delete }} <strong>{{ confirmingDelete.description }}</strong>?</div>
        <div class="modal-footer">
          <button class="btn btn-alt-secondary" @click="confirmingDelete = null">{{ t.cancel }}</button>
          <button class="btn btn-danger" @click="deleteRow">{{ t.delete }}</button>
        </div>
      </div>
    </div>
  </div>
</template>
