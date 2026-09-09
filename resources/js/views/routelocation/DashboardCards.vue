<script setup>
import { computed } from "vue";

const props = defineProps({ metrics: Object, loading: Boolean, error: String });
const emit = defineEmits(["inspect"]);
const number = (value, digits = 0) => value == null ? "—" : Number(value).toLocaleString(undefined, { maximumFractionDigits: digits });
const percent = (value) => value == null ? "—" : `${number(value, 1)}%`;
const cards = computed(() => {
    const m = props.metrics;
    const variance = m?.cft_variance_minutes;
    return [
        { title: "Routes started", icon: "fa-route", tone: "blue", value: number(m?.journeys_started),
            note: m ? `${number(m.unique_routes)} distinct routes · Not started: unavailable` : "",
            definition: "Counts every journey started within the selected period. Routes not started requires an agreed operating schedule." },
        { title: "Planned coverage", icon: "fa-location-dot", tone: "teal", value: percent(m?.coverage_percent),
            note: m ? `${number(m.pending_customers)} pending · ${number(m.missed_customers)} missed` : "",
            detail: m?.journeys_without_plan ? `${number(m.journeys_without_plan)} journeys without a plan` : "",
            definition: "Distinct planned customers visited per journey divided by planned customers per journey. Unvisited customers are pending on open journeys and missed on closed journeys." },
        { title: "Productive visits", icon: "fa-check-double", tone: "teal", value: percent(m?.productivity_percent),
            note: m ? `${number(m.nonproductive_visits)} visits with no sale or order` : "",
            definition: "Completed visits with a positive-value, non-voided sale or order, divided by completed visits. Each visit counts once. Collection-only and return-only visits do not count as productive." },
        { title: "Net sales", icon: "fa-chart-line", tone: "blue", amounts: m?.amounts.sales,
            note: "Non-voided invoice totals", definition: "Sum of final invoice totals belonging to selected journeys, including transactions after the period end. Currencies are shown separately." },
        { title: "Order value", icon: "fa-file-invoice", tone: "violet", amounts: m?.amounts.orders,
            note: "Non-voided orders", definition: "Sum of order totals belonging to selected journeys. Orders and invoiced sales are separate measures and should not be added together." },
        { title: "Collections", icon: "fa-wallet", tone: "teal", amounts: m?.amounts.collections,
            note: "Non-voided collection receipts", definition: "Amount paid on collection receipts belonging to selected journeys. Invoice payments are not added again." },
        { title: "Customer Face Time", icon: "fa-clock", tone: "violet", value: number(m?.cft_minutes, 1), unit: "min",
            note: m ? (variance == null ? "Variance unavailable · No planned CFT" : `${variance > 0 ? "+" : ""}${number(variance, 1)} min vs planned · ${number(m.cft_configured_visits)} visits`) : "",
            definition: "Actual duration of completed visits. Variance includes only visits with a positive planned CFT recorded in the customer visit log." },
        { title: "OTP usage", icon: "fa-key", tone: "amber", value: number(m?.otp.events),
            note: m ? `${number(m.otp.visits)} visits matched · All OTP types` : "",
            definition: "OTP events during selected journey time windows. Visits are matched by customer and visit timestamps. Event count does not imply approval." },
    ];
});
</script>

<template>
    <section class="dashboard-overview" aria-labelledby="dashboard-overview-title" :aria-busy="loading">
        <div class="dashboard-overview-heading">
            <div>
                <h2 id="dashboard-overview-title">Journey overview</h2>
                <p>All journeys started in the selected period</p>
            </div>
            <span v-if="loading" role="status">Updating figures...</span>
        </div>
        <p v-if="error" class="dashboard-metrics-error" role="alert">{{ error }} Use Refresh to try again.</p>
        <div class="dashboard-metric-grid">
            <article v-for="card in cards" :key="card.title" class="dashboard-metric-card" :class="`tone-${card.tone}`" :title="card.definition"
                role="button" :tabindex="metrics && !loading ? 0 : -1" :aria-disabled="!metrics || loading"
                :aria-label="`${card.title}. ${card.definition} View journey details.`"
                @click="metrics && !loading && emit('inspect', card.title)"
                @keydown.enter="metrics && !loading && emit('inspect', card.title)"
                @keydown.space.prevent="metrics && !loading && emit('inspect', card.title)">
                <div class="dashboard-metric-heading">
                    <h3>{{ card.title }}</h3>
                    <span class="dashboard-metric-icon"><i class="fa" :class="card.icon" aria-hidden="true"></i></span>
                </div>
                <div v-if="loading" class="dashboard-metric-skeleton" aria-hidden="true"></div>
                <div v-else-if="!metrics" class="dashboard-metric-value">—</div>
                <div v-else-if="card.amounts" class="dashboard-metric-amounts">
                    <div v-for="amount in card.amounts" :key="amount.currency" class="dashboard-metric-money">
                        <strong>{{ number(amount.amount, 3) }}</strong><span>{{ amount.currency }}</span>
                    </div>
                    <div v-if="!card.amounts.length" class="dashboard-metric-value">0</div>
                </div>
                <div v-else class="dashboard-metric-value">{{ card.value }} <span v-if="card.unit">{{ card.unit }}</span></div>
                <p class="dashboard-metric-note">{{ loading ? 'Loading...' : metrics ? card.note : 'Figures unavailable' }}</p>
                <p v-if="!loading && metrics && card.detail" class="dashboard-metric-detail">{{ card.detail }}</p>
            </article>
        </div>
    </section>
