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
          <slot name="header-extra">
            <!-- Dark Mode -->
            <div class="dropdown d-inline-block mx-1">
              <button
                type="button"
                class="btn btn-sm btn-alt-secondary"
                id="sidebar-dark-mode-dropdown"
                data-bs-toggle="dropdown"
                aria-haspopup="true"
                aria-expanded="false"
              >
                <i
                  v-if="!store.settings.darkModeActive"
                  class="far fa-fw fa-moon"
                ></i>
                <i
                  v-if="store.settings.darkModeActive"
                  class="fa fa-fw fa-moon"
                ></i>
              </button>
              <div
                class="dropdown-menu dropdown-menu-end smini-hide border-0"
                aria-labelledby="sidebar-dark-mode-dropdown"
              >
                <button
                  @click="() => store.darkMode({ mode: 'off' })"
                  type="button"
                  class="dropdown-item d-flex align-items-center gap-2"
                  :class="{ active: store.settings.darkMode === 'off' }"
                >
                  <i class="far fa-sun fa-fw opacity-50"></i>
                  <span class="fs-sm fw-medium">Light</span>
                </button>
                <button
                  @click="() => store.darkMode({ mode: 'on' })"
                  type="button"
                  class="dropdown-item d-flex align-items-center gap-2"
                  :class="{ active: store.settings.darkMode === 'on' }"
                >
                  <i class="far fa-moon fa-fw opacity-50"></i>
                  <span class="fs-sm fw-medium">Dark</span>
                </button>
                <button
                  @click="() => store.darkMode({ mode: 'system' })"
                  type="button"
                  class="dropdown-item d-flex align-items-center gap-2"
                  :class="{ active: store.settings.darkMode === 'system' }"
                >
                  <i class="fa fa-desktop fa-fw opacity-50"></i>
                  <span class="fs-sm fw-medium">System</span>
                </button>
              </div>
            </div>
            <!-- END Dark Mode -->

            <!-- Options -->
            <div class="dropdown d-inline-block ms-1">
              <button
                type="button"
                class="btn btn-sm btn-alt-secondary"
                id="sidebar-themes-dropdown"
                data-bs-toggle="dropdown"
                aria-haspopup="true"
                aria-expanded="false"
              >
                <i class="fa fa-fw fa-brush"></i>
              </button>
              <div
                class="dropdown-menu dropdown-menu-end fs-sm smini-hide border-0"
                aria-labelledby="sidebar-themes-dropdown"
              >
                <!-- Color Themes -->
                <button
                  @click="store.setColorTheme({ theme: '' })"
                  type="button"
                  class="dropdown-item d-flex align-items-center justify-content-between fw-medium"
                  :class="{ active: store.settings.colorTheme === '' }"
                >
                  <span>Default</span>
                  <i class="fa fa-circle text-default"></i>
                </button>
                <button
                  @click="store.setColorTheme({ theme: 'amethyst' })"
                  type="button"
                  class="dropdown-item d-flex align-items-center justify-content-between fw-medium"
                  :class="{ active: store.settings.colorTheme === 'amethyst' }"
                >
                  <span>Amethyst</span>
                  <i class="fa fa-circle text-amethyst"></i>
                </button>
                <button
                  @click="store.setColorTheme({ theme: 'city' })"
                  type="button"
                  class="dropdown-item d-flex align-items-center justify-content-between fw-medium"
                  :class="{ active: store.settings.colorTheme === 'city' }"
                >
                  <span>City</span>
                  <i class="fa fa-circle text-city"></i>
                </button>
                <button
                  @click="store.setColorTheme({ theme: 'flat' })"
                  type="button"
                  class="dropdown-item d-flex align-items-center justify-content-between fw-medium"
                  :class="{ active: store.settings.colorTheme === 'flat' }"
                >
                  <span>Flat</span>
                  <i class="fa fa-circle text-flat"></i>
                </button>
                <button
                  @click="store.setColorTheme({ theme: 'modern' })"
                  type="button"
                  class="dropdown-item d-flex align-items-center justify-content-between fw-medium"
                  :class="{ active: store.settings.colorTheme === 'modern' }"
                >
                  <span>Modern</span>
                  <i class="fa fa-circle text-modern"></i>
                </button>
                <button
                  @click="store.setColorTheme({ theme: 'smooth' })"
                  type="button"
                  class="dropdown-item d-flex align-items-center justify-content-between fw-medium"
                  :class="{ active: store.settings.colorTheme === 'smooth' }"
                >
                  <span>Smooth</span>
                  <i class="fa fa-circle text-smooth"></i>
                </button>
                <!-- END Color Themes -->

                <template v-if="!store.settings.darkModeActive">
                  <div class="dropdown-divider"></div>

                  <!-- Sidebar Styles -->
                  <button
                    @click="store.sidebarStyle({ mode: 'light' })"
                    type="button"
                    class="dropdown-item fw-medium"
                    :class="{ active: !store.settings.sidebarDark }"
                  >
                    <span>Sidebar Light</span>
                  </button>
                  <button
                    @click="store.sidebarStyle({ mode: 'dark' })"
                    type="button"
                    class="dropdown-item fw-medium"
                    :class="{ active: store.settings.sidebarDark }"
                  >
                    <span>Sidebar Dark</span>
                  </button>
                  <!-- END Sidebar Styles -->

                  <div class="dropdown-divider"></div>

                  <!-- Header Styles -->
                  <button
                    @click="store.headerStyle({ mode: 'light' })"
                    type="button"
                    class="dropdown-item fw-medium"
                    :class="{ active: !store.settings.headerDark }"
                  >
                    <span>Header Light</span>
                  </button>
                  <button
                    @click="store.headerStyle({ mode: 'dark' })"
                    type="button"
                    class="dropdown-item fw-medium"
                    :class="{ active: store.settings.headerDark }"
                  >
                    <span>Header Dark</span>
                  </button>
                  <!-- END Header Styles -->
                </template>
              </div>
            </div>
            <!-- END Options -->
          </slot>

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
