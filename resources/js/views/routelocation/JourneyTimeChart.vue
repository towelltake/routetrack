<script setup>
import { computed, ref, watch, onBeforeUnmount } from 'vue';
import axios from 'axios';
import JourneyTimelinePlot from './JourneyTimelinePlot.vue';
import { selectTimelineRoutes } from './journeyTimeline';
const props = defineProps({ journeys: Array, loading: Boolean, routeCount: Number, filters: Object });
const expanded = ref(false);
const dialog = ref(null);
const allJourneys = ref(null);
const expanding = ref(false);
const expandError = ref('');
let expansionRequest;
function resetExpansion() {
    expansionRequest?.abort();
    expansionRequest = null;
    closeExpansion();
    allJourneys.value = null;
    expanding.value = false;
    expandError.value = '';
}
watch(() => [props.journeys, props.loading, props.filters], resetExpansion);
onBeforeUnmount(resetExpansion);
function closeExpansion() {
    expansionRequest?.abort();
    expansionRequest = null;
    expanding.value = false;
    expanded.value = false;
    dialog.value?.close();
}
async function openExpansion() {
    if (props.loading) return;
    expanded.value = true;
    if (!dialog.value.open) dialog.value.showModal();
    if (allJourneys.value || expanding.value) return;
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
        if (expansionRequest === current && !axios.isCancel(error)) expandError.value = 'Unable to load all routes. Please try again.';
    } finally {
        if (expansionRequest === current) { expanding.value = false; expansionRequest = null; }
    }
}
const routeCount = computed(() => props.routeCount ?? new Set((props.journeys ?? []).map(row => String(row.routecode))).size);

const previewJourneys = computed(() => selectTimelineRoutes(props.journeys ?? []));
</script>

<template>
    <section class="journey-time-panel" aria-label="Where journey time goes">
        <div class="chart-heading">
            <h3>Where journey time goes</h3>
            <button v-if="routeCount > 0" type="button" class="btn btn-sm btn-alt-primary" aria-haspopup="dialog" :aria-expanded="expanded" :disabled="loading" @click="openExpansion">Expand all routes ({{ routeCount }})</button>
        </div>
        <p>Top 10 routes by measured journey duration &middot; Each row shows a route and calendar date</p>
        <JourneyTimelinePlot :journeys="previewJourneys" :loading="loading" />
        <dialog ref="dialog" class="journey-timeline-dialog" aria-labelledby="journey-timeline-title" @cancel.prevent="closeExpansion" @close="closeExpansion" @click="($event.target === dialog) && closeExpansion()">
            <header class="dialog-heading">
                <div><h3 id="journey-timeline-title">Where journey time goes &mdash; All routes</h3><p>{{ routeCount }} routes in the selection &middot; 00:00&ndash;24:00</p></div>
                <button type="button" class="btn btn-sm btn-alt-secondary" autofocus @click="closeExpansion">Close</button>
            </header>
            <div v-if="expanded" class="dialog-content">
                <div v-if="expandError" role="alert" class="text-danger mb-3">{{ expandError }} <button type="button" class="btn btn-sm btn-alt-primary" @click="openExpansion">Retry</button></div>
                <JourneyTimelinePlot v-else :journeys="allJourneys ?? []" :loading="expanding" />
            </div>
        </dialog>
    </section>
</template>

<style scoped>
.journey-time-panel { background: #fff; border: 1px solid #e2e8f0; border-radius: 14px; padding: 22px; min-width: 0; }
h3 { font-size: 15px; font-weight: 700; color: #172b45; margin: 0 0 6px; }
p { color: #64748b; font-size: 12px; }
.chart-heading, .dialog-heading { display: flex; align-items: center; justify-content: space-between; gap: 16px; }
.chart-heading { flex-wrap: wrap; margin-bottom: 6px; }
.journey-timeline-dialog { width: min(1600px, 96vw); max-width: 96vw; max-height: 92vh; padding: 0; border: 1px solid #e2e8f0; border-radius: 14px; color: #172b45; background: #fff; }
.journey-timeline-dialog::backdrop { background: #0f172a88; }
.dialog-heading { position: sticky; top: 0; z-index: 1; padding: 20px 24px; background: #fff; border-bottom: 1px solid #e2e8f0; }
.dialog-heading p { margin: 0; }
.dialog-content { padding: 20px 24px; }
</style>
