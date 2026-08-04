<script setup>
import { usePage } from "@inertiajs/vue3";

defineProps({
  form: {
    type: Object,
    required: true,
  },
  currencies: {
    type: Array,
    required: true,
  },
  companies: {
    type: Array,
    required: true,
  },
  nationalSalesManagers: {
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
      <label class="form-label">{{ t.country_name }} <span class="text-danger">*</span></label>
      <input
        v-model="form.countryname"
        class="form-control"
        :class="{ 'is-invalid': form.errors.countryname }"
        maxlength="50"
        :readonly="isViewing"
      />
      <div class="invalid-feedback">{{ form.errors.countryname }}</div>
    </div>

    <div class="col-md-4">
      <label class="form-label">{{ t.arb_country_name }}</label>
      <input
        v-model="form.arbcountryname"
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

    <div class="col-md-4">
      <label class="form-label">{{ t.currency }} <span class="text-danger">*</span></label>
      <select
        v-model="form.currencycode"
        class="form-select"
        :class="{ 'is-invalid': form.errors.currencycode }"
        :disabled="isViewing"
      >
        <option :value="null">- {{ t.select }} -</option>
        <option
          v-for="currency in currencies"
          :key="currency.currencycode"
          :value="currency.currencycode"
        >
          {{ currency.currencyname }}
        </option>
      </select>
      <div class="invalid-feedback">{{ form.errors.currencycode }}</div>
    </div>

    <div class="col-md-4">
      <label class="form-label">{{ t.company }} <span class="text-danger">*</span></label>
      <select
        v-model="form.cmpycode"
        class="form-select"
        :class="{ 'is-invalid': form.errors.cmpycode }"
        :disabled="isViewing"
      >
        <option :value="null">- {{ t.select }} -</option>
        <option v-for="company in companies" :key="company.cmpycode" :value="company.cmpycode">
          {{ company.name }}
        </option>
      </select>
      <div class="invalid-feedback">{{ form.errors.cmpycode }}</div>
    </div>

    <div class="col-md-4">
      <label class="form-label">{{ t.national_sales_manager }}</label>
      <select
        v-model="form.nationalsalesmanagercode"
        class="form-select"
        :class="{ 'is-invalid': form.errors.nationalsalesmanagercode }"
        :disabled="isViewing"
      >
        <option :value="null">- {{ t.select }} -</option>
        <option
          v-for="manager in nationalSalesManagers"
          :key="manager.nationalsalesmanagercode"
          :value="manager.nationalsalesmanagercode"
        >
          {{ manager.nationalsalesmanagername }}
        </option>
      </select>
      <div class="invalid-feedback">{{ form.errors.nationalsalesmanagercode }}</div>
    </div>

    <div class="col-md-4">
      <label class="form-label">{{ t.price_change_variance }}</label>
      <input
        v-model="form.pricechangevariance"
        type="number"
        min="0"
        step="1"
        class="form-control"
        :class="{ 'is-invalid': form.errors.pricechangevariance }"
        :readonly="isViewing"
      />
      <div class="invalid-feedback">{{ form.errors.pricechangevariance }}</div>
    </div>
  </div>
</template>
