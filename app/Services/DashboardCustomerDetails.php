<?php

namespace App\Services;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class DashboardCustomerDetails
{
    public function build(Collection $journeys, string $type): array
    {
        if ($journeys->isEmpty()) return ['groups' => []];
        $keys = $journeys->pluck('routekey');
        $rows = collect();
        if (in_array($type, ['duration', 'outside'])) {
            $operational = $type === 'outside' ? collect($this->build($journeys, 'operational')['groups'])
                ->mapWithKeys(fn ($group) => [$group['routekey'] => $group['rows']->sum('actual_cft')]) : collect();
            $rows = $journeys->map(function ($journey) use ($operational) {
                $timing = app(DashboardAnalysis::class)->journeyTiming($journey);
                return ['id' => $journey->routekey, 'routekey' => $journey->routekey, 'customercode' => null,
                    'routecode' => $journey->routecode, 'salesman' => $journey->salesman,
                    'start' => $timing['start'] === null ? null : date('Y-m-d H:i:s', $timing['start']),
                    'end' => $timing['end'] === null ? null : date('Y-m-d H:i:s', $timing['end']),
                    'operational' => $operational->get($journey->routekey, 0),
                    'outside' => $timing['duration'] === null ? null : max(0, $timing['duration'] - $operational->get($journey->routekey, 0)),
                    'duration' => $timing['duration'], 'status' => (int) $journey->routeclosed === 1 ? 'Closed' : 'Open'];
            });
        } elseif (in_array($type, ['cft', 'operational', 'otp_time', 'actual_face'])) {
            $visits = DB::table('customervisitlog')->whereIn('routekey', $keys)
                ->orderBy('logstartdate')->orderBy('logstarttime')->orderBy('logkey')
                ->get(['logkey', 'routekey', 'customercode', 'cft', 'logstartdate', 'logstarttime', 'logenddate', 'logendtime']);
            $otp = in_array($type, ['otp_time', 'actual_face']) ? app(DashboardMetrics::class)->otp($journeys, $visits)['by_visit'] : [];
            $rows = $visits->map(function ($visit) use ($otp) {
                    $validStart = $visit->logstartdate && $visit->logstarttime && !str_starts_with($visit->logstartdate, '0000-');
                    $validEnd = $visit->logenddate && $visit->logendtime && !str_starts_with($visit->logenddate, '0000-');
                    $start = $validStart ? strtotime(substr($visit->logstartdate, 0, 10).' '.$visit->logstarttime) : false;
                    $end = $validEnd ? strtotime(substr($visit->logenddate, 0, 10).' '.$visit->logendtime) : false;
                    $actual = $start !== false && $end !== false && $end >= $start ? ($end - $start) / 60 : null;
                    $planned = max(0, (float) ($visit->cft ?? 0));
                    return ['id' => $visit->logkey, 'routekey' => $visit->routekey, 'customercode' => $visit->customercode,
                        'date' => substr((string) $visit->logstartdate, 0, 10), 'time' => $visit->logstarttime, 'planned_cft' => $planned,
                        'check_in' => $start === false ? null : date('Y-m-d H:i:s', $start),
                        'check_out' => $end === false ? null : date('Y-m-d H:i:s', $end),
                        'otp_times' => array_map(fn ($event) => substr((string) $event['otpdate'], 0, 10).' '.$event['otptime'], $otp[$visit->routekey.':'.$visit->logkey] ?? []),
                        'actual_cft' => $actual, 'variance' => $planned > 0 && $actual !== null ? $actual - $planned : null];
                });
            if ($type !== 'cft') $rows = $rows->filter(fn ($row) => $row['actual_cft'] !== null
                && ($type !== 'otp_time' || count($row['otp_times']) > 0)
                && ($type !== 'actual_face' || count($row['otp_times']) === 0));
        } elseif (in_array($type, ['sales', 'orders', 'collections', 'returns'])) {
            $sources = ['sales' => ['invoiceheader', 'totalsalesamount'], 'orders' => ['salesorderheader', 'totalinvoiceamount'], 'collections' => ['arheader', 'amountpaid']];
            foreach ($type === 'returns' ? ['sales', 'orders'] : [$type] as $source) {
                [$table, $amount] = $sources[$source];
                $expression = $type === 'returns' ? 'COALESCE(totalreturnamount, 0) + COALESCE(totaldamagedamount, 0)' : "COALESCE({$amount}, 0)";
                $query = DB::table($table)->whereIn('routekey', $keys);
                if ($type === 'returns') $query->where('voidflag', 0)->whereRaw($expression.' > 0');
                else $query->where(fn ($q) => $q->whereNull('voidflag')->orWhere('voidflag', 0));
                $records = $query->orderBy('transactiondate')->orderBy('transactiontime')->get([
                    'routekey', 'customercode', 'transactionkey', 'documentnumber', 'transactiondate', 'transactiontime', 'currencycode', DB::raw($expression.' as amount'),
                ]);
                foreach ($records as $record) $rows->push([
                    'id' => $source.':'.$record->transactionkey, 'routekey' => $record->routekey, 'customercode' => $record->customercode,
                    'date' => $record->transactiondate, 'time' => $record->transactiontime, 'document' => $record->documentnumber,
                    'source' => ['sales' => 'Invoice', 'orders' => 'Order', 'collections' => 'Receipt'][$source],
                    'currencycode' => $record->currencycode, 'amount' => (float) $record->amount * ($type === 'returns' ? -1 : 1),
                ]);
            }
            $currencies = DB::table('currencymaster')->whereIn('currencycode', $rows->pluck('currencycode')->unique())->pluck('currencysymbol', 'currencycode');
            $rows = $rows->map(fn ($row) => $row + ['currency' => $currencies->get($row['currencycode']) ?: ($row['currencycode'] ? 'Currency '.$row['currencycode'] : 'Unspecified currency')]);
        } elseif ($type === 'otp') {
            $rows = collect(app(DashboardMetrics::class)->otp($journeys, collect())['details'])
                ->map(fn ($event) => [
                    'routekey' => $event['routekey'], 'id' => $event['otplogid'],
                    'customercode' => $event['customercode'], 'date' => $event['otpdate'],
                    'time' => $event['otptime'], 'otp_type' => $event['otptype'],
                    'recorded_by' => $event['username'], 'comments' => $event['comments'], 'reason' => $event['otpreason'],
                ]);
        } else {
            $plans = DB::table('routesequencecustomerstatus')->whereIn('routekey', $keys)->where('schelduledflag', 1)
                ->select('routekey', 'customercode')->distinct()->get()->groupBy('routekey');
            $visits = DB::table('customervisitlog')->whereIn('routekey', $keys)
                ->orderBy('logstartdate')->orderBy('logstarttime')->orderBy('logkey')
                ->get(['logkey', 'routekey', 'customercode', 'logstartdate', 'logstarttime', 'logenddate', 'logendtime']);
            $documents = [];
            $operations = collect();
            if ($type === 'productive') {
                $operations = DB::table('customeroperationscontrol')->whereIn('routekey', $keys)->where('log_id', '>', 0)
                    ->orderByDesc('primary_id')->get(['routekey', 'log_id', 'visitkey'])
                    ->unique(fn ($row) => $row->routekey.':'.$row->log_id)->keyBy(fn ($row) => $row->routekey.':'.$row->log_id);
                foreach (['invoices' => ['invoiceheader', 'totalsalesamount'], 'orders' => ['salesorderheader', 'totalinvoiceamount'], 'collections' => ['arheader', 'amountpaid']] as $label => [$table, $amount]) {
                    // Match the Dashboard card's existing productivity rules and count headers before joins.
                    $documents[$label] = DB::table($table)->whereIn('routekey', $keys)
                        ->where(fn ($q) => $q->whereNull('voidflag')->orWhere('voidflag', 0))
                        ->where('visitkey', '>', 0)
                        ->selectRaw("routekey, visitkey, COUNT(*) as documents, SUM(CASE WHEN {$amount} > 0 THEN 1 ELSE 0 END) as positive_documents")
                        ->groupBy('routekey', 'visitkey')->get()->keyBy(fn ($row) => $row->routekey.':'.$row->visitkey);
                }
            }
            $byJourney = $visits->groupBy('routekey');
            foreach ($journeys as $journey) {
                $planCodes = $plans->get($journey->routekey, collect())->pluck('customercode');
                $journeyVisits = $byJourney->get($journey->routekey, collect());
                $visitedCounts = $journeyVisits->countBy('customercode');
                if ($type === 'planned' || $type === 'unplanned') {
                    // An absent plan cannot establish that a customer was unplanned.
                    if ($planCodes->isEmpty()) continue;
                    $codes = $type === 'planned' ? $planCodes : $journeyVisits->pluck('customercode')->unique()->diff($planCodes);
                    foreach ($codes as $code) $rows->push([
                        'routekey' => $journey->routekey, 'id' => $code, 'customercode' => $code,
                        'status' => $type === 'unplanned' ? 'Unplanned' : ($visitedCounts->get($code, 0) ? 'Visited' : 'Not visited'),
                        'visit_count' => $visitedCounts->get($code, 0),
                    ]);
                } else {
                    foreach ($journeyVisits as $visit) {
                        if (!$visit->logstartdate || !$visit->logstarttime || !$visit->logenddate || !$visit->logendtime || str_starts_with($visit->logstartdate, '0000-') || str_starts_with($visit->logenddate, '0000-')) continue;
                        $start = strtotime($visit->logstartdate.' '.$visit->logstarttime);
                        $end = strtotime($visit->logenddate.' '.$visit->logendtime);
                        if ($start === false || $end === false || $end < $start) continue;
                        $operation = $operations->get($journey->routekey.':'.$visit->logkey);
                        $key = $journey->routekey.':'.($operation?->visitkey ?? '');
                        $productive = ($documents['invoices']->get($key)?->positive_documents ?? 0) > 0 || ($documents['orders']->get($key)?->positive_documents ?? 0) > 0;
                        $rows->push([
                            'routekey' => $journey->routekey, 'id' => $visit->logkey, 'customercode' => $visit->customercode,
                            'time' => $visit->logstarttime, 'status' => $productive ? 'Productive' : 'Nonproductive',
                            'invoices' => (int) ($documents['invoices']->get($key)?->documents ?? 0),
                            'orders' => (int) ($documents['orders']->get($key)?->documents ?? 0),
                            'collections' => (int) ($documents['collections']->get($key)?->documents ?? 0),
                        ]);
                    }
                }
            }
        }
        $customers = in_array($type, ['duration', 'outside']) ? collect() : DB::table('customermaster')->whereIn('customercode', $rows->pluck('customercode')->unique())
            ->get(['customercode', 'alternatecode', 'customeraddress1'])->keyBy('customercode');
        $grouped = $rows->map(function ($row) use ($customers) {
            $customer = $customers->get($row['customercode']);
            return $row + ['customer_code' => $customer?->alternatecode ?: $row['customercode'], 'customer_name' => $customer?->customeraddress1 ?: 'Customer '.$row['customercode']];
        })->groupBy('routekey');
        return ['groups' => $journeys->map(fn ($journey) => [
            'routekey' => $journey->routekey, 'date' => substr((string) $journey->routestartdate, 0, 10),
            'routecode' => $journey->routecode, 'routename' => $journey->routename,
            'salesman' => $journey->salesman, 'rows' => $grouped->get($journey->routekey, collect())->values(),
        ])->filter(fn ($group) => $group['rows']->isNotEmpty())->values()];
    }
}
