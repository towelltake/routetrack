<script setup>
import { computed } from 'vue';
import DashboardChart from './DashboardChart.vue';
import { chartOptions, dataset } from './analytics';

const props = defineProps({ metrics: Object, loading: Boolean });
const daily = computed(() => props.metrics?.charts?.daily ?? []);
const routes = computed(() => props.metrics?.charts?.routes ?? []);
const series = (rows, fields) => ({ labels: rows.map(row => row.label), datasets: fields.map(([label, key, color]) => dataset(label, rows.map(row => row[key]), color)) });
const coverage = computed(() => series(daily.value, [['Covered', 'covered', '#14b8a6'], ['Pending', 'pending', '#fbbf24'], ['Missed', 'missed', '#fb7185']]));
const productivity = computed(() => series(daily.value, [['Productive', 'productive', '#3b82f6'], ['No sale / order', 'nonproductive', '#cbd5e1']]));
const exceptions = computed(() => ({ labels: daily.value.length ? ['Unplanned visits', 'Out of sequence', 'Repeat visits'] : [], datasets: [dataset('Visit events', ['unplanned', 'out_of_sequence', 'repeat'].map(key => daily.value.reduce((sum, row) => sum + Number(row[key] ?? 0), 0)), ['#c2410c', '#f59e0b', '#8b5cf6'])] }));
const cftRoutes = computed(() => [...routes.value].filter(row => row.configured_visits).sort((a, b) => b.configured_actual_cft - a.configured_actual_cft).slice(0, 10));
const cft = computed(() => series(cftRoutes.value, [['Planned (min)', 'expected_cft', '#c4b5fd'], ['Actual (min)', 'configured_actual_cft', '#7c3aed']]));
const timeRoutes = computed(() => [...routes.value].filter(row => row.duration_count).sort((a, b) => b.duration - a.duration).slice(0, 10));
const time = computed(() => series(timeRoutes.value, [['Customer visit time (min)', 'visit_time', '#14b8a6'], ['Time outside visits (min)', 'remaining_time', '#cbd5e1']]));
const stacked = chartOptions({ stacked: true });
const horizontal = chartOptions({ horizontal: true });
const timeOptions = chartOptions({ horizontal: true, stacked: true });
</script>

<template>
    <section class="dashboard-graphs" aria-label="Performance graphs">
        <DashboardChart title="Customer coverage" description="Planned customers by journey start date" :data="coverage" :options="stacked" :loading="loading" note="Journeys without a plan are excluded from coverage." />
        <DashboardChart title="Visit productivity" description="Completed visits with and without a sale or order" :data="productivity" :options="stacked" :loading="loading" />
        <DashboardChart title="Journey-plan exceptions" description="Where actual visits differ from the plan" :data="exceptions" :options="horizontal" :loading="loading" note="Categories can overlap." />
        <DashboardChart title="Planned vs actual face time" description="Top 10 routes with recorded planned CFT" :data="cft" :options="horizontal" :loading="loading" note="Compares the same visits on both sides." />
        <DashboardChart class="time-graph" title="Where journey time goes" description="Top 10 routes by measured journey duration" :data="time" :options="timeOptions" :loading="loading" note="Open journeys use the last reported location. Overlapping visits count once. Time outside visits includes travel, idle time and breaks." />
    </section>
</template>

<style scoped>
.dashboard-graphs { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 18px; margin: 20px 0; }
.time-graph { grid-column: 1 / -1; }
@media (max-width: 850px) { .dashboard-graphs { grid-template-columns: 1fr; } }
</style>
