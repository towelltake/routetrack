<?php

use App\Services\DashboardMetrics;
use App\Services\DashboardCustomerDetails;
use Illuminate\Support\Facades\DB;

uses(Tests\TestCase::class);

test('CFT popups retain OTP visits as excluded zero contributions while cards and graphs omit them', function () {
    $journeys = DB::table('startendday')->whereIn('routekey', [1, 2])->get();
    $journeys->each(function ($journey) { $journey->routename = 'Route'; $journey->salesman = 'Salesman'; });
    $metrics = app(DashboardMetrics::class)->summarize($journeys);
    expect($metrics['cft_minutes'])->toEqual(25)
        ->and($metrics['recorded_visit_minutes'])->toEqual(75)
        ->and(collect($metrics['analysis']['journeys'])->sum('actual_cft'))->toEqual(25);
    foreach (['cft', 'actual_face'] as $type) {
        $rows = collect(app(DashboardCustomerDetails::class)->build($journeys, $type)['groups'])->flatMap(fn ($group) => $group['rows']);
        expect($rows)->toHaveCount(6)
            ->and($rows->where('otp_excluded', true))->toHaveCount(2)
            ->and($rows->sum('actual_cft'))->toEqual(25)
            ->and($rows->where('otp_excluded', true)->sum('actual_cft'))->toEqual(0)
            ->and($rows->where('otp_excluded', true)->sum('planned_cft'))->toEqual(0)
            ->and($rows->firstWhere('id', 12)['otp_excluded'])->toBeFalse();
    }
});

test('collection and sales order productivity overlap without inflating the combined total', function () {
    $journeys = DB::table('startendday')->whereIn('routekey', [1, 2])->get();
    foreach ([10, 20] as $amount) DB::table('arheader')->insert(['routekey' => 1, 'visitkey' => 500, 'amountpaid' => $amount, 'voidflag' => 0]);
    DB::table('customeroperationscontrol')->insert(['primary_id' => 8, 'routekey' => 2, 'log_id' => 23, 'visitkey' => 800]);
    foreach ([[0, 0], [-10, 0], [100, 1], [100, 2]] as [$amount, $void]) DB::table('arheader')->insert(['routekey' => 2, 'visitkey' => 800, 'amountpaid' => $amount, 'voidflag' => $void]);
    $metrics = app(DashboardMetrics::class)->summarize($journeys);
    expect($metrics)->toMatchArray([
        'productive_visits' => 3, 'completed_visits' => 5, 'productivity_percent' => 60.0,
        'collection_productive_visits' => 2, 'sales_order_productive_visits' => 2,
        'collection_productivity_percent' => 40.0, 'sales_order_productivity_percent' => 40.0,
        'unique_productive_customers' => 2, 'unique_visited_customers' => 5, 'efficiency_percent' => 40.0,
        'collection_productive_customers' => 2, 'sales_order_productive_customers' => 1,
        'collection_efficiency_percent' => 40.0, 'sales_order_efficiency_percent' => 20.0,
    ]);
    $analysis = collect($metrics['analysis']['journeys']);
    expect($analysis->sum('productive'))->toBe(3)
        ->and($analysis->sum('collection_productive'))->toBe(2)
        ->and($analysis->sum('sales_order_productive'))->toBe(2)
        ->and($analysis->sum('productive_customers'))->toBe(2);
    $journeys->each(function ($journey) { $journey->routename = 'Route'; $journey->salesman = 'Salesman'; });
    $details = collect(app(DashboardCustomerDetails::class)->build($journeys, 'efficiency')['groups'])->flatMap(fn ($group) => $group['rows']);
    expect($details->where('collection_productive', true))->toHaveCount(2)
        ->and($details->where('sales_order_productive', true))->toHaveCount(1);
});

