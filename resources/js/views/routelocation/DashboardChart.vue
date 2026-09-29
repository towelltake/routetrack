<script setup>
import { computed, ref } from "vue";
import { chartValueLabel, number } from "./analytics";
import { Bar } from "vue-chartjs";
import { Chart as ChartJS, CategoryScale, LinearScale, BarElement, Tooltip, Legend } from "chart.js";
ChartJS.register(CategoryScale, LinearScale, BarElement, Tooltip, Legend);
const props = defineProps({ title: String, description: String, data: Object, options: Object, loading: Boolean, note: String });
const percentageMode = ref(true);
const percentageMetrics = computed(() => props.data.percentageMetrics ?? props.data.datasets);
const chartData = computed(() => ({ ...props.data, datasets: props.data.datasets.map(set => ({
    ...set, label: percentageMode.value ? set.label.replace(' (min)', '') : set.label,
    data: percentageMode.value ? set.percentages : set.data,
})) }));
const optionsWithPercentages = computed(() => {
    const options = props.options;
    const axis = options.indexAxis === 'y' ? 'x' : 'y';
    return {
        ...options,
        plugins: { ...options.plugins, tooltip: { ...options.plugins.tooltip, callbacks: {
            ...options.plugins.tooltip.callbacks,
            label: context => `${props.data.datasets[context.datasetIndex].label}: ${chartValueLabel(props.data.datasets[context.datasetIndex], context.dataIndex)}`,
        } } },
        scales: { ...options.scales, [axis]: {
            ...options.scales[axis],
            title: { display: true, text: percentageMode.value ? 'Percentage (%)' : 'Value' },
            ticks: { ...options.scales[axis].ticks, callback: value => `${number(value, 1)}${percentageMode.value ? '%' : ''}` },
        } },
    };
});
</script>

<template>
    <section class="analytics-panel">
        <header><h3>{{ title }}</h3><p>{{ description }}</p></header>
        <div v-if="loading" class="chart-empty" role="status">Loading chart...</div>
        <template v-else-if="data.labels.length">
            <div class="chart-percentages" aria-label="Percentage metrics">
                <div v-for="metric in percentageMetrics" :key="metric.label"><strong>{{ metric.percentage == null ? 'Unavailable' : `${number(metric.percentage, 1)}%` }}</strong><span>{{ metric.label.replace(' (min)', '') }}</span></div>
            </div>
            <div class="chart-mode" role="group" :aria-label="`${title} display mode`">
                <button type="button" :aria-pressed="!percentageMode" @click="percentageMode = false">Values</button>
                <button type="button" :aria-pressed="percentageMode" @click="percentageMode = true">Percentages</button>
            </div>
            <div class="chart-canvas"><Bar :data="chartData" :options="optionsWithPercentages" role="img" :aria-label="`${title}. ${description}. ${percentageMode ? 'Percentages' : 'Values'} shown. Values and percentages are available in the table below.`" /></div>
            <details class="chart-values"><summary>View chart values</summary><div class="chart-data-scroll"><table>
                <thead><tr><th scope="col">Category</th><th v-for="set in data.datasets" :key="set.label" scope="col">{{ set.label }}</th></tr></thead>
                <tbody><tr v-for="(label, index) in data.labels" :key="index"><th scope="row">{{ label }}</th><td v-for="set in data.datasets" :key="set.label">{{ chartValueLabel(set, index) }}</td></tr></tbody>
            </table></div></details>
            <div v-if="data.breakdownMetrics" class="chart-percentages chart-breakdown" aria-label="Productivity breakdown">
                <div v-for="metric in data.breakdownMetrics" :key="metric.label"><strong>{{ metric.percentage == null ? 'Unavailable' : `${number(metric.percentage, 1)}%` }}</strong><span>{{ metric.label }}</span></div>
            </div>
        </template>
        <div v-else class="chart-empty">No matching data for this chart.</div>
        <p v-if="note" class="chart-note">{{ note }}</p>
    </section>
</template>

<style scoped>
.analytics-panel { background: #fff; border: 1px solid #e2e8f0; border-radius: 14px; padding: 22px; min-width: 0; }
header { margin-bottom: 22px; }
h3 { font-size: 15px; font-weight: 700; color: #172b45; margin: 0 0 6px; }
header p, .chart-note { color: #64748b; font-size: 12px; line-height: 1.5; margin: 0; }
.chart-canvas { position: relative; height: 285px; }
.chart-percentages { display: flex; flex-wrap: wrap; gap: 12px 24px; margin-bottom: 16px; }
.chart-percentages div { display: grid; gap: 3px; }
.chart-percentages strong { color: #172b45; font-size: 20px; }
.chart-percentages span { color: #64748b; font-size: 11px; }
.chart-breakdown { margin-top: 16px; border-top: 1px solid #edf2f7; padding-top: 12px; }
.chart-mode { display: flex; gap: 4px; margin-bottom: 12px; }
.chart-mode button { border: 1px solid #cbd5e1; background: #fff; color: #52657b; padding: 5px 10px; border-radius: 6px; font-size: 12px; }
.chart-mode button[aria-pressed="true"] { background: #172b45; border-color: #172b45; color: #fff; }
.chart-empty { height: 285px; display: grid; place-content: center; color: #64748b; background: #f8fafc; border-radius: 8px; font-size: 13px; }
.chart-note { margin-top: 15px; }
.chart-values { margin-top: 12px; font-size: 11px; color: #52657b; }
.chart-values summary { cursor: pointer; }
.chart-data-scroll { overflow: auto; max-height: 220px; margin-top: 10px; }
table { width: 100%; border-collapse: collapse; white-space: nowrap; }
th, td { padding: 7px; border-bottom: 1px solid #edf2f7; text-align: start; }
</style>
