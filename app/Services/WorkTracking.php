<?php

namespace App\Services;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

class WorkTracking
{
    /** Tracking bounds are the first and last timestamped records, regardless of coordinate accuracy, never scheduled hours. */
    public function observedBounds(array $points): array
    {
        return ['start' => $points ? strtotime($points[0]->effective_timestamp) : null,
            'end' => $points ? strtotime($points[array_key_last($points)]->effective_timestamp) : null];
    }

    public function observations(array $records): array
    {
        $records = array_values(array_filter($records, fn ($point) => strtotime($point->effective_timestamp ?? '') !== false));
        $detector = app(StationaryDetection::class);
        $allPoints = array_values(array_filter($records, fn ($point) => $detector->validCoordinates($point)));
        $points = $allPoints;
        foreach ($points as $point) $point->unreliable = $detector->unreliable($point);
        return $this->observedBounds($records) + ['points' => $points, 'all_points' => $allPoints,
            'reason' => $points ? null : ($records ? 'GPS timestamps recorded, but coordinates are invalid.' : 'No GPS readings for the selected device and date.')];
    }

    /** Caller must first authorize this route; never substitute today's device for a historical one. */
    public function load(int $routecode, string $date, ?object $journey = null): array
    {
        $empty = ['start' => null, 'end' => null, 'points' => [], 'all_points' => [], 'available' => false];
        $today = CarbonImmutable::now(config('tracking.timezone', 'Asia/Muscat'))->toDateString();
        $device = '';
        if ($date === $today) {
            $device = trim((string) DB::table('routemaster')->where('routecode', $routecode)->value('device_assigned_id'));
        }
        if ($date !== $today) {
            if ($journey !== null && !property_exists($journey, 'dataconrefnumber')) {
                $journey = DB::table('startendday')->where('routecode', $routecode)->where('routekey', $journey->routekey)->first();
            }
            if ($journey === null) {
                $journey = DB::table('startendday')->where('routecode', $routecode)
                    ->where(function ($q) use ($date) {
                        $q->whereDate('routestartdate', $date)->orWhereDate('routeenddate', $date)
                            ->orWhere(fn ($q) => $q->whereDate('routestartdate', '<=', $date)->whereDate('routeenddate', '>=', $date));
                    })->orderByDesc('routekey')->first();
            }
            $device = trim((string) ($journey->dataconrefnumber ?? ''));
        }
        if ($device === '') return $empty + ['reason' => $date === $today ? 'No device is assigned to this route.' : 'No historical device is recorded for this journey.'];
        $connection = DB::connection('tracking_pgsql');
        $timestamp = $connection->getDriverName() === 'sqlite' ? "date || ' ' || time" : 'date + time';
        $points = $connection->table('trac_routetrack')->where('deviceid', $device)
            ->whereDate('date', $date)
            ->selectRaw("trac_routetrack.*, {$timestamp} as effective_timestamp")
            ->orderBy('effective_timestamp')->orderBy('id')->get()->all();
        return $this->observations($points) + ['available' => true];
    }

    /** Keep isolated outside-route observations visible without bridging GPS gaps. */
    public function outsideGeometries(array $rawPoints, ?int $routeStart, ?int $routeEnd): array
    {
        $outsideGeometries = [];
        foreach (['before', 'after'] as $side) {
            $segments = []; $segment = []; $previous = null;
            foreach ($rawPoints as $point) {
                $time = strtotime($point->effective_timestamp);
                $outside = $side === 'before' ? ($routeStart === null || $time < $routeStart) : ($routeEnd !== null && $time > $routeEnd && ($routeStart === null || $time >= $routeStart));
                if (!$outside || ($previous !== null && $time - $previous > config('tracking.stationary_max_gap_seconds'))) {
                    if ($segment !== []) $segments[] = $segment;
                    $segment = [];
                }
                if ($outside) $segment[] = [(float) $point->longitude, (float) $point->latitude];
                $previous = $time;
            }
            if ($segment !== []) $segments[] = $segment;
            foreach ($segments as $coordinates) $outsideGeometries[] = count($coordinates) === 1
                ? ['type' => 'Point', 'coordinates' => $coordinates[0], 'tracking_period' => $side]
                : ['type' => 'LineString', 'coordinates' => $coordinates, 'tracking_period' => $side];
        }
        return $outsideGeometries;
    }

    /** Build low-confidence trail segments without joining across gaps or reliable readings. */
    public function unreliableGeometries(array $points): array
    {
        $segments = []; $segment = []; $previous = null;
        foreach ($points as $point) {
            $time = strtotime($point->effective_timestamp);
            $low = app(StationaryDetection::class)->unreliable($point);
            if (!$low || ($previous !== null && $time - $previous > config('tracking.stationary_max_gap_seconds'))) {
                if ($segment) $segments[] = $segment;
                $segment = [];
            }
            if ($low) $segment[] = $point;
            $previous = $time;
        }
        if ($segment) $segments[] = $segment;
        return array_map(function ($segment) {
            $coordinates = array_map(fn ($point) => [(float) $point->longitude, (float) $point->latitude], $segment);
            return ['type' => 'Feature', 'properties' => ['start' => $segment[0]->effective_timestamp,
                'end' => $segment[array_key_last($segment)]->effective_timestamp,
                'accuracy' => max(array_map(fn ($point) => (float) $point->accuracy_m, $segment))],
                'geometry' => ['type' => count($coordinates) === 1 ? 'Point' : 'LineString',
                    'coordinates' => count($coordinates) === 1 ? $coordinates[0] : $coordinates]];
        }, $segments);
    }

    public function marker(?object $point, ?int $eventTime = null): ?array
    {
        return $point ? ['lat' => (float) $point->latitude, 'lng' => (float) $point->longitude,
            'time' => $eventTime === null ? $point->effective_timestamp : date('Y-m-d H:i:s', $eventTime),
            'gps_time' => $point->effective_timestamp,
            'accuracy_m' => is_numeric($point->accuracy_m ?? null) ? (float) $point->accuracy_m : null,
            'unreliable' => app(StationaryDetection::class)->unreliable($point)] : null;
    }

    /** Use the nearest valid observation and disclose its recorded time and accuracy. */
    public function eventMarker(array $points, ?int $time): ?array
    {
        if ($time === null || !$points) return null;
        usort($points, fn ($a, $b) => abs(strtotime($a->effective_timestamp) - $time) <=> abs(strtotime($b->effective_timestamp) - $time));
        return $this->marker($points[0], $time);
    }
}
