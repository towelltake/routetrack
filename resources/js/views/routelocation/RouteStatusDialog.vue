<script setup>
import { computed, nextTick, ref, onBeforeUnmount } from "vue";
import axios from "axios";

const dialog = ref(null);
const loading = ref(false);
const error = ref("");
const data = ref({ routes: [], journeys: [] });
const dates = ref([]);
const day = ref("");
let controller;
const rows = computed(() => {
    const byRoute = new Map();
    for (const journey of data.value.journeys) {
        if (journey.date !== day.value) continue;
        const key = String(journey.routecode);
        if (!byRoute.has(key)) byRoute.set(key, []);
        byRoute.get(key).push(journey);
    }
    return data.value.routes.map((route) => ({ ...route, journeys: byRoute.get(String(route.routecode)) ?? [] }));
});
const started = computed(() => rows.value.filter((route) => route.journeys.length));
const notStarted = computed(() => rows.value.filter((route) => !route.journeys.length));
async function open(filters) {
    controller?.abort();
    const request = new AbortController();
    controller = request;
    loading.value = true;
    error.value = "";
    data.value = { routes: [], journeys: [] };
    dates.value = [];
    const date = new Date(`${filters.from_date}T12:00:00`);
    while (true) {
        const value = `${date.getFullYear()}-${String(date.getMonth() + 1).padStart(2, "0")}-${String(date.getDate()).padStart(2, "0")}`;
        if (value > filters.to_date) break;
        dates.value.push(value);
        date.setDate(date.getDate() + 1);
    }
    day.value = dates.value[0];
    await nextTick();
    if (!dialog.value.open) dialog.value.showModal();
    try {
        const response = await axios.get('/dashboard/route-status.json', { params: filters, signal: request.signal, timeout: 60000 });
        if (!request.signal.aborted) data.value = response.data;
    } catch (e) {
        if (!axios.isCancel(e)) error.value = 'Unable to load route status. Close and reopen to retry.';
    } finally {
        if (controller === request) loading.value = false;
    }
}
function close() { controller?.abort(); dialog.value?.close(); }
onBeforeUnmount(close);
defineExpose({ open, close });
</script>

<template>
    <dialog ref="dialog" class="route-status-dialog" aria-labelledby="route-status-title" @cancel="close" @click="($event.target === dialog) && close()">
        <header><div><h2 id="route-status-title">Routes Started / Total</h2><p>Active routes grouped by route-start date</p></div><button type="button" aria-label="Close" @click="close">×</button></header>
        <div class="route-status-body">
            <label for="route-status-date">Date</label>
            <select id="route-status-date" v-model="day"><option v-for="date in dates" :key="date" :value="date">{{ date }}</option></select>
            <p v-if="loading" role="status">Loading route status…</p>
            <p v-else-if="error" role="alert">{{ error }}</p>
            <template v-else>
                <h3>{{ day }} · {{ started.length }} started / {{ rows.length }} routes</h3>
                <section v-for="group in [{ title: 'Started', rows: started }, { title: 'Not started', rows: notStarted }]" :key="group.title">
                    <h4>{{ group.title }} ({{ group.rows.length }})</h4>
                    <div class="route-status-table"><table><thead><tr><th>Date</th><th>Route code</th><th>Route name</th><th>Salesman name</th><th>Start time</th><th>Route end time</th></tr></thead>
                        <tbody><template v-for="route in group.rows" :key="route.routecode">
                            <tr v-for="journey in route.journeys.length ? route.journeys : [null]" :key="journey?.routekey ?? 'not-started'">
                                <td>{{ day }}</td><td>{{ route.routecode }}</td><td>{{ route.routename }}</td><td>{{ (journey ? journey.salesman : route.salesman) || 'Not available' }}</td>
                                <td>{{ journey ? journey.start || 'Time unavailable' : 'Not started' }}</td><td>{{ journey ? journey.end || (journey.closed ? 'Time unavailable' : 'Open') : 'Not started' }}</td>
                            </tr>
                        </template><tr v-if="!group.rows.length"><td colspan="6">No routes in this group.</td></tr></tbody>
                    </table></div>
                </section>
            </template>
        </div>
    </dialog>
</template>

<style scoped>
.route-status-dialog { width: min(1100px, 95vw); max-height: 88vh; padding: 0; border: 1px solid #e2e8f0; border-radius: 12px; color: #172b45; }
.route-status-dialog::backdrop { background: #0f172a88; }
header { display: flex; justify-content: space-between; align-items: center; padding: 16px 20px; border-bottom: 1px solid #e2e8f0; }
h2 { font-size: 18px; margin: 0; } header p { margin: 4px 0 0; font-size: 12px; color: #64748b; }
header button { border: 0; background: #f1f5f9; border-radius: 6px; font-size: 25px; }
.route-status-body { padding: 16px 20px; } select { margin-left: 12px; padding: 6px; border: 1px solid #cbd5e1; border-radius: 6px; }
h3 { margin: 18px 0; font-size: 15px; } h4 { margin: 14px 0 8px; font-size: 13px; }
.route-status-table { overflow-x: auto; } table { width: 100%; font-size: 12px; border-collapse: collapse; } th, td { padding: 9px; text-align: left; border-bottom: 1px solid #e2e8f0; } th { background: #f8fafc; white-space: nowrap; }
</style>
