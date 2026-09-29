import test from 'node:test';
import assert from 'node:assert/strict';
import { analytics, assignCustomers, clock, dateAt, dayOf, generatePlan, moveVisit, scheduleDay, validateInputs, weekPattern } from '../resources/js/views/journeyplan/workspace.js';

const customer = (id = 'c', extra = {}) => ({ id, name: id, lat: 23.6, lng: 58.4, frequency: 4, face: 20, days: [0, 1, 2, 3, 4], pin: '', area: '', region: '', channel: '', windowStart: '', windowEnd: '', ...extra });
const rep = (id = 'r', extra = {}) => ({ id, name: id, days: [0, 1, 2, 3, 4], start: '08:00', end: '17:00', maxVisits: 20, areas: '', regions: '', channels: [], lat: 23.5, lng: 58.3, commute: false, ...extra });
const settings = { start: '2026-10-04', speed: 40 };

test('frequencies 1–20 retain all requested visits and follow week patterns', () => {
  for (let frequency = 1; frequency <= 20; frequency++) {
    const c = customer('c', { frequency }), p = generatePlan([c], [rep()], settings, { c: 'r' });
    assert.equal(p.days.reduce((sum, d) => sum + d.ids.length, 0), frequency);
    assert.equal(p.issues.length, 0);
    assert.ok(p.days.every(d => new Set(d.ids).size === d.ids.length));
    assert.equal(analytics(p).frequency[0].delta, 0);
  }
  assert.deepEqual(weekPattern(2, [1, 0, 1, 0]), [1, 3]);
  assert.deepEqual(weekPattern(3, [0, 1, 0, 0]), [0, 2, 3]);
  assert.equal(dateAt('2026-12-20', 27), '2027-01-16');
});

test('reports each unplaced repeat instead of treating the customer as fulfilled', () => {
  const p = generatePlan([customer('c', { frequency: 8, days: [1] })], [rep()], settings, { c: 'r' });
  assert.equal(p.days.reduce((sum, d) => sum + d.ids.length, 0), 4);
  assert.equal(p.issues.reduce((sum, i) => sum + i.count, 0), 4);
  assert.ok(p.days.filter(d => d.ids.length).every(d => dayOf(d.date) === 1));
});

test('ownership, area/channel eligibility and missing coordinates remain explicit', () => {
  const cs = [customer('a', { area: 'north', channel: 'MT' }), customer('b', { area: 'south', channel: 'TT' }), customer('bad', { lat: null }), customer('pin', { pin: 'north', area: 'south', channel: 'TT' })];
  const rs = [rep('north', { areas: 'north', channels: ['MT'] }), rep('south', { areas: 'south', channels: ['TT'] })];
  const a = assignCustomers(cs, rs);
  assert.equal(a.assignments.a, 'north'); assert.equal(a.assignments.b, 'south'); assert.equal(a.assignments.pin, 'north');
  assert.equal(a.issues[0].customer, 'bad');
  const p = generatePlan(cs, rs, settings, a.assignments);
  assert.equal(p.issues.reduce((sum, i) => sum + i.count, 0), 4);
  assert.ok(p.days.filter(d => d.ids.includes('pin')).every(d => d.rep === 'north'));
});

test('time windows allow waiting and overflow is visible without losing visits', () => {
  const c = customer('c', { face: 120, windowStart: '16:30', windowEnd: '16:45' });
  const d = scheduleDay(['c'], rep(), { c }, 40);
  assert.equal(d.visits[0].start, 990); assert.equal(d.waiting, 510); assert.equal(d.overload, 90);
  assert.ok(d.visits[0].warnings.includes('Outside working hours'));
  const late = scheduleDay(['c'], rep('r', { start: '17:00', end: '18:00' }), { c }, 40);
  assert.ok(late.visits[0].warnings.includes('Outside customer time window'));
  assert.equal(clock(59.9), '01:00');
});

test('commute and return travel are included only when enabled', () => {
  const c = customer(), byId = { c };
  const without = scheduleDay(['c'], rep(), byId, 40), withCommute = scheduleDay(['c'], rep('r', { commute: true }), byId, 40);
  assert.equal(without.travel, 0); assert.ok(withCommute.travel > 0);
  assert.ok(withCommute.returnMinutes > 0); assert.ok(withCommute.finish > without.finish);
});

test('cross-salesman move transfers every visit and leaves input plan immutable', () => {
  const p = generatePlan([customer()], [rep('a'), rep('b')], settings, { c: 'a' });
  const source = p.days.find(d => d.ids.includes('c')), dest = p.days.find(d => d.rep === 'b' && d.date === source.date);
  const moved = moveVisit(p, 'c', source.key, dest.key);
  assert.equal(moved.days.filter(d => d.rep === 'a').flatMap(d => d.ids).length, 0);
  assert.equal(moved.days.filter(d => d.rep === 'b').flatMap(d => d.ids).length, 4);
  assert.equal(p.days.filter(d => d.rep === 'a').flatMap(d => d.ids).length, 4);
  assert.equal(moved.assignments.c, 'b');
});

test('invalid move is atomic, pins cannot be bypassed, same-rep move preserves demand', () => {
  const p = generatePlan([customer('c', { pin: 'a' })], [rep('a'), rep('b')], settings, {});
  const source = p.days.find(d => d.ids.length), dest = p.days.find(d => d.rep === 'b');
  const before = structuredClone(p);
  assert.throws(() => moveVisit(p, 'c', source.key, dest.key), /violates/);
  assert.deepEqual(p, before);
  const sameRep = p.days.find(d => d.rep === 'a' && !d.ids.length);
  const moved = moveVisit(p, 'c', source.key, sameRep.key);
  assert.equal(moved.days.find(d => d.key === source.key).ids.length, 0);
  assert.equal(analytics(moved).frequency[0].planned, 4);
});

test('validation rejects invalid schedules and dates without confusing missing GPS with fatal input errors', () => {
  assert.equal(validateInputs([customer()], [rep()], settings).length, 0);
  assert.equal(validateInputs([customer('c', { lat: null })], [rep()], settings).length, 0);
  assert.ok(validateInputs([customer('c', { frequency: 0, days: [], windowStart: '10:00' })], [rep('r', { end: '07:00' })], { start: '2026-02-30', speed: 0 }).length >= 5);
  assert.throws(() => generatePlan([customer()], [rep()], { ...settings, start: '' }, {}), /valid period/);
});

test('monthly work balances independently for each salesman and snapshot survives input edits', () => {
  const cs = Array.from({ length: 16 }, (_, i) => customer(String(i), { frequency: 1, pin: i < 8 ? 'a' : 'b' }));
  const rs = [rep('a'), rep('b')], p = generatePlan(cs, rs, settings, {});
  for (const r of analytics(p).reps) assert.deepEqual(r.weeks, [2, 2, 2, 2]);
  cs[0].name = 'Changed'; rs[0].start = '10:00';
  assert.notEqual(p.customers[0].name, cs[0].name); assert.equal(p.reps[0].start, '08:00');
});
