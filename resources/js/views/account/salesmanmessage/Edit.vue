<script setup>
import FormPage from "./FormPage.vue";

defineProps({
  messageData: { type: Object, required: true },
  optionSets: { type: Object, required: true },
});
</script>

<template>
  <FormPage mode="edit" :message-data="messageData" :option-sets="optionSets" />
</template>
