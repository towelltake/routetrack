<script setup>
import { computed } from "vue";
import { Head, router, usePage } from "@inertiajs/vue3";
import { reportModules } from "@/config/reports";

const page = usePage();
const t = page.props.translations.ui;

function canView(permission) {
  if (!permission) {
    return true;
  }

  const details = page.props.auth?.formPermissions?.[permission];

  return !!(details?.all || details?.view || details?.read);
}

const availableReportModules = computed(() =>
  reportModules
    .filter((module) => canView(module.permission))
    .map((module) => ({
      ...module,
      groups: module.groups
        .map((group) => ({
          ...group,
          items: group.items.filter((item) => canView(item.permission)),
        }))
        .filter((group) => group.items.length),
    }))
    .filter((module) => module.groups.length),
);

function itemCount(module) {
  return module.groups.reduce((total, group) => total + group.items.length, 0);
}

function moduleTitle(module) {
  return module.titleKey ? (t[module.titleKey] ?? module.title) : module.title;
}

function openModule(module) {
  const firstItem = module.groups[0]?.items[0];

  if (firstItem?.href) {
    router.get(firstItem.href);
  }
}
</script>

<template>
  <Head :title="t.reports" />

  <BasePageHeading
    :title="t.reports"
    :subtitle="t.reports_note"
  />

  <div class="content">
    <div class="row g-4">
      <div
        v-for="module in availableReportModules"
        :key="module.key"
        class="col-md-6"
      >
        <BaseBlock
          tag="button"
          class="w-100 h-100 text-start"
          transparent
          @click="openModule(module)"
        >
          <div class="d-flex align-items-start gap-3">
            <div class="fs-2 text-primary">
              <i :class="module.icon"></i>
            </div>
            <div>
              <div class="fw-semibold fs-4 mb-1">{{ moduleTitle(module) }}</div>
              <div class="text-muted mb-3">
                {{ t.report_module_structure }}
              </div>
              <div class="fs-sm text-primary">
                {{ t.reports_available.replace(':count', String(itemCount(module))) }}
              </div>
            </div>
          </div>
        </BaseBlock>
      </div>
    </div>
  </div>
</template>
