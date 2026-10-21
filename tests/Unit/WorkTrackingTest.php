<?php

use App\Services\WorkTracking;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

uses(Tests\TestCase::class);

beforeEach(function () {
    config(['database.default' => 'work_tracking_test', 'database.connections.work_tracking_test' => ['driver' => 'sqlite', 'database' => ':memory:', 'prefix' => ''],
        'database.connections.tracking_pgsql' => ['driver' => 'sqlite', 'database' => ':memory:', 'prefix' => ''],
        'tracking.timezone' => 'Asia/Muscat', 'tracking.max_accuracy_m' => 50, 'tracking.stationary_max_gap_seconds' => 120]);
    DB::purge('work_tracking_test'); DB::purge('tracking_pgsql');
    CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-10-21 12:00:00', 'Asia/Muscat'));
    DB::statement('CREATE TABLE routemaster (routecode integer, device_assigned_id text)');
    DB::statement('CREATE TABLE startendday (routekey integer, routecode integer, routestartdate text, routeenddate text, dataconrefnumber text)');
    DB::table('routemaster')->insert(['routecode' => 10, 'device_assigned_id' => 'new']);
    DB::table('startendday')->insert(['routekey' => 1, 'routecode' => 10, 'routestartdate' => '2026-10-20', 'routeenddate' => '2026-10-20', 'dataconrefnumber' => 'old']);
    DB::connection('tracking_pgsql')->statement('CREATE TABLE trac_routetrack (id integer, deviceid text, routecode integer, date text, time text, latitude real, longitude real, accuracy_m real)');
});

afterEach(fn () => CarbonImmutable::setTestNow());

test('today includes all GPS times without schedule columns and excludes other devices', function () {
    foreach ([[0, 'new', '00:00:00'], [6, 'new', '23:59:59'], [1, 'new', '07:59:59'], [2, 'new', '08:00:00'], [3, 'old', '09:00:00'], [4, 'new', '17:00:00'], [5, 'new', '17:00:01']] as [$id, $device, $time]) {
        DB::connection('tracking_pgsql')->table('trac_routetrack')->insert(['id' => $id, 'deviceid' => $device, 'routecode' => null, 'date' => '2026-10-21', 'time' => $time, 'latitude' => 23.5, 'longitude' => 58.5]);
    }
    expect(array_column(app(WorkTracking::class)->load(10, '2026-10-21')['points'], 'id'))->toBe([0, 1, 2, 4, 5, 6]);
});

test('historical tracking uses recorded journey device after current assignment changes', function () {
    foreach (['old', 'new'] as $id => $device) DB::connection('tracking_pgsql')->table('trac_routetrack')->insert([
        'id' => $id, 'deviceid' => $device, 'date' => '2026-10-20', 'time' => '09:00:00', 'latitude' => 23.5, 'longitude' => 58.5,
    ]);
    expect(array_column(app(WorkTracking::class)->load(10, '2026-10-20')['points'], 'deviceid'))->toBe(['old']);
    DB::table('startendday')->update(['dataconrefnumber' => null]);
    expect(app(WorkTracking::class)->load(10, '2026-10-20')['available'])->toBeFalse();
});

test('missing historical journey never falls back to currently assigned device', function () {
    expect(app(WorkTracking::class)->load(10, '2026-10-19')['available'])->toBeFalse();
});

test('tracking bounds use observed first and last readings with no scheduled fallback', function () {
    $work = app(WorkTracking::class);
    $points = [(object) ['effective_timestamp' => '2026-10-21 00:01:00'], (object) ['effective_timestamp' => '2026-10-21 23:58:00']];
    $bounds = $work->observedBounds($points);
    expect(date('H:i:s', $bounds['start']))->toBe('00:01:00')->and(date('H:i:s', $bounds['end']))->toBe('23:58:00')
        ->and($work->observedBounds([]))->toBe(['start' => null, 'end' => null]);
});

test('route event markers require nearby measured GPS rather than fabricated coordinates', function () {
    $work = app(WorkTracking::class);
    $point = (object) ['latitude' => 23.5, 'longitude' => 58.5, 'effective_timestamp' => '2026-10-20 09:00:00'];
    expect($work->eventMarker([$point], strtotime('2026-10-20 09:01:00'))['gps_time'])->toBe('2026-10-20 09:00:00')
        ->and($work->eventMarker([$point], strtotime('2026-10-20 10:00:00')))->toBeNull();
});


test('selected date excludes adjacent dates even for the same device', function () {
    foreach (['2026-10-20', '2026-10-21', '2026-10-22'] as $id => $date) DB::connection('tracking_pgsql')->table('trac_routetrack')->insert([
        'id' => $id, 'deviceid' => 'new', 'date' => $date, 'time' => '23:59:00', 'latitude' => 23.5, 'longitude' => 58.5,
    ]);
    $tracking = app(WorkTracking::class)->load(10, '2026-10-21');
    expect(array_column($tracking['points'], 'id'))->toBe([1])->and($tracking['start'])->toBe($tracking['end']);
});


test('before after toggle retains isolated GPS points and does not bridge reporting gaps', function () {
    $points = array_map(fn ($time) => (object) ['effective_timestamp' => '2026-10-21 '.$time, 'latitude' => 23.5, 'longitude' => 58.5], ['07:00:00', '08:00:00', '17:00:00', '18:00:00', '18:10:00']);
    $geometries = app(WorkTracking::class)->outsideGeometries($points, strtotime('2026-10-21 08:00:00'), strtotime('2026-10-21 17:00:00'));
    expect(array_column($geometries, 'type'))->toBe(['Point', 'Point', 'Point']);
    expect(app(WorkTracking::class)->outsideGeometries(array_slice($points, 1, 2), strtotime('2026-10-21 08:00:00'), strtotime('2026-10-21 17:00:00')))->toBe([]);
});
