<?php

namespace App\Services;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class DashboardOutsideVisits
{
    public function build(Collection $journeys): Collection
    {
        if ($journeys->isEmpty()) return collect();
        $visits = DB::table('customervisitlog')->whereIn('routekey', $journeys->pluck('routekey'))
            ->get(['routekey', 'logstartdate', 'logstarttime', 'logenddate', 'logendtime'])->groupBy('routekey');
        $starts = DB::table('startendday')->whereIn('routecode', $journeys->pluck('routecode')->unique())
            ->whereDate('routestartdate', '>=', substr((string) $journeys->min('routestartdate'), 0, 10))
            ->get(['routecode', 'routestartdate', 'routestarttime'])->groupBy('routecode');
        $detector = app(StationaryDetection::class);
        return $journeys->map(function ($journey) use ($visits, $starts, $detector) {
            $timing = app(DashboardAnalysis::class)->journeyTiming($journey);
            ['start' => $start, 'end' => $end, 'duration' => $duration] = $timing;
            $intervals = [];
            foreach ($visits->get($journey->routekey, collect()) as $visit) {
                $visitTiming = app(DashboardAnalysis::class)->journeyTiming((object) [
                    'routestartdate' => $visit->logstartdate, 'routestarttime' => $visit->logstarttime,
                    'routeenddate' => $visit->logenddate, 'routeendtime' => $visit->logendtime, 'routeclosed' => 1,
                ]);
                $a = $visitTiming['start'];
                $b = $visitTiming['end'] ?? ((int) $journey->routeclosed !== 1 ? $end : null);
                if ($duration !== null && $a !== null && $b !== null && $b >= $a && $b > $start && $a < $end) $intervals[] = [max($start, $a), min($end, $b)];
            }
            sort($intervals);
            $merged = [];
            foreach ($intervals as [$a, $b]) {
                $last = array_key_last($merged);
                if ($last !== null && $a <= $merged[$last][1]) $merged[$last][1] = max($merged[$last][1], $b);
                else $merged[] = [$a, $b];
            }
            $cft = $duration === null ? null : array_sum(array_map(fn ($interval) => ($interval[1] - $interval[0]) / 60, $merged));
            $stationary = null;
            if ($duration !== null) {
                $next = $starts->get($journey->routecode, collect())->map(fn ($row) => strtotime(substr($row->routestartdate, 0, 10).' '.$row->routestarttime))
                    ->filter(fn ($value) => $value !== false && $value > $start)->min();
                $connection = DB::connection('tracking_pgsql');
                $timestamp = $connection->getDriverName() === 'sqlite' ? "date || ' ' || time" : 'date + time';
                $points = $connection->table('trac_routetrack')->where('routecode', $journey->routecode)
                    ->whereBetween('date', [date('Y-m-d', $start), date('Y-m-d', $end)])
                    ->whereRaw("{$timestamp} >= ?", [date('Y-m-d H:i:s', $start)])
                    ->whereRaw("{$timestamp} <= ?", [date('Y-m-d H:i:s', $end)])
                    ->when($next, fn ($q) => $q->whereRaw("{$timestamp} < ?", [date('Y-m-d H:i:s', $next)]))
                    ->selectRaw("trac_routetrack.*, {$timestamp} as effective_timestamp")
                    ->orderBy('effective_timestamp')->orderBy('id')->get()
                    ->filter(fn ($point) => $detector->usable($point))->values()->all();
                if (count($points) >= 2) {
                    $stationary = 0;
                    foreach ($detector->detect($points) as $stop) {
                        $a = strtotime($stop['start_time']);
                        $b = strtotime($stop['end_time']);
                        $seconds = $b - $a;
                        foreach ($merged as [$visitStart, $visitEnd]) $seconds -= max(0, min($b, $visitEnd) - max($a, $visitStart));
                        $stationary += max(0, $seconds) / 60;
                    }
                }
            }
            return ['id' => $journey->routekey, 'routekey' => $journey->routekey, 'customercode' => null,
                'routecode' => $journey->routecode, 'salesman' => $journey->salesman,
                'start' => $start === null ? null : date('Y-m-d H:i:s', $start),
                'end' => $end === null ? null : date('Y-m-d H:i:s', $end),
                'status' => (int) $journey->routeclosed === 1 ? 'Closed' : 'Open',
                'customer_cft' => $cft, 'stationary' => $stationary,
                'travel' => $duration !== null && $stationary !== null ? max(0, $duration - $cft - $stationary) : null];
        });
    }
}
