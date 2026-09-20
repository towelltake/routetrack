<script setup>
import { computed, ref, watch, onBeforeUnmount } from 'vue';
import axios from 'axios';
import { Bar } from 'vue-chartjs';
import { Chart as ChartJS, CategoryScale, LinearScale, BarElement, Tooltip, Legend } from 'chart.js';
import { journeyTimeline, clockTime, selectTimelineRoutes } from './journeyTimeline';
import { number, percent } from './analytics';
ChartJS.register(CategoryScale, LinearScale, BarElement, Tooltip, Legend);
const props = defineProps({ journeys: Array, loading: Boolean, routeCount: Number, filters: Object });
const expanded = ref(false);
const allJourneys = ref(null);
const expanding = ref(false);
const expandError = ref('');
let expansionRequest;
function resetExpansion() {
    expansionRequest?.abort();
    expansionRequest = null;
    expanded.value = false;
    allJourneys.value = null;
    expanding.value = false;
    expandError.value = '';
}
watch(() => [props.journeys, props.loading, props.filters], resetExpansion);
onBeforeUnmount(resetExpansion);
async function toggleExpansion() {
    if (expanded.value) { expanded.value = false; return; }
    if (allJourneys.value) { expanded.value = true; return; }
    if (expanding.value || props.loading) return;
    const current = new AbortController();
    expansionRequest = current;
    expanding.value = true;
    expandError.value = '';
    try {
        const { data } = await axios.get('/dashboard/metrics.json', {
            params: { ...props.filters, timeline_only: 1 }, signal: current.signal, timeout: 60000,
        });
        if (expansionRequest !== current) return;
        allJourneys.value = data.timeline;
        expanded.value = true;
    } catch (error) {
        if (expansionRequest === current && !axios.isCancel(error)) expandError.value = 'Unable to load all routes. Click Expand to retry.';
    } finally {
        if (expansionRequest === current) { expanding.value = false; expansionRequest = null; }
    }
}
const routeCount = computed(() => props.routeCount ?? new Set((props.journeys ?? []).map(row => String(row.routecode))).size);
const rows = computed(() => journeyTimeline(selectTimelineRoutes(expanded.value ? (allJourneys.value ?? props.journeys ?? []) : (props.journeys ?? []), expanded.value)));
const totals = computed(() => rows.value.reduce((sum, row) => ({ duration: sum.duration + row.duration, visits: sum.visits + row.visitMinutes, otp: sum.otp + row.otpMinutes, outside: sum.outside + row.outsideMinutes }), { duration: 0, visits: 0, otp: 0, outside: 0 }));
const data = computed(() => ({
    labels: rows.value.map(row => row.key),
    datasets: [['visit', 'Customer visit time', '#14b8a6'], ['otp', 'OTP customer visits', '#8b5cf6'], ['outside', 'Outside visits (idle / travel)', '#ef4444']].map(([kind, label, color]) => ({
        label, backgroundColor: color, grouped: false, barThickness: 16, borderRadius: 0,
        data: rows.value.flatMap(row => row.segments.filter(segment => segment.kind === kind).map(segment => ({
            y: row.key, rowLabel: row.label, x: [segment.from, segment.to], minutes: segment.minutes, duration: row.duration,
        }))),
    })),
}));
const options = {
    responsive: true, maintainAspectRatio: false, indexAxis: 'y', animation: false,
    interaction: { mode: 'nearest', intersect: true },
    plugins: {
        legend: { position: 'bottom' },
        tooltip: { callbacks: {
            title: items => items[0]?.raw.rowLabel ?? '',
            label: context => `${context.dataset.label}: ${clockTime(context.raw.x[0])}–${clockTime(context.raw.x[1])} · ${number(context.raw.minutes, 1)} min (${percent(context.raw.minutes, context.raw.duration)} of this row)`,
        } },
    },
    scales: {
        x: { type: 'linear', min: 0, max: 1440, title: { display: true, text: 'Recorded time of day' }, ticks: { stepSize: 120, callback: clockTime }, grid: { color: '#eef2f6' } },
        y: { type: 'category', border: { display: false }, grid: { display: false }, ticks: { autoSkip: false, font: { size: 10 }, callback: value => rows.value[value]?.label ?? '' } },
    },
};
</script>

