<script setup>
import { Bar } from "vue-chartjs";
import { Chart as ChartJS, CategoryScale, LinearScale, BarElement, Tooltip, Legend } from "chart.js";
ChartJS.register(CategoryScale, LinearScale, BarElement, Tooltip, Legend);
defineProps({ title: String, description: String, data: Object, options: Object, loading: Boolean, note: String });
</script>

<template>
    <section class="analytics-panel">
        <header><h3>{{ title }}</h3><p>{{ description }}</p></header>
        <div v-if="loading" class="chart-empty" role="status">Loading chart...</div>
        <template v-else-if="data.labels.length">
            <div class="chart-canvas"><Bar :data="data" :options="options" role="img" :aria-label="`${title}. ${description}. Values are available in the table below.`" /></div>
            <details class="chart-values"><summary>View chart values</summary><div class="chart-data-scroll"><table>
                <thead><tr><th scope="col">Category</th><th v-for="set in data.datasets" :key="set.label" scope="col">{{ set.label }}</th></tr></thead>
                <tbody><tr v-for="(label, index) in data.labels" :key="index"><th scope="row">{{ label }}</th><td v-for="set in data.datasets" :key="set.label">{{ set.data[index] == null ? 'Unavailable' : Number(set.data[index]).toLocaleString(undefined, { maximumFractionDigits: 1 }) }}</td></tr></tbody>
            </table></div></details>
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
.chart-empty { height: 285px; display: grid; place-content: center; color: #64748b; background: #f8fafc; border-radius: 8px; font-size: 13px; }
.chart-note { margin-top: 15px; }
.chart-values { margin-top: 12px; font-size: 11px; color: #52657b; }
.chart-values summary { cursor: pointer; }
.chart-data-scroll { overflow: auto; max-height: 220px; margin-top: 10px; }
table { width: 100%; border-collapse: collapse; white-space: nowrap; }
th, td { padding: 7px; border-bottom: 1px solid #edf2f7; text-align: start; }
</style>
