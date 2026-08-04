<script setup>
import FormPage from "./FormPage.vue";

defineProps({
  channelData: { type: Object, required: true },
  optionSets: { type: Object, required: true },
});
</script>

<template>
  <FormPage mode="view" :channel-data="channelData" :option-sets="optionSets" />
</template>
