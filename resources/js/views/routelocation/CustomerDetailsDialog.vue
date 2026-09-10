<script setup>
import { computed, nextTick, onBeforeUnmount, ref } from 'vue';
import axios from 'axios';

const titles = { planned: 'Planned Customer Visits', unplanned: 'Unplanned Customers Visited', otp: 'OTP Requests', productive: 'Productive Visits', sales: 'Sales', orders: 'Orders', collections: 'Collections', returns: 'Returns', duration: 'Total Duration', cft: 'Customer Face Time', operational: 'Operational Time', otp_time: 'OTP Customer Time', actual_face: 'Actual Face Time', outside: 'Time Outside Visits' };
const isRouteTime = computed(() => ['duration', 'outside'].includes(type.value));
const signedDuration = (value) => value == null ? 'Unavailable' : `${value > 0 ? '+' : value < 0 ? '-' : ''}${duration(Math.abs(value))}`;
const isTransaction = computed(() => ['sales', 'orders', 'collections', 'returns'].includes(type.value));
const duration = (value) => value == null ? 'Unavailable' : `${Math.floor(Math.round(value) / 60)}:${String(Math.round(value) % 60).padStart(2, '0')}`;
const money = (value) => Number(value).toLocaleString(undefined, { minimumFractionDigits: 3, maximumFractionDigits: 3 });
const dialog = ref(null);
const type = ref('planned');
const groups = ref([]);
const day = ref('');
const route = ref('');
const status = ref('All');
const search = ref('');
const loading = ref(false);
const error = ref('');
const page = ref(1);
let request;
const dates = computed(() => [...new Set(groups.value.map((group) => group.date))]);
const routes = computed(() => groups.value.filter((group) => day.value === '' || group.date === day.value));
const group = computed(() => routes.value.find((item) => String(item.routekey) === route.value));
const scopedRows = computed(() => routes.value
    .filter(item => isRouteTime.value || route.value === '' || String(item.routekey) === route.value)
    .flatMap(item => item.rows.map(row => ({ ...row, route_date: item.date, routekey: item.routekey, routecode: item.routecode, salesman: item.salesman }))));
const statuses = computed(() => type.value === 'planned' ? ['All', 'Visited', 'Not visited'] : type.value === 'productive' ? ['All', 'Productive', 'Nonproductive'] : ['All']);
const rows = computed(() => scopedRows.value.filter((row) => (status.value === 'All' || row.status === status.value)
    && `${row.customer_code} ${row.customercode} ${row.customer_name} ${row.otp_type ?? ''} ${row.recorded_by ?? ''} ${row.comments ?? ''} ${row.document ?? ''} ${row.routecode ?? ''} ${row.salesman ?? ''}`.toLowerCase().includes(search.value.trim().toLowerCase())));
const pages = computed(() => Math.max(1, Math.ceil(rows.value.length / 50)));
const visibleRows = computed(() => rows.value.slice((page.value - 1) * 50, page.value * 50));
function chooseDate() { route.value = ''; page.value = 1; }
function close() { request?.abort(); dialog.value?.close(); }
async function open(kind, filters) {
    request?.abort();
    const current = new AbortController(); request = current;
    type.value = kind; groups.value = []; status.value = kind === 'productive' ? 'Productive' : 'All';
    search.value = ''; error.value = ''; loading.value = true; page.value = 1;
    await nextTick();
    if (!dialog.value.open) dialog.value.showModal();
    try {
        const { data } = await axios.get('/dashboard/customer-details.json', { params: { ...filters, type: kind }, signal: current.signal, timeout: 60000 });
        if (request !== current || current.signal.aborted) return;
        groups.value = data.groups; day.value = ''; chooseDate();
    } catch (e) {
        if (request === current && !axios.isCancel(e)) error.value = 'Unable to load details. Close and reopen to retry.';
    } finally { if (request === current) loading.value = false; }
}
onBeforeUnmount(close);
defineExpose({ open, close });
</script>

