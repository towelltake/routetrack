<?php

namespace App\Http\Controllers\RouteLocation;

use App\Http\Controllers\Controller;
use App\Models\AccountSalesman;
use App\Models\AreaMaster;
use App\Models\CompanyMaster;
use App\Models\RouteMaster;
use App\Models\RouteSequence;
use App\Models\SubAreaMaster;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class RouteLocationController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('routelocation/Index');
    }

    public function companies(): JsonResponse
    {
        $routedCmpyCodes = RouteMaster::query()
            ->whereIn('routecode', RouteSequence::query()->distinct()->pluck('routecode'))
            ->distinct()
            ->pluck('cmpycode');

        $companies = CompanyMaster::query()
            ->whereIn('cmpycode', session('user_access.company_codes', []))
            ->whereIn('cmpycode', $routedCmpyCodes)
            ->orderBy('name')
            ->get(['cmpycode', 'name']);

        return response()->json($companies);
    }

    public function areas(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'companycode' => ['nullable', 'integer'],
        ]);

        $routedSubareaCodes = RouteMaster::query()
            ->whereIn('cmpycode', session('user_access.company_codes', []))
            ->whereIn('subareacode', session('user_access.subarea_codes', []))
            ->whereIn('routecode', RouteSequence::query()->distinct()->pluck('routecode'))
            ->when($validated['companycode'] ?? null, fn ($query, $companycode) => $query->where('cmpycode', $companycode))
            ->pluck('subareacode');

        $areaCodes = SubAreaMaster::query()
            ->whereIn('subareacode', $routedSubareaCodes)
            ->distinct()
            ->pluck('areacode');

        $areas = AreaMaster::query()
            ->whereIn('areacode', session('user_access.area_codes', []))
            ->whereIn('areacode', $areaCodes)
            ->orderBy('areaname')
            ->get(['areacode', 'areaname']);

        return response()->json($areas);
    }

    public function subareas(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'areacode' => ['required', 'integer'],
            'companycode' => ['nullable', 'integer'],
        ]);

        $routedSubareaCodes = RouteMaster::query()
            ->whereIn('cmpycode', session('user_access.company_codes', []))
            ->whereIn('subareacode', session('user_access.subarea_codes', []))
            ->whereIn('routecode', RouteSequence::query()->distinct()->pluck('routecode'))
            ->when($validated['companycode'] ?? null, fn ($query, $companycode) => $query->where('cmpycode', $companycode))
            ->pluck('subareacode');

        $subareas = SubAreaMaster::query()
            ->whereIn('subareacode', session('user_access.subarea_codes', []))
            ->where('areacode', $validated['areacode'])
            ->whereIn('subareacode', $routedSubareaCodes)
            ->orderBy('subareaname')
            ->get(['subareacode', 'subareaname']);

        return response()->json($subareas);
    }

    /**
     * The last known GPS ping on this date for every route matching the
     * current Company/Area/Sub Area filter — one marker per route, all at once.
     */
    public function lastLocations(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'date' => ['required', 'date_format:Y-m-d'],
            'companycode' => ['required', 'integer'],
            'areacode' => ['required', 'integer'],
            'subareacode' => ['required', 'integer'],
        ]);

        $matchingRouteCodes = RouteMaster::query()
            ->whereIn('cmpycode', session('user_access.company_codes', []))
            ->whereIn('subareacode', session('user_access.subarea_codes', []))
            ->whereIn('routecode', RouteSequence::query()->distinct()->pluck('routecode'))
            ->when($validated['companycode'] ?? null, fn ($query, $companycode) => $query->where('cmpycode', $companycode))
            ->when($validated['subareacode'] ?? null, fn ($query, $subareacode) => $query->where('subareacode', $subareacode))
            ->when(
                ($validated['areacode'] ?? null) && ! ($validated['subareacode'] ?? null),
                fn ($query) => $query->whereIn('subareacode', SubAreaMaster::query()->where('areacode', $validated['areacode'])->pluck('subareacode'))
            )
            ->pluck('routecode');

        $routes = RouteMaster::query()
            ->whereIn('routecode', $matchingRouteCodes)
            ->orderBy('routename')
            ->get(['routecode', 'routename'])
            ->keyBy('routecode');

        if ($routes->isEmpty()) {
            return response()->json([]);
        }

        $points = DB::connection('tracking_pgsql')->table('trac_routetrack')
            ->whereIn('routecode', $routes->keys())
            ->where('date', $validated['date'])
            ->whereNotNull('latitude')
            ->whereNotNull('longitude')
            ->where('latitude', '!=', 0)
            ->where('longitude', '!=', 0)
            ->selectRaw('routecode, salesmancode, latitude, longitude, COALESCE(cdate, date + time) as effective_timestamp')
            ->orderByDesc('effective_timestamp')
            ->orderByDesc('id')
            ->get()
            ->unique('routecode')
            ->keyBy('routecode');

        $salesmen = AccountSalesman::query()
            ->whereIn('salesmancode', $points->pluck('salesmancode')->unique())
            ->get(['salesmancode', 'salesmanname1'])
            ->keyBy('salesmancode');

        $results = $points->map(function ($point) use ($routes, $salesmen) {
            $route = $routes->get($point->routecode);

            return [
                'routecode' => $point->routecode,
                'routename' => $route?->routename,
                'salesmanname' => $salesmen->get($point->salesmancode)?->salesmanname1,
                'lat' => (float) $point->latitude,
                'lng' => (float) $point->longitude,
                'time' => $point->effective_timestamp,
            ];
        })->values();

        return response()->json($results);
    }
}
