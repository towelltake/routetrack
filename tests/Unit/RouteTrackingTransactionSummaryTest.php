<?php

use App\Http\Controllers\RouteTracking\RouteTrackingController;

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
