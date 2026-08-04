<script setup>
import { computed, ref, watch } from "vue";
import { Head, Link, router, usePage } from "@inertiajs/vue3";
import { Bar, Doughnut, Line } from "vue-chartjs";
import { Chart, registerables } from "chart.js";
import VueSelect from "vue-select";
import { useAmountFormatter } from "@/composables/useAmountFormatter";

Chart.register(...registerables);

const props = defineProps({
  board: { type: Object, required: true },
  boards: { type: Array, required: true },
  filters: { type: Object, required: true },
  filterOptions: { type: Object, required: true },
  scope: { type: Object, required: true },
  cards: { type: Array, required: true },
  charts: { type: Array, required: true },
  tables: { type: Array, required: true },
  insights: { type: Array, required: true },
});
const t = usePage().props.translations.ui;

const { amountDecimalPlaces } = useAmountFormatter();

const routeMap = {
  overview: "/analytics/overview",
  sales: "/analytics/sales",
  collections: "/analytics/collections",
  inventory: "/analytics/inventory",
};

const fromDate = ref(props.filters.from_date ?? "");
const toDate = ref(props.filters.to_date ?? "");
const cmpycode = ref(props.filters.cmpycode ? String(props.filters.cmpycode) : "");
const regionmstcode = ref(props.filters.regionmstcode ? String(props.filters.regionmstcode) : "");
const depotcode = ref(props.filters.depotcode ? String(props.filters.depotcode) : "");
const areacode = ref(props.filters.areacode ? String(props.filters.areacode) : "");
const subareacode = ref(props.filters.subareacode ? String(props.filters.subareacode) : "");
const routecode = ref(props.filters.routecode ? String(props.filters.routecode) : "");

watch(
  () => props.filters,
  (value) => {
    fromDate.value = value.from_date ?? "";
    toDate.value = value.to_date ?? "";
    cmpycode.value = value.cmpycode ? String(value.cmpycode) : "";
    regionmstcode.value = value.regionmstcode ? String(value.regionmstcode) : "";
    depotcode.value = value.depotcode ? String(value.depotcode) : "";
    areacode.value = value.areacode ? String(value.areacode) : "";
    subareacode.value = value.subareacode ? String(value.subareacode) : "";
    routecode.value = value.routecode ? String(value.routecode) : "";
  },
  { deep: true }
);

const scopeBadges = computed(() => props.scope.badges ?? []);
const panelLabel = computed(() => {
  switch (props.board.key) {
    case "sales":
      return t.sales_performance_snapshot;
    case "collections":
      return t.collection_performance_snapshot;
    case "inventory":
      return t.inventory_movement_snapshot;
    default:
      return t.business_performance_snapshot;
  }
});

const companyValue = optionModel(() => props.filterOptions.companies, cmpycode);
const regionValue = optionModel(() => props.filterOptions.regions, regionmstcode);
const depotValue = optionModel(() => props.filterOptions.depots, depotcode);
const areaValue = optionModel(() => props.filterOptions.areas, areacode);
const subAreaValue = optionModel(() => props.filterOptions.subAreas, subareacode);
const routeValue = optionModel(() => props.filterOptions.routes, routecode);

function optionModel(optionsFactory, state) {
  return computed({
    get: () => (optionsFactory() ?? []).find((option) => String(option.id) === String(state.value)) ?? null,
    set: (option) => {
      state.value = option ? String(option.id) : "";
    },
  });
}

function reload() {
  router.get(
    routeMap[props.board.key] ?? routeMap.overview,
    {
      from_date: fromDate.value || undefined,
      to_date: toDate.value || undefined,
      cmpycode: cmpycode.value || undefined,
      regionmstcode: regionmstcode.value || undefined,
      depotcode: depotcode.value || undefined,
      areacode: areacode.value || undefined,
      subareacode: subareacode.value || undefined,
      routecode: routecode.value || undefined,
    },
    {
      preserveScroll: true,
      preserveState: true,
      replace: true,
    }
  );
}

function resetFilters() {
  const today = new Date();
  const start = new Date(today);
  start.setDate(start.getDate() - 29);

  fromDate.value = start.toISOString().slice(0, 10);
  toDate.value = today.toISOString().slice(0, 10);
  cmpycode.value = "";
  regionmstcode.value = "";
  depotcode.value = "";
  areacode.value = "";
  subareacode.value = "";
  routecode.value = "";
  reload();
}