</template>

<style scoped>
.dashboard-overview { margin: 28px 0; color: #172b45; }
.dashboard-overview-heading { display: flex; align-items: center; justify-content: space-between; gap: 16px; margin: 0 2px 16px; }
.dashboard-overview-heading h2 { margin: 0 0 5px; font-size: 19px; font-weight: 700; letter-spacing: -.35px; }
.dashboard-overview-heading p, .dashboard-overview-heading > span { margin: 0; color: #64748b; font-size: 12px; }
.dashboard-metric-grid { display: grid; grid-template-columns: repeat(4, minmax(0, 1fr)); gap: 16px; }
.dashboard-metric-card { --accent: #2563eb; --tint: #eff6ff; min-width: 0; padding: 20px; border: 1px solid #e2e8f0; border-radius: 14px; background: #fff; box-shadow: 0 3px 12px #172b4505; }
.dashboard-metric-card[aria-disabled="false"] { cursor: pointer; }
.dashboard-metric-card[aria-disabled="false"]:hover { border-color: #bfdbfe; box-shadow: 0 6px 20px #172b450c; }
.dashboard-metric-card:focus-visible { outline: 3px solid #93c5fd; outline-offset: 3px; }
.tone-teal { --accent: #0f766e; --tint: #f0fdfa; }
.tone-violet { --accent: #7c3aed; --tint: #f5f3ff; }
.tone-amber { --accent: #b45309; --tint: #fffbeb; }
.dashboard-metric-heading { display: flex; justify-content: space-between; align-items: center; gap: 8px; margin-bottom: 20px; }
.dashboard-metric-heading h3 { margin: 0; color: #52657b; font-size: 13px; font-weight: 600; }
.dashboard-metric-icon { display: grid; place-items: center; flex-shrink: 0; width: 34px; height: 34px; border-radius: 10px; background: var(--tint); color: var(--accent); }
.dashboard-metric-value { font-size: clamp(25px, 2.5vw, 34px); line-height: 1.2; font-weight: 750; letter-spacing: -1px; overflow-wrap: anywhere; font-variant-numeric: tabular-nums; }
.dashboard-metric-value span { font-size: 13px; letter-spacing: 0; color: #64748b; font-weight: 500; }
.dashboard-metric-money { display: flex; align-items: baseline; gap: 8px; flex-wrap: wrap; }
.dashboard-metric-money strong { font-size: clamp(23px, 2.2vw, 32px); letter-spacing: -.8px; font-variant-numeric: tabular-nums; overflow-wrap: anywhere; min-width: 0; }
.dashboard-metric-money span { color: #64748b; font-size: 12px; }
.dashboard-metric-note { border-top: 1px solid #f1f5f9; padding-top: 12px; margin: 16px 0 0; color: #64748b; font-size: 11px; line-height: 1.6; }
.dashboard-metric-detail { color: #b45309; font-size: 11px; margin: 5px 0 0; }
.dashboard-metric-skeleton { height: 38px; width: 65%; border-radius: 6px; background: #edf2f7; }
.dashboard-metrics-error { padding: 12px 16px; border-radius: 8px; background: #fef2f2; color: #b91c1c; font-size: 13px; }
@media (max-width: 1100px) { .dashboard-metric-grid { grid-template-columns: repeat(2, minmax(0, 1fr)); } }
@media (max-width: 540px) { .dashboard-metric-grid { grid-template-columns: 1fr; } .dashboard-overview-heading { align-items: flex-start; } }
</style>
