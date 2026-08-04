<?php

namespace App\Services\LegacyApi;

use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

class IndexApiService
{
    public function __construct(private readonly LegacyProcedureService $support)
    {
    }

    public function salesmanLogin(string $username, string $password, string $deviceId): array
    {
        $salesmanTable = $this->support->resolveTable('salesman');
        $routeTable = $this->support->resolveTable('routemaster');

        $salesman = DB::selectOne(
            "SELECT
                0 as STATUS,
                rm.routecode,
                rm.cdat,
                sm.*,
                0 as useencription,
                COALESCE(NULLIF(rm.device_assigned_id, ''), '-') as assigned_device
            FROM `{$salesmanTable}` as sm
            LEFT JOIN `{$routeTable}` as rm
                ON rm.salesmancode = sm.salesmancode
            WHERE sm.username = ?
              AND sm.userpassword = ?
            LIMIT 1",
            [$username, trim($password)]
        );

        if (! $salesman) {
            return [['STATUS' => 1]];
        }

        if (($salesman->assigned_device ?? '-') === '-') {
            return [['STATUS' => 3]];
        }

        if ((string) $salesman->assigned_device !== $deviceId) {
            return [['STATUS' => 2]];
        }

        $row = (array) $salesman;
        unset($row['assigned_device']);

        return $this->support->normalizeNulls([$row]);
    }

    public function companyIdByDevice(string $deviceId): array
    {
        $company = $this->support->table('tbl_device')->where('device_id', $deviceId)->value('company_id');

        if (! $company) {
            return [];
        }

        $version = $this->support->hasTable('tbl_version')
            ? (array) ($this->support->table('tbl_version')->select('url', 'verno')->first() ?? [])
            : [];

        return $this->support->normalizeNulls([[
            'status' => '1',
            'url' => $version['url'] ?? '',
            'ver' => $version['verno'] ?? '',
        ]]);
    }

    public function getSyncData(string $userId, string $deviceId, string $routeId, string $mdate, int $table): array
    {
        $payload = $this->loadSyncPayload($userId, $deviceId, $routeId, false);

        $groups = [
            1 => ['ControlPanel', 'Setup', 'companydetail', 'SalesmanMaster', 'RouteMaster', 'startendday', 'synctime', 'CurrencyMaster', 'itemmustheader', 'itemmustdetail'],
            2 => ['itemgroup', 'ItemMaster', 'itempackagemaster', 'routegoal', 'avgsalesqty', 'outletitemcodes', 'taxmaster'],
            3 => ['startingloaddetail', 'inventorysummarydetail'],
            4 => ['CustomerMaster', 'salescalender', 'routesequence', 'customerinvoice'],
            5 => ['discountkeyheader', 'discountkeydetail', 'distributionkeydetails', 'productgroupheader', 'productgroupdetail', 'promokeyheader', 'promokeydetail', 'promoplanheader', 'promoplandetail', 'promotionassignmentadvanced', 'customerpricing1', 'pricingdetail1'],
            6 => ['POSmaster', 'customerposinventory', 'customerposlimit', 'posinstructions', 'customersurveyplan', 'customersurveykeyplan', 'customersurveykey', 'customersurveydefinition', 'customersurveydefassign', 'lookupindexdetail'],
            7 => ['nonservreasons', 'expreasons', 'expiryreturnreasons', 'retitmreasons', 'freegoodreasons', 'voidreasons', 'routebook', 'salestrend'],
            8 => ['customermessages', 'salesmanmessages', 'vanmaster', 'bankmaster', 'cashdesc', 'inventorylocation'],
            9 => ['salesorderheader', 'salesorderdetail', 'suggestedsalesinvoice', 'inventorytransactiondetail', 'customer_foc_balance', 'customer_foc_detail', 'journeyplancreditlimit', 'batchexpirydetail', 'customer_foc', 'warehousestock', 'deletemaster'],
            10 => ['itemmustheader', 'itemmustdetail'],
        ];

        if (! isset($groups[$table])) {
            return [];
        }

        return $this->support->normalizeNulls(array_intersect_key($payload, array_flip($groups[$table])));
    }