<template>
    <dialog ref="dialog" class="customer-details-dialog" aria-labelledby="customer-details-title" @cancel="close" @click="($event.target === dialog) && close()">
        <header><div><h2 id="customer-details-title">{{ titles[type] }}</h2><p>Grouped by route-start date and route</p></div><button type="button" aria-label="Close" @click="close">×</button></header>
        <div class="details-body">
            <p v-if="loading" role="status">Loading details…</p>
            <p v-else-if="error" role="alert">{{ error }}</p>
            <p v-else-if="!groups.length">No matching records in the selected period.</p>
            <template v-else>
                <div class="details-filters">
                    <label>Date<select v-model="day" @change="chooseDate"><option value="">All</option><option v-for="date in dates" :key="date">{{ date }}</option></select></label>
                    <label v-if="!isRouteTime">Route / Journey<select v-model="route" @change="page = 1"><option value="">All</option><option v-for="item in routes" :key="item.routekey" :value="String(item.routekey)">{{ item.date }} · {{ item.routecode }} · {{ item.routename }} · Journey {{ item.routekey }}</option></select></label>
                    <label>Search<input v-model="search" placeholder="Customer or details…" @input="page = 1" /></label>
                </div>
                <h3>{{ day || 'All dates in selected period' }}<template v-if="!isRouteTime && group"> · {{ group.routecode }} — {{ group.routename }}</template><template v-else-if="!isRouteTime"> · All routes / journeys</template></h3>
                <p v-if="!isRouteTime && group" class="salesman">Salesman: {{ group.salesman || 'Not available' }}</p>
                <p v-if="isRouteTime" class="salesman">Open routes use the last known location time for duration.</p>
                <div v-if="statuses.length > 1" class="status-tabs" aria-label="Visit status">
                    <button v-for="tab in statuses" :key="tab" type="button" :class="{ active: status === tab }" :aria-pressed="status === tab" @click="status = tab; page = 1">{{ tab }} ({{ scopedRows.filter((row) => tab === 'All' || row.status === tab).length }})</button>
                </div>
                <div v-if="type === 'outside'" class="details-table">
                    <p class="salesman">Times in h:mm. Time outside visits = total duration minus operational time.</p>
                    <table><thead><tr><th>Route code</th><th>Date</th><th>Salesman</th><th>Start time</th><th>End time</th><th>Total duration</th><th>Operational time</th><th>Time outside visits</th></tr></thead>
                    <tbody><tr v-for="row in visibleRows" :key="`${row.routekey}:${row.id}`"><td>{{ row.routecode }}</td><td>{{ row.route_date }}</td><td>{{ row.salesman || 'Not available' }}</td><td>{{ row.start || 'Unavailable' }}</td><td>{{ row.status === 'Open' ? 'Not ended' : row.end || 'Unavailable' }}<small v-if="row.status === 'Open'">Last location: {{ row.end || 'Unavailable' }}</small></td><td>{{ duration(row.duration) }}</td><td>{{ duration(row.operational) }}</td><td>{{ duration(row.outside) }}</td></tr><tr v-if="!visibleRows.length"><td colspan="8">No matching records.</td></tr></tbody></table>
                </div>
                <div v-else-if="['operational', 'otp_time', 'actual_face'].includes(type)" class="details-table">
                    <p class="salesman">Duration in h:mm. Each completed visit counts once.<template v-if="type === 'otp_time'"> OTP requests are matched by customer and visit timestamps; all matched OTP times are listed.</template></p>
                    <table><thead><tr><th>Route code</th><th>Date</th><th>Customer code</th><th>Customer name</th><th>Check-in time</th><th>Check-out time</th><th>Duration (h:mm)</th><th v-if="type === 'otp_time'">OTP time</th></tr></thead>
                    <tbody><tr v-for="row in visibleRows" :key="`${row.routekey}:${row.id}`"><td>{{ row.routecode }}</td><td>{{ row.date || row.route_date }}</td><td>{{ row.customer_code }}</td><td>{{ row.customer_name }}</td><td>{{ row.check_in || 'Unavailable' }}</td><td>{{ row.check_out || 'Unavailable' }}</td><td>{{ duration(row.actual_cft) }}</td><td v-if="type === 'otp_time'"><div v-for="(time, index) in row.otp_times" :key="index">{{ time }}</div></td></tr><tr v-if="!visibleRows.length"><td :colspan="type === 'otp_time' ? 8 : 7">No matching records.</td></tr></tbody></table>
                </div>
                <div v-else-if="type === 'duration'" class="details-table"><table>
                    <thead><tr><th>Date</th><th>Route code</th><th>Salesman name</th><th>Start time</th><th>End time</th><th>Duration (h:mm)</th><th>Route status</th></tr></thead>
                    <tbody><tr v-for="row in visibleRows" :key="`${row.routekey}:${row.id}`"><td>{{ row.route_date }}</td><td>{{ row.routecode }}</td><td>{{ row.salesman || 'Not available' }}</td><td>{{ row.start || 'Unavailable' }}</td><td>{{ row.status === 'Open' ? 'Not ended' : row.end || 'Unavailable' }}<small v-if="row.status === 'Open'">Last location: {{ row.end || 'Unavailable' }}</small></td><td>{{ duration(row.duration) }}</td><td>{{ row.status }}</td></tr><tr v-if="!visibleRows.length"><td colspan="7">No matching records.</td></tr></tbody>
                </table></div>
                <div v-else-if="type === 'cft'" class="details-table">
                    <p class="salesman">Times in h:mm. Variance = actual minus planned. Variance is unavailable when planned CFT is missing or zero, or visit timing is incomplete.</p>
                    <table><thead><tr><th>Date</th><th>Route code</th><th>Salesman</th><th>Customer code</th><th>Planned CFT</th><th>Actual CFT</th><th>Variance</th></tr></thead>
                    <tbody><tr v-for="row in visibleRows" :key="`${row.routekey}:${row.id}`"><td>{{ row.date || row.route_date }}<small>{{ row.time }}</small></td><td>{{ row.routecode }}</td><td>{{ row.salesman || 'Not available' }}</td><td :title="row.customer_name">{{ row.customer_code }}</td><td>{{ duration(row.planned_cft) }}</td><td>{{ duration(row.actual_cft) }}</td><td>{{ signedDuration(row.variance) }}</td></tr><tr v-if="!visibleRows.length"><td colspan="7">No matching records.</td></tr></tbody></table>
                </div>
                <div v-else class="details-table"><table>
                    <thead><tr><th>Date</th><th>Route code</th><th v-if="type === 'otp'">Salesman</th><th>Customer code</th><th>Customer name</th>
                        <template v-if="type === 'otp'"><th>OTP type</th><th>Recorded by</th><th>Comments / Reason</th></template>
                        <template v-else-if="isTransaction"><th>Document type</th><th>Document number</th><th>Amount</th><th>Currency</th></template>
                        <template v-else-if="type === 'productive'"><th>Visit time</th><th>Status</th><th>Orders</th><th>Collections</th><th>Invoices</th></template>
                        <template v-else><th>Status</th><th>Visits</th></template>
                    </tr></thead>
                    <tbody><tr v-for="row in visibleRows" :key="`${row.routekey}:${row.id}`"><td>{{ row.date || row.route_date }}<small v-if="type === 'otp'">{{ row.time }}</small></td><td>{{ row.routecode }}</td><td v-if="type === 'otp'">{{ row.salesman || 'Not available' }}</td><td>{{ row.customer_code }}</td><td>{{ row.customer_name }}</td>
                        <template v-if="type === 'otp'"><td>{{ row.otp_type }}</td><td>{{ row.recorded_by || '—' }}</td><td>{{ [row.comments, row.reason].filter(Boolean).join(' · ') || '—' }}</td></template>
                        <template v-else-if="isTransaction"><td>{{ row.source }}</td><td>{{ row.document }}</td><td :class="{ missed: type === 'returns' }">{{ money(row.amount) }}</td><td>{{ row.currency }}</td></template>
                        <template v-else-if="type === 'productive'"><td>{{ row.time }}</td><td>{{ row.status }}</td><td>{{ row.orders }}</td><td>{{ row.collections }}</td><td>{{ row.invoices }}</td></template>
                        <template v-else><td :class="{ missed: row.status === 'Not visited' }">{{ row.status }}</td><td>{{ row.visit_count }}</td></template>
                    </tr><tr v-if="!visibleRows.length"><td :colspan="type === 'otp' || isTransaction ? 8 : type === 'productive' ? 9 : 6">No matching records.</td></tr></tbody>
                </table></div>
                <footer><span>{{ rows.length }} records</span><div><button :disabled="page <= 1" @click="page--">Previous</button><span>{{ page }} / {{ pages }}</span><button :disabled="page >= pages" @click="page++">Next</button></div></footer>
            </template>
        </div>
    </dialog>
