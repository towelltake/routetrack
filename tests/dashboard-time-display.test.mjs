import test from 'node:test';
import assert from 'node:assert/strict';
import { displayedTimeMinutes } from '../resources/js/views/routelocation/time-display.js';

test('time defaults to average across all filtered route starts, including unmeasured starts', () => {
    assert.equal(displayedTimeMinutes(600, 10), 60);
    assert.equal(displayedTimeMinutes(600, 5), 120);
});

test('a date range uses summed durations and route/date starts rather than averaging daily averages', () => {
    const days = [{ minutes: 120, starts: 2 }, { minutes: 600, starts: 3 }];
    assert.equal(displayedTimeMinutes(days.reduce((sum, day) => sum + day.minutes, 0),
        days.reduce((sum, day) => sum + day.starts, 0)), 144);
});

test('totals preserve the original figure and averages retain precision until display', () => {
    assert.equal(displayedTimeMinutes(121, 3, 'totals'), 121);
    assert.equal(displayedTimeMinutes(121, 3), 121 / 3);
    assert.equal(displayedTimeMinutes(0, 3), 0);
    assert.equal(displayedTimeMinutes(0, 0, 'totals'), 0);
});

test('missing durations and absent route starts remain unavailable', () => {
    for (const value of [null, undefined, NaN, Infinity]) {
        assert.equal(displayedTimeMinutes(value, 5), null);
        assert.equal(displayedTimeMinutes(value, 5, 'totals'), null);
    }
    for (const divisor of [null, undefined, 0, -1, NaN, Infinity]) {
        assert.equal(displayedTimeMinutes(120, divisor), null);
    }
});

test('averaging both CFT durations preserves their variance percentage', () => {
    const actual = displayedTimeMinutes(1800, 10);
    const planned = displayedTimeMinutes(2400, 10);
    assert.equal((actual - planned) / planned * 100, -25);
});
