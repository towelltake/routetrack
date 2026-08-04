<script setup>
import { computed } from "vue";
import { usePage } from "@inertiajs/vue3";
import { reportModules } from "@/config/reports";

const page = usePage();
const t = computed(() => page.props.translations.ui ?? {});

function localizedLabel(node) {
  if (!node) {
    return "";
  }

  if (node.titleKey && t.value[node.titleKey]) {
    return t.value[node.titleKey];
  }

  return node.title ?? node.name ?? "";
}

function canView(permission) {
  if (!permission) {
    return true;
  }

  const details = page.props.auth?.formPermissions?.[permission];

  return !!(details?.all || details?.view || details?.read);
}

const navigationNodes = computed(() =>
  reportModules
    .filter((module) => canView(module.permission))
    .map((module) => ({
      name: localizedLabel(module),
      icon: module.icon,
      sub: module.groups
        .map((group) => ({
          name: localizedLabel(group),
          icon: group.icon,
          sub: group.items
            .filter((item) => canView(item.permission))
            .map((item) => ({
              name: localizedLabel(item),
              icon: item.icon,
              to: item.href,
            })),
        }))
        .filter((group) => group.sub.length),
    }))
    .filter((module) => module.sub.length),
);
</script>

<template>
  <div class="content-side content-side-full">
    <BaseNavigation :nodes="navigationNodes" />
  </div>
</template>
