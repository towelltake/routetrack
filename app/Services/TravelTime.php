<?php

namespace App\Services;

class TravelTime
{
    /** Subtract disjoint, journey-clipped intervals without changing journey duration. */
    public function summarize(array $actual, iterable $visits): array
    {
        $duration = $actual['duration'] ?? null;
        $start = $actual['journey_start_timestamp'] ?? null;
        $empty = ['travel_time' => null, 'travel_visit_seconds' => null, 'travel_idle_seconds' => null, 'travel_unknown_seconds' => null];
        if ($duration === null || $start === null) return $empty;
        $end = $start + $duration;
        $clip = function ($a, $b) use ($start, $end) {
            return $a !== false && $b !== false && $a !== null && $b !== null && $b > $a
                ? [max($start, $a), min($end, $b)] : null;
        };
        $visitIntervals = [];
        foreach ($visits as $visit) {
            if (empty($visit['visit_start_date']) || empty($visit['visit_start_time']) || empty($visit['visit_end_date']) || empty($visit['visit_end_time'])) continue;
            $visitIntervals[] = $clip(strtotime($visit['visit_start_date'].' '.$visit['visit_start_time']), strtotime($visit['visit_end_date'].' '.$visit['visit_end_time']));
        }
        $stops = [];
        foreach ($actual['stationary_periods'] ?? [] as $stop) $stops[] = $clip(strtotime($stop['start_time']), strtotime($stop['end_time']));
        $gaps = [];
        foreach ($actual['gps_gaps'] ?? [] as $gap) $gaps[] = $clip(strtotime($gap['start_time']), strtotime($gap['end_time']));
        $first = $actual['travel_gps_first'] ?? null;
        $last = $actual['travel_gps_last'] ?? null;
        if ($first === null || $last === null || $last <= $first) {
            $gaps[] = [$start, $end];
        } else {
            $gaps[] = $clip($start, $first);
            $gaps[] = $clip($last, $end);
        }
        $visitsTotal = $this->covered($visitIntervals);
        $visitsAndStops = $this->covered([...$visitIntervals, ...$stops]);
        $all = $this->covered([...$visitIntervals, ...$stops, ...$gaps]);
        return [
            'travel_time' => max(0, $duration - $all),
            'travel_visit_seconds' => $visitsTotal,
            'travel_idle_seconds' => $visitsAndStops - $visitsTotal,
            'travel_unknown_seconds' => $all - $visitsAndStops,
        ];
    }

    private function covered(array $intervals): int
    {
        $intervals = array_values(array_filter($intervals, fn ($interval) => $interval && $interval[1] > $interval[0]));
        sort($intervals);
        $total = 0;
        $previousEnd = PHP_INT_MIN;
        foreach ($intervals as [$start, $end]) {
            $total += max(0, $end - max($start, $previousEnd));
            $previousEnd = max($previousEnd, $end);
        }
        return $total;
    }
}
