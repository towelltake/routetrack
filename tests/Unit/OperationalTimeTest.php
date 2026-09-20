<?php

use App\Services\OperationalTime;

test('operational time spans first to last non OTP visits including gaps and intervening OTP visits', function () {
    $visits = [];
    foreach ([[1, '08:00:00', '08:30:00'], [2, '09:00:00', '09:10:00'], [3, '10:00:00', '10:30:00'], [4, '11:00:00', '11:20:00'], [5, '12:00:00', '12:30:00']] as [$id, $start, $end]) {
        $visits[] = (object) ['routekey' => 1, 'logkey' => $id, 'customercode' => $id,
            'logstartdate' => '2026-09-20', 'logstarttime' => $start, 'logenddate' => '2026-09-20', 'logendtime' => $end];
    }
    $otp = ['1:1' => [['id' => 1]], '1:3' => [['id' => 2]], '1:5' => [['id' => 3]]];
    $service = new OperationalTime();
    $dashboard = $service->fromLogs($visits, $otp);
    $tracking = array_map(fn ($visit) => [
        'customercode' => $visit->customercode,
        'visit_start_date' => $visit->logstartdate, 'visit_start_time' => $visit->logstarttime,
        'visit_end_date' => $visit->logenddate, 'visit_end_time' => $visit->logendtime,
        'operational_otp' => isset($otp['1:'.$visit->logkey]),
    ], $visits);
    expect($dashboard)->toEqual([
        'start' => '2026-09-20 09:00:00', 'end' => '2026-09-20 11:20:00',
        'minutes' => 140, 'first_customer' => 2, 'last_customer' => 4,
    ])->and($service->fromTracking(array_reverse($tracking)))->toBe($dashboard);
});

test('operational time handles overnight visits and does not fall back from an unfinished final customer', function () {
    $service = new OperationalTime();
    $visits = [
        ['customercode' => 1, 'visit_start_date' => '2026-09-20', 'visit_start_time' => '23:00:00', 'visit_end_date' => '2026-09-20', 'visit_end_time' => '23:10:00'],
        ['customercode' => 2, 'visit_start_date' => '2026-09-21', 'visit_start_time' => '01:00:00', 'visit_end_date' => '2026-09-21', 'visit_end_time' => '01:30:00'],
    ];
    expect($service->fromTracking($visits)['minutes'])->toEqual(150);
    $visits[1]['visit_end_time'] = null;
    expect($service->fromTracking($visits)['minutes'])->toBeNull();
    $visits[0]['operational_otp'] = true;
    $visits[1]['operational_otp'] = true;
    expect($service->fromTracking($visits)['minutes'])->toBeNull()
        ->and($service->fromTracking([])['minutes'])->toBeNull();
});