test('toplpo customers are excluded only from efficiency and productivity including analysis and drilldowns', function () {
    $journeys = DB::table('startendday')->whereIn('routekey', [1, 2])->get();
    $journeys->each(function ($journey) { $journey->routename = 'Route'; $journey->salesman = 'Salesman'; });
    $service = app(DashboardMetrics::class);
    $baseline = $service->summarize($journeys);
    foreach ([[101, 1], [104, 0], [105, null], [106, 2]] as [$code, $flag]) {
        DB::table('customermaster')->insert(['customercode' => $code, 'toplpo' => $flag]);
    }
    $result = $service->summarize($journeys);
    expect($result)->toMatchArray([
        'unique_visited_customers' => 3, 'unique_productive_customers' => 0, 'efficiency_percent' => 0.0,
        'completed_visits' => 2, 'productive_visits' => 0, 'nonproductive_visits' => 2, 'productivity_percent' => 0.0,
    ]);
    foreach (['planned_customers', 'planned_visited', 'coverage_percent', 'amounts', 'operational_minutes', 'cft_minutes', 'otp'] as $key) {
        expect($result[$key])->toBe($baseline[$key]);
    }
    $analysis = collect($result['analysis']['journeys']);
    expect($analysis->sum('completed'))->toBe(2)
        ->and($analysis->sum('productive'))->toBe(0)
        ->and($analysis->sum('nonproductive'))->toBe(2)
        ->and($analysis->sum('visits'))->toBe(6);
    $groups = app(DashboardCustomerDetails::class)->build($journeys, 'productive')['groups'];
    $rows = collect($groups)->flatMap(fn ($group) => $group['rows']);
    expect($rows)->toHaveCount(5)
        ->and($rows->where('status', 'Ignored'))->toHaveCount(3)
        ->and($rows->where('ignored', false)->pluck('customercode')->values()->all())->toBe([105, 106]);
    $efficiency = collect(app(DashboardCustomerDetails::class)->build($journeys, 'efficiency')['groups'])
        ->flatMap(fn ($group) => $group['rows']);
    expect($efficiency)->toHaveCount(5)
        ->and($efficiency->where('status', 'Ignored'))->toHaveCount(2)
        ->and($efficiency->where('ignored', false))->toHaveCount(3)
        ->and($efficiency->first()['visit_count'])->toBe(2);

    DB::table('customermaster')->update(['toplpo' => 1]);
    expect($service->summarize($journeys))->toMatchArray([
        'unique_visited_customers' => 0, 'unique_productive_customers' => 0, 'efficiency_percent' => null,
        'completed_visits' => 0, 'productive_visits' => 0, 'productivity_percent' => null,
    ]);
    $ignored = collect(app(DashboardCustomerDetails::class)->build($journeys, 'productive')['groups'])->flatMap(fn ($group) => $group['rows']);
    expect($ignored)->toHaveCount(6)->and($ignored->where('status', 'Ignored'))->toHaveCount(6);
});

test('efficiency drilldown groups repeated customers by journey and matches metric counts', function () {
    $journeys = DB::table('startendday')->whereIn('routekey', [1, 2])->get();
    $journeys->each(function ($journey) { $journey->routename = 'Route'; $journey->salesman = 'Salesman'; });
    $rows = collect(app(DashboardCustomerDetails::class)->build($journeys, 'efficiency')['groups'])->flatMap(fn ($group) => $group['rows']);
    $metrics = app(DashboardMetrics::class)->summarize($journeys);
    expect($rows)->toHaveCount($metrics['unique_visited_customers'])
        ->and($rows->where('status', 'Productive'))->toHaveCount($metrics['unique_productive_customers'])
        ->and($rows->where('customercode', 101))->toHaveCount(2);
});

test('journey analysis carries recorded visit intervals for the clock timeline without changing totals', function () {
    $result = app(DashboardMetrics::class)->summarize(DB::table('startendday')->where('routekey', 1)->get());
    $row = $result['analysis']['journeys'][0];
    expect($row['timeline'])->toBe([
        'start' => '2026-09-01 08:00:00',
        'end' => '2026-09-02 02:00:00',
        'visits' => [
            ['2026-09-01 10:00:00', '2026-09-01 10:20:00'],
            ['2026-09-01 10:30:00', '2026-09-01 10:40:00'],
        ],
        'otp_visits' => [['2026-09-01 10:00:00', '2026-09-01 10:20:00']],
    ])->and($row['visit_time'])->toEqual(30)
        ->and($row['duration'])->toEqual(1080);
});

test('efficiency counts unique visited and productive customers per journey', function () {
    $journeys = DB::table('startendday')->whereIn('routekey', [1, 2])->get();
    $service = app(DashboardMetrics::class);
    // Customer 101 has repeated productive visits and multiple documents in journey 1,
    // but only a collection and void invoice in journey 2. Incomplete visits count as visited.
    expect($service->summarize($journeys))->toMatchArray([
        'unique_visited_customers' => 5,
        'unique_productive_customers' => 2,
        'efficiency_percent' => 40.0,
        'productive_visits' => 3,
    ]);

    DB::table('salesorderheader')->insert([
        'routekey' => 2, 'visitkey' => 500, 'totalinvoiceamount' => 25, 'voidflag' => null,
    ]);
    // Adding an order to a collection-productive customer does not double-count the total.
    expect($service->summarize($journeys))->toMatchArray([
        'unique_visited_customers' => 5,
        'unique_productive_customers' => 2,
        'efficiency_percent' => 40.0,
    ]);
    expect($service->summarize(collect()))->toMatchArray([
        'unique_visited_customers' => 0,
        'unique_productive_customers' => 0,
        'efficiency_percent' => null,
    ]);
});

test('actual face time remains available when every planned CFT is zero or null', function () {
    DB::table('customervisitlog')->update(['cft' => 0]);
    DB::table('customervisitlog')->where('logkey', 11)->update(['cft' => null]);
    $result = app(DashboardMetrics::class)->summarize(DB::table('startendday')->whereIn('routekey', [1, 2])->get());
    expect($result['planned_cft_minutes'])->toEqual(0)
        ->and($result['cft_minutes'])->toEqual(25)
        ->and($result['completed_visits'])->toBe(5)
        ->and($result['cft_variance_minutes'])->toBeNull();
});

