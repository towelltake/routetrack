<?php

namespace App\Http\Controllers\RouteReplay;

use App\Http\Controllers\Controller;
use App\Models\AreaMaster;
use App\Models\CompanyMaster;
use App\Models\RouteMaster;
use App\Models\RouteSequence;
use App\Models\SubAreaMaster;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class RouteReplayController extends Controller
{
    private const MIN_DOWNSAMPLE_METERS = 20;
    private const MAX_PLAUSIBLE_SPEED_KMH = 150;

    public function index(): Response
    {
        return Inertia::render('routereplay/Index');
    }

    public function companies(): JsonResponse
    {
        $routedCmpyCodes = RouteMaster::query()
            ->whereIn('routecode', RouteSequence::query()->distinct()->pluck('routecode'))
            ->distinct()
            ->pluck('cmpycode');

        $companies = CompanyMaster::query()
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
            ->whereIn('routecode', RouteSequence::query()->distinct()->pluck('routecode'))
            ->when($validated['companycode'] ?? null, fn ($query, $companycode) => $query->where('cmpycode', $companycode))
            ->pluck('subareacode');

        $areaCodes = SubAreaMaster::query()
            ->whereIn('subareacode', $routedSubareaCodes)
            ->distinct()
            ->pluck('areacode');

        $areas = AreaMaster::query()
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
            ->whereIn('routecode', RouteSequence::query()->distinct()->pluck('routecode'))
            ->when($validated['companycode'] ?? null, fn ($query, $companycode) => $query->where('cmpycode', $companycode))
            ->pluck('subareacode');

        $subareas = SubAreaMaster::query()
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
            ->whereIn('routecode', RouteSequence::query()->distinct()->pluck('routecode'))
            ->when($validated['companycode'] ?? null, fn ($query, $companycode) => $query->where('cmpycode', $companycode))
            ->when($validated['subareacode'] ?? null, fn ($query, $subareacode) => $query->where('subareacode', $subareacode))
            ->orderBy('routename')
            ->get(['routecode', 'routename']);

        return response()->json($routes);
    }

    /**
     * The real recorded GPS trail for this route/date, cleaned up (deduped +
     * speed-anomaly-filtered) and annotated with the instantaneous speed of
     * each segment — used to animate a marker along the real path at the
     * real recorded pace, and to color the path by how fast it was driven.
     */
    public function track(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'routecode' => ['required', 'integer'],
            'date' => ['required', 'date_format:Y-m-d'],
        ]);

        $points = $this->fetchCleanTrail($validated['routecode'], $validated['date']);

        if (count($points) < 2) {
            return response()->json(['error' => 'Not enough GPS points recorded for this route on this date'], 422);
        }

        $totalDistance = 0;
        $result = [];
        $previous = null;

        foreach ($points as $point) {
            $speedKmh = 0;

            if ($previous) {
                $segmentMeters = $this->haversineMeters(
                    (float) $previous->latitude, (float) $previous->longitude,
                    (float) $point->latitude, (float) $point->longitude
                );
                $totalDistance += $segmentMeters;

                $seconds = max(1, strtotime($point->effective_timestamp) - strtotime($previous->effective_timestamp));
                $speedKmh = ($segmentMeters / $seconds) * 3.6;
            }

            $result[] = [
                'lat' => (float) $point->latitude,
                'lng' => (float) $point->longitude,
                'time' => $point->effective_timestamp,
                'speed_kmh' => round($speedKmh, 1),
            ];

            $previous = $point;
        }

        $start = $points[0];
        $end = $points[count($points) - 1];

        return response()->json([
            'points' => $result,
            'distance_meters' => round($totalDistance),
            'duration_seconds' => max(0, strtotime($end->effective_timestamp) - strtotime($start->effective_timestamp)),
            'point_count' => count($result),
            'start_time' => $start->effective_timestamp,
            'end_time' => $end->effective_timestamp,
        ]);
    }

    /**
     * routetrack logs the same coordinate repeatedly while stationary and
     * occasionally logs an impossible "teleport" glitch — both would make
     * the replay animation stutter or jump, so they're filtered out here.
     */
    private function fetchCleanTrail(int $routecode, string $date): array
    {
        // devicetimestamp is null for some devices/routes — entrydate + entrytime
        // is always populated, so it's used as a fallback ordering/timing source.
        $points = DB::connection('pgsql_transfer')->table('trac_routetrack')
            ->where('routecode', $routecode)
            ->where('entrydate', $date)
            ->whereNotNull('latitude')
            ->whereNotNull('longitude')
            ->where('latitude', '!=', 0)
            ->where('longitude', '!=', 0)
            ->selectRaw('latitude, longitude, COALESCE(devicetimestamp, entrydate + entrytime) as effective_timestamp')
            ->orderBy('effective_timestamp')
            ->orderBy('entryid')
            ->get()
            ->values()
            ->all();

        return $this->removeSpeedAnomalies($this->downsampleByDistance($points));
    }

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
}
