<script setup>
import { computed, nextTick, ref, watch } from "vue";
import { dimensions, groupJourneys, number, percent, rate, trackUrl } from "./analytics";

const props = defineProps({ metrics: Object, loading: Boolean, view: String, initialState: Object });
const rows = computed(() => props.metrics?.analysis?.journeys ?? []);
const grouping = ref("route");
const search = ref("");
const selectedDay = ref("");
const sortKey = ref("label");
const descending = ref(false);
const page = ref(1);
const extended = ref(false);
const issueFilter = ref("");
const attentionPage = ref(1);
const performanceSection = ref(null);
const attentionSection = ref(null);
const detailDialog = ref(null);
const detailRows = ref([]);
const detailTitle = ref("");
const detailPage = ref(1);
const pageSize = 15;
const detailPages = computed(() => Math.max(1, Math.ceil(detailRows.value.length / pageSize)));
const visibleDetails = computed(() => detailRows.value.slice((detailPage.value - 1) * pageSize, detailPage.value * pageSize));
const reviewRows = computed(() => rows.value.filter((row) => row.issues.length));

async function openRows(title, journeys) {
    detailTitle.value = title;
    detailRows.value = journeys;
    detailPage.value = 1;
    await nextTick();
    if (!detailDialog.value.open) detailDialog.value.showModal();
}
function openOverview(title) {
    const details = title === "Returns" ? rows.value.filter((row) => row.amounts.returns?.some((amount) => Number(amount.amount) > 0)) : rows.value;
    if (details.length) openRows(title, details);
}
function getState() {
    return { grouping: grouping.value, search: search.value, selectedDay: selectedDay.value, sortKey: sortKey.value, descending: descending.value,
        page: page.value, extended: extended.value, issueFilter: issueFilter.value, attentionPage: attentionPage.value,
        tableScroll: performanceSection.value?.querySelector('.analytics-table-scroll')?.scrollLeft ?? 0, detailScroll: detailDialog.value?.scrollTop ?? 0,
        detailIds: detailDialog.value?.open ? detailRows.value.map(row => row.routekey) : [], detailTitle: detailTitle.value, detailPage: detailPage.value };
}
defineExpose({ openOverview, getState });
const comparison = computed(() => {
    const source = selectedDay.value ? rows.value.filter((row) => row.date === selectedDay.value) : rows.value;
    const groups = groupJourneys(source, grouping.value).filter((row) => row.label.toLowerCase().includes(search.value.trim().toLowerCase()));
    const value = (row) => sortKey.value === "coverage" ? rate(row.covered, row.planned) : sortKey.value === "productivity" ? rate(row.productive, row.completed) : sortKey.value === "journeys" ? row.rows.length : row[sortKey.value];
    return groups.sort((a, b) => {
        const x = value(a), y = value(b);
        if (x == null) return y == null ? 0 : 1;
        if (y == null) return -1;
        return (typeof x === "string" ? x.localeCompare(y, undefined, { numeric: true }) : x - y) * (descending.value ? -1 : 1);
    });
});
const pages = computed(() => Math.max(1, Math.ceil(comparison.value.length / pageSize)));
const visibleGroups = computed(() => comparison.value.slice((page.value - 1) * pageSize, page.value * pageSize));
const issues = computed(() => [...new Set(reviewRows.value.flatMap((row) => row.issues.map((issue) => issue.label)))].sort());
const attention = computed(() => reviewRows.value.filter((row) => !issueFilter.value || row.issues.some((issue) => issue.label === issueFilter.value)));
const attentionPages = computed(() => Math.max(1, Math.ceil(attention.value.length / pageSize)));
const visibleAttention = computed(() => attention.value.slice((attentionPage.value - 1) * pageSize, attentionPage.value * pageSize));
const money = (amounts) => Object.values(amounts).map((amount) => `${number(amount.amount, 3)} ${amount.currency}`).join(" / ") || "0";
const variance = (row) => row.configured_visits ? `${row.configured_actual_cft - row.expected_cft > 0 ? '+' : ''}${number(row.configured_actual_cft - row.expected_cft, 1)}` : "—";
function sortBy(key) { descending.value = sortKey.value === key ? !descending.value : key !== "label"; sortKey.value = key; }
watch([grouping, search, selectedDay, sortKey, descending], () => page.value = 1);
watch(issueFilter, () => attentionPage.value = 1);
watch(() => props.metrics, async () => {
    page.value = 1; attentionPage.value = 1; selectedDay.value = ""; issueFilter.value = "";
    detailDialog.value?.close(); detailRows.value = [];
    const saved = props.initialState;
    if (saved && props.metrics) {
        grouping.value = saved.grouping; search.value = saved.search; selectedDay.value = saved.selectedDay; sortKey.value = saved.sortKey;
        descending.value = saved.descending; extended.value = saved.extended; issueFilter.value = saved.issueFilter;
        await nextTick(); page.value = saved.page; attentionPage.value = saved.attentionPage;
        const table = performanceSection.value?.querySelector('.analytics-table-scroll');
        if (table) table.scrollLeft = saved.tableScroll ?? 0;
        if (saved.detailIds?.length) {
            await openRows(saved.detailTitle, rows.value.filter(row => saved.detailIds.includes(row.routekey)));
            detailPage.value = saved.detailPage;
            await nextTick(); detailDialog.value.scrollTop = saved.detailScroll ?? 0;
        }
    }
}, { immediate: true });
</script>

