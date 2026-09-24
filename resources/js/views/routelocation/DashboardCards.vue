<script setup>
import { computed } from "vue";

const props = defineProps({ metrics: Object, loading: Boolean, error: String, idle: Object });
const emit = defineEmits(["inspect"]);
const number = (value, digits = 0) => value == null ? "—" : Number(value).toLocaleString(undefined, { maximumFractionDigits: digits });
const percent = (value) => value == null ? "—" : `${number(value, 1)}%`;
const ratioPercent = (value, total) => total > 0 && value != null ? percent(100 * value / total) : "—";
const signedPercent = (value) => value == null ? "N/A" : `${value > 0 ? "+" : ""}${number(value, 1)}%`;
const duration = (value) => {
    if (value == null) return "—";
    const minutes = Math.max(0, Math.round(Number(value)));
    return `${Math.floor(minutes / 60)}:${String(minutes % 60).padStart(2, "0")}`;
};
const cards = computed(() => {
    const m = props.metrics;
    return [
        { title: "Routes Started / Total", icon: "fa-route", tone: "green", value: ratioPercent(m?.routes_started, m?.total_routes),
            note: m ? `${number(m.routes_started)} started / ${number(m.total_routes)} total · ${number(m.routes_closed)} closed` : "",
            detail: m ? `${number(m.route_count)} routes × ${number(m.period_days)} days · ${number(m.routes_not_started)} not started` : "",
            definition: "Started routes / filtered total routes × 100. Routes count once per route start date; closed means all journeys for that route and start date are closed. Total equals accessible routes matching the filters multiplied by inclusive calendar days, including weekends and routes without a journey plan." },
        { title: "JP compliance", icon: "fa-location-dot", tone: "blue", value: percent(m?.planned_without_otp_percent),
            note: m ? `${number(m.planned_visited_without_otp)} / ${number(m.planned_customers)} unique customers visited without OTP` : "",
            detail: m ? `${number(m.pending_customers)} pending / ${number(m.missed_customers)} missed${m.journeys_without_plan ? ` / ${number(m.journeys_without_plan)} journeys without a plan` : ''}` : "",
            breakdown: [{ label: 'Customers visited with OTP', value: m?.planned_with_otp_percent, count: m ? `${number(m.planned_visited_with_otp)} / ${number(m.planned_customers)} unique planned customers` : '' }],
            definition: "Unique planned customers visited without any matched OTP / unique scheduled route sequence customers, counted per journey. Customers with any OTP visit appear separately below, even if they also have a non-OTP visit. All OTP types count; unplanned customers are excluded." },
        { title: "Productivity", icon: "fa-check-double", tone: "green", value: percent(m?.productivity_percent),
            breakdown: [{ label: 'Collection', value: m?.collection_productivity_percent }, { label: 'Orders/Invoices', value: m?.sales_order_productivity_percent }],
            note: m ? `${number(m.productive_visits)} of ${number(m.completed_visits)} visits productive` : "",
            definition: "Eligible completed visits with a positive, non-voided collection, sale or order / eligible completed visits. Each visit counts once in the total. Collection and sales/order breakdowns can overlap. LPO Customers are excluded." },
        { title: "Sales", icon: "fa-chart-line", tone: "green", amounts: m?.amounts.sales,
            note: "Total invoice sales value", definition: "Total sales value" },
        { title: "Order value", icon: "fa-file-invoice", tone: "blue", amounts: m?.amounts.orders,
            note: "Total orders value", definition: "Total order value" },
        { title: "Collections", icon: "fa-wallet", tone: "navy", amounts: m?.amounts.collections,
            note: "Total collection receipts value", definition: "Total collection value" },
        { title: "Operational Time", icon: "fa-clock", tone: "green", value: duration(m?.operational_minutes), unit: "h:mm",
            note: "First check-in to last checkout without OTP", detail: m?.operational_missing_journeys ? `${m.operational_missing_journeys} journeys unavailable` : "", definition: "Sum of each journey's first non-OTP check-in to its last non-OTP checkout. Includes intervening time. A missing final checkout makes the journey unavailable." },
        { title: "OTP usage", icon: "fa-key", tone: "purple", value: ratioPercent(m?.otp?.events, m?.total_visits),
            note: m ? `${number(m.otp?.events)} total OTP / ${number(m.total_visits)} total visits` : "",
            definition: "Total OTP events / total visits × 100 for the selected journeys. Includes all OTP types and all visits, including repeats, incomplete visits and LPO customers. Date ranges use summed counts. Multiple OTP events per visit can produce a rate above 100%. No visits means unavailable." },
        { title: "Unplanned Customers", icon: "fa-location-dot", tone: "orange", value: percent(m?.unplanned_without_otp_percent),
            note: m ? `${number(m.unplanned_customers_without_otp)} / ${number(m.all_unique_visited_customers)} unique visited customers: unplanned without OTP` : "",
            breakdown: [{ label: 'Unplanned customers with OTP', value: m?.unplanned_with_otp_percent, count: m ? `${number(m.unplanned_customers_with_otp)} / ${number(m.all_unique_visited_customers)} unique visited customers` : '' }],
            definition: "Unique unplanned customers without OTP / all unique customers visited in the selected journeys. The OTP percentage below uses the same denominator. Each customer counts once per journey, including incomplete visits and LPO customers. Any matched OTP places that customer only in the OTP group. Journeys without a plan cannot contribute unplanned customers, but their visits remain in the total visited denominator. Date ranges use summed customer counts, not averaged percentages." },
        { title: "Total Duration", icon: "fa-clock", tone: "navy", value: duration(m?.duration_minutes), unit: "h:mm",
            note: m ? `${number(m.duration_available_journeys)} journeys measured · ${number(m.duration_missing_journeys)} unavailable` : "",
            definition: "Route start to end for closed journeys; route start to last reported location for open journeys. GPS readings from subsequent journeys are excluded." },
        { title: "Time Outside Visits", icon: "fa-car", tone: "slate", value: duration(m?.outside_visit_minutes), unit: "h:mm",
            note: "Includes travel, idle time and breaks",
            detail: m?.duration_missing_journeys ? `${number(m.duration_missing_journeys)} journeys excluded: duration unavailable` : "",
            definition: "Journey duration minus summed completed customer visit time, for journeys with available duration" },
        { title: "Returns", icon: "fa-rotate-left", tone: "red", amounts: m?.amounts.returns?.map((amount) => ({ ...amount, amount: -Math.abs(Number(amount.amount)) })),
            note: "Invoice and order returns", definition: "Total returns value" },
        { title: "OTP Customer Time", icon: "fa-key", tone: "purple", value: duration(m?.otp_customer_minutes), unit: "h:mm",
            note: "Visits with OTP", definition: "Total visit time for customers with OTP" },
        { title: "Face Time Compliance", icon: "fa-user-clock", tone: "green", value: signedPercent(m?.face_time_variance_percent),
            comparison: { actual: m?.actual_face_minutes, planned: m?.planned_face_minutes },
            note: m?.face_time_variance_percent == null ? "No planned time available" : m.face_time_variance_percent > 0 ? "Above planned time" : m.face_time_variance_percent < 0 ? "Below planned time" : "On planned time",
            definition: "Actual and planned customer face time exclude OTP visits. Variance (%) = (actual CFT - planned CFT) / planned CFT x 100. Positive is above plan; negative is below plan. Unavailable without planned time." },
        { title: "Efficiency", icon: "fa-gauge-high", tone: "green", value: percent(m?.efficiency_percent),
            breakdown: [{ label: 'Collection', value: m?.collection_efficiency_percent }, { label: 'Orders/Invoices', value: m?.sales_order_efficiency_percent }],
            note: m ? `${number(m.unique_productive_customers)} of ${number(m.unique_visited_customers)} unique customers productive` : "",
            definition: "Unique eligible customers with a completed visit linked to a positive, non-voided collection, invoice or sales order / unique eligible customers visited. Each customer counts once per journey. Collection and sales/order breakdowns can overlap. LPO Customers are excluded." },
        { title: "Idle Time Outside Customer Visits", icon: "fa-hourglass-half", tone: "red", value: props.idle?.loading ? 'Loading...' : duration(props.idle?.minutes), unit: props.idle?.loading ? '' : "h:mm",
            interactive: !props.idle?.loading,
            note: props.idle?.error || "Stationary time only; excludes travel and all customer visits",
            detail: props.idle?.missing ? props.idle.missing + ' journeys unavailable; total includes measured journeys only' : '',
            definition: "GPS-detected stationary time outside customer visit intervals, including exclusion of OTP visits. Missing GPS or journey boundaries are unavailable, not zero. Click for route details." },
    ];
});
const groups = computed(() => [
    { key: "journeys", title: "Journeys", cards: [cards.value[0]] },
    { key: "customers", title: "Customer performance", cards: [cards.value[1], cards.value[8], cards.value[14], cards.value[2], cards.value[7]] },
    { key: "time", title: "Time", cards: [cards.value[9], cards.value[6], cards.value[12], cards.value[13], cards.value[10], cards.value[15]] },
    { key: "transactions", title: "Transactions", cards: [cards.value[3], cards.value[4], cards.value[5], cards.value[11]] },
]);
</script>

