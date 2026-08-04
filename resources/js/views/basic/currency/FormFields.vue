<script setup>
import { usePage } from "@inertiajs/vue3";

defineProps({
  form: {
    type: Object,
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
    <div class="col-6">
      <label class="form-label">{{ t.alternate_code }}</label>
      <input
        v-model="form.alternatecode"
        class="form-control"
        :class="{ 'is-invalid': form.errors.alternatecode }"
        maxlength="20"
        :readonly="isViewing"
      />
      <div class="invalid-feedback">{{ form.errors.alternatecode }}</div>
    </div>
    <div class="col-6">
      <label class="form-label">{{ t.symbol }} <span class="text-danger">*</span></label>
      <input
        v-model="form.currencysymbol"
        class="form-control"
        :class="{ 'is-invalid': form.errors.currencysymbol }"
        maxlength="10"
        :readonly="isViewing"
      />
      <div class="invalid-feedback">{{ form.errors.currencysymbol }}</div>
    </div>
    <div class="col-6">
      <label class="form-label">{{ t.currency_name }} <span class="text-danger">*</span></label>
      <input
        v-model="form.currencyname"
        class="form-control"
        :class="{ 'is-invalid': form.errors.currencyname }"
        :readonly="isViewing"
      />
      <div class="invalid-feedback">{{ form.errors.currencyname }}</div>
    </div>
    <div class="col-6">
      <label class="form-label">{{ t.arab_depot_name }}</label>
      <input
        v-model="form.arbcurrencyname"
        class="form-control"
        dir="rtl"
        :readonly="isViewing"
      />
    </div>
    <div class="col-6">
      <label class="form-label">{{ t.decimal_places }}</label>
      <input
        v-model.number="form.decimalplaces"
        type="number"
        min="0"
        max="6"
        class="form-control"
        :readonly="isViewing"
      />
    </div>
    <div class="col-6 d-flex align-items-end">
      <div class="form-check">
        <input
          id="defcur"
          v-model="form.defaultcurrency"
          :true-value="1"
          :false-value="0"
          type="checkbox"
          class="form-check-input"
          :disabled="isViewing"
        />
        <label class="form-check-label" for="defcur">{{ t.default_currency }}</label>
      </div>
    </div>
  </div>
</template>