    public function getSyncFullData(string $userId, string $deviceId, string $routeId, string $mdate): array
    {
        $payload = $this->loadSyncPayload($userId, $deviceId, $routeId, true);
        $payload['synccount'] = collect($payload)
            ->filter(fn ($value, $key) => is_array($value) && $key !== 'synccount')
            ->map(fn ($rows, $key) => ['tablename' => $key, 'tablecount' => count($rows)])
            ->values()
            ->all();

        return $this->support->normalizeNulls($payload);
    }

    public function updateSyncDate(
        string $userId,
        string $deviceId,
        string $routeCode,
        string $routeKey,
        string $routeClosed
    ): array {
        $resolvedRouteCode = DB::table('routemaster')->where('salesmancode', $userId)->value('routecode') ?: $routeCode;

        if ($this->support->hasTable('tbl_syncservice')) {
            $this->support->table('tbl_syncservice')->insert([
                'userid' => $userId,
                'deviceid' => $deviceId,
                'syncdate' => now(),
                'synctime' => now()->format('H:i:s'),
                'routecode' => $resolvedRouteCode,
                'synctype' => '1',
                'routeclosed' => $routeClosed,
                'routekey' => $routeKey !== '' ? $routeKey : null,
            ]);
        }

        if ($this->support->hasTable('tbl_synclog')) {
            $this->support->table('tbl_synclog')->insert([
                'userid' => $userId,
                'routecode' => $routeCode,
                'routekey' => $routeKey,
                'routeclosed' => $routeClosed,
                'synctype' => '1',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        return ['status' => 'success'];
    }

    private function loadSyncPayload(string $userId, string $deviceId, string $routeId, bool $includeExtra): array
    {
        $route = (array) (DB::table('routemaster')->where('routecode', $routeId)->first() ?? []);
        $resolvedUserId = $this->resolvedSalesmanCode($userId, $route);
        $routeItemGroup = $route['routeitemgrpcode'] ?? null;
        $itemMustKey = $route['itemmustkey'] ?? null;
        $syncCustomerCodes = $this->syncCustomerCodes($routeId);
        $customerRows = $this->customerMasterRows($route, $syncCustomerCodes);
        $customerCodes = collect($customerRows)->pluck('customercode')->filter()->values();
        $customerInvoiceCodes = collect($this->customerInvoiceCustomerCodes($syncCustomerCodes))->values();
        $latestRouteKey = DB::table('startendday')
            ->where('routecode', $routeId)
            ->where('routeclosed', 1)
            ->max('routekey');
        $syncDate = $this->support->table('tbl_syncservice')
            ->where('userid', $userId)
            ->where('deviceid', $deviceId)
            ->latest('syncdate')
            ->value('syncdate');

        $payload = [
            'ControlPanel' => $this->rows('controlpanel'),
            'Setup' => $this->rows('setup'),
            'companydetail' => $this->companyDetailRows($route),
            'SalesmanMaster' => $this->rows('salesman', fn ($q) => $q->where('salesmancode', $resolvedUserId)),
            'RouteMaster' => $this->routeMasterRows($routeId, $route),
            'startendday' => $this->rows('startendday', fn ($q) => $q->where('routecode', $routeId)->orderByDesc('routekey')->limit(5)),
            'synctime' => $this->rows('tbl_syncservice', fn ($q) => $q->where('userid', $userId)->where('deviceid', $deviceId)->orderByDesc('syncdate')->limit(1)),
            'CurrencyMaster' => $this->rows('currencymaster'),
            'itemmustheader' => $this->rows('itemmustheader', fn ($q) => $q->where('itemmustcode', $itemMustKey)),
            'itemmustdetail' => $this->rows('itemmustdetail', fn ($q) => $q->where('itemmustcode', $itemMustKey)),
            'itemgroup' => $this->rows('itemgroup', fn ($q) => $q->whereIn('itemgroupcode', function ($sub) use ($routeItemGroup) {
                $sub->from('routeitemmapping as map')
                    ->join('itemmaster as im', 'im.actualitemcode', '=', 'map.itemcode')
                    ->select('im.itemgroupcode')
                    ->where('map.routeitemgrpcode', $routeItemGroup);
            })),
            'ItemMaster' => $this->rows('itemmaster', fn ($q) => $q->whereIn('actualitemcode', function ($sub) use ($routeItemGroup) {
                $sub->from('routeitemmapping')->select('itemcode')->where('routeitemgrpcode', $routeItemGroup);
            })),
            'itempackagemaster' => $this->rows('itempackagemaster'),
            'routegoal' => $this->rows('routegoal', fn ($q) => $q->where('routecode', $routeId)),
            'avgsalesqty' => $this->rows('avgsalesqty', fn ($q) => $q->where('routecode', $routeId)),
            'outletitemcodes' => $this->rows('outletitemcodes'),
            'taxmaster' => $this->taxMasterRows(),
            'startingloaddetail' => $this->rows('startingloaddetail', fn ($q) => $q->where('routecode', $routeId)->whereDate('ddate', now()->toDateString())->where('status', 0)),
            'inventorysummarydetail' => $this->rows('inventorysummarydetail', fn ($q) => $q->where('routekey', $latestRouteKey ?: 0)),
            'CustomerMaster' => $customerRows,
            'salescalender' => $this->salesCalendarRows(),
            'routesequence' => $this->routeSequenceRows($routeId, $syncCustomerCodes),
            'customerinvoice' => $this->customerInvoiceRows($customerInvoiceCodes->all()),
            'discountkeyheader' => $this->rows('discountkeyheader'),
            'discountkeydetail' => $this->rows('discountkeydetail'),
            'distributionkeydetails' => $this->rows('distributionkeydetails'),
            'productgroupheader' => $this->rows('productgroupheader'),
            'productgroupdetail' => $this->rows('productgroupdetail'),
            'promokeyheader' => $this->rows('promokeyheader'),
            'promokeydetail' => $this->rows('promokeydetail'),
            'promoplanheader' => $this->rows('promoplanheader'),
            'promoplandetail' => $this->rows('promoplandetail'),
            'promotionassignmentadvanced' => $this->rows('promotionassignmentadvanced'),
            'customerpricing1' => $this->rows('customerpricing1'),
            'pricingdetail1' => $this->rows('pricingdetail1'),
            'POSmaster' => $this->rows('posmaster'),
            'customerposinventory' => $this->rows('customerposinventory', fn ($q) => $q->whereIn('customercode', $customerCodes)),
            'customerposlimit' => $this->rows('customerposlimit', fn ($q) => $q->whereIn('customercode', $customerCodes)),
            'posinstructions' => $this->rows('posinstructions'),
            'customersurveyplan' => $this->rows('customersurveyplan'),
            'customersurveykeyplan' => $this->rows('customersurveykeyplan'),
            'customersurveykey' => $this->rows('customersurveykey'),
            'customersurveydefinition' => $this->rows('customersurveydefinition'),
            'customersurveydefassign' => $this->rows('customersurveydefassign'),
            'lookupindexdetail' => $this->rows('lookupindexdetail'),
            'nonservreasons' => $this->rows('nonservreasons'),
            'expreasons' => $this->rows('expreasons'),
            'expiryreturnreasons' => $this->rows('expiryreturnreasons'),
            'retitmreasons' => $this->rows('retitmreasons'),
            'freegoodreasons' => $this->rows('freegoodreasons'),
            'voidreasons' => $this->rows('voidreasons'),
            'routebook' => $this->rows('routebook', fn ($q) => $q->where('routecode', $routeId)),
            'salestrend' => $this->rows('salestrend'),
            'customermessages' => $this->rows('customermessages'),
            'salesmanmessages' => $this->rows('salesmanmessages'),
            'vanmaster' => $this->rows('vanmaster'),
            'bankmaster' => $this->rows('bankmaster'),
            'cashdesc' => $this->rows('cashdesc'),
            'inventorylocation' => $this->rows('inventorylocation'),
            'salesorderheader' => $this->rows('salesorderheader', fn ($q) => $q->where('routecode', $routeId)),
            'salesorderdetail' => $this->rows('salesorderdetail', fn ($q) => $q->where('routekey', $latestRouteKey ?: 0)),
            'suggestedsalesinvoice' => $this->rows('suggestedsalesinvoice', fn ($q) => $q->where('routecode', $routeId)),
            'inventorytransactiondetail' => $this->rows('inventorytransactiondetail', fn ($q) => $q->where('routekey', $latestRouteKey ?: 0)),
            'customer_foc_balance' => $this->rows('customer_foc_balance', fn ($q) => $q->whereIn('customercode', $customerCodes)),
            'customer_foc_detail' => $this->rows('customer_foc_detail', fn ($q) => $q->whereIn('customercode', $customerCodes)),
            'journeyplancreditlimit' => $this->rows('journeyplancreditlimit', fn ($q) => $q->where('routecode', $routeId)),
            'batchexpirydetail' => $this->rows('batchexpirydetail', fn ($q) => $q->where('routekey', $latestRouteKey ?: 0)),
            'customer_foc' => $this->rows('customer_foc', fn ($q) => $q->whereIn('customercode', $customerCodes)),
            'warehousestock' => $this->rows('warehousestock'),
            'deletemaster' => $this->rows('logmaster', fn ($q) => $q->when($syncDate, fn ($query) => $query->where('cdat', '>', $syncDate))->where('operation_type', 'delete')),
        ];

        if ($includeExtra) {
            $payload['customeritemgrp'] = $this->rows('customeritemgrp');
            $payload['customeritemmap'] = $this->rows('customeritemmap');
            $payload['tempcustinventory'] = $this->rows('tempcustinventory');
            $payload['itemnrp'] = $this->rows('itemnrp');
            $payload['custnrp'] = $this->rows('custnrp');
        }

        return $payload;
    }

    private function resolvedSalesmanCode(string $userId, array $route): string
    {
        $trimmed = trim($userId);

        if ($trimmed !== '' && $trimmed !== '0' && $trimmed !== '-1') {
            return $trimmed;
        }

        return (string) ($route['salesmancode'] ?? $trimmed);
    }

    private function companyDetailRows(array $route): array
    {
        $rows = [];

        if ($this->support->hasTable('companydetail')) {
            $rows = $this->rows('companydetail');
        } elseif ($this->support->hasTable('company')) {
            $companyCode = $route['cmpycode'] ?? null;

            $rows = $this->rows(
                'company',
                $companyCode !== null
                    ? fn ($q) => $q->where('cmpycode', $companyCode)
                    : null
            );
        }

        return array_map(function (array $row): array {
            if (! array_key_exists('country', $row) && array_key_exists('trac_country', $row)) {
                $row['country'] = $row['trac_country'];
                unset($row['trac_country']);
            }

            return $row;
        }, $rows);
    }

    private function taxMasterRows(): array
    {
        if ($this->support->hasTable('tbltaxmaster')) {
            $columns = [
                'taxcode',
                'taxdescription',
                'arbtaxdescription',
                'taxtype',
                'taxpercentage',
                'taxbase',
            ];

            if (Schema::hasColumn('tbltaxmaster', 'enabletaxtype')) {
                $columns[] = 'enabletaxtype';
            }

            $rows = $this->rows('tbltaxmaster', fn ($q) => $q->select($columns));

            return $this->appendEnableTaxType($rows, 'tbltaxmaster');
        }

        if ($this->support->hasTable('taxmaster')) {
            $columns = [
                'taxcode',
                'taxdescription',
                'arbtaxdescription',
                'taxtype',
                'taxpercentage',
                'taxbase',
            ];

            if (Schema::hasColumn('taxmaster', 'enabletaxtype')) {
                $columns[] = 'enabletaxtype';
            }

            $rows = $this->rows('taxmaster', fn ($q) => $q->select($columns));

            return $this->appendEnableTaxType($rows, 'taxmaster');
        }

        return [];
    }

    private function routeMasterRows(string $routeId, array $route): array
    {
        $routeCodes = $this->syncRouteCodes($routeId, $route);
        $rows = $this->rows('routemaster', fn ($q) => $q->whereIn('routecode', $routeCodes));

        return array_map(function (array $row): array {
            if (! array_key_exists('routecreditlimit', $row) && array_key_exists('creditlimit', $row)) {
                $row['routecreditlimit'] = $row['creditlimit'];
            }

            return $row;
        }, $rows);
    }

    private function syncRouteCodes(string $routeId, array $route): array
    {
        $routeCode = trim($routeId);

        if ($routeCode === '' || ! $this->support->hasTable('routemaster')) {
            return [$routeId];
        }

        if (
            ! $this->support->hasTable('setup')
            || ! $this->support->hasTable('subareamaster')
            || ! $this->support->hasTable('areamaster')
        ) {
            return [$routeCode];
        }

        $subAreaCode = $route['subareacode'] ?? null;
        if ($subAreaCode === null || $subAreaCode === '') {
            return [$routeCode];
        }

        $depotCode = DB::table('areamaster as am')
            ->join('subareamaster as sam', 'sam.areacode', '=', 'am.areacode')
            ->where('sam.subareacode', $subAreaCode)
            ->value('am.depotcode');

        if ($depotCode === null || $depotCode === '') {
            return [$routeCode];
        }

        $transferFlag = (int) ($this->support->table('setup')->value('transferinventoryflag') ?? 0);

        $query = DB::table('areamaster as am')
            ->join('subareamaster as sam', 'sam.areacode', '=', 'am.areacode')
            ->join('routemaster as rm', 'rm.subareacode', '=', 'sam.subareacode')
            ->where('am.depotcode', $depotCode);

        if (Schema::hasColumn('routemaster', 'activestatus')) {
            $query->where('rm.activestatus', 1);
        }

        if ($transferFlag === 0) {
            $query->where('rm.routecode', $routeCode);
        } elseif (! Schema::hasColumn('routemaster', 'depotrouteflag')) {
            $query->where('rm.routecode', $routeCode);
        } elseif ($transferFlag === 1) {
            $query->where('rm.depotrouteflag', 0);
        } elseif ($transferFlag === 2) {
            $query->where('rm.depotrouteflag', 1);
        } else {
            $query->where('rm.depotrouteflag', '<', 2);
        }

        $routeCodes = $query
            ->distinct()
            ->pluck('rm.routecode')
            ->filter(fn ($code) => $code !== null && $code !== '')
            ->map(fn ($code) => (string) $code)
            ->values()
            ->all();

        if ($transferFlag === 2 && ! in_array($routeCode, $routeCodes, true)) {
            $routeCodes[] = $routeCode;
        }

        return $routeCodes !== [] ? $routeCodes : [$routeCode];
    }

    private function customerMasterRows(array $route, array $customerCodes): array
    {
        $routeItemMustKey = $route['itemmustkey'] ?? null;

        if ($customerCodes === []) {
            return [];
        }

        $rows = $this->rows('customermaster', function ($q) use ($customerCodes) {
            $q->whereIn('customercode', $customerCodes)->where('type', '<', 3);

            if (Schema::hasColumn('customermaster', 'activecustomer')) {
                $q->where('activecustomer', 1);
            }
        });

        return array_map(function (array $row) use ($routeItemMustKey): array {
            if (($row['itemmustkey'] ?? 0) <= 0 && $routeItemMustKey !== null) {
                $row['itemmustkey'] = $routeItemMustKey;
            }

            if (! array_key_exists('splitfree', $row)) {
                $row['splitfree'] = 0;
            }

            if (($row['invoicepaymentterms'] ?? null) != 2) {
                if (array_key_exists('creditlimitdays', $row)) {
                    $row['creditlimitdays'] = 0;
                }

                if (array_key_exists('creditlimit', $row)) {
                    $row['creditlimit'] = 0;
                }

                if (array_key_exists('enablearcollection', $row)) {
                    $row['enablearcollection'] = 0;
                }
            }

            return $row;
        }, $rows);
    }

    private function routeSequenceRows(string $routeId, array $customerCodes): array
    {
        if ($customerCodes === []) {
            return [];
        }

        $week = $this->syncWeekNumber();

        return $this->rows('routesequence', function ($q) use ($routeId, $customerCodes, $week) {
            $q->where('routecode', $routeId)->whereIn('customercode', $customerCodes);

            if ($week !== null && Schema::hasColumn('routesequence', 'rp32weeknumber')) {
                $q->where('rp32weeknumber', $week);
            }
        });
    }

    private function salesCalendarRows(): array
    {
        return $this->rows('salescalender', function ($q) {
            if (Schema::hasColumn('salescalender', 'salesyear')) {
                $q->where('salesyear', now()->year);
            }
        });
    }

    private function customerInvoiceRows(array $customerCodes): array
    {
        if ($customerCodes === []) {
            return [];
        }

        return $this->rows('customerinvoice', function ($q) use ($customerCodes) {
            $q->whereIn('customercode', $customerCodes);

            if (Schema::hasColumn('customerinvoice', 'transactiontype')) {
                $q->whereIn('transactiontype', [1, 2]);
            }

            if (Schema::hasColumn('customerinvoice', 'voidflag')) {
                $q->where('voidflag', 0);
            }
        });
    }

    private function customerInvoiceCustomerCodes(array $customerCodes): array
    {
        if ($customerCodes === []) {
            return [];
        }

        return $this->support->table('customermaster')
            ->whereIn('customercode', $customerCodes)
            ->where(function ($query) {
                $query->where('invoicepaymentterms', '<=', 1)
                    ->orWhere('enablearcollection', '!=', 0)
                    ->orWhereNull('enablearcollection');
            })
            ->pluck('customercode')
            ->filter()
            ->values()
            ->all();
    }

    private function syncCustomerCodes(string $routeId): array
    {
        if (! $this->support->hasTable('routesequence')) {
            return DB::table('customermaster')->where('routecode', $routeId)->pluck('customercode')->all();
        }

        $week = $this->syncWeekNumber();
        $callDayColumn = $this->syncCallDayColumn();
        $journeyPlanFlag = (int) ($this->support->hasTable('setup')
            ? ($this->support->table('setup')->value('journeyplanflag') ?? 0)
            : 0);

        $query = DB::table('routesequence')->where('routecode', $routeId);

        if ($week !== null && Schema::hasColumn('routesequence', 'rp32weeknumber')) {
            $query->where('rp32weeknumber', $week);
        }

        if ($journeyPlanFlag !== 2 && $callDayColumn !== null && Schema::hasColumn('routesequence', $callDayColumn)) {
            $query->where($callDayColumn, 1);
        }

        return $query->distinct()->pluck('customercode')->filter()->values()->all();
    }

    private function syncWeekNumber(): ?int
    {
        if (! $this->support->hasTable('setup')) {
            return null;
        }

        $routeSequencePlanFlag = (int) ($this->support->table('setup')->value('routesequenceplanflag') ?? 0);

        if ($routeSequencePlanFlag === 1) {
            return 9;
        }

        if (! $this->support->hasTable('salescalender')) {
            return 9;
        }

        return (int) (DB::table('salescalender')
            ->whereDate('weekstartdate', '<=', now()->toDateString())
            ->whereDate('weekenddate', '>=', now()->toDateString())
            ->value('rp32weeknumber') ?? 9);
    }

    private function syncCallDayColumn(): ?string
    {
        return match (now()->dayOfWeek) {
            0 => 'callrestrictiondays7',
            1 => 'callrestrictiondays1',
            2 => 'callrestrictiondays2',
            3 => 'callrestrictiondays3',
            4 => 'callrestrictiondays4',
            5 => 'callrestrictiondays5',
            6 => 'callrestrictiondays6',
            default => null,
        };
    }

    private function appendEnableTaxType(array $rows, string $table): array
    {
        return array_map(function (array $row): array {
            $row['enabletaxtype'] = $row['enabletaxtype'] ?? 0;

            return $row;
        }, $rows);
    }

    private function rows(string $table, ?callable $callback = null): array
    {
        if (! $this->support->hasTable($table)) {
            return [];
        }

        $query = $this->support->table($table);

        if ($callback) {
            $callback($query);
        }

        return $query->get()->map(fn ($row) => (array) $row)->all();
    }
}