function formatMetric(value, kind) {
  if (kind === "amount") {
    return Number(value || 0).toLocaleString(undefined, {
      minimumFractionDigits: amountDecimalPlaces.value,
      maximumFractionDigits: amountDecimalPlaces.value,
    });
  }

  if (kind === "percent") {
    return `${Number(value || 0).toLocaleString(undefined, {
      minimumFractionDigits: 1,
      maximumFractionDigits: 1,
    })}%`;
  }

  return Number(value || 0).toLocaleString();
}

function chartComponent(type) {
  return type === "bar" ? Bar : type === "doughnut" ? Doughnut : Line;
}

function chartData(chart) {
  return {
    labels: chart.labels ?? [],
    datasets: (chart.datasets ?? []).map((dataset) => ({
      tension: chart.type === "line" ? 0.35 : 0,
      fill: chart.type === "line",
      borderWidth: 2,
      pointRadius: 0,
      pointHoverRadius: 4,
      borderRadius: 8,
      maxBarThickness: 28,
      ...dataset,
    })),
  };
}

function chartOptions(chart) {
  const amountTooltip = ["line", "bar"].includes(chart.type);

  return {
    responsive: true,
    maintainAspectRatio: false,
    interaction: {
      intersect: false,
      mode: "index",
    },
    plugins: {
      legend: {
        display: true,
        position: "bottom",
        labels: {
          usePointStyle: true,
          boxWidth: 10,
          color: "#334155",
        },
      },
      tooltip: {
        backgroundColor: "rgba(15, 23, 42, 0.92)",
        padding: 12,
        callbacks: amountTooltip
          ? {
              label: (context) => {
                const value = context.parsed?.y ?? context.parsed ?? 0;
                return `${context.dataset.label}: ${formatMetric(value, "amount")}`;
              },
            }
          : undefined,
      },
    },
    scales:
      chart.type === "doughnut"
        ? {}
        : {
            x: {
              grid: { display: false },
              ticks: { color: "#64748b" },
            },
            y: {
              beginAtZero: true,
              grid: { color: "rgba(148, 163, 184, 0.18)" },
              ticks: {
                color: "#64748b",
                callback: (value) => Number(value).toLocaleString(),
              },
            },
          },
  };
}

function tableValue(row, column) {
  return row?.[column.key];
}

function formatTableValue(value, kind) {
  if (kind === "amount") {
    return formatMetric(value, "amount");
  }

  if (kind === "number") {
    return formatMetric(value, "number");
  }

  if (kind === "percent") {
    return formatMetric(value, "percent");
  }

  return value ?? "";
}
</script>

