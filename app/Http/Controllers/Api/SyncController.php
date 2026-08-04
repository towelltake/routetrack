<?php

namespace App\Http\Controllers\Api;

use App\Services\LegacyApi\SyncApiService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class SyncController extends LegacyApiController
{
    public function __construct(private readonly SyncApiService $service)
    {
    }

    public function sendData(Request $request, ?string $tail = null): JsonResponse
    {
        $params = $this->legacyParams($request, $tail);

        try {
            return $this->legacyJson($this->service->sync($params));
        } catch (\Throwable $exception) {
            Log::error('sync.senddata failed', [
                'message' => $exception->getMessage(),
                'userid' => $params['userid'] ?? null,
                'routecode' => $params['routecode'] ?? null,
                'routekey' => $params['routekey'] ?? null,
                'keys' => array_keys($params),
            ]);

            return response()->json([
                'status' => 500,
                'error' => 'sync_senddata_failed',
                'message' => $exception->getMessage(),
            ], 500, ['Access-Control-Allow-Origin' => '*']);
        }
    }
}
