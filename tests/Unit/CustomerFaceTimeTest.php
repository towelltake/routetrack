<?php

use App\Http\Controllers\RouteTracking\RouteTrackingController;
use Illuminate\Support\Facades\DB;

uses(Tests\TestCase::class);

test('planned customer face time uses only the visit log CFT in minutes', function ($visitCft, int $expected) {
    config(['database.default' => 'cft_test', 'database.connections.cft_test' => [
        'driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '',
    ]]);
    DB::purge('cft_test');

    foreach ([
        'customermaster (customercode integer, customeraddress1 text, alternatecode text, fixedlatitude real, fixedlongitude real)',
        'customervisitlog (logkey integer, customercode integer, routekey integer, logstartdate text, logstarttime text, logenddate text, logendtime text, cft integer)',
        'customeroperationscontrol (primary_id integer, routekey integer, log_id integer, visitkey integer, latitude real, longitude real)',
    ] as $table) {
        DB::statement('CREATE TABLE '.$table);
    }
    DB::table('customermaster')->insert([
        'customercode' => 1,
        'fixedlatitude' => 23.5, 'fixedlongitude' => 58.5,
    ]);
    DB::table('customervisitlog')->insert([
        'logkey' => 1, 'customercode' => 1, 'routekey' => 100,
        'logstartdate' => '2026-09-07', 'logstarttime' => '10:00:00',
        'logenddate' => '2026-09-07', 'logendtime' => '10:18:00',
        'cft' => $visitCft,
    ]);

    $method = new ReflectionMethod(RouteTrackingController::class, 'fetchCustomerVisits');
    $visits = $method->invoke(app(RouteTrackingController::class), 100, 1);
    expect($visits)->toHaveCount(1)
        ->and($visits->first()['default_face_time_minutes'])->toBe($expected)
        ->and($visits->first()['visit_duration_minutes'])->toBe(18);
})->with([
    'visit CFT is used' => [12, 12],
    'null visit CFT becomes zero' => [null, 0],
    'zero visit CFT remains zero' => [0, 0],
]);

test('planned face time totals targets for every planned customer', function () {
    config(['database.default' => 'planned_cft_test', 'database.connections.planned_cft_test' => [
        'driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '',
    ]]);
    DB::purge('planned_cft_test');
    DB::statement('CREATE TABLE customervisitlog (routekey integer, customercode integer, cft integer)');
    DB::table('customervisitlog')->insert([
        ['routekey' => 100, 'customercode' => 1, 'cft' => 15],
        ['routekey' => 100, 'customercode' => 2, 'cft' => 20],
        ['routekey' => 100, 'customercode' => 3, 'cft' => null],
        ['routekey' => 200, 'customercode' => 1, 'cft' => 99],
        ['routekey' => 100, 'customercode' => 99, 'cft' => 99],
    ]);

    $method = new ReflectionMethod(RouteTrackingController::class, 'plannedFaceTimeSeconds');
    $seconds = $method->invoke(app(RouteTrackingController::class), 100, collect([
        (object) ['customercode' => 1],
        (object) ['customercode' => 2],
        (object) ['customercode' => 3],
    ]));

    expect($seconds)->toBe(35 * 60);
});
