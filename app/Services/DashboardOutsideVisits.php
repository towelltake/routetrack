<?php

namespace App\Services;

use App\Models\CustomerMaster;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class DashboardOutsideVisits
{
    public function build(Collection $journeys): Collection
    {
        if ($journeys->isEmpty()) return collect();
        $visits = DB::table('customervisitlog')
            ->whereIn('customercode', CustomerMaster::query()->select('customercode'))
            ->whereIn('routekey', $journeys->pluck('routekey'))->get()->groupBy('routekey');
        $work = app(WorkTracking::class);
        $detector = app(StationaryDetection::class);
        // A GPS observation window belongs to a route/date, not to each repeated journey that day.
        return $journeys->groupBy(fn ($journey) => $journey->routecode.':'.substr((string) $journey->routestartdate, 0, 10))
            ->map(function ($dayJourneys) use ($visits, $work, $detector) {
                $journey = $dayJourneys->sortByDesc('routekey')->first();
                $date = substr((string) $journey->routestartdate, 0, 10);
                $tracking = $work->load((int) $journey->routecode, $date, $journey);
                $start = $tracking['start']; $end = $tracking['end'];
                $points = $tracking['all_points'] ?? $tracking['points'];
                $intervals = [];
                foreach ($dayJourneys as $dayJourney) foreach ($visits->get($dayJourney->routekey, collect()) as $visit) {
                    $timing = app(DashboardAnalysis::class)->journeyTiming((object) [
                        'routestartdate' => $visit->logstartdate, 'routestarttime' => $visit->logstarttime,
                        'routeenddate' => $visit->logenddate, 'routeendtime' => $visit->logendtime, 'routeclosed' => 1,
                    ]);
                    $a = $timing['start']; $b = $timing['end'];
                    if ($b === null && $points) $b = strtotime($points[array_key_last($points)]->effective_timestamp);
                    if ($start !== null && $end !== null && $a !== null && $b !== null && $b > $a && $b > $start && $a < $end) $intervals[] = [max($start, $a), min($end, $b)];
                }
                sort($intervals); $merged = [];
                foreach ($intervals as [$a, $b]) {
                    $last = array_key_last($merged);
                    if ($last !== null && $a <= $merged[$last][1]) $merged[$last][1] = max($merged[$last][1], $b);
                    else $merged[] = [$a, $b];
                }
                $cft = $start === null ? null : array_sum(array_map(fn ($interval) => ($interval[1] - $interval[0]) / 60, $merged));
                $stationary = null; $unreliable = null;
                if ($tracking['available'] && count($points) >= 2) {
                    $stationary = 0; $unreliable = 0;
                    foreach ($detector->detect($points, true) as $stop) {
                        $a = strtotime($stop['start_time']); $b = strtotime($stop['end_time']); $seconds = $b - $a;
                        foreach ($merged as [$visitStart, $visitEnd]) $seconds -= max(0, min($b, $visitEnd) - max($a, $visitStart));
                        $stationary += max(0, $seconds) / 60;
                        if ($stop['unreliable'] ?? false) $unreliable += max(0, $seconds) / 60;
                    }
                }
                return ['id' => $journey->routekey, 'routekey' => $journey->routekey, 'customercode' => null,
                    'routecode' => $journey->routecode, 'salesman' => $journey->salesman ?? '',
                    'start' => $start === null ? null : date('Y-m-d H:i:s', $start),
                    'end' => $end === null ? null : date('Y-m-d H:i:s', $end),
                    'status' => 'GPS tracking', 'tracking_message' => $tracking['reason'],
                    'unreliable_idle' => $unreliable, 'reliable_idle' => $stationary === null ? null : $stationary - $unreliable,
                    'customer_cft' => $cft, 'stationary' => $stationary, 'travel' => null];
            })->values();
    }
}