<template>
    <section class="dashboard-overview" aria-label="Dashboard metrics" :aria-busy="loading">
        <span v-if="loading" class="visually-hidden" role="status">Updating figures...</span>
        <p v-if="error" class="dashboard-metrics-error" role="alert">{{ error }} Use Refresh to try again.</p>
        <div class="dashboard-metric-groups">
            <section v-for="group in groups" :key="group.key" class="dashboard-metric-group" :class="`group-${group.key}`" :aria-label="group.title">
                <h3 class="dashboard-group-title">{{ group.title }}</h3>
                <div class="dashboard-metric-grid">
            <article v-for="card in group.cards" :key="card.title" class="dashboard-metric-card" :class="`tone-${card.tone}`" :title="card.definition"
                :role="card.interactive === false ? undefined : 'button'" :tabindex="card.interactive !== false && metrics && !loading ? 0 : -1" :aria-disabled="card.interactive === false ? undefined : !metrics || loading"
                :aria-label="`${card.title}. ${card.definition}${card.interactive === false ? '' : ' View journey details.'}`"
                @click="card.interactive !== false && metrics && !loading && emit('inspect', card.title)"
                @keydown.enter="card.interactive !== false && metrics && !loading && emit('inspect', card.title)"
                @keydown.space.prevent="card.interactive !== false && metrics && !loading && emit('inspect', card.title)">
                <span class="dashboard-metric-icon"><i class="fa" :class="card.icon" aria-hidden="true"></i></span>
                <div class="dashboard-metric-copy">
                <h4 class="dashboard-card-title">{{ card.title }}</h4>
                <div v-if="loading" class="dashboard-metric-skeleton" aria-hidden="true"></div>
                <div v-else-if="!metrics" class="dashboard-metric-value">—</div>
                <div v-else-if="card.amounts" class="dashboard-metric-amounts">
                    <div v-for="amount in card.amounts" :key="amount.currency" class="dashboard-metric-money">
                        <strong>{{ number(amount.amount, 3) }}</strong><span>{{ amount.currency }}</span>
                    </div>
                    <div v-if="!card.amounts.length" class="dashboard-metric-value">0</div>
                </div>
                <div v-else-if="!card.comparison" class="dashboard-metric-value">{{ card.value }} <span v-if="card.unit">{{ card.unit }}</span></div>
                <div v-if="!loading && metrics && card.comparison" class="dashboard-time-comparison">
                    <div><span>Actual CFT</span><strong>{{ duration(card.comparison.actual) }}</strong><small>h:mm</small></div>
                    <div><span>Planned CFT</span><strong>{{ duration(card.comparison.planned) }}</strong><small>h:mm</small></div>
                </div>
                <div v-if="!loading && metrics && card.comparison" class="dashboard-face-variance"><strong>{{ card.value }}</strong><span>Variance (%)</span></div>
                <p class="dashboard-metric-note">{{ loading ? 'Loading...' : metrics ? card.note : 'Figures unavailable' }}</p>
                <p v-if="!loading && metrics && card.detail" class="dashboard-metric-detail">{{ card.detail }}</p>
                <div v-if="!loading && metrics && card.breakdown" class="dashboard-metric-breakdown" :class="{ 'single-breakdown': card.breakdown.length === 1 }"><div v-for="(item, index) in card.breakdown" :key="item.label" :class="index === 0 ? 'collection-share' : 'sales-share'"><strong>{{ item.format === 'number' ? number(item.value) : percent(item.value) }}</strong><span>{{ item.label }}</span><small v-if="item.count">{{ item.count }}</small></div></div>
                </div>
                <i v-if="card.interactive !== false" class="fa fa-chevron-right dashboard-metric-open" aria-hidden="true"></i>
            </article>
                </div>
            </section>
        </div>
    </section>
