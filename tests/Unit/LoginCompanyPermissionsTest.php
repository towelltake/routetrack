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
        'routemaster (routecode integer, cmpycode integer, subareacode integer)',
        'subareamaster (subareacode integer, areacode integer)',
        'areamaster (areacode integer, depotcode integer)',
        'depotmaster (depotcode integer, regionmstcode integer)',
        'regionmaster (regionmstcode integer, countrycode integer)',
        'country (countrycode integer)',
    ] as $table) $db->statement('CREATE TABLE '.$table);
    $db->table('country')->insert(['countrycode' => 1]);
    $db->table('regionmaster')->insert(['regionmstcode' => 1, 'countrycode' => 1]);
    $db->table('depotmaster')->insert(['depotcode' => 1, 'regionmstcode' => 1]);
    $db->table('areamaster')->insert(['areacode' => 1, 'depotcode' => 1]);
    $db->table('subareamaster')->insert([['subareacode' => 1, 'areacode' => 1], ['subareacode' => 2, 'areacode' => 1]]);
    foreach ([[1, 10, 1], [2, 20, 1], [3, 10, 2], [4, 20, 2]] as [$route, $company, $subarea]) $db->table('routemaster')->insert(['routecode' => $route, 'cmpycode' => $company, 'subareacode' => $subarea]);
});

test('login applies company and geographic permissions together per permission row', function ($permissions, $expectedRoutes, $expectedCompanies) {
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
    'company and shared subarea' => [[['cmpycode' => 10, 'subareacode' => 1]], [1], [10]],
    'company and shared region' => [[['cmpycode' => 10, 'regionmstcode' => 1]], [1, 3], [10]],
    'separate company scopes' => [[['cmpycode' => 10, 'subareacode' => 1], ['cmpycode' => 20, 'subareacode' => 2]], [1, 4], [10, 20]],
    'legacy geographic permission' => [[['subareacode' => 1]], [1, 2], [10, 20]],
    'empty permission row' => [[[]], [], []],
    'no permission rows' => [[], [], []],
]);
