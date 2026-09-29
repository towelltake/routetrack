import test from 'node:test';
import assert from 'node:assert/strict';
import { hasCoordinates, optimiseOrder, routeDistance } from '../resources/js/views/journeyplan/planning.js';

const point = (id, longitude) => ({ customercode: id, fixedlatitude: 23, fixedlongitude: longitude });
test('preview improves crossed sequence, preserves first stop and all customers', () => {
  const original = [point(1, 58), point(2, 58.3), point(3, 58.1), point(4, 58.2)];
  const before = structuredClone(original);
  const result = optimiseOrder(original);
  assert.equal(result[0].customercode, 1);
  assert.deepEqual(result.map(row => row.customercode).sort(), [1, 2, 3, 4]);
  assert.ok(routeDistance(result) < routeDistance(original));
  assert.deepEqual(original, before);
});
test('missing coordinates do not drop a customer or invent a distance', () => {
  const original = [point(1, 58), { ...point(2, 59), fixedlatitude: null }, point(3, 60)];
  assert.equal(routeDistance(original), null);
  assert.deepEqual(optimiseOrder(original), original);
  assert.equal(hasCoordinates({ fixedlatitude: 0, fixedlongitude: 0 }), false);
  assert.equal(hasCoordinates({ fixedlatitude: 91, fixedlongitude: 58 }), false);
});
test('preview never worsens the measured open-path distance', () => {
  for (let i = 0; i < 40; i++) {
    const rows = Array.from({ length: 12 }, (_, k) => point(k, 50 + ((k * 13 + i * 7) % 19) / 10));
    assert.ok(routeDistance(optimiseOrder(rows)) <= routeDistance(rows));
  }
  assert.deepEqual(optimiseOrder([]), []);
  assert.equal(routeDistance([point(1, 58)]), 0);
});
