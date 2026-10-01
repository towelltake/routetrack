<script setup>
import { computed, onMounted } from "vue";
import { usePage } from "@inertiajs/vue3";
import { useTemplateStore } from "@/stores/template";

import BaseNavigation from "@/components/BaseNavigation.vue";
import { navigation as navigationConfig } from "@/config/navigation";

// SimpleBar, for more info and examples you can check out https://github.com/Grsmto/simplebar/tree/master/packages/simplebar-vue
import SimpleBar from "simplebar";

// Component properties
defineProps({
  withMiniNav: {
    type: Boolean,
    default: false,
    description: "If the sidebar is in Mini Nav Mode",
  },
});

// Main store
const store = useTemplateStore();
const page = usePage();
const t = computed(() => page.props.translations?.ui ?? {});

const navigation = computed(() =>
  navigationConfig
    .map((item) => translateNode(item))
    .filter(Boolean),
);

function translateNode(node) {
  if (!canViewNode(node)) {
    return null;
  }

  const translated = { ...node };

  if (node.label) {
    translated.name = t.value[node.label] ?? node.label;
  }

  if (node.heading) {
    translated.heading = t.value[node.heading] ?? node.heading;
  }

  if (node.sub) {
    translated.sub = node.sub.map(translateNode).filter(Boolean);
    if (!translated.sub.length) {
      return null;
    }
  }

  return translated;
}

function canViewNode(node) {
  return true;
}

// Init SimpleBar (custom scrolling)
onMounted(() => {
  new SimpleBar(document.getElementById("simplebar-sidebar"));
});
</script>

<template>
  <!-- Sidebar -->
  <!--
    Sidebar Mini Mode - Display Helper classes

    Adding 'smini-hide' class to an element will make it invisible (opacity: 0) when the sidebar is in mini mode
    Adding 'smini-show' class to an element will make it visible (opacity: 1) when the sidebar is in mini mode
    If you would like to disable the transition animation, make sure to also add the 'no-transition' class to your element

    Adding 'smini-hidden' to an element will hide it when the sidebar is in mini mode
    Adding 'smini-visible' to an element will show it (display: inline-block) only when the sidebar is in mini mode
    Adding 'smini-visible-block' to an element will show it (display: block) only when the sidebar is in mini mode
  -->
  <nav
    id="sidebar"
    :class="{ 'with-mini-nav': withMiniNav }"
    aria-label="Main Navigation"
  >
    <slot>
      <!-- Side Header -->
      <div class="content-header">
        <slot name="header">
          <!-- Logo -->
          <Link href="/" class="fw-semibold text-dual">
            <span class="smini-visible">
              <i class="fa fa-circle-notch text-primary"></i>
            </span>
            <span class="smini-hide fs-5 tracking-wider">
              TRAC
              <span class="fw-normal">1.0</span>
            </span>
          </Link>
          <!-- END Logo -->
        </slot>

        <!-- Extra -->
        <div>
          <slot name="header-extra"></slot>

          <!-- Close Sidebar, Visible only on mobile screens -->
          <button
            type="button"
            class="d-lg-none btn btn-sm btn-alt-secondary ms-1"
            @click="store.sidebar({ mode: 'close' })"
          >
            <i class="fa fa-fw fa-times"></i>
          </button>
          <!-- END Close Sidebar -->
        </div>
        <!-- END Extra -->
      </div>
      <!-- END Side Header -->

      <!-- Sidebar Scrolling -->
      <div id="simplebar-sidebar" class="js-sidebar-scroll">
        <slot name="content">
          <!-- Side Navigation -->
          <div class="content-side">
            <BaseNavigation :nodes="navigation" />
          </div>
          <!-- END Side Navigation -->
        </slot>
      </div>
      <!-- END Sidebar Scrolling -->
    </slot>
  </nav>
  <!-- END Sidebar -->
</template>
