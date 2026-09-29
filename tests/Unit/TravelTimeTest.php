<?php

use App\Services\TravelTime;

function travelFixture(): array
{
    return ['duration' => 7200, 'journey_start_timestamp' => strtotime('2026-09-10 08:00:00'),
        'travel_gps_first' => strtotime('2026-09-10 08:00:00'), 'travel_gps_last' => strtotime('2026-09-10 10:00:00'),
        'stationary_periods' => [['start_time' => '2026-09-10 08:15:00', 'end_time' => '2026-09-10 09:00:00']],
        'gps_gaps' => [['start_time' => '2026-09-10 08:20:00', 'end_time' => '2026-09-10 09:15:00']]];
}

test('travel subtraction counts overlapping visits stops and gaps once including OTP and LPO visits', function () {
    $visits = [
        ['visit_start_date' => '2026-09-10', 'visit_start_time' => '07:50:00', 'visit_end_date' => '2026-09-10', 'visit_end_time' => '08:25:00', 'operational_otp' => true],
        ['visit_start_date' => '2026-09-10', 'visit_start_time' => '08:10:00', 'visit_end_date' => '2026-09-10', 'visit_end_time' => '08:30:00', 'toplpo' => 1],
    ];
    $actual = travelFixture();
    $summary = (new TravelTime)->summarize($actual, $visits);
    expect($summary)->toBe(['travel_time' => 2700, 'travel_visit_seconds' => 1800, 'travel_idle_seconds' => 1800, 'travel_unknown_seconds' => 900])
        ->and(array_sum($summary))->toBe($actual['duration'])
        ->and($actual['duration'])->toBe(7200);
});

test('travel does not count missing GPS or uncovered journey boundaries as travel', function () {
    $actual = travelFixture();
    $actual['stationary_periods'] = []; $actual['gps_gaps'] = [];
    $actual['travel_gps_first'] += 600; $actual['travel_gps_last'] -= 600;
    expect((new TravelTime)->summarize($actual, [])['travel_time'])->toBe(6000);
    $actual['travel_gps_first'] = null;
    expect((new TravelTime)->summarize($actual, []))->toMatchArray(['travel_time' => 0, 'travel_unknown_seconds' => 7200]);
    $actual['duration'] = null;
    expect((new TravelTime)->summarize($actual, [])['travel_time'])->toBeNull();
});