</template>

<style scoped>
.dashboard-overview { margin: 18px 0; color: #172b45; }
.dashboard-metric-groups { display: grid; grid-template-columns: repeat(12, minmax(0, 1fr)); gap: 12px; }
.dashboard-metric-group { display: flex; flex-direction: column; min-width: 0; padding: 12px; border: 1px solid #e8edf3; border-radius: 12px; background: #f8fafc; }
.group-journeys { grid-column: span 2; }
.group-customers { grid-column: span 10; }
.group-time, .group-transactions { grid-column: span 12; }
.dashboard-group-title { margin: 0 0 9px 2px; color: #475569; font-size: 12px; font-weight: 750; letter-spacing: .07em; text-transform: uppercase; }
.dashboard-metric-grid { display: grid; flex: 1; grid-auto-rows: 1fr; grid-template-columns: minmax(0, 1fr); gap: 12px; align-items: stretch; }
.group-customers .dashboard-metric-grid, .group-transactions .dashboard-metric-grid { grid-template-columns: repeat(4, minmax(0, 1fr)); }
.group-time .dashboard-metric-grid { grid-template-columns: repeat(6, minmax(0, 1fr)); }
.group-customers .dashboard-metric-grid { grid-template-columns: repeat(5, minmax(0, 1fr)); }
.dashboard-metric-card { --accent: #2563eb; --tint: #eff6ff; display: grid; grid-template-columns: 32px minmax(0, 1fr) 10px; align-content: start; gap: 10px 8px; min-width: 0; padding: 16px; border: 1px solid #e2e8f0; border-radius: 12px; background: #fff; box-shadow: 0 2px 8px #172b4505; }
.dashboard-metric-card[aria-disabled="false"] { cursor: pointer; transition: border-color .15s, box-shadow .15s; }
.dashboard-metric-card[aria-disabled="false"]:hover { border-color: var(--accent); box-shadow: 0 4px 14px #172b4510; }
.dashboard-metric-card:focus-visible { outline: 3px solid #93c5fd; outline-offset: 3px; }
.tone-green { --accent: #15803d; --tint: #f0fdf4; }
.tone-purple { --accent: #7c3aed; --tint: #f5f3ff; }
.tone-orange { --accent: #c2410c; --tint: #fff7ed; }
.tone-navy { --accent: #172b45; --tint: #eef2f6; }
.tone-slate { --accent: #475569; --tint: #f1f5f9; }
.tone-red { --accent: #dc2626; --tint: #fef2f2; }
.dashboard-metric-copy { display: contents; }
.dashboard-metric-icon { display: grid; place-items: center; width: 32px; height: 32px; border-radius: 9px; background: var(--tint); color: var(--accent); font-size: 13px; }
.dashboard-card-title { margin: 0; align-self: center; color: #475569; font-size: 12px; line-height: 1.35; font-weight: 650; }
.dashboard-metric-value, .dashboard-metric-amounts, .dashboard-metric-note, .dashboard-metric-detail, .dashboard-metric-skeleton, .dashboard-time-comparison, .dashboard-face-variance { grid-column: 1 / -1; }
.dashboard-metric-value, .dashboard-metric-money strong { color: var(--accent); font-size: clamp(23px, 1.8vw, 28px); font-weight: 750; letter-spacing: -.6px; line-height: 1.15; overflow-wrap: anywhere; font-variant-numeric: tabular-nums; }
.dashboard-metric-value span, .dashboard-metric-money span { margin-left: 4px; color: #64748b; font-size: 11px; font-weight: 500; letter-spacing: 0; }
.dashboard-metric-money + .dashboard-metric-money { margin-top: 7px; }
.dashboard-metric-note, .dashboard-metric-detail { margin: 0; color: #64748b; font-size: 11.5px; line-height: 1.45; }
.dashboard-metric-open { grid-column: 3; grid-row: 1; align-self: center; color: #94a3b8; font-size: 10px; }
.dashboard-time-comparison { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 8px; }
.dashboard-time-comparison span { display: block; color: #64748b; font-size: 11px; }
.dashboard-time-comparison strong { color: var(--accent); font-size: clamp(21px, 1.6vw, 26px); font-weight: 650; font-variant-numeric: tabular-nums; }
.dashboard-face-variance { display: flex; align-items: baseline; gap: 8px; padding-top: 9px; border-top: 1px solid #edf2f7; }
.dashboard-face-variance strong { font-size: 15px; color: var(--accent); }
.dashboard-face-variance span { font-size: 11px; color: #64748b; }
.dashboard-time-comparison small { display: block; margin-top: 3px; color: #94a3b8; font-size: 10px; }
.dashboard-metric-skeleton { height: 35px; border-radius: 6px; background: #edf2f7; }
.dashboard-metric-breakdown { grid-column: 1 / -1; width: 100%; display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 8px; border-top: 1px solid #e2e8f0; margin-top: 8px; padding-top: 12px; }
.dashboard-metric-breakdown.single-breakdown { grid-template-columns: minmax(0, 1fr); }
.dashboard-metric-breakdown small { color: #64748b; font-size: 11px; line-height: 1.4; }
.dashboard-metric-breakdown > div { display: flex; flex-direction: column; align-items: center; gap: 6px; padding: 12px 6px; border-radius: 10px; text-align: center; min-width: 0; }
.dashboard-metric-breakdown strong { font-size: 24px; font-weight: 800; line-height: 1.1; letter-spacing: -.6px; font-variant-numeric: tabular-nums; }
.dashboard-metric-breakdown span { color: #52657b; font-size: 10px; font-weight: 600; line-height: 1.4; }
.dashboard-metric-breakdown .collection-share { color: #7c3aed; background: #f5f3ff; }
.dashboard-metric-breakdown .sales-share { color: #2563eb; background: #eff6ff; }
.group-customers .dashboard-metric-card { grid-template-rows: 32px auto minmax(34px, auto) 1fr auto; }
.group-customers .dashboard-metric-value, .group-customers .dashboard-metric-skeleton { grid-row: 2; }
.group-customers .dashboard-metric-note { grid-row: 3; }
.group-customers .dashboard-metric-detail { grid-row: 4; }
.group-customers .dashboard-metric-breakdown { grid-row: 5; align-self: end; margin-top: 0; }
@media (max-width: 1500px) and (min-width: 1051px) { .group-time .dashboard-metric-grid { grid-template-columns: repeat(3, minmax(0, 1fr)); } }
.dashboard-metrics-error { padding: 12px 16px; border-radius: 8px; background: #fef2f2; color: #b91c1c; font-size: 13px; }
@media (max-width: 1200px) { .dashboard-metric-group { grid-column: span 12; } }
@media (max-width: 1050px) { .group-customers .dashboard-metric-grid, .group-time .dashboard-metric-grid { grid-template-columns: repeat(3, minmax(0, 1fr)); } }
@media (max-width: 700px) { .group-customers .dashboard-metric-grid, .group-transactions .dashboard-metric-grid, .group-time .dashboard-metric-grid { grid-template-columns: repeat(2, minmax(0, 1fr)); } .dashboard-metric-card { padding: 12px; } }
@media (max-width: 420px) { .group-time .dashboard-metric-grid { grid-template-columns: 1fr; } }
</style>
