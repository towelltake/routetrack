<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

abstract class LegacyApiController extends Controller
{
    protected function legacyParams(Request $request, ?string $tail = null): array
    {
        $params = $request->query();

        foreach ($request->request->all() as $key => $value) {
            $params[$key] = $value;
        }

        foreach ($request->json()->all() as $key => $value) {
            $params[$key] = $value;
        }

        if ($params === []) {
            $raw = $request->getContent();
            $decoded = is_string($raw) && $raw !== '' ? json_decode($raw, true) : null;

            if (is_array($decoded)) {
                $params = $decoded;
            }
        }

        $segments = array_values(array_filter(
            explode('/', trim((string) $tail, '/')),
            static fn ($value) => $value !== ''
        ));

        for ($index = 0; $index < count($segments); $index += 2) {
            $key = $segments[$index] ?? null;
            $value = $segments[$index + 1] ?? null;

            if ($key !== null) {
                $params[$key] = $value;
            }
        }

        return $params;
    }

    protected function legacyJson(array $payload): JsonResponse
    {
        return response()->json(
            $payload,
            200,
            ['Access-Control-Allow-Origin' => '*'],
            JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
        );
    }

    protected function legacyText(string $payload): Response
    {
        return response(
            $payload,
            200,
            ['Access-Control-Allow-Origin' => '*', 'Content-Type' => 'text/plain; charset=UTF-8']
        );
    }
}
