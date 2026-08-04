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
        v-model="form.alternatebranchmanagercode"
        class="form-control"
        :class="{ 'is-invalid': form.errors.alternatebranchmanagercode }"
        maxlength="50"
        :readonly="isViewing"
      />
      <div class="invalid-feedback">{{ form.errors.alternatebranchmanagercode }}</div>
    </div>

    <div class="col-md-6">
      <label class="form-label">{{ t.branch_depot_manager_name }} <span class="text-danger">*</span></label>
      <input
        v-model="form.branchmanagername"
        class="form-control"
        :class="{ 'is-invalid': form.errors.branchmanagername }"
        maxlength="50"
        :readonly="isViewing"
      />
      <div class="invalid-feedback">{{ form.errors.branchmanagername }}</div>
    </div>

    <div class="col-md-6">
      <label class="form-label">{{ t.arabic_branch_depot_manager_name }} <span class="text-danger">*</span></label>
      <input
        v-model="form.arbbranchmanagername"
        class="form-control"
        :class="{ 'is-invalid': form.errors.arbbranchmanagername }"
        dir="rtl"
        maxlength="50"
        :readonly="isViewing"
      />
      <div class="invalid-feedback">{{ form.errors.arbbranchmanagername }}</div>
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
          id="branch-manager-active"
          v-model="form.activestatus"
          :true-value="1"
          :false-value="0"
          type="checkbox"
          class="form-check-input"
          :disabled="isViewing"
        />
        <label class="form-check-label" for="branch-manager-active">{{ t.status_active }}</label>
      </div>
    </div>
  </div>
</template>
