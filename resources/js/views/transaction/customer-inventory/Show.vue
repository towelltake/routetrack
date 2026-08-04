<script setup>
import { computed } from "vue";
import { Head, Link, usePage } from "@inertiajs/vue3";

const props = defineProps({
  header: { type: Object, required: true },
  lines: { type: Array, required: true },
  filters: { type: Object, required: true },
});

const page = usePage();
const t = page.props.translations.ui;
const locale = page.props.locale;

const backUrl = computed(() => {
  const params = new URLSearchParams();

  if (props.filters?.date) params.set("date", props.filters.date);
  if (props.filters?.search) params.set("search", props.filters.search);
  if (props.filters?.page) params.set("page", String(props.filters.page));
  if (props.filters?.per_page) params.set("per_page", String(props.filters.per_page));
  if (props.filters?.sort_by) params.set("sort_by", props.filters.sort_by);
  if (props.filters?.sort_dir) params.set("sort_dir", props.filters.sort_dir);

  return `/transaction/customer-inventory?${params.toString()}`;
});

function routeLabel() {
  return locale === "ar"
    ? (props.header.arbroutename || props.header.routename || "")
    : (props.header.routename || props.header.arbroutename || "");
}

function customerLabel() {
  return locale === "ar"
    ? (props.header.arbcustomername || props.header.customername || "")
    : (props.header.customername || props.header.arbcustomername || "");
}
</script>

<template>
  <Head :title="t.customer_inventory ?? 'Customer Inventory'" />

  <BasePageHeading
    :title="t.customer_inventory ?? 'Customer Inventory'"
    :subtitle="t.customer_inventory_note ?? 'Review customer inventory visits using the legacy overview workflow'"
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
          <label class="form-label">{{ t.visit_date ?? "Visit Date" }}</label>
          <div class="form-control-plaintext">{{ header.visitdate || "-" }}</div>
        </div>
        <div class="col-md-4">
          <label class="form-label">{{ t.route ?? "Route" }}</label>
          <div class="form-control-plaintext">{{ header.routecode }} - {{ routeLabel() || "-" }}</div>
        </div>
        <div class="col-md-4">
          <label class="form-label">{{ t.salesman_code ?? "Salesman Code" }}</label>
          <div class="form-control-plaintext">{{ header.salesmancode || "-" }}</div>
        </div>
        <div class="col-md-4">
          <label class="form-label">{{ t.customer_code ?? "Customer Code" }}</label>
          <div class="form-control-plaintext">{{ header.customercode || "-" }}</div>
        </div>
        <div class="col-md-4">
          <label class="form-label">{{ t.alternate_code ?? "Alternate Code" }}</label>
          <div class="form-control-plaintext">{{ header.alternatecode || "-" }}</div>
        </div>
        <div class="col-md-4">
          <label class="form-label">{{ t.customer_name ?? "Customer Name" }}</label>
          <div class="form-control-plaintext">{{ customerLabel() || "-" }}</div>
        </div>
      </div>

      <div class="table-responsive">
        <table class="table table-hover table-vcenter fs-sm">
          <thead>
            <tr>
              <th>{{ t.item_code ?? "Item Code" }}</th>
              <th>{{ t.item_name ?? "Item Description" }}</th>
              <th class="text-center">{{ t.upc ?? "UPC" }}</th>
              <th class="text-center">{{ t.location1 ?? "Location 1" }}</th>
              <th class="text-center">{{ t.location2 ?? "Location 2" }}</th>
              <th class="text-center">{{ t.location3 ?? "Location 3" }}</th>
            </tr>
          </thead>
          <tbody>
            <tr v-if="!lines.length">
              <td colspan="6" class="text-center text-muted py-4">{{ t.no_records ?? "No records found." }}</td>
            </tr>
            <tr v-for="line in lines" :key="`${line.itemcode}-${line.display_code}`">
              <td>{{ line.display_code }}</td>
              <td>{{ line.description || "-" }}</td>
              <td class="text-center">{{ line.upc }}</td>
              <td class="text-center">{{ line.location1 }}</td>
              <td class="text-center">{{ line.location2 }}</td>
              <td class="text-center">{{ line.location3 }}</td>
            </tr>
          </tbody>
        </table>
      </div>
    </BaseBlock>
  </div>
</template>
