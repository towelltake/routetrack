<?php

use App\Services\DashboardMetrics;
use Illuminate\Support\Facades\DB;

uses(Tests\TestCase::class);

beforeEach(function () {
    config(['database.default' => 'metrics_test', 'database.connections.metrics_test' => [
        'driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '',
    ]]);
    DB::purge('metrics_test');
    foreach ([
        'startendday (routekey integer, routecode integer, routestartdate text, routestarttime text, routeenddate text, routeendtime text, routeclosed integer)',
        'routesequencecustomerstatus (routekey integer, customercode integer, schelduledflag integer)',
        'customermaster (customercode integer, channelcode integer, customerfacetime integer)',
        'channelmaster (channelcode integer, customercft integer)',
        'customervisitlog (logkey integer, routekey integer, customercode integer, logstartdate text, logstarttime text, logenddate text, logendtime text)',
        'customeroperationscontrol (primary_id integer, routekey integer, log_id integer, visitkey integer)',
        'invoiceheader (routekey integer, visitkey integer, totalinvoiceamount decimal, currencycode integer, voidflag integer)',
        'salesorderheader (routekey integer, visitkey integer, totalinvoiceamount decimal, currencycode integer, voidflag integer)',
        'arheader (routekey integer, visitkey integer, amountpaid decimal, currencycode integer, voidflag integer)',
        'currencymaster (currencycode integer, currencysymbol text)',
        'otplogdetail (otplogid integer, routecode integer, customercode integer, otpdate text, otptime text, otptype text)',
    ] as $table) DB::statement('CREATE TABLE '.$table);
    DB::table('startendday')->insert([
        ['routekey' => 1, 'routecode' => 1, 'routestartdate' => '2026-09-01', 'routestarttime' => '08:00:00', 'routeenddate' => '2026-09-02', 'routeendtime' => '02:00:00', 'routeclosed' => 1],
        ['routekey' => 2, 'routecode' => 1, 'routestartdate' => '2026-09-03', 'routestarttime' => '08:00:00', 'routeenddate' => null, 'routeendtime' => null, 'routeclosed' => 0],
        ['routekey' => 3, 'routecode' => 2, 'routestartdate' => '2026-08-31', 'routestarttime' => '08:00:00', 'routeenddate' => null, 'routeendtime' => null, 'routeclosed' => 0],
        ['routekey' => 4, 'routecode' => 1, 'routestartdate' => '2026-09-04', 'routestarttime' => '08:00:00', 'routeenddate' => null, 'routeendtime' => null, 'routeclosed' => 0],
    ]);
    foreach ([[1, 101], [1, 101], [1, 102], [2, 101], [2, 103], [3, 101]] as [$journey, $customer]) {
        DB::table('routesequencecustomerstatus')->insert(['routekey' => $journey, 'customercode' => $customer, 'schelduledflag' => 1]);
    }
    DB::table('channelmaster')->insert(['channelcode' => 1, 'customercft' => 15]);
    foreach ([[101, 10, 1], [104, null, 1], [105, 0, 1], [106, 0, 99]] as [$customer, $cft, $channel]) {
        DB::table('customermaster')->insert(['customercode' => $customer, 'customerfacetime' => $cft, 'channelcode' => $channel]);
    }
    foreach ([[11, 1, 101, '2026-09-01', '10:00:00', '10:20:00'], [12, 1, 101, '2026-09-01', '10:30:00', '10:40:00'],
        [21, 2, 101, '2026-09-03', '09:00:00', '09:30:00'], [22, 2, 104, '2026-09-03', '11:00:00', null],
        [23, 2, 105, '2026-09-03', '12:00:00', '12:10:00'], [24, 2, 106, '2026-09-03', '13:00:00', '13:05:00']] as [$id, $journey, $customer, $date, $start, $end]) {
        DB::table('customervisitlog')->insert(['logkey' => $id, 'routekey' => $journey, 'customercode' => $customer,
            'logstartdate' => $date, 'logstarttime' => $start, 'logenddate' => $end ? $date : null, 'logendtime' => $end]);
    }
    foreach ([[1, 1, 11, 500], [2, 1, 12, 501], [3, 2, 21, 500]] as [$id, $journey, $log, $visit]) {
        DB::table('customeroperationscontrol')->insert(['primary_id' => $id, 'routekey' => $journey, 'log_id' => $log, 'visitkey' => $visit]);
    }
    foreach ([[1, 500, 100, 1, 0], [1, 500, 50, 1, 0], [2, 999, 20, 2, 0], [2, 500, 999, 1, 1], [3, 500, 777, 1, 0]] as [$journey, $visit, $amount, $currency, $void]) {
        DB::table('invoiceheader')->insert(['routekey' => $journey, 'visitkey' => $visit, 'totalinvoiceamount' => $amount, 'currencycode' => $currency, 'voidflag' => $void]);
    }
    foreach ([[501, 40], [500, 20]] as [$visit, $amount]) DB::table('salesorderheader')->insert(['routekey' => 1, 'visitkey' => $visit, 'totalinvoiceamount' => $amount, 'currencycode' => 1, 'voidflag' => 0]);
    DB::table('arheader')->insert(['routekey' => 2, 'visitkey' => 500, 'amountpaid' => 75, 'currencycode' => 1, 'voidflag' => 0]);
    DB::table('currencymaster')->insert([['currencycode' => 1, 'currencysymbol' => 'OMR'], ['currencycode' => 2, 'currencysymbol' => 'USD']]);
    foreach ([[1, 1, 101, '2026-09-01', '10:05:00'], [2, 1, 101, '2026-09-01', '10:06:00'],
        [3, 1, 999, '2026-09-02', '01:00:00'], [4, 1, 101, '2026-09-02', '03:00:00'],
        [5, 1, 101, '2026-09-03', '09:05:00'], [6, 2, 101, '2026-09-01', '10:00:00'],
        [7, 1, 101, '2026-09-04', '09:00:00']] as [$id, $route, $customer, $date, $time]) {
        DB::table('otplogdetail')->insert(['otplogid' => $id, 'routecode' => $route, 'customercode' => $customer, 'otpdate' => $date, 'otptime' => $time, 'otptype' => $id % 2 ? 'GPS IN' : 'OTHER']);
    }
});

