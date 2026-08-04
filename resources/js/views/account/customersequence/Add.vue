<script setup>
import { computed, ref, watch } from "vue";
import { Head, router, usePage, useForm } from "@inertiajs/vue3";

const props = defineProps({
  filters: { type: Object, required: true },
  customers: { type: Array, required: true },
  optionSets: { type: Object, required: true },
});

const t = usePage().props.translations.ui;
const sourceRoute = ref(props.filters.source_routecode);
const selected = ref([]);
const rows = computed(() => props.customers ?? []);
const form = useForm({
  routecode: props.filters.routecode,
  week: props.filters.week,
  customers: [],
});

watch(selected, (value) => {
  form.customers = value;
}, { deep: true });

function reload() {
  router.get("/account/customer-sequence/add", {
    routecode: props.filters.routecode,
    week: props.filters.week,
    source_routecode: sourceRoute.value,
  });
}

function submit() {
  form.post("/account/customer-sequence/add");
}
</script>

<template>
  <Head :title="t.add_customer" />

  <BasePageHeading
    :title="t.add_customer"
    :subtitle="t.add_customer_note"
  >
    <template #extra>
      <div class="d-flex gap-2">
        <button class="btn btn-alt-secondary" @click="router.get('/account/customer-sequence/arrange', { routecode: props.filters.routecode, week: props.filters.week })">
          <i class="fa fa-arrow-left me-1"></i> {{ t.back }}
        </button>
        <button class="btn btn-primary" :disabled="form.processing || !selected.length" @click="submit">
          <i class="fa fa-plus me-1"></i> {{ t.add_selected }}
        </button>
      </div>
    </template>
  </BasePageHeading>

  <div class="content">
    <BaseBlock>
      <div class="row g-4 mb-4">
        <div class="col-md-5">
          <label class="form-label">{{ t.source_route }}</label>
          <select v-model="sourceRoute" class="form-select" @change="reload">
            <option v-for="option in optionSets.routeOptions" :key="option.id" :value="option.id">
              {{ option.label }}
            </option>
          </select>
        </div>
      </div>

      <div class="table-responsive">
        <table class="table table-hover table-vcenter fs-sm">
          <thead>
            <tr>
              <th>{{ t.select_row }}</th>
              <th>{{ t.code }}</th>
              <th>{{ t.alternate_code }}</th>
              <th>{{ t.name }}</th>
              <th>{{ t.address }}</th>
            </tr>
          </thead>
          <tbody>
            <tr v-if="!rows.length">
              <td colspan="5" class="text-center text-muted py-4">{{ t.no_available_customers_found }}</td>
            </tr>
            <tr v-for="row in rows" :key="row.customercode">
              <td><input v-model="selected" type="checkbox" class="form-check-input" :value="row.customercode" /></td>
              <td>{{ row.customercode }}</td>
              <td>{{ row.alternatecode || "-" }}</td>
              <td>{{ row.customername }}</td>
              <td>{{ row.customeraddress1 || "-" }}</td>
            </tr>
          </tbody>
        </table>
      </div>
    </BaseBlock>
  </div>
</template>
