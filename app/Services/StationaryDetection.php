<?php

namespace App\Services;

class StationaryDetection
{
    public function usable(object $point): bool
    {
        return is_numeric($point->latitude ?? null) && is_numeric($point->longitude ?? null)
            && abs((float) $point->latitude) <= 90 && abs((float) $point->longitude) <= 180
            && (float) $point->latitude != 0 && (float) $point->longitude != 0
            && (($point->accuracy_m ?? null) === null || (is_numeric($point->accuracy_m)
                && $point->accuracy_m >= 0 && $point->accuracy_m < config('tracking.max_accuracy_m')));
    }

    /** Points must be ordered by device timestamp, before distance downsampling. */
    public function detect(array $points): array
    {
        $stops = [];
        $candidate = [];
        $outside = null;
        $previousTime = null;
        $finish = function () use (&$candidate, &$stops) {
            if (count($candidate) < 2) {
                return;
            }
            $first = $candidate[0];
            $last = $candidate[array_key_last($candidate)];
            $seconds = strtotime($last->effective_timestamp) - strtotime($first->effective_timestamp);
            if ($seconds >= config('tracking.stationary_minutes') * 60) {
                $stops[] = [
                    'lat' => (float) $first->latitude,
                    'lng' => (float) $first->longitude,
                    'start_time' => $first->effective_timestamp,
                    'end_time' => $last->effective_timestamp,
                    'duration_seconds' => $seconds,
                    'radius_m' => config('tracking.stationary_radius_m'),
                    'point_count' => count($candidate),
                    'accuracy_unknown' => count(array_filter($candidate, fn ($p) => ($p->accuracy_m ?? null) === null)) > 0,
                ];
            }
        };

        foreach ($points as $point) {
            if (! $this->usable($point) || ($time = strtotime($point->effective_timestamp ?? '')) === false) {
                continue;
            }
            if ($previousTime !== null && $time <= $previousTime) {
                continue;
            }
            if ($previousTime !== null && $time - $previousTime > config('tracking.stationary_max_gap_seconds')) {
                $finish();
                $candidate = [];
                $outside = null;
            }
            $previousTime = $time;
            if ($candidate === []) {
                $candidate = [$point];
                continue;
            }
            // Fixed anchor prevents a slow-moving trail from drifting into a stop.
            if ($this->distance($candidate[0], $point) <= config('tracking.stationary_radius_m')) {
                // Even a discarded excursion cannot bridge a long evidence gap.
                if ($time - strtotime($candidate[array_key_last($candidate)]->effective_timestamp) > config('tracking.stationary_max_gap_seconds')) {
                    $finish();
                    $candidate = [];
                }
                $candidate[] = $point;
                $outside = null;
            } elseif ($outside === null) {
                $outside = $point;
            } else {
                // Two successive outside readings confirm departure; end at last inside reading.
                $finish();
                $candidate = $this->distance($outside, $point) <= config('tracking.stationary_radius_m')
                    ? [$outside, $point] : [$point];
                $outside = null;
            }
        }
        $finish();

        return $stops;
    }

    private function distance(object $a, object $b): float
    {
        $lat = deg2rad((float) $b->latitude - (float) $a->latitude);
        $lng = deg2rad((float) $b->longitude - (float) $a->longitude);
        $h = sin($lat / 2) ** 2 + cos(deg2rad((float) $a->latitude)) * cos(deg2rad((float) $b->latitude)) * sin($lng / 2) ** 2;

        return 6371000 * 2 * asin(sqrt(min(1, $h)));
    }
}
