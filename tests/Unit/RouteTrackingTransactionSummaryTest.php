<?php

use App\Http\Controllers\RouteTracking\RouteTrackingController;
use Illuminate\Support\Facades\DB;

uses(Tests\TestCase::class);

test('route efficiency ignores every toplpo visit in numerator and denominator', function () {
    $method = new ReflectionMethod(RouteTrackingController::class, 'summarizeEfficiency');
    $controller = app(RouteTrackingController::class);
    $productive = ['visit_duration_minutes' => 10, 'transactions' => ['sales' => [['amount' => 10, 'voided' => false]]]];
    $visits = collect([
        $productive + ['customercode' => 1, 'toplpo' => 1],
        $productive + ['customercode' => 1, 'toplpo' => '1'],
        $productive + ['customercode' => 2, 'toplpo' => 0],
        ['customercode' => 3, 'toplpo' => null],
        ['customercode' => 4, 'toplpo' => 2],
        ['customercode' => 5, 'toplpo' => 1],
    ]);
    expect($method->invoke($controller, $visits))->toMatchArray([
        'unique_visited_customers' => 3, 'unique_productive_customers' => 1, 'efficiency_percent' => 33.3,
    ])->and($method->invoke($controller, $visits->filter(fn ($visit) => (int) ($visit['toplpo'] ?? 0) === 1)))->toMatchArray([
        'unique_visited_customers' => 0, 'unique_productive_customers' => 0, 'efficiency_percent' => null,
    ]);
});

test('route efficiency combines collections and sales orders without double counting overlaps', function () {
    $document = fn ($amount, $voided = false) => ['amount' => $amount, 'voided' => $voided];
    $visit = fn ($customer, $transactions, $duration = 10) => [
        'customercode' => $customer, 'visit_duration_minutes' => $duration, 'transactions' => $transactions,
    ];
    $visits = collect([
        $visit(1, ['sales' => [$document(20), $document(30)], 'orders' => [$document(10)], 'collections' => [$document(10), $document(20)]]),
        $visit(1, ['orders' => [$document(40)]]),
        $visit(2, ['collections' => [$document(50)]]),
        $visit(3, ['sales' => [$document(100, true), $document(0), $document(-10)]]),
        $visit(4, ['orders' => [$document(20)]], null),
        $visit(5, []),
        $visit(5, ['orders' => [$document(10)]]),
    ]);
    $method = new ReflectionMethod(RouteTrackingController::class, 'summarizeEfficiency');
    $controller = app(RouteTrackingController::class);
    expect($method->invoke($controller, $visits))->toMatchArray([
        'unique_visited_customers' => 5,
        'unique_productive_customers' => 3,
        'efficiency_percent' => 60.0,
        'collection_productive_customers' => 2, 'sales_order_productive_customers' => 2,
        'collection_efficiency_percent' => 40.0, 'sales_order_efficiency_percent' => 40.0,
    ])->and($method->invoke($controller, collect()))->toMatchArray([
        'unique_visited_customers' => 0,
        'unique_productive_customers' => 0,
        'efficiency_percent' => null,
        'collection_efficiency_percent' => null, 'sales_order_efficiency_percent' => null,
    ])->and($method->invoke($controller, collect([$visit(1, [])]))['efficiency_percent'])->toBe(0.0);
});

test('visit transactions use dashboard amount fields and void rules', function () {
    config(['database.default' => 'transaction_sources_test', 'database.connections.transaction_sources_test' => [
        'driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '',
    ]]);
    DB::purge('transaction_sources_test');
    foreach (['invoiceheader', 'salesorderheader', 'arheader'] as $table) {
        DB::statement("CREATE TABLE {$table} (transactionkey integer, routekey integer, visitkey integer, documentnumber text, transactiondate text, transactiontime text, totalsalesamount real, totalinvoiceamount real, totalreturnamount real, totaldamagedamount real, amountpaid real, voidflag integer)");
    }
    foreach (['invoiceheader', 'salesorderheader'] as $table) {
        foreach ([0, 1, null, 2] as $index => $flag) {
            DB::table($table)->insert([
                'transactionkey' => $index + 1, 'routekey' => 7, 'visitkey' => 8,
                'documentnumber' => 'DOC'.$index, 'totalsalesamount' => 100,
                'totalinvoiceamount' => 70, 'totalreturnamount' => 20,
                'totaldamagedamount' => 10, 'voidflag' => $flag,
            ]);
        }
        DB::table($table)->insert([
            'transactionkey' => 5, 'routekey' => 7, 'visitkey' => 8,
            'totalsalesamount' => 50, 'totalreturnamount' => null,
            'totaldamagedamount' => null, 'voidflag' => 0,
        ]);
    }
    $controller = app(RouteTrackingController::class);
    $visits = (new ReflectionMethod(RouteTrackingController::class, 'attachVisitTransactions'))
        ->invoke($controller, collect([['visitkey' => 8]]), 7);
    expect($visits[0]['transactions']['sales'])->toHaveCount(3)
        ->and($visits[0]['transactions']['sales']->sum('amount'))->toEqual(250)
        ->and($visits[0]['transactions']['orders']->sum('amount'))->toEqual(140)
        ->and($visits[0]['transactions']['sales']->sum('return_amount'))->toEqual(30)
        ->and($visits[0]['transactions']['orders']->sum('return_amount'))->toEqual(30);
});

test('transaction summary for an absent journey is empty', function () {
    $summary = (new ReflectionMethod(RouteTrackingController::class, 'summarizeTransactions'))
        ->invoke(app(RouteTrackingController::class), null);
    foreach ($summary as $type) expect($type)->toBe(['count' => 0, 'amounts' => [], 'documents' => []]);
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
