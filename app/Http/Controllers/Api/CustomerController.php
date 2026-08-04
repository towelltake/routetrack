<?php

namespace App\Http\Controllers\Api;

use App\Services\LegacyApi\LegacyProcedureService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class CustomerController extends LegacyApiController
{
    public function __construct(private readonly LegacyProcedureService $support)
    {
    }

    public function customerMaster(Request $request, ?string $tail = null): JsonResponse
    {
        $params = $this->legacyParams($request, $tail);
        $query = DB::table('customermaster');

        if (($params['routecode'] ?? '') !== '') {
            $query->where('routecode', $params['routecode']);
        }

        if (($params['customercode'] ?? '') !== '') {
            $query->where('customercode', $params['customercode']);
        }

        $payload = (array) ($query->first() ?? []);

        return response()->json(
            $this->support->normalizeNulls($payload),
            200,
            ['Access-Control-Allow-Origin' => '*'],
            JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
        );
    }

}
