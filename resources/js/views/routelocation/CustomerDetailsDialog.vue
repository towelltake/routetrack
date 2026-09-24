<script setup>
import { computed, nextTick, onBeforeUnmount, ref } from 'vue';
import axios from 'axios';

const titles = { planned: 'Planned Customer Visits', unplanned: 'Unplanned Customers Visited', otp: 'OTP Requests', productive: 'Productivity', sales: 'Sales', orders: 'Orders', collections: 'Collections', returns: 'Returns', duration: 'Total Duration', cft: 'Customer Face Time', operational: 'Operational Time', otp_time: 'OTP Customer Time', actual_face: 'Face Time Compliance', outside: 'Time Outside Visits' };
titles.idle = 'Idle Time Outside Customer Visits';
const isRouteTime = computed(() => ['duration', 'outside', 'idle'].includes(type.value));
titles.efficiency = 'Efficiency — Unique Customers';
const faceVariancePercent = (row) => {
    if (row.otp_excluded || row.actual_cft == null || !(Number(row.planned_cft) > 0)) return 'N/A';
    const value = Math.round((Number(row.actual_cft) - Number(row.planned_cft)) / Number(row.planned_cft) * 1000) / 10;
    return `${value > 0 ? '+' : ''}${value.toLocaleString(undefined, { maximumFractionDigits: 1 })}%`;
};
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
const statusLabel = value => value === 'Ignored' ? 'LPO Customers' : value;
const statuses = computed(() => type.value === 'planned' ? ['All', 'Visited without OTP', 'Visited with OTP', 'Not visited'] : type.value === 'unplanned' ? ['All', 'Visited without OTP', 'Visited with OTP'] : ['productive', 'efficiency'].includes(type.value) ? ['All', 'Productive', 'Nonproductive', 'Ignored'] : ['All']);
const rows = computed(() => scopedRows.value.filter((row) => (status.value === 'All' || row.status === status.value)
    && `${row.customer_code} ${row.customercode} ${row.customer_name} ${row.otp_type ?? ''} ${row.recorded_by ?? ''} ${row.comments ?? ''} ${row.document ?? ''} ${row.routecode ?? ''} ${row.salesman ?? ''}`.toLowerCase().includes(search.value.trim().toLowerCase())));
