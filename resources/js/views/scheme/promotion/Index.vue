<script setup>
import { computed, ref, watch } from "vue";
import { Head, router, usePage } from "@inertiajs/vue3";

const props = defineProps({
  available: { type: Boolean, default: false },
  promotions: { type: Object, required: true },
  filters: { type: Object, default: () => ({}) },
  optionSets: { type: Object, required: true },
});

const search = ref(props.filters?.search ?? "");
const perPage = ref(props.filters?.per_page ?? 10);
const rows = computed(() => props.promotions?.data ?? []);
const promotionTypeLabels = computed(
  () => props.optionSets?.promotionTypeLabels ?? {},
);
const statusLabels = computed(() => props.optionSets?.statusLabels ?? {});
const t = usePage().props.translations.ui;

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

function reloadList(pageNumber = 1) {
  router.get(
    "/scheme/promotion",
    {
      search: search.value || undefined,
      per_page: perPage.value,
      page: pageNumber,
    },
    {
      preserveScroll: true,
      preserveState: true,
      replace: true,
      only: ["available", "promotions", "filters"],
    },
  );
}

function formatDate(value) {
  if (!value) {
    return "-";
  }

  const parsed = new Date(value);

  if (Number.isNaN(parsed.getTime())) {
    return value;
  }

  return parsed.toLocaleDateString("en-GB");
}
</script>

<template>
  <Head :title="t.promotion" />

  <BasePageHeading
    :title="t.promotion"
    :subtitle="t.promotion_note"
  />

  <div class="content">
    <div v-if="!available" class="alert alert-warning d-flex align-items-center" role="alert">
      <i class="fa fa-triangle-exclamation me-2"></i>
      <div>
        Legacy table <code>promotioncontrol</code> is not available in this environment yet. The
        Scheme menu and Promotion page are ready for the next implementation step.
      </div>
    </div>

    <BaseBlock :title="t.promotion_overview">
      <template #options>
        <div class="d-flex gap-2">
          <input
            v-model="search"
            type="text"
            class="form-control form-control-sm"
            :placeholder="t.search"
            style="width: 220px"
          />
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
              <th>Plan No.</th>
              <th>{{ t.promo_key }}</th>
              <th>Description</th>
              <th>Arabic Description</th>
              <th>{{ t.promotion_type }}</th>
              <th>{{ t.start_date }}</th>
              <th>{{ t.end_date }}</th>
              <th>{{ t.status }}</th>
            </tr>
          </thead>
          <tbody>
            <tr v-if="!rows.length">
              <td colspan="9" class="text-center text-muted py-4">{{ t.no_promotions_found }}</td>
            </tr>
            <tr v-for="(record, index) in rows" :key="record.promotionplannumber">
              <td class="text-muted">{{ (promotions.from ?? 1) + index }}</td>
              <td>{{ record.promotionplannumber }}</td>
              <td>{{ record.promotionkey }}</td>
              <td class="fw-semibold">{{ record.promotiondescription || "-" }}</td>
              <td>{{ record.arbpromotiondescription || "-" }}</td>
              <td>{{ promotionTypeLabels[record.promotiontypecode] || "-" }}</td>
              <td>{{ formatDate(record.startdate) }}</td>
              <td>{{ formatDate(record.enddate) }}</td>
              <td>
                <span
                  class="badge"
                  :class="record.status ? 'bg-success-subtle text-success' : 'bg-danger-subtle text-danger'"
                >
                  {{ statusLabels[record.status] || "-" }}
                </span>
              </td>
            </tr>
          </tbody>
        </table>
      </div>

      <div class="d-flex justify-content-between align-items-center pt-3">
        <div class="text-muted fs-sm">
          {{ t.showing }} {{ promotions.from ?? 0 }} {{ t.to }} {{ promotions.to ?? 0 }} {{ t.of }}
          {{ promotions.total ?? 0 }}
        </div>
        <div class="btn-group">
          <button
            class="btn btn-sm btn-alt-secondary"
            :disabled="!promotions.prev_page_url"
            @click="reloadList((promotions.current_page || 1) - 1)"
          >
            {{ t.previous }}
          </button>
          <button class="btn btn-sm btn-alt-secondary" disabled>
            {{ promotions.current_page || 1 }} / {{ promotions.last_page || 1 }}
          </button>
          <button
            class="btn btn-sm btn-alt-secondary"
            :disabled="!promotions.next_page_url"
            @click="reloadList((promotions.current_page || 1) + 1)"
          >
            {{ t.next }}
          </button>
        </div>
      </div>
    </BaseBlock>
  </div>
</template>
