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
const canDelete = computed(() => !!(permission.value.all || permission.value.delete));

function loadForDate(event) {
  router.get("/account/settlement/cash-receipt", { date: event.target.value });
}

function openCreate() {
  router.get("/account/settlement/cash-receipt/create", { date: props.filters.date });
}

function openView(documentnumber) {
  router.get(`/account/settlement/cash-receipt/${documentnumber}`, { date: props.filters.date });
}

function removeRecord(documentnumber) {
  if (!window.confirm(t.cashier_receipt_delete_confirm)) {
    return;
  }

  router.delete(`/account/settlement/cash-receipt/${documentnumber}`, {
    data: { date: props.filters.date },
  });
}
</script>

<template>
  <Head :title="t.cashier_receipt" />

  <BasePageHeading
    :title="t.cashier_receipt"
    :subtitle="t.cashier_receipt_note"
  >
    <template #extra>
      <button v-if="canCreate" class="btn btn-primary" @click="openCreate">
        <i class="fa fa-plus me-1"></i> {{ t.add_cashier_receipt }}
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
              <th>{{ t.document_no }}</th>
              <th>{{ t.salesman }}</th>
              <th>{{ t.route }}</th>
              <th>{{ t.bank_master }}</th>
              <th>{{ t.slip_no }}</th>
              <th class="text-end">{{ t.total }}</th>
              <th class="text-end">{{ t.actions }}</th>
            </tr>
          </thead>
          <tbody>
            <tr v-if="!rows.length">
              <td colspan="8" class="text-center text-muted py-4">{{ t.no_records_found }}</td>
            </tr>
            <tr v-for="(row, index) in rows" :key="row.documentnumber">
              <td>{{ index + 1 }}</td>
              <td>{{ row.documentnumber }}</td>
              <td>{{ row.salesmancode }} - {{ row.salesmanname || "-" }}</td>
              <td>{{ row.routecode }} - {{ row.routename || "-" }}</td>
              <td>{{ row.bankname || "-" }}</td>
              <td>{{ row.slipno || "-" }}</td>
              <td class="text-end">{{ row.total }}</td>
              <td class="text-end">
                <div class="btn-group btn-group-sm">
                  <button class="btn btn-alt-secondary" @click="openView(row.documentnumber)">
                    <i class="fa fa-eye"></i>
                  </button>
                  <button v-if="canDelete" class="btn btn-alt-danger" @click="removeRecord(row.documentnumber)">
                    <i class="fa fa-trash"></i>
                  </button>
                </div>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </BaseBlock>
  </div>
</template>
