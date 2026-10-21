<?php

use App\Services\DashboardOutsideVisits;
use Illuminate\Support\Facades\DB;

uses(Tests\TestCase::class);

beforeEach(function () {
    config(['database.default' => 'outside_test', 'database.connections.outside_test' => ['driver' => 'sqlite', 'database' => ':memory:', 'prefix' => ''],
        'database.connections.tracking_pgsql' => ['driver' => 'sqlite', 'database' => ':memory:', 'prefix' => ''],
        'tracking.stationary_minutes' => 5, 'tracking.stationary_radius_m' => 30, 'tracking.stationary_max_gap_seconds' => 120, 'tracking.max_accuracy_m' => 50]);
    DB::purge('outside_test'); DB::purge('tracking_pgsql');
    DB::statement("CREATE TABLE startendday (routekey integer, routecode integer, routestartdate text, routestarttime text, routeenddate text, routeendtime text, routeclosed integer, dataconrefnumber text default 'old-device')");
    DB::statement('CREATE TABLE customervisitlog (customercode integer default 1, routekey integer, logstartdate text, logstarttime text, logenddate text, logendtime text)');
    DB::statement("CREATE TABLE routemaster (routecode integer, device_assigned_id text, autojp_work_start_time text, autojp_work_end_time text)");
    DB::table('routemaster')->insert(['routecode' => 10, 'device_assigned_id' => 'new-device', 'autojp_work_start_time' => '08:00:00', 'autojp_work_end_time' => '09:00:00']);
    DB::statement('CREATE TABLE customermaster (customercode integer, activecustomer integer)');
    DB::table('customermaster')->insert(['customercode' => 1, 'activecustomer' => 1]);
    // Legacy devices have no accuracy/provider columns.
    DB::connection('tracking_pgsql')->statement("CREATE TABLE trac_routetrack (id integer, routecode integer, date text, time text, latitude real, longitude real, deviceid text default 'old-device')");
    DB::table('startendday')->insert(['routekey' => 1, 'routecode' => 10, 'routestartdate' => '2026-09-09', 'routestarttime' => '08:00:00', 'routeenddate' => '2026-09-09', 'routeendtime' => '09:00:00', 'routeclosed' => 1]);
});

function outsideJourney(): \Illuminate\Support\Collection
{
    return DB::table('startendday')->where('routekey', 1)->get()->each(function ($row) { $row->salesman = 'Salesman'; });
}

test('inactive customers do not contribute visit time to the idle calculation', function () {
    DB::table('customervisitlog')->insert([
        'routekey' => 1, 'logstartdate' => '2026-09-09', 'logstarttime' => '08:10:00',
        'logenddate' => '2026-09-09', 'logendtime' => '08:30:00',
    ]);
    expect(app(DashboardOutsideVisits::class)->build(outsideJourney())[0]['customer_cft'])->toEqual(20);
    DB::table('customermaster')->update(['activecustomer' => 0]);
    expect(app(DashboardOutsideVisits::class)->build(outsideJourney())[0]['customer_cft'])->toEqual(0);
});

test('outside visit times merge overlap and subtract only the stationary portion outside visits', function () {
    foreach ([['08:10:00', '08:25:00'], ['08:20:00', '08:30:00']] as [$start, $end]) DB::table('customervisitlog')->insert([
        'routekey' => 1, 'logstartdate' => '2026-09-09', 'logstarttime' => $start, 'logenddate' => '2026-09-09', 'logendtime' => $end,
    ]);
    for ($minute = 0; $minute <= 40; $minute++) DB::connection('tracking_pgsql')->table('trac_routetrack')->insert([
        'id' => $minute, 'routecode' => 10, 'date' => '2026-09-09', 'time' => sprintf('08:%02d:00', $minute), 'latitude' => 23.5, 'longitude' => 58.5,
    ]);
    $row = app(DashboardOutsideVisits::class)->build(outsideJourney())[0];
    expect($row)->toMatchArray(['customer_cft' => 20, 'stationary' => 20, 'travel' => null, 'status' => 'Work window']);
});

