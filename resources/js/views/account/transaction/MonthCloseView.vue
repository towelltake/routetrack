<script setup>
import { Head, router, usePage } from "@inertiajs/vue3";

const page = usePage();
const t = page.props.translations.ui;

defineProps({
  header: { type: Object, required: true },
  rows: { type: Array, required: true },
});
</script>

<template>
  <Head :title="t.month_close_details" />

  <BasePageHeading
    :title="t.month_close_details"
    :subtitle="`${t.route} ${header.routecode} - ${header.routename || t.route} | ${header.monthLabel} ${header.year}`"
  >
    <template #extra>
      <button
        class="btn btn-alt-secondary"
        @click="router.get('/account/transaction/month-close', { year: header.year, month: header.month })"
      >
        <i class="fa fa-arrow-left me-1"></i> {{ t.back }}
      </button>
    </template>
  </BasePageHeading>

  <div class="content">
    <BaseBlock :title="t.monthly_summary_details">
      <div class="table-responsive">
        <table class="table table-hover table-vcenter fs-sm">
          <thead>
            <tr>
              <th>{{ t.item_code }}</th>
              <th>{{ t.item_name }}</th>
              <th>{{ t.upc }}</th>
              <th>{{ t.begin_qty }}</th>
              <th>{{ t.load_qty }}</th>
              <th>{{ t.load_adjust }}</th>
              <th>{{ t.transfer_qty }}</th>
              <th>{{ t.sales_qty }}</th>
              <th>{{ t.buyback_qty }}</th>
              <th>{{ t.return_qty }}</th>
              <th>{{ t.truck_spoil }}</th>
              <th>{{ t.free_goods }}</th>
              <th>{{ t.buyback_free }}</th>
              <th>{{ t.giveaway }}</th>
              <th>{{ t.ending_qty }}</th>
              <th>{{ t.damage_qty }}</th>
              <th>{{ t.ending_value }}</th>
            </tr>
          </thead>
          <tbody>
            <tr v-if="!rows.length">
              <td colspan="17" class="text-center text-muted py-4">{{ t.no_records_found }}</td>
            </tr>
            <tr v-for="row in rows" :key="row.itemcode">
              <td>{{ row.itemcode }}</td>
              <td class="fw-semibold">{{ row.itemshortdescription || "-" }}</td>
              <td>{{ row.upc || "-" }}</td>
              <td>{{ row.quantitybegininventory }}</td>
              <td>{{ row.quantityload }}</td>
              <td>{{ row.quantityloadadjust }}</td>
              <td>{{ row.quantitytransfer }}</td>
              <td>{{ row.quantitysales }}</td>
              <td>{{ row.quantitybuybacks }}</td>
              <td>{{ row.quantityreturnscredited }}</td>
              <td>{{ row.quantitytruckspoilage }}</td>
              <td>{{ row.quantityfreegood }}</td>
              <td>{{ row.quantitybuybackfree }}</td>
              <td>{{ row.quantitygiveaway }}</td>
              <td>{{ row.quantityendinginventory }}</td>
              <td>{{ row.quantitydamage }}</td>
              <td>{{ row.valueendinginventory }}</td>
            </tr>
          </tbody>
        </table>
      </div>
    </BaseBlock>
  </div>
</template>
