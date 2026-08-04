<script setup>
import { router } from "@inertiajs/vue3";
import { useTemplateStore } from "@/stores/template";

// Main store
const store = useTemplateStore();

// Get saved dark mode
const savedDarkMode = localStorage.getItem("oneuiVueDarkMode");

// Set dark mode off
store.darkMode({ mode: "off" });

// Set example settings
store.sidebarStyle({ mode: "light" });

// Restore original dark mode preference before leaving this page
router.on("start", () => {
  store.darkMode({ mode: savedDarkMode });
});
</script>

<template>
  <Head title="Light - Sidebar - Layout" />

  <!-- Hero -->
  <BasePageHeading title="Sidebar" subtitle="Light">
    <template #extra>
      <nav aria-label="breadcrumb">
        <ol class="breadcrumb breadcrumb-alt">
          <li class="breadcrumb-item">
            <a class="link-fx" href="javascript:void(0)">Layout</a>
          </li>
          <li class="breadcrumb-item">
            <a class="link-fx" href="javascript:void(0)">Sidebar</a>
          </li>
          <li class="breadcrumb-item" aria-current="page">Light</li>
        </ol>
      </nav>
    </template>
  </BasePageHeading>
  <!-- END Hero -->

  <!-- Page Content -->
  <div class="content">
    <BaseBlock class="text-center">
      <p class="text-center">
        You can have a light themed Sidebar (works with Dark Mode off).
      </p>
    </BaseBlock>
  </div>
  <!-- END Page Content -->
</template>
