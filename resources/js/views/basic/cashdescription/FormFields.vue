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
      <label class="form-label">{{ t.description }} <span class="text-danger">*</span></label>
      <input
        v-model="form.description"
        class="form-control"
        :class="{ 'is-invalid': form.errors.description }"
        maxlength="50"
        :readonly="isViewing"
      />
      <div class="invalid-feedback">{{ form.errors.description }}</div>
    </div>

    <div class="col-md-6">
      <label class="form-label">{{ t.arabic_description }} <span class="text-danger">*</span></label>
      <input
        v-model="form.arbdescription"
        class="form-control"
        :class="{ 'is-invalid': form.errors.arbdescription }"
        dir="rtl"
        maxlength="50"
        :readonly="isViewing"
      />
      <div class="invalid-feedback">{{ form.errors.arbdescription }}</div>
    </div>
  </div>
</template>
