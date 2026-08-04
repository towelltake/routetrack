<?php

namespace App\Http\Controllers\Api;

use App\Services\LegacyApi\IndexApiService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class IndexController extends LegacyApiController
{
    public function __construct(private readonly IndexApiService $service)
    {
    }

    public function salesmanLogin(Request $request, ?string $tail = null): JsonResponse
    {
        $params = $this->legacyParams($request, $tail);

        return $this->legacyJson(
            $this->service->salesmanLogin(
                (string) ($params['username'] ?? ''),
                (string) ($params['password'] ?? ''),
                (string) ($params['deviceid'] ?? '')
            )
        );
    }

    public function companyIdByDevice(Request $request, ?string $tail = null): JsonResponse
    {
        $params = $this->legacyParams($request, $tail);

        return $this->legacyJson(
            $this->service->companyIdByDevice((string) ($params['deviceid'] ?? ''))
        );
    }

    public function getSyncData(Request $request, ?string $tail = null): JsonResponse
    {
        $params = $this->legacyParams($request, $tail);

        return $this->legacyJson(
            $this->service->getSyncData(
                (string) ($params['userid'] ?? ''),
                (string) ($params['deviceid'] ?? ''),
                (string) ($params['routeid'] ?? ''),
                (string) ($params['mdate'] ?? ''),
                (int) ($params['table'] ?? 0)
            )
        );
    }

    public function getSyncFullData(Request $request, ?string $tail = null): JsonResponse
    {
        $params = $this->legacyParams($request, $tail);

        return $this->legacyJson(
            $this->service->getSyncFullData(
                (string) ($params['userid'] ?? ''),
                (string) ($params['deviceid'] ?? ''),
                (string) ($params['routeid'] ?? ''),
                (string) ($params['mdate'] ?? '')
            )
        );
    }

    public function updateSyncDate(Request $request, ?string $tail = null): JsonResponse
    {
        $params = $this->legacyParams($request, $tail);

        return $this->legacyJson(
            $this->service->updateSyncDate(
                (string) ($params['userid'] ?? ''),
                (string) ($params['deviceid'] ?? ''),
                (string) ($params['routecode'] ?? ''),
                (string) ($params['routekey'] ?? ''),
                (string) ($params['routeclosed'] ?? '')
            )
        );
    }

}
