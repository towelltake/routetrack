<script setup>
import FormPage from "./FormPage.vue";

defineProps({
  customerData: { type: Object, required: true },
  optionSets: { type: Object, required: true },
});
</script>

<template>
  <FormPage mode="create" :customer-data="customerData" :option-sets="optionSets" />
</template>
