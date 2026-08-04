<script setup>
import FormPage from "./FormPage.vue";

defineProps({
  detailData: { type: Object, required: true },
  lookupOptions: { type: Object, required: true },
  formMeta: { type: Object, required: true },
});
</script>

<template>
  <FormPage mode="edit" :detail-data="detailData" :lookup-options="lookupOptions" :form-meta="formMeta" />
</template>
