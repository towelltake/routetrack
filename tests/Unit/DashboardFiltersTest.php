<?php

use App\Http\Controllers\Dashboard\DashboardController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

uses(Tests\TestCase::class);

test('route started card counts filtered route days inclusively without duplicate starts', function () {
    $this->mock(\App\Services\DashboardMetrics::class, function ($mock) {
        $mock->shouldReceive('summarize')->andReturn([]);
    });
    DB::table('startendday')->insert([
        ['routecode' => 1, 'routekey' => 100, 'routestartdate' => '2026-09-07'],
        ['routecode' => 1, 'routekey' => 101, 'routestartdate' => '2026-09-08'],
    ]);
    $controller = app(DashboardController::class);
    $result = $controller->metrics(Request::create('/', 'GET', [
        'from_date' => '2026-09-07', 'to_date' => '2026-09-08', 'divisions' => [1],
    ]))->getData(true);
    expect($result)->toMatchArray([
        'route_count' => 3, 'period_days' => 2, 'total_routes' => 6,
        'routes_started' => 4, 'routes_not_started' => 2,
    ]);
    $single = $controller->metrics(Request::create('/', 'GET', [
        'date' => '2026-09-07', 'routes' => [7],
    ]))->getData(true);
    expect($single)->toMatchArray(['route_count' => 1, 'period_days' => 1, 'total_routes' => 1, 'routes_started' => 1]);
});

beforeEach(function () {
    config(['database.default' => 'dashboard_test', 'database.connections.dashboard_test' => [
        'driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '',
    ], 'database.connections.tracking_pgsql' => [
        'driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '',
    ]]);
    DB::purge('dashboard_test');
    DB::purge('tracking_pgsql');

    foreach ([
        'company (cmpycode integer, name text, entity text, clustercode integer, activestatus integer)',
        'clustermaster (clustercode integer, clustername text)',
        'regionmaster (regionmstcode integer, regionmstname text)',
        'routemaster (routecode integer, routename text, cmpycode integer, regionmstcode integer, subareacode integer)',
        'routesequence (routecode integer)',
        'salesman (salesmancode integer, salesmanname1 text)',
        'startendday (routecode integer, routekey integer, routestartdate text, routestarttime text, routeenddate text, routeendtime text, routeclosed integer)',
    ] as $table) DB::statement('CREATE TABLE '.$table);

    DB::table('company')->insert([
        ['cmpycode' => 1, 'name' => 'Division A', 'entity' => 'Entity A', 'clustercode' => 10, 'activestatus' => 1],
        ['cmpycode' => 2, 'name' => 'Division B', 'entity' => 'Entity B', 'clustercode' => 20, 'activestatus' => 1],
        ['cmpycode' => 3, 'name' => 'Inactive', 'entity' => 'Entity C', 'clustercode' => 20, 'activestatus' => 0],
    ]);
    DB::table('clustermaster')->insert([
        ['clustercode' => 10, 'clustername' => 'Cluster A'], ['clustercode' => 20, 'clustername' => 'Cluster B'],
    ]);
    DB::table('regionmaster')->insert([
        ['regionmstcode' => 100, 'regionmstname' => 'North'], ['regionmstcode' => 200, 'regionmstname' => 'South'],
    ]);
    foreach ([[1, 1, 100, 1], [2, 1, 200, 1], [3, 2, 100, 1], [4, 3, 100, 1], [5, 2, 100, 1], [6, 1, 100, 99], [7, 1, 100, 1]] as [$code, $company, $region, $subarea]) {
        DB::table('routemaster')->insert(['routecode' => $code, 'routename' => 'Route '.$code, 'cmpycode' => $company, 'regionmstcode' => $region, 'subareacode' => $subarea]);
        if ($code !== 7) DB::table('routesequence')->insert(['routecode' => $code]);
    }
    session(['user_access' => ['route_codes' => [1, 2, 3, 4, 6, 7], 'company_codes' => [1, 2, 3], 'subarea_codes' => [1]]]);
    DB::connection('tracking_pgsql')->statement('CREATE TABLE trac_routetrack (id integer, routecode integer, salesmancode integer, latitude real, longitude real, cdate text, date text, time text)');
    foreach (range(1, 7) as $code) {
        DB::table('startendday')->insert(['routecode' => $code, 'routekey' => $code, 'routestartdate' => '2026-09-07', 'routestarttime' => '08:00:00', 'routeclosed' => 0]);
        DB::connection('tracking_pgsql')->table('trac_routetrack')->insert([
            'id' => $code, 'routecode' => $code, 'salesmancode' => 1, 'latitude' => 23.5, 'longitude' => 58.5,
            'cdate' => '2026-09-07 10:00:00', 'date' => '2026-09-07', 'time' => '10:00:00',
        ]);
    }
});

test('dashboard catalog respects active divisions and all existing access restrictions', function () {
    $rows = app(DashboardController::class)->filters()->getData(true);
    expect(array_column($rows, 'routecode'))->toBe([1, 2, 3]);
    expect($rows[0])->toMatchArray(['entity' => 'Entity A', 'clustercode' => 10, 'regionmstcode' => 100]);
    session(['user_access.company_codes' => [2]]);
    expect(array_column(app(DashboardController::class)->filters()->getData(true), 'routecode'))->toBe([3]);
});