test('face time details retain individual visits and handle missing plans incomplete timing and overnight visits', function () {
    DB::table('customervisitlog')->where('logkey', 12)->update(['cft' => 20]);
    DB::table('customervisitlog')->where('logkey', 23)->update(['cft' => null]);
    DB::table('customervisitlog')->insert(['logkey' => 99, 'routekey' => 1, 'customercode' => 101, 'cft' => 20,
        'logstartdate' => '2026-09-01', 'logstarttime' => '23:50:00', 'logenddate' => '2026-09-02', 'logendtime' => '00:20:00']);
    $journeys = DB::table('startendday')->whereIn('routekey', [1, 2])->get();
    $journeys->each(function ($journey) { $journey->routename = 'Route'; $journey->salesman = 'Salesman'; });
    $groups = app(DashboardCustomerDetails::class)->build($journeys, 'cft')['groups'];
    expect($groups[0]['rows'])->toHaveCount(3)
        ->and($groups[0]['rows'][0])->toMatchArray(['planned_cft' => 0, 'actual_cft' => 0, 'variance' => null, 'otp_excluded' => true])
        ->and($groups[0]['rows'][1]['variance'])->toEqual(-10)
        ->and($groups[0]['rows'][2])->toMatchArray(['actual_cft' => 30, 'variance' => 10])
        ->and($groups[1]['rows'][1])->toMatchArray(['actual_cft' => null, 'variance' => null])
        ->and($groups[1]['rows'][2])->toMatchArray(['planned_cft' => 0, 'actual_cft' => 10, 'variance' => null])
        ->and($groups[1]['rows'][3])->toMatchArray(['planned_cft' => 0, 'actual_cft' => 5, 'variance' => null]);
});

test('transaction drilldowns list headers once and returns only from both sources with negative amounts', function () {
    foreach (['invoiceheader', 'salesorderheader', 'arheader'] as $table) {
        foreach (['customercode integer', 'transactionkey integer', 'documentnumber text', 'transactiondate text', 'transactiontime text'] as $column) DB::statement("ALTER TABLE {$table} ADD COLUMN {$column}");
        DB::table($table)->update(['customercode' => 101, 'transactionkey' => 1, 'documentnumber' => 'D1', 'transactiondate' => '2026-09-02', 'transactiontime' => '10:00:00']);
    }
    $journeys = DB::table('startendday')->where('routekey', 1)->get();
    $journeys->each(function ($journey) { $journey->routename = 'Route'; $journey->salesman = 'Salesman'; });
    $service = app(DashboardCustomerDetails::class);
    $sales = $service->build($journeys, 'sales')['groups'][0]['rows'];
    expect($sales)->toHaveCount(2)->and($sales->sum('amount'))->toEqual(150)->and($sales[0]['currency'])->toBe('OMR');
    expect($service->build($journeys, 'orders')['groups'][0]['rows']->sum('amount'))->toEqual(60);
    foreach (['invoiceheader', 'salesorderheader'] as $table) DB::table($table)->where('routekey', 1)->where('visitkey', 500)->update(['totalreturnamount' => 3, 'totaldamagedamount' => 2]);
    DB::table('invoiceheader')->insert(['routekey' => 1, 'voidflag' => 1, 'totalreturnamount' => 99]);
    $returns = $service->build($journeys, 'returns')['groups'][0]['rows'];
    expect($returns)->toHaveCount(3)->and($returns->sum('amount'))->toEqual(-15)
        ->and($returns->pluck('source')->unique()->values()->all())->toBe(['Invoice', 'Order']);
    $journeys = DB::table('startendday')->where('routekey', 2)->get();
    $journeys->each(function ($journey) { $journey->routename = 'Route'; $journey->salesman = 'Salesman'; });
    expect($service->build($journeys, 'collections')['groups'][0]['rows']->sum('amount'))->toEqual(75);
});

test('duration drilldown shares card timing for closed open and unavailable journeys', function () {
    $journeys = DB::table('startendday')->whereIn('routekey', [1, 2, 4])->get();
    $journeys->each(function ($journey) { $journey->routename = 'Route'; $journey->salesman = 'Salesman'; });
    $journeys[1]->last_location_time = '2026-09-03 12:00:00';
    $groups = app(DashboardCustomerDetails::class)->build($journeys, 'duration')['groups'];
    expect($groups[0]['rows'][0])->toMatchArray(['status' => 'Closed', 'duration' => 1080, 'end' => '2026-09-02 02:00:00'])
        ->and($groups[1]['rows'][0])->toMatchArray(['status' => 'Open', 'duration' => 240, 'end' => '2026-09-03 12:00:00'])
        ->and($groups[2]['rows'][0])->toMatchArray(['status' => 'Open', 'duration' => null, 'end' => null]);
});

