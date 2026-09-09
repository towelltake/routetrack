<?php

use App\Http\Controllers\RouteTracking\RouteTrackingController;
use Illuminate\Support\Facades\DB;

uses(Tests\TestCase::class);

test('route tracking totals non-voided documents once and combines good and bad returns', function () {
    $sale = ['transactionkey' => 11, 'amount' => 100, 'return_amount' => 15, 'voided' => false];
    $visits = collect([
        ['transactions' => [
            'sales' => [$sale, ['transactionkey' => 12, 'amount' => 50, 'return_amount' => 8, 'voided' => true]],
            'orders' => [['transactionkey' => 21, 'amount' => 80, 'voided' => false]],
            'collections' => [['transactionkey' => 31, 'amount' => 45, 'voided' => false]],
        ]],
        ['transactions' => ['sales' => [$sale]]],
    ]);

    $method = new ReflectionMethod(RouteTrackingController::class, 'summarizeTransactions');
    $summary = $method->invoke(app(RouteTrackingController::class), $visits);

    expect($summary['sales'])->toBe(['count' => 1, 'amount' => 100.0])
        ->and($summary['orders'])->toBe(['count' => 1, 'amount' => 80.0])
        ->and($summary['collections'])->toBe(['count' => 1, 'amount' => 45.0])
        ->and($summary['returns'])->toBe(['count' => 1, 'amount' => 15.0]);
});

test('route journey details expose route salesman times odometers version and optional phone', function () {
    config(['database.default' => 'route_details_test', 'database.connections.route_details_test' => [
        'driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '',
    ]]);
    DB::purge('route_details_test');
    DB::statement('CREATE TABLE routemaster (routecode integer primary key, routename text)');
    DB::statement('CREATE TABLE salesman (salesmancode integer primary key, salesmanname1 text)');
    DB::table('routemaster')->insert(['routecode' => 7, 'routename' => 'Muscat Route']);
    DB::table('salesman')->insert(['salesmancode' => 9, 'salesmanname1' => 'Ahmed']);
    $journey = (object) [
        'salesmancode' => 9, 'routestartdate' => '2026-09-08 00:00:00', 'routestarttime' => '08:00:00',
        'routeenddate' => '2026-09-08 00:00:00', 'routeendtime' => '17:00:00',
        'routestartodometer' => 100, 'routeendodometer' => 240, 'versionno' => '5.2.1',
    ];

    $method = new ReflectionMethod(RouteTrackingController::class, 'routeDetails');
    $details = $method->invoke(app(RouteTrackingController::class), $journey, 7);

    expect($details)->toMatchArray([
        'routecode' => 7, 'routename' => 'Muscat Route', 'salesmancode' => 9,
        'salesmanname' => 'Ahmed',
        'start_time' => '2026-09-08 08:00:00', 'end_time' => '2026-09-08 17:00:00',
        'start_odometer' => 100.0, 'end_odometer' => 240.0, 'version' => '5.2.1',
    ]);
});

test('full route OTP list includes all request types even when no customer visit can match them', function () {
    config(['database.default' => 'route_otp_test', 'database.connections.route_otp_test' => [
        'driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '',
    ]]);
    DB::purge('route_otp_test');
    DB::statement('CREATE TABLE customermaster (customercode integer, customeraddress1 text, alternatecode text)');
    DB::statement('CREATE TABLE otplogdetail (otplogid integer, username text, customercode integer, routecode integer, otptype text, otpdate text, otptime text, comments text, otpreason text)');
    DB::table('customermaster')->insert(['customercode' => 8, 'customeraddress1' => 'Customer Eight', 'alternatecode' => 'C008']);
    DB::table('otplogdetail')->insert([
        ['otplogid' => 1, 'username' => 'manager', 'customercode' => 8, 'routecode' => 7, 'otptype' => 'GPS IN', 'otpdate' => '2026-09-08', 'otptime' => '10:00:00', 'comments' => 'Approved', 'otpreason' => 'GPS issue'],
        ['otplogid' => 2, 'username' => 'manager', 'customercode' => 8, 'routecode' => 7, 'otptype' => 'OTHER', 'otpdate' => '2026-09-08', 'otptime' => '11:00:00', 'comments' => null, 'otpreason' => null],
    ]);

    $method = new ReflectionMethod(RouteTrackingController::class, 'fetchRouteOtpLogs');
    $logs = $method->invoke(app(RouteTrackingController::class), 7, '2026-09-08');

    expect($logs)->toHaveCount(2)->and($logs->first())->toMatchArray([
        'id' => 1, 'customername' => 'Customer Eight', 'alternatecode' => 'C008',
        'approved_by' => 'manager', 'reason' => 'GPS issue', 'comments' => 'Approved',
    ])->and($logs->last()['type'])->toBe('OTHER');
});
