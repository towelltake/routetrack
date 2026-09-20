<script setup>
import { computed } from 'vue';
import { Bar } from 'vue-chartjs';
import { Chart as ChartJS, CategoryScale, LinearScale, BarElement, Tooltip, Legend } from 'chart.js';
import { journeyTimeline, clockTime } from './journeyTimeline';
import { number, percent } from './analytics';
ChartJS.register(CategoryScale, LinearScale, BarElement, Tooltip, Legend);
const props = defineProps({ journeys: Array, loading: Boolean });
const rows = computed(() => journeyTimeline(props.journeys ?? []));
const totals = computed(() => rows.value.reduce((sum, row) => ({ duration: sum.duration + row.duration, visits: sum.visits + row.visitMinutes }), { duration: 0, visits: 0 }));
const data = computed(() => ({
    labels: rows.value.map(row => row.label),
    datasets: [['visit', 'Customer visit time', '#14b8a6'], ['outside', 'Time outside recorded visits', '#cbd5e1']].map(([kind, label, color]) => ({
        label, backgroundColor: color, grouped: false, barThickness: 16, borderRadius: 0,
        data: rows.value.flatMap(row => row.segments.filter(segment => segment.kind === kind).map(segment => ({
            y: row.label, x: [segment.from, segment.to], minutes: segment.minutes, duration: row.duration,
        }))),
    })),
}));
const options = {
    responsive: true, maintainAspectRatio: false, indexAxis: 'y', animation: false,
    interaction: { mode: 'nearest', intersect: true },
    plugins: {
        legend: { position: 'bottom' },
        tooltip: { callbacks: {
            title: items => items[0]?.raw.y ?? '',
            label: context => `${context.dataset.label}: ${clockTime(context.raw.x[0])}–${clockTime(context.raw.x[1])} · ${number(context.raw.minutes, 1)} min (${percent(context.raw.minutes, context.raw.duration)} of this row)`,
        } },
    },
    scales: {
        x: { type: 'linear', min: 0, max: 1440, title: { display: true, text: 'Recorded time of day' }, ticks: { stepSize: 120, callback: clockTime }, grid: { color: '#eef2f6' } },
        y: { type: 'category', grid: { display: false }, ticks: { autoSkip: false, font: { size: 10 } } },
    },
};
</script>

<template>
    <section class="journey-time-panel" aria-label="Where journey time goes">
        <h3>Where journey time goes</h3>
        <p>Top 10 routes by measured journey duration · Each row shows a journey and calendar date</p>
        <div v-if="loading" class="empty" role="status">Loading timeline...</div>
        <template v-else-if="rows.length">
            <div class="metrics"><span><strong>{{ percent(totals.visits, totals.duration) }}</strong> Customer visit time</span><span><strong>{{ percent(totals.duration - totals.visits, totals.duration) }}</strong> Outside recorded visits</span></div>
            <div class="timeline-scroll"><div class="timeline" :style="{ height: `${Math.max(300, rows.length * 34 + 90)}px` }">
                <Bar :data="data" :options="options" role="img" aria-label="Journey timeline from 00:00 to 24:00. Recorded intervals and percentages are available in the table below." />
            </div></div>
            <details><summary>View timeline values</summary><div class="table-scroll"><table>
                <thead><tr><th>Journey / Date</th><th>Status</th><th>Time range</th><th>Customer visit time</th><th>Outside recorded visits</th></tr></thead>
                <tbody><tr v-for="row in rows" :key="row.key"><th scope="row">{{ row.label }}</th><td>{{ row.closed ? 'Closed' : 'Open · ends at last reported location' }}</td><td>{{ clockTime(row.from) }}–{{ clockTime(row.to) }}</td><td>{{ number(row.visitMinutes, 1) }} min ({{ percent(row.visitMinutes, row.duration) }})</td><td>{{ number(row.outsideMinutes, 1) }} min ({{ percent(row.outsideMinutes, row.duration) }})</td></tr></tbody>
            </table></div></details>
        </template>
        <div v-else class="empty">No journeys with a usable start and end time.</div>
        <p class="note">Bars start at the recorded journey start time. Open journeys end at the last reported location. Overnight journeys continue on the next dated row. Completed visits are clipped to the journey and overlapping intervals count once. Gaps have no completed visit recorded; they can include travel, breaks or incomplete visits. Percentages cover the displayed timeline and can differ from summed visit-time cards.</p>
    </section>
</template>

<style scoped>
.journey-time-panel { background: #fff; border: 1px solid #e2e8f0; border-radius: 14px; padding: 22px; min-width: 0; }
h3 { font-size: 15px; font-weight: 700; color: #172b45; margin: 0 0 6px; }
p, details { color: #64748b; font-size: 12px; }
.metrics { display: flex; flex-wrap: wrap; gap: 24px; margin: 18px 0; }
.metrics strong { color: #172b45; font-size: 20px; margin-right: 6px; }
.timeline-scroll { overflow: auto; max-height: 650px; }
.timeline { min-width: 800px; }
.table-scroll { overflow: auto; max-height: 300px; }
table { width: 100%; border-collapse: collapse; white-space: nowrap; }
th, td { padding: 8px; text-align: start; border-bottom: 1px solid #edf2f7; }
summary { cursor: pointer; margin: 12px 0; }
.note { margin: 15px 0 0; line-height: 1.5; }
.empty { min-height: 200px; display: grid; place-content: center; color: #64748b; }
</style>
