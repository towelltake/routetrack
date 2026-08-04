<script setup>
import FormPage from "./FormPage.vue";

defineProps({
  routeItemGroupData: { type: Object, required: true },
  lookupOptions: { type: Object, required: true },
  formMeta: { type: Object, required: true },
  optionSets: { type: Object, required: true },
});
</script>

<template>
  <FormPage
    mode="create"
    :route-item-group-data="routeItemGroupData"
    :lookup-options="lookupOptions"
    :form-meta="formMeta"
    :option-sets="optionSets"
  />
</template>