test('cards aggregate every selected journey without multiplying customers or transaction totals', function () {
    $result = app(DashboardMetrics::class)->summarize(DB::table('startendday')->whereIn('routekey', [1, 2])->get());
    expect($result)->toMatchArray([
        'journeys_started' => 2, 'unique_routes' => 1, 'routes_not_started' => null,
        'planned_customers' => 4, 'planned_visited' => 2, 'coverage_percent' => 50.0,
        'pending_customers' => 1, 'missed_customers' => 1, 'completed_visits' => 5,
        'productive_visits' => 2, 'nonproductive_visits' => 3, 'productivity_percent' => 40.0,
        'cft_minutes' => 75.0, 'cft_variance_minutes' => 25.0, 'cft_configured_visits' => 4,
        'otp' => ['events' => 4, 'visits' => 2],
    ]);
    expect($result['amounts']['sales'])->toHaveCount(2)
        ->and((float) $result['amounts']['sales'][0]['amount'])->toBe(150.0)
        ->and($result['amounts']['sales'][1]['currency'])->toBe('USD')
        ->and((float) $result['amounts']['sales'][1]['amount'])->toBe(20.0)
        ->and((float) $result['amounts']['orders'][0]['amount'])->toBe(60.0)
        ->and((float) $result['amounts']['collections'][0]['amount'])->toBe(75.0);
});

test('empty periods have zero totals and undefined rates rather than fabricated percentages', function () {
    $result = app(DashboardMetrics::class)->summarize(collect());
    expect($result)->toMatchArray(['journeys_started' => 0, 'coverage_percent' => null, 'productivity_percent' => null,
        'cft_minutes' => 0.0, 'cft_variance_minutes' => null, 'otp' => ['events' => 0, 'visits' => 0]])
        ->and($result['amounts']['sales'])->toBe([]);
});

test('a return-only invoice changes sales total but does not make a visit productive', function () {
    DB::table('customeroperationscontrol')->insert(['primary_id' => 9, 'routekey' => 2, 'log_id' => 23, 'visitkey' => 777]);
    DB::table('invoiceheader')->insert(['routekey' => 2, 'visitkey' => 777, 'totalinvoiceamount' => -10, 'currencycode' => 1, 'voidflag' => 0]);
    $result = app(DashboardMetrics::class)->summarize(DB::table('startendday')->whereIn('routekey', [1, 2])->get());
    expect($result['productive_visits'])->toBe(2)
        ->and((float) $result['amounts']['sales'][0]['amount'])->toBe(140.0);
});
