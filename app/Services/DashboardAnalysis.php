<?php

namespace App\Services;

use Illuminate\Support\Collection;

class DashboardAnalysis
{
    public function build(Collection $journeys, Collection $plans, Collection $visits, Collection $operations, array $transactions, array $amounts, Collection $currencies, array $otp): array
    {
        $plansByJourney = $plans->groupBy('routekey');
        $visitsByJourney = $visits->groupBy('routekey');
        $otpByJourney = collect($otp['details'])->groupBy('routekey');
        $moneyByJourney = [];
        foreach ($amounts as $type => $values) $moneyByJourney[$type] = $values->groupBy('routekey');
        $rows = [];
        foreach ($journeys as $journey) {
            $plan = $plansByJourney->get($journey->routekey, collect())->keyBy('customercode');
            $journeyVisits = $visitsByJourney->get($journey->routekey, collect());
            $operational = app(OperationalTime::class)->fromLogs($journeyVisits, $otp['by_visit'] ?? []);
            $closed = (int) $journey->routeclosed === 1;
            ['start' => $start, 'end' => $end, 'duration' => $duration] = $this->journeyTiming($journey);
            $row = [
                'routekey' => (string) $journey->routekey, 'routecode' => (string) $journey->routecode,
                'route' => $journey->routename ?? 'Route '.$journey->routecode,
                'date' => substr((string) $journey->routestartdate, 0, 10), 'closed' => $closed,
                'cmpycode' => (string) ($journey->cmpycode ?? ''), 'division' => $journey->division ?? 'Unassigned',
                'entity' => ($journey->entity ?? null) ?: 'Unassigned',
                'clustercode' => (string) ($journey->clustercode ?? ''), 'cluster' => $journey->cluster ?? 'Unassigned',
                'regionmstcode' => (string) ($journey->regionmstcode ?? ''), 'region' => $journey->region ?? 'Unassigned',
                'salesmancode' => (string) ($journey->salesmancode ?? ''), 'salesperson' => $journey->salesperson ?? 'Unassigned',
                'planned' => $plan->count(), 'covered' => 0, 'pending' => 0, 'missed' => 0,
                'visits' => $journeyVisits->count(), 'customer_codes' => $journeyVisits->pluck('customercode')->map(fn ($code) => (string) $code)->unique()->values()->all(),
                'completed' => 0, 'productive' => 0, 'nonproductive' => 0,
                'unplanned' => 0, 'unplanned_customers' => 0, 'out_of_sequence' => 0, 'repeat' => 0,
                'actual_cft' => 0, 'expected_cft' => 0, 'configured_actual_cft' => 0, 'configured_visits' => 0,
                'missing_cft' => 0, 'incomplete_visits' => 0,
                'duration' => $duration, 'visit_time' => null, 'remaining_time' => null, 'stationary_time' => null,
                'operational_minutes' => $operational['minutes'], 'operational_start' => $operational['start'], 'operational_end' => $operational['end'],
                'timeline' => [
                    'start' => $start === null ? null : date('Y-m-d H:i:s', $start),
                    'end' => $end === null ? null : date('Y-m-d H:i:s', $end),
                    'visits' => [],
                ],
                'distance' => $closed && ($journey->routestartodometer ?? 0) > 0 && ($journey->routeendodometer ?? 0) >= $journey->routestartodometer
                    ? (float) $journey->routeendodometer - (float) $journey->routestartodometer : null,
                'otp' => $otpByJourney->get($journey->routekey, collect())->count(),
                'otp_events' => $otpByJourney->get($journey->routekey, collect())->values()->all(),
                'amounts' => [], 'issues' => [],
            ];
            $seen = [];
            $recordedVisitMinutes = 0;
            $eligibleCustomers = []; $productiveCustomers = []; $salesOrderCustomers = []; $collectionCustomers = [];
            $row['sales_order_productive'] = 0; $row['collection_productive'] = 0;
            foreach ($journeyVisits->values() as $index => $visit) {
                $customer = (string) $visit->customercode;
                if (!($visit->productivity_excluded ?? false)) $eligibleCustomers[$customer] = true;
                $seen[$customer] = ($seen[$customer] ?? 0) + 1;
                if ($seen[$customer] > 1) $row['repeat']++;
                if ($plan->isNotEmpty()) {
                    if (!$plan->has($customer) && $seen[$customer] === 1) $row['unplanned_customers']++;
                    if (!$plan->has($customer)) $row['unplanned']++;
                    elseif ($seen[$customer] === 1 && $plan[$customer]->sequencenumber > 0 && (int) $plan[$customer]->sequencenumber !== $index + 1) $row['out_of_sequence']++;
                }
                if ($visit->expected_minutes <= 0) $row['missing_cft']++;
                $visitStart = $this->timestamp($visit->logstartdate, $visit->logstarttime);
                $visitEnd = $this->timestamp($visit->logenddate, $visit->logendtime);
                if ($visitStart === null || $visitEnd === null || $visitEnd < $visitStart) {
                    $row['incomplete_visits']++;
                    continue;
                }
                $minutes = ($visitEnd - $visitStart) / 60;
                $row['timeline']['visits'][] = [date('Y-m-d H:i:s', $visitStart), date('Y-m-d H:i:s', $visitEnd)];
                if (!($visit->productivity_excluded ?? false)) $row['completed']++;
                $recordedVisitMinutes += $minutes;
                $hasOtp = !empty($otp['by_visit'][$visit->routekey.':'.$visit->logkey]);
                if (!$hasOtp) $row['actual_cft'] += $minutes;
                if ($visit->expected_minutes > 0 && !$hasOtp) {
                    $row['configured_visits']++;
                    $row['expected_cft'] += $visit->expected_minutes;
                    $row['configured_actual_cft'] += $minutes;
                }
                $operation = $operations->get($visit->routekey.':'.$visit->logkey);
                $key = $visit->routekey.':'.($operation?->visitkey ?? '');
                if (!($visit->productivity_excluded ?? false)) {
                    $salesOrder = $transactions['sales']->has($key) || $transactions['orders']->has($key);
                    $collection = $transactions['collections']->has($key);
                    if ($salesOrder) { $row['sales_order_productive']++; $salesOrderCustomers[$customer] = true; }
                    if ($collection) { $row['collection_productive']++; $collectionCustomers[$customer] = true; }
                    if ($salesOrder || $collection) { $row['productive']++; $productiveCustomers[$customer] = true; }
                }
            }
            $row['covered'] = $plan->keys()->filter(fn ($code) => isset($seen[(string) $code]))->count();
            $row[$closed ? 'missed' : 'pending'] = $row['planned'] - $row['covered'];
            $row['nonproductive'] = $row['completed'] - $row['productive'];
            $row['eligible_customers'] = count($eligibleCustomers);
            $row['productive_customers'] = count($productiveCustomers);
            $row['sales_order_customers'] = count($salesOrderCustomers);
            $row['collection_customers'] = count($collectionCustomers);
            if ($duration !== null) {
                $row['visit_time'] = $recordedVisitMinutes;
                $row['remaining_time'] = max(0, $duration - $row['visit_time']);
            }
            foreach ($moneyByJourney as $type => $values) {
                $row['amounts'][$type] = $values->get($journey->routekey, collect())->map(fn ($value) => [
                    'currencycode' => (string) $value->currencycode,
                    'currency' => $currencies->get($value->currencycode)?->currencysymbol ?: ((int) $value->currencycode === 0 ? 'Unspecified currency' : 'Currency '.$value->currencycode),
                    'amount' => (string) $value->amount,
                ])->values()->all();
            }
            foreach ([
                ['missed', 'Missed customers', 'review'], ['unplanned', 'Unplanned visits', 'review'],
                ['out_of_sequence', 'Out of sequence', 'review'], ['repeat', 'Repeat visits', 'info'],
                ['missing_cft', 'Missing expected CFT', 'data'],
            ] as [$key, $label, $level]) {
                if ($row[$key]) $row['issues'][] = ['label' => $label, 'count' => $row[$key], 'level' => $level];
            }
            if (!$row['planned']) $row['issues'][] = ['label' => 'Journey plan unavailable', 'count' => 1, 'level' => 'data'];
            if ($closed && $row['incomplete_visits']) $row['issues'][] = ['label' => 'Incomplete visit timestamps', 'count' => $row['incomplete_visits'], 'level' => 'data'];
            if ($closed && $duration === null) $row['issues'][] = ['label' => 'Journey timestamps incomplete', 'count' => 1, 'level' => 'data'];
            $rows[] = $row;
        }
        return ['journeys' => $rows, 'distance_source' => 'Recorded odometer difference', 'stationary_available' => false];
    }

    public function journeyTiming(object $journey): array
    {
        $start = $this->timestamp($journey->routestartdate, $journey->routestarttime);
        $end = (int) $journey->routeclosed === 1
            ? $this->timestamp($journey->routeenddate, $journey->routeendtime)
            : (!empty($journey->last_location_time) ? (strtotime($journey->last_location_time) ?: null) : null);
        return ['start' => $start, 'end' => $end, 'duration' => $start !== null && $end !== null && $end >= $start ? ($end - $start) / 60 : null];
    }

    private function timestamp(mixed $date, mixed $time): ?int
    {
        if (!$date || !$time || str_starts_with((string) $date, '0000-')) return null;
        $value = strtotime(substr((string) $date, 0, 10).' '.$time);
        return $value === false ? null : $value;
    }
}
