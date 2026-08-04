<script setup>
import { computed } from "vue";
import { Head, Link, usePage } from "@inertiajs/vue3";
import { useAmountFormatter } from "@/composables/useAmountFormatter";

const props = defineProps({
  header: { type: Object, required: true },
  lines: { type: Array, required: true },
  filters: { type: Object, required: true },
});

const page = usePage();
const t = page.props.translations.ui;
const locale = page.props.locale;
const { formatAmount } = useAmountFormatter();

const backUrl = computed(() => {
  const params = new URLSearchParams();

  if (props.filters?.date) params.set("date", props.filters.date);
  if (props.filters?.search) params.set("search", props.filters.search);
  if (props.filters?.page) params.set("page", String(props.filters.page));
  if (props.filters?.per_page) params.set("per_page", String(props.filters.per_page));
  if (props.filters?.sort_by) params.set("sort_by", props.filters.sort_by);
  if (props.filters?.sort_dir) params.set("sort_dir", props.filters.sort_dir);

  return `/transaction/inventory-summary?${params.toString()}`;
});

function routeLabel() {
  return locale === "ar"
    ? (props.header.arbroutename || props.header.routename || "")
    : (props.header.routename || props.header.arbroutename || "");
}

function amount(value) {
  return formatAmount(value);
}
</script>

<template>
  <Head :title="t.inventory_summary ?? 'Inventory Summary'" />

  <BasePageHeading
    :title="t.inventory_summary ?? 'Inventory Summary'"
    :subtitle="t.inventory_summary_note ?? 'Review route-wise inventory summary records by route start date.'"
  >
    <template #extra>
      <Link class="btn btn-alt-secondary" :href="backUrl">
        <i class="fa fa-arrow-left me-1"></i> {{ t.back ?? "Back" }}
      </Link>
    </template>
  </BasePageHeading>

  <div class="content">
    <BaseBlock :title="t.details ?? 'Details'">
      <div class="row g-3 fs-sm mb-4">
        <div class="col-md-4">
          <label class="form-label">{{ t.route ?? "Route" }}</label>
          <div class="form-control-plaintext">{{ header.routecode }} - {{ routeLabel() || "-" }}</div>
        </div>
        <div class="col-md-4">
          <label class="form-label">{{ t.salesman_code ?? "Salesman Code" }}</label>
          <div class="form-control-plaintext">{{ header.salesmancode || "-" }}</div>
        </div>
        <div class="col-md-4">
          <label class="form-label">{{ t.route_close ?? "Route Close" }}</label>
          <div class="form-control-plaintext">{{ header.routeclosed || "-" }}</div>
        </div>
        <div class="col-md-4">
          <label class="form-label">{{ t.route_start_date ?? "Route Start Date" }}</label>
          <div class="form-control-plaintext">{{ header.routestartdate || "-" }}</div>
        </div>
        <div class="col-md-4">
          <label class="form-label">{{ t.route_end_date ?? "Route End Date" }}</label>
          <div class="form-control-plaintext">{{ header.routeenddate || "-" }}</div>
        </div>
      </div>

      <div class="table-responsive">
        <table class="table table-hover table-vcenter fs-sm">
          <thead>
            <tr>
              <th>{{ t.item_code ?? "Item Code" }}</th>
              <th>{{ t.item_name ?? "Item Description" }}</th>
              <th class="text-center">{{ t.opening ?? "Opening" }}</th>
              <th class="text-center">{{ t.load ?? "Load" }}</th>
              <th class="text-center">{{ t.transfer_in ?? "Transfer In" }}</th>
              <th class="text-center">{{ t.transfer_out ?? "Transfer Out" }}</th>
              <th class="text-center">{{ t.sales ?? "Sales" }}</th>
              <th class="text-center">{{ t.good_return ?? "Good Return" }}</th>
              <th class="text-center">{{ t.damaged ?? "Damaged" }}</th>
              <th class="text-center">{{ t.promo ?? "Promo" }}</th>
              <th class="text-center">{{ t.free ?? "Free" }}</th>
              <th class="text-center">{{ t.closing ?? "Closing" }}</th>
              <th class="text-end">{{ t.opening_value ?? "Opening Value" }}</th>
              <th class="text-end">{{ t.loaded_value ?? "Loaded Value" }}</th>
              <th class="text-end">{{ t.truck_stock_value ?? "Truck Stock Value" }}</th>
              <th class="text-end">{{ t.closing_value ?? "Closing Value" }}</th>
            </tr>
          </thead>
          <tbody>
            <tr v-if="!lines.length">
              <td colspan="16" class="text-center text-muted py-4">{{ t.no_records ?? "No records found." }}</td>
            </tr>
            <tr v-for="line in lines" :key="`${line.itemcode}-${line.display_code}`">
              <td>{{ line.display_code }}</td>
              <td>{{ line.description || "-" }}</td>
              <td class="text-center">{{ line.opening.cases }}/{{ line.opening.pieces }}</td>
              <td class="text-center">{{ line.load.cases }}/{{ line.load.pieces }}</td>
              <td class="text-center">{{ line.transfer_in.cases }}/{{ line.transfer_in.pieces }}</td>
              <td class="text-center">{{ line.transfer_out.cases }}/{{ line.transfer_out.pieces }}</td>
              <td class="text-center">{{ line.sales.cases }}/{{ line.sales.pieces }}</td>
              <td class="text-center">{{ line.good_return.cases }}/{{ line.good_return.pieces }}</td>
              <td class="text-center">{{ line.damaged.cases }}/{{ line.damaged.pieces }}</td>
              <td class="text-center">{{ line.promo.cases }}/{{ line.promo.pieces }}</td>
              <td class="text-center">{{ line.free.cases }}/{{ line.free.pieces }}</td>
              <td class="text-center">{{ line.closing.cases }}/{{ line.closing.pieces }}</td>
              <td class="text-end">{{ amount(line.opening_value) }}</td>
              <td class="text-end">{{ amount(line.loaded_value) }}</td>
              <td class="text-end">{{ amount(line.truck_stock_value) }}</td>
              <td class="text-end">{{ amount(line.closing_value) }}</td>
            </tr>
          </tbody>
        </table>
      </div>
    </BaseBlock>
  </div>
</template>
