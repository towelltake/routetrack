<?php

namespace App\Http\Controllers\CustomerLocation;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

class OsrmRouteController extends Controller
{
    public function route(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'from_lat' => ['required', 'numeric'],
            'from_lng' => ['required', 'numeric'],
            'to_lat' => ['required', 'numeric'],
            'to_lng' => ['required', 'numeric'],
        ]);

        $coordinates = sprintf(
            '%F,%F;%F,%F',
            $validated['from_lng'],
            $validated['from_lat'],
            $validated['to_lng'],
            $validated['to_lat'],
        );

        $response = Http::baseUrl(config('services.osrm.url'))
            ->get("/route/v1/driving/{$coordinates}", [
                'overview' => 'full',
                'geometries' => 'geojson',
            ]);

        if (! $response->successful() || $response->json('code') !== 'Ok') {
            return response()->json(['error' => 'Unable to compute route'], 502);
        }

        $route = $response->json('routes.0');

        return response()->json([
            'distance' => $route['distance'],
            'duration' => $route['duration'],
            'geometry' => $route['geometry'],
        ]);
    }
}
