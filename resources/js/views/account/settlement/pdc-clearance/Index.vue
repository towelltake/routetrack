<script setup>
import { computed } from "vue";
import { Head, router, usePage } from "@inertiajs/vue3";

const props = defineProps({
  filters: { type: Object, required: true },
  rows: { type: Array, required: true },
});

const page = usePage();
const t = page.props.translations.ui;
const permission = computed(
  () => page.props.auth?.formPermissions?.["account transaction"] ?? {},
);
const canCreate = computed(() => !!(permission.value.all || permission.value.create));

function loadForDate(event) {
  router.get("/account/settlement/pdc-clearance", { date: event.target.value });
}

function openCreate() {
  router.get("/account/settlement/pdc-clearance/create", { date: props.filters.date });
}
</script>

<template>
  <Head :title="t.pdc_clearance" />

  <BasePageHeading
    :title="t.pdc_clearance"
    :subtitle="t.pdc_clearance_note"
  >
    <template #extra>
      <button v-if="canCreate" class="btn btn-primary" @click="openCreate">
        <i class="fa fa-plus me-1"></i> {{ t.add_pdc_clearance }}
      </button>
    </template>
  </BasePageHeading>

  <div class="content">
    <BaseBlock :title="t.overview">
      <div class="row g-3 align-items-end mb-3">
        <div class="col-md-3">
          <label class="form-label">{{ t.select_date }}</label>
          <input :value="filters.date" type="date" class="form-control" @change="loadForDate" />
        </div>
      </div>

      <div class="table-responsive">
        <table class="table table-hover table-vcenter fs-sm">
          <thead>
            <tr>
              <th>#</th>
              <th>{{ t.route }}</th>
              <th>{{ t.customer }}</th>
              <th>{{ t.salesman }}</th>
              <th>{{ t.bank_master }}</th>
              <th class="text-end">{{ t.amount }}</th>
            </tr>
          </thead>
          <tbody>
            <tr v-if="!rows.length">
              <td colspan="6" class="text-center text-muted py-4">{{ t.no_records_found }}</td>
            </tr>
            <tr v-for="(row, index) in rows" :key="row.transactionkey">
              <td>{{ index + 1 }}</td>
              <td>{{ row.routecode }} - {{ row.routename || "-" }}</td>
              <td>{{ row.customercode }} - {{ row.customername || "-" }}</td>
              <td>{{ row.salesmancode }} - {{ row.salesmanname || "-" }}</td>
              <td>{{ row.bankname || "-" }}</td>
              <td class="text-end">{{ row.chequeamount }}</td>
            </tr>
          </tbody>
        </table>
      </div>
    </BaseBlock>
  </div>
</template>