test('open visits stop at last GPS and absent GPS does not fabricate stationary or travel time', function () {
    $journeys = outsideJourney();
    $journeys[0]->routeclosed = 0;
    $journeys[0]->last_location_time = '2026-09-09 08:40:00';
    DB::table('customervisitlog')->insert(['routekey' => 1, 'logstartdate' => '2026-09-09', 'logstarttime' => '08:10:00']);
    $row = app(DashboardOutsideVisits::class)->build($journeys)[0];
    expect($row)->toMatchArray(['customer_cft' => 0, 'stationary' => null, 'travel' => null, 'end' => '2026-09-09 09:00:00', 'status' => 'Work window']);
    $journeys[0]->last_location_time = null;
    expect(app(DashboardOutsideVisits::class)->build($journeys)[0])->toMatchArray(['customer_cft' => 0, 'stationary' => null, 'travel' => null]);
});

test('reporting gaps break stops while the full work window includes later journey tracking', function () {
    DB::table('startendday')->insert(['routekey' => 2, 'routecode' => 10, 'routestartdate' => '2026-09-09', 'routestarttime' => '08:30:00']);
    foreach (array_merge(range(0, 5), range(20, 25), range(30, 40)) as $minute) DB::connection('tracking_pgsql')->table('trac_routetrack')->insert([
        'id' => $minute, 'routecode' => 10, 'date' => '2026-09-09', 'time' => sprintf('08:%02d:00', $minute), 'latitude' => 23.5, 'longitude' => 58.5,
    ]);
    expect(app(DashboardOutsideVisits::class)->build(outsideJourney())[0]['stationary'])->toEqual(20);
});

test('idle details retain stationary minutes and unavailable journeys in the customer popup', function () {
    $journeys = outsideJourney()->each(function ($row) { $row->routename = 'Test route'; });
    for ($minute = 0; $minute <= 10; $minute++) DB::connection('tracking_pgsql')->table('trac_routetrack')->insert([
        'id' => $minute, 'routecode' => 10, 'date' => '2026-09-09', 'time' => sprintf('08:%02d:00', $minute), 'latitude' => 23.5, 'longitude' => 58.5,
    ]);
    DB::table('customervisitlog')->insert(['routekey' => 1, 'logstartdate' => '2026-09-09', 'logstarttime' => '08:02:00', 'logenddate' => '2026-09-09', 'logendtime' => '08:07:00']);
    $result = app(\App\Services\DashboardCustomerDetails::class)->build($journeys, 'idle');
    expect($result['groups'][0]['rows'][0]['stationary'])->toEqual(5);
    DB::connection('tracking_pgsql')->table('trac_routetrack')->delete();
    $result = app(\App\Services\DashboardCustomerDetails::class)->build($journeys, 'idle');
    expect($result['groups'][0]['rows'][0]['stationary'])->toBeNull();
});


test('idle covers tracking before and after route events and repeated journeys count one workday', function () {
    DB::table('startendday')->where('routekey', 1)->update(['routestarttime' => '08:15:00', 'routeendtime' => '08:25:00']);
    DB::table('startendday')->insert(['routekey' => 2, 'routecode' => 10, 'routestartdate' => '2026-09-09', 'routestarttime' => '08:40:00', 'routeenddate' => '2026-09-09', 'routeendtime' => '08:45:00', 'routeclosed' => 1]);
    for ($minute = 0; $minute <= 50; $minute++) DB::connection('tracking_pgsql')->table('trac_routetrack')->insert([
        'id' => $minute, 'routecode' => null, 'date' => '2026-09-09', 'time' => sprintf('08:%02d:00', $minute), 'latitude' => 23.5, 'longitude' => 58.5,
    ]);
    $rows = app(DashboardOutsideVisits::class)->build(DB::table('startendday')->get());
    expect($rows)->toHaveCount(1)->and($rows[0]['stationary'])->toEqual(50)
        ->and($rows[0]['start'])->toBe('2026-09-09 08:00:00')->and($rows[0]['end'])->toBe('2026-09-09 09:00:00');
});