<template>
    <div class="dashboard-analytics" :aria-busy="loading">
        <section v-if="view === 'performance'" ref="performanceSection" class="analytics-table-panel" aria-labelledby="performance-title">
            <header class="analytics-table-header"><div><h2 id="performance-title">Performance comparison</h2><p>Compare every selected journey across your organisation.</p></div>
                <div class="analytics-table-controls">
                    <label>Group by <select v-model="grouping"><option v-for="dimension in dimensions" :key="dimension.key" :value="dimension.key">{{ dimension.label }}</option></select></label>
                    <label class="analytics-search"><span class="visually-hidden">Search groups</span><input v-model="search" type="search" placeholder="Search groups..." /></label>
                    <label class="analytics-checkbox"><input v-model="extended" type="checkbox" /> More metrics</label>
                </div>
            </header>
            <div v-if="selectedDay" class="analytics-day-filter">Table filtered to {{ selectedDay }} <button type="button" @click="selectedDay = ''">Clear date filter &times;</button></div>
            <div class="analytics-table-scroll" tabindex="0" aria-label="Scrollable performance comparison">
                <table><thead><tr>
                    <th scope="col"><button @click="sortBy('label')">{{ dimensions.find(d => d.key === grouping).label }} ↕</button></th>
                    <th scope="col"><button @click="sortBy('journeys')">Journeys ↕</button></th>
                    <th scope="col"><button @click="sortBy('coverage')">Coverage ↕</button></th>
                    <th scope="col"><button @click="sortBy('productivity')">Productive ↕</button></th>
                    <th scope="col">Customers</th><th scope="col"><button @click="sortBy('actual_cft')">CFT min ↕</button></th><th scope="col">CFT variance</th><th scope="col">Distance km</th>
                    <th scope="col">Sales</th><th scope="col">Orders</th><th scope="col">Collections</th><th scope="col"><button @click="sortBy('otp')">OTP ↕</button></th>
                    <template v-if="extended"><th scope="col">No sale/order</th><th scope="col">Unplanned</th><th scope="col">Out of sequence</th><th scope="col">Repeat</th><th scope="col">Avg duration min</th></template>
                    <th scope="col">Details</th>
                </tr></thead><tbody>
                    <tr v-if="loading || !visibleGroups.length"><td :colspan="extended ? 18 : 13" class="analytics-empty">{{ loading ? 'Loading performance...' : 'No matching journeys.' }}</td></tr>
                    <tr v-for="group in visibleGroups" :key="group.id">
                        <th scope="row"><button class="analytics-group-link" @click="openRows(group.label, group.rows)">{{ group.label }}</button></th>
                        <td>{{ number(group.rows.length) }}<small>{{ number(group.closed) }} closed</small></td>
                        <td><strong>{{ percent(group.covered, group.planned) }}</strong><small>{{ group.pending }} pending / {{ group.missed }} missed</small></td>
                        <td>{{ percent(group.productive, group.completed) }}<small>{{ group.productive }} / {{ group.completed }} visits</small></td>
                        <td>{{ number(group.customers.size) }}</td><td>{{ number(group.actual_cft, 1) }}</td><td>{{ variance(group) }}</td>
                        <td>{{ number(group.distance, 1) }}<small v-if="group.distance_count">{{ group.distance_count }} journeys</small></td>
                        <td>{{ money(group.amounts.sales) }}</td><td>{{ money(group.amounts.orders) }}</td><td>{{ money(group.amounts.collections) }}</td><td>{{ number(group.otp) }}</td>
                        <template v-if="extended"><td>{{ number(group.nonproductive) }}</td><td>{{ number(group.unplanned) }}</td><td>{{ number(group.out_of_sequence) }}</td><td>{{ number(group.repeat) }}</td><td>{{ number(group.duration_count ? group.duration / group.duration_count : null, 1) }}</td></template>
                        <td><button class="analytics-detail-button" @click="openRows(group.label, group.rows)" :aria-label="`View journeys for ${group.label}`">View</button></td>
                    </tr>
                </tbody></table>
            </div>
            <footer class="analytics-pagination"><span>{{ number(comparison.length) }} groups · Amounts stay in their recorded currencies</span><div><button :disabled="page <= 1" @click="page--">Previous</button><span>{{ page }} / {{ pages }}</span><button :disabled="page >= pages" @click="page++">Next</button></div></footer>
        </section>

        <section v-if="view === 'attention'" ref="attentionSection" class="analytics-table-panel" aria-labelledby="attention-title">
            <header class="analytics-table-header"><div><h2 id="attention-title">Journeys needing attention <span class="analytics-count">{{ number(reviewRows.length) }}</span></h2><p>Review execution differences and incomplete data. Flags are not automatic violations.</p></div>
                <label class="analytics-issue-select">Issue <select v-model="issueFilter"><option value="">All issues</option><option v-for="issue in issues" :key="issue">{{ issue }}</option></select></label>
            </header>
            <div class="analytics-table-scroll" tabindex="0" aria-label="Scrollable journey review queue"><table><thead><tr><th scope="col">Route / start date</th><th scope="col">Division</th><th scope="col">Status</th><th scope="col">Review items</th><th scope="col">OTP</th><th scope="col">Investigate</th></tr></thead><tbody>
                <tr v-if="loading || !visibleAttention.length"><td colspan="6" class="analytics-empty">{{ loading ? 'Loading review queue...' : rows.length ? 'No matching review items.' : 'No journeys to review.' }}</td></tr>
                <tr v-for="row in visibleAttention" :key="row.routekey">
                    <th scope="row">{{ row.routecode }} - {{ row.route }}<small>{{ row.date }} · Journey {{ row.routekey }}</small></th><td>{{ row.division }}</td>
                    <td><span class="analytics-status" :class="{ closed: row.closed }">{{ row.closed ? 'Closed' : 'Open' }}</span></td>
                    <td><div class="analytics-issue-list"><span v-for="issue in row.issues" :key="issue.label" class="analytics-issue" :class="issue.level">{{ issue.label }} · {{ issue.count }}</span></div></td>
                    <td><button class="analytics-group-link" @click="openRows(`Journey ${row.routekey}`, [row])">{{ row.otp }} events</button></td><td><a class="analytics-track-link" :href="trackUrl(row)">Track route &rarr;</a></td>
                </tr>
            </tbody></table></div>
            <footer class="analytics-pagination"><span>{{ number(attention.length) }} matching journeys</span><div><button :disabled="attentionPage <= 1" @click="attentionPage--">Previous</button><span>{{ attentionPage }} / {{ attentionPages }}</span><button :disabled="attentionPage >= attentionPages" @click="attentionPage++">Next</button></div></footer>
        </section>

        <dialog ref="detailDialog" class="analytics-dialog" aria-labelledby="journey-detail-title">
            <header><div><h2 id="journey-detail-title">{{ detailTitle }}</h2><p>{{ detailRows.length }} selected journeys · Activity follows journey start date</p></div><button @click="detailDialog.close()" aria-label="Close journey details">&times;</button></header>
            <div class="analytics-dialog-body">
                <article v-for="row in visibleDetails" :key="row.routekey" class="analytics-journey-detail">
                    <div class="analytics-journey-title"><h3>{{ row.routecode }} - {{ row.route }} <small>{{ row.date }} · Journey {{ row.routekey }}</small></h3><a :href="trackUrl(row)">Open Route Tracking &rarr;</a></div>
                    <dl><div><dt>Coverage</dt><dd>{{ percent(row.covered, row.planned) }}</dd></div><div><dt>Productive visits</dt><dd>{{ row.productive }} / {{ row.completed }}</dd></div><div><dt>Actual CFT</dt><dd>{{ number(row.actual_cft, 1) }} min</dd></div><div><dt>CFT variance</dt><dd>{{ variance(row) }} min</dd></div><div><dt>Journey duration</dt><dd>{{ number(row.duration, 1) }} min</dd></div><div><dt>Recorded distance</dt><dd>{{ number(row.distance, 1) }} km</dd></div></dl>
                    <div class="analytics-detail-money"><span v-for="type in ['sales', 'orders', 'collections', 'returns']" :key="type"><strong>{{ type }}:</strong> {{ money(type === 'returns' ? (row.amounts.returns ?? []).map((amount) => ({ ...amount, amount: -Math.abs(Number(amount.amount)) })) : row.amounts[type]) }}</span></div>
                    <p v-if="row.issues.length" class="analytics-detail-issues">{{ row.issues.map(i => `${i.label}: ${i.count}`).join(' · ') }}</p>
                    <details v-if="row.otp_events.length"><summary>{{ row.otp_events.length }} OTP events — view details</summary><div class="analytics-table-scroll"><table><thead><tr><th>Customer</th><th>Type</th><th>Date / time</th><th>Recorded user</th><th>Reason / comments</th></tr></thead><tbody><tr v-for="event in row.otp_events" :key="event.otplogid"><td>{{ event.customercode }}</td><td>{{ event.otptype }}</td><td>{{ event.otpdate }} {{ event.otptime }}</td><td>{{ event.username || '—' }}</td><td>{{ event.otpreason || '—' }}<small>{{ event.comments }}</small></td></tr></tbody></table></div></details>
                </article>
            </div>
            <footer class="analytics-pagination"><span>{{ detailRows.length }} journeys</span><div><button :disabled="detailPage <= 1" @click="detailPage--">Previous</button><span>{{ detailPage }} / {{ detailPages }}</span><button :disabled="detailPage >= detailPages" @click="detailPage++">Next</button></div></footer>
        </dialog>
    </div>
