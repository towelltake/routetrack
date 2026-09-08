<?php

use App\Services\StationaryDetection;

uses(Tests\TestCase::class);

function stationaryPoints(int $seconds = 300, float $step = 0): array
{
    $points = [];
    for ($t = 0; $t <= $seconds; $t += 30) {
        $points[] = (object) [
            'latitude' => 23.5 + $step * $t / 30, 'longitude' => 58.5,
            'effective_timestamp' => date('Y-m-d H:i:s', strtotime('2026-09-08 08:00:00') + $t),
            'accuracy_m' => 10, 'provider' => 'gps',
        ];
    }

    return $points;
}

beforeEach(function () {
    config(['tracking.stationary_minutes' => 5, 'tracking.stationary_radius_m' => 30,
        'tracking.stationary_max_gap_seconds' => 120, 'tracking.max_accuracy_m' => 50]);
});

test('stationary duration starts at first reading and honours configurable threshold', function () {
    $detector = new StationaryDetection;
    expect($detector->detect(stationaryPoints(270)))->toBe([]);
    $stops = $detector->detect(stationaryPoints());
    expect($stops)->toHaveCount(1)
        ->and($stops[0]['duration_seconds'])->toBe(300)
        ->and($stops[0]['start_time'])->toBe('2026-09-08 08:00:00')
        ->and($stops[0]['accuracy_unknown'])->toBeFalse();
    config(['tracking.stationary_minutes' => 6]);
    expect($detector->detect(stationaryPoints()))->toBe([]);
});

test('legacy readings work and poor or invalid coordinates are rejected', function () {
    $detector = new StationaryDetection;
    $points = stationaryPoints();
    foreach ($points as $point) {
        unset($point->accuracy_m, $point->provider);
    }
    expect($detector->detect($points)[0]['accuracy_unknown'])->toBeTrue();
    $point = $points[0];
    foreach ([null, 0, 49.9] as $accuracy) {
        $point->accuracy_m = $accuracy;
        expect($detector->usable($point))->toBeTrue();
    }
    foreach ([50, 80, -1, 'bad'] as $accuracy) {
        $point->accuracy_m = $accuracy;
        expect($detector->usable($point))->toBeFalse();
    }
    $point->accuracy_m = 10;
    $point->latitude = 100;
    expect($detector->usable($point))->toBeFalse();
});

test('reporting gaps and rejected accuracy never become stationary duration', function () {
    $detector = new StationaryDetection;
    $points = stationaryPoints(900);
    expect($detector->detect([$points[0], $points[30]]))->toBe([]);
    foreach ($points as $index => $point) {
        if ($index > 5 && $index < 25) $point->accuracy_m = 70;
    }
    expect($detector->detect($points))->toBe([]);
    $stops = $detector->detect([...stationaryPoints(), ...array_slice(stationaryPoints(900), 20)]);
    expect(array_column($stops, 'duration_seconds'))->toBe([300, 300]);
});

test('isolated jump is tolerated but successive outside readings end the stop', function () {
    $detector = new StationaryDetection;
    $points = stationaryPoints(600);
    $points[5]->latitude += 0.01;
    $points[12]->latitude += 0.01;
    $points[13]->latitude += 0.02;
    for ($i = 14; $i < count($points); $i++) $points[$i]->latitude += 0.03;
    $stops = $detector->detect($points);
    expect($stops)->toHaveCount(1)->and($stops[0]['duration_seconds'])->toBe(330);
});

test('fixed anchor rejects slow drift and duplicate timestamps cannot extend a stop', function () {
    $detector = new StationaryDetection;
    expect($detector->detect(stationaryPoints(900, 0.00006)))->toBe([]);
    expect($detector->detect(array_fill(0, 20, stationaryPoints()[0])))->toBe([]);
    expect($detector->detect([]))->toBe([]);
});

test('actual route detects legacy stationary pings before downsampling and filters poor accuracy', function () {
    $points = stationaryPoints();
    foreach ($points as $point) unset($point->accuracy_m, $point->provider);
    $points[] = (object) ['latitude' => 23.8, 'longitude' => 58.5,
        'accuracy_m' => 70, 'effective_timestamp' => '2026-09-08 08:05:30'];
    $query = Mockery::mock();
    $query->shouldReceive('where', 'whereIn', 'whereNotNull', 'selectRaw', 'orderBy')->andReturnSelf();
    $query->shouldReceive('get')->once()->andReturn(collect($points));
    $connection = Mockery::mock();
    $connection->shouldReceive('table')->with('trac_routetrack')->andReturn($query);
    \Illuminate\Support\Facades\DB::shouldReceive('connection')->with('tracking_pgsql')->andReturn($connection);
    $journey = Mockery::mock();
    $journey->shouldReceive('where', 'orderByDesc')->andReturnSelf();
    $journey->shouldReceive('first')->andReturn((object) [
        'routestartdate' => '2026-09-08', 'routestarttime' => '08:00:00',
    ]);
    \Illuminate\Support\Facades\DB::shouldReceive('table')->with('startendday')->andReturn($journey);
    \Illuminate\Support\Facades\Http::fake(['*' => \Illuminate\Support\Facades\Http::response(['code' => 'NoMatch'])]);
    $method = new ReflectionMethod(\App\Http\Controllers\RouteTracking\RouteTrackingController::class, 'computeMatchedActual');
    $actual = $method->invoke(app(\App\Http\Controllers\RouteTracking\RouteTrackingController::class), 1, '2026-09-08');
    expect($actual['has_tracking_data'])->toBeTrue()
        ->and($actual['stationary_seconds'])->toBe(300)
        ->and($actual['stationary_periods'])->toHaveCount(1)
        ->and($actual['stationary_periods'][0]['accuracy_unknown'])->toBeTrue()
        ->and($actual['point_count'])->toBe(2)
        ->and($actual['end']['time'])->toBe('2026-09-08 08:05:00')
        ->and($actual['distance'])->toEqual(0);
});
