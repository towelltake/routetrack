<script setup>
import FormPage from "./FormPage.vue";

defineProps({
  routeData: Object,
  lookupOptions: Object,
  optionSets: Object,
});
</script>

<template>
  <FormPage
    mode="view"
    :route-data="routeData"
    :lookup-options="lookupOptions"
    :option-sets="optionSets"
  />
</template>
