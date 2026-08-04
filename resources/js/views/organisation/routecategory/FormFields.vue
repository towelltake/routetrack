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
    <div class="col-md-6">
      <label class="form-label">{{ t.route_category_name }} <span class="text-danger">*</span></label>
      <input
        v-model="form.routecatname"
        class="form-control"
        :class="{ 'is-invalid': form.errors.routecatname }"
        maxlength="50"
        :readonly="isViewing"
      />
      <div class="invalid-feedback">{{ form.errors.routecatname }}</div>
    </div>

    <div class="col-md-6">
      <label class="form-label">{{ t.arb_route_category_name }}</label>
      <input
        v-model="form.arbroutecatname"
        class="form-control"
        dir="rtl"
        maxlength="50"
        :readonly="isViewing"
      />
    </div>
  </div>
</template>
