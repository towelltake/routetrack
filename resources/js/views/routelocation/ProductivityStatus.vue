<script setup>
defineProps({ status: { type: String, default: '' }, ignored: Boolean });
</script>
<template>
    <span v-if="ignored">LPO Customers</span>
    <span v-else-if="['Productive', 'Nonproductive'].includes(status)" class="productivity-status" :class="status === 'Productive' ? 'productive' : 'nonproductive'" :aria-label="status" :title="status"><i class="fa" :class="status === 'Productive' ? 'fa-check-circle' : 'fa-times-circle'" aria-hidden="true"></i><span class="visually-hidden">{{ status }}</span></span>
    <span v-else>{{ status }}</span>
</template>
<style scoped>
.productivity-status { font-size: 20px; }
.productive { color: #15803d; }
.nonproductive { color: #dc2626; }
</style>
