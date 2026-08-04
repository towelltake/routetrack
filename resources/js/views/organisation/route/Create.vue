<script setup>
import FormPage from "./FormPage.vue";

defineProps({
  routeData: Object,
  lookupOptions: Object,
  optionSets: Object,
  selectedTemplateId: [Number, String],
});
</script>

<template>
  <FormPage
    mode="create"
    :route-data="routeData"
    :lookup-options="lookupOptions"
    :option-sets="optionSets"
    :selected-template-id="selectedTemplateId"
  />
</template>
