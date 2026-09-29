<?php

use App\Http\Controllers\RouteTracking\RouteTrackingController;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;

uses(Tests\TestCase::class);

test('a routing connection failure is attempted once per controller request', function () {
    $attempts = 0;
    Http::fake(function () use (&$attempts) {
        $attempts++;
        throw new ConnectionException('Routing unavailable');
    });
    $controller = new RouteTrackingController;
    $method = new ReflectionMethod($controller, 'routingGet');
    foreach (['/route/v1/driving/1,2;3,4', '/match/v1/driving/1,2;3,4'] as $path) {
        expect(fn () => $method->invoke($controller, $path, []))->toThrow(ConnectionException::class);
    }
    expect($attempts)->toBe(1);
    // A later request may recover; failures are not cached globally.
    expect(fn () => $method->invoke(new RouteTrackingController, '/route/v1/driving/1,2;3,4', []))->toThrow(ConnectionException::class);
    expect($attempts)->toBe(2);
});

test('healthy routing continues to request every required leg', function () {
    Http::fake(['*' => Http::response(['code' => 'Ok'], 200)]);
    $controller = new RouteTrackingController;
    $method = new ReflectionMethod($controller, 'routingGet');
    for ($i = 0; $i < 2; $i++) {
        expect($method->invoke($controller, '/route/v1/driving/1,2;3,4', [])->json('code'))->toBe('Ok');
    }
    Http::assertSentCount(2);
});
