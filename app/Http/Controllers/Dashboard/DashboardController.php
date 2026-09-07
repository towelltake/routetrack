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
     * The last known GPS ping for the latest journey started within the
     * selected filters — one marker per route, all at once.
     */
    public function lastLocations(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'date' => ['required_without_all:from_date,to_date', 'date_format:Y-m-d'],
            'from_date' => ['required_without:date', 'required_with:to_date', 'date_format:Y-m-d'],
            'to_date' => ['required_without:date', 'required_with:from_date', 'date_format:Y-m-d', 'after_or_equal:from_date'],
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

        $fromDate = $validated['from_date'] ?? $validated['date'];
        $toDate = $validated['to_date'] ?? $validated['date'];
        $routeDays = DB::table('startendday')
            ->whereIn('routecode', $routes->keys())
            ->whereDate('routestartdate', '>=', $fromDate)
            ->whereDate('routestartdate', '<=', $toDate)
            ->orderByDesc('routestartdate')
            ->orderByDesc('routestarttime')
            ->orderByDesc('routekey')
            ->get(['routekey', 'routecode', 'routestartdate', 'routestarttime', 'routeenddate', 'routeendtime', 'routeclosed'])
            ->unique('routecode')
            ->keyBy('routecode');

        if ($routeDays->isEmpty()) {
            return response()->json([]);
        }

        // Match GPS records to the chosen journey, including journeys ending after midnight.
        $points = collect();
        foreach ($routeDays as $routeDay) {
            $start = $this->routeDateTime($routeDay->routestartdate, $routeDay->routestarttime ?: '00:00:00');
            $end = (int) $routeDay->routeclosed === 1
                ? $this->routeDateTime($routeDay->routeenddate, $routeDay->routeendtime) : null;
            $nextJourney = DB::table('startendday')
                ->where('routecode', $routeDay->routecode)
                ->whereRaw("CONCAT(SUBSTR(routestartdate, 1, 10), ' ', COALESCE(routestarttime, '00:00:00')) > ?", [$start])
                ->orderBy('routestartdate')->orderBy('routestarttime')
                ->first(['routestartdate', 'routestarttime']);
            $nextStart = $nextJourney
                ? $this->routeDateTime($nextJourney->routestartdate, $nextJourney->routestarttime ?: '00:00:00') : null;
            $point = DB::connection('tracking_pgsql')->table('trac_routetrack')
                ->where('routecode', $routeDay->routecode)
                ->whereRaw('COALESCE(cdate, date + time) >= ?', [$start])
                ->when($end, fn ($query) => $query->whereRaw('COALESCE(cdate, date + time) <= ?', [$end]))
                ->when($nextStart, fn ($query) => $query->whereRaw('COALESCE(cdate, date + time) < ?', [$nextStart]))
                ->whereNotNull('latitude')->whereNotNull('longitude')
                ->where('latitude', '!=', 0)->where('longitude', '!=', 0)
                ->selectRaw('routecode, salesmancode, latitude, longitude, COALESCE(cdate, date + time) as effective_timestamp')
                ->orderByDesc('effective_timestamp')->orderByDesc('id')
                ->first();
            if ($point) $points->put($routeDay->routecode, $point);
        }

        $salesmen = AccountSalesman::query()
            ->whereIn('salesmancode', $points->pluck('salesmancode')->unique())
            ->get(['salesmancode', 'salesmanname1'])
            ->keyBy('salesmancode');

        $results = $points->map(function ($point) use ($routes, $salesmen, $routeDays) {
            $route = $routes->get($point->routecode);
            $routeDay = $routeDays->get($point->routecode);
            $closed = (int) ($routeDay?->routeclosed ?? 0) === 1;

            return [
                'routecode' => $point->routecode,
                'routekey' => (int) $routeDay->routekey,
                'route_date' => substr((string) $routeDay->routestartdate, 0, 10),
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
