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
  router.get("/account/transaction/credit-note/route", { date: event.target.value });
}

function openCreate() {
  router.get("/account/transaction/credit-note/route/create", { date: props.filters.date });
}

function openView(transactionkey) {
  router.get(`/account/transaction/credit-note/route/${transactionkey}`, { date: props.filters.date });
}

function removeRecord(transactionkey) {
  if (!window.confirm(t.route_credit_note_delete_confirm)) {
    return;
  }

  router.delete(`/account/transaction/credit-note/route/${transactionkey}`, {
    data: { date: props.filters.date },
  });
}
</script>

<template>
  <Head :title="t.credit_note_route" />

  <BasePageHeading
    :title="t.credit_note_route"
    :subtitle="t.credit_note_route_note"
  >
    <template #extra>
      <button v-if="canCreate" class="btn btn-primary" @click="openCreate">
        <i class="fa fa-plus me-1"></i> {{ t.add_route_credit_note }}
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
              <th>{{ t.invoice_no_short }}</th>
              <th>{{ t.document_no }}</th>
              <th>{{ t.route }}</th>
              <th>{{ t.salesman }}</th>
              <th class="text-end">{{ t.amount }}</th>
              <th class="text-end">{{ t.actions }}</th>
            </tr>
          </thead>
          <tbody>
            <tr v-if="!rows.length">
              <td colspan="7" class="text-center text-muted py-4">{{ t.no_records_found }}</td>
            </tr>
            <tr v-for="(row, index) in rows" :key="row.transactionkey">
              <td>{{ index + 1 }}</td>
              <td>{{ row.invoicenumber || "-" }}</td>
              <td>{{ row.documentnumber || "-" }}</td>
              <td>{{ row.routecode }} - {{ row.routename || "-" }}</td>
              <td>{{ row.salesmancode }} - {{ row.salesmanname || "-" }}</td>
              <td class="text-end">{{ row.amountpaid }}</td>
              <td class="text-end">
                <div class="btn-group btn-group-sm">
                  <button class="btn btn-alt-secondary" @click="openView(row.transactionkey)">
                    <i class="fa fa-eye"></i>
                  </button>
                  <button v-if="canDelete" class="btn btn-alt-danger" @click="removeRecord(row.transactionkey)">
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
