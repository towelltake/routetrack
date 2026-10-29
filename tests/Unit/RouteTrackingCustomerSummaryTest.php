<?php

use App\Http\Controllers\RouteTracking\RouteTrackingController;
use App\Services\DashboardCustomerDetails;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\HttpException;

uses(Tests\TestCase::class);

beforeEach(function () {
    config(['database.default' => 'summary_test', 'database.connections.summary_test' => ['driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '']]);
    DB::purge('summary_test');
    DB::statement('CREATE TABLE routemaster (routecode integer primary key, routename text, cmpycode integer, subareacode integer)');
    DB::statement('CREATE TABLE startendday (routekey integer, routecode integer, routestartdate text)');
    DB::table('routemaster')->insert(['routecode' => 10, 'routename' => 'Allowed route', 'cmpycode' => 1, 'subareacode' => 2]);
    DB::table('startendday')->insert([['routekey' => 100, 'routecode' => 10, 'routestartdate' => '2026-10-21'], ['routekey' => 101, 'routecode' => 10, 'routestartdate' => '2026-10-21'], ['routekey' => 200, 'routecode' => 20, 'routestartdate' => '2026-10-21']]);
    session(['user_access.route_codes' => [10], 'user_access.company_codes' => [1], 'user_access.subarea_codes' => [2]]);
});

test('tracking customer popup stays on displayed journey even if a newer same day journey exists', function () {
    $this->mock(DashboardCustomerDetails::class, fn ($mock) => $mock->shouldReceive('build')->once()
        ->withArgs(fn ($journeys, $type) => $type === 'planned' && $journeys->count() === 1 && $journeys->first()->routekey === 100)
        ->andReturn(['groups' => []]));
    $request = Request::create('/', 'GET', ['routecode' => 10, 'date' => '2026-10-21', 'routekey' => 100, 'type' => 'planned']);
    expect(app(RouteTrackingController::class)->customerDetails($request)->getStatusCode())->toBe(200);
});

test('tracking customer popup cannot read a journey belonging to another route', function () {
    $this->mock(DashboardCustomerDetails::class, fn ($mock) => $mock->shouldReceive('build')->once()->withArgs(fn ($journeys) => $journeys->isEmpty())->andReturn(['groups' => []]));
    $request = Request::create('/', 'GET', ['routecode' => 10, 'date' => '2026-10-21', 'routekey' => 200, 'type' => 'unplanned']);
    expect(app(RouteTrackingController::class)->customerDetails($request)->getData(true))->toBe(['groups' => []]);
});

test('tracking customer popup preserves company and geographical route restrictions', function ($key) {
    session([$key => []]);
    $request = Request::create('/', 'GET', ['routecode' => 10, 'date' => '2026-10-21', 'routekey' => 100, 'type' => 'coverage']);
    expect(fn () => app(RouteTrackingController::class)->customerDetails($request))->toThrow(HttpException::class);
})->with(['user_access.route_codes', 'user_access.company_codes', 'user_access.subarea_codes']);
