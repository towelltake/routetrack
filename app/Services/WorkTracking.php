<?php

namespace App\Services;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

class WorkTracking
{
    /** Build a wall-clock work window; timestamps in the GPS table use the same local clock. */
    public function window(string $date, ?string $from, ?string $to): ?array
    {
        if (!preg_match('/^\d{2}:\d{2}(:\d{2})?$/', $from ?? '') || !preg_match('/^\d{2}:\d{2}(:\d{2})?$/', $to ?? '')) return null;
        foreach ([$from, $to] as $time) {
            $parts = explode(':', $time);
            if ((int) $parts[0] > 23 || (int) $parts[1] > 59 || (int) ($parts[2] ?? 0) > 59) return null;
        }
        $start = strtotime($date.' '.$from);
        $end = strtotime($date.' '.$to);
        if ($start === false || $end === false || $start === $end) return null;
        if ($end < $start) $end = strtotime('+1 day', $end);
        return ['start' => $start, 'end' => $end];
    }

    /** Caller must first authorize this route; never substitute today's device for a historical one. */
    public function load(int $routecode, string $date, ?object $journey = null): array
    {
        $route = DB::table('routemaster')->where('routecode', $routecode)
            ->first(['device_assigned_id', 'autojp_work_start_time', 'autojp_work_end_time']);
        $window = $this->window($date, $route?->autojp_work_start_time, $route?->autojp_work_end_time);
        $empty = ['start' => $window['start'] ?? null, 'end' => $window['end'] ?? null, 'points' => [], 'available' => false];
        if (!$window) return $empty + ['reason' => 'Tracking work hours are missing or invalid.'];
        $today = CarbonImmutable::now(config('tracking.timezone', 'Asia/Muscat'))->toDateString();
        $device = $date === $today ? trim((string) ($route?->device_assigned_id ?? '')) : '';
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
            ->whereBetween('date', [date('Y-m-d', $window['start']), date('Y-m-d', $window['end'])])
            ->whereRaw("{$timestamp} >= ?", [date('Y-m-d H:i:s', $window['start'])])
            ->whereRaw("{$timestamp} <= ?", [date('Y-m-d H:i:s', $window['end'])])
            ->selectRaw("trac_routetrack.*, {$timestamp} as effective_timestamp")
            ->orderBy('effective_timestamp')->orderBy('id')->get()
            ->filter(fn ($point) => app(StationaryDetection::class)->usable($point))->values()->all();
        return $window + ['points' => $points, 'available' => true, 'reason' => $points ? null : 'No usable GPS readings within the tracking work window.'];
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