</template>

<style scoped>
.customer-details-dialog { width: min(1250px, 96vw); max-height: 88vh; padding: 0; border: 1px solid #e2e8f0; border-radius: 12px; color: #172b45; }
.customer-details-dialog::backdrop { background: #0f172a88; }
header, footer, footer > div { display: flex; align-items: center; justify-content: space-between; gap: 12px; }
header { padding: 16px 20px; border-bottom: 1px solid #e2e8f0; } h2 { font-size: 18px; margin: 0; }
header p, .salesman { margin: 5px 0; color: #64748b; font-size: 12px; } header button { font-size: 24px; }
button { padding: 5px 10px; border: 1px solid #e2e8f0; background: #f8fafc; border-radius: 6px; color: #334155; } button:disabled { opacity: .5; }
.details-body { padding: 16px 20px; } .details-filters { display: flex; flex-wrap: wrap; gap: 12px; } label { display: grid; gap: 4px; font-size: 11px; flex: 1; min-width: 160px; }
select, input { min-width: 0; padding: 7px; border: 1px solid #cbd5e1; border-radius: 6px; background: white; color: #172b45; font-size: 12px; }
h3 { margin: 18px 0 4px; font-size: 15px; } .status-tabs { display: flex; gap: 6px; margin: 12px 0; flex-wrap: wrap; } .active { background: #eff6ff; color: #2563eb; border-color: #93c5fd; }
.details-table { overflow-x: auto; margin-top: 12px; } table { width: 100%; border-collapse: collapse; font-size: 12px; } th, td { padding: 9px; text-align: left; border-bottom: 1px solid #e2e8f0; } th { white-space: nowrap; background: #f8fafc; } td { overflow-wrap: anywhere; } small { display: block; color: #64748b; } .missed { color: #b91c1c; } footer { margin-top: 14px; font-size: 12px; }
</style>
