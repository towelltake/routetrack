<script setup>
import { usePage } from "@inertiajs/vue3";

defineProps({
  form: {
    type: Object,
    required: true,
  },
  companies: {
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
    <div class="col-md-6">
      <label class="form-label">{{ t.alternate_code }}</label>
      <input
        v-model="form.alternateareamanagercode"
        class="form-control"
        :class="{ 'is-invalid': form.errors.alternateareamanagercode }"
        maxlength="50"
        :readonly="isViewing"
      />
      <div class="invalid-feedback">{{ form.errors.alternateareamanagercode }}</div>
    </div>

    <div class="col-md-6">
      <label class="form-label">{{ t.area_manager_name }} <span class="text-danger">*</span></label>
      <input
        v-model="form.areamanagername"
        class="form-control"
        :class="{ 'is-invalid': form.errors.areamanagername }"
        maxlength="50"
        :readonly="isViewing"
      />
      <div class="invalid-feedback">{{ form.errors.areamanagername }}</div>
    </div>

    <div class="col-md-6">
      <label class="form-label">{{ t.arabic_area_manager_name }} <span class="text-danger">*</span></label>
      <input
        v-model="form.arbareamanagername"
        class="form-control"
        :class="{ 'is-invalid': form.errors.arbareamanagername }"
        dir="rtl"
        maxlength="50"
        :readonly="isViewing"
      />
      <div class="invalid-feedback">{{ form.errors.arbareamanagername }}</div>
    </div>

    <div class="col-md-6">
      <label class="form-label">{{ t.parent_company_label }}</label>
      <select
        v-model="form.parentcompany"
        class="form-select"
        :class="{ 'is-invalid': form.errors.parentcompany }"
        :disabled="isViewing"
      >
        <option :value="null">- {{ t.select }} -</option>
        <option v-for="company in companies" :key="company.cmpycode" :value="company.cmpycode">
          {{ company.name }}
        </option>
      </select>
      <div class="invalid-feedback">{{ form.errors.parentcompany }}</div>
    </div>

    <div class="col-12 d-flex align-items-center">
      <div class="form-check">
        <input
          id="area-manager-active"
          v-model="form.activestatus"
          :true-value="1"
          :false-value="0"
          type="checkbox"
          class="form-check-input"
          :disabled="isViewing"
        />
        <label class="form-check-label" for="area-manager-active">{{ t.status_active }}</label>
      </div>
    </div>
  </div>
</template>