test('returns combine both document sources and retain currencies without void or out of scope documents', function () {
    foreach (['invoiceheader', 'salesorderheader'] as $table) {
        foreach ([[1, 1, 0, 10, 5], [1, 2, 0, null, 7], [1, 1, 1, 99, 99], [1, 1, null, 99, 99], [3, 1, 0, 99, 99]] as [$route, $currency, $void, $good, $bad]) {
            DB::table($table)->insert(['routekey' => $route, 'currencycode' => $currency, 'voidflag' => $void, 'totalreturnamount' => $good, 'totaldamagedamount' => $bad]);
        }
    }
    $result = app(DashboardMetrics::class)->summarize(DB::table('startendday')->where('routekey', 1)->get());
    $returns = collect($result['amounts']['returns'])->keyBy('currency');
    expect($returns)->toHaveCount(2)
        ->and((float) $returns['OMR']['amount'])->toBe(30.0)
        ->and($returns['OMR']['documents'])->toBe(2)
        ->and((float) $returns['USD']['amount'])->toBe(14.0);
});

test('outside time subtracts completed operational time from the last GPS duration', function () {
    $journeys = DB::table('startendday')->where('routekey', 2)->get();
    $journeys->first()->last_location_time = '2026-09-03 12:00:00';
    $result = app(DashboardMetrics::class)->summarize($journeys);
    // Four-hour measured journey minus 45 minutes of completed visits; incomplete visits have no duration.
    expect($result['duration_minutes'])->toEqual(240)
        ->and($result['operational_minutes'])->toEqual(125)
        ->and($result['outside_visit_minutes'])->toEqual(195)
        ->and($result['duration_missing_journeys'])->toBe(0)
        ->and($result['unplanned_customers'])->toBe(3);
});

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
        'invoiceheader (routekey integer, visitkey integer, totalsalesamount decimal, totalinvoiceamount decimal, currencycode integer, voidflag integer, totalreturnamount decimal, totaldamagedamount decimal)',
        'salesorderheader (routekey integer, visitkey integer, totalinvoiceamount decimal, currencycode integer, voidflag integer, totalreturnamount decimal, totaldamagedamount decimal)',
        'arheader (routekey integer, visitkey integer, amountpaid decimal, currencycode integer, voidflag integer)',
        'currencymaster (currencycode integer, currencysymbol text)',
        'customermaster (customercode integer, alternatecode text, customeraddress1 text, toplpo integer)',
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
        DB::table('invoiceheader')->insert(['routekey' => $journey, 'visitkey' => $visit, 'totalsalesamount' => $amount, 'totalinvoiceamount' => $amount + 10, 'currencycode' => $currency, 'voidflag' => $void]);
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

test('customer drilldowns separate planned visited and not visited and deduplicate unplanned customers', function () {
    $journeys = DB::table('startendday')->whereIn('routekey', [1, 2])->get();
    $journeys->each(function ($journey) { $journey->routename = 'Route'; $journey->salesman = 'Salesman'; });
    DB::table('customermaster')->insert(['customercode' => 101, 'alternatecode' => 'C101', 'customeraddress1' => 'Customer One']);
    $service = app(DashboardCustomerDetails::class);
    $planned = $service->build($journeys, 'planned')['groups'];
    expect($planned)->toHaveCount(2)
        ->and($planned[0]['rows'])->toHaveCount(2)
        ->and($planned[0]['rows'][0])->toMatchArray(['customer_code' => 'C101', 'customer_name' => 'Customer One', 'status' => 'Visited with OTP', 'visit_count' => 2, 'otp_visit_count' => 1])
        ->and($planned[0]['rows'][1]['status'])->toBe('Not visited');
    $unplanned = $service->build($journeys, 'unplanned')['groups'];
    expect($unplanned)->toHaveCount(1)->and($unplanned[0]['rows'])->toHaveCount(3);
    DB::table('routesequencecustomerstatus')->where('routekey', 2)->delete();
    expect($service->build($journeys, 'unplanned')['groups'])->toHaveCount(0);
});

test('productive drilldown counts documents per completed visit without mixing journeys or void invoices', function () {
    $journeys = DB::table('startendday')->whereIn('routekey', [1, 2])->get();
    $journeys->each(function ($journey) { $journey->routename = 'Route'; $journey->salesman = 'Salesman'; });
    $groups = app(DashboardCustomerDetails::class)->build($journeys, 'productive')['groups'];
    expect($groups[0]['rows'][0])->toMatchArray(['is_revisit' => false, 'visit_number' => 1, 'status' => 'Productive', 'invoices' => 2, 'orders' => 1, 'collections' => 0])
        ->and($groups[0]['rows'][1])->toMatchArray(['is_revisit' => true, 'visit_number' => 2, 'status' => 'Productive', 'invoices' => 0, 'orders' => 1])
        ->and($groups[1]['rows'])->toHaveCount(3)
        ->and($groups[1]['rows'][0])->toMatchArray(['is_revisit' => false, 'visit_number' => 1, 'status' => 'Productive', 'invoices' => 0, 'orders' => 0, 'collections' => 1]);
});

