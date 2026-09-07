<?php

use App\Http\Controllers\RouteTracking\RouteTrackingController;
use Illuminate\Support\Facades\DB;

uses(Tests\TestCase::class);

test('customer face time uses customer then channel then zero in minutes', function ($customerCft, $channelCft, bool $hasChannel, int $expected) {
    config(['database.default' => 'cft_test', 'database.connections.cft_test' => [
        'driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '',
    ]]);
    DB::purge('cft_test');

    foreach ([
        'customermaster (customercode integer, customeraddress1 text, alternatecode text, fixedlatitude real, fixedlongitude real, channelcode integer, customerfacetime integer)',
        'channelmaster (channelcode integer, customercft integer)',
        'customervisitlog (logkey integer, customercode integer, routekey integer, logstartdate text, logstarttime text, logenddate text, logendtime text)',
        'customeroperationscontrol (primary_id integer, routekey integer, log_id integer, visitkey integer, latitude real, longitude real)',
    ] as $table) {
        DB::statement('CREATE TABLE '.$table);
    }
    DB::table('customermaster')->insert([
        'customercode' => 1, 'channelcode' => 10, 'customerfacetime' => $customerCft,
        'fixedlatitude' => 23.5, 'fixedlongitude' => 58.5,
    ]);
    if ($hasChannel) {
        DB::table('channelmaster')->insert(['channelcode' => 10, 'customercft' => $channelCft]);
    }
    // An unrelated channel must never supply the fallback.
    DB::table('channelmaster')->insert(['channelcode' => 20, 'customercft' => 99]);
    DB::table('customervisitlog')->insert([
        'logkey' => 1, 'customercode' => 1, 'routekey' => 100,
        'logstartdate' => '2026-09-07', 'logstarttime' => '10:00:00',
        'logenddate' => '2026-09-07', 'logendtime' => '10:18:00',
    ]);

    $method = new ReflectionMethod(RouteTrackingController::class, 'fetchCustomerVisits');
    $visits = $method->invoke(app(RouteTrackingController::class), 100, 1);
    expect($visits)->toHaveCount(1)
        ->and($visits->first()['default_face_time_minutes'])->toBe($expected)
        ->and($visits->first()['visit_duration_minutes'])->toBe(18);
})->with([
    'customer overrides channel' => [12, 25, true, 12],
    'null customer falls back to channel' => [null, 25, true, 25],
    'zero customer falls back to channel' => [0, 25, true, 25],
    'both null' => [null, null, true, 0],
    'both zero' => [0, 0, true, 0],
    'zero customer and null channel' => [0, null, true, 0],
    'null customer and zero channel' => [null, 0, true, 0],
    'missing channel defaults to zero' => [0, null, false, 0],
    'customer works without channel' => [12, null, false, 12],
]);
