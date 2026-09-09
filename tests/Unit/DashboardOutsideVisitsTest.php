<?php

use App\Services\DashboardOutsideVisits;
use Illuminate\Support\Facades\DB;

uses(Tests\TestCase::class);

beforeEach(function () {
    config(['database.default' => 'outside_test', 'database.connections.outside_test' => ['driver' => 'sqlite', 'database' => ':memory:', 'prefix' => ''],
        'database.connections.tracking_pgsql' => ['driver' => 'sqlite', 'database' => ':memory:', 'prefix' => ''],
        'tracking.stationary_minutes' => 5, 'tracking.stationary_radius_m' => 30, 'tracking.stationary_max_gap_seconds' => 120, 'tracking.max_accuracy_m' => 50]);
    DB::purge('outside_test'); DB::purge('tracking_pgsql');
    DB::statement('CREATE TABLE startendday (routekey integer, routecode integer, routestartdate text, routestarttime text, routeenddate text, routeendtime text, routeclosed integer)');
    DB::statement('CREATE TABLE customervisitlog (routekey integer, logstartdate text, logstarttime text, logenddate text, logendtime text)');
    // Legacy devices have no accuracy/provider columns.
    DB::connection('tracking_pgsql')->statement('CREATE TABLE trac_routetrack (id integer, routecode integer, date text, time text, latitude real, longitude real)');
    DB::table('startendday')->insert(['routekey' => 1, 'routecode' => 10, 'routestartdate' => '2026-09-09', 'routestarttime' => '08:00:00', 'routeenddate' => '2026-09-09', 'routeendtime' => '09:00:00', 'routeclosed' => 1]);
});

function outsideJourney(): \Illuminate\Support\Collection
{
    return DB::table('startendday')->where('routekey', 1)->get()->each(function ($row) { $row->salesman = 'Salesman'; });
}

test('outside visit times merge overlap and subtract only the stationary portion outside visits', function () {
    foreach ([['08:10:00', '08:25:00'], ['08:20:00', '08:30:00']] as [$start, $end]) DB::table('customervisitlog')->insert([
        'routekey' => 1, 'logstartdate' => '2026-09-09', 'logstarttime' => $start, 'logenddate' => '2026-09-09', 'logendtime' => $end,
    ]);
    for ($minute = 0; $minute <= 40; $minute++) DB::connection('tracking_pgsql')->table('trac_routetrack')->insert([
        'id' => $minute, 'routecode' => 10, 'date' => '2026-09-09', 'time' => sprintf('08:%02d:00', $minute), 'latitude' => 23.5, 'longitude' => 58.5,
    ]);
    $row = app(DashboardOutsideVisits::class)->build(outsideJourney())[0];
    expect($row)->toMatchArray(['customer_cft' => 20, 'stationary' => 20, 'travel' => 20, 'status' => 'Closed']);
});

test('open visits stop at last GPS and absent GPS does not fabricate stationary or travel time', function () {
    $journeys = outsideJourney();
    $journeys[0]->routeclosed = 0;
    $journeys[0]->last_location_time = '2026-09-09 08:40:00';
    DB::table('customervisitlog')->insert(['routekey' => 1, 'logstartdate' => '2026-09-09', 'logstarttime' => '08:10:00']);
    $row = app(DashboardOutsideVisits::class)->build($journeys)[0];
    expect($row)->toMatchArray(['customer_cft' => 30, 'stationary' => null, 'travel' => null, 'end' => '2026-09-09 08:40:00', 'status' => 'Open']);
    $journeys[0]->last_location_time = null;
    expect(app(DashboardOutsideVisits::class)->build($journeys)[0])->toMatchArray(['customer_cft' => null, 'stationary' => null, 'travel' => null]);
});

test('reporting gaps and points belonging to the next journey do not inflate stationary time', function () {
    DB::table('startendday')->insert(['routekey' => 2, 'routecode' => 10, 'routestartdate' => '2026-09-09', 'routestarttime' => '08:30:00']);
    foreach (array_merge(range(0, 5), range(20, 25), range(30, 40)) as $minute) DB::connection('tracking_pgsql')->table('trac_routetrack')->insert([
        'id' => $minute, 'routecode' => 10, 'date' => '2026-09-09', 'time' => sprintf('08:%02d:00', $minute), 'latitude' => 23.5, 'longitude' => 58.5,
    ]);
    expect(app(DashboardOutsideVisits::class)->build(outsideJourney())[0]['stationary'])->toEqual(10);
});
