<?php

namespace App\Http\Controllers\Api;

use App\Services\LegacyApi\LegacyProcedureService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class WsController extends LegacyApiController
{
    public function __construct(private readonly LegacyProcedureService $support)
    {
    }

    public function sendData(Request $request, ?string $tail = null): JsonResponse
    {
        $params = $this->legacyParams($request, $tail);
        $result = [];

        foreach ($this->decodeItems($params['startday'] ?? null) as $item) {
            $latest = DB::table('startendday')
                ->where('routecode', $item['routecode'] ?? '')
                ->orderByDesc('routekey')
                ->first();

            if (($latest->routeclosed ?? 2) != 0) {
                $routeMeta = DB::table('routemaster')
                    ->join('subareamaster', 'subareamaster.subareacode', '=', 'routemaster.subareacode')
                    ->join('areamaster', 'areamaster.areacode', '=', 'subareamaster.areacode')
                    ->join('depotmaster', 'depotmaster.depotcode', '=', 'areamaster.depotcode')
                    ->join('company', 'company.cmpycode', '=', 'depotmaster.cmpycode')
                    ->select([
                        'routemaster.subareacode',
                        'subareamaster.supervisorcode',
                        'subareamaster.areacode',
                        'areamaster.areamanagercode',
                        'areamaster.depotcode',
                        'depotmaster.branchmanagercode',
                        'depotmaster.cmpycode',
                        'company.nationalsalesmanagercode',
                        'routemaster.amountdecimaldigits',
                        'routemaster.memo1 as tourid',
                    ])
                    ->where('routemaster.routecode', $item['routecode'] ?? '')
                    ->first();

                $routeKey = DB::table('startendday')->insertGetId([
                    'routecode' => $item['routecode'] ?? '',
                    'salesmancode' => $item['salesmancode'] ?? '',
                    'routestartdate' => now()->toDateString(),
                    'routestarttime' => now()->format('H:i:s'),
                    'routestartodometer' => $item['routestartodometer'] ?? '',
                    'triptype' => 0,
                    'areacode' => $routeMeta->areacode ?? null,
                    'areamanagercode' => $routeMeta->areamanagercode ?? null,
                    'branchmanagercode' => $routeMeta->branchmanagercode ?? null,
                    'cmpycode' => $routeMeta->cmpycode ?? null,
                    'currencycode' => $routeMeta->amountdecimaldigits ?? null,
                    'depotcode' => $routeMeta->depotcode ?? null,
                    'nationalsalesmanagercode' => $routeMeta->nationalsalesmanagercode ?? null,
                    'subareacode' => $routeMeta->subareacode ?? null,
                    'supervisorcode' => $routeMeta->supervisorcode ?? null,
                    'dataconrefnumber' => $item['deviceid'] ?? '',
                    'versionno' => $item['ver'] ?? '',
                    'tourid' => $routeMeta->tourid ?? null,
                ]);

                $row = DB::table('startendday')->where('routekey', $routeKey)->first();
                $result[] = [
                    'status' => 0,
                    'routekey' => $row->routekey ?? '',
                    'routestartdate' => $row->routestartdate ?? '',
                    'routestarttime' => $row->routestarttime ?? '',
                    'routestartodometer' => $row->routestartodometer ?? '',
                ];
                continue;
            }

            $result[] = ['status' => 1];
        }

        return $this->legacyJson($this->support->normalizeNulls(['startday' => $result]));
    }

    public function endDay(Request $request, ?string $tail = null): JsonResponse
    {
        $params = $this->legacyParams($request, $tail);
        $result = [];

        foreach ($this->decodeItems($params['endday'] ?? null) as $item) {
            DB::table('startendday')
                ->where('routekey', $item['routekey'] ?? '')
                ->update([
                    'routeenddate' => $item['routeenddate'] ?? null,
                    'routeendodometer' => $item['routeendodometer'] ?? null,
                    'routeendtime' => now()->format('H:i:s'),
                    'routeclosed' => 1,
                    'totaldocuments' => $this->zeroDefault($item['totaldocuments'] ?? 0),
                    'totalcash' => $this->zeroDefault($item['totalcash'] ?? 0),
                    'totalchecks' => $this->zeroDefault($item['totalchecks'] ?? 0),
                    'totalcheckrequests' => 0,
                    'totalorderamount' => $this->zeroDefault($item['totalorderamount'] ?? 0),
                    'totalinvoiceamount' => $this->zeroDefault($item['totalinvoiceamount'] ?? 0),
                    'totalchargesales' => $this->zeroDefault($item['totalchargesales'] ?? 0),
                    'totalcashsales' => $this->zeroDefault($item['totalcashsales'] ?? 0),
                    'totalacctsreceivable' => $this->zeroDefault($item['totalacctsreceivable'] ?? 0),
                    'totalexpenses' => $this->zeroDefault($item['totalexpenses'] ?? 0),
                    'inventoryvariance' => $this->zeroDefault($item['inventoryvariance'] ?? 0),
                    'cashvariance' => $this->zeroDefault($item['cashvariance'] ?? 0),
                ]);

            $row = DB::table('startendday')->where('routekey', $item['routekey'] ?? '')->first();
            if ($row) {
                $result[] = [
                    'routekey' => $row->routekey ?? '',
                    'routeenddate' => $row->routeenddate ?? '',
                    'routeendtime' => $row->routeendtime ?? '',
                ];
            }
        }

        return $this->legacyJson($this->support->normalizeNulls(['endday' => $result]));
    }

    public function logout(Request $request, ?string $tail = null): Response
    {
        $params = $this->legacyParams($request, $tail);

        foreach ($this->decodeItems($params['logout'] ?? null) as $item) {
            DB::table('startendday')
                ->where('routekey', $item['routekey'] ?? 0)
                ->orWhere(function ($query) use ($item) {
                    $query->where('routecode', $item['routecode'] ?? '')
                        ->where('triptype', 0)
                        ->where('routeclosed', 0);
                })
                ->delete();
        }

        return $this->legacyText('');
    }

    public function checkLoad(Request $request, ?string $tail = null): Response
    {
        $params = $this->legacyParams($request, $tail);
        $status72 = DB::table('controlpanel')->where('flagid', 72)->value('status');

        if ((int) $status72 === 0) {
            return $this->legacyText('1');
        }

        $count = DB::table('startingloaddetail')
            ->where('status', 0)
            ->where('routecode', $params['routeid'] ?? '')
            ->whereDate('ddate', now()->toDateString())
            ->where('salesmancode', $params['userid'] ?? '')
            ->count();

        return $this->legacyText($count > 0 ? '1' : '0');
    }

    public function routeTrackL12(Request $request, ?string $tail = null): JsonResponse
    {
        $params = $this->legacyParams($request, $tail);
        $last = [];
        $nullable = fn ($value) => $value === '' || $value === null ? null : $value;

        foreach ($this->decodeItems($params['gpstrack'] ?? null) as $item) {
            $timestamp = now();

            DB::connection('pgsql_transfer')->table('trac_routetrack')->insert([
                'routekey' => $nullable($item['routekey'] ?? $params['routekey'] ?? null),
                'routecode' => $nullable($item['routecode'] ?? $params['routeid'] ?? null),
                'salesmancode' => $nullable($item['salesmancode'] ?? $params['userid'] ?? null),
                'entrydate' => $nullable($item['entrydate'] ?? null) ?? $timestamp->toDateString(),
                'entrytime' => $nullable($item['entrytime'] ?? null) ?? $timestamp->format('H:i:s'),
                'latitude' => $nullable($item['lat'] ?? null),
                'longitude' => $nullable($item['log'] ?? null),
                'deviceid' => $nullable($item['deviceid'] ?? null),
                'devicetimestamp' => $nullable($item['devicetimestamp'] ?? null) ?? $timestamp->toDateTimeString(),
            ]);

            $last = [[
                'latitude' => $item['lat'] ?? '',
                'longitude' => $item['log'] ?? '',
                'deviceid' => $item['deviceid'] ?? '',
            ]];
        }

        return $this->legacyJson($this->support->normalizeNulls($last));
    }

    public function getDelivery(Request $request, ?string $tail = null): JsonResponse
    {
        $params = $this->legacyParams($request, $tail);
        $item = $this->decodeItems($params['delivery'] ?? null)[0] ?? [];

        if ($item === []) {
            return $this->legacyJson(['getdelivery' => []]);
        }

        $orderQuery = DB::table('deliveryheader');

        if (($item['customercode'] ?? '') !== '') {
            $orderQuery->join('customermaster', 'customermaster.customercode', '=', 'deliveryheader.customercode')
                ->where('customermaster.alternatecode', 'like', '%' . strtoupper((string) $item['customercode']) . '%')
                ->select('deliveryheader.orderno');
        } elseif (($item['orderno'] ?? '') !== '') {
            $orderQuery->where('orderno', 'like', '%' . $item['orderno'] . '%')->select('orderno');
        } elseif (($item['lpono'] ?? '') !== '') {
            $orderQuery->where('loadsheetnumber', 'like', '%' . strtoupper((string) $item['lpono']) . '%')->select('orderno');
        }

        $orders = $orderQuery->limit(10)->pluck('orderno');

        return $this->legacyJson($this->support->normalizeNulls([
            'deliveryheader' => DB::table('deliveryheader as dh')
                ->join('customermaster as cm', 'cm.customercode', '=', 'dh.customercode')
                ->whereIn('dh.orderno', $orders)
                ->get([
                    'dh.deliveryno', 'dh.orderno', 'dh.customercode', 'dh.deliveryroute', 'dh.deliverydate',
                    'dh.drivercode', 'dh.loadsheetnumber', DB::raw('cm.alternatecode as reference'), 'dh.totalamount',
                ])->map(fn ($row) => (array) $row)->all(),
            'deliverydetail' => DB::table('deliverydetail as dd')
                ->join('deliveryheader as dh', 'dh.deliveryno', '=', 'dd.deliveryno')
                ->whereIn('dh.orderno', $orders)
                ->select('dd.*')
                ->get()->map(fn ($row) => (array) $row)->all(),
        ]));
    }

    public function getWhStock(Request $request, ?string $tail = null): JsonResponse
    {
        $params = $this->legacyParams($request, $tail);
        $item = $this->decodeItems($params['whstock'] ?? null)[0] ?? [];

        if ($item === []) {
            return $this->legacyJson(['whstock' => []]);
        }

        $rows = DB::table('itemmaster as item')
            ->leftJoin('routeitemmapping as rim', 'rim.itemcode', '=', 'item.actualitemcode')
            ->leftJoin('routemaster as rm', 'rm.routeitemgrpcode', '=', 'rim.routeitemgrpcode')
            ->where('item.activeitem', 1)
            ->where('rm.routecode', $item['routecode'] ?? '')
            ->get([
                'item.actualitemcode',
                'item.defaultsalesprice',
                'item.defaultreturnprice',
                'item.caseprice',
                'item.returncaseprice',
                'item.warehousestock',
            ])->map(fn ($row) => (array) $row)->all();

        return $this->legacyJson($this->support->normalizeNulls(['whstock' => $rows]));
    }

    public function getCustomerBalance(Request $request, ?string $tail = null): JsonResponse
    {
        $params = $this->legacyParams($request, $tail);
        $item = $this->decodeItems($params['customerbalance'] ?? null)[0] ?? [];

        if ($item === []) {
            return $this->legacyJson(['customerbalance' => []]);
        }

        $allowOtherRoutes = (string) DB::table('controlpanel')->where('flagid', 51)->value('status') === '1';
        $query = DB::table('customerinvoice as ci')
            ->join('salesman as sm', 'sm.salesmancode', '=', 'ci.salesmancode')
            ->where('ci.transactiontype', 2)
            ->where('ci.voidflag', 0)
            ->whereNotNull('ci.duedate')
            ->where('ci.customercode', $item['customercode'] ?? '');

        if (! $allowOtherRoutes) {
            $query->where('ci.routecode', $item['routecode'] ?? '');
        }

        $rows = $query->get([
            'ci.transactionkey', 'ci.transactiontype', 'ci.documentnumber', 'ci.invoicenumber',
            'ci.transactiondate', 'ci.transactiontime', 'ci.customercode',
            DB::raw((int) ($item['routecode'] ?? 0) . ' as routecode'),
            'ci.salesmancode', 'ci.totalinvoiceamount', 'ci.totalsalesamount', 'ci.totalreturnamount',
            'ci.totaldamagedamount', 'ci.totalfreesampleamount', 'ci.immediatepaid', 'ci.amountpaid',
            'ci.dnamountpaid', 'ci.cnamountpaid', 'ci.invoicebalance', 'ci.paymenttype', 'ci.voidflag',
            'ci.paymentstatus', DB::raw('sm.alternatesalesmancode as remarks1'), DB::raw("'' as remarks2"),
            'ci.routestartdate', 'ci.erpreferencenumber', 'ci.mdat', 'ci.totalpromoamount', 'ci.gcpaymenttype',
            'ci.totaltaxesamount', 'ci.itemlinetaxamount', 'ci.totaldiscountamount', 'ci.pdcindicator',
            'ci.chequecollection', 'ci.totalexpiryamount', 'ci.currencycode', 'ci.pdcbalance',
            'ci.totalmanualfree', 'ci.totallimitedfree', 'ci.totalrebaterent', 'ci.totalfixedrent', 'ci.data',
            'ci.totaldiscdistributionamount', 'ci.totalreplacementamount', 'ci.pdcdate', 'ci.totalbuybackfreeamount',
            'ci.duedate',
        ])->map(fn ($row) => (array) $row)->all();

        return $this->legacyJson($this->support->normalizeNulls(['customerbalance' => $rows]));
    }

    public function getRouteBalance(Request $request, ?string $tail = null): JsonResponse
    {
        $params = $this->legacyParams($request, $tail);
        $item = $this->decodeItems($params['routebalance'] ?? null)[0] ?? [];

        if ($item === []) {
            return $this->legacyJson(['routebalance' => []]);
        }

        $balance = DB::table('customerinvoice')
            ->where('routecode', $item['routecode'] ?? '')
            ->where('voidflag', 0)
            ->sum('invoicebalance');

        DB::table('routemaster')->where('routecode', $item['routecode'] ?? '')->update(['routebalance' => $balance]);

        return $this->legacyJson($this->support->normalizeNulls([
            'routebalance' => [['routebalance' => $balance]],
        ]));
    }

    public function getWarehouseStock(Request $request, ?string $tail = null): JsonResponse
    {
        $params = $this->legacyParams($request, $tail);
        $routeId = $params['routeid'] ?? '';
        $userId = trim((string) ($params['userid'] ?? ''));

        if (Schema::hasTable('warehousestock')) {
            $rows = DB::table('warehousestock as ws')
                ->join('depotmaster as dm', 'dm.depotcode', '=', 'ws.warehousecode')
                ->join('areamaster as am', 'am.depotcode', '=', 'dm.depotcode')
                ->join('subareamaster as sam', 'sam.areacode', '=', 'am.areacode')
                ->join('routemaster as rm', 'rm.subareacode', '=', 'sam.subareacode')
                ->join('routeitemgrp as rtg', 'rtg.routeitemgrpcode', '=', 'rm.routeitemgrpcode')
                ->join('routeitemmapping as rim', function ($join) {
                    $join->on('rim.routeitemgrpcode', '=', 'rtg.routeitemgrpcode')
                        ->on('rim.itemcode', '=', 'ws.itemcode');
                })
                ->where('rm.routecode', $routeId)
                ->where('ws.totunits', '>', 0)
                ->get([
                    'ws.warehousecode',
                    'ws.trandate',
                    'ws.itemcode',
                    'ws.cases',
                    'ws.units',
                    'ws.totunits',
                    'ws.upc',
                    'ws.caseprice',
                    'ws.eachprice',
                    'ws.balanceqty',
                ])->map(function ($row) {
                    return [
                        'warehousecode' => $row->warehousecode ?? '',
                        'trandate' => $row->trandate,
                        'itemcode' => $row->itemcode,
                        'cases' => (int) ($row->cases ?? 0),
                        'units' => (int) ($row->units ?? 0),
                        'totunits' => (int) ($row->totunits ?? 0),
                        'upc' => (int) ($row->upc ?? 0),
                        'caseprice' => $row->caseprice ?? 0,
                        'eachprice' => $row->eachprice ?? 0,
                        'balanceqty' => $row->balanceqty ?? 0,
                    ];
                })->all();
        } else {
            $query = DB::table('startingloaddetail as load')
                ->select([
                    DB::raw("'' as warehousecode"),
                    DB::raw('COALESCE(DATE(transactiondate), ddate) as trandate'),
                    'load.itemcode',
                    DB::raw('IFNULL(cases,0) as cases'),
                    DB::raw('IFNULL(units,0) as units'),
                    DB::raw('IFNULL(totunits,0) as totunits'),
                    DB::raw('IFNULL(upc,0) as upc'),
                    DB::raw('IFNULL(caseprice,0) as caseprice'),
                    DB::raw('IFNULL(salesprice,0) as eachprice'),
                    DB::raw('IFNULL(totunits,0) as balanceqty'),
                ])
                ->where('load.routecode', $routeId)
                ->where('load.status', 0)
                ->where('load.totunits', '>', 0);

            if ($userId !== '' && $userId !== '0' && $userId !== '-1') {
                $query->where('load.salesmancode', $userId);
            }

            $rows = $query
                ->orderBy('load.itemcode')
                ->get()
                ->map(fn ($row) => (array) $row)
                ->all();
        }

        return $this->legacyJson($this->support->normalizeNulls(['warehousestock' => $rows]));
    }

    public function getSupervisorFoc(Request $request, ?string $tail = null): JsonResponse
    {
        $params = $this->legacyParams($request, $tail);
        $rows = DB::table('subareamaster as sb')
            ->join('supervisor as s', 'sb.supervisorcode', '=', 's.supervisorcode')
            ->join('routemaster as rm', 'rm.subareacode', '=', 'sb.subareacode')
            ->join('supervisor_foc_balance as sfb', 'sfb.supervisorcode', '=', 's.supervisorcode')
            ->join('supervisor_foc as sf', 'sf.supervisorcode', '=', 'sfb.supervisorcode')
            ->where('rm.routecode', $params['routeid'] ?? '')
            ->where('sfb.balanceqty', '>', 0)
            ->where('sf.enddate', '>=', now())
            ->get([
                'sfb.supervisorcode', 'sfb.itemcode', 'sfb.originalqty', 'sfb.balanceqty',
                'sf.startdate', 'sf.enddate', 'rm.routecode', 'sf.contractid',
            ])->map(fn ($row) => (array) $row)->all();

        return $this->legacyJson($this->support->normalizeNulls(['supervisorfoc' => $rows]));
    }

    public function updateSupervisorFoc(Request $request, ?string $tail = null): Response
    {
        $params = $this->legacyParams($request, $tail);
        $supervisorCode = (string) ($params['supervisorcode'] ?? '');
        $items = $this->decodeItems($params['itemcode'] ?? null);

        if ($supervisorCode === '' || $items === []) {
            return $this->legacyText('Invalid Input');
        }

        foreach ($items as $item) {
            DB::table('supervisor_foc_balance')
                ->where('itemcode', $item['itemcode'] ?? '')
                ->where('supervisorcode', $supervisorCode)
                ->decrement('balanceqty', (float) ($item['manualfreeqty'] ?? 0));
        }

        return $this->legacyText('Success');
    }

    private function decodeItems(mixed $raw): array
    {
        if (! is_string($raw) || $raw === '') {
            return [];
        }

        $decoded = json_decode(stripslashes($raw), true);

        return is_array($decoded) ? $decoded : [];
    }

    private function zeroDefault(mixed $value): mixed
    {
        return $value === '' ? 0 : $value;
    }
}
