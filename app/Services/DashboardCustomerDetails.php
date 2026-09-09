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
        if ($type === 'otp') {
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
        $customers = DB::table('customermaster')->whereIn('customercode', $rows->pluck('customercode')->unique())
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
