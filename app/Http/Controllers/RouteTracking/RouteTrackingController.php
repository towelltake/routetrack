<?php

namespace App\Http\Controllers\RouteTracking;

use App\Http\Controllers\Controller;
use App\Models\AreaMaster;
use App\Models\CompanyMaster;
use App\Models\RouteMaster;
use App\Models\SubAreaMaster;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Inertia\Inertia;
use Inertia\Response;

class RouteTrackingController extends Controller
{
    private const MATCH_CHUNK_SIZE = 100;
    private const MIN_DOWNSAMPLE_METERS = 20;
    private const MAX_PLAUSIBLE_SPEED_KMH = 150;
    private const MIN_MATCH_CONFIDENCE = 0.1;
    private const MIN_MATCH_DISTANCE_METERS = 50;

    // Matches the OMAN_BOUNDS used on the map UI — guards against bad
    // customermaster coordinates (e.g. a mistyped digit) sending a
    // roundtrip trip hundreds of km outside Oman.
    private const OMAN_MIN_LAT = 16.0;
    private const OMAN_MAX_LAT = 27.0;
    private const OMAN_MIN_LNG = 51.5;
    private const OMAN_MAX_LNG = 60.5;

    private const CARBON_DAYOFWEEK_TO_KEY = [
        0 => 'sun',
        1 => 'mon',
        2 => 'tue',
        3 => 'wed',
        4 => 'thu',
        5 => 'fri',
        6 => 'sat',
    ];

    public function index(): Response
    {
        return Inertia::render('routetracking/Index');
    }

    public function companies(): JsonResponse
    {
        $routedCmpyCodes = RouteMaster::query()
            ->whereIn('routecode', session('user_access.route_codes', []))
            ->whereIn('routecode', $this->routesequenceCustomerStatusRouteCodes())
            ->distinct()
            ->pluck('cmpycode');

        $companies = CompanyMaster::query()
            ->whereIn('cmpycode', session('user_access.company_codes', []))
            ->whereIn('cmpycode', $routedCmpyCodes)
            ->orderBy('name')
            ->get(['cmpycode', 'name']);

        return response()->json($companies);
    }

