<script setup>
import FormPage from "./FormPage.vue";

defineProps({
  itemData: { type: Object, required: true },
  lookupOptions: { type: Object, required: true },
  optionSets: { type: Object, required: true },
});
</script>

<template>
  <FormPage mode="edit" :item-data="itemData" :lookup-options="lookupOptions" :option-sets="optionSets" />
</template>