<template>
  <Head :title="board.title" />

  <!-- <BasePageHeading :title="board.title" :subtitle="board.subtitle">
    <template #extra>
      <div class="analytics-heading-extra">
        <span class="analytics-chip">
          {{ filters.from_date }} to {{ filters.to_date }}
        </span>
        <span class="analytics-chip analytics-chip-accent">
          {{ scope.access_type }} Scope
        </span>
      </div>
    </template>
  </BasePageHeading> -->

  <div class="content analytics-shell">
    <section class="analytics-hero">
      <div class="analytics-hero__copy">
        <p class="analytics-kicker">{{ t.operational_intelligence }}</p>
        <h1>{{ board.title }}</h1>
        <p class="analytics-hero__text">
          {{ t.analytics_hero_text }}
        </p>
        <div class="analytics-scope-grid">
          <div v-for="badge in scopeBadges" :key="badge.label" class="analytics-scope-card">
            <span>{{ badge.label }}</span>
            <strong>{{ formatMetric(badge.value, "number") }}</strong>
          </div>
        </div>
      </div>
      <div class="analytics-hero__panel">
        <div class="analytics-hero__panel-top">
          <span class="analytics-panel-label">{{ panelLabel }}</span>
          <strong>{{ scope.route_count }} {{ t.routes }}</strong>
        </div>
        <p>{{ scope.message }}</p>
        <div class="analytics-board-switch">
          <Link
            v-for="item in boards.filter((entry) => entry.canView)"
            :key="item.key"
            :href="item.href"
            class="analytics-board-pill"
            :class="{ 'is-active': item.key === board.key }"
          >
            {{ item.label }}
          </Link>
        </div>
      </div>
    </section>

    <BaseBlock :title="t.analytics_filters" class="analytics-filter-block">
      <div class="row g-3 align-items-end">
        <div class="col-md-3">
          <label class="form-label">{{ t.from_date }}</label>
          <input v-model="fromDate" type="date" class="form-control" />
        </div>
        <div class="col-md-3">
          <label class="form-label">{{ t.to_date }}</label>
          <input v-model="toDate" type="date" class="form-control" />
        </div>
        <div class="col-md-3">
          <label class="form-label">{{ t.company }}</label>
          <VueSelect v-model="companyValue" :options="filterOptions.companies" label="label" :clearable="true" :searchable="true" :placeholder="t.search_company" />
        </div>
        <div class="col-md-3">
          <label class="form-label">{{ t.region }}</label>
          <VueSelect v-model="regionValue" :options="filterOptions.regions" label="label" :clearable="true" :searchable="true" :placeholder="t.search_region" />
        </div>
        <div class="col-md-3">
          <label class="form-label">{{ t.depot }}</label>
          <VueSelect v-model="depotValue" :options="filterOptions.depots" label="label" :clearable="true" :searchable="true" :placeholder="t.search_depot" />
        </div>
        <div class="col-md-3">
          <label class="form-label">{{ t.area }}</label>
          <VueSelect v-model="areaValue" :options="filterOptions.areas" label="label" :clearable="true" :searchable="true" :placeholder="t.search_area" />
        </div>
        <div class="col-md-3">
          <label class="form-label">{{ t.sub_area }}</label>
          <VueSelect v-model="subAreaValue" :options="filterOptions.subAreas" label="label" :clearable="true" :searchable="true" :placeholder="t.search_sub_area" />
        </div>
        <div class="col-md-3">
          <label class="form-label">{{ t.route }}</label>
          <VueSelect v-model="routeValue" :options="filterOptions.routes" label="label" :clearable="true" :searchable="true" :placeholder="t.search_route" />
        </div>
        <div class="col-12 d-flex gap-2 justify-content-end mb-3">
          <button class="btn btn-primary" @click="reload">
            <i class="fa fa-chart-line me-1"></i> {{ t.refresh_dashboard }}
          </button>
          <button class="btn btn-alt-secondary" @click="resetFilters">
            <i class="fa fa-rotate-left me-1"></i> {{ t.reset }}
          </button>
        </div>
      </div>
    </BaseBlock>

    <div v-if="cards.length" class="analytics-kpi-grid">
      <article v-for="card in cards" :key="card.label" class="analytics-kpi-card">
        <span class="analytics-kpi-card__label">{{ card.label }}</span>
        <strong class="analytics-kpi-card__value">{{ formatMetric(card.value, card.kind) }}</strong>
        <small class="analytics-kpi-card__note">{{ card.note }}</small>
      </article>
    </div>

    <div v-if="charts.length" class="analytics-chart-grid">
      <BaseBlock
        v-for="chart in charts"
        :key="chart.title"
        :title="chart.title"
        class="analytics-visual-card"
      >
        <div class="analytics-chart">
          <component :is="chartComponent(chart.type)" :data="chartData(chart)" :options="chartOptions(chart)" />
        </div>
      </BaseBlock>
    </div>

    <div v-if="insights.length" class="analytics-insights">
      <BaseBlock :title="t.key_takeaways">
        <div class="analytics-insight-list mb-3">
          <div v-for="insight in insights" :key="insight" class="analytics-insight">
            <i class="fa fa-bolt"></i>
            <span>{{ insight }}</span>
          </div>
        </div>
      </BaseBlock>
    </div>

    <div v-if="tables.length" class="analytics-table-stack">
      <BaseBlock v-for="table in tables" :key="table.title" :title="table.title" class="analytics-table-card">
        <div class="table-responsive mb-3">
          <table class="table table-striped table-hover align-middle mb-0 analytics-table">
            <thead>
              <tr>
                <th v-for="column in table.columns" :key="column.key">{{ column.label }}</th>
              </tr>
            </thead>
            <tbody>
              <tr v-if="!table.rows.length">
                <td :colspan="table.columns.length" class="text-center text-muted py-4">
                  {{ t.no_data_for_selected_filters }}
                </td>
              </tr>
              <tr v-for="(row, rowIndex) in table.rows" :key="`${table.title}-${rowIndex}`">
                <td v-for="column in table.columns" :key="column.key">
                  {{ formatTableValue(tableValue(row, column), column.kind) }}
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </BaseBlock>
    </div>
  </div>
</template>

