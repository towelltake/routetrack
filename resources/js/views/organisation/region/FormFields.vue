<script setup>
import { usePage } from "@inertiajs/vue3";

defineProps({
  form: {
    type: Object,
    required: true,
  },
  countries: {
    type: Array,
    required: true,
  },
  regionManagers: {
    type: Array,
    required: true,
  },
  isViewing: {
    type: Boolean,
    default: false,
  },
});

const t = usePage().props.translations.ui;
</script>

<template>
  <div class="modal-body row g-3">
    <div class="col-md-4">
      <label class="form-label">{{ t.region_name }} <span class="text-danger">*</span></label>
      <input
        v-model="form.regionmstname"
        class="form-control"
        :class="{ 'is-invalid': form.errors.regionmstname }"
        maxlength="50"
        :readonly="isViewing"
      />
      <div class="invalid-feedback">{{ form.errors.regionmstname }}</div>
    </div>

    <div class="col-md-4">
      <label class="form-label">{{ t.arb_region_name }}</label>
      <input
        v-model="form.arbregionmstname"
        class="form-control"
        dir="rtl"
        maxlength="50"
        :readonly="isViewing"
      />
    </div>

    <div class="col-md-4">
      <label class="form-label">{{ t.alternate_code }}</label>
      <input
        v-model="form.alternatecode"
        class="form-control"
        :class="{ 'is-invalid': form.errors.alternatecode }"
        maxlength="50"
        :readonly="isViewing"
      />
      <div class="invalid-feedback">{{ form.errors.alternatecode }}</div>
    </div>

    <div class="col-md-6">
      <label class="form-label">{{ t.country }} <span class="text-danger">*</span></label>
      <select
        v-model="form.countrycode"
        class="form-select"
        :class="{ 'is-invalid': form.errors.countrycode }"
        :disabled="isViewing"
      >
        <option :value="null">- {{ t.select }} -</option>
        <option
          v-for="country in countries"
          :key="country.countrycode"
          :value="country.countrycode"
        >
          {{ country.countryname }}
        </option>
      </select>
      <div class="invalid-feedback">{{ form.errors.countrycode }}</div>
    </div>

    <div class="col-md-6">
      <label class="form-label">{{ t.region_manager }}</label>
      <select
        v-model="form.regionmanagercode"
        class="form-select"
        :class="{ 'is-invalid': form.errors.regionmanagercode }"
        :disabled="isViewing"
      >
        <option :value="null">- {{ t.select }} -</option>
        <option
          v-for="manager in regionManagers"
          :key="manager.regionmanagercode"
          :value="manager.regionmanagercode"
        >
          {{ manager.regionmanagername }}
        </option>
      </select>
      <div class="invalid-feedback">{{ form.errors.regionmanagercode }}</div>
    </div>
  </div>
</template>
