<?php

use App\Http\Controllers\JourneyPlan\JourneyPlanController;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

uses(Tests\TestCase::class);

beforeEach(function () {
    config(['database.connections.sfa_mysql' => ['driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '']]);
    DB::purge('sfa_mysql');
    $db = DB::connection('sfa_mysql');
    $db->statement('CREATE TABLE routemaster (routecode integer, routename text, cmpycode integer, subareacode integer, salesmancode integer)');
    $db->table('routemaster')->insert([
        ['routecode' => 1, 'routename' => 'Allowed', 'cmpycode' => 10, 'subareacode' => 20, 'salesmancode' => 1],
        ['routecode' => 2, 'routename' => 'Other company', 'cmpycode' => 11, 'subareacode' => 20, 'salesmancode' => 2],
        ['routecode' => 3, 'routename' => 'Other geography', 'cmpycode' => 10, 'subareacode' => 21, 'salesmancode' => 3],
        ['routecode' => 4, 'routename' => 'Other route', 'cmpycode' => 10, 'subareacode' => 20, 'salesmancode' => 4],
    ]);
    session(['user_access' => ['route_codes' => [1, 2, 3], 'company_codes' => [10], 'subarea_codes' => [20]]]);
});

afterEach(function () {
    DB::purge('sfa_mysql');
});

test('journey plan catalog intersects route company and geographic access', function () {
    $response = app(JourneyPlanController::class)->routes()->getData(true);
    expect(array_column($response, 'routecode'))->toBe([1]);
    session()->forget('user_access');
    expect(app(JourneyPlanController::class)->routes()->getData(true))->toBe([]);
});

test('journey plan rejects inaccessible routes before reading customer records', function ($routecode) {
    $request = Request::create('/journey-plan/plan.json', 'GET', ['routecode' => $routecode]);
    expect(fn () => app(JourneyPlanController::class)->plan($request))->toThrow(ModelNotFoundException::class);
})->with([2, 3, 4, 999]);
