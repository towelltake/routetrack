<script setup>
import FormPage from "./FormPage.vue";

defineProps({
  salesmanData: { type: Object, required: true },
  optionSets: { type: Object, required: true },
});
</script>

<template>
  <FormPage mode="edit" :salesman-data="salesmanData" :option-sets="optionSets" />
</template>
