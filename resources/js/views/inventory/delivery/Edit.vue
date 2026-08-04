<script setup>
import FormPage from "./FormPage.vue";

defineProps({
  deliveryData: { type: Object, required: true },
  lookupOptions: { type: Object, required: true },
  formMeta: { type: Object, required: true },
  useAlternateCode: { type: Boolean, default: false },
});
</script>

<template>
  <FormPage
    mode="edit"
    :delivery-data="deliveryData"
    :lookup-options="lookupOptions"
    :form-meta="formMeta"
    :use-alternate-code="useAlternateCode"
  />
</template>