const pages = computed(() => Math.max(1, Math.ceil(rows.value.length / 50)));
const visibleRows = computed(() => rows.value.slice((page.value - 1) * 50, page.value * 50));
function chooseDate() { route.value = ''; page.value = 1; }
function close() { request?.abort(); dialog.value?.close(); }
async function open(kind, filters, cachedData = null) {
    request?.abort();
    const current = new AbortController(); request = current;
    type.value = kind; groups.value = []; status.value = 'All';
    search.value = ''; error.value = ''; loading.value = true; page.value = 1;
    await nextTick();
    if (!dialog.value.open) dialog.value.showModal();
    try {
        const { data } = cachedData ? { data: cachedData } : await axios.get('/dashboard/customer-details.json', { params: { ...filters, type: kind }, signal: current.signal, timeout: 60000 });
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
                    <button v-for="tab in statuses" :key="tab" type="button" :class="{ active: status === tab }" :aria-pressed="status === tab" @click="status = tab; page = 1">{{ statusLabel(tab) }} ({{ scopedRows.filter((row) => tab === 'All' || row.status === tab).length }})</button>
                </div>
                <p v-if="['productive', 'efficiency'].includes(type)" class="salesman">LPO Customers are shown in yellow for reference and excluded from both metric counts.<template v-if="type === 'efficiency'"> Each customer appears once per journey.</template></p>
                <div v-if="type === 'idle'" class="details-table">
                    <p class="salesman">GPS-detected stationary time outside all customer visits, including OTP visits. Travel is excluded. Times in h:mm; unavailable journeys are excluded from the total.</p>
                    <table><thead><tr><th>Route</th><th>Date</th><th>Salesman</th><th>Start</th><th>End / Last location</th><th>Idle outside visits</th></tr></thead>
                    <tbody><tr v-for="row in visibleRows" :key="row.routekey"><td>{{ row.routecode }}</td><td>{{ row.route_date }}</td><td>{{ row.salesman || 'Not available' }}</td><td>{{ row.start || 'Unavailable' }}</td><td>{{ row.end || 'Unavailable' }}</td><td>{{ duration(row.stationary) }}</td></tr><tr v-if="!visibleRows.length"><td colspan="6">No matching records.</td></tr></tbody></table>
                </div>
                <div v-else-if="type === 'outside'" class="details-table">
                    <p class="salesman">Times in h:mm. Time outside visits = total duration minus summed customer visit time.</p>
                    <table><thead><tr><th>Route code</th><th>Date</th><th>Salesman</th><th>Start time</th><th>End time</th><th>Total duration</th><th>Customer visit time</th><th>Time outside visits</th></tr></thead>
                    <tbody><tr v-for="row in visibleRows" :key="`${row.routekey}:${row.id}`" :class="{ 'planned-otp-row': ['planned', 'unplanned'].includes(type) && row.otp_visit_count > 0, 'cft-otp-excluded': row.otp_excluded, 'lpo-customer-row': ['productive', 'efficiency'].includes(type) && row.ignored }"><td>{{ row.routecode }}</td><td>{{ row.route_date }}</td><td>{{ row.salesman || 'Not available' }}</td><td>{{ row.start || 'Unavailable' }}</td><td>{{ row.status === 'Open' ? 'Not ended' : row.end || 'Unavailable' }}<small v-if="row.status === 'Open'">Last location: {{ row.end || 'Unavailable' }}</small></td><td>{{ duration(row.duration) }}</td><td>{{ duration(row.operational) }}</td><td>{{ duration(row.outside) }}</td></tr><tr v-if="!visibleRows.length"><td colspan="8">No matching records.</td></tr></tbody></table>
                </div>
                <div v-else-if="type === 'operational'" class="details-table">
                    <p class="salesman">First non-OTP check-in to the last non-OTP customer's checkout, including time between visits. Each journey counts once. A missing final checkout is unavailable.</p>
                    <table><thead><tr><th>Route</th><th>Date</th><th>First customer</th><th>First check-in</th><th>Last customer</th><th>Last checkout</th><th>Operational time (h:mm)</th></tr></thead>
                    <tbody><tr v-for="row in visibleRows" :key="row.routekey"><td>{{ row.routecode }}</td><td>{{ row.route_date }}</td><td>{{ row.first_customer ?? 'Unavailable' }}</td><td>{{ row.check_in || 'Unavailable' }}</td><td>{{ row.last_customer ?? 'Unavailable' }}</td><td>{{ row.check_out || 'Unavailable' }}</td><td>{{ duration(row.actual_cft) }}</td></tr></tbody></table>
                </div>
                <div v-else-if="['otp_time', 'actual_face'].includes(type)" class="details-table">
                    <p class="salesman">Duration in h:mm.<template v-if="type === 'actual_face'"> Variance (%) = (actual CFT - planned CFT) / planned CFT &times; 100. N/A means no planned time, incomplete visit timing, or an excluded OTP visit.</template> Each completed visit counts once. OTP visits shown in red are excluded from Face Time Compliance.<template v-if="type === 'otp_time'"> OTP requests are matched to the nearest visit start for that customer within the same journey; all matched OTP times are listed.</template></p>
                    <table><thead><tr><th>Route code</th><th>Date</th><th>Customer code</th><th>Customer name</th><th>Check-in time</th><th>Check-out time</th><th>{{ type === 'actual_face' ? 'Actual CFT (h:mm)' : 'Duration (h:mm)' }}</th><template v-if="type === 'actual_face'"><th>Planned CFT (h:mm)</th><th>Variance (%)</th></template><th v-if="type === 'otp_time'">OTP time</th></tr></thead>
                    <tbody><tr v-for="row in visibleRows" :key="`${row.routekey}:${row.id}`" :class="{ 'planned-otp-row': ['planned', 'unplanned'].includes(type) && row.otp_visit_count > 0, 'cft-otp-excluded': row.otp_excluded, 'lpo-customer-row': ['productive', 'efficiency'].includes(type) && row.ignored }"><td>{{ row.routecode }}</td><td>{{ row.date || row.route_date }}</td><td>{{ row.customer_code }}</td><td>{{ row.customer_name }}<small v-if="row.otp_excluded">OTP - excluded</small></td><td>{{ row.check_in || 'Unavailable' }}</td><td>{{ row.check_out || 'Unavailable' }}</td><td>{{ duration(row.actual_cft) }}</td><template v-if="type === 'actual_face'"><td>{{ duration(row.planned_cft) }}</td><td>{{ faceVariancePercent(row) }}</td></template><td v-if="type === 'otp_time'"><div v-for="(time, index) in row.otp_times" :key="index">{{ time }}</div></td></tr><tr v-if="!visibleRows.length"><td :colspan="type === 'otp_time' ? 8 : 9">No matching records.</td></tr></tbody></table>
                </div>
                <div v-else-if="type === 'duration'" class="details-table"><table>
                    <thead><tr><th>Date</th><th>Route code</th><th>Salesman name</th><th>Start time</th><th>End time</th><th>Duration (h:mm)</th><th>Route status</th></tr></thead>
                    <tbody><tr v-for="row in visibleRows" :key="`${row.routekey}:${row.id}`" :class="{ 'planned-otp-row': ['planned', 'unplanned'].includes(type) && row.otp_visit_count > 0, 'cft-otp-excluded': row.otp_excluded, 'lpo-customer-row': ['productive', 'efficiency'].includes(type) && row.ignored }"><td>{{ row.route_date }}</td><td>{{ row.routecode }}</td><td>{{ row.salesman || 'Not available' }}</td><td>{{ row.start || 'Unavailable' }}</td><td>{{ row.status === 'Open' ? 'Not ended' : row.end || 'Unavailable' }}<small v-if="row.status === 'Open'">Last location: {{ row.end || 'Unavailable' }}</small></td><td>{{ duration(row.duration) }}</td><td>{{ statusLabel(row.status) }}</td></tr><tr v-if="!visibleRows.length"><td colspan="7">No matching records.</td></tr></tbody>
                </table></div>
                <div v-else-if="type === 'cft'" class="details-table">
                    <p class="salesman">OTP visits are shown in red, excluded from actual and planned CFT, and count as zero. Times in h:mm. Variance = actual minus planned. Variance is unavailable when planned CFT is missing or zero, or visit timing is incomplete.</p>
                    <table><thead><tr><th>Date</th><th>Route code</th><th>Salesman</th><th>Customer code</th><th>Planned CFT</th><th>Actual CFT</th><th>Variance</th></tr></thead>
                    <tbody><tr v-for="row in visibleRows" :key="`${row.routekey}:${row.id}`" :class="{ 'planned-otp-row': ['planned', 'unplanned'].includes(type) && row.otp_visit_count > 0, 'cft-otp-excluded': row.otp_excluded, 'lpo-customer-row': ['productive', 'efficiency'].includes(type) && row.ignored }"><td>{{ row.date || row.route_date }}<small>{{ row.time }}</small></td><td>{{ row.routecode }}</td><td>{{ row.salesman || 'Not available' }}</td><td :title="row.customer_name">{{ row.customer_code }}<small v-if="row.otp_excluded">OTP - excluded</small></td><td>{{ duration(row.planned_cft) }}</td><td>{{ duration(row.actual_cft) }}</td><td>{{ signedDuration(row.variance) }}</td></tr><tr v-if="!visibleRows.length"><td colspan="7">No matching records.</td></tr></tbody></table>
                </div>
                <div v-else class="details-table"><table>
                    <thead><tr><th>{{ type === 'productive' ? 'Route Start Date' : 'Date' }}</th><th>Route code</th><th v-if="type === 'otp'">Salesman</th><th>Customer code</th><th>Customer name</th>
                        <template v-if="type === 'otp'"><th>OTP type</th><th>Recorded by</th><th>Comments / Reason</th></template>
                        <template v-else-if="isTransaction"><th>Document type</th><th>Document number</th><th>Amount</th><th>Currency</th></template>
                        <template v-else-if="type === 'productive'"><th>Visit start time</th><th>Visit end time</th><th>Status</th><th>Orders</th><th>Collections</th><th>Invoices</th></template>
                        <template v-else><th>Status</th><th v-if="type === 'unplanned'">OTP Status</th><th>Visits</th><th v-if="['planned', 'unplanned'].includes(type)">OTP visits</th></template>
                    </tr></thead>
                    <tbody><tr v-for="row in visibleRows" :key="`${row.routekey}:${row.id}`" :class="{ 'planned-otp-row': ['planned', 'unplanned'].includes(type) && row.otp_visit_count > 0, 'cft-otp-excluded': row.otp_excluded, 'lpo-customer-row': ['productive', 'efficiency'].includes(type) && row.ignored }"><td>{{ row.date || row.route_date }}<small v-if="type === 'otp'">{{ row.time }}</small></td><td>{{ row.routecode }}</td><td v-if="type === 'otp'">{{ row.salesman || 'Not available' }}</td><td>{{ row.customer_code }}</td><td>{{ row.customer_name }}<small v-if="row.otp_excluded">OTP - excluded</small></td>
                        <template v-if="type === 'otp'"><td>{{ row.otp_type }}</td><td>{{ row.recorded_by || '—' }}</td><td>{{ [row.comments, row.reason].filter(Boolean).join(' · ') || '—' }}</td></template>
                        <template v-else-if="isTransaction"><td>{{ row.source }}</td><td>{{ row.document }}</td><td :class="{ missed: type === 'returns' }">{{ money(row.amount) }}</td><td>{{ row.currency }}</td></template>
                        <template v-else-if="type === 'productive'"><td class="visit-timestamp"><template v-if="row.start_date && row.time">{{ row.start_date }}<br>{{ row.time }}</template><template v-else>Unavailable</template><span v-if="row.is_revisit" class="revisit-badge" title="Repeat visit to this customer within the same journey">Revisit #{{ row.visit_number }}</span></td><td class="visit-timestamp" :class="{ 'overnight-checkout': row.ends_later_date }"><template v-if="row.end_date && row.end_time">{{ row.end_date }}<br>{{ row.end_time }}</template><template v-else>Unavailable</template></td><td>{{ statusLabel(row.status) }}<small v-if="['productive', 'efficiency'].includes(type) && !row.ignored">Collection: {{ row.collection_productive ? "Yes" : "No" }} &middot; Orders/Invoices: {{ row.sales_order_productive ? "Yes" : "No" }}</small><small v-if="row.ignored">{{ row.exclusion_reason }}</small></td><td>{{ row.orders }}</td><td>{{ row.collections }}</td><td>{{ row.invoices }}</td></template>
                        <template v-else><td :class="{ missed: row.status === 'Not visited' }">{{ statusLabel(row.status) }}<small v-if="['productive', 'efficiency'].includes(type) && !row.ignored">Collection: {{ row.collection_productive ? "Yes" : "No" }} &middot; Orders/Invoices: {{ row.sales_order_productive ? "Yes" : "No" }}</small><small v-if="row.ignored">{{ row.exclusion_reason }}</small></td><td v-if="type === 'unplanned'"><span class="otp-status-badge" :class="row.otp_visit_count > 0 ? 'with-otp' : 'without-otp'">{{ row.otp_visit_count > 0 ? 'With OTP' : 'Without OTP' }}</span></td><td>{{ row.visit_count }}</td><td v-if="['planned', 'unplanned'].includes(type)">{{ row.otp_visit_count }}</td></template>
                    </tr><tr v-if="!visibleRows.length"><td :colspan="type === 'otp' || isTransaction ? 8 : type === 'productive' ? 10 : type === 'unplanned' ? 8 : type === 'planned' ? 7 : 6">No matching records.</td></tr></tbody>
                </table></div>
                <footer><span>{{ rows.length }} records</span><div><button :disabled="page <= 1" @click="page--">Previous</button><span>{{ page }} / {{ pages }}</span><button :disabled="page >= pages" @click="page++">Next</button></div></footer>
            </template>
        </div>
    </dialog>
</template>

<style scoped>
.visit-timestamp { white-space: nowrap; line-height: 1.6; font-variant-numeric: tabular-nums; }
.revisit-badge { display: block; width: fit-content; margin-top: 5px; padding: 3px 7px; border-radius: 6px; color: #1d4ed8; background: #dbeafe; font-size: 11px; font-weight: 700; white-space: nowrap; }
.otp-status-badge { display: inline-block; padding: 4px 8px; border-radius: 6px; font-size: 11px; font-weight: 650; white-space: nowrap; }
.otp-status-badge.with-otp { color: #6d28d9; background: #ede9fe; }
.otp-status-badge.without-otp { color: #475569; background: #f1f5f9; }
.planned-otp-row > td { background: #f5f3ff; color: #6d28d9; }
.cft-otp-excluded td, .cft-otp-excluded small { color: #b91c1c; background: #fef2f2; }
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
.lpo-customer-row > td, .lpo-customer-row small { background: #fef9c3; color: #854d0e; }
.details-table td.overnight-checkout, .details-table td.overnight-checkout small { color: #b91c1c; font-weight: 650; }
</style>
