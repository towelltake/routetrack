<script setup>
import { computed, ref, watch } from "vue";
import { Head, router, usePage } from "@inertiajs/vue3";
import { usePermissions } from "@/composables/usePermissions";

const props = defineProps({
  available: { type: Boolean, default: false },
  plans: { type: Object, required: true },
  filters: { type: Object, default: () => ({}) },
  optionSets: { type: Object, required: true },
});

const search = ref(props.filters?.search ?? "");
const perPage = ref(props.filters?.per_page ?? 10);
const confirmingDelete = ref(null);
const rows = computed(() => props.plans?.data ?? []);
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
    "/scheme/loyalty/loyalty-plan",
    {
      search: search.value || undefined,
      per_page: perPage.value,
      page: pageNumber,
    },
    {
      preserveScroll: true,
      preserveState: true,
      replace: true,
      only: ["available", "plans", "filters"],
    },
  );
}

function deleteRow() {
  router.delete(`/scheme/loyalty/loyalty-plan/${confirmingDelete.value.loyaltyplanid}`, {
    preserveScroll: true,
    onSuccess: () => {
      confirmingDelete.value = null;
    },
  });
}
</script>

<template>
  <Head :title="t.loyalty_plan" />

  <BasePageHeading :title="t.loyalty_plan" :subtitle="t.loyalty_plan_note">
    <template #extra>
      <button
        v-if="available && can('loyalty plan', 'create')"
        class="btn btn-primary"
        @click="router.get('/scheme/loyalty/loyalty-plan/create')"
      >
        <i class="fa fa-plus me-1"></i> {{ t.create_loyalty_plan }}
      </button>
    </template>
  </BasePageHeading>

  <div class="content">
    <div v-if="!available" class="alert alert-warning d-flex align-items-center" role="alert">
      <i class="fa fa-triangle-exclamation me-2"></i>
      <div>Legacy tables <code>loyaltyplanheader</code> and <code>loyaltyplandetail</code> are required for the loyalty plan workflow.</div>
    </div>

    <BaseBlock :title="t.loyalty_plan_overview">
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
              <th>{{ t.plan_key }}</th>
              <th>{{ t.description }}</th>
              <th>{{ t.remarks }}</th>
              <th>{{ t.status }}</th>
              <th class="text-center" style="width: 140px">{{ t.actions }}</th>
            </tr>
          </thead>
          <tbody>
            <tr v-if="!rows.length">
              <td colspan="6" class="text-center text-muted py-4">{{ t.no_loyalty_plans_found }}</td>
            </tr>
            <tr v-for="(record, index) in rows" :key="record.loyaltyplanid">
              <td class="text-muted">{{ (plans.from ?? 1) + index }}</td>
              <td>{{ record.loyaltyplanid }}</td>
              <td>
                <div class="fw-semibold">{{ record.description || "-" }}</div>
                <div class="text-muted">{{ record.arbdescription || "-" }}</div>
              </td>
              <td>{{ record.remarks || "-" }}</td>
              <td>
                <span class="badge" :class="record.active ? 'bg-success-subtle text-success' : 'bg-danger-subtle text-danger'">
                  {{ statusLabels[record.active] || "-" }}
                </span>
              </td>
              <td class="text-center text-nowrap">
                <button v-if="canViewAction('loyalty plan')" class="btn btn-sm btn-alt-info me-1" @click="router.get(`/scheme/loyalty/loyalty-plan/${record.loyaltyplanid}`)">
                  <i class="fa fa-eye"></i>
                </button>
                <button v-if="can('loyalty plan', 'edit')" class="btn btn-sm btn-alt-secondary me-1" @click="router.get(`/scheme/loyalty/loyalty-plan/${record.loyaltyplanid}/edit`)">
                  <i class="fa fa-pen"></i>
                </button>
                <button v-if="can('loyalty plan', 'delete')" class="btn btn-sm btn-alt-danger" @click="confirmingDelete = record">
                  <i class="fa fa-trash"></i>
                </button>
              </td>
            </tr>
          </tbody>
        </table>
      </div>

      <div class="d-flex justify-content-between align-items-center pt-3">
        <div class="text-muted fs-sm">{{ t.showing }} {{ plans.from ?? 0 }} {{ t.to }} {{ plans.to ?? 0 }} {{ t.of }} {{ plans.total ?? 0 }}</div>
        <div class="btn-group">
          <button class="btn btn-sm btn-alt-secondary" :disabled="!plans.prev_page_url" @click="reloadList((plans.current_page || 1) - 1)">{{ t.previous }}</button>
          <button class="btn btn-sm btn-alt-secondary" disabled>{{ plans.current_page || 1 }} / {{ plans.last_page || 1 }}</button>
          <button class="btn btn-sm btn-alt-secondary" :disabled="!plans.next_page_url" @click="reloadList((plans.current_page || 1) + 1)">{{ t.next }}</button>
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
