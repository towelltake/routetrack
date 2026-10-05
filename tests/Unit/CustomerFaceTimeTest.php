<?php

use App\Http\Controllers\RouteTracking\RouteTrackingController;
use Illuminate\Support\Facades\DB;

uses(Tests\TestCase::class);

test('dashboard and tracking resolve customer CFT before division and channel fallback', function ($masterCft, $mappingCft, int $expected, bool $missingMapping = false) {
    config(['database.default' => 'cft_test', 'database.connections.cft_test' => [
        'driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '',
    ]]);
    DB::purge('cft_test');

    foreach ([
        'customermaster (customerfacetime integer default 0, DivisionCode text, channel text, customercode integer, activecustomer integer default 1, customeraddress1 text, alternatecode text, fixedlatitude real, fixedlongitude real, toplpo integer)',
        'customerclustermapping (divisioncode text, channel text, cft integer, UNIQUE (divisioncode, channel))',
        'customervisitlog (logkey integer, customercode integer, routekey integer, logstartdate text, logstarttime text, logenddate text, logendtime text, cft integer)',
        'customeroperationscontrol (primary_id integer, routekey integer, log_id integer, visitkey integer, latitude real, longitude real)',
    ] as $table) {
        DB::statement('CREATE TABLE '.$table);
    }
    DB::table('customermaster')->insert([
        'customercode' => 1,
        'toplpo' => 1, 'customerfacetime' => $masterCft, 'DivisionCode' => 'D1', 'channel' => 'Retail',
        'fixedlatitude' => 23.5, 'fixedlongitude' => 58.5,
    ]);
    DB::table('customerclustermapping')->insert([
        ['divisioncode' => 'D1', 'channel' => 'Retail', 'cft' => $mappingCft],
        ['divisioncode' => 'D2', 'channel' => 'Retail', 'cft' => 88],
        ['divisioncode' => 'D1', 'channel' => 'Wholesale', 'cft' => 99],
    ]);
    if ($missingMapping) {
        DB::table('customerclustermapping')->where('divisioncode', 'D1')->where('channel', 'Retail')->delete();
    }
    DB::table('customervisitlog')->insert([
        'logkey' => 1, 'customercode' => 1, 'routekey' => 100,
        'logstartdate' => '2026-09-07 00:00:00', 'logstarttime' => '10:00:00',
        'logenddate' => '2026-09-07 00:00:00', 'logendtime' => '10:18:45',
        'cft' => 999,
    ]);

    $method = new ReflectionMethod(RouteTrackingController::class, 'fetchCustomerVisits');
    $visits = $method->invoke(app(RouteTrackingController::class), 100, 1);
    expect($visits)->toHaveCount(1)
        ->and($visits->first()['toplpo'])->toBe(1)
        ->and($visits->first()['default_face_time_minutes'])->toBe($expected)
        ->and($visits->first()['visit_duration_minutes'])->toBe(18.75);
    $time = (new ReflectionMethod(RouteTrackingController::class, 'summarizeVisitTime'))->invoke(
        app(RouteTrackingController::class), ['duration' => 3600, 'stationary_seconds' => 0, 'stationary_periods' => []], $visits,
    );
    $journey = (object) ['routekey' => 100, 'routecode' => 1, 'routestartdate' => '2026-09-07', 'routename' => 'Route', 'salesman' => 'Salesman'];
    $this->mock(\App\Services\DashboardMetrics::class, fn ($mock) => $mock->shouldReceive('otp')->once()->andReturn(['by_visit' => []]));
    $dashboard = app(\App\Services\DashboardCustomerDetails::class)->build(collect([$journey]), 'cft');
    expect($time['face_time'])->toEqual(1125)
        ->and($dashboard['groups'][0]['rows']->sum('actual_cft'))->toEqual($time['face_time'] / 60)
        ->and($dashboard['groups'][0]['rows']->sum('planned_cft'))->toEqual($expected)
        ->and($time['planned_cft'])->toEqual($expected * 60);

})->with([
    'customer override wins' => [12, 20, 12],
    'null override uses matching mapping' => [null, 20, 20],
    'zero override uses matching mapping' => [0, 15, 15],
    'negative override uses matching mapping' => [-1, 15, 15],
    'no positive target' => [0, null, 0],
    'negative mapping is unavailable' => [0, -5, 0],
    'neither partial mapping matches both keys' => [0, 20, 0, true],
    'customer override does not require mapping' => [12, 20, 12, true],
]);

test('planned face time totals targets for every planned customer', function () {
    config(['database.default' => 'planned_cft_test', 'database.connections.planned_cft_test' => [
        'driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '',
    ]]);
    DB::purge('planned_cft_test');
    DB::statement('CREATE TABLE customermaster (customerfacetime integer default 0, DivisionCode text, channel text, customercode integer, activecustomer integer)');
    DB::statement('CREATE TABLE customerclustermapping (divisioncode text, channel text, cft integer)');
    foreach ([1, 2, 3, 99] as $code) DB::table('customermaster')->insert(['customercode' => $code, 'activecustomer' => 1, 'customerfacetime' => [1 => 15, 2 => 20][$code] ?? 0]);
    DB::statement('CREATE TABLE customervisitlog (routekey integer, customercode integer, cft integer)');
    DB::table('customervisitlog')->insert([
        ['routekey' => 100, 'customercode' => 1, 'cft' => 15],
        ['routekey' => 100, 'customercode' => 2, 'cft' => 20],
        ['routekey' => 100, 'customercode' => 3, 'cft' => null],
        ['routekey' => 200, 'customercode' => 1, 'cft' => 99],
        ['routekey' => 100, 'customercode' => 99, 'cft' => 99],
    ]);

    $method = new ReflectionMethod(RouteTrackingController::class, 'plannedFaceTimeSeconds');
    $seconds = $method->invoke(app(RouteTrackingController::class), 100, collect([
        (object) ['customercode' => 1],
        (object) ['customercode' => 2],
        (object) ['customercode' => 3],
    ]));

    expect($seconds)->toBe(35 * 60);
});
