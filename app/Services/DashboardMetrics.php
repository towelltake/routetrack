<?php

namespace App\Services;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class DashboardMetrics
{
    public function summarize(Collection $journeys): array
    {
        $keys = $journeys->pluck('routekey');
        $byJourney = $journeys->keyBy('routekey');
        $plans = DB::table('routesequencecustomerstatus')
            ->whereIn('routekey', $keys)->where('schelduledflag', 1)
            ->select('routekey', 'customercode')->selectRaw('COALESCE(MIN(CASE WHEN sequencenumber > 0 THEN sequencenumber END), 0) as sequencenumber')
            ->groupBy('routekey', 'customercode')->get();
        $visits = DB::table('customervisitlog as v')
            ->whereIn('v.routekey', $keys)
            ->orderBy('v.routekey')->orderBy('v.logstartdate')->orderBy('v.logstarttime')->orderBy('v.logkey')
            ->get(['v.logkey', 'v.routekey', 'v.customercode', 'v.logstartdate', 'v.logstarttime', 'v.logenddate', 'v.logendtime',
                DB::raw('COALESCE(v.cft, 0) as expected_minutes')]);
        $operations = DB::table('customeroperationscontrol')
            ->whereIn('routekey', $keys)->where('log_id', '>', 0)
            ->orderByDesc('primary_id')->get(['routekey', 'log_id', 'visitkey'])
            ->unique(fn ($row) => $row->routekey.':'.$row->log_id)
            ->keyBy(fn ($row) => $row->routekey.':'.$row->log_id);

        $transactions = [];
        $amounts = [];
        $journeyAmounts = [];
        foreach (['sales' => ['invoiceheader', 'totalsalesamount'], 'orders' => ['salesorderheader', 'totalinvoiceamount'], 'collections' => ['arheader', 'amountpaid']] as $type => [$table, $amount]) {
            $query = DB::table($table)->whereIn('routekey', $keys)
                ->where(fn ($query) => $query->whereNull('voidflag')->orWhere('voidflag', 0));
            $transactions[$type] = (clone $query)->where($amount, '>', 0)->whereNotNull('visitkey')->where('visitkey', '>', 0)
                ->select('routekey', 'visitkey')->distinct()->get()
                ->keyBy(fn ($row) => $row->routekey.':'.$row->visitkey);
            $journeyAmounts[$type] = (clone $query)->selectRaw("routekey, COALESCE(currencycode, 0) as currencycode, SUM(COALESCE({$amount}, 0)) as amount")
                ->groupBy('routekey')->groupByRaw('COALESCE(currencycode, 0)')->get();
            // Aggregate transaction headers before any visit joins to avoid multiplying amounts.
            $amounts[$type] = (clone $query)->selectRaw("COALESCE(currencycode, 0) as currencycode, SUM(COALESCE({$amount}, 0)) as amount, COUNT(*) as documents")
                ->groupByRaw('COALESCE(currencycode, 0)')->orderBy('currencycode')->get();
        }
        $returnSources = null;
        foreach (['invoiceheader', 'salesorderheader'] as $table) {
            $source = DB::table($table)->whereIn('routekey', $keys)->where('voidflag', 0)
                ->whereRaw('COALESCE(totalreturnamount, 0) + COALESCE(totaldamagedamount, 0) > 0')
                ->selectRaw('routekey, COALESCE(currencycode, 0) as currencycode, COALESCE(totalreturnamount, 0) + COALESCE(totaldamagedamount, 0) as amount');
            if ($returnSources === null) $returnSources = $source;
            else $returnSources->unionAll($source);
        }
        $journeyAmounts['returns'] = DB::query()->fromSub($returnSources, 'returns_documents')
            ->selectRaw('routekey, currencycode, SUM(amount) as amount, COUNT(*) as documents')
            ->groupBy('routekey', 'currencycode')->get();
        $amounts['returns'] = $journeyAmounts['returns']->groupBy('currencycode')->map(fn ($rows, $currency) => (object) [
            'currencycode' => $currency, 'amount' => $rows->sum('amount'), 'documents' => $rows->sum('documents'),
        ])->values();
        $currencies = DB::table('currencymaster')->whereIn('currencycode', collect($amounts)->flatten(1)->pluck('currencycode')->unique())
            ->get(['currencycode', 'currencysymbol'])->keyBy('currencycode');
        foreach ($amounts as $type => $rows) {
            $amounts[$type] = $rows->map(fn ($row) => [
                'currency' => $currencies->get($row->currencycode)?->currencysymbol
                    ?: ((int) $row->currencycode === 0 ? 'Unspecified currency' : 'Currency '.$row->currencycode),
                'amount' => (string) $row->amount,
                'documents' => (int) $row->documents,
            ])->values()->all();
        }

        $visited = [];
        $completed = 0;
        $productive = 0;
        $actualSeconds = 0;
        $configuredSeconds = 0;
        $expectedMinutes = 0;
        $configuredVisits = 0;
        foreach ($visits as $visit) {
            $visited[$visit->routekey.':'.$visit->customercode] = true;
            $start = $this->timestamp($visit->logstartdate, $visit->logstarttime);
            $end = $this->timestamp($visit->logenddate, $visit->logendtime);
            if ($start === null || $end === null || $end < $start) {
                continue;
            }
            $completed++;
            $actualSeconds += $end - $start;
            if ($visit->expected_minutes > 0) {
                $configuredVisits++;
                $configuredSeconds += $end - $start;
                $expectedMinutes += (int) $visit->expected_minutes;
            }
            $operation = $operations->get($visit->routekey.':'.$visit->logkey);
            $transactionKey = $visit->routekey.':'.($operation?->visitkey ?? '');
            if ($transactions['sales']->has($transactionKey) || $transactions['orders']->has($transactionKey)) {
                $productive++;
            }
        }
        $covered = 0;
        $pending = 0;
        $missed = 0;
        foreach ($plans as $plan) {
            if (isset($visited[$plan->routekey.':'.$plan->customercode])) {
                $covered++;
            } elseif ((int) $byJourney->get($plan->routekey)->routeclosed === 1) {
                $missed++;
            } else {
                $pending++;
            }
        }

        $otp = $this->otp($journeys, $visits);
        $analysis = app(DashboardAnalysis::class)->build($journeys, $plans, $visits, $operations, $transactions, $journeyAmounts, $currencies, $otp);
        $timed = collect($analysis['journeys'])->filter(fn ($row) => $row['duration'] !== null);

        return [
            'unplanned_customers' => collect($analysis['journeys'])->sum('unplanned_customers'),
            'duration_minutes' => $timed->isEmpty() ? null : $timed->sum('duration'),
            'outside_visit_minutes' => $timed->isEmpty() ? null : round(max(0, $timed->sum('duration') - $actualSeconds / 60), 1),
            'operational_minutes' => $actualSeconds / 60,
            'otp_customer_minutes' => $otp['customer_minutes'],
            'actual_face_minutes' => $actualSeconds / 60 - $otp['customer_minutes'],
            'duration_available_journeys' => $timed->count(),
            'duration_missing_journeys' => $journeys->count() - $timed->count(),
            'planned_cft_minutes' => $expectedMinutes,
            'comparable_actual_cft_minutes' => $configuredSeconds / 60,
            'journeys_started' => $journeys->count(),
            'unique_routes' => $journeys->pluck('routecode')->unique()->count(),
            'routes_not_started' => null,
            'planned_customers' => $plans->count(),
            'planned_visited' => $covered,
            'coverage_percent' => $plans->isEmpty() ? null : round(100 * $covered / $plans->count(), 1),
            'pending_customers' => $pending,
            'missed_customers' => $missed,
            'journeys_without_plan' => $journeys->count() - $plans->pluck('routekey')->unique()->count(),
            'completed_visits' => $completed,
            'productive_visits' => $productive,
            'nonproductive_visits' => $completed - $productive,
            'productivity_percent' => $completed ? round(100 * $productive / $completed, 1) : null,
            'cft_minutes' => round($actualSeconds / 60, 1),
            'cft_variance_minutes' => $configuredVisits ? round($configuredSeconds / 60 - $expectedMinutes, 1) : null,
            'cft_configured_visits' => $configuredVisits,
            'amounts' => $amounts,
            'otp' => ['events' => $otp['events'], 'visits' => $otp['visits']],
            'analysis' => $analysis,
        ];
    }

