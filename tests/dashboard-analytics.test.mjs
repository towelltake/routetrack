import { test } from "node:test";
import assert from "node:assert/strict";
import { efficiencyCustomers } from "../resources/js/views/routelocation/analytics.js";
import { journeyTimeline, clockTime, selectTimelineRoutes } from "../resources/js/views/routelocation/journeyTimeline.js";
import { groupJourneys, rate, trackUrl, percentageDataset, percentageSeries, chartValueLabel } from "../resources/js/views/routelocation/analytics.js";

const journey = (overrides = {}) => ({
    routekey: "1", routecode: "10", route: "North", date: "2026-09-01", division: "Retail", cmpycode: "1",
    closed: true, planned: 1, covered: 1, completed: 1, productive: 1,
    customer_codes: ["100"], duration: 60, visit_time: 20, remaining_time: 40, distance: 10,
    issues: [], amounts: { sales: [{ currencycode: "1", currency: "OMR", amount: "10.5" }], orders: [], collections: [] },
    ...overrides,
});

test("efficiency popup keeps ignored customers and deduplicates productive repeat visits", () => {
    const sale = { visit_duration_minutes: 10, transactions: { sales: [{ amount: 20, voided: false }] } };
    const rows = efficiencyCustomers([
        { customercode: 1, toplpo: '1', ...sale },
        { customercode: 1, toplpo: 1 },
        { customercode: 2 },
        { customercode: 2, ...sale },
        { customercode: 3, ...sale, visit_duration_minutes: null },
        { customercode: 4, visit_duration_minutes: 10, transactions: { collections: [{ amount: 30 }] } },
    ]);
    assert.deepEqual(rows.map(row => [row.customercode, row.status, row.visit_count]), [
        [1, 'Ignored', 2], [2, 'Productive', 2], [3, 'Nonproductive', 1], [4, 'Productive', 1],
    ]);
    assert.deepEqual(efficiencyCustomers([]), []);
});

test("collection breakdown counts positive completed collections and overlaps sales without duplicating customers", () => {
    const visits = [
        { customercode: 1, visit_duration_minutes: 10, transactions: { collections: [{ amount: 20 }] } },
        { customercode: 1, visit_duration_minutes: 10, transactions: { orders: [{ amount: 10 }] } },
        { customercode: 2, visit_duration_minutes: 10, transactions: { collections: [{ amount: 0 }, { amount: -1 }, { amount: 10, voided: true }] } },
        { customercode: 3, toplpo: 1, visit_duration_minutes: 10, transactions: { collections: [{ amount: 50 }] } },
        { customercode: 4, visit_duration_minutes: null, transactions: { collections: [{ amount: 50 }] } },
    ];
    const rows = efficiencyCustomers(visits);
    assert.equal(rows.filter(row => row.status === 'Productive').length, 1);
    assert.equal(rows.filter(row => row.collection_productive).length, 1);
    assert.equal(rows.filter(row => row.sales_order_productive).length, 1);
    assert.equal(rows[0].visit_count, 2);
    assert.equal(rows[2].status, 'Ignored');
});

test("comparison uses weighted coverage and distinct customers across journeys", () => {
    const [group] = groupJourneys([journey(), journey({ routekey: "2", planned: 9, covered: 1, customer_codes: ["100", "101"] })]);
    assert.equal(rate(group.covered, group.planned), 20);
    assert.equal(group.customers.size, 2);
    assert.equal(group.rows.length, 2);
    assert.equal(group.amounts.sales["1"].amount, 21);
});
test("currency totals remain separate and missing readings are not counted as zero samples", () => {
    const [group] = groupJourneys([journey(), journey({ routekey: "2", closed: false, duration: null, distance: null,
        visit_time: null, remaining_time: null, amounts: { sales: [{ currencycode: "2", currency: "USD", amount: "50" }], orders: [], collections: [] } })]);
    assert.equal(group.duration_count, 1);
    assert.equal(group.distance_count, 1);
    assert.equal(group.duration, 60);
    assert.equal(group.amounts.sales["1"].amount, 10.5);
    assert.equal(group.amounts.sales["2"].amount, 50);
});
test("identically named divisions stay separate and daily totals use journey start date", () => {
    const rows = [journey(), journey({ routekey: "2", cmpycode: "2", date: "2026-09-02" })];
    assert.equal(groupJourneys(rows, "division").length, 2);
    assert.deepEqual(groupJourneys(rows, "date").map(r => r.id), ["2026-09-01", "2026-09-02"]);
    assert.equal(trackUrl(rows[1]), "/route-tracking?routecode=10&date=2026-09-02&from=dashboard");
});
test("empty results and zero denominators remain unavailable", () => {
    assert.deepEqual(groupJourneys([]), []);
    assert.equal(rate(0, 0), null);
    const [group] = groupJourneys([journey({ distance: null, duration: null, visit_time: null, remaining_time: null })]);
    assert.equal(group.distance, undefined);
    assert.equal(group.duration_count, 0);
});

