<?php

namespace App\Http\Controllers\RouteLocation;

use App\Http\Controllers\Controller;
use App\Models\AccountSalesman;
use App\Models\CompanyMaster;
use App\Models\RouteMaster;
use App\Models\RouteSequence;
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
            ->whereIn('routecode', session('user_access.route_codes', []))
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

    public function routes(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'companycode' => ['nullable', 'integer'],
        ]);

        $routes = RouteMaster::query()
            ->whereIn('routecode', session('user_access.route_codes', []))
            ->whereIn('cmpycode', session('user_access.company_codes', []))
            ->whereIn('subareacode', session('user_access.subarea_codes', []))
            ->whereIn('routecode', RouteSequence::query()->distinct()->pluck('routecode'))
            ->when($validated['companycode'] ?? null, fn ($query, $companycode) => $query->where('cmpycode', $companycode))
            ->orderBy('routename')
            ->get(['routecode', 'routename']);

        return response()->json($routes);
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
            'routecode' => ['nullable', 'integer'],
        ]);

        $matchingRouteCodes = RouteMaster::query()
            ->whereIn('routecode', session('user_access.route_codes', []))
            ->whereIn('cmpycode', session('user_access.company_codes', []))
            ->whereIn('subareacode', session('user_access.subarea_codes', []))
            ->whereIn('routecode', RouteSequence::query()->distinct()->pluck('routecode'))
            ->when($validated['companycode'] ?? null, fn ($query, $companycode) => $query->where('cmpycode', $companycode))
            ->when($validated['routecode'] ?? null, fn ($query, $routecode) => $query->where('routecode', $routecode))
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

        $routeDays = DB::table('startendday')
            ->whereIn('routecode', $routes->keys())
            ->where(function ($query) use ($validated) {
                $query->whereDate('routestartdate', $validated['date'])
                    ->orWhereDate('routeenddate', $validated['date'])
                    ->orWhere(function ($query) use ($validated) {
                        $query->whereDate('routestartdate', '<=', $validated['date'])
                            ->whereDate('routeenddate', '>=', $validated['date']);
                    });
            })
            ->orderByDesc('routekey')
            ->get(['routekey', 'routecode', 'routestartdate', 'routestarttime', 'routeenddate', 'routeendtime', 'routeclosed'])
            ->unique('routecode')
            ->keyBy('routecode');

        $results = $points->map(function ($point) use ($routes, $salesmen, $routeDays) {
            $route = $routes->get($point->routecode);
            $routeDay = $routeDays->get($point->routecode);
            $closed = (int) ($routeDay?->routeclosed ?? 0) === 1;

            return [
                'routecode' => $point->routecode,
                'routename' => $route?->routename,
                'salesmanname' => $salesmen->get($point->salesmancode)?->salesmanname1,
                'status' => $closed ? 'Route End' : 'LIVE',
                'closed' => $closed,
                'route_start_time' => $this->routeDateTime($routeDay?->routestartdate, $routeDay?->routestarttime),
                'route_end_time' => $this->routeDateTime($routeDay?->routeenddate, $routeDay?->routeendtime),
                'lat' => (float) $point->latitude,
                'lng' => (float) $point->longitude,
                'time' => $point->effective_timestamp,
            ];
        })->values();

        return response()->json($results);
    }

    private function routeDateTime(mixed $date, mixed $time): ?string
    {
        if (! $date || ! $time) {
            return null;
        }

        return substr((string) $date, 0, 10).' '.$time;
    }
}
