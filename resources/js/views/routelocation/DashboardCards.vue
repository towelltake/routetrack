<script setup>
import { computed } from "vue";

const props = defineProps({ metrics: Object, loading: Boolean, error: String });
const emit = defineEmits(["inspect"]);
const number = (value, digits = 0) => value == null ? "—" : Number(value).toLocaleString(undefined, { maximumFractionDigits: digits });
const percent = (value) => value == null ? "—" : `${number(value, 1)}%`;
const signedPercent = (value) => value == null ? "0%" : `${value > 0 ? "+" : ""}${number(value, 1)}%`;
const duration = (value) => {
    if (value == null) return "—";
    const minutes = Math.max(0, Math.round(Number(value)));
    return `${Math.floor(minutes / 60)}:${String(minutes % 60).padStart(2, "0")}`;
};
const cards = computed(() => {
    const m = props.metrics;
    return [
        { title: "Routes Started / Total", icon: "fa-route", tone: "green", value: m ? `${number(m.routes_started)} / ${number(m.total_routes)}` : "—",
            note: m ? `${number(m.route_count)} routes × ${number(m.period_days)} days · ${number(m.routes_not_started)} not started` : "",
            definition: "Started routes count once per route start date. Total routes equals accessible routes matching the filters multiplied by inclusive calendar days, including weekends. Uses the current route master, including routes without a journey plan." },
        { title: "Planned coverage", icon: "fa-location-dot", tone: "blue", value: percent(m?.coverage_percent),
            note: m ? `${number(m.planned_visited)} / ${number(m.planned_customers)} covered · ${number(m.pending_customers)} pending · ${number(m.missed_customers)} missed` : "",
            detail: m?.journeys_without_plan ? `${number(m.journeys_without_plan)} journeys without a plan` : "",
            definition: "Distinct planned customers visited per journey divided by planned customers per journey. Unvisited customers are pending on open journeys and missed on closed journeys." },
        { title: "Productive visits", icon: "fa-check-double", tone: "green", value: percent(m?.productivity_percent),
            note: m ? `${number(m.productive_visits)} of ${number(m.completed_visits)} visits productive` : "",
            definition: "Completed visits with a positive-value, non-voided sale or order, divided by completed visits. Each visit counts once. Collection-only and return-only visits do not count as productive." },
        { title: "Sales", icon: "fa-chart-line", tone: "green", amounts: m?.amounts.sales,
            note: "Total invoice sales value", definition: "Total sales value" },
        { title: "Order value", icon: "fa-file-invoice", tone: "blue", amounts: m?.amounts.orders,
            note: "Total orders value", definition: "Total order value" },
        { title: "Collections", icon: "fa-wallet", tone: "navy", amounts: m?.amounts.collections,
            note: "Total collection receipts value", definition: "Total collection value" },
        { title: "Operational Time", icon: "fa-clock", tone: "green", value: duration(m?.operational_minutes), unit: "h:mm",
            note: "All completed customer visits", definition: "Total customer visit time" },
        { title: "OTP usage", icon: "fa-key", tone: "purple", value: number(m?.otp.events),
            note: "All OTP types",
            definition: "OTP events during selected journey time windows. Visits are matched by customer and visit timestamps. Event count does not imply approval." },
        { title: "Unplanned Customers", icon: "fa-location-dot", tone: "orange", value: number(m?.unplanned_customers),
            note: "Unique customers per journey", definition: "Customers visited outside the journey plan. Journeys without a plan are excluded." },
        { title: "Total Duration", icon: "fa-clock", tone: "navy", value: duration(m?.duration_minutes), unit: "h:mm",
            note: m ? `${number(m.duration_available_journeys)} journeys measured · ${number(m.duration_missing_journeys)} unavailable` : "",
            definition: "Route start to end for closed journeys; route start to last reported location for open journeys. GPS readings from subsequent journeys are excluded." },
        { title: "Time Outside Visits", icon: "fa-car", tone: "slate", value: duration(m?.outside_visit_minutes), unit: "h:mm",
            note: "Includes travel, idle time and breaks",
            detail: m?.duration_missing_journeys ? `${number(m.duration_missing_journeys)} journeys excluded: duration unavailable` : "",
            definition: "Journey duration minus operational time, summed for journeys with available duration" },
        { title: "Returns", icon: "fa-rotate-left", tone: "red", amounts: m?.amounts.returns?.map((amount) => ({ ...amount, amount: -Math.abs(Number(amount.amount)) })),
            note: "Invoice and order returns", definition: "Total returns value" },
        { title: "OTP Customer Time", icon: "fa-key", tone: "purple", value: duration(m?.otp_customer_minutes), unit: "h:mm",
            note: "Visits with OTP", definition: "Total visit time for customers with OTP" },
        { title: "Face Time Compliance", icon: "fa-user-clock", tone: "green", value: signedPercent(m?.face_time_variance_percent),
            comparison: { actual: m?.actual_face_minutes, planned: m?.planned_face_minutes },
            note: m?.face_time_variance_percent == null ? "No planned time available" : m.face_time_variance_percent > 0 ? "Above planned time" : m.face_time_variance_percent < 0 ? "Below planned time" : "On planned time",
            definition: "Variance from planned face time, excluding OTP visits" },
    ];
});
const groups = computed(() => [
    { key: "journeys", title: "Journeys", cards: [cards.value[0]] },
    { key: "customers", title: "Customer performance", cards: [cards.value[1], cards.value[8], cards.value[2], cards.value[7]] },
    { key: "time", title: "Time", cards: [cards.value[9], cards.value[6], cards.value[12], cards.value[13], cards.value[10]] },
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
                role="button" :tabindex="metrics && !loading ? 0 : -1" :aria-disabled="!metrics || loading"
                :aria-label="`${card.title}. ${card.definition} View journey details.`"
                @click="metrics && !loading && emit('inspect', card.title)"
                @keydown.enter="metrics && !loading && emit('inspect', card.title)"
                @keydown.space.prevent="metrics && !loading && emit('inspect', card.title)">
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
                    <div><span>Actual</span><strong>{{ duration(card.comparison.actual) }}</strong></div>
                    <div><span>Planned</span><strong>{{ duration(card.comparison.planned) }}</strong></div>
                    <small>h:mm</small>
                </div>
                <div v-if="!loading && metrics && card.comparison" class="dashboard-face-variance"><strong>{{ card.value }}</strong><span>Variance</span></div>
                <p class="dashboard-metric-note">{{ loading ? 'Loading...' : metrics ? card.note : 'Figures unavailable' }}</p>
                <p v-if="!loading && metrics && card.detail" class="dashboard-metric-detail">{{ card.detail }}</p>
                </div>
                <i class="fa fa-chevron-right dashboard-metric-open" aria-hidden="true"></i>
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
.group-journeys { grid-column: span 3; }
.group-customers { grid-column: span 9; }
.group-time, .group-transactions { grid-column: span 12; }
.dashboard-group-title { margin: 0 0 9px 2px; color: #475569; font-size: 12px; font-weight: 750; letter-spacing: .07em; text-transform: uppercase; }
.dashboard-metric-grid { display: grid; flex: 1; grid-auto-rows: 1fr; grid-template-columns: minmax(0, 1fr); gap: 12px; align-items: stretch; }
.group-customers .dashboard-metric-grid, .group-transactions .dashboard-metric-grid { grid-template-columns: repeat(4, minmax(0, 1fr)); }
.group-time .dashboard-metric-grid { grid-template-columns: repeat(5, minmax(0, 1fr)); }
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
.dashboard-time-comparison { display: grid; grid-template-columns: 1fr 1fr auto; gap: 8px; }
.dashboard-time-comparison span { display: block; color: #64748b; font-size: 11px; }
.dashboard-time-comparison strong { color: var(--accent); font-size: clamp(21px, 1.6vw, 26px); font-weight: 650; font-variant-numeric: tabular-nums; }
.dashboard-face-variance { display: flex; align-items: baseline; gap: 8px; padding-top: 9px; border-top: 1px solid #edf2f7; }
.dashboard-face-variance strong { font-size: 15px; color: var(--accent); }
.dashboard-face-variance span { font-size: 11px; color: #64748b; }
.dashboard-time-comparison small { align-self: end; color: #94a3b8; font-size: 10px; }
.dashboard-metric-skeleton { height: 35px; border-radius: 6px; background: #edf2f7; }
.dashboard-metrics-error { padding: 12px 16px; border-radius: 8px; background: #fef2f2; color: #b91c1c; font-size: 13px; }
@media (max-width: 1200px) { .dashboard-metric-group { grid-column: span 12; } }
@media (max-width: 1050px) { .group-time .dashboard-metric-grid { grid-template-columns: repeat(3, minmax(0, 1fr)); } }
@media (max-width: 700px) { .group-customers .dashboard-metric-grid, .group-transactions .dashboard-metric-grid, .group-time .dashboard-metric-grid { grid-template-columns: repeat(2, minmax(0, 1fr)); } .dashboard-metric-card { padding: 12px; } }
@media (max-width: 420px) { .group-time .dashboard-metric-grid { grid-template-columns: 1fr; } }
</style>