test('OTP drilldown preserves all types and records overnight events under their route start date', function () {
    $journeys = DB::table('startendday')->whereIn('routekey', [1, 2])->get();
    $journeys->each(function ($journey) { $journey->routename = 'Route'; $journey->salesman = 'Salesman'; });
    DB::table('otplogdetail')->where('otplogid', 3)->update(['username' => 'Manager', 'comments' => 'Approved']);
    $groups = app(DashboardCustomerDetails::class)->build($journeys, 'otp')['groups'];
    expect($groups[0]['date'])->toBe('2026-09-01')->and($groups[0]['rows'])->toHaveCount(3)
        ->and($groups[0]['rows'][1]['otp_type'])->toBe('OTHER')
        ->and($groups[0]['rows'][2])->toMatchArray(['date' => '2026-09-02', 'recorded_by' => 'Manager', 'comments' => 'Approved'])
        ->and($groups[1]['rows'])->toHaveCount(1);
});

test('cards aggregate every selected journey without multiplying customers or transaction totals', function () {
    $result = app(DashboardMetrics::class)->summarize(DB::table('startendday')->whereIn('routekey', [1, 2])->get());
    expect($result)->toMatchArray([
        'journeys_started' => 2, 'unique_routes' => 1, 'routes_not_started' => null,
        'planned_customers' => 4, 'planned_visited' => 2, 'coverage_percent' => 50.0,
        'pending_customers' => 1, 'missed_customers' => 1, 'completed_visits' => 5, 'total_visits' => 6,
        'productive_visits' => 3, 'nonproductive_visits' => 2, 'productivity_percent' => 60.0,
        'cft_minutes' => 25.0, 'cft_variance_minutes' => -5.0, 'cft_configured_visits' => 2,
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
    expect($result)->toMatchArray(['journeys_started' => 0, 'total_visits' => 0, 'coverage_percent' => null, 'productivity_percent' => null,
        'cft_minutes' => 0.0, 'cft_variance_minutes' => null, 'otp' => ['events' => 0, 'visits' => 0]])
        ->and($result['amounts']['sales'])->toBe([]);
});

test('a return-only invoice does not change gross sales or make a visit productive', function () {
    DB::table('customeroperationscontrol')->insert(['primary_id' => 9, 'routekey' => 2, 'log_id' => 23, 'visitkey' => 777]);
    DB::table('invoiceheader')->insert(['routekey' => 2, 'visitkey' => 777, 'totalsalesamount' => 0, 'totalinvoiceamount' => -10, 'currencycode' => 1, 'voidflag' => 0]);
    $result = app(DashboardMetrics::class)->summarize(DB::table('startendday')->whereIn('routekey', [1, 2])->get());
    expect($result['productive_visits'])->toBe(3)
        ->and((float) $result['amounts']['sales'][0]['amount'])->toBe(150.0);
});

test('analysis exposes journey coverage, repeat visits, OTP details and missing data honestly', function () {
    $result = app(DashboardMetrics::class)->summarize(DB::table('startendday')->whereIn('routekey', [1, 2])->get());
    $rows = collect($result['analysis']['journeys'])->keyBy('routekey');
    expect($rows[1])->toMatchArray(['planned' => 2, 'covered' => 1, 'missed' => 1, 'repeat' => 1, 'otp' => 3]);
    expect($rows[2])->toMatchArray(['pending' => 1, 'unplanned' => 3, 'incomplete_visits' => 1, 'missing_cft' => 1, 'duration' => null, 'distance' => null, 'stationary_time' => null]);
    expect($rows[1]['otp_events'])->toHaveCount(3)
        ->and(collect($rows[1]['issues'])->pluck('label')->all())->toContain('Missed customers', 'Repeat visits');
});

test('time chart uses operational visit totals and distance requires completed valid readings', function () {
    DB::table('customervisitlog')->insert(['logkey' => 13, 'routekey' => 1, 'customercode' => 101,
        'logstartdate' => '2026-09-01', 'logstarttime' => '10:10:00', 'logenddate' => '2026-09-01', 'logendtime' => '10:35:00', 'cft' => 10]);
    $journeys = DB::table('startendday')->whereIn('routekey', [1, 2])->get();
    foreach ($journeys as $journey) {
        $journey->routestartodometer = 1234;
        $journey->routeendodometer = 1250;
    }
    $rows = collect(app(DashboardMetrics::class)->summarize($journeys)['analysis']['journeys'])->keyBy('routekey');
    expect($rows[1]['actual_cft'])->toEqual(10)
        ->and($rows[1]['visit_time'])->toEqual(55)
        ->and($rows[1]['remaining_time'])->toEqual($rows[1]['duration'] - 55)
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


test('dashboard operational and OTP time cards reconcile with customer detail rows', function () {
    $journeys = DB::table('startendday')->whereIn('routekey', [1, 2])->get();
    $journeys->each(function ($journey) { $journey->routename = 'Route'; $journey->salesman = 'Salesman'; });
    $journeys[1]->last_location_time = '2026-09-03 12:00:00';
    DB::table('customermaster')->insert(['customercode' => 101, 'alternatecode' => 'C101', 'customeraddress1' => 'Customer One']);
    $metrics = app(DashboardMetrics::class)->summarize($journeys);
    expect($metrics['operational_minutes'])->toEqual(135)
        ->and($metrics['otp_customer_minutes'])->toEqual(50)
        ->and($metrics['actual_face_minutes'])->toEqual(25)
        ->and($metrics['outside_visit_minutes'])->toEqual($metrics['duration_minutes'] - 75);

    $details = app(DashboardCustomerDetails::class);
    $operational = collect($details->build($journeys, 'operational')['groups'])->flatMap(fn ($group) => $group['rows']);
    $otpGroups = $details->build($journeys, 'otp_time')['groups'];
    $otp = collect($otpGroups)->flatMap(fn ($group) => $group['rows']);
    $face = collect($details->build($journeys, 'actual_face')['groups'])->flatMap(fn ($group) => $group['rows']);
    expect($operational)->toHaveCount(2)
        ->and($operational->sum('actual_cft'))->toEqual($metrics['operational_minutes'])
        ->and($otp)->toHaveCount(2)
        ->and($otp->sum('actual_cft'))->toEqual($metrics['otp_customer_minutes'])
        ->and($face)->toHaveCount(6)
        ->and($face->sum('actual_cft'))->toEqual($metrics['actual_face_minutes'])
        ->and($otpGroups[0])->toMatchArray(['routecode' => 1, 'date' => '2026-09-01'])
        ->and($otp[0])->toMatchArray(['customer_code' => 'C101', 'customer_name' => 'Customer One',
            'check_in' => '2026-09-01 10:00:00', 'check_out' => '2026-09-01 10:20:00', 'actual_cft' => 20,
            'otp_times' => ['2026-09-01 10:05:00', '2026-09-01 10:06:00']]);
    // A repeat visit to the same customer without an OTP stays in actual face time.
    expect($face->pluck('id')->all())->toContain(12);
    $outside = collect($details->build($journeys, 'outside')['groups'])->flatMap(fn ($group) => $group['rows']);
    expect($outside->sum('operational'))->toEqual($metrics['recorded_visit_minutes'])
        ->and($outside->sum('outside'))->toEqual($metrics['outside_visit_minutes']);
});

test('new dashboard time cards handle unavailable duration and no OTP matches', function () {
    DB::table('otplogdetail')->delete();
    $journeys = DB::table('startendday')->where('routekey', 2)->get();
    $metrics = app(DashboardMetrics::class)->summarize($journeys);
    expect($metrics['operational_minutes'])->toEqual(245)
        ->and($metrics['otp_customer_minutes'])->toEqual(0)
        ->and($metrics['actual_face_minutes'])->toEqual(45)
        ->and($metrics['outside_visit_minutes'])->toBeNull();
    $empty = app(DashboardMetrics::class)->summarize(collect());
    expect($empty['operational_minutes'])->toBeNull()
        ->and($empty['otp_customer_minutes'])->toEqual(0)
        ->and($empty['actual_face_minutes'])->toEqual(0);
});


test('OTP before check-in uses the nearest customer visit in the same journey without counting requests twice', function () {
    DB::table('otplogdetail')->delete();
    foreach ([[1, 101, '2026-09-01', '09:58:00'], [2, 101, '2026-09-01', '09:59:00'],
        [3, 101, '2026-09-01', '10:29:00'], [4, 999, '2026-09-01', '10:00:00'],
        [5, 101, '2026-09-03', '08:59:00']] as [$id, $customer, $date, $time]) {
        DB::table('otplogdetail')->insert(['otplogid' => $id, 'routecode' => 1, 'customercode' => $customer,
            'otpdate' => $date, 'otptime' => $time, 'otptype' => 'GPS IN']);
    }
    $journeys = DB::table('startendday')->where('routekey', 1)->get();
    $journeys->each(function ($journey) { $journey->routename = 'Route'; $journey->salesman = 'Salesman'; });
    $metrics = app(DashboardMetrics::class)->summarize($journeys);
    expect($metrics['otp_customer_minutes'])->toEqual(30)
        ->and($metrics['actual_face_minutes'])->toEqual(0)
        ->and($metrics['otp'])->toBe(['events' => 4, 'visits' => 2]);
    $rows = app(DashboardCustomerDetails::class)->build($journeys, 'otp_time')['groups'][0]['rows'];
    expect($rows)->toHaveCount(2)
        ->and($rows->sum('actual_cft'))->toEqual(30)
        ->and($rows[0]['otp_times'])->toBe(['2026-09-01 09:58:00', '2026-09-01 09:59:00'])
        ->and($rows[1]['otp_times'])->toBe(['2026-09-01 10:29:00']);
});

test('OTP matched to an incomplete visit does not borrow time from another completed visit', function () {
    DB::table('otplogdetail')->delete();
    DB::table('customervisitlog')->where('logkey', 12)->update(['logendtime' => null]);
    DB::table('otplogdetail')->insert(['otplogid' => 1, 'routecode' => 1, 'customercode' => 101,
        'otpdate' => '2026-09-01', 'otptime' => '10:29:00', 'otptype' => 'GPS IN']);
    $metrics = app(DashboardMetrics::class)->summarize(DB::table('startendday')->where('routekey', 1)->get());
    expect($metrics['otp_customer_minutes'])->toEqual(0)
        ->and($metrics['actual_face_minutes'])->toEqual(20);
});


test('route transaction cards and documents match dashboard totals without requiring visit links', function () {
    foreach (['invoiceheader', 'salesorderheader', 'arheader'] as $table) {
        foreach (['customercode integer', 'transactionkey integer', 'documentnumber text', 'transactiondate text', 'transactiontime text'] as $column) DB::statement("ALTER TABLE {$table} ADD COLUMN {$column}");
        DB::table($table)->update(['customercode' => 101, 'transactionkey' => 1, 'documentnumber' => 'D1', 'transactiondate' => '2026-09-01', 'transactiontime' => '10:00:00']);
    }
    // Include headers with no visit link and null void flags, but exclude void documents.
    foreach ([null, 0, 1, 2] as $void) {
        DB::table('invoiceheader')->insert(['routekey' => 1, 'customercode' => 101, 'transactionkey' => 99,
            'totalsalesamount' => 15, 'totalreturnamount' => 4, 'totaldamagedamount' => 1, 'currencycode' => 2, 'voidflag' => $void]);
        DB::table('salesorderheader')->insert(['routekey' => 1, 'customercode' => 101, 'transactionkey' => 99,
            'totalinvoiceamount' => 25, 'totalreturnamount' => 3, 'currencycode' => 1, 'voidflag' => $void]);
        DB::table('arheader')->insert(['routekey' => 1, 'customercode' => 101, 'transactionkey' => 99,
            'amountpaid' => 35, 'currencycode' => 1, 'voidflag' => $void]);
    }
    $dashboard = app(DashboardMetrics::class)->summarize(DB::table('startendday')->where('routekey', 1)->get());
    $tracking = (new ReflectionMethod(\App\Http\Controllers\RouteTracking\RouteTrackingController::class, 'summarizeTransactions'))
        ->invoke(app(\App\Http\Controllers\RouteTracking\RouteTrackingController::class), 1);
    foreach (['sales', 'orders', 'collections', 'returns'] as $type) {
        $expected = collect($dashboard['amounts'][$type])->keyBy('currency');
        expect($tracking[$type]['count'])->toBe($expected->sum('documents'));
        foreach ($tracking[$type]['amounts'] as $amount) {
            expect(abs($amount['amount']))->toEqual(abs((float) $expected[$amount['currency']]['amount']));
            $documents = collect($tracking[$type]['documents'])->where('currency', $amount['currency']);
            expect($documents->sum('amount'))->toEqual($amount['amount']);
        }
    }
});


test('face time variance excludes OTP planned allowances and incomplete visits', function ($target, $expectedPlan, $expectedVariance) {
    DB::table('customervisitlog')->whereIn('logkey', [11, 21, 22])->update(['cft' => 999]);
    DB::table('customervisitlog')->whereIn('logkey', [12, 23])->update(['cft' => $target]);
    $metrics = app(DashboardMetrics::class)->summarize(DB::table('startendday')->whereIn('routekey', [1, 2])->get());
    expect($metrics['actual_face_minutes'])->toEqual(25)
        ->and($metrics['planned_face_minutes'])->toEqual($expectedPlan)
        ->and($metrics['face_time_variance_percent'])->toEqual($expectedVariance);
})->with([
    'below plan' => [20, 40, -37.5],
    'above plan' => [5, 10, 150.0],
    'no plan' => [0, 0, null],
]);


test('all-route outside time totals available journeys without subtracting visits from unavailable journeys', function () {
    DB::table('startendday')->where('routekey', 1)->update(['routeenddate' => '2026-09-01', 'routeendtime' => '11:00:00']);
    DB::table('customervisitlog')->where('logkey', 21)->update(['logenddate' => '2026-09-04']);
    $journeys = DB::table('startendday')->whereIn('routekey', [1, 2])->get();
    $journeys->each(function ($journey) { $journey->routename = 'Route'; $journey->salesman = 'Salesman'; });
    $service = app(DashboardMetrics::class);
    $single = $service->summarize($journeys->where('routekey', 1));
    $all = $service->summarize($journeys);
    expect($single['outside_visit_minutes'])->toEqual(150)
        ->and($all['outside_visit_minutes'])->toEqual($single['outside_visit_minutes'])
        ->and($all['duration_missing_journeys'])->toBe(1);
    $rows = collect(app(DashboardCustomerDetails::class)->build($journeys, 'outside')['groups'])->flatMap(fn ($group) => $group['rows']);
    expect($rows->sum('outside'))->toEqual($all['outside_visit_minutes']);

    // A measured journey whose visits exceed its duration cannot cancel another journey's outside time.
    $journeys[1]->last_location_time = '2026-09-03 10:00:00';
    $all = $service->summarize($journeys);
    expect($all['outside_visit_minutes'])->toEqual(150)
        ->and($all['duration_missing_journeys'])->toBe(0);
});


test('planned customer OTP percentages deduplicate customers within each journey and keep groups disjoint', function () {
    $journeys = DB::table('startendday')->whereIn('routekey', [1, 2])->get();
    $service = app(DashboardMetrics::class);
    // Customer 101 has repeat visits and multiple OTP requests in journey 1,
    // including a non-OTP repeat. It belongs only to the OTP customer group.
    $metrics = $service->summarize($journeys);
    expect($metrics)->toMatchArray([
        'planned_customers' => 4, 'planned_visited_without_otp' => 0,
        'planned_visited_with_otp' => 2, 'planned_without_otp_percent' => 0.0,
        'planned_with_otp_percent' => 50.0,
    ]);
    // The same customer in a second journey counts separately, without OTP.
    DB::table('otplogdetail')->where('otplogid', 5)->delete();
    $metrics = $service->summarize($journeys);
    expect($metrics)->toMatchArray([
        'planned_visited_without_otp' => 1, 'planned_visited_with_otp' => 1,
        'planned_without_otp_percent' => 25.0, 'planned_with_otp_percent' => 25.0,
    ]);
    // OTP on an unplanned customer cannot inflate either planned numerator.
    DB::table('otplogdetail')->insert(['otplogid' => 90, 'routecode' => 1, 'customercode' => 104,
        'otpdate' => '2026-09-03', 'otptime' => '11:00:00', 'otptype' => 'OTHER']);
    expect($service->summarize($journeys)['planned_visited_with_otp'])->toBe(1);
    $details = app(DashboardCustomerDetails::class)->build($journeys, 'planned')['groups'];
    expect($details[1]['rows'][0]['status'])->toBe('Visited without OTP');
    expect($service->summarize(collect()))->toMatchArray([
        'planned_visited_without_otp' => 0, 'planned_visited_with_otp' => 0,
        'planned_without_otp_percent' => null, 'planned_with_otp_percent' => null,
    ]);
});


test('unplanned OTP customers are separate, deduplicated and require a journey plan', function () {
    $journeys = DB::table('startendday')->whereIn('routekey', [1, 2])->get();
    DB::table('otplogdetail')->insert(['otplogid' => 90, 'routecode' => 1, 'customercode' => 104,
        'otpdate' => '2026-09-03', 'otptime' => '11:00:00', 'otptype' => 'OTHER']);
    DB::table('customervisitlog')->insert(['logkey' => 90, 'routekey' => 2, 'customercode' => 104,
        'logstartdate' => '2026-09-03', 'logstarttime' => '14:00:00',
        'logenddate' => '2026-09-03', 'logendtime' => '14:10:00', 'cft' => 10]);
    $service = app(DashboardMetrics::class);
    expect($service->summarize($journeys))->toMatchArray([
        'unplanned_customers' => 3, 'unplanned_customers_without_otp' => 2,
        'unplanned_customers_with_otp' => 1,
        'all_unique_visited_customers' => 5,
        'unplanned_without_otp_percent' => 40.0, 'unplanned_with_otp_percent' => 20.0,
    ]);
    $rows = collect(app(DashboardCustomerDetails::class)->build($journeys, 'unplanned')['groups'][0]['rows']);
    expect($rows->firstWhere('customercode', 104))->toMatchArray([
        'status' => 'Visited with OTP', 'visit_count' => 2, 'otp_visit_count' => 1,
    ]);
    expect($rows->firstWhere('customercode', 105)['status'])->toBe('Visited without OTP');
    DB::table('routesequencecustomerstatus')->where('routekey', 2)->delete();
    expect($service->summarize($journeys))->toMatchArray([
        'unplanned_customers_without_otp' => 0, 'unplanned_customers_with_otp' => 0,
    ]);
    expect($service->summarize(collect()))->toMatchArray([
        'unplanned_customers_without_otp' => 0, 'unplanned_customers_with_otp' => 0,
        'all_unique_visited_customers' => 0,
        'unplanned_without_otp_percent' => null, 'unplanned_with_otp_percent' => null,
    ]);
});
