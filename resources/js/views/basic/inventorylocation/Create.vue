<script setup>
import { usePage } from "@inertiajs/vue3";
import FormFields from "./FormFields.vue";

defineProps({
  form: {
    type: Object,
    required: true,
  },
});

const emit = defineEmits(["close", "submit"]);
const t = usePage().props.translations.ui;
</script>

<template>
  <div
    class="modal fade show d-block"
    style="background: rgba(0, 0, 0, 0.45)"
  >
    <div class="modal-dialog modal-dialog-centered">
      <div class="modal-content">
        <div class="modal-header">
          <h5 class="modal-title">{{ t.create_inventory_location }}</h5>
          <button class="btn-close" @click="emit('close')"></button>
        </div>
        <form @submit.prevent="emit('submit')">
          <FormFields :form="form" />
          <div class="modal-footer">
            <button type="button" class="btn btn-alt-secondary" @click="emit('close')">{{ t.cancel }}</button>
            <button type="submit" class="btn btn-primary" :disabled="form.processing">{{ t.create }}</button>
          </div>
        </form>
      </div>
    </div>
  </div>
</template>