test('dashboard filters combine dimensions and allow multiple values without a required division', function (array $filters, array $expected) {
    $request = Request::create('/', 'GET', ['date' => '2026-09-07', ...$filters]);
    $rows = app(DashboardController::class)->lastLocations($request)->getData(true);
    $codes = array_column($rows, 'routecode');
    sort($codes);
    expect($codes)->toBe($expected);
})->with([
    'all accessible routes' => [[], [1, 2, 3]],
    'region alone across divisions' => [['regions' => [100]], [1, 3]],
    'multiple divisions and region' => [['divisions' => [1, 2], 'regions' => [100]], [1, 3]],
    'entity and cluster and region' => [['entities' => ['Entity A'], 'clusters' => [10], 'regions' => [200]], [2]],
    'multiple routes' => [['routes' => [2, 3]], [2, 3]],
    'conflicting dimensions' => [['entities' => ['Entity A'], 'clusters' => [20]], []],
    'unauthorized route' => [['routes' => [5]], []],
    'inactive division' => [['divisions' => [3]], []],
]);

test('dashboard rejects malformed multi-select inputs', function () {
    app(DashboardController::class)->lastLocations(Request::create('/', 'GET', [
        'date' => '2026-09-07', 'regions' => ['not-a-code'],
    ]));
})->throws(ValidationException::class);

test('metric endpoint passes all and only authorized journeys started in the period', function () {
    DB::table('startendday')->insert(['routekey' => 99, 'routecode' => 1, 'routestartdate' => '2026-09-06', 'routestarttime' => '08:00:00', 'routeclosed' => 1]);
    DB::table('startendday')->insert(['routekey' => 100, 'routecode' => 1, 'routestartdate' => '2026-09-05', 'routestarttime' => '08:00:00', 'routeclosed' => 0]);
    $service = Mockery::mock(App\Services\DashboardMetrics::class);
    $service->shouldReceive('summarize')->once()->withArgs(function ($journeys) {
        expect($journeys->pluck('routekey')->all())->toEqualCanonicalizing([1, 2, 3, 7, 99]);
        return true;
    })->andReturn(['journeys_started' => 5]);
    app()->instance(App\Services\DashboardMetrics::class, $service);
    $response = app(DashboardController::class)->metrics(Request::create('/', 'GET', ['from_date' => '2026-09-06', 'to_date' => '2026-09-07']));
    expect($response->getData(true))->toMatchArray(['journeys_started' => 5, 'routes_started' => 5, 'total_routes' => 8]);
});

test('dashboard selects journeys by inclusive start date, not GPS date or end date', function () {
    DB::table('startendday')->where('routecode', 1)->update(['routestartdate' => '2026-09-01']);
    DB::table('startendday')->where('routecode', 2)->update(['routestartdate' => '2026-08-31', 'routeenddate' => '2026-09-07']);
    $rows = app(DashboardController::class)->lastLocations(Request::create('/', 'GET', [
        'from_date' => '2026-09-01', 'to_date' => '2026-09-07',
    ]))->getData(true);
    expect(array_column($rows, 'routecode'))->toEqualCanonicalizing([1, 3]);
    expect(collect($rows)->firstWhere('routecode', 1)['route_date'])->toBe('2026-09-01');
});

test('dashboard excludes GPS from later journeys and includes overnight journey GPS', function () {
    DB::table('startendday')->where('routecode', 1)->update([
        'routestartdate' => '2026-09-06', 'routeenddate' => '2026-09-07', 'routeendtime' => '11:00:00', 'routeclosed' => 1,
    ]);
    DB::table('startendday')->insert(['routecode' => 1, 'routekey' => 99, 'routestartdate' => '2026-09-08', 'routestarttime' => '08:00:00', 'routeclosed' => 0]);
    DB::connection('tracking_pgsql')->table('trac_routetrack')->insert([
        'id' => 99, 'routecode' => 1, 'salesmancode' => 1, 'latitude' => 24, 'longitude' => 58,
        'date' => '2026-09-08', 'time' => '10:00:00', 'cdate' => '2026-09-08 10:00:00',
    ]);
    $rows = app(DashboardController::class)->lastLocations(Request::create('/', 'GET', [
        'from_date' => '2026-09-06', 'to_date' => '2026-09-06',
    ]))->getData(true);
    expect($rows)->toHaveCount(1)->and($rows[0]['routekey'])->toBe(1)->and($rows[0]['time'])->toBe('2026-09-07 10:00:00');
    DB::table('startendday')->where('routekey', 1)->update(['routeclosed' => 0]);
    $rows = app(DashboardController::class)->lastLocations(Request::create('/', 'GET', [
        'from_date' => '2026-09-06', 'to_date' => '2026-09-06',
    ]))->getData(true);
    expect($rows[0]['time'])->toBe('2026-09-07 10:00:00');
    $rows = app(DashboardController::class)->lastLocations(Request::create('/', 'GET', [
        'from_date' => '2026-09-06', 'to_date' => '2026-09-08', 'routes' => [1],
    ]))->getData(true);
    expect($rows)->toHaveCount(1)->and($rows[0]['routekey'])->toBe(99)
        ->and($rows[0]['route_date'])->toBe('2026-09-08');
});

test('dashboard rejects invalid date ranges', function (array $dates) {
    app(DashboardController::class)->lastLocations(Request::create('/', 'GET', $dates));
})->with([
    [['from_date' => '2026-09-08', 'to_date' => '2026-09-07']],
    [['from_date' => '2026-09-01']],
    [['to_date' => '2026-09-07']],
    [['from_date' => '2026-02-30', 'to_date' => '2026-09-07']],
])->throws(ValidationException::class);
