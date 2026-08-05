<script setup>
import { useTemplateStore } from "@/stores/template";
import AlertModal from "@/components/AlertModal.vue";
import BaseLayout from "@/layouts/BaseLayout.vue";
import BaseNavigation from "@/components/BaseNavigation.vue";
import FlashMessage from "@/components/FlashMessage.vue";
import { navigation } from "@/config/navigation";
import { useI18n } from "@/composables/useI18n";
import { computed } from "vue";
import { usePage } from "@inertiajs/vue3";

const page = usePage();
const t = computed(() => page.props.translations.ui) || {};

const { isRtl } = useI18n();

// Main store
const store = useTemplateStore();

// Set default elements for this layout
store.setLayout({
  header: true,
  sidebar: true,
  sideOverlay: true,
  footer: true,
});

// Set various template options for this layout variation
store.header({ mode: "fixed" });
store.headerStyle({ mode: "light" });
store.mainContent({ mode: "narrow" });

const translatedNavigation = computed(() =>
  navigation
    .map((item) => translateNode(item))
    .filter(Boolean),
);

const availableSettingsModules = computed(() => []);

const currentPath = computed(() => page.url.split("?")[0]);

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

function isMenuItemActive(item) {
  return currentPath.value === item.href;
}

function markLogoutRedirect() {
  window.sessionStorage.setItem("trac_force_login_redirect", "1");
}
</script>

