<script setup>
import { computed } from "vue";
import { Head, router, useForm, usePage } from "@inertiajs/vue3";
import { usePermissions } from "@/composables/usePermissions";

const props = defineProps({
  filters: { type: Object, required: true },
  yearOptions: { type: Array, required: true },
  monthOptions: { type: Array, required: true },
  rows: { type: Array, required: true },
});
const page = usePage();
const t = page.props.translations.ui;

const { can } = usePermissions();
const filterForm = useForm({
  year: props.filters.year,
  month: props.filters.month ?? null,
});
const canSubmitClose = computed(() => !!filterForm.year && !!filterForm.month);

function loadRows() {
  router.get("/account/transaction/month-close", {
    year: filterForm.year,
    month: filterForm.month || undefined,
  });
}

function submitClose() {
  filterForm.post("/account/transaction/month-close");
}
</script>

<template>
  <Head :title="t.month_close" />

  <BasePageHeading
    :title="t.month_close"
    :subtitle="t.month_close_note"
  >
    <template #extra>
      <button class="btn btn-alt-secondary me-2" @click="router.get('/account/transaction')">
        <i class="fa fa-arrow-left me-1"></i> {{ t.back }}
      </button>
      <button
        v-if="can('account transaction', 'create')"
        class="btn btn-primary"
        :disabled="!canSubmitClose || filterForm.processing"
        @click="submitClose"
      >
        <i class="fa fa-lock me-1"></i> {{ t.close_month }}
      </button>
    </template>
  </BasePageHeading>

  <div class="content">
    <BaseBlock :title="t.month_close_overview">
      <div class="row g-3 align-items-end mb-3">
        <div class="col-md-3">
          <label class="form-label">{{ t.year }}</label>
          <select v-model="filterForm.year" class="form-select">
            <option v-for="option in yearOptions" :key="option.id" :value="option.id">{{ option.label }}</option>
          </select>
        </div>
        <div class="col-md-3">
          <label class="form-label">{{ t.month }}</label>
          <select v-model="filterForm.month" class="form-select">
            <option :value="null">{{ t.all }}</option>
            <option v-for="option in monthOptions" :key="option.id" :value="option.id">{{ option.label }}</option>
          </select>
        </div>
        <div class="col-md-2">
          <button class="btn btn-alt-primary w-100" @click="loadRows">{{ t.load }}</button>
        </div>
      </div>

      <div class="table-responsive">
        <table class="table table-hover table-vcenter fs-sm">
          <thead>
            <tr>
              <th>#</th>
              <th>{{ t.route_code }}</th>
              <th>{{ t.route_name }}</th>
              <th>{{ t.year }}</th>
              <th>{{ t.month }}</th>
              <th class="text-center" style="width: 110px">{{ t.view }}</th>
            </tr>
          </thead>
          <tbody>
            <tr v-if="!rows.length">
              <td colspan="6" class="text-center text-muted py-4">{{ t.no_records_found }}</td>
            </tr>
            <tr v-for="(row, index) in rows" :key="`${row.routecode}-${row.byear}-${row.bmonth}`">
              <td>{{ index + 1 }}</td>
              <td>{{ row.routecode }}</td>
              <td class="fw-semibold">{{ row.routename || "-" }}</td>
              <td>{{ row.byear }}</td>
              <td>{{ row.month_label }}</td>
              <td class="text-center">
                <button
                  class="btn btn-sm btn-alt-info"
                  @click="router.get(`/account/transaction/month-close/${row.routecode}/${row.byear}/${row.bmonth}`)"
                >
                  <i class="fa fa-eye"></i>
                </button>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </BaseBlock>
  </div>
</template>
