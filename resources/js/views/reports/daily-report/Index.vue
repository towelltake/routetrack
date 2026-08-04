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

const reportsModule = reportModules.find((module) => module.key === "reports");
const dailyReportGroup = computed(() => {
  if (!reportsModule || !canView(reportsModule.permission)) {
    return null;
  }

  const group = reportsModule.groups.find((item) => item.key === "daily-reports");

  if (!group) {
    return null;
  }

  return {
    ...group,
    items: group.items.filter((item) => canView(item.permission)),
  };
});

function reportTitle(report) {
  return report.titleKey ? (t[report.titleKey] ?? report.title) : report.title;
}

function reportDescription(report) {
  return report.descriptionKey ? (t[report.descriptionKey] ?? report.description) : report.description;
}
</script>

<template>
  <Head :title="t.daily_report" />

  <BasePageHeading
    :title="t.daily_report"
    :subtitle="t.daily_report_note"
  />

  <div class="content">
    <BaseBlock :title="t.available_reports">
      <div class="row g-4">
        <div
          v-for="report in dailyReportGroup?.items ?? []"
          :key="report.key"
          class="col-md-6"
        >
          <BaseBlock
            tag="button"
            class="w-100 h-100 text-start"
            transparent
            @click="router.get(report.href)"
          >
            <div class="d-flex align-items-start gap-3">
              <div class="fs-2 text-primary">
                <i :class="report.icon"></i>
              </div>
              <div>
                <div class="fw-semibold fs-4 mb-1">{{ reportTitle(report) }}</div>
                <div class="text-muted">{{ reportDescription(report) }}</div>
              </div>
            </div>
          </BaseBlock>
        </div>
      </div>
    </BaseBlock>
  </div>
</template>
