<script setup>
import { useTemplateStore } from "@/stores/template";

import BaseLayout from "@/layouts/BaseLayout.vue";

// Main store
const store = useTemplateStore();

// Set default elements for this layout
store.setLayout({
  header: true,
  sidebar: false,
  sideOverlay: false,
  footer: true,
});

// Set various template options for this layout variation
store.header({ mode: "static" });
store.headerStyle({ mode: "light" });
store.mainContent({ mode: "boxed" });
</script>

<template>
  <BaseLayout>
    <!-- Header Content Left -->
    <!-- Using the available v-slot, we can override the default Side Overlay content from layouts/partials/Header.vue -->
    <template #header-content-left>
      <div class="d-flex align-items-center">
        <!-- Logo -->
        <Link href="/" class="fw-bold fs-lg text-dual me-2">
          OneUI
          <span class="fw-medium">Vue</span>
        </Link>
        <!-- END Logo -->

        <!-- Version -->
        <div
          class="fs-xs fw-semibold py-1 px-2 rounded-pill bg-body-dark text-dark"
        >
          v{{ store.app.version }}
        </div>
      </div>
    </template>
    <!-- END Header Content Left -->

    <!-- Header Content Right -->
    <!-- Using the available v-slot, we can override the default Side Overlay content from layouts/partials/Header.vue -->
    <template #header-content-right>
      <!-- If user is authenticated -->
      <template v-if="$page.props.auth.user">
        <Link
          href="/dashboard"
          class="btn btn-sm btn-alt-secondary"
          v-click-ripple
        >
          Dashboard
          <i class="fa fa-fw fa-sign-in-alt ms-1 opacity-50"></i>
        </Link>
      </template>

      <!-- If user is not authenticated -->
      <template v-else>
        <!-- Login -->
        <Link href="/login" class="btn btn-sm btn-alt-secondary" v-click-ripple>
          <i class="fa fa-fw fa-sign-in-alt opacity-50"></i>
          <span class="d-none d-sm-inline-block ms-1">Log in</span>
        </Link>
        <!-- END Login -->

        <!-- Register -->
        <Link
          href="/register"
          class="btn btn-sm btn-alt-secondary ms-2"
          v-click-ripple
        >
          <i class="fa fa-fw fa-plus opacity-50"></i>
          <span class="d-none d-sm-inline-block ms-1">Create account</span>
        </Link>
        <!-- END Register -->
      </template>
    </template>
    <!-- END Header Content Right -->

    <slot />
  </BaseLayout>
</template>
