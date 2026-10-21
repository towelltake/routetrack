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
    DB::statement('CREATE TABLE routemaster (routecode integer, device_assigned_id text, autojp_work_start_time text, autojp_work_end_time text)');
    DB::statement('CREATE TABLE startendday (routekey integer, routecode integer, routestartdate text, routeenddate text, dataconrefnumber text)');
    DB::table('routemaster')->insert(['routecode' => 10, 'device_assigned_id' => 'new', 'autojp_work_start_time' => '08:00:00', 'autojp_work_end_time' => '17:00:00']);
    DB::table('startendday')->insert(['routekey' => 1, 'routecode' => 10, 'routestartdate' => '2026-10-20', 'routeenddate' => '2026-10-20', 'dataconrefnumber' => 'old']);
    DB::connection('tracking_pgsql')->statement('CREATE TABLE trac_routetrack (id integer, deviceid text, routecode integer, date text, time text, latitude real, longitude real, accuracy_m real)');
});

afterEach(fn () => CarbonImmutable::setTestNow());

test('today tracks assigned device before route start and excludes other devices and outside work hours', function () {
    foreach ([[1, 'new', '07:59:59'], [2, 'new', '08:00:00'], [3, 'old', '09:00:00'], [4, 'new', '17:00:00'], [5, 'new', '17:00:01']] as [$id, $device, $time]) {
        DB::connection('tracking_pgsql')->table('trac_routetrack')->insert(['id' => $id, 'deviceid' => $device, 'routecode' => null, 'date' => '2026-10-21', 'time' => $time, 'latitude' => 23.5, 'longitude' => 58.5]);
    }
    expect(array_column(app(WorkTracking::class)->load(10, '2026-10-21')['points'], 'id'))->toBe([2, 4]);
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

test('overnight work window crosses midnight and malformed or zero length hours are unavailable', function () {
    $work = app(WorkTracking::class);
    $window = $work->window('2026-10-20', '22:00:00', '06:00:00');
    expect(date('Y-m-d H:i:s', $window['end']))->toBe('2026-10-21 06:00:00')
        ->and($work->window('2026-10-20', '08:00', '08:00'))->toBeNull()
        ->and($work->window('2026-10-20', null, '17:00'))->toBeNull()
        ->and($work->window('2026-10-20', '28:00', '17:00'))->toBeNull();
});

test('route event markers require nearby measured GPS rather than fabricated coordinates', function () {
    $work = app(WorkTracking::class);
    $point = (object) ['latitude' => 23.5, 'longitude' => 58.5, 'effective_timestamp' => '2026-10-20 09:00:00'];
    expect($work->eventMarker([$point], strtotime('2026-10-20 09:01:00'))['gps_time'])->toBe('2026-10-20 09:00:00')
        ->and($work->eventMarker([$point], strtotime('2026-10-20 10:00:00')))->toBeNull();
});
