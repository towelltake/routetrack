<script setup>
defineProps({ onPremise: { default: null }, holdOrder: { default: false } });
</script>

<template>
    <span class="visit-badges">
        <span v-if="onPremise !== null" class="visit-badge" :class="onPremise ? 'on-premise' : 'off-premise'">{{ onPremise ? 'On premises' : 'Off premises' }}</span>
        <span v-if="holdOrder" class="visit-badge hold-order">Hold order</span>
    </span>
</template>

<style scoped>
.visit-badges { display: inline-flex; flex-wrap: wrap; gap: 4px; margin-left: 6px; }
.visit-badge { display: inline-block; padding: 2px 6px; border-radius: 5px; font-size: 11px; font-weight: 600; white-space: nowrap; }
.on-premise { background: #dcfce7; color: #166534; }
.off-premise { background: #fee2e2; color: #991b1b; }
.hold-order { background: #fef3c7; color: #92400e; }
</style>
