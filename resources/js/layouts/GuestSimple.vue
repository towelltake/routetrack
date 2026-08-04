<script setup>
import { useTemplateStore } from "@/stores/template";

import BaseLayout from "@/layouts/BaseLayout.vue";

// Main store
const store = useTemplateStore();

// Set default elements for this layout
store.setLayout({
  header: false,
  sidebar: false,
  sideOverlay: false,
  footer: false,
});
</script>

<template>
  <BaseLayout>
    <slot />
  </BaseLayout>
</template>
