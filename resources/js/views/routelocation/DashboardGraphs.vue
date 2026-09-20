<script setup>
import { computed } from 'vue';
import DashboardChart from './DashboardChart.vue';
import JourneyTimeChart from './JourneyTimeChart.vue';
import { chartOptions, percentageDataset, percentageSeries } from './analytics';

const props = defineProps({ metrics: Object, loading: Boolean, timelineFilters: Object });
const daily = computed(() => props.metrics?.charts?.daily ?? []);
const routes = computed(() => props.metrics?.charts?.routes ?? []);
const coverage = computed(() => percentageSeries(daily.value, [['Covered', 'covered', '#14b8a6'], ['Pending', 'pending', '#fbbf24'], ['Missed', 'missed', '#fb7185']], row => row.planned));
const productiveSeries = (fields, denominator) => {
    const data = percentageSeries(daily.value, fields, denominator);
    return { ...data, percentageMetrics: data.datasets.slice(0, 1), breakdownMetrics: data.datasets.slice(1) };
};
const productivity = computed(() => productiveSeries([
    ['Total productive', 'productive', '#14b8a6'], ['Collection', 'collection_productive', '#8b5cf6'], ['Orders/Invoices', 'sales_order_productive', '#3b82f6'],
], row => row.productive + row.nonproductive));
const efficiency = computed(() => productiveSeries([
    ['Total efficiency', 'productive_customers', '#14b8a6'], ['Collection', 'collection_customers', '#8b5cf6'], ['Orders/Invoices', 'sales_order_customers', '#3b82f6'],
], row => row.eligible_customers));
const exceptions = computed(() => {
    const visits = daily.value.reduce((sum, row) => sum + Number(row.visits ?? 0), 0);
    const labels = daily.value.length ? ['Unplanned visits', 'Out of sequence', 'Repeat visits'] : [];
    const set = percentageDataset('Visit events', ['unplanned', 'out_of_sequence', 'repeat'].map(key => daily.value.reduce((sum, row) => sum + Number(row[key] ?? 0), 0)), ['#c2410c', '#f59e0b', '#8b5cf6'], [visits, visits, visits]);
    return { labels, datasets: [set], percentageMetrics: labels.map((label, index) => ({ label, percentage: set.percentages[index] })) };
});
const cftRoutes = computed(() => [...routes.value].filter(row => row.configured_visits).sort((a, b) => b.configured_actual_cft - a.configured_actual_cft).slice(0, 10));
const cft = computed(() => percentageSeries(cftRoutes.value, [['Planned (min)', 'expected_cft', '#c4b5fd'], ['Customer visit time (min)', 'configured_actual_cft', '#7c3aed']], row => row.expected_cft));
const stacked = chartOptions({ stacked: true });
const horizontal = chartOptions({ horizontal: true });
const grouped = chartOptions();
</script>

<template>
    <section class="dashboard-graphs" aria-label="Performance graphs">
        <DashboardChart title="Customer coverage" description="Planned customers by journey start date" :data="coverage" :options="stacked" :loading="loading" note="Percentages use planned customers. Journeys without a plan are excluded from coverage." />
        <DashboardChart title="Visit productivity" description="Total includes collections, sales orders and invoices" :data="productivity" :options="grouped" :loading="loading" note="Percentages use eligible completed visits. A visit with both collection and sales/order counts once in the total and in both breakdowns. Ignored customers are excluded." />
        <DashboardChart title="Customer efficiency" description="Unique productive customers, including collections" :data="efficiency" :options="grouped" :loading="loading" note="Percentages use unique eligible customers per journey. Collection and sales/order can overlap; each customer counts once in the total per journey. Ignored customers are excluded." />
        <DashboardChart title="Journey-plan exceptions" description="Where actual visits differ from the plan" :data="exceptions" :options="horizontal" :loading="loading" note="Each percentage uses all recorded visits. Categories can overlap and do not add up to 100%. Unplanned and out-of-sequence counts require a journey plan." />
        <DashboardChart title="Planned vs customer visit time" description="Top 10 routes with recorded planned CFT, excluding OTP visits" :data="cft" :options="horizontal" :loading="loading" note="Percentages use planned CFT for the same completed non-OTP visits. Planned time is 100%; customer visit time can exceed 100%. Summary covers the displayed routes." />
        <JourneyTimeChart class="time-graph" :journeys="metrics?.charts?.timeline ?? []" :route-count="metrics?.charts?.timeline_route_count" :filters="timelineFilters" :loading="loading" />
    </section>
</template>

<style scoped>
.dashboard-graphs { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 18px; margin: 20px 0; }
.time-graph { grid-column: 1 / -1; }
@media (max-width: 850px) { .dashboard-graphs { grid-template-columns: 1fr; } }
</style>