<template>
    <section class="journey-time-panel" aria-label="Where journey time goes">
        <div class="chart-heading"><h3>Where journey time goes</h3><button v-if="routeCount > 10" type="button" class="btn btn-sm btn-alt-primary" :aria-expanded="expanded" :disabled="expanding || loading" @click="toggleExpansion">{{ expanding ? 'Loading routes...' : expanded ? 'Collapse to top 10' : 'Expand all routes (' + routeCount + ')' }}</button></div>
        <p>{{ expanded ? 'All ' + routeCount + ' routes in the selection' : 'Top 10 routes by measured journey duration' }} &middot; Each row shows a route and calendar date</p>
        <p v-if="expandError" role="alert" class="text-danger">{{ expandError }}</p>
        <div v-if="loading" class="empty" role="status">Loading timeline...</div>
        <template v-else-if="rows.length">
            <div class="metrics"><span><strong>{{ percent(totals.visits, totals.duration) }}</strong> Customer visit time</span><span class="otp-metric"><strong>{{ percent(totals.otp, totals.duration) }}</strong> OTP customer visits</span><span class="outside-metric"><strong>{{ percent(totals.outside, totals.duration) }}</strong> Outside visits (idle / travel)</span></div>
            <div class="timeline-scroll"><div class="journey-timeline-canvas" :style="{ height: `${Math.max(300, rows.length * 34 + 90)}px` }">
                <Bar :data="data" :options="options" role="img" aria-label="Journey timeline from 00:00 to 24:00. Recorded intervals and percentages are available in the table below." />
            </div></div>
            <details><summary>View timeline values</summary><div class="table-scroll"><table>
                <thead><tr><th>Route / Date</th><th>Status</th><th>Time range</th><th>Customer visit time</th><th>OTP customer visits</th><th>Outside visits (idle / travel)</th></tr></thead>
                <tbody><tr v-for="row in rows" :key="row.key"><th scope="row">{{ row.label }}</th><td>{{ row.closed ? 'Closed' : 'Open · ends at last reported location' }}</td><td>{{ clockTime(row.from) }}–{{ clockTime(row.to) }}</td><td>{{ number(row.visitMinutes, 1) }} min ({{ percent(row.visitMinutes, row.duration) }})</td><td>{{ number(row.otpMinutes, 1) }} min ({{ percent(row.otpMinutes, row.duration) }})</td><td>{{ number(row.outsideMinutes, 1) }} min ({{ percent(row.outsideMinutes, row.duration) }})</td></tr></tbody>
            </table></div></details>
        </template>
        <div v-else class="empty">No journeys with a usable start and end time.</div>
        <p class="note">Bars start at the recorded journey start time. Open journeys end at the last reported location. Overnight journeys continue on the next dated row. Completed visits are clipped to the journey and overlapping intervals count once; OTP takes precedence where visits overlap. Teal shows non-OTP visits, purple shows OTP visits, and red shows time outside recorded visits. Gaps have no completed visit recorded; they can include travel, breaks or incomplete visits. Percentages cover the displayed timeline and can differ from summed visit-time cards.</p>
    </section>
</template>

<style scoped>
.journey-time-panel { background: #fff; border: 1px solid #e2e8f0; border-radius: 14px; padding: 22px; min-width: 0; }
h3 { font-size: 15px; font-weight: 700; color: #172b45; margin: 0 0 6px; }
p, details { color: #64748b; font-size: 12px; }
.metrics { display: flex; flex-wrap: wrap; gap: 24px; margin: 18px 0; }
.metrics strong { color: #172b45; font-size: 20px; margin-right: 6px; }
.chart-heading { display: flex; align-items: center; justify-content: space-between; gap: 12px; flex-wrap: wrap; margin-bottom: 6px; }
.otp-metric strong { color: #7c3aed; }
.outside-metric strong { color: #dc2626; }
.timeline-scroll { overflow: auto; max-height: 650px; }
.journey-timeline-canvas { min-width: 800px; }
.table-scroll { overflow: auto; max-height: 300px; }
table { width: 100%; border-collapse: collapse; white-space: nowrap; }
th, td { padding: 8px; text-align: start; border-bottom: 1px solid #edf2f7; }
summary { cursor: pointer; margin: 12px 0; }
.note { margin: 15px 0 0; line-height: 1.5; }
.empty { min-height: 200px; display: grid; place-content: center; color: #64748b; }
</style>
