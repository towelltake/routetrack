<script setup>
import { computed } from "vue";
import { Head, router, usePage, useForm } from "@inertiajs/vue3";

const props = defineProps({
  filters: { type: Object, required: true },
  rows: { type: Array, required: true },
  yearOptions: { type: Array, required: true },
  mode: { type: String, required: true },
});

const t = usePage().props.translations.ui;
const weekDayNames = {
  1: t.monday,
  2: t.tuesday,
  3: t.wednesday,
  4: t.thursday,
  5: t.friday,
  6: t.saturday,
  7: t.sunday,
};

const form = useForm({
  year: props.filters.year,
  action: "load",
});

const weekStartDayLabel = computed(() => weekDayNames[props.filters.weekStartDay] ?? t.monday);
const isGenerated = computed(() => props.mode === "generated");

function submit(action) {
  form.action = action;
  form.post("/account/customer-sequence/sales-calendar");
}

function onYearChange() {
  router.get("/account/customer-sequence/sales-calendar", { year: form.year }, { preserveState: true });
}
</script>

<template>
  <Head :title="t.sales_calendar" />

  <BasePageHeading
    :title="t.sales_calendar"
    :subtitle="t.sales_calendar_note"
  >
    <template #extra>
      <button class="btn btn-alt-secondary" @click="router.get('/account/customer-sequence')">
        <i class="fa fa-arrow-left me-1"></i> {{ t.back }}
      </button>
    </template>
  </BasePageHeading>

  <div class="content">
    <BaseBlock>
      <div class="row g-4 align-items-end mb-3">
        <div class="col-md-4">
          <label class="form-label">{{ t.week_start_day }}</label>
          <input class="form-control" :value="weekStartDayLabel" disabled />
        </div>
        <div class="col-md-4">
          <label class="form-label">{{ t.year }}</label>
          <select v-model="form.year" class="form-select" @change="onYearChange">
            <option v-for="option in yearOptions" :key="option.id" :value="option.id">
              {{ option.label }}
            </option>
          </select>
        </div>
        <div class="col-md-4">
          <button class="btn btn-primary w-100" :disabled="form.processing" @click="submit('load')">
            <i class="fa fa-table me-1"></i> {{ t.load_data }}
          </button>
        </div>
      </div>
    </BaseBlock>

    <BaseBlock class="mt-4" content-full>
      <div class="table-responsive" style="max-height: 420px">
        <table class="table table-striped table-vcenter mb-0">
          <thead>
            <tr>
              <th>{{ t.start_date }}</th>
              <th>{{ t.end_date }}</th>
              <th>{{ t.calendar_week }}</th>
              <th>{{ t.period }}</th>
              <th>{{ t.routepro_week }}</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="row in rows" :key="`${row.weeknumber}-${row.weekstartdate}-${row.rp32weeknumber}`">
              <td>{{ row.weekstartdate }}</td>
              <td>{{ row.weekenddate }}</td>
              <td>{{ row.weeknumber }}</td>
              <td>{{ row.salesperiod }}</td>
              <td>{{ row.rp32weeknumber }}</td>
            </tr>
            <tr v-if="rows.length === 0">
              <td colspan="5" class="text-center text-muted py-4">{{ t.no_records_found }}</td>
            </tr>
          </tbody>
        </table>
      </div>

      <div class="row g-3 justify-content-center mt-1">
        <template v-if="isGenerated">
          <div class="col-md-2">
            <button class="btn btn-primary w-100" :disabled="form.processing" @click="submit('save')">
              {{ t.save }}
            </button>
          </div>
          <div class="col-md-2">
            <button class="btn btn-alt-primary w-100" :disabled="form.processing" @click="submit('auto')">
              {{ t.auto }}
            </button>
          </div>
          <div class="col-md-2">
            <button class="btn btn-alt-secondary w-100" @click="router.get('/account/customer-sequence')">
              {{ t.close }}
            </button>
          </div>
        </template>

        <template v-else>
          <div class="col-md-2">
            <button class="btn btn-primary w-100" :disabled="form.processing" @click="submit('auto')">
              {{ t.auto }}
            </button>
          </div>
          <div class="col-md-2">
            <button class="btn btn-danger w-100" :disabled="form.processing" @click="submit('delete')">
              {{ t.delete }}
            </button>
          </div>
          <div class="col-md-2">
            <button class="btn btn-alt-secondary w-100" @click="router.get('/account/customer-sequence')">
              {{ t.close }}
            </button>
          </div>
        </template>
      </div>
    </BaseBlock>
  </div>
</template>
