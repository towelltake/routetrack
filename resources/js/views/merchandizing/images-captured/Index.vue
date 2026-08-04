<script setup>
import { computed, ref, watch } from "vue";
import { Head, router, usePage } from "@inertiajs/vue3";
import { usePermissions } from "@/composables/usePermissions";

const props = defineProps({
  available: { type: Boolean, default: false },
  records: { type: Object, required: true },
  filters: { type: Object, default: () => ({}) },
  formMeta: { type: Object, required: true },
});

const selectedDate = ref(props.filters?.date ?? "");
const selectedRoute = ref(props.filters?.routecode ?? "");
const perPage = ref(props.filters?.per_page ?? 10);
const rows = computed(() => props.records?.data ?? []);
const { can } = usePermissions();
const t = usePage().props.translations.ui;

watch(selectedDate, () => reloadList());
watch(selectedRoute, () => reloadList());
watch(perPage, () => reloadList());

function reloadList(pageNumber = 1) {
  router.get(
    props.formMeta.indexUrl,
    { date: selectedDate.value || undefined, routecode: selectedRoute.value || undefined, per_page: perPage.value, page: pageNumber },
    { preserveScroll: true, preserveState: true, replace: true, only: ["records", "filters", "available"] },
  );
}

function resetFilters() {
  selectedDate.value = "";
  selectedRoute.value = "";
  router.get(
    props.formMeta.indexUrl,
    { per_page: perPage.value },
    { preserveScroll: true, preserveState: true, replace: true, only: ["records", "filters", "available"] },
  );
}
</script>

<template>
  <Head :title="t.images_captured" />

  <BasePageHeading :title="t.images_captured" :subtitle="t.images_captured_note" />

  <div class="content">
    <div v-if="!available" class="alert alert-warning d-flex align-items-center" role="alert">
      <i class="fa fa-triangle-exclamation me-2"></i>
      <div>{{ t.legacy_customer_image_tables_required }}</div>
    </div>

    <BaseBlock :title="t.images_captured_overview">
      <template #options>
        <div class="d-flex gap-2">
          <input v-model="selectedDate" type="date" class="form-control form-control-sm" style="width:180px" />
          <select v-model="selectedRoute" class="form-select form-select-sm" style="width:240px">
            <option value="">{{ t.all_routes }}</option>
            <option v-for="option in formMeta.routeOptions" :key="option.id" :value="option.id">
              {{ option.label }}
            </option>
          </select>
          <select v-model="perPage" class="form-select form-select-sm" style="width:90px">
            <option :value="10">10</option>
            <option :value="25">25</option>
            <option :value="50">50</option>
            <option :value="100">100</option>
          </select>
          <button class="btn btn-sm btn-alt-secondary" @click="resetFilters">{{ t.reset }}</button>
        </div>
      </template>

      <div class="table-responsive">
        <table class="table table-hover table-vcenter fs-sm">
          <thead>
            <tr>
              <th>#</th>
              <th>{{ t.customer_code }}</th>
              <th>{{ t.customer_name }}</th>
              <th>{{ t.image_count }}</th>
              <th class="text-center" style="width:100px">{{ t.action }}</th>
            </tr>
          </thead>
          <tbody>
            <tr v-if="!rows.length">
              <td colspan="5" class="text-center text-muted py-4">{{ t.no_captured_images_found }}</td>
            </tr>
            <tr v-for="(record, index) in rows" :key="record.customercode">
              <td class="text-muted">{{ (records.from ?? 1) + index }}</td>
              <td>{{ record.customercode }}</td>
              <td class="fw-semibold">{{ record.customername }}</td>
              <td>{{ record.image_count }}</td>
              <td class="text-center text-nowrap">
                <button v-if="can('images captured', 'view') || can('images captured', 'edit')" class="btn btn-sm btn-alt-info" @click="router.get(`${formMeta.baseUrl}/${record.customercode}`)">
                  <i class="fa fa-eye"></i>
                </button>
              </td>
            </tr>
          </tbody>
        </table>
      </div>

      <div class="d-flex justify-content-between align-items-center pt-3">
        <div class="text-muted fs-sm">{{ t.showing }} {{ records.from ?? 0 }} {{ t.to }} {{ records.to ?? 0 }} {{ t.of }} {{ records.total ?? 0 }}</div>
        <div class="btn-group">
          <button class="btn btn-sm btn-alt-secondary" :disabled="!records.prev_page_url" @click="reloadList((records.current_page || 1) - 1)">{{ t.previous }}</button>
          <button class="btn btn-sm btn-alt-secondary" disabled>{{ records.current_page || 1 }} / {{ records.last_page || 1 }}</button>
          <button class="btn btn-sm btn-alt-secondary" :disabled="!records.next_page_url" @click="reloadList((records.current_page || 1) + 1)">{{ t.next }}</button>
        </div>
      </div>
    </BaseBlock>
  </div>
</template>
