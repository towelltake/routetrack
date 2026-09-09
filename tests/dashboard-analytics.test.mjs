import { test } from "node:test";
import assert from "node:assert/strict";
import { groupJourneys, rate, trackUrl } from "../resources/js/views/routelocation/analytics.js";

const journey = (overrides = {}) => ({
    routekey: "1", routecode: "10", route: "North", date: "2026-09-01", division: "Retail", cmpycode: "1",
    closed: true, planned: 1, covered: 1, completed: 1, productive: 1,
    customer_codes: ["100"], duration: 60, visit_time: 20, remaining_time: 40, distance: 10,
    issues: [], amounts: { sales: [{ currencycode: "1", currency: "OMR", amount: "10.5" }], orders: [], collections: [] },
    ...overrides,
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
    assert.equal(trackUrl(rows[1]), "/route-tracking?routecode=10&date=2026-09-02");
});
test("empty results and zero denominators remain unavailable", () => {
    assert.deepEqual(groupJourneys([]), []);
    assert.equal(rate(0, 0), null);
    const [group] = groupJourneys([journey({ distance: null, duration: null, visit_time: null, remaining_time: null })]);
    assert.equal(group.distance, undefined);
    assert.equal(group.duration_count, 0);
});
