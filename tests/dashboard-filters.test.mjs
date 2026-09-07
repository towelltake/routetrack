import { test } from "node:test";
import assert from "node:assert/strict";
import { filterFields, filterOptions } from "../resources/js/views/routelocation/filters.js";

const rows = [
    { routecode: 1, routename: "One", entity: "A", clustercode: 10, cmpycode: 1, regionmstcode: 100 },
    { routecode: 2, routename: "Two", entity: "A", clustercode: 10, cmpycode: 1, regionmstcode: 200 },
    { routecode: 3, routename: "Three", entity: "B", clustercode: 20, cmpycode: 2, regionmstcode: 100 },
    { routecode: 4, routename: "Four", entity: " ", clustercode: null, cmpycode: 3, regionmstcode: 300 },
];
const values = (key, selection = {}) => filterOptions(rows,
    { ...Object.fromEntries(filterFields.map(({ key }) => [key, []])), ...selection },
    filterFields.find((field) => field.key === key)).map(({ value }) => value);

test("route selection narrows every related dimension", () => {
    for (const [key, expected] of Object.entries({ entities: ["A"], clusters: [10], divisions: [1], regions: [200] })) {
        assert.deepEqual(values(key, { routes: [2] }), expected);
    }
});
test("region narrows routes but remains independent of company hierarchy", () => {
    assert.deepEqual(values("routes", { regions: [100] }), [1, 3]);
    assert.deepEqual(values("entities", { regions: [200] }), ["A", "B"]);
    assert.deepEqual(values("regions", { divisions: [1] }), [100, 200, 300]);
});
test("multi-select combines dimensions and preserves additional choices within the same filter", () => {
    assert.deepEqual(values("routes", { divisions: [1, 2], regions: [100] }), [1, 3]);
    assert.deepEqual(values("entities", { entities: ["A"] }), ["A", "B"]);
    assert.deepEqual(values("routes", { clusters: [20], divisions: [1] }), []);
    assert.deepEqual(values("entities", { routes: [1, 3] }), ["A", "B"]);
});
test("clearing selections restores distinct nonblank options", () => {
    assert.deepEqual(values("entities"), ["A", "B"]);
    assert.deepEqual(values("clusters"), [10, 20]);
});