    public function otp(Collection $journeys, Collection $visits): array
    {
        if ($journeys->isEmpty()) {
            return ['events' => 0, 'visits' => 0, 'details' => [], 'by_visit' => [], 'customer_minutes' => 0];
        }
        // OTP has no verified journey key; associate it by route and journey time window.
        $starts = DB::table('startendday')->whereIn('routecode', $journeys->pluck('routecode')->unique())
            ->whereDate('routestartdate', '>=', substr((string) $journeys->min('routestartdate'), 0, 10))
            ->orderBy('routestartdate')->orderBy('routestarttime')->orderBy('routekey')
            ->get(['routekey', 'routecode', 'routestartdate', 'routestarttime'])
            ->groupBy('routecode');
        $windows = [];
        foreach ($journeys as $journey) {
            $start = $this->timestamp($journey->routestartdate, $journey->routestarttime ?: '00:00:00');
            if ($start === null) {
                continue;
            }
            $end = (int) $journey->routeclosed === 1 ? $this->timestamp($journey->routeenddate, $journey->routeendtime) : null;
            $next = $starts->get($journey->routecode)->map(fn ($row) => $this->timestamp($row->routestartdate, $row->routestarttime ?: '00:00:00'))
                ->filter(fn ($time) => $time !== null && $time > $start)->min();
            $windows[$journey->routecode][] = ['key' => $journey->routekey, 'start' => $start, 'end' => $end, 'next' => $next];
        }
        $events = DB::table('otplogdetail')->whereIn('routecode', array_keys($windows))
            ->whereDate('otpdate', '>=', substr((string) $journeys->min('routestartdate'), 0, 10))
            ->orderBy('otpdate')->orderBy('otptime')->orderBy('otplogid')
            ->get(['otplogid', 'routecode', 'customercode', 'otpdate', 'otptime', 'otptype', 'username', 'otpreason', 'comments']);
        $details = [];
        $count = 0;
        $matchedVisits = [];
        $byVisit = [];
        $visitsByCustomer = $visits->groupBy(fn ($row) => $row->routekey.':'.$row->customercode);
        foreach ($events as $event) {
            $timestamp = $this->timestamp($event->otpdate, $event->otptime);
            if ($timestamp === null) {
                continue;
            }
            foreach ($windows[$event->routecode] as $window) {
                if ($timestamp < $window['start'] || ($window['end'] !== null && $timestamp > $window['end']) || ($window['next'] !== null && $timestamp >= $window['next'])) {
                    continue;
                }
                $count++;
                $details[] = (array) $event + ['routekey' => $window['key']];
                // GPS override OTPs can precede check-in; match the nearest visit start,
                // as route tracking does, but only for this customer and journey.
                $matchedVisit = null;
                $nearest = PHP_INT_MAX;
                foreach ($visitsByCustomer->get($window['key'].':'.$event->customercode, collect()) as $visit) {
                    $start = $this->timestamp($visit->logstartdate, $visit->logstarttime);
                    if ($start !== null && abs($timestamp - $start) < $nearest) {
                        $matchedVisit = $visit;
                        $nearest = abs($timestamp - $start);
                    }
                }
                if ($matchedVisit !== null) {
                    $key = $matchedVisit->routekey.':'.$matchedVisit->logkey;
                    $byVisit[$key][] = (array) $event;
                    $start = $this->timestamp($matchedVisit->logstartdate, $matchedVisit->logstarttime);
                    $end = $this->timestamp($matchedVisit->logenddate, $matchedVisit->logendtime);
                    if ($end !== null && $end >= $start) $matchedVisits[$key] = ($end - $start) / 60;
                }
                break;
            }
        }

        return ['events' => $count, 'visits' => count($matchedVisits), 'details' => $details,
            'by_visit' => $byVisit, 'customer_minutes' => array_sum($matchedVisits)];
    }

    private function timestamp(mixed $date, mixed $time): ?int
    {
        if (! $date || ! $time || str_starts_with((string) $date, '0000-')) {
            return null;
        }
        $value = strtotime(substr((string) $date, 0, 10).' '.$time);

        return $value === false ? null : $value;
    }
}
