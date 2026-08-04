<script setup>
import { computed, ref, watch } from "vue";
import { Head, router, usePage, useForm } from "@inertiajs/vue3";

const props = defineProps({
  filters: { type: Object, required: true },
  customers: { type: Array, required: true },
  optionSets: { type: Object, required: true },
});

const t = usePage().props.translations.ui;
const day = ref(props.filters.day);
const rows = ref((props.customers ?? []).map((row) => ({ ...row })));
const form = useForm({
  routecode: props.filters.routecode,
  week: props.filters.week,
  day: props.filters.day,
  customer_order: rows.value.map((row) => row.customercode),
});

watch(day, (value) => {
  router.get("/account/customer-sequence/route-sequence", {
    routecode: props.filters.routecode,
    week: props.filters.week,
    day: value,
  });
});

watch(
  rows,
  (value) => {
    form.customer_order = value.map((row) => row.customercode);
  },
  { deep: true },
);

function move(index, direction) {
  const nextIndex = index + direction;
  if (nextIndex < 0 || nextIndex >= rows.value.length) {
    return;
  }

  const copy = [...rows.value];
  const [item] = copy.splice(index, 1);
  copy.splice(nextIndex, 0, item);
  rows.value = copy.map((row, idx) => ({ ...row, dayseq: idx + 1 }));
}

function submit() {
  form.day = day.value;
  form.post("/account/customer-sequence/route-sequence");
}
</script>

<template>
  <Head :title="t.route_sequence" />

  <BasePageHeading
    :title="t.route_sequence"
    :subtitle="t.route_sequence_note"
  >
    <template #extra>
      <div class="d-flex gap-2">
        <button class="btn btn-alt-secondary" @click="router.get('/account/customer-sequence')">
          <i class="fa fa-arrow-left me-1"></i> {{ t.back }}
        </button>
        <button class="btn btn-primary" :disabled="form.processing" @click="submit">
          <i class="fa fa-floppy-disk me-1"></i> {{ t.save }}
        </button>
      </div>
    </template>
  </BasePageHeading>

  <div class="content">
    <BaseBlock>
      <div class="row g-4 mb-4">
        <div class="col-md-5">
          <label class="form-label">{{ t.route }}</label>
          <select :value="props.filters.routecode" class="form-select" disabled>
            <option v-for="option in optionSets.routeOptions" :key="option.id" :value="option.id">
              {{ option.label }}
            </option>
          </select>
        </div>
        <div class="col-md-3">
          <label class="form-label">{{ t.day }}</label>
          <select v-model="day" class="form-select">
            <option v-for="option in optionSets.dayOptions" :key="option.id" :value="option.id">
              {{ option.label }}
            </option>
          </select>
        </div>
      </div>

      <div class="table-responsive">
        <table class="table table-hover table-vcenter fs-sm">
          <thead>
            <tr>
              <th>{{ t.sequence }}</th>
              <th>{{ t.code }}</th>
              <th>{{ t.alternate_code }}</th>
              <th>{{ t.name }}</th>
              <th>{{ t.address }}</th>
              <th class="text-center">{{ t.move }}</th>
            </tr>
          </thead>
          <tbody>
            <tr v-if="!rows.length">
              <td colspan="6" class="text-center text-muted py-4">{{ t.no_customers_found_for_selected_day }}</td>
            </tr>
            <tr v-for="(row, index) in rows" :key="row.customercode">
              <td>{{ index + 1 }}</td>
              <td>{{ row.customercode }}</td>
              <td>{{ row.alternatecode || "-" }}</td>
              <td>{{ row.customername }}</td>
              <td>{{ row.customeraddress1 || "-" }}</td>
              <td class="text-center text-nowrap">
                <button class="btn btn-sm btn-alt-secondary me-1" :disabled="index === 0" @click="move(index, -1)">
                  <i class="fa fa-arrow-up"></i>
                </button>
                <button class="btn btn-sm btn-alt-secondary" :disabled="index === rows.length - 1" @click="move(index, 1)">
                  <i class="fa fa-arrow-down"></i>
                </button>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </BaseBlock>
  </div>
</template>
