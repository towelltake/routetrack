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
        'routesequencecustomerstatus (routekey integer, customercode integer, schelduledflag integer, sequencenumber integer)',
        'customervisitlog (logkey integer, routekey integer, customercode integer, logstartdate text, logstarttime text, logenddate text, logendtime text, cft integer)',
        'customeroperationscontrol (primary_id integer, routekey integer, log_id integer, visitkey integer)',
        'invoiceheader (routekey integer, visitkey integer, totalinvoiceamount decimal, currencycode integer, voidflag integer)',
        'salesorderheader (routekey integer, visitkey integer, totalinvoiceamount decimal, currencycode integer, voidflag integer)',
        'arheader (routekey integer, visitkey integer, amountpaid decimal, currencycode integer, voidflag integer)',
        'currencymaster (currencycode integer, currencysymbol text)',
        'otplogdetail (otplogid integer, routecode integer, customercode integer, otpdate text, otptime text, otptype text, username text, otpreason text, comments text)',
    ] as $table) {
        DB::statement('CREATE TABLE '.$table);
    }
    DB::table('startendday')->insert([
        ['routekey' => 1, 'routecode' => 1, 'routestartdate' => '2026-09-01', 'routestarttime' => '08:00:00', 'routeenddate' => '2026-09-02', 'routeendtime' => '02:00:00', 'routeclosed' => 1],
        ['routekey' => 2, 'routecode' => 1, 'routestartdate' => '2026-09-03', 'routestarttime' => '08:00:00', 'routeenddate' => null, 'routeendtime' => null, 'routeclosed' => 0],
        ['routekey' => 3, 'routecode' => 2, 'routestartdate' => '2026-08-31', 'routestarttime' => '08:00:00', 'routeenddate' => null, 'routeendtime' => null, 'routeclosed' => 0],
        ['routekey' => 4, 'routecode' => 1, 'routestartdate' => '2026-09-04', 'routestarttime' => '08:00:00', 'routeenddate' => null, 'routeendtime' => null, 'routeclosed' => 0],
    ]);
    foreach ([[1, 101], [1, 101], [1, 102], [2, 101], [2, 103], [3, 101]] as [$journey, $customer]) {
        DB::table('routesequencecustomerstatus')->insert(['routekey' => $journey, 'customercode' => $customer, 'schelduledflag' => 1]);
    }
    foreach ([[11, 1, 101, '2026-09-01', '10:00:00', '10:20:00', 10], [12, 1, 101, '2026-09-01', '10:30:00', '10:40:00', 10],
        [21, 2, 101, '2026-09-03', '09:00:00', '09:30:00', 10], [22, 2, 104, '2026-09-03', '11:00:00', null, 15],
        [23, 2, 105, '2026-09-03', '12:00:00', '12:10:00', 15], [24, 2, 106, '2026-09-03', '13:00:00', '13:05:00', 0]] as [$id, $journey, $customer, $date, $start, $end, $cft]) {
        DB::table('customervisitlog')->insert(['logkey' => $id, 'routekey' => $journey, 'customercode' => $customer,
            'logstartdate' => $date, 'logstarttime' => $start, 'logenddate' => $end ? $date : null, 'logendtime' => $end, 'cft' => $cft]);
    }
    foreach ([[1, 1, 11, 500], [2, 1, 12, 501], [3, 2, 21, 500]] as [$id, $journey, $log, $visit]) {
        DB::table('customeroperationscontrol')->insert(['primary_id' => $id, 'routekey' => $journey, 'log_id' => $log, 'visitkey' => $visit]);
    }
    foreach ([[1, 500, 100, 1, 0], [1, 500, 50, 1, 0], [2, 999, 20, 2, 0], [2, 500, 999, 1, 1], [3, 500, 777, 1, 0]] as [$journey, $visit, $amount, $currency, $void]) {
        DB::table('invoiceheader')->insert(['routekey' => $journey, 'visitkey' => $visit, 'totalinvoiceamount' => $amount, 'currencycode' => $currency, 'voidflag' => $void]);
    }
    foreach ([[501, 40], [500, 20]] as [$visit, $amount]) {
        DB::table('salesorderheader')->insert(['routekey' => 1, 'visitkey' => $visit, 'totalinvoiceamount' => $amount, 'currencycode' => 1, 'voidflag' => 0]);
    }
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

test('analysis exposes journey coverage, repeat visits, OTP details and missing data honestly', function () {
    $result = app(DashboardMetrics::class)->summarize(DB::table('startendday')->whereIn('routekey', [1, 2])->get());
    $rows = collect($result['analysis']['journeys'])->keyBy('routekey');
    expect($rows[1])->toMatchArray(['planned' => 2, 'covered' => 1, 'missed' => 1, 'repeat' => 1, 'otp' => 3]);
    expect($rows[2])->toMatchArray(['pending' => 1, 'unplanned' => 3, 'incomplete_visits' => 1, 'missing_cft' => 1, 'duration' => null, 'distance' => null, 'stationary_time' => null]);
    expect($rows[1]['otp_events'])->toHaveCount(3)
        ->and(collect($rows[1]['issues'])->pluck('label')->all())->toContain('Missed customers', 'Repeat visits');
});

test('time chart merges overlapping visit intervals and distance requires completed valid readings', function () {
    DB::table('customervisitlog')->insert(['logkey' => 13, 'routekey' => 1, 'customercode' => 101,
        'logstartdate' => '2026-09-01', 'logstarttime' => '10:10:00', 'logenddate' => '2026-09-01', 'logendtime' => '10:35:00', 'cft' => 10]);
    $journeys = DB::table('startendday')->whereIn('routekey', [1, 2])->get();
    foreach ($journeys as $journey) {
        $journey->routestartodometer = 1234;
        $journey->routeendodometer = 1250;
    }
    $rows = collect(app(DashboardMetrics::class)->summarize($journeys)['analysis']['journeys'])->keyBy('routekey');
    expect($rows[1]['actual_cft'])->toEqual(55)
        ->and($rows[1]['visit_time'])->toEqual(40)
        ->and($rows[1]['remaining_time'])->toEqual($rows[1]['duration'] - 40)
        ->and($rows[1]['distance'])->toBe(16.0)
        ->and($rows[2]['distance'])->toBeNull();
});

test('sequence exceptions use planned order and do not label missing-plan visits unplanned', function () {
    DB::table('routesequencecustomerstatus')->where('routekey', 1)->where('customercode', 101)->update(['sequencenumber' => 2]);
    DB::table('routesequencecustomerstatus')->where('routekey', 2)->delete();
    $rows = collect(app(DashboardMetrics::class)->summarize(DB::table('startendday')->whereIn('routekey', [1, 2])->get())['analysis']['journeys'])->keyBy('routekey');
    expect($rows[1]['out_of_sequence'])->toBe(1)->and($rows[1]['repeat'])->toBe(1)
        ->and($rows[2]['unplanned'])->toBe(0)
        ->and(collect($rows[2]['issues'])->pluck('label')->all())->toContain('Journey plan unavailable');
});
