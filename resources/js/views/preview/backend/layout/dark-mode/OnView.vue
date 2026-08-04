<script setup>
import { router } from "@inertiajs/vue3";
import { useTemplateStore } from "@/stores/template";

// Main store
const store = useTemplateStore();

// Get saved dark mode
const savedDarkMode = localStorage.getItem("oneuiVueDarkMode");

// Set dark mode on
store.darkMode({ mode: "on" });

// Restore original dark mode preference before leaving this page
router.on("start", () => {
  store.darkMode({ mode: savedDarkMode });
});
</script>

<template>
  <Head title="Dark Mode On - Layout" />

  <!-- Hero -->
  <BasePageHeading title="Dark Mode" subtitle="On">
    <template #extra>
      <nav aria-label="breadcrumb">
        <ol class="breadcrumb breadcrumb-alt">
          <li class="breadcrumb-item">
            <a class="link-fx" href="javascript:void(0)">Layout</a>
          </li>
          <li class="breadcrumb-item">
            <a class="link-fx" href="javascript:void(0)">Dark Mode</a>
          </li>
          <li class="breadcrumb-item" aria-current="page">On</li>
        </ol>
      </nav>
    </template>
  </BasePageHeading>
  <!-- END Hero -->

  <!-- Page Content -->
  <div class="content">
    <BaseBlock class="text-center">
      <p>Dark Mode enabled.</p>
    </BaseBlock>
  </div>
  <!-- END Page Content -->
</template>
