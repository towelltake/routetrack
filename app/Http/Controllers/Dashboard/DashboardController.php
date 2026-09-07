<?php

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use App\Models\AccountSalesman;
use App\Models\CompanyMaster;
use App\Models\RouteMaster;
use App\Models\RouteSequence;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('routelocation/Index');
    }

    private function matchingRoutes(array $filters = []): Builder
    {
        $query = RouteMaster::query()
            ->join('company', 'company.cmpycode', '=', 'routemaster.cmpycode')
            ->leftJoin('clustermaster', 'clustermaster.clustercode', '=', 'company.clustercode')
            ->leftJoin('regionmaster', 'regionmaster.regionmstcode', '=', 'routemaster.regionmstcode')
            ->where('company.activestatus', 1)
            ->whereIn('routemaster.routecode', session('user_access.route_codes', []))
            ->whereIn('routemaster.cmpycode', session('user_access.company_codes', []))
            ->whereIn('routemaster.subareacode', session('user_access.subarea_codes', []))
            ->whereIn('routemaster.routecode', RouteSequence::query()->select('routecode'));

        foreach ([
            'entities' => 'company.entity',
            'clusters' => 'company.clustercode',
            'divisions' => 'routemaster.cmpycode',
            'regions' => 'routemaster.regionmstcode',
            'routes' => 'routemaster.routecode',
        ] as $filter => $column) {
            if (!empty($filters[$filter])) {
                $query->whereIn($column, $filters[$filter]);
            }
        }

        return $query;
    }

    public function filters(): JsonResponse
    {
        return response()->json($this->matchingRoutes()
            ->orderBy('routemaster.routename')
            ->get([
                'routemaster.routecode', 'routemaster.routename',
                'company.cmpycode', 'company.name', 'company.entity',
                'clustermaster.clustercode', 'clustermaster.clustername',
                'regionmaster.regionmstcode', 'regionmaster.regionmstname',
            ]));
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
     * selected filters — one marker per route, all at once.
     */
    public function lastLocations(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'date' => ['required', 'date_format:Y-m-d'],
            'companycode' => ['nullable', 'integer'],
            'routecode' => ['nullable', 'integer'],
            'entities' => ['sometimes', 'array', 'max:1000'],
            'entities.*' => ['string', 'max:100'],
            'clusters' => ['sometimes', 'array', 'max:1000'],
            'clusters.*' => ['integer'],
            'divisions' => ['sometimes', 'array', 'max:1000'],
            'divisions.*' => ['integer'],
            'regions' => ['sometimes', 'array', 'max:1000'],
            'regions.*' => ['integer'],
            'routes' => ['sometimes', 'array', 'max:1000'],
            'routes.*' => ['integer'],
        ]);

        $matchingRouteCodes = $this->matchingRoutes($validated)
            ->when($validated['companycode'] ?? null, fn ($query, $code) => $query->where('routemaster.cmpycode', $code))
            ->when($validated['routecode'] ?? null, fn ($query, $code) => $query->where('routemaster.routecode', $code))
            ->pluck('routemaster.routecode');

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
