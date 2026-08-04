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
    <div class="col-md-6">
      <label class="form-label">{{ t.alternate_code }} <span class="text-danger">*</span></label>
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
      <label class="form-label">{{ t.bank_name }} <span class="text-danger">*</span></label>
      <input
        v-model="form.bankname"
        class="form-control"
        :class="{ 'is-invalid': form.errors.bankname }"
        maxlength="50"
        :readonly="isViewing"
      />
      <div class="invalid-feedback">{{ form.errors.bankname }}</div>
    </div>

    <div class="col-md-6">
      <label class="form-label">{{ t.arab_depot_name }}</label>
      <input
        v-model="form.arbbankname"
        class="form-control"
        dir="rtl"
        maxlength="50"
        :readonly="isViewing"
      />
    </div>

    <div class="col-md-6">
      <label class="form-label">{{ t.account_number }}</label>
      <input
        v-model="form.acnumber"
        class="form-control"
        :class="{ 'is-invalid': form.errors.acnumber }"
        maxlength="18"
        :readonly="isViewing"
      />
      <div class="invalid-feedback">{{ form.errors.acnumber }}</div>
    </div>

    <div class="col-md-6">
      <label class="form-label">{{ t.bank_type }}</label>
      <select
        v-model="form.type"
        class="form-select"
        :class="{ 'is-invalid': form.errors.type }"
        :disabled="isViewing"
      >
        <option :value="null"></option>
        <option :value="1">{{ t.deposit }}</option>
        <option :value="2">{{ t.customer }}</option>
      </select>
      <div class="invalid-feedback">{{ form.errors.type }}</div>
    </div>

    <div class="col-md-6 d-flex align-items-end">
      <div class="form-check">
        <input
          id="bankactive"
          v-model="form.activestatus"
          :true-value="1"
          :false-value="0"
          type="checkbox"
          class="form-check-input"
          :disabled="isViewing"
        />
        <label class="form-check-label" for="bankactive">{{ t.status_active }}</label>
      </div>
    </div>
  </div>
</template>
