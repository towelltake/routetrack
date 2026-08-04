<script setup>
import { usePage } from "@inertiajs/vue3";

defineProps({
  form: { type: Object, required: true },
  isViewing: { type: Boolean, default: false },
});

const t = usePage().props.translations.ui;
</script>

<template>
  <div class="modal-body row g-3">
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
      <label class="form-label">{{ t.vehicle_registration }} <span class="text-danger">*</span></label>
      <input
        v-model="form.vanregno"
        class="form-control"
        :class="{ 'is-invalid': form.errors.vanregno }"
        maxlength="50"
        :readonly="isViewing"
      />
      <div class="invalid-feedback">{{ form.errors.vanregno }}</div>
    </div>

    <div class="col-md-4">
      <label class="form-label">{{ t.vanmodel }}</label>
      <input
        v-model="form.vanmodel"
        class="form-control"
        :class="{ 'is-invalid': form.errors.vanmodel }"
        maxlength="50"
        :readonly="isViewing"
      />
      <div class="invalid-feedback">{{ form.errors.vanmodel }}</div>
    </div>

    <div class="col-md-4">
      <label class="form-label">{{ t.vantype }}</label>
      <input
        v-model="form.vantype"
        class="form-control"
        :class="{ 'is-invalid': form.errors.vantype }"
        maxlength="50"
        :readonly="isViewing"
      />
      <div class="invalid-feedback">{{ form.errors.vantype }}</div>
    </div>

    <div class="col-md-4">
      <label class="form-label">{{ t.vehicle_description }} <span class="text-danger">*</span></label>
      <input
        v-model="form.vandescription"
        class="form-control"
        :class="{ 'is-invalid': form.errors.vandescription }"
        maxlength="50"
        :readonly="isViewing"
      />
      <div class="invalid-feedback">{{ form.errors.vandescription }}</div>
    </div>

    <div class="col-md-4">
      <label class="form-label">{{ t.arab_vehicle_description }}</label>
      <input
        v-model="form.arbvandescription"
        class="form-control"
        dir="rtl"
        maxlength="50"
        :readonly="isViewing"
      />
    </div>

    <div class="col-12">
      <div class="form-check">
        <input
          id="van-active"
          v-model="form.activestatus"
          :true-value="1"
          :false-value="0"
          type="checkbox"
          class="form-check-input"
          :disabled="isViewing"
        />
        <label class="form-check-label" for="van-active">{{ t.status_active }}</label>
      </div>
    </div>
  </div>
</template>
