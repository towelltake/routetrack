<script setup>
import { router } from "@inertiajs/vue3";
import { useTemplateStore } from "@/stores/template";

// Main store
const store = useTemplateStore();

// Set example settings
store.sideOverlay({ mode: "open" });

// Before leaving this page
router.on("start", () => {
  // Restore original settings
  store.sideOverlay({ mode: "close" });
});
</script>

<template>
  <Head title="Visible - Side Overlay - Layout" />

  <!-- Hero -->
  <BasePageHeading title="Side Overlay" subtitle="Visible">
    <template #extra>
      <nav aria-label="breadcrumb">
        <ol class="breadcrumb breadcrumb-alt">
          <li class="breadcrumb-item">
            <a class="link-fx" href="javascript:void(0)">Layout</a>
          </li>
          <li class="breadcrumb-item">
            <a class="link-fx" href="javascript:void(0)">Side Overlay</a>
          </li>
          <li class="breadcrumb-item" aria-current="page">Visible</li>
        </ol>
      </nav>
    </template>
  </BasePageHeading>
  <!-- END Hero -->

  <!-- Page Content -->
  <div class="content">
    <BaseBlock class="text-center">
      <p class="text-center">
        You can make the Side Overlay visible by default.
      </p>
    </BaseBlock>
  </div>
  <!-- END Page Content -->
</template>
