<script setup>
import FormPage from "./FormPage.vue";

defineProps({
  templateData: Object,
  lookupOptions: Object,
  optionSets: Object,
});
</script>

<template>
  <FormPage mode="create" :template-data="templateData" :lookup-options="lookupOptions" :option-sets="optionSets" />
</template>