<template>
  <BaseLayout>
    <!-- Sidebar Content -->
    <!-- Using the available v-slot, we can override the default Sidebar content from layouts/partials/Sidebar.vue -->
    <template #sidebar-content>
      <div class="content-side">
        <BaseNavigation :nodes="translatedNavigation" />
      </div>
    </template>
    <!-- END Sidebar Content -->

    <!-- Header Content Left -->
    <!-- Using the available v-slot, we can override the default Header c	ontent from layouts/partials/Header.vue -->
    <template #header-content-left>
      <!-- Toggle Sidebar -->
      <button
        type="button"
        class="btn btn-sm btn-alt-secondary me-2"
        @click="store.sidebar({ mode: 'toggle' })"
      >
        <i class="fa fa-fw fa-bars"></i>
      </button>
      <!-- END Toggle Sidebar -->

      <div
        v-if="availableSettingsModules.length && !isRtl"
        class="dropdown d-inline-block"
      >
        <button
          class="btn btn-sm btn-alt-secondary"
          type="button"
          id="page-header-settings-mega-menu"
          data-bs-toggle="dropdown"
          data-bs-auto-close="true"
          aria-expanded="false"
        >
          <i class="fa fa-gear opacity-50 me-2"></i>
          <span>{{ t.settings ?? "Settings" }}</span>
          <i class="fa fa-fw fa-angle-down opacity-50 ms-2"></i>
        </button>
        <div
          :class="[
            'dropdown-menu dropdown-menu-mega p-0 border-0 settings-mega-menu',
            'settings-mega-menu-ltr',
          ]"
          aria-labelledby="page-header-settings-mega-menu"
        >
          <div
            class="px-3 py-3 bg-primary rounded-top d-flex align-items-center justify-content-between"
          >
            <div>
              <h3 class="h5 fw-semibold text-white mb-1">
                {{ t.settings ?? "Settings" }}
              </h3>
            </div>
            <i class="fa fa-2x fa-gear text-white opacity-25 ms-2"></i>
          </div>
          <div class="p-3">
            <div class="row fs-sm">
              <template
                v-for="module in availableSettingsModules"
                :key="module.key"
              >
                <div
                  v-for="group in module.groups"
                  :key="group.key"
                  class="col-lg-6"
                >
                  <h4
                    class="h6 p-2 mb-3 bg-body rounded-3 d-flex align-items-center text-nowrap"
                  >
                    <i :class="[group.icon, 'me-2']"></i>
                    <span>{{ group.title }}</span>
                  </h4>

                  <ul class="list list-simple-mini mb-0">
                    <li
                      v-for="item in group.items"
                      :key="item.key"
                    >
                      <Link
                        :href="item.href"
                        :class="[
                          'fw-semibold d-inline-flex align-items-center rounded px-2 py-1',
                          isMenuItemActive(item) ? 'mega-menu-link-active text-primary' : '',
                        ]"
                      >
                        <i
                          :class="[
                            item.icon,
                            'fa-fw me-1',
                            isMenuItemActive(item) ? 'text-primary' : 'text-primary-lighter',
                          ]"
                        ></i>
                        {{ item.title }}
                      </Link>
                    </li>
                  </ul>
                </div>
              </template>
            </div>
          </div>
        </div>
      </div>
    </template>
    <!-- END Header Content Left -->

    <!-- Header Content Right -->
    <!-- Using the available v-slot, we can override the default Header content from layouts/partials/Header.vue -->
    <template #header-content-right>
      <div
        v-if="availableSettingsModules.length && isRtl"
        class="dropdown d-inline-block ms-2"
      >
        <button
          class="btn btn-sm btn-alt-secondary"
          type="button"
          id="page-header-settings-mega-menu-rtl"
          data-bs-toggle="dropdown"
          data-bs-auto-close="true"
          aria-expanded="false"
        >
          <i class="fa fa-gear opacity-50 me-2"></i>
          <span>{{ t.settings ?? "Settings" }}</span>
          <i class="fa fa-fw fa-angle-down opacity-50 ms-2"></i>
        </button>
        <div
          class="dropdown-menu dropdown-menu-end dropdown-menu-mega p-0 border-0 settings-mega-menu"
          aria-labelledby="page-header-settings-mega-menu-rtl"
        >
          <div
            class="px-3 py-3 bg-primary rounded-top d-flex align-items-center justify-content-between"
          >
            <div>
              <h3 class="h5 fw-semibold text-white mb-1">
                {{ t.settings ?? "Settings" }}
              </h3>
            </div>
            <i class="fa fa-2x fa-gear text-white opacity-25 ms-2"></i>
          </div>
          <div class="p-3">
            <div class="row fs-sm">
              <template
                v-for="module in availableSettingsModules"
                :key="module.key"
              >
                <div
                  v-for="group in module.groups"
                  :key="group.key"
                  class="col-lg-6"
                >
                  <h4
                    class="h6 p-2 mb-3 bg-body rounded-3 d-flex align-items-center text-nowrap"
                  >
                    <i :class="[group.icon, 'me-2']"></i>
                    <span>{{ group.title }}</span>
                  </h4>

                  <ul class="list list-simple-mini mb-0">
                    <li
                      v-for="item in group.items"
                      :key="item.key"
                    >
                      <Link
                        :href="item.href"
                        :class="[
                          'fw-semibold d-inline-flex align-items-center rounded px-2 py-1',
                          isMenuItemActive(item) ? 'mega-menu-link-active text-primary' : '',
                        ]"
                      >
                        <i
                          :class="[
                            item.icon,
                            'fa-fw me-1',
                            isMenuItemActive(item) ? 'text-primary' : 'text-primary-lighter',
                          ]"
                        ></i>
                        {{ item.title }}
                      </Link>
                    </li>
                  </ul>
                </div>
              </template>
            </div>
          </div>
        </div>
      </div>

      <!-- User Dropdown -->

      <div class="dropdown d-inline-block ms-2">
        <button
          type="button"
          class="btn btn-sm btn-alt-secondary d-flex align-items-center"
          id="page-header-user-dropdown"
          data-bs-toggle="dropdown"
          aria-haspopup="true"
          aria-expanded="false"
        >
          <!-- <img
            class="rounded-circle"
            :src="$page.props.auth.user.gravatar"
            alt="User Avatar"
            style="width: 21px"
          /> -->
          <span class="d-none d-sm-inline-block ms-2">{{
            $page.props.auth.user.name.split(" ")[0]
          }}</span>
          <i
            class="fa fa-fw fa-angle-down d-none d-sm-inline-block opacity-50 ms-1 mt-1"
          ></i>
        </button>
        <div
          class="dropdown-menu dropdown-menu-md dropdown-menu-end p-0 border-0"
          aria-labelledby="page-header-user-dropdown"
        >
          <div class="p-2">
            <Link
              href="/profile"
              class="dropdown-item d-flex align-items-center justify-content-between"
            >
              <span class="fs-sm fw-medium">{{ t.profile ?? "Profile" }}</span>
            </Link>
            <Link
              href="/logout"
              method="post"
              class="dropdown-item d-flex align-items-center justify-content-between"
              as="button"
              @click="markLogoutRedirect"
            >
              <span class="fs-sm fw-medium">{{
                t.log_out ?? "Log Out"
              }}</span>
            </Link>
          </div>
        </div>
      </div>
      <!-- END User Dropdown -->

      <!-- Toggle Side Overlay -->
      <!-- <button
        type="button"
        class="btn btn-sm btn-alt-secondary ms-2"
        @click="store.sideOverlay({ mode: 'toggle' })"
      >
        <i class="fa fa-fw fa-list-ul fa-flip-horizontal"></i>
      </button> -->
      <!-- END Toggle Side Overlay -->
    </template>
    <!-- END Header Content Right -->

    <!-- Footer Content Left -->
    <!-- Using the available v-slot, we can override the default Footer content from layouts/partials/Footer.vue -->
    <template #footer-content-left>
      <strong>{{ store.app.version }}</strong>
      &copy; {{ store.app.copyright }}
    </template>
    <!-- END Footer Content Left -->

    <template #page-top-content>
      <AlertModal />
    </template>

    <template #default>
      <FlashMessage />
      <slot />
    </template>
  </BaseLayout>
</template>

<style scoped>
.settings-mega-menu {
  min-width: 420px;
  z-index: 1085;
}

.settings-mega-menu-ltr {
  left: 0;
  right: auto;
}

:deep(#page-container.rtl-support #page-header) {
  z-index: 1080;
}

.mega-menu-link-active {
  background: rgba(243, 244, 246, 0.88);
  border: 1px solid rgba(209, 213, 219, 0.75);
  box-shadow:
    0 10px 24px rgba(15, 23, 42, 0.08),
    inset 0 1px 0 rgba(255, 255, 255, 0.72);
  backdrop-filter: blur(12px) saturate(160%);
  -webkit-backdrop-filter: blur(12px) saturate(160%);
}
</style>
