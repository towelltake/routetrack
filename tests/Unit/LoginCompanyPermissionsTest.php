<?php

use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Requests\Auth\LoginRequest;
use Illuminate\Support\Facades\DB;

uses(Tests\TestCase::class);

beforeEach(function () {
    config(['database.connections.sfa_mysql' => ['driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '']]);
    DB::purge('sfa_mysql');
    $db = DB::connection('sfa_mysql');
    foreach ([
        'useraccesscodes (username text, cmpycode integer, countrycode integer, regionmstcode integer, depotcode integer, areacode integer, subareacode integer)',
        'routemaster (routecode integer, cmpycode integer, subareacode integer, regionmstcode integer)',
        'company (cmpycode integer, countrycode integer)',
        'subareamaster (subareacode integer, areacode integer)',
        'areamaster (areacode integer, depotcode integer)',
        'depotmaster (depotcode integer, regionmstcode integer)',
        'regionmaster (regionmstcode integer, countrycode integer)',
        'country (countrycode integer)',
    ] as $table) $db->statement('CREATE TABLE '.$table);
    $db->table('country')->insert(['countrycode' => 1]);
    $db->table('company')->insert([['cmpycode' => 10, 'countrycode' => 1], ['cmpycode' => 20, 'countrycode' => 2]]);
    $db->table('regionmaster')->insert(['regionmstcode' => 1, 'countrycode' => 1]);
    $db->table('depotmaster')->insert(['depotcode' => 1, 'regionmstcode' => 1]);
    $db->table('areamaster')->insert(['areacode' => 1, 'depotcode' => 1]);
    $db->table('subareamaster')->insert([['subareacode' => 1, 'areacode' => 1], ['subareacode' => 2, 'areacode' => 1]]);
    foreach ([[1, 10, 1], [2, 20, 1], [3, 10, 2], [4, 20, 2]] as [$route, $company, $subarea]) $db->table('routemaster')->insert(['routecode' => $route, 'cmpycode' => $company, 'subareacode' => $subarea, 'regionmstcode' => 1]);
});

test('login authorizes only explicitly granted companies', function ($permissions, $expectedRoutes, $expectedCompanies) {
    $db = DB::connection('sfa_mysql');
    foreach ($permissions as $permission) $db->table('useraccesscodes')->insert(['username' => 'permitted-user'] + $permission);
    $db->table('useraccesscodes')->insert(['username' => 'someone-else', 'cmpycode' => 20]);
    $request = Mockery::mock(LoginRequest::class)->makePartial();
    $request->shouldReceive('authenticate')->once();
    $request->setUserResolver(fn () => (object) ['username' => 'permitted-user', 'accesstypeid' => 1]);
    $request->setLaravelSession(app('session')->driver());
    app(AuthenticatedSessionController::class)->store($request);
    expect(session('user_access.route_codes'))->toEqualCanonicalizing($expectedRoutes)
        ->and(session('user_access.company_codes'))->toEqualCanonicalizing($expectedCompanies);
})->with([
    'company only' => [[['cmpycode' => 10]], [1, 3], [10]],
    'multiple companies union' => [[['cmpycode' => 10], ['cmpycode' => 20]], [1, 2, 3, 4], [10, 20]],
    'country only denies' => [[['countrycode' => 1]], [], []],
    'region only denies' => [[['regionmstcode' => 1]], [], []],
    'depot only denies' => [[['depotcode' => 1]], [], []],
    'area only denies' => [[['areacode' => 1]], [], []],
    'subarea only denies' => [[['subareacode' => 1]], [], []],
    'geography does not narrow company' => [[['countrycode' => 1, 'cmpycode' => 20, 'regionmstcode' => 99, 'depotcode' => 99, 'areacode' => 99, 'subareacode' => 99]], [2, 4], [20]],
    'separate geographic grants do not broaden company' => [[['cmpycode' => 10], ['countrycode' => 2], ['subareacode' => 1]], [1, 3], [10]],
    'duplicate companies' => [[['cmpycode' => 10], ['cmpycode' => 10]], [1, 3], [10]],
    'blank company denies' => [[['cmpycode' => '']], [], []],
    'null company with geography denies' => [[['cmpycode' => null, 'countrycode' => 1, 'subareacode' => 1]], [], []],
    'unknown company denies' => [[['cmpycode' => 999]], [], []],
    'empty permission row' => [[[]], [], []],
    'no permission rows' => [[], [], []],
]);

test('company grants retain missing geography and replace previous login scope', function () {
    $db = DB::connection('sfa_mysql');
    $db->table('routemaster')->insert([
        ['routecode' => 5, 'cmpycode' => 10, 'subareacode' => null, 'regionmstcode' => 2],
        ['routecode' => 6, 'cmpycode' => 10, 'subareacode' => 99, 'regionmstcode' => 2],
    ]);
    $db->table('useraccesscodes')->insert(['username' => 'permitted-user', 'cmpycode' => 10]);
    $request = Mockery::mock(LoginRequest::class)->makePartial();
    $request->shouldReceive('authenticate')->times(3);
    $request->setUserResolver(fn () => (object) ['username' => 'permitted-user', 'accesstypeid' => 1]);
    $request->setLaravelSession(app('session')->driver());
    $controller = app(AuthenticatedSessionController::class);
    $controller->store($request);
    expect(session('user_access.route_codes'))->toEqualCanonicalizing([1, 3, 5, 6])
        ->and(session('user_access.subarea_codes'))->toEqualCanonicalizing([1, 2, 99])
        ->and(session('user_access.region_codes'))->toEqualCanonicalizing([1, 2]);

    $db->table('useraccesscodes')->where('username', 'permitted-user')->delete();
    $db->table('useraccesscodes')->insert(['username' => 'permitted-user', 'cmpycode' => 20]);
    // Editing grants does not mutate the existing login snapshot.
    expect(session('user_access.route_codes'))->toEqualCanonicalizing([1, 3, 5, 6]);
    $controller->store($request);
    expect(session('user_access.route_codes'))->toEqualCanonicalizing([2, 4])
        ->and(session('user_access.company_codes'))->toBe([20])
        ->and(session('user_access.country_codes'))->toBe([2]);

    $db->table('useraccesscodes')->where('username', 'permitted-user')->delete();
    $controller->store($request);
    expect(session('user_access.route_codes'))->toBe([])
        ->and(session('user_access.company_codes'))->toBe([]);
});
