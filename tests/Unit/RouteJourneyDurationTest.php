<?php

use App\Http\Controllers\Dashboard\DashboardController;
use App\Http\Controllers\RouteTracking\RouteTrackingController;
use App\Services\DashboardAnalysis;
use Illuminate\Support\Facades\DB;

uses(Tests\TestCase::class);

beforeEach(function () {
    config(['database.default' => 'duration_test',
        'database.connections.duration_test' => ['driver' => 'sqlite', 'database' => ':memory:', 'prefix' => ''],
        'database.connections.tracking_pgsql' => ['driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '']]);
    DB::purge('duration_test'); DB::purge('tracking_pgsql');
    DB::statement('CREATE TABLE startendday (routekey integer, routecode integer, salesmancode integer, routestartdate text, routestarttime text, routeenddate text, routeendtime text, routeclosed integer, routestartodometer real, routeendodometer real, versionno text)');
    DB::connection('tracking_pgsql')->statement('CREATE TABLE trac_routetrack (id integer, routecode integer, salesmancode integer, latitude real, longitude real, date text, time text, cdate text)');
    DB::table('startendday')->insert(['routekey' => 1, 'routecode' => 10, 'routestartdate' => '2026-09-10', 'routestarttime' => '08:00:00', 'routeclosed' => 1, 'routeenddate' => '2026-09-11', 'routeendtime' => '01:00:00']);
});

test('closed route duration matches dashboard across midnight even without GPS', function () {
    $journey = DB::table('startendday')->first();
    $expected = app(DashboardAnalysis::class)->journeyTiming($journey);
    $method = new ReflectionMethod(RouteTrackingController::class, 'computeMatchedActual');
    $actual = $method->invoke(app(RouteTrackingController::class), 10, '2026-09-10');
    expect($actual['duration'])->toEqual($expected['duration'] * 60)
        ->and($actual['duration'])->toEqual(17 * 3600)
        ->and($actual['has_tracking_data'])->toBeFalse();
});

test('open route and dashboard use the same reading timestamp before the next journey', function () {
    DB::table('startendday')->where('routekey', 1)->update(['routeclosed' => 0, 'routeenddate' => null, 'routeendtime' => null]);
    DB::table('startendday')->insert(['routekey' => 2, 'routecode' => 10, 'routestartdate' => '2026-09-11', 'routestarttime' => '08:00:00']);
    foreach ([[1, '2026-09-10', '09:00:00'], [2, '2026-09-11', '00:30:00'], [3, '2026-09-11', '09:00:00']] as [$id, $date, $time]) {
        DB::connection('tracking_pgsql')->table('trac_routetrack')->insert(['id' => $id, 'routecode' => 10, 'date' => $date, 'time' => $time, 'cdate' => '2026-09-12 10:00:00']);
    }
    $journeys = DB::table('startendday')->where('routekey', 1)->get();
    $locations = (new ReflectionMethod(DashboardController::class, 'journeyLocations'))->invoke(app(DashboardController::class), $journeys);
    $journeys[0]->last_location_time = $locations[1]->effective_timestamp;
    $dashboard = app(DashboardAnalysis::class)->journeyTiming($journeys[0]);
    $tracking = (new ReflectionMethod(RouteTrackingController::class, 'routeTiming'))->invoke(app(RouteTrackingController::class), 10, '2026-09-10');
    expect($tracking)->toBe($dashboard)
        ->and($tracking['duration'])->toEqual(990);
});

test('open route with no reported readings has unavailable duration', function () {
    DB::table('startendday')->where('routekey', 1)->update(['routeclosed' => 0, 'routeenddate' => null, 'routeendtime' => null]);
    $actual = (new ReflectionMethod(RouteTrackingController::class, 'computeMatchedActual'))->invoke(app(RouteTrackingController::class), 10, '2026-09-10');
    expect($actual['duration'])->toBeNull();
});
