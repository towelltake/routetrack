<script setup>
import { computed, ref, watch } from "vue";
import { Head, router, usePage } from "@inertiajs/vue3";
import { usePermissions } from "@/composables/usePermissions";

const props = defineProps({
  templates: Object,
  filters: Object,
});

const search = ref(props.filters?.search ?? "");
const perPage = ref(props.filters?.per_page ?? 10);
const rows = computed(() => props.templates?.data ?? []);
const confirmingDelete = ref(null);
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

function paymentModeLabel(value) {
  const labels = {
    0: "CASH Only",
    1: "CASH or CHEQUE",
    2: "CHARGE Only (GC)",
    3: "TC (CASH or CHEQUE)",
    4: "TC (CASH Only)",
  };

  return labels[value] ?? "-";
}

function reloadList(pageNumber = 1) {
  router.get(
    "/account/customer-template",
    {
      search: search.value || undefined,
      per_page: perPage.value,
      page: pageNumber,
    },
    {
      preserveScroll: true,
      preserveState: true,
      replace: true,
      only: ["templates", "filters"],
    },
  );
}

function deleteRow() {
  router.delete(`/account/customer-template/${confirmingDelete.value.customercode}`, {
    preserveScroll: true,
    onSuccess: () => {
      confirmingDelete.value = null;
    },
  });
}
</script>

<template>
  <Head :title="t.customer_template" />

  <BasePageHeading
    :title="t.customer_template"
    :subtitle="t.customer_template_note"
  >
    <template #extra>
      <button
        v-if="can('account customer template', 'create')"
        class="btn btn-primary"
        @click="router.get('/account/customer-template/create')"
      >
        <i class="fa fa-plus me-1"></i> {{ t.add }}
      </button>
    </template>
  </BasePageHeading>

  <div class="content">
    <BaseBlock :title="t.customer_template_overview">
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
              <th>{{ t.template_name }}</th>
              <th>{{ t.customer_name }}</th>
              <th>{{ t.route }}</th>
              <th>{{ t.payment_mode }}</th>
              <th>{{ t.status }}</th>
              <th class="text-center" style="width: 140px">{{ t.actions }}</th>
            </tr>
          </thead>
          <tbody>
            <tr v-if="!rows.length">
              <td colspan="8" class="text-center text-muted py-4">{{ t.no_records_found }}</td>
            </tr>
            <tr v-for="(record, index) in rows" :key="record.customercode">
              <td class="text-muted">{{ (templates.from ?? 1) + index }}</td>
              <td>{{ record.customercode }}</td>
              <td class="fw-semibold">{{ record.templatename || "-" }}</td>
              <td>{{ record.customername }}</td>
              <td>{{ record.routename || "-" }}</td>
              <td>{{ paymentModeLabel(record.invoicepaymentterms) }}</td>
              <td>
                <span class="badge" :class="record.activecustomer ? 'bg-success-subtle text-success' : 'bg-danger-subtle text-danger'">
                  {{ record.activecustomer ? t.active : t.inactive }}
                </span>
              </td>
              <td class="text-center text-nowrap">
                <button v-if="canViewAction('account customer template')" class="btn btn-sm btn-alt-info me-1" :title="t.view" @click="router.get(`/account/customer-template/${record.customercode}`)">
                  <i class="fa fa-eye"></i>
                </button>
                <button v-if="can('account customer template', 'edit')" class="btn btn-sm btn-alt-secondary me-1" :title="t.edit" @click="router.get(`/account/customer-template/${record.customercode}/edit`)">
                  <i class="fa fa-pen"></i>
                </button>
                <button v-if="can('account customer template', 'delete')" class="btn btn-sm btn-alt-danger" :title="t.delete" @click="confirmingDelete = record">
                  <i class="fa fa-trash"></i>
                </button>
              </td>
            </tr>
          </tbody>
        </table>
      </div>

      <div class="d-flex justify-content-between align-items-center pt-3">
        <div class="text-muted fs-sm">
          {{ t.showing }} {{ templates.from ?? 0 }} {{ t.to }} {{ templates.to ?? 0 }} {{ t.of }} {{ templates.total ?? 0 }}
        </div>
        <div class="btn-group">
          <button class="btn btn-sm btn-alt-secondary" :disabled="!templates.prev_page_url" @click="reloadList((templates.current_page || 1) - 1)">{{ t.previous }}</button>
          <button class="btn btn-sm btn-alt-secondary" disabled>{{ templates.current_page || 1 }} / {{ templates.last_page || 1 }}</button>
          <button class="btn btn-sm btn-alt-secondary" :disabled="!templates.next_page_url" @click="reloadList((templates.current_page || 1) + 1)">{{ t.next }}</button>
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
        <div class="modal-body">{{ t.delete }} <strong>{{ confirmingDelete.templatename || confirmingDelete.customername }}</strong>?</div>
        <div class="modal-footer">
          <button class="btn btn-alt-secondary" @click="confirmingDelete = null">{{ t.cancel }}</button>
          <button class="btn btn-danger" @click="deleteRow">{{ t.delete }}</button>
        </div>
      </div>
    </div>
  </div>
</template>
