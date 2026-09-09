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

    private function matchingRoutes(array $filters = [], bool $requireSequence = true): Builder
    {
        $query = RouteMaster::query()
            ->join('company', 'company.cmpycode', '=', 'routemaster.cmpycode')
            ->leftJoin('clustermaster', 'clustermaster.clustercode', '=', 'company.clustercode')
            ->leftJoin('regionmaster', 'regionmaster.regionmstcode', '=', 'routemaster.regionmstcode')
            ->where('company.activestatus', 1)
            ->where('routemaster.activestatus', 1)
            ->whereIn('routemaster.routecode', session('user_access.route_codes', []))
            ->whereIn('routemaster.cmpycode', session('user_access.company_codes', []))
            ->whereIn('routemaster.subareacode', session('user_access.subarea_codes', []))
            ->when($requireSequence, fn ($query) => $query->whereIn('routemaster.routecode', RouteSequence::query()->select('routecode')));

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
            ->where('activestatus', 1)
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
            ->where('activestatus', 1)
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
        $validated = $this->validateFilters($request);

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
        $points = $this->journeyLocations($routeDays, true)->keyBy('routecode');

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

    private function validateFilters(Request $request): array
    {
        return $request->validate([
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
    }

    public function routeStatus(Request $request): JsonResponse
    {
        $filters = $this->validateFilters($request);
        $routes = $this->matchingRoutes($filters, false)
            ->when($filters['companycode'] ?? null, fn ($q, $code) => $q->where('routemaster.cmpycode', $code))
            ->when($filters['routecode'] ?? null, fn ($q, $code) => $q->where('routemaster.routecode', $code))
            ->orderBy('routemaster.routecode')
            ->get(['routemaster.routecode', 'routemaster.routename', 'routemaster.salesmancode']);
        $journeys = DB::table('startendday')->whereIn('routecode', $routes->pluck('routecode'))
            ->whereDate('routestartdate', '>=', $filters['from_date'] ?? $filters['date'])
            ->whereDate('routestartdate', '<=', $filters['to_date'] ?? $filters['date'])
            ->orderBy('routestartdate')->orderBy('routestarttime')->orderBy('routekey')
            ->get(['routekey', 'routecode', 'salesmancode', 'routestartdate', 'routestarttime', 'routeenddate', 'routeendtime', 'routeclosed']);
        $salesmen = AccountSalesman::query()->whereIn('salesmancode', $routes->pluck('salesmancode')->merge($journeys->pluck('salesmancode'))->filter()->unique())
            ->pluck('salesmanname1', 'salesmancode');
        return response()->json([
            'routes' => $routes->map(fn ($route) => ['routecode' => $route->routecode, 'routename' => $route->routename, 'salesman' => $salesmen->get($route->salesmancode)]),
            'journeys' => $journeys->map(fn ($journey) => [
                'routekey' => $journey->routekey, 'routecode' => $journey->routecode,
                'date' => substr((string) $journey->routestartdate, 0, 10),
                'salesman' => $salesmen->get($journey->salesmancode),
                'start' => $this->routeDateTime($journey->routestartdate, $journey->routestarttime),
                'end' => $this->routeDateTime($journey->routeenddate, $journey->routeendtime),
                'closed' => (int) $journey->routeclosed === 1,
            ]),
        ]);
    }

    public function metrics(Request $request): JsonResponse
    {
        $filters = $this->validateFilters($request);
        $routeCodes = $this->matchingRoutes($filters, false)
            ->when($filters['companycode'] ?? null, fn ($query, $code) => $query->where('routemaster.cmpycode', $code))
            ->when($filters['routecode'] ?? null, fn ($query, $code) => $query->where('routemaster.routecode', $code))
            ->select('routemaster.routecode');
        $journeys = DB::table('startendday')
            ->whereIn('routecode', $routeCodes)
            ->whereDate('routestartdate', '>=', $filters['from_date'] ?? $filters['date'])
            ->whereDate('routestartdate', '<=', $filters['to_date'] ?? $filters['date'])
            ->get();

        $metadata = $this->matchingRoutes($filters, false)->get([
            'routemaster.routecode', 'routemaster.routename', 'company.cmpycode', 'company.name as division',
            'company.entity', 'clustermaster.clustercode', 'clustermaster.clustername as cluster',
            'regionmaster.regionmstcode', 'regionmaster.regionmstname as region',
        ])->keyBy('routecode');
        $salesmen = AccountSalesman::query()->whereIn('salesmancode', $journeys->pluck('salesmancode')->filter()->unique())
            ->get(['salesmancode', 'salesmanname1'])->keyBy('salesmancode');
        $livePoints = $this->journeyLocations($journeys->filter(fn ($journey) => (int) $journey->routeclosed !== 1));
        foreach ($journeys as $journey) {
            if ((int) $journey->routeclosed !== 1) {
                $journey->last_location_time = $livePoints->get($journey->routekey)?->effective_timestamp;
            }
            $route = $metadata->get($journey->routecode);
            foreach (['routename', 'cmpycode', 'division', 'entity', 'clustercode', 'cluster', 'regionmstcode', 'region'] as $field) {
                $journey->$field = $route?->$field;
            }
            $journey->salesperson = $salesmen->get($journey->salesmancode ?? null)?->salesmanname1;
        }

        $days = (new \DateTimeImmutable($filters['from_date'] ?? $filters['date']))
            ->diff(new \DateTimeImmutable($filters['to_date'] ?? $filters['date']))->days + 1;
        $routeCount = (clone $routeCodes)->distinct()->count('routemaster.routecode');
        $started = $journeys->unique(fn ($journey) => $journey->routecode.':'.substr((string) $journey->routestartdate, 0, 10))->count();
        $metrics = app(\App\Services\DashboardMetrics::class)->summarize($journeys);
        $metrics['routes_started'] = $started;
        $metrics['route_count'] = $routeCount;
        $metrics['period_days'] = $days;
        $metrics['total_routes'] = $routeCount * $days;
        $metrics['routes_not_started'] = max(0, $metrics['total_routes'] - $started);

        return response()->json($metrics);
    }

    private function journeyLocations(\Illuminate\Support\Collection $journeys, bool $requireCoordinates = false): \Illuminate\Support\Collection
    {
        if ($journeys->isEmpty()) return collect();
        $starts = DB::table('startendday')->whereIn('routecode', $journeys->pluck('routecode')->unique())
            ->whereDate('routestartdate', '>=', substr((string) $journeys->min('routestartdate'), 0, 10))
            ->orderBy('routestartdate')->orderBy('routestarttime')
            ->get(['routecode', 'routestartdate', 'routestarttime'])->groupBy('routecode')
            ->map(fn ($rows) => $rows->map(fn ($row) => $this->routeDateTime($row->routestartdate, $row->routestarttime ?: '00:00:00'))->filter());
        $connection = DB::connection('tracking_pgsql');
        $points = collect();
        // Batch remote lookups without transferring the full GPS trail into PHP.
        foreach ($journeys->chunk(100) as $chunk) {
            $batch = null;
            foreach ($chunk as $journey) {
                $start = $this->routeDateTime($journey->routestartdate, $journey->routestarttime ?: ($requireCoordinates ? '00:00:00' : null));
                if (!$start) continue;
                $next = $starts->get($journey->routecode, collect())->first(fn ($value) => $value > $start);
                $end = (int) $journey->routeclosed === 1 ? $this->routeDateTime($journey->routeenddate, $journey->routeendtime) : null;
                $query = $connection->table('trac_routetrack')->where('routecode', $journey->routecode)
                    ->whereRaw('COALESCE(cdate, date + time) >= ?', [$start])
                    ->when($next, fn ($q) => $q->whereRaw('COALESCE(cdate, date + time) < ?', [$next]))
                    ->when($end, fn ($q) => $q->whereRaw('COALESCE(cdate, date + time) <= ?', [$end]))
                    ->when($requireCoordinates, fn ($q) => $q->whereNotNull('latitude')->whereNotNull('longitude')->where('latitude', '!=', 0)->where('longitude', '!=', 0))
                    ->selectRaw('CAST(? AS BIGINT) as journey_key, routecode, salesmancode, latitude, longitude, COALESCE(cdate, date + time) as effective_timestamp', [$journey->routekey])
                    ->orderByDesc('effective_timestamp')->orderByDesc('id')->limit(1);
                $part = $connection->query()->fromSub($query, 'latest_point');
                if ($batch === null) $batch = $part;
                else $batch->unionAll($part);
            }
            if ($batch !== null) $points = $points->concat($batch->get());
        }
        return $points->keyBy('journey_key');
    }

    private function routeDateTime(mixed $date, mixed $time): ?string
    {
        if (! $date || ! $time) {
            return null;
        }

        return substr((string) $date, 0, 10).' '.$time;
    }
}