</template>

<style scoped>
.dashboard-analytics { color: #172b45; margin: 28px 0; }
.analytics-section-heading, .analytics-table-header, .analytics-pagination, .analytics-pagination > div, .analytics-dialog > header, .analytics-journey-title { display: flex; align-items: center; justify-content: space-between; gap: 16px; }
.analytics-section-heading { margin: 0 2px 18px; }
h2 { font-size: 19px; letter-spacing: -.3px; font-weight: 700; margin: 0 0 6px; }
.analytics-section-heading p, .analytics-table-header p, .analytics-dialog header p { margin: 0; font-size: 12px; color: #64748b; line-height: 1.6; }
.analytics-tag { padding: 6px 10px; border: 1px solid #dbeafe; background: #eff6ff; color: #2563eb; border-radius: 6px; font-size: 11px; white-space: nowrap; }
.analytics-signals { display: grid; grid-template-columns: repeat(4, 1fr); gap: 1px; margin-bottom: 20px; border: 1px solid #e2e8f0; background: #e2e8f0; border-radius: 12px; overflow: hidden; }
.analytics-signals > div { display: flex; flex-direction: column; gap: 9px; padding: 18px 22px; background: #fff; }
.analytics-signals span { font-size: 12px; color: #64748b; }
.analytics-signals strong { font-size: 25px; font-variant-numeric: tabular-nums; }
.analytics-charts { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 20px; }
.analytics-time-row { display: grid; grid-template-columns: minmax(0, 2fr) minmax(250px, 1fr); gap: 20px; margin: 20px 0; }
.analytics-operations { background: #172b45; border-radius: 14px; padding: 24px; color: #fff; }
.analytics-operations h3 { font-size: 15px; font-weight: 600; margin: 0 0 24px; color: #fff; }
.analytics-operations > div { border-top: 1px solid #ffffff20; margin-top: 18px; padding-top: 18px; }
.analytics-operations span { display: block; font-size: 12px; color: #cbd5e1; }
.analytics-operations strong { font-size: 26px; display: block; margin: 5px 0; color: #fff; }
.analytics-operations strong small { font-size: 12px; font-weight: 400; color: #cbd5e1; }
.analytics-operations p { font-size: 11px; color: #cbd5e1; margin: 0; line-height: 1.6; }
.analytics-unavailable strong { font-size: 17px; }
.analytics-table-panel { margin: 22px 0; background: #fff; border: 1px solid #e2e8f0; border-radius: 14px; overflow: hidden; scroll-margin-top: 80px; }
.analytics-table-header { padding: 22px; flex-wrap: wrap; }
.analytics-table-header h2 { font-size: 16px; }
.analytics-table-controls { display: flex; align-items: center; gap: 12px; flex-wrap: wrap; }
label { font-size: 11px; color: #52657b; }
select, input[type="search"] { border: 1px solid #d7e0e9; border-radius: 7px; background: #fff; padding: 9px 10px; color: #172b45; font: inherit; font-size: 12px; }
.analytics-table-controls label:not(.analytics-checkbox), .analytics-issue-select { display: flex; align-items: center; gap: 7px; }
.analytics-checkbox { display: flex; align-items: center; gap: 6px; }
.analytics-table-scroll { overflow-x: auto; }
table { border-collapse: collapse; width: 100%; text-align: start; font-size: 12px; }
th, td { padding: 14px 16px; border-bottom: 1px solid #edf2f7; white-space: nowrap; text-align: start; vertical-align: middle; font-variant-numeric: tabular-nums; }
thead th { background: #f8fafc; color: #64748b; font-size: 11px; font-weight: 600; }
thead button { border: 0; background: none; padding: 0; color: inherit; font: inherit; }
tbody th { font-weight: 600; }
tbody tr:hover { background: #f8fafc; }
td small, th small { display: block; font-size: 10px; color: #64748b; font-weight: 400; margin-top: 4px; }
button { cursor: pointer; }
button:focus-visible, select:focus-visible, input:focus-visible, a:focus-visible, summary:focus-visible { outline: 3px solid #93c5fd; outline-offset: 3px; }
.analytics-group-link { font: inherit; color: #2563eb; border: 0; background: transparent; text-align: start; padding: 0; }
.analytics-detail-button, .analytics-pagination button { border: 1px solid #dbe3ed; border-radius: 6px; background: #fff; color: #334155; padding: 6px 10px; font-size: 11px; }
.analytics-detail-button:hover, .analytics-pagination button:hover:not(:disabled) { background: #eff6ff; border-color: #bfdbfe; }
.analytics-pagination { padding: 15px 22px; flex-wrap: wrap; font-size: 11px; color: #64748b; }
.analytics-pagination button:disabled { opacity: .4; cursor: not-allowed; }
.analytics-empty { text-align: center; color: #64748b; padding: 40px; }
.analytics-day-filter { background: #eff6ff; padding: 10px 22px; color: #1d4ed8; font-size: 12px; }
.analytics-day-filter button { border: 0; background: transparent; color: #1d4ed8; text-decoration: underline; margin-left: 12px; }
.analytics-count { display: inline-block; font-size: 12px; color: #b45309; background: #fffbeb; padding: 4px 7px; border-radius: 6px; margin-left: 7px; }
.analytics-issue-list { display: flex; gap: 6px; flex-wrap: wrap; min-width: 220px; max-width: 520px; }
.analytics-issue { border-radius: 5px; padding: 4px 7px; font-size: 10px; background: #f1f5f9; color: #52657b; }
.analytics-issue.review { background: #fff7ed; color: #9a3412; }
.analytics-issue.data { background: #f5f3ff; color: #6d28d9; }
.analytics-status { background: #ecfdf5; color: #047857; padding: 4px 8px; border-radius: 5px; font-size: 10px; }
.analytics-status.closed { background: #f1f5f9; color: #52657b; }
.analytics-track-link { color: #2563eb; font-size: 11px; font-weight: 600; text-decoration: none; }
.analytics-dialog { width: min(1050px, 94vw); max-height: 88vh; padding: 0; border: 1px solid #e2e8f0; border-radius: 16px; color: #172b45; box-shadow: 0 20px 90px #0f172a40; }
.analytics-dialog::backdrop { background: #0f172a88; }
.analytics-dialog > header { padding: 22px; border-bottom: 1px solid #e2e8f0; position: sticky; top: 0; background: #fff; z-index: 1; }
.analytics-dialog header button { border: 0; font-size: 28px; background: #f1f5f9; border-radius: 8px; padding: 0 12px; color: #334155; }
.analytics-dialog-body { padding: 20px; }
.analytics-journey-detail { border: 1px solid #e2e8f0; border-radius: 10px; padding: 18px; margin-bottom: 16px; }
.analytics-journey-title h3 { font-size: 14px; margin: 0; }
.analytics-journey-title small { display: block; margin-top: 7px; font-size: 11px; font-weight: 400; color: #64748b; }
.analytics-journey-title a { color: #2563eb; font-size: 12px; text-decoration: none; }
dl { display: grid; grid-template-columns: repeat(3, 1fr); gap: 16px; padding: 18px 0; margin: 0; }
dt { color: #64748b; font-size: 11px; font-weight: 400; } dd { margin: 5px 0 0; font-size: 16px; font-weight: 600; }
.analytics-detail-money { display: flex; gap: 16px; flex-wrap: wrap; font-size: 12px; padding: 12px; background: #f8fafc; border-radius: 7px; }
.analytics-detail-money strong { text-transform: capitalize; }
.analytics-detail-issues { font-size: 11px; color: #9a3412; margin: 14px 0; }
details { font-size: 12px; margin-top: 15px; } summary { cursor: pointer; color: #2563eb; }
@media (max-width: 1000px) { .analytics-time-row { grid-template-columns: 1fr; } .analytics-operations { display: grid; grid-template-columns: repeat(3, 1fr); gap: 20px; } .analytics-operations h3 { grid-column: 1 / -1; margin: 0; } .analytics-operations > div { margin: 0; } }
@media (max-width: 767px) { .analytics-charts { grid-template-columns: 1fr; } .analytics-signals { grid-template-columns: repeat(2, 1fr); } .analytics-tag { display: none; } .analytics-operations { grid-template-columns: 1fr; } .analytics-table-header, .analytics-journey-title { align-items: flex-start; flex-direction: column; } .analytics-table-controls { width: 100%; } .analytics-search input { width: 150px; } dl { grid-template-columns: repeat(2, 1fr); } }
</style>
