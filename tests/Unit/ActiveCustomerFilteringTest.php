<?php

use App\Http\Controllers\CustomerLocation\CustomerLocationController;
use App\Http\Controllers\RouteTracking\RouteTrackingController;
use App\Models\CustomerMaster;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\HttpException;

uses(Tests\TestCase::class);

beforeEach(function () {
    config(['database.default' => 'active_customer_test', 'database.connections.active_customer_test' => [
        'driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '',
    ]]);
    DB::purge('active_customer_test');
    foreach ([
        'customermaster (customerfacetime integer default 0, DivisionCode text, channel text, customercode integer primary key, activecustomer integer, customername text, customeraddress1 text, customeraddress2 text, alternatecode text, fixedlatitude real, fixedlongitude real, toplpo integer)',
        'routemaster (routecode integer, cmpycode integer, subareacode integer)',
        'routesequence (routecode integer, customercode integer)',
        'routesequencecustomerstatus (routekey integer, customercode integer, schelduledflag integer, sequencenumber integer, servicedflag integer, scannedflag integer)',
        'customerclustermapping (divisioncode text, channel text, cft integer, UNIQUE (divisioncode, channel))',
        'customervisitlog (routekey integer, logkey integer, customercode integer, cft integer, logstartdate text, logstarttime text, logenddate text, logendtime text)',
        'customeroperationscontrol (primary_id integer, routekey integer, log_id integer, visitkey integer, latitude real, longitude real)',
        'otplogdetail (otplogid integer, routecode integer, customercode integer, otpdate text, otptime text, otptype text, username text, comments text, otpreason text)',
        'arheader (transactionkey integer, routecode integer, routekey integer, visitkey integer, customercode integer)',
        'ardetail (transactionkey integer, primary_key integer, invoicenumber text, alternateinvoicenumber text, invoicedate text, totalinvoiceamount real, amountpaid real, invoicebalance real, arcollectiontype integer, referenceno text)',
    ] as $table) DB::statement('CREATE TABLE '.$table);
    DB::table('routemaster')->insert(['routecode' => 7, 'cmpycode' => 1, 'subareacode' => 1]);
    session(['user_access.route_codes' => [7], 'user_access.company_codes' => [1], 'user_access.subarea_codes' => [1]]);
    foreach ([1 => 1, 2 => 0, 3 => null, 4 => 2, 5 => 'missing'] as $code => $status) {
        if ($status !== 'missing') DB::table('customermaster')->insert([
            'customercode' => $code, 'activecustomer' => $status, 'customername' => 'Customer '.$code,
            'fixedlatitude' => 23.5, 'fixedlongitude' => 58.5,
        ]);
        DB::table('routesequence')->insert(['routecode' => 7, 'customercode' => $code]);
        DB::table('routesequencecustomerstatus')->insert([
            'routekey' => 10, 'customercode' => $code, 'schelduledflag' => 1, 'sequencenumber' => $code,
        ]);
        DB::table('customervisitlog')->insert([
            'routekey' => 10, 'logkey' => $code, 'customercode' => $code, 'cft' => 10,
            'logstartdate' => '2026-09-29', 'logstarttime' => '10:00:00', 'logenddate' => '2026-09-29', 'logendtime' => '10:10:00',
        ]);
        DB::table('otplogdetail')->insert([
            'otplogid' => $code, 'routecode' => 7, 'customercode' => $code,
            'otpdate' => '2026-09-29', 'otptime' => '10:00:00', 'otptype' => 'GPS IN',
        ]);
        DB::table('arheader')->insert([
            'transactionkey' => $code, 'routecode' => 7, 'routekey' => 10, 'visitkey' => $code, 'customercode' => $code,
        ]);
    }
});

test('customer model and location endpoint require status one while preserving session access', function () {
    expect(CustomerMaster::query()->pluck('customercode')->all())->toBe([1]);
    $controller = app(CustomerLocationController::class);
    $request = Request::create('/', 'GET', ['companycode' => 1]);
    expect(array_column($controller->locations($request)->getData(true), 'customercode'))->toBe([1]);
    session(['user_access.route_codes' => []]);
    expect($controller->locations($request)->getData(true))->toBe([]);
});

test('route plans visits OTP and planned CFT exclude inactive and missing master records', function () {
    $controller = app(RouteTrackingController::class);
    foreach ([
        'fetchScheduledCustomersForRouteKey' => [10], 'fetchJourneyPlan' => [10],
        'fetchCustomerVisits' => [10, 7], 'fetchRouteOtpLogs' => [7, '2026-09-29'],
    ] as $method => $arguments) {
        $rows = (new ReflectionMethod(RouteTrackingController::class, $method))->invoke($controller, ...$arguments);
        expect($rows->pluck('customercode')->all())->toBe([1]);
    }
    $allCodes = collect(range(1, 5))->map(fn ($code) => (object) ['customercode' => $code]);
    expect((new ReflectionMethod(RouteTrackingController::class, 'plannedFaceTimeSeconds'))
        ->invoke($controller, 10, $allCodes))->toBe(600);
    // Active planned customers without usable coordinates still belong in plan counts.
    DB::table('customermaster')->where('customercode', 1)->update(['fixedlatitude' => null]);
    expect((new ReflectionMethod(RouteTrackingController::class, 'fetchJourneyPlan'))
        ->invoke($controller, 10)->pluck('customercode')->all())->toBe([1]);
});

test('transaction details reject inactive customers and still enforce route access for active customers', function () {
    $controller = app(RouteTrackingController::class);
    foreach ([1 => 200, 2 => 404, 3 => 404, 4 => 404, 5 => 404] as $code => $expected) {
        $request = Request::create('/', 'GET', ['type' => 'collections', 'transactionkey' => $code, 'routekey' => 10, 'visitkey' => $code]);
        try {
            $status = $controller->transactionDetails($request)->getStatusCode();
        } catch (HttpException $exception) {
            $status = $exception->getStatusCode();
        }
        expect($status)->toBe($expected);
    }
    session(['user_access.route_codes' => []]);
    $request = Request::create('/', 'GET', ['type' => 'collections', 'transactionkey' => 1, 'routekey' => 10, 'visitkey' => 1]);
    try {
        $status = $controller->transactionDetails($request)->getStatusCode();
    } catch (HttpException $exception) {
        $status = $exception->getStatusCode();
    }
    expect($status)->toBe(403);
});