<style lang="scss">
@import "vue-select/dist/vue-select.css";
@import "@scss/vendor/vue-select";
</style>

<style scoped>
.analytics-shell {
  --analytics-accent: v-bind("board.accent");
}

.analytics-hero {
  display: grid;
  grid-template-columns: 1.6fr 1fr;
  gap: 1.5rem;
  margin-bottom: 1.5rem;
  padding: 1.75rem;
  border-radius: 1.5rem;
  background:
    radial-gradient(circle at top left, color-mix(in srgb, var(--analytics-accent) 22%, white), transparent 42%),
    linear-gradient(135deg, #f8fafc 0%, #eff6ff 52%, #ffffff 100%);
  border: 1px solid rgba(148, 163, 184, 0.22);
}

.analytics-kicker {
  margin: 0 0 0.5rem;
  font-size: 0.78rem;
  font-weight: 700;
  letter-spacing: 0.16em;
  text-transform: uppercase;
  color: color-mix(in srgb, var(--analytics-accent) 72%, #0f172a);
}

.analytics-hero h1 {
  margin: 0;
  font-size: clamp(1.9rem, 3vw, 2.8rem);
  font-weight: 800;
  color: #0f172a;
}

.analytics-hero__text,
.analytics-hero__panel p {
  color: #475569;
  line-height: 1.6;
}

.analytics-scope-grid {
  display: grid;
  grid-template-columns: repeat(4, minmax(0, 1fr));
  gap: 0.85rem;
  margin-top: 1.5rem;
}

.analytics-scope-card,
.analytics-hero__panel,
.analytics-kpi-card {
  border: 1px solid rgba(148, 163, 184, 0.18);
  background: rgba(255, 255, 255, 0.88);
  backdrop-filter: blur(10px);
  border-radius: 1.1rem;
}

.analytics-scope-card {
  padding: 0.9rem 1rem;
}

.analytics-scope-card span,
.analytics-kpi-card__label,
.analytics-panel-label {
  display: block;
  color: #64748b;
  font-size: 0.78rem;
  text-transform: uppercase;
  letter-spacing: 0.08em;
}

.analytics-scope-card strong,
.analytics-kpi-card__value {
  display: block;
  margin-top: 0.45rem;
  color: #0f172a;
  font-size: 1.45rem;
  font-weight: 800;
}

.analytics-hero__panel {
  padding: 1.25rem;
  display: flex;
  flex-direction: column;
  justify-content: space-between;
}

.analytics-hero__panel-top {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 1rem;
}

.analytics-board-switch {
  display: flex;
  flex-wrap: wrap;
  gap: 0.65rem;
  margin-top: 1.25rem;
}

.analytics-board-pill,
.analytics-chip {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  border-radius: 999px;
  padding: 0.55rem 0.95rem;
  font-size: 0.85rem;
  font-weight: 700;
  text-decoration: none;
}

.analytics-board-pill {
  background: #e2e8f0;
  color: #334155;
  opacity: 1;
  -webkit-text-fill-color: currentColor;
}

.analytics-board-pill.is-active {
  background: var(--analytics-accent);
  color: #ffffff !important;
  opacity: 1 !important;
  -webkit-text-fill-color: #ffffff;
  box-shadow: 0 10px 24px -14px color-mix(in srgb, var(--analytics-accent) 72%, #0f172a);
  text-shadow: 0 1px 1px rgba(15, 23, 42, 0.18);
}

.analytics-board-pill.is-active:link,
.analytics-board-pill.is-active:visited,
.analytics-board-pill.is-active:hover,
.analytics-board-pill.is-active:focus {
  color: #ffffff !important;
  opacity: 1 !important;
  -webkit-text-fill-color: #ffffff;
}

.analytics-heading-extra {
  display: flex;
  flex-wrap: wrap;
  gap: 0.55rem;
}

.analytics-chip {
  background: rgba(255, 255, 255, 0.86);
  color: #334155;
  border: 1px solid rgba(148, 163, 184, 0.22);
}

.analytics-chip-accent {
  background: color-mix(in srgb, var(--analytics-accent) 10%, white);
  color: color-mix(in srgb, var(--analytics-accent) 75%, #0f172a);
}

.analytics-filter-block :deep(.vs__dropdown-toggle) {
  min-height: calc(1.5em + 0.75rem + 2px);
  height: calc(1.5em + 0.75rem + 2px);
  border: 1px solid #d0d7de;
  border-radius: 0.375rem;
  background: #ffffff;
  box-shadow: none;
  transition: border-color 0.15s ease-in-out, box-shadow 0.15s ease-in-out;
}

.analytics-filter-block :deep(.vs__selected-options) {
  padding: 0 0.25rem 0 0.6rem;
  display: flex;
  flex-wrap: nowrap;
  overflow: hidden;
}

.analytics-filter-block :deep(.vs__search) {
  color: #0f172a;
  padding: 0;
  margin: 0;
  line-height: 1.5;
  font-size: 1rem;
}

.analytics-filter-block :deep(.vs__search::placeholder) {
  color: #6c757d;
}

.analytics-filter-block :deep(.vs__selected) {
  display: block;
  max-width: 100%;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
  color: #0f172a;
  font-weight: 400;
  margin: 0;
  line-height: 1.5;
}

.analytics-filter-block :deep(.vs__dropdown-menu) {
  border-radius: 0.375rem;
  border: 1px solid #d0d7de;
  box-shadow: 0 0.5rem 1rem rgba(0, 0, 0, 0.15);
}

.analytics-filter-block :deep(.vs__dropdown-option) {
  color: #334155;
}

.analytics-filter-block :deep(.vs__dropdown-option--highlight) {
  background: color-mix(in srgb, var(--analytics-accent) 16%, white);
  color: #0f172a;
}

.analytics-filter-block :deep(.vs__actions) {
  padding: 0 0.5rem 0 0.25rem;
  align-self: center;
  padding-right: 0.5rem;
}

.analytics-filter-block :deep(.vs__open-indicator) {
  fill: #6c757d;
}

.analytics-filter-block :deep(.vs__clear) {
  fill: #6c757d;
}

.analytics-filter-block :deep(.vs--open .vs__dropdown-toggle),
.analytics-filter-block :deep(.vs__dropdown-toggle:hover),
.analytics-filter-block :deep(.vs__dropdown-toggle:focus-within) {
  border-color: #86b7fe;
  box-shadow: 0 0 0 0.25rem rgba(13, 110, 253, 0.25);
}

.analytics-kpi-grid {
  display: grid;
  grid-template-columns: repeat(6, minmax(0, 1fr));
  gap: 1rem;
  margin-bottom: 1.5rem;
}

.analytics-kpi-card {
  padding: 1.1rem 1rem;
  box-shadow: 0 16px 30px -24px rgba(15, 23, 42, 0.4);
}

.analytics-kpi-card__note {
  display: block;
  margin-top: 0.55rem;
  color: #64748b;
  font-size: 0.85rem;
}

.analytics-chart-grid {
  display: grid;
  grid-template-columns: repeat(2, minmax(0, 1fr));
  gap: 1rem;
}

.analytics-visual-card {
  overflow: hidden;
}

.analytics-chart {
  height: 320px;
}

.analytics-insights,
.analytics-table-stack {
  margin-top: 1.5rem;
}

.analytics-insight-list {
  display: grid;
  gap: 0.9rem;
}

.analytics-insight {
  display: flex;
  gap: 0.85rem;
  align-items: flex-start;
  padding: 1rem 1.1rem;
  border-radius: 1rem;
  background: linear-gradient(135deg, rgba(248, 250, 252, 0.94), rgba(239, 246, 255, 0.9));
  border: 1px solid rgba(148, 163, 184, 0.16);
  color: #334155;
}

.analytics-insight i {
  margin-top: 0.15rem;
  color: var(--analytics-accent);
}

.analytics-table-stack {
  display: grid;
  gap: 1rem;
}

.analytics-table thead th {
  color: #475569;
  font-size: 0.77rem;
  text-transform: uppercase;
  letter-spacing: 0.08em;
}

@media (max-width: 1399.98px) {
  .analytics-kpi-grid {
    grid-template-columns: repeat(3, minmax(0, 1fr));
  }
}

@media (max-width: 991.98px) {
  .analytics-hero,
  .analytics-chart-grid {
    grid-template-columns: 1fr;
  }

  .analytics-scope-grid {
    grid-template-columns: repeat(2, minmax(0, 1fr));
  }
}

@media (max-width: 767.98px) {
  .analytics-kpi-grid,
  .analytics-scope-grid {
    grid-template-columns: 1fr;
  }

  .analytics-hero {
    padding: 1.2rem;
  }

  .analytics-chart {
    height: 280px;
  }
}
</style>
