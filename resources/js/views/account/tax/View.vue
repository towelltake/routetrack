<script setup>
import FormPage from "./FormPage.vue";

defineProps({
  taxData: { type: Object, required: true },
  optionSets: { type: Object, required: true },
});
</script>

<template>
  <FormPage mode="view" :tax-data="taxData" :option-sets="optionSets" />
</template>
