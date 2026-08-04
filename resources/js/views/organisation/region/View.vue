<script setup>
import { usePage } from "@inertiajs/vue3";
import FormFields from "./FormFields.vue";

defineProps({
  form: { type: Object, required: true },
  countries: { type: Array, required: true },
  regionManagers: { type: Array, required: true },
});

defineEmits(["close"]);
const t = usePage().props.translations.ui;
</script>

<template>
  <div class="modal fade show d-block" style="background: rgba(0,0,0,.45)">
    <div class="modal-dialog modal-lg modal-dialog-centered">
      <div class="modal-content">
        <div class="modal-header">
          <h5 class="modal-title">{{ t.view_region }}</h5>
          <button class="btn-close" @click="$emit('close')"></button>
        </div>
        <FormFields
          :form="form"
          :countries="countries"
          :region-managers="regionManagers"
          :is-viewing="true"
        />
        <div class="modal-footer">
          <button type="button" class="btn btn-alt-secondary" @click="$emit('close')">{{ t.close }}</button>
        </div>
      </div>
    </div>
  </div>
</template>
