<?php

namespace App\Services;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

class WorkTracking
{
    /** Tracking bounds are the first and last usable observations, never scheduled hours. */
    public function observedBounds(array $points): array
    {
        return ['start' => $points ? strtotime($points[0]->effective_timestamp) : null,
            'end' => $points ? strtotime($points[array_key_last($points)]->effective_timestamp) : null];
    }

    /** Caller must first authorize this route; never substitute today's device for a historical one. */
    public function load(int $routecode, string $date, ?object $journey = null): array
    {
        $empty = ['start' => null, 'end' => null, 'points' => [], 'available' => false];
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
            ->orderBy('effective_timestamp')->orderBy('id')->get()
            ->filter(fn ($point) => app(StationaryDetection::class)->usable($point) && strtotime($point->effective_timestamp ?? '') !== false)->values()->all();
        return $this->observedBounds($points) + ['points' => $points, 'available' => true, 'reason' => $points ? null : 'No usable GPS readings for the selected device and date.'];
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
                ? ['type' => 'Point', 'coordinates' => $coordinates[0]]
                : ['type' => 'LineString', 'coordinates' => $coordinates];
        }
        return $outsideGeometries;
    }

    public function marker(?object $point, ?int $eventTime = null): ?array
    {
        return $point ? ['lat' => (float) $point->latitude, 'lng' => (float) $point->longitude,
            'time' => $eventTime === null ? $point->effective_timestamp : date('Y-m-d H:i:s', $eventTime),
            'gps_time' => $point->effective_timestamp] : null;
    }

    /** Only place route event markers when a nearby GPS observation exists. */
    public function eventMarker(array $points, ?int $time): ?array
    {
        if ($time === null || !$points) return null;
        usort($points, fn ($a, $b) => abs(strtotime($a->effective_timestamp) - $time) <=> abs(strtotime($b->effective_timestamp) - $time));
        return abs(strtotime($points[0]->effective_timestamp) - $time) <= config('tracking.stationary_max_gap_seconds', 120)
            ? $this->marker($points[0], $time) : null;
    }
}