test("graph percentages use each category denominator and weighted summary totals", () => {
    const result = percentageSeries([
        { label: 'Day 1', covered: 1, planned: 1 },
        { label: 'Day 2', covered: 1, planned: 9 },
    ], [['Covered', 'covered', '#123']], row => row.planned);
    assert.deepEqual(result.labels, ['Day 1', 'Day 2']);
    const set = result.datasets[0];
    assert.deepEqual(set.data, [1, 1]);
    assert.equal(set.percentages[0], 100);
    assert.equal(set.percentages[1], 100 / 9);
    assert.equal(set.percentage, 20);
    assert.equal(chartValueLabel(set, 0), '1 (100%)');
});

test("graph percentages preserve missing denominators and values above plan", () => {
    const set = percentageDataset('Operational', [150, 0, null, 10], '#123', [100, 0, 50, null]);
    assert.deepEqual(set.percentages, [150, null, null, null]);
    assert.equal(set.percentage, 150);
    assert.equal(chartValueLabel(set, 1), '0 (percentage unavailable)');
    assert.equal(percentageDataset('Empty', [], '#123', []).percentage, null);
    assert.equal(percentageDataset('Zero', [0], '#123', [5]).percentage, 0);
});

test("exception percentages use all visits rather than the sum of overlapping categories", () => {
    const set = percentageDataset('Exceptions', [8, 6, 4], '#123', [10, 10, 10]);
    assert.deepEqual(set.percentages, [80, 60, 40]);
});

test("timeline positions visits at recorded clock times and merges clipped overlaps", () => {
    const [row] = journeyTimeline([{ routekey: 1, routecode: 10, route: 'North', closed: true,
        start: '2026-09-01 08:00:00', end: '2026-09-01 12:00:00', visits: [
            ['2026-09-01 09:00:00', '2026-09-01 10:00:00'],
            ['2026-09-01 09:30:00', '2026-09-01 10:30:00'],
            ['2026-09-01 11:30:00', '2026-09-01 13:00:00'],
            [null, null], ['2026-09-01 11:00:00', '2026-09-01 10:00:00'],
        ] }]);
    assert.equal(row.from, 480);
    assert.equal(row.to, 720);
    assert.equal(row.visitMinutes, 120);
    assert.equal(row.outsideMinutes, 120);
    assert.deepEqual(row.segments.map(s => [s.kind, s.from, s.to]), [
        ['outside', 480, 540], ['visit', 540, 630], ['outside', 630, 690], ['visit', 690, 720],
    ]);
});

test("timeline splits midnight without shifting local times or adding an empty end day", () => {
    const base = { routekey: 1, routecode: 10, route: 'North', closed: false,
        start: '2026-09-01 23:00:00', end: '2026-09-02 01:00:00',
        visits: [['2026-09-01 23:30:00', '2026-09-02 00:30:00']] };
    const rows = journeyTimeline([base]);
    assert.deepEqual(rows.map(r => [r.date, r.from, r.to, r.visitMinutes]), [
        ['2026-09-01', 1380, 1440, 30], ['2026-09-02', 0, 60, 30],
    ]);
    assert.equal(clockTime(1440), '24:00');
    assert.equal(clockTime(0), '00:00');
    assert.equal(rows[0].closed, false);
    assert.equal(journeyTimeline([{ ...base, end: '2026-09-02 00:00:00' }]).length, 1);
});

test("timeline preserves separate journeys and excludes unavailable or zero durations", () => {
    const base = { routekey: 1, routecode: 10, route: 'North', start: '2026-09-01 08:00:00', end: '2026-09-01 10:00:00', visits: [] };
    const rows = journeyTimeline([base, { ...base, routekey: 2 }, { ...base, end: null }, { ...base, end: base.start }]);
    assert.equal(rows.length, 2);
    assert.notEqual(rows[0].key, rows[1].key);
    assert.equal(rows[0].label, rows[1].label);
    assert.ok(!rows[0].label.includes('Journey'));
    assert.equal(rows[0].outsideMinutes, 120);
    assert.deepEqual(journeyTimeline([]), []);
});

test('OTP timeline overlaps count once and split across midnight', () => {
    const rows = journeyTimeline([{ routekey: 1, routecode: 10, route: 'North',
        start: '2026-09-01 23:00:00', end: '2026-09-02 02:00:00',
        visits: [['2026-09-01 23:15:00', '2026-09-02 01:00:00'], ['2026-09-01 23:30:00', '2026-09-02 00:30:00']],
        otp_visits: [['2026-09-01 23:30:00', '2026-09-02 00:30:00']],
    }]);
    assert.deepEqual(rows.map(r => [r.visitMinutes, r.otpMinutes, r.outsideMinutes]), [[15, 30, 15], [30, 30, 60]]);
    for (const row of rows) assert.equal(row.visitMinutes + row.otpMinutes + row.outsideMinutes, row.duration);
});

test('timeline expansion includes every selected route while collapsed ranks total route duration', () => {
    const journeys = Array.from({ length: 12 }, (_, i) => ({ routekey: i, routecode: i,
        start: '2026-09-01 08:00:00', end: `2026-09-01 ${String(i + 9).padStart(2, '0')}:00:00` }));
    assert.equal(selectTimelineRoutes(journeys).length, 10);
    assert.ok(!selectTimelineRoutes(journeys).some(row => row.routecode < 2));
    assert.equal(selectTimelineRoutes(journeys, true).length, 12);
});