    public function areas(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'companycode' => ['nullable', 'integer'],
        ]);

        $routedSubareaCodes = RouteMaster::query()
            ->whereIn('cmpycode', session('user_access.company_codes', []))
            ->whereIn('subareacode', session('user_access.subarea_codes', []))
            ->whereIn('routecode', $this->routesequenceCustomerStatusRouteCodes())
            ->when($validated['companycode'] ?? null, fn ($query, $companycode) => $query->where('cmpycode', $companycode))
            ->pluck('subareacode');

        $areaCodes = SubAreaMaster::query()
            ->whereIn('subareacode', $routedSubareaCodes)
            ->distinct()
            ->pluck('areacode');

        $areas = AreaMaster::query()
            ->whereIn('areacode', session('user_access.area_codes', []))
            ->whereIn('areacode', $areaCodes)
            ->orderBy('areaname')
            ->get(['areacode', 'areaname']);

        return response()->json($areas);
    }

    public function subareas(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'areacode' => ['required', 'integer'],
            'companycode' => ['nullable', 'integer'],
        ]);

        $routedSubareaCodes = RouteMaster::query()
            ->whereIn('cmpycode', session('user_access.company_codes', []))
            ->whereIn('subareacode', session('user_access.subarea_codes', []))
            ->whereIn('routecode', $this->routesequenceCustomerStatusRouteCodes())
            ->when($validated['companycode'] ?? null, fn ($query, $companycode) => $query->where('cmpycode', $companycode))
            ->pluck('subareacode');

        $subareas = SubAreaMaster::query()
            ->whereIn('subareacode', session('user_access.subarea_codes', []))
            ->where('areacode', $validated['areacode'])
            ->whereIn('subareacode', $routedSubareaCodes)
            ->orderBy('subareaname')
            ->get(['subareacode', 'subareaname']);

        return response()->json($subareas);
    }

    public function routes(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'subareacode' => ['nullable', 'integer'],
            'companycode' => ['nullable', 'integer'],
        ]);

        $routes = RouteMaster::query()
            ->whereIn('routecode', session('user_access.route_codes', []))
            ->whereIn('cmpycode', session('user_access.company_codes', []))
            ->whereIn('subareacode', session('user_access.subarea_codes', []))
            ->whereIn('routecode', $this->routesequenceCustomerStatusRouteCodes())
            ->when($validated['companycode'] ?? null, fn ($query, $companycode) => $query->where('cmpycode', $companycode))
            ->when($validated['subareacode'] ?? null, fn ($query, $subareacode) => $query->where('subareacode', $subareacode))
            ->orderBy('routename')
            ->get(['routecode', 'routename']);

        return response()->json($routes);
    }

    public function actualRoute(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'routecode' => ['required', 'integer'],
            'date' => ['required', 'date_format:Y-m-d'],
        ]);

        $this->ensureRouteAllowed($validated['routecode']);

        $result = $this->computeMatchedActual($validated['routecode'], $validated['date']);

        if (isset($result['error'])) {
            return response()->json($result, 422);
        }

        return response()->json($result);
    }

    public function plannedRoute(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'routecode' => ['required', 'integer'],
            'date' => ['required', 'date_format:Y-m-d'],
        ]);

        $this->ensureRouteAllowed($validated['routecode']);

        $result = $this->computePlannedRoute($validated['routecode'], $validated['date']);

        if (isset($result['error'])) {
            return response()->json($result, 422);
        }

        return response()->json($result);
    }

    public function compare(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'routecode' => ['required', 'integer'],
            'date' => ['required', 'date_format:Y-m-d'],
        ]);

        $this->ensureRouteAllowed($validated['routecode']);

        $planned = $this->computePlannedRoute($validated['routecode'], $validated['date']);
        $actual = $this->computeMatchedActual($validated['routecode'], $validated['date']);

        if (isset($planned['error']) || isset($actual['error'])) {
            return response()->json([
                'planned' => $planned,
                'actual' => $actual,
                'error' => 'Could not compute a full comparison — see planned/actual for details',
            ], 422);
        }

        $distanceRatio = $planned['distance'] > 0 ? $actual['distance'] / $planned['distance'] : null;
        $durationRatio = $planned['duration'] > 0 ? $actual['duration'] / $planned['duration'] : null;

        return response()->json([
            'planned' => $planned,
            'actual' => $actual,
            'distance_ratio' => $distanceRatio,
            'duration_ratio' => $durationRatio,
        ]);
    }

    /**
     * Reconstructs the real driven path from raw routetrack GPS pings via
     * OSRM Map Matching. Pings are heavily duplicated (the same coordinate
     * logged many times a second) so they're downsampled by distance moved
     * before the speed-anomaly filter and chunked /match calls.
     */
    private function computeMatchedActual(int $routecode, string $date): array
    {
        $points = $this->fetchCleanTrail($routecode, $date);

        if (count($points) < 2) {
            return ['error' => 'Not enough GPS points recorded for this route on this date'];
        }

        $totalDistance = 0;
        $totalDuration = 0;
        $geometries = [];
        $chunksAttempted = 0;
        $chunksFailed = 0;
        $usedFallbackGeometry = false;

        foreach (array_chunk($points, self::MATCH_CHUNK_SIZE) as $chunk) {
            if (count($chunk) < 2) {
                continue;
            }

            $chunksAttempted++;

            $coordinates = implode(';', array_map(
                fn ($p) => sprintf('%F,%F', $p->longitude, $p->latitude),
                $chunk
            ));
            $timestamps = implode(';', array_map(
                fn ($p) => strtotime($p->effective_timestamp),
                $chunk
            ));

            try {
                $response = Http::baseUrl(config('services.osrm.url'))
                    ->get("/match/v1/driving/{$coordinates}", [
                        'timestamps' => $timestamps,
                        'geometries' => 'geojson',
                        'overview' => 'full',
                    ]);
            } catch (ConnectionException) {
                $chunksFailed++;
                continue;
            }

            if (! $response->successful() || $response->json('code') !== 'Ok') {
                $chunksFailed++;
                continue;
            }

            foreach ($response->json('matchings') ?? [] as $matching) {
                if ($matching['confidence'] < self::MIN_MATCH_CONFIDENCE || $matching['distance'] < self::MIN_MATCH_DISTANCE_METERS) {
                    continue;
                }

                $totalDistance += $matching['distance'];
                $totalDuration += $matching['duration'];
                $geometries[] = $matching['geometry'];
            }
        }

        $start = $points[0];
        $end = $points[count($points) - 1];

        if ($geometries === []) {
            $fallback = $this->rawTrailFallback($points);
            $totalDistance = $fallback['distance'];
            $totalDuration = $fallback['duration'];
            $geometries[] = $fallback['geometry'];
            $usedFallbackGeometry = true;
        }

        return [
            'distance' => $totalDistance,
            'duration' => $totalDuration,
            'geometries' => $geometries,
            'start' => ['lat' => (float) $start->latitude, 'lng' => (float) $start->longitude, 'time' => $start->effective_timestamp],
            'end' => ['lat' => (float) $end->latitude, 'lng' => (float) $end->longitude, 'time' => $end->effective_timestamp],
            'point_count' => count($points),
            'chunks_attempted' => $chunksAttempted,
            'chunks_failed' => $chunksFailed,
            'used_fallback_geometry' => $usedFallbackGeometry,
            'geometry_source' => $usedFallbackGeometry ? 'raw_gps' : 'osrm_match',
        ];
    }

    private function fetchCleanTrail(int $routecode, string $date): array
    {
        // date + time is the actual tracking timestamp; cdate is only the
        // database audit/creation time and must not be sent to OSRM.
        $points = DB::connection('tracking_pgsql')->table('trac_routetrack')
            ->where('routecode', $routecode)
            ->where('date', $date)
            ->whereNotNull('latitude')
            ->whereNotNull('longitude')
            ->where('latitude', '!=', 0)
            ->where('longitude', '!=', 0)
            ->selectRaw('latitude, longitude, date + time as effective_timestamp')
            ->orderBy('effective_timestamp')
            ->orderBy('id')
            ->get()
            ->values()
            ->all();

        return $this->removeSpeedAnomalies($this->downsampleByDistance($points));
    }

    /**
     * Collapses the raw ping stream down to points that represent real
     * movement — routetrack logs the same coordinate repeatedly while
     * stationary, which would otherwise waste OSRM /match calls.
     */
    private function downsampleByDistance(array $points): array
    {
        if ($points === []) {
            return [];
        }

        $kept = [$points[0]];

        foreach ($points as $point) {
            $last = end($kept);
            $meters = $this->haversineMeters(
                (float) $last->latitude, (float) $last->longitude,
                (float) $point->latitude, (float) $point->longitude
            );

            if ($meters >= self::MIN_DOWNSAMPLE_METERS) {
                $kept[] = $point;
            }
        }

        return $kept;
    }

    /**
     * Drops points that imply an impossible speed (>150km/h) from BOTH the
     * previous and next neighbor — catches isolated "teleport" glitches
     * without cascading false positives from a single bad anchor point.
     */
    private function removeSpeedAnomalies(array $points): array
    {
        $count = count($points);
        if ($count < 3) {
            return $points;
        }

        $keep = array_fill(0, $count, true);

        for ($i = 1; $i < $count - 1; $i++) {
            $speedIn = $this->impliedSpeedKmh($points[$i - 1], $points[$i]);
            $speedOut = $this->impliedSpeedKmh($points[$i], $points[$i + 1]);

            if ($speedIn > self::MAX_PLAUSIBLE_SPEED_KMH && $speedOut > self::MAX_PLAUSIBLE_SPEED_KMH) {
                $keep[$i] = false;
            }
        }

        if ($this->impliedSpeedKmh($points[0], $points[1]) > self::MAX_PLAUSIBLE_SPEED_KMH) {
            $keep[0] = false;
        }
        if ($this->impliedSpeedKmh($points[$count - 2], $points[$count - 1]) > self::MAX_PLAUSIBLE_SPEED_KMH) {
            $keep[$count - 1] = false;
        }

        $clean = [];
        foreach ($points as $i => $point) {
            if ($keep[$i]) {
                $clean[] = $point;
            }
        }

        return $clean;
    }

    private function impliedSpeedKmh(object $a, object $b): float
    {
        $seconds = abs(strtotime($b->effective_timestamp) - strtotime($a->effective_timestamp));
        if ($seconds <= 0) {
            $seconds = 1;
        }

        $meters = $this->haversineMeters((float) $a->latitude, (float) $a->longitude, (float) $b->latitude, (float) $b->longitude);

        return ($meters / $seconds) * 3.6;
    }

    private function haversineMeters(float $lat1, float $lng1, float $lat2, float $lng2): float
    {
        $earthRadiusMeters = 6371000;

        $latDelta = deg2rad($lat2 - $lat1);
        $lngDelta = deg2rad($lng2 - $lng1);

        $a = sin($latDelta / 2) ** 2
            + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($lngDelta / 2) ** 2;

        return $earthRadiusMeters * 2 * atan2(sqrt($a), sqrt(1 - $a));
    }

    private function computePlannedRoute(int $routecode, string $date): array
    {
        $dayOfWeek = (int) date('w', strtotime($date));
        $dayKey = self::CARBON_DAYOFWEEK_TO_KEY[$dayOfWeek];

        $routeDay = $this->findRouteDay($routecode, $date);

        if ($routeDay === null) {
            return ['error' => "No route day found for route {$routecode} on {$date}"];
        }

        $customers = $this->fetchScheduledCustomersForRouteKey((int) $routeDay->routekey);

        if ($customers->count() < 2) {
            return ['error' => "Not enough planned customers for this route on {$dayKey}"];
        }

        $totalDistance = 0;
        $totalDuration = 0;
        $geometries = [];
        $orderedCustomers = [];
        $chunksFailed = 0;
        $osrmLegs = 0;
        $fallbackLegs = 0;

        $customerValues = $customers->values();

        for ($i = 0; $i < $customerValues->count() - 1; $i++) {
            $from = $customerValues[$i];
            $to = $customerValues[$i + 1];
            $coordinates = sprintf('%F,%F;%F,%F', $from['lng'], $from['lat'], $to['lng'], $to['lat']);

            try {
                $response = Http::baseUrl(config('services.osrm.url'))
                    ->get("/route/v1/driving/{$coordinates}", [
                        'geometries' => 'geojson',
                        'overview' => 'full',
                    ]);
            } catch (ConnectionException) {
                $chunksFailed++;
                $fallbackLegs++;
                $fallback = $this->plannedLegFallback($from, $to);
                $totalDistance += $fallback['distance'];
                $totalDuration += $fallback['duration'];
                $geometries[] = $fallback['geometry'];
                continue;
            }

            if (! $response->successful() || $response->json('code') !== 'Ok') {
                $chunksFailed++;
                $fallbackLegs++;
                $fallback = $this->plannedLegFallback($from, $to);
                $totalDistance += $fallback['distance'];
                $totalDuration += $fallback['duration'];
                $geometries[] = $fallback['geometry'];
                continue;
            }

            $route = $response->json('routes.0');
            if (! is_array($route)) {
                $chunksFailed++;
                $fallbackLegs++;
                $fallback = $this->plannedLegFallback($from, $to);
                $totalDistance += $fallback['distance'];
                $totalDuration += $fallback['duration'];
                $geometries[] = $fallback['geometry'];
                continue;
            }

            $totalDistance += $route['distance'];
            $totalDuration += $route['duration'];
            $geometries[] = $route['geometry'];
            $osrmLegs++;
        }

        $customerValues
            ->map(fn ($customer, $i) => [
                'customercode' => $customer['customercode'],
                'customername' => $customer['customername'],
                'lat' => $customer['lat'],
                'lng' => $customer['lng'],
                'scheduled_sequence' => $customer['scheduled_sequence'],
                'serviced_flag' => $customer['serviced_flag'],
                'scanned_flag' => $customer['scanned_flag'],
                'sequence' => $i,
            ])
            ->each(function ($c) use (&$orderedCustomers) {
                $orderedCustomers[] = $c;
            });

        if ($geometries === []) {
            return ['error' => 'Could not compute planned route through OSRM'];
        }

        foreach ($orderedCustomers as &$customer) {
            $customer['visited'] = $customer['serviced_flag'] !== 0;
            $customer['visit_time'] = null;
        }

        return [
            'day' => $dayKey,
            'routekey' => (int) $routeDay->routekey,
            'customer_count' => $customers->count(),
            'visited_count' => $customers->where('serviced_flag', '!=', 0)->count(),
            'distance' => $totalDistance,
            'duration' => $totalDuration,
            'geometries' => $geometries,
            'customers' => $orderedCustomers,
            'chunks_failed' => $chunksFailed,
            'osrm_legs' => $osrmLegs,
            'fallback_legs' => $fallbackLegs,
            'used_fallback_geometry' => $fallbackLegs > 0,
            'geometry_source' => $fallbackLegs > 0 ? ($osrmLegs > 0 ? 'mixed' : 'straight_line') : 'osrm_route',
        ];
    }

    private function ensureRouteAllowed(int $routecode): void
    {
        abort_unless(RouteMaster::query()
            ->whereIn('routecode', session('user_access.route_codes', []))
            ->where('routecode', $routecode)
            ->whereIn('cmpycode', session('user_access.company_codes', []))
            ->whereIn('subareacode', session('user_access.subarea_codes', []))
            ->exists(), 403);
    }

    private function routesequenceCustomerStatusRouteCodes(): Collection
    {
        return DB::table('routesequencecustomerstatus')
            ->distinct()
            ->pluck('routecode');
    }

    private function findRouteDay(int $routecode, string $date): ?object
    {
        $routeDay = DB::table('startendday')
            ->where('routecode', $routecode)
            ->where(function ($query) use ($date) {
                $query->whereDate('routeenddate', $date)
                    ->orWhereDate('routestartdate', $date)
                    ->orWhere(function ($query) use ($date) {
                        $query->whereDate('routestartdate', '<=', $date)
                            ->whereDate('routeenddate', '>=', $date);
                    });
            })
            ->orderByDesc('routekey')
            ->first(['routekey', 'routecode', 'routestartdate', 'routeenddate']);

        if ($routeDay !== null) {
            return $routeDay;
        }

        $statusRouteKey = DB::table('routesequencecustomerstatus')
            ->where('routecode', $routecode)
            ->where('seqweekday', $this->legacySeqWeekday($date))
            ->where('seqweeknumber', $this->legacySeqWeekNumber($date))
            ->where('schelduledflag', 1)
            ->max('routekey');

        if ($statusRouteKey === null) {
            return null;
        }

        return (object) [
            'routekey' => (int) $statusRouteKey,
            'routecode' => $routecode,
            'routestartdate' => $date,
            'routeenddate' => $date,
        ];
    }

    private function legacySeqWeekday(string $date): int
    {
        return (int) date('N', strtotime($date));
    }

    private function legacySeqWeekNumber(string $date): int
    {
        return min(4, (int) ceil(((int) date('j', strtotime($date))) / 7));
    }

    private function fetchScheduledCustomersForRouteKey(int $routekey): Collection
    {
        return DB::table('routesequencecustomerstatus as rscs')
            ->join('customermaster as cm', 'cm.customercode', '=', 'rscs.customercode')
            ->where('rscs.routekey', $routekey)
            ->where('rscs.schelduledflag', 1)
            ->whereNotNull('fixedlatitude')
            ->whereNotNull('fixedlongitude')
            ->where('fixedlatitude', '!=', 0)
            ->where('fixedlongitude', '!=', 0)
            ->whereBetween('fixedlatitude', [self::OMAN_MIN_LAT, self::OMAN_MAX_LAT])
            ->whereBetween('fixedlongitude', [self::OMAN_MIN_LNG, self::OMAN_MAX_LNG])
            ->orderByRaw('CASE WHEN COALESCE(sequencenumber, 0) > 0 THEN 0 ELSE 1 END')
            ->orderBy('rscs.sequencenumber')
            ->orderBy('rscs.customercode')
            ->get([
                'rscs.customercode',
                'rscs.sequencenumber',
                'rscs.servicedflag',
                'rscs.scannedflag',
                'cm.customername',
                'cm.fixedlatitude',
                'cm.fixedlongitude',
            ])
            ->map(fn (object $customer) => [
                'customercode' => $customer->customercode,
                'customername' => $customer->customername,
                'lat' => (float) $customer->fixedlatitude,
                'lng' => (float) $customer->fixedlongitude,
                'scheduled_sequence' => (int) ($customer->sequencenumber ?? 0),
                'serviced_flag' => (int) ($customer->servicedflag ?? 0),
                'scanned_flag' => (int) ($customer->scannedflag ?? 0),
            ]);
    }

    private function plannedLegFallback(array $from, array $to): array
    {
        $distance = $this->haversineMeters($from['lat'], $from['lng'], $to['lat'], $to['lng']);

        return [
            'distance' => $distance,
            'duration' => $this->estimatedDrivingSeconds($distance),
            'geometry' => [
                'type' => 'LineString',
                'coordinates' => [
                    [(float) $from['lng'], (float) $from['lat']],
                    [(float) $to['lng'], (float) $to['lat']],
                ],
            ],
        ];
    }

    private function rawTrailFallback(array $points): array
    {
        $distance = 0;
        for ($i = 0; $i < count($points) - 1; $i++) {
            $distance += $this->haversineMeters(
                (float) $points[$i]->latitude,
                (float) $points[$i]->longitude,
                (float) $points[$i + 1]->latitude,
                (float) $points[$i + 1]->longitude
            );
        }

        $first = $points[0];
        $last = $points[count($points) - 1];
        $duration = max(0, strtotime($last->effective_timestamp) - strtotime($first->effective_timestamp));

        return [
            'distance' => $distance,
            'duration' => $duration,
            'geometry' => [
                'type' => 'LineString',
                'coordinates' => array_map(
                    fn ($point) => [(float) $point->longitude, (float) $point->latitude],
                    $points
                ),
            ],
        ];
    }

    private function estimatedDrivingSeconds(float $distanceMeters): float
    {
        return $distanceMeters / (40_000 / 3600);
    }
}
