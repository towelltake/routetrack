<?php

namespace App\Services;

use App\Models\CustomerMaster;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class DashboardCustomerDetails
{
    public function build(Collection $journeys, string $type): array
    {
        if ($journeys->isEmpty()) return ['groups' => []];
        $keys = $journeys->pluck('routekey');
        $rows = collect();
        if ($type === 'idle') {
            $rows = app(DashboardOutsideVisits::class)->build($journeys);
        } elseif (in_array($type, ['duration', 'outside'])) {
            $operational = $type === 'outside' ? collect($this->build($journeys, 'cft')['groups'])
                ->mapWithKeys(fn ($group) => [$group['routekey'] => $group['rows']->sum('recorded_actual_cft')]) : collect();
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
        } elseif (in_array($type, ['planned', 'unplanned'])) {
            $rows = $this->complianceRows($journeys, $type);
        } elseif ($type === 'coverage') {
            $visits = DB::table('customervisitlog')
                ->whereIn('customercode', CustomerMaster::query()->select('customercode'))
                ->whereIn('routekey', $keys)
                ->orderBy('logstartdate')->orderBy('logstarttime')->orderBy('logkey')
                ->get(['routekey', 'logkey', 'customercode', 'logstartdate', 'logstarttime', 'logenddate', 'logendtime']);
            $otp = app(DashboardMetrics::class)->otp($journeys, $visits)['by_visit'];
            $ordinals = [];
            $rows = $visits->map(function ($visit) use ($otp, &$ordinals) {
                $key = $visit->routekey.':'.$visit->customercode;
                $ordinal = $ordinals[$key] = ($ordinals[$key] ?? 0) + 1;
                $timestamp = function ($date, $time) {
                    if (!$date || !$time || str_starts_with((string) $date, '0000-')) return null;
                    $value = strtotime(substr((string) $date, 0, 10).' '.$time);
                    return $value === false ? null : date('Y-m-d H:i:s', $value);
                };
                $checkIn = $timestamp($visit->logstartdate, $visit->logstarttime);
                $checkOut = $timestamp($visit->logenddate, $visit->logendtime);
                return [
                    'id' => $visit->logkey, 'routekey' => $visit->routekey, 'customercode' => $visit->customercode,
                    'date' => $visit->logstartdate && !str_starts_with((string) $visit->logstartdate, '0000-') ? substr((string) $visit->logstartdate, 0, 10) : null,
                    'check_in' => $checkIn, 'check_out' => $checkOut,
                    'status' => empty($otp[$visit->routekey.':'.$visit->logkey]) ? 'Non-OTP' : 'OTP',
                    'visit_number' => $ordinal, 'is_revisit' => $ordinal > 1,
                ];
            });
        } elseif ($type === 'operational') {
            $visits = DB::table('customervisitlog')
                ->whereIn('customercode', CustomerMaster::query()->select('customercode'))
                ->whereIn('routekey', $keys)
                ->orderBy('logstartdate')->orderBy('logstarttime')->orderBy('logkey')
                ->get(['routekey', 'logkey', 'customercode', 'logstartdate', 'logstarttime', 'logenddate', 'logendtime']);
            $otp = app(DashboardMetrics::class)->otp($journeys, $visits)['by_visit'];
            $byJourney = $visits->groupBy('routekey');
            $rows = $journeys->map(function ($journey) use ($byJourney, $otp) {
                $window = app(OperationalTime::class)->fromLogs($byJourney->get($journey->routekey, collect()), $otp);
                return ['id' => $journey->routekey, 'routekey' => $journey->routekey, 'customercode' => null,
                    'check_in' => $window['start'], 'check_out' => $window['end'], 'actual_cft' => $window['minutes'],
                    'first_customer' => $window['first_customer'], 'last_customer' => $window['last_customer']];
            });
            $boundaryCustomers = CustomerMaster::query()
                ->whereIn('customercode', $rows->pluck('first_customer')->merge($rows->pluck('last_customer'))->filter()->unique())
                ->get(['customercode', 'alternatecode', 'customeraddress1'])->keyBy('customercode');
            $rows = $rows->map(function ($row) use ($boundaryCustomers) {
                foreach (['first_customer', 'last_customer'] as $field) {
                    $customer = $boundaryCustomers->get($row[$field]);
                    if ($customer) $row[$field] = ($customer->alternatecode ?: $customer->customercode).' - '.$customer->customeraddress1;
                }
                return $row;
            });
        } elseif (in_array($type, ['cft', 'otp_time', 'actual_face'])) {
            $visits = DB::table('customervisitlog')
                ->whereIn('customercode', CustomerMaster::query()->select('customercode'))
                ->whereIn('routekey', $keys)
                ->orderBy('logstartdate')->orderBy('logstarttime')->orderBy('logkey')
                ->get(['logkey', 'routekey', 'customercode', 'logstartdate', 'logstarttime', 'logenddate', 'logendtime', 'cft', 'autocheckout']);
            $otp = app(DashboardMetrics::class)->otp($journeys, $visits)['by_visit'];
            $rows = $visits->map(function ($visit) use ($otp, $type) {
                    $validStart = $visit->logstartdate && $visit->logstarttime && !str_starts_with($visit->logstartdate, '0000-');
                    $validEnd = $visit->logenddate && $visit->logendtime && !str_starts_with($visit->logenddate, '0000-');
                    $start = $validStart ? strtotime(substr($visit->logstartdate, 0, 10).' '.$visit->logstarttime) : false;
                    $end = $validEnd ? strtotime(substr($visit->logenddate, 0, 10).' '.$visit->logendtime) : false;
                    $actual = $start !== false && $end !== false && $end >= $start ? ($end - $start) / 60 : null;
                    $planned = CustomerFaceTime::plannedMinutes($visit->cft);
                    $excluded = $type !== 'otp_time' && !empty($otp[$visit->routekey.':'.$visit->logkey]);
                    return ['id' => $visit->logkey, 'routekey' => $visit->routekey, 'customercode' => $visit->customercode,
                        'date' => substr((string) $visit->logstartdate, 0, 10), 'time' => $visit->logstarttime, 'planned_cft' => $excluded ? 0 : $planned,
                        'auto_checkout' => (int) ($visit->autocheckout ?? 0) === 1, 'otp_excluded' => $excluded, 'recorded_actual_cft' => $actual,
                        'check_in' => $start === false ? null : date('Y-m-d H:i:s', $start),
                        'check_out' => $end === false ? null : date('Y-m-d H:i:s', $end),
                        'otp_times' => array_map(fn ($event) => substr((string) $event['otpdate'], 0, 10).' '.$event['otptime'], $otp[$visit->routekey.':'.$visit->logkey] ?? []),
                        'actual_cft' => $excluded ? 0 : $actual, 'variance' => !$excluded && $planned > 0 && $actual !== null ? $actual - $planned : null];
                });
            if ($type === 'otp_time') $rows = $rows->filter(fn ($row) => $row['actual_cft'] !== null && count($row['otp_times']) > 0);
        } elseif (in_array($type, ['sales', 'orders', 'collections', 'returns'])) {
            $rows = $this->transactionRows($keys, $type);
        } elseif ($type === 'otp') {
            $rows = collect(app(DashboardMetrics::class)->otp($journeys, collect())['details'])
                ->map(fn ($event) => [
                    'routekey' => $event['routekey'], 'id' => $event['otplogid'],
                    'customercode' => $event['customercode'], 'date' => $event['otpdate'],
                    'time' => $event['otptime'], 'otp_type' => $event['otptype'],
                    'recorded_by' => $event['username'], 'comments' => $event['comments'], 'reason' => $event['otpreason'],
                ]);
        } else {
            $visits = DB::table('customervisitlog')
                ->whereIn('customercode', CustomerMaster::query()->select('customercode'))
                ->whereIn('routekey', $keys)
                ->orderBy('logstartdate')->orderBy('logstarttime')->orderBy('logkey')
                ->get(['logkey', 'routekey', 'customercode', 'logstartdate', 'logstarttime', 'logenddate', 'logendtime', 'autocheckout']);
            $documents = [];
            $operations = collect();
            if (in_array($type, ['productive', 'efficiency'])) {
                $excludedCustomers = CustomerMaster::query()->where('toplpo', 1)
                    ->whereIn('customercode', $visits->pluck('customercode')->unique())->pluck('customercode')->flip();
                $operations = DB::table('customeroperationscontrol')->whereIn('routekey', $keys)->where('log_id', '>', 0)
                    ->where('visitkey', '>', 0)->get(['routekey', 'log_id', 'visitkey'])
                    ->groupBy(fn ($row) => $row->routekey.':'.$row->log_id);
                foreach (['invoices' => ['invoiceheader', 'totalsalesamount'], 'orders' => ['salesorderheader', 'totalinvoiceamount'], 'collections' => ['arheader', 'amountpaid']] as $label => [$table, $amount]) {
                    // Match the Dashboard card's existing productivity rules and count headers before joins.
                    $documents[$label] = DB::table($table)->whereIn('customercode', CustomerMaster::query()->select('customercode'))->whereIn('routekey', $keys)
                        ->where(fn ($q) => $q->whereNull('voidflag')->orWhere('voidflag', 0))
                        ->where('visitkey', '>', 0)
                        ->selectRaw("routekey, visitkey, COUNT(*) as documents, SUM(CASE WHEN {$amount} > 0 THEN 1 ELSE 0 END) as positive_documents")
                        ->groupBy('routekey', 'visitkey')->get()->keyBy(fn ($row) => $row->routekey.':'.$row->visitkey);
                }
            }
            $byJourney = $visits->groupBy('routekey');
            foreach ($journeys as $journey) {
                $journeyVisits = $byJourney->get($journey->routekey, collect());
                $visitOrdinals = [];
                foreach ($journeyVisits as $visit) {
                    $ordinal = ($visitOrdinals[$visit->customercode] ?? 0) + 1;
                    $visitOrdinals[$visit->customercode] = $ordinal;
                    $ignored = $excludedCustomers->has($visit->customercode);
                    $start = $visit->logstartdate && $visit->logstarttime && !str_starts_with($visit->logstartdate, '0000-') ? strtotime($visit->logstartdate.' '.$visit->logstarttime) : false;
                    $end = $visit->logenddate && $visit->logendtime && !str_starts_with($visit->logenddate, '0000-') ? strtotime($visit->logenddate.' '.$visit->logendtime) : false;
                    $completed = $start !== false && $end !== false && $end >= $start;
                    if ($type === 'productive' && !$completed && !$ignored) continue;
                    $transactionKeys = $operations->get($journey->routekey.':'.$visit->logkey, collect())
                        ->pluck('visitkey')->unique()->map(fn ($key) => $journey->routekey.':'.$key);
                    $visitDocuments = collect($documents)->map(fn ($headers) => $transactionKeys
                        ->map(fn ($key) => $headers->get($key))->filter());
                    $salesOrder = $completed && ($visitDocuments['invoices']->sum('positive_documents') > 0 || $visitDocuments['orders']->sum('positive_documents') > 0);
                    $collection = $completed && $visitDocuments['collections']->sum('positive_documents') > 0;
                    $productive = $salesOrder || $collection;
                    $rows->push([
                        'routekey' => $journey->routekey, 'id' => $visit->logkey, 'customercode' => $visit->customercode,
                        'time' => $visit->logstarttime,
                        'start_date' => $start !== false ? substr((string) $visit->logstartdate, 0, 10) : null,
                        'end_time' => $end !== false ? $visit->logendtime : null,
                        'auto_checkout' => (int) ($visit->autocheckout ?? 0) === 1,
                        'end_date' => $end !== false ? substr((string) $visit->logenddate, 0, 10) : null,
                        'ends_later_date' => $start !== false && $end !== false && date('Y-m-d', $end) > date('Y-m-d', $start),
                        'status' => $ignored ? 'Ignored' : ($completed && $productive ? 'Productive' : 'Nonproductive'),
                        'ignored' => $ignored, 'exclusion_reason' => $ignored ? 'Customer marked toplpo = 1; excluded from calculations' : null,
                        'visit_count' => 1,
                        'visit_number' => $ordinal,
                        'is_revisit' => $ordinal > 1,
                        'sales_order_productive' => !$ignored && $salesOrder,
                        'collection_productive' => !$ignored && $collection,
                        'invoices' => (int) $visitDocuments['invoices']->sum('documents'),
                        'orders' => (int) $visitDocuments['orders']->sum('documents'),
                        'collections' => (int) $visitDocuments['collections']->sum('documents'),
                    ]);
                }
            }
        }
        if ($type === 'efficiency') {
            $rows = $rows->groupBy(fn ($row) => $row['routekey'].':'.$row['customercode'])->map(function ($visits) {
                $row = $visits->first();
                $row['id'] = $row['customercode'];
                $row['visit_count'] = $visits->count();
                $row['sales_order_productive'] = $visits->contains('sales_order_productive', true);
                $row['collection_productive'] = $visits->contains('collection_productive', true);
                $row['status'] = $row['ignored'] ? 'Ignored' : ($visits->contains('status', 'Productive') ? 'Productive' : 'Nonproductive');
                return $row;
            })->values();
        }
        $customers = in_array($type, ['duration', 'outside', 'idle']) ? collect() : CustomerMaster::query()->whereIn('customercode', $rows->pluck('customercode')->unique())
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
    private function complianceRows(Collection $journeys, string $type): Collection
    {
        $keys = $journeys->pluck('routekey');
        $sequence = DB::table('routesequencecustomerstatus')
            ->whereIn('customercode', CustomerMaster::query()->select('customercode'))
            ->whereIn('routekey', $keys)
            ->select('routekey', 'customercode')
            ->selectRaw('MAX(CASE WHEN schelduledflag = 1 THEN 1 ELSE 0 END) as scheduled')
            ->selectRaw('MIN(CASE WHEN sequencenumber > 0 THEN sequencenumber END) as sequencenumber')
            ->groupBy('routekey', 'customercode')->get()->groupBy('routekey');
        $visits = DB::table('customervisitlog')
            ->whereIn('customercode', CustomerMaster::query()->select('customercode'))
            ->whereIn('routekey', $keys)
            ->orderBy('logstartdate')->orderBy('logstarttime')->orderBy('logkey')->get();
        $otp = app(DashboardMetrics::class)->otp($journeys, $visits)['by_visit'];
        $byJourney = $visits->groupBy('routekey');
        $rows = collect();
        foreach ($journeys as $journey) {
            $customers = $sequence->get($journey->routekey, collect())->keyBy('customercode');
            $scheduled = $customers->filter(fn ($customer) => (int) $customer->scheduled === 1);
            if ($scheduled->isEmpty()) continue;
            $customerVisits = $byJourney->get($journey->routekey, collect())->groupBy('customercode');
            $codes = $type === 'planned' ? $scheduled->keys()
                : $customers->keys()->merge($customerVisits->keys())->unique()->diff($scheduled->keys());
            $codes = $codes->sort(function ($a, $b) use ($customers) {
                $aSeq = $customers->get($a)?->sequencenumber ?? PHP_INT_MAX;
                $bSeq = $customers->get($b)?->sequencenumber ?? PHP_INT_MAX;
                return ($aSeq <=> $bSeq) ?: ($a <=> $b);
            });
            foreach ($codes as $code) {
                $records = $customerVisits->get($code, collect());
                $visitRows = $records->values()->map(function ($visit, $index) use ($otp) {
                    $timestamp = function ($date, $time) {
                        if (!$date || !$time || str_starts_with((string) $date, '0000-')) return null;
                        $value = strtotime(substr((string) $date, 0, 10).' '.$time);
                        return $value === false ? null : $value;
                    };
                    $start = $timestamp($visit->logstartdate, $visit->logstarttime);
                    $end = $timestamp($visit->logenddate, $visit->logendtime);
                    $actual = $start !== null && $end !== null && $end >= $start ? ($end - $start) / 60 : null;
                    $planned = CustomerFaceTime::plannedMinutes($visit->cft);
                    $hasOtp = !empty($otp[$visit->routekey.':'.$visit->logkey]);
                    $qualified = CustomerFaceTime::qualifies($actual, $planned, $hasOtp);
                    return ['id' => 'visit:'.$visit->logkey, 'visit_number' => $index + 1, 'is_revisit' => $index > 0,
                        'check_in' => $start === null ? null : date('Y-m-d H:i:s', $start),
                        'check_out' => $end === null ? null : date('Y-m-d H:i:s', $end),
                        'actual_cft' => $actual, 'planned_cft' => $planned, 'has_otp' => $hasOtp, 'qualified' => $qualified,
                        'status' => $qualified ? 'Qualified' : ($hasOtp ? 'OTP visit' : ($actual === null ? 'Incomplete visit' : ($planned <= 0 ? 'Missing CFT target' : 'CFT not met')))];
                });
                $customerQualified = $visitRows->contains('qualified', true);
                if ($visitRows->isEmpty()) $visitRows->push(['id' => 'not-visited:'.$code, 'visit_number' => null,
                    'is_revisit' => false, 'check_in' => null, 'check_out' => null, 'actual_cft' => null,
                    'planned_cft' => null, 'has_otp' => false, 'qualified' => false, 'status' => 'Not visited']);
                foreach ($visitRows as $row) $rows->push($row + ['routekey' => $journey->routekey,
                    'customercode' => $code, 'sequence' => $customers->get($code)?->sequencenumber,
                    'customer_qualified' => $customerQualified, 'visit_count' => $records->count(),
                    'otp_visit_count' => $visitRows->where('has_otp', true)->count()]);
            }
        }
        return $rows;
    }

    public function transactionRows(Collection $keys, string $type): Collection
    {
        $rows = collect();
        $sources = ['sales' => ['invoiceheader', 'totalsalesamount'], 'orders' => ['salesorderheader', 'totalinvoiceamount'], 'collections' => ['arheader', 'amountpaid']];
        foreach ($type === 'returns' ? ['sales', 'orders'] : [$type] as $source) {
            [$table, $amount] = $sources[$source];
            $expression = $type === 'returns' ? 'COALESCE(totalreturnamount, 0) + COALESCE(totaldamagedamount, 0)' : "COALESCE({$amount}, 0)";
            $query = DB::table($table)->whereIn('customercode', CustomerMaster::query()->select('customercode'))->whereIn('routekey', $keys);
            if ($type === 'returns') $query->where('voidflag', 0)->whereRaw($expression.' > 0');
            else $query->where(fn ($q) => $q->whereNull('voidflag')->orWhere('voidflag', 0));
            $records = $query->orderBy('transactiondate')->orderBy('transactiontime')->get([
                'routekey', 'customercode', 'transactionkey', 'documentnumber', 'transactiondate', 'transactiontime', 'currencycode', DB::raw($expression.' as amount'),
            ]);
            foreach ($records as $record) $rows->push([
                'id' => $source.':'.$record->transactionkey, 'routekey' => $record->routekey, 'customercode' => $record->customercode,
                'date' => $record->transactiondate, 'time' => $record->transactiontime, 'document' => $record->documentnumber,
                'source' => ['sales' => 'Invoice', 'orders' => 'Order', 'collections' => 'Receipt'][$source],
                'currencycode' => (int) ($record->currencycode ?? 0), 'amount' => (float) $record->amount * ($type === 'returns' ? -1 : 1),
            ]);
        }
        $currencies = DB::table('currencymaster')->whereIn('currencycode', $rows->pluck('currencycode')->unique())->pluck('currencysymbol', 'currencycode');
        $rows = $rows->map(fn ($row) => $row + ['currency' => $currencies->get($row['currencycode']) ?: ($row['currencycode'] ? 'Currency '.$row['currencycode'] : 'Unspecified currency')]);
        return $rows;
    }

}
