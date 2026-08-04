<script setup>
import FormPage from "./FormPage.vue";

defineProps({
  loadData: { type: Object, required: true },
  lookupOptions: { type: Object, required: true },
  formMeta: { type: Object, required: true },
  useAlternateCode: { type: Boolean, default: false },
});
</script>

<template>
  <FormPage
    mode="edit"
    :load-data="loadData"
    :lookup-options="lookupOptions"
    :form-meta="formMeta"
    :use-alternate-code="useAlternateCode"
  />
</template>
