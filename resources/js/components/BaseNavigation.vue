<script setup>
import { computed } from "vue";
import { usePage } from "@inertiajs/vue3";
import { useTemplateStore } from "@/stores/template";

const page = usePage();
const currentPath = computed(() => page.url.split("?")[0]); // ✅ no query

// Main store
const store = useTemplateStore();

// Props
const props = defineProps({
  nodes: { type: Array, description: "The nodes of the navigation" },
  subMenu: { type: Boolean, default: false },
  dark: { type: Boolean, default: false },
  horizontal: { type: Boolean, default: false },
  horizontalHover: { type: Boolean, default: false },
  horizontalCenter: { type: Boolean, default: false },
  horizontalJustify: { type: Boolean, default: false },
  disableClick: { type: Boolean, default: false },
});

// Classes
const classContainer = computed(() => ({
  "nav-main": !props.subMenu,
  "nav-main-submenu": props.subMenu,
  "nav-main-dark": props.dark,
  "nav-main-horizontal": props.horizontal,
  "nav-main-hover": props.horizontalHover,
  "nav-main-horizontal-center": props.horizontalCenter,
  "nav-main-horizontal-justify": props.horizontalJustify,
}));

// ✅ Checks if submenu path is active (ignores query string)
function subIsActive(paths) {
  const activePaths = Array.isArray(paths) ? paths : [paths];
  const pathOnly = currentPath.value;

  return activePaths.some((p) => typeof p === "string" && p && pathOnly.startsWith(p));
}

function collectSubPaths(nodes = []) {
  return nodes.flatMap((node) => {
    const paths = [];

    if (typeof node?.to === "string" && node.to && node.to !== "#") {
      paths.push(node.to);
    }

    if (Array.isArray(node?.sub) && node.sub.length) {
      paths.push(...collectSubPaths(node.sub));
    }

    return paths;
  });
}

// Click handler
function linkClicked(e, submenu) {
  if (submenu) {
    const el = e.target.closest("li");

    if (
      !(
        window.innerWidth > 991 &&
        ((props.horizontal && props.horizontalHover) || props.disableClick)
      )
    ) {
      if (el.classList.contains("open")) {
        el.classList.remove("open");
      } else {
        Array.from(el.closest("ul").children).forEach((element) => {
          element.classList.remove("open");
        });
        el.classList.add("open");
      }
    }
  } else {
    if (window.innerWidth < 992) {
      store.sidebar({ mode: "close" });
    }
  }
}

// ✅ Active for normal links (ignores query string)
function isActive(to) {
  if (!to || to === "#") return false;
  const path = currentPath.value;
  return path === to || path.startsWith(to + "/");
}
</script>

<template>
  <ul :class="classContainer">
    <li
      v-for="(node, index) in nodes"
      :key="`node-${index}`"
      :class="{
        'nav-main-heading': node.heading,
        'nav-main-item': !node.heading,
        open: node.sub ? subIsActive(node.subActivePaths ?? collectSubPaths(node.sub)) : false,
      }"
    >
      <!-- Heading -->
      {{ node.heading ? node.name : "" }}

      <!-- Normal Link -->
      <div v-if="!node.heading && !node.sub" @click="linkClicked($event)">
        <Link
          v-if="!node.to.startsWith('http://') && !node.to.startsWith('https://')"
          :href="node.to && node.to !== '#' ? node.to : '#'"
          class="nav-main-link"
          :class="isActive(node.to) ? 'active' : ''"
        >
          <i v-if="node.icon" :class="`nav-main-link-icon ${node.icon}`"></i>
          <span v-if="node.name" class="nav-main-link-name">{{ node.name }}</span>

          <span
            v-if="node.badge"
            class="nav-main-link-badge badge rounded-pill"
            :class="node['badge-variant'] ? `bg-${node['badge-variant']}` : 'bg-primary'"
          >
            {{ node.badge }}
          </span>
        </Link>

        <a
          v-else
          :href="node.to"
          class="nav-main-link"
          :target="node.target || null"
        >
          <i v-if="node.icon" :class="`nav-main-link-icon ${node.icon}`"></i>
          <span v-if="node.name" class="nav-main-link-name">{{ node.name }}</span>
        </a>
      </div>

      <!-- Submenu Link -->
      <a
        v-else-if="!node.heading && node.sub"
        href="#"
        class="nav-main-link nav-main-link-submenu"
        @click.prevent="linkClicked($event, true)"
      >
        <i v-if="node.icon" :class="`nav-main-link-icon ${node.icon}`"></i>
        <span v-if="node.name" class="nav-main-link-name">{{ node.name }}</span>
      </a>

      <!-- Sub Navigation -->
      <BaseNavigation
        v-if="node.sub"
        :nodes="node.sub"
        sub-menu
        :disable-click="props.horizontal && props.horizontalHover"
      />
    </li>
  </ul>
</template>
