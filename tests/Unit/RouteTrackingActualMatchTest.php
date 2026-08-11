<?php

use App\Http\Controllers\RouteTracking\RouteTrackingController;
use Illuminate\Support\Facades\Http;

uses(Tests\TestCase::class);

function actualMatchController(): RouteTrackingController
{
    return new class extends RouteTrackingController
    {
        public function matchPoints(array $points): array
        {
            return $this->matchActualPoints($points);
        }
    };
}

function gpsPoints(int $count, ?array $timestamps = null): array
{
    $start = strtotime('2026-08-11 08:00:00');

    return array_map(fn (int $index) => (object) [
        'latitude' => 23.5 + ($index * 0.00001),
        'longitude' => 58.4 + ($index * 0.00001),
        'effective_timestamp' => $timestamps[$index] ?? date('Y-m-d H:i:s', $start + $index),
    ], range(0, $count - 1));
}

function osrmMatchingResponse(int $pointCount, float $distance = 100, float $duration = 10): array
{
    return [
        'code' => 'Ok',
        'tracepoints' => array_fill(0, $pointCount, ['matchings_index' => 0, 'waypoint_index' => 0]),
        'matchings' => [[
            'confidence' => 1,
            'distance' => $distance,
            'duration' => $duration,
            'geometry' => ['type' => 'LineString', 'coordinates' => [[58.4, 23.5], [58.5, 23.6]]],
        ]],
    ];
}

it('matches overlapping chunks without double counting points or metrics', function () {
    Http::fakeSequence()
        ->push(osrmMatchingResponse(100, 1000, 100))
        ->push(osrmMatchingResponse(2, 200, 20));

    $result = actualMatchController()->matchPoints(gpsPoints(101));

    expect($result)
        ->geometry_source->toBe('osrm_match')
        ->chunks_attempted->toBe(2)
        ->matched_point_count->toBe(101)
        ->unmatched_point_count->toBe(0)
        ->matched_geometry_count->toBe(2)
        ->fallback_geometry_count->toBe(0)
        ->distance->toBe(1200.0)
        ->duration->toBe(120.0);

    $requests = Http::recorded();
    $firstCoordinates = explode(';', substr(parse_url($requests[0][0]->url(), PHP_URL_PATH), strlen('/match/v1/driving/')));
    $secondCoordinates = explode(';', substr(parse_url($requests[1][0]->url(), PHP_URL_PATH), strlen('/match/v1/driving/')));

    expect($firstCoordinates)->toHaveCount(100)
        ->and($secondCoordinates)->toHaveCount(2)
        ->and($secondCoordinates[0])->toBe($firstCoordinates[99]);
});

it('keeps successful chunks around a completely failed chunk', function () {
    Http::fakeSequence()
        ->push(osrmMatchingResponse(100, 1000, 100))
        ->pushStatus(500)
        ->push(osrmMatchingResponse(52, 500, 50));

    $points = gpsPoints(250);
    $result = actualMatchController()->matchPoints($points);
    $fallbackCoordinates = $result['geometries'][1]['coordinates'];

    expect($result)
        ->geometry_source->toBe('mixed')
        ->chunks_failed->toBe(1)
        ->matched_geometry_count->toBe(2)
        ->fallback_geometry_count->toBe(1)
        ->duration->toBe(249.0)
        ->and($fallbackCoordinates[0])->toBe([(float) $points[99]->longitude, (float) $points[99]->latitude])
        ->and($fallbackCoordinates[count($fallbackCoordinates) - 1])->toBe([(float) $points[198]->longitude, (float) $points[198]->latitude]);
});

it('interleaves raw geometry for unmatched tracepoints in a successful response', function () {
    Http::fake([
        '*' => Http::response([
            'code' => 'Ok',
            'tracepoints' => [
                ['matchings_index' => 0],
                ['matchings_index' => 0],
                null,
                null,
                ['matchings_index' => 1],
            ],
            'matchings' => [
                [
                    'confidence' => 1,
                    'distance' => 100,
                    'duration' => 10,
                    'geometry' => ['type' => 'LineString', 'coordinates' => [[58.4, 23.5], [58.41, 23.51]]],
                ],
                [
                    'confidence' => 1,
                    'distance' => 100,
                    'duration' => 10,
                    'geometry' => ['type' => 'LineString', 'coordinates' => [[58.43, 23.53], [58.44, 23.54]]],
                ],
            ],
        ]),
    ]);

    $points = gpsPoints(5);
    $result = actualMatchController()->matchPoints($points);

    expect($result)
        ->geometry_source->toBe('mixed')
        ->matched_point_count->toBe(3)
        ->unmatched_point_count->toBe(2)
        ->matched_geometry_count->toBe(2)
        ->fallback_geometry_count->toBe(1)
        ->chunks_partially_matched->toBe(1)
        ->used_fallback_geometry->toBeTrue()
        ->duration->toBe(23.0)
        ->and($result['geometries'][1]['coordinates'])->toBe([
            [(float) $points[1]->longitude, (float) $points[1]->latitude],
            [(float) $points[2]->longitude, (float) $points[2]->latitude],
            [(float) $points[3]->longitude, (float) $points[3]->latitude],
            [(float) $points[4]->longitude, (float) $points[4]->latitude],
        ]);
});

it('uses raw gps when a chunk has no usable matching', function () {
    Http::fake(['*' => Http::response(['code' => 'NoMatch'], 200)]);

    $result = actualMatchController()->matchPoints(gpsPoints(4));

    expect($result)
        ->geometry_source->toBe('raw_gps')
        ->matched_point_count->toBe(0)
        ->unmatched_point_count->toBe(4)
        ->matched_geometry_count->toBe(0)
        ->fallback_geometry_count->toBe(1)
        ->chunks_failed->toBe(1)
        ->duration->toBe(3.0)
        ->and($result['geometries'][0]['coordinates'])->toHaveCount(4);
});

it('does not draw or measure raw fallback across a long gps time gap', function () {
    Http::fake(['*' => Http::response(['code' => 'NoMatch'], 200)]);
    $timestamps = [
        '2026-08-11 08:00:00',
        '2026-08-11 08:00:01',
        '2026-08-11 08:20:00',
        '2026-08-11 08:20:01',
    ];

    $result = actualMatchController()->matchPoints(gpsPoints(4, $timestamps));

    expect($result)
        ->geometry_source->toBe('raw_gps')
        ->fallback_geometry_count->toBe(2)
        ->duration->toBe(2.0)
        ->and($result['geometries'][0]['coordinates'])->toHaveCount(2)
        ->and($result['geometries'][1]['coordinates'])->toHaveCount(2);
});

it('returns none without requesting osrm when fewer than two points exist', function () {
    Http::fake();

    $result = actualMatchController()->matchPoints(gpsPoints(1));

    expect($result)
        ->has_tracking_data->toBeFalse()
        ->geometry_source->toBe('none')
        ->matched_point_count->toBe(0)
        ->unmatched_point_count->toBe(1)
        ->matched_geometry_count->toBe(0)
        ->fallback_geometry_count->toBe(0)
        ->geometries->toBe([]);

    Http::assertNothingSent();
});
