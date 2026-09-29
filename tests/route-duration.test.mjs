import { test } from 'node:test';
import assert from 'node:assert/strict';
import { formatVisitMinutes } from '../resources/js/views/routetracking/duration.js';

test('visit durations round before splitting hours and minutes', () => {
    assert.equal(formatVisitMinutes(0.08333333333333333), '<1 min');
    assert.equal(formatVisitMinutes(10.49), '10 min');
    assert.equal(formatVisitMinutes(10.5), '11 min');
    assert.equal(formatVisitMinutes(59.9), '1h 00m');
    assert.equal(formatVisitMinutes(125.6), '2h 06m');
    assert.equal(formatVisitMinutes(0), '0 min');
});

test('missing and invalid visit durations are unavailable', () => {
    for (const value of [null, undefined, '', NaN, Infinity, -1, 'bad']) {
        assert.equal(formatVisitMinutes(value), 'Unavailable');
    }
    assert.equal(formatVisitMinutes('10.5'), '11 min');
});
