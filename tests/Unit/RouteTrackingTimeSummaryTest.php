<?php

use App\Http\Controllers\RouteTracking\RouteTrackingController;

uses(Tests\TestCase::class);

test('route time excludes OTP visits once and splits partially overlapping stationary periods', function () {
    $visit = fn ($key, $start, $end, $minutes, $otp = []) => [
        'logkey' => $key, 'visit_start_date' => '2026-09-10', 'visit_start_time' => $start,
        'visit_end_date' => '2026-09-10', 'visit_end_time' => $end,
        'visit_duration_minutes' => $minutes, 'otp_logs' => $otp,
    ];
    $actual = ['duration' => 7200, 'stationary_seconds' => 4200, 'stationary_periods' => [
        ['start_time' => '2026-09-10 09:00:00', 'end_time' => '2026-09-10 10:00:00', 'duration_seconds' => 3600],
        ['start_time' => '2026-09-10 11:00:00', 'end_time' => '2026-09-10 11:10:00', 'duration_seconds' => 600],
    ]];
    $visits = collect([
        $visit(1, '08:55:00', '09:20:00', 25, [['id' => 1], ['id' => 2]]),
        $visit(2, '09:10:00', '09:30:00', 20),
        $visit(3, '09:40:00', '09:50:00', 10),
        $visit(4, '11:00:00', null, null, [['id' => 3]]),
    ]);
    $method = new ReflectionMethod(RouteTrackingController::class, 'summarizeVisitTime');
    $summary = $method->invoke(app(RouteTrackingController::class), $actual, $visits);

    expect($summary['face_time'])->toBe(3300)
        ->and($summary['otp_customer_time'])->toBe(1500)
        ->and($summary['actual_cft'])->toBe(1800)
        ->and($summary['travel_time'])->toBe(3000)
        ->and($summary['stationary_with_customer_seconds'])->toBe(2400)
        ->and($summary['stationary_without_customer_seconds'])->toBe(1800)
        ->and($summary['stationary_periods'][0]['customer_visits'])->toHaveCount(3)
        ->and($summary['stationary_periods'][0]['customer_visits'][0]['stationary_overlap_seconds'])->toBe(1200)
        ->and($summary['stationary_periods'][1]['customer_visits'])->toBe([]);

    $empty = $method->invoke(app(RouteTrackingController::class), ['duration' => null, 'stationary_seconds' => 0, 'stationary_periods' => []], collect());
    expect($empty['actual_cft'])->toBe(0)
        ->and($empty['otp_customer_time'])->toBe(0)
        ->and($empty['travel_time'])->toBeNull()
        ->and($empty['stationary_with_customer_seconds'])->toBe(0)
        ->and($empty['stationary_without_customer_seconds'])->toBe(0);

    $short = $method->invoke(app(RouteTrackingController::class), array_replace($actual, ['duration' => 3600]), $visits);
    expect($short['travel_time'])->toBe(0);
});


test('route face time variance compares planned and actual durations without OTP visits', function () {
    $visits = collect([
        ['visit_duration_minutes' => 30, 'default_face_time_minutes' => 20, 'otp_logs' => []],
        ['visit_duration_minutes' => 45, 'default_face_time_minutes' => 90, 'otp_logs' => [['id' => 1], ['id' => 2]]],
        ['visit_duration_minutes' => null, 'default_face_time_minutes' => 100, 'otp_logs' => []],
    ]);
    $method = new ReflectionMethod(RouteTrackingController::class, 'summarizeVisitTime');
    $result = $method->invoke(app(RouteTrackingController::class), ['duration' => 7200, 'stationary_seconds' => 0, 'stationary_periods' => []], $visits);
    expect($result['actual_cft'])->toEqual(1800)
        ->and($result['planned_cft'])->toEqual(1200)
        ->and($result['face_time_variance_percent'])->toEqual(50);
    $result = $method->invoke(app(RouteTrackingController::class), ['duration' => null, 'stationary_seconds' => 0, 'stationary_periods' => []], collect());
    expect($result['face_time_variance_percent'])->toBeNull();
});
