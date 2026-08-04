<script setup>
import FormPage from "./FormPage.vue";

defineProps({
  groupData: { type: Object, required: true },
  itemGroupOptions: { type: Array, required: true },
  assignedItems: { type: Array, default: () => [] },
  itemGroupItemsUrl: { type: String, required: true },
});
</script>

<template>
  <FormPage
    mode="view"
    :group-data="groupData"
    :item-group-options="itemGroupOptions"
    :assigned-items="assignedItems"
    :item-group-items-url="itemGroupItemsUrl"
  />
</template>
