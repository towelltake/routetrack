<?php

namespace App\Http\Controllers\Api;

use App\Services\LegacyApi\LegacyProcedureService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class TransactionController extends LegacyApiController
{
    public function __construct(private readonly LegacyProcedureService $support)
    {
    }

    public function tranData(Request $request, ?string $tail = null): JsonResponse
    {
        $params = $this->legacyParams($request, $tail);
        $routeKey = $params['routekey'] ?? '';
        $payload = [
            ['TYPE' => 'SEDY', 'CloudCount' => DB::table('startendday')->where('routekey', $routeKey)->count()],
            ['TYPE' => 'IVTH', 'CloudCount' => DB::table('inventorytransactionheader')->where('routekey', $routeKey)->where('data', 0)->where('voidflag', 0)->where('manualinvoice', 0)->count()],
            ['TYPE' => 'IVTD', 'CloudCount' => DB::table('inventorytransactiondetail')->where('routekey', $routeKey)->count()],
            ['TYPE' => 'IVSD', 'CloudCount' => DB::table('inventorysummarydetail')->where('routekey', $routeKey)->count()],
            ['TYPE' => 'RSCS', 'CloudCount' => DB::table('routesequencecustomerstatus')->where('routekey', $routeKey)->count()],
            ['TYPE' => 'CUOC', 'CloudCount' => DB::table('customeroperationscontrol')->where('routekey', $routeKey)->count()],
            ['TYPE' => 'INVH', 'CloudCount' => DB::table('invoiceheader')->where('routekey', $routeKey)->where('data', 0)->where('manualinvoicetype', 0)->count()],
            ['TYPE' => 'INVD', 'CloudCount' => DB::table('invoicedetail')->where('routekey', $routeKey)->count()],
            ['TYPE' => 'INVR', 'CloudCount' => DB::table('invoicerxddetail')->where('routekey', $routeKey)->count()],
            ['TYPE' => 'BAED', 'CloudCount' => DB::table('batchexpirydetail')->where('routekey', $routeKey)->count()],
            ['TYPE' => 'SAOH', 'CloudCount' => DB::table('salesorderheader')->where('routekey', $routeKey)->count()],
            ['TYPE' => 'SAOD', 'CloudCount' => DB::table('salesorderdetail')->where('routekey', $routeKey)->count()],
            ['TYPE' => 'SAOR', 'CloudCount' => DB::table('orderrxddetail')->where('routekey', $routeKey)->count()],
            ['TYPE' => 'PRMD', 'CloudCount' => DB::table('promotiondetail')->where('routekey', $routeKey)->count()],
            ['TYPE' => 'ARHR', 'CloudCount' => DB::table('arheader')->where('routekey', $routeKey)->count()],
            ['TYPE' => 'ARDL', 'CloudCount' => DB::table('ardetail')->where('routekey', $routeKey)->count()],
            ['TYPE' => 'CCDS', 'CloudCount' => (int) DB::table('cashcheckdetail as ccd')->where('ccd.routekey', $routeKey)->get()->sum(function ($row) {
                return DB::table('invoiceheader')->where('routekey', $row->routekey)->where('visitkey', $row->visitkey)->where('voidflag', 0)->where('manualinvoicetype', 0)->count()
                    + DB::table('arheader')->where('routekey', $row->routekey)->where('visitkey', $row->visitkey)->where('voidflag', 0)->count();
            })],
            ['TYPE' => 'CPPD', 'CloudCount' => DB::table('customerpromotionplandetail')->where('routekey', $routeKey)->count()],
            ['TYPE' => 'SADS', 'CloudCount' => DB::table('surveyauditdetail')->where('routekey', $routeKey)->count()],
            ['TYPE' => 'PECD', 'CloudCount' => DB::table('posequipmentchangedetail')->where('routekey', $routeKey)->count()],
            ['TYPE' => 'CUID', 'CloudCount' => DB::table('customerinventorydetail')->where('routekey', $routeKey)->count()],
            ['TYPE' => 'NOSC', 'CloudCount' => DB::table('nonservicedcustomer')->where('routekey', $routeKey)->count()],
            ['TYPE' => 'NOSH', 'CloudCount' => DB::table('nosalesheader')->where('routekey', $routeKey)->count()],
            ['TYPE' => 'AORL', 'CloudCount' => DB::table('t_access_override_log')->where('routekey', $routeKey)->count()],
        ];

        return response()->json(
            $this->support->normalizeNulls($payload),
            200,
            ['Access-Control-Allow-Origin' => '*'],
            JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
        );
    }

}
