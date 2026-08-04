<?php

namespace App\Http\Controllers\Links;

use App\Http\Controllers\Controller;
use App\Services\AccessScopeService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class RouteItemGroupController extends Controller
{
    private const FILL_BY_REGION = 1;
    private const FILL_BY_DEPOT = 2;
    private const FILL_BY_AREA = 3;

    public function index(): Response
    {
        return Inertia::render('links/route-item-group/Index', [
            'available' => $this->hasRequiredTables(),
            'formMeta' => [
                'title' => 'Route Item Group',
                'subtitle' => 'Assign a route item group to active routes by selected region, depot, or area',
                'indexUrl' => '/links/route-item-group',
                'loadUrl' => '/links/route-item-group/load',
                'saveUrl' => '/links/route-item-group/save',
                'permission' => 'route item group',
            ],
            'optionSets' => [
                'routeItemGroupOptions' => $this->routeItemGroupOptions(),
                'fillByOptions' => $this->fillByOptions(),
                'regionOptions' => $this->regionOptions(),
                'depotOptions' => $this->depotOptions(),
                'areaOptions' => $this->areaOptions(),
            ],
        ]);
    }

    public function load(Request $request): JsonResponse
    {
        abort_unless($this->hasRequiredTables(), 404);

        $data = $this->validatedPayload($request, false);

        $allRoutes = $this->routeQuery(
            (int) $data['fill_by'],
            (int) $data['filter_value']
        )->get();

        $selectedRoutes = $this->routeQuery(
            (int) $data['fill_by'],
            (int) $data['filter_value']
        )
            ->where('route.routeitemgrpcode', (int) $data['route_item_group'])
            ->get();

        return response()->json([
            'routes' => $this->transformRoutes($allRoutes),
            'selectedRoutes' => $this->transformRoutes($selectedRoutes),
            'selectedRouteIds' => $selectedRoutes->pluck('id')->map(fn ($value) => (int) $value)->all(),
        ]);
    }

    public function save(Request $request): RedirectResponse
    {
        abort_unless($this->hasRequiredTables(), 404);

        $data = $this->validatedPayload($request, true);

        $routeItemGroup = (int) $data['route_item_group'];
        $fillBy = (int) $data['fill_by'];
        $filterValue = (int) $data['filter_value'];
        $routeIds = collect($data['routes'] ?? [])
            ->map(fn ($value) => (int) $value)
            ->unique()
            ->values();

        DB::transaction(function () use ($routeItemGroup, $fillBy, $filterValue, $routeIds) {
            // Preserve the legacy NMWC workflow: clear route item groups only inside the selected scope.
            $this->routeScopedQuery($fillBy, $filterValue)
                ->where('route.routeitemgrpcode', $routeItemGroup)
                ->update(['routeitemgrpcode' => null]);

            if ($routeIds->isNotEmpty()) {
                DB::table('routemaster')
                    ->whereIn('routecode', $routeIds->all())
                    ->update(['routeitemgrpcode' => $routeItemGroup]);
            }
        });

        return redirect('/links/route-item-group')->with('success', 'Update Record');
    }

    private function validatedPayload(Request $request, bool $withRoutes): array
    {
        $rules = [
            'route_item_group' => ['required', 'integer', Rule::exists('routeitemgrp', 'routeitemgrpcode')],
            'fill_by' => ['required', 'integer', Rule::in([
                self::FILL_BY_REGION,
                self::FILL_BY_DEPOT,
                self::FILL_BY_AREA,
            ])],
            'filter_value' => ['required', 'integer', 'min:0'],
        ];

        if ($withRoutes) {
            $rules['routes'] = ['array'];
            $rules['routes.*'] = ['integer', Rule::exists('routemaster', 'routecode')];
        }

        $data = $request->validate($rules);
        $this->assertFilterAccess($request, (int) $data['fill_by'], (int) $data['filter_value']);

        $fillBy = (int) $data['fill_by'];
        $filterValue = (int) $data['filter_value'];

        if ($filterValue > 0) {
            validator(
                ['filter_value' => $filterValue],
                ['filter_value' => [Rule::exists($this->filterTable($fillBy), $this->filterColumn($fillBy))]]
            )->validate();
        }

        return $data;
    }

    private function routeItemGroupOptions(): array
    {
        $query = DB::table('routeitemgrp');

        if (Schema::hasColumn('routeitemgrp', 'transferstatus')) {
            $query->where('transferstatus', 1);
        }

        return $query
            ->orderBy('routeitemgrpcode')
            ->get(['routeitemgrpcode', 'description'])
            ->map(fn ($record) => [
                'id' => (int) $record->routeitemgrpcode,
                'label' => trim($record->routeitemgrpcode . ' -- ' . ($record->description ?? '')),
            ])
            ->all();
    }

    private function fillByOptions(): array
    {
        return [
            ['id' => self::FILL_BY_REGION, 'label' => 'By Region'],
            ['id' => self::FILL_BY_DEPOT, 'label' => 'By Depot'],
            ['id' => self::FILL_BY_AREA, 'label' => 'By Area'],
        ];
    }

    private function regionOptions(): array
    {
        $query = DB::table('regionmaster');
        app(AccessScopeService::class)->scopeQuery(request()->user(), $query, 'region', 'regionmstcode');

        $options = $query
            ->orderBy('regionmstcode')
            ->get(['regionmstcode', 'regionmstname'])
            ->map(fn ($record) => [
                'id' => (int) $record->regionmstcode,
                'label' => trim($record->regionmstcode . ' -- ' . ($record->regionmstname ?? '')),
            ])
            ->all();

        array_unshift($options, ['id' => 0, 'label' => '--- ALL ---']);

        return $options;
    }

    private function depotOptions(): array
    {
        $query = DB::table('depotmaster');

        if (Schema::hasColumn('depotmaster', 'activestatus')) {
            $query->where('activestatus', 1);
        }

        app(AccessScopeService::class)->scopeQuery(request()->user(), $query, 'depot', 'depotcode');

        $options = $query
            ->orderBy('depotcode')
            ->get(['depotcode', 'depotname'])
            ->map(fn ($record) => [
                'id' => (int) $record->depotcode,
                'label' => trim($record->depotcode . ' -- ' . ($record->depotname ?? '')),
            ])
            ->all();

        array_unshift($options, ['id' => 0, 'label' => '--- ALL ---']);

        return $options;
    }

    private function areaOptions(): array
    {
        $query = DB::table('areamaster');

        if (Schema::hasColumn('areamaster', 'activestatus')) {
            $query->where('activestatus', 1);
        }

        app(AccessScopeService::class)->scopeQuery(request()->user(), $query, 'area', 'areacode');

        $options = $query
            ->orderBy('areacode')
            ->get(['areacode', 'areaname'])
            ->map(fn ($record) => [
                'id' => (int) $record->areacode,
                'label' => trim($record->areacode . ' -- ' . ($record->areaname ?? '')),
            ])
            ->all();

        array_unshift($options, ['id' => 0, 'label' => '--- ALL ---']);

        return $options;
    }

    private function routeQuery(int $fillBy, int $filterValue)
    {
        return $this->routeScopeQuery($fillBy, $filterValue)
            ->distinct()
            ->orderBy('route.routecode')
            ->select([
                'route.routecode as id',
                'route.routename',
            ]);
    }

    private function routeScopedQuery(int $fillBy, int $filterValue)
    {
        return $this->routeScopeQuery($fillBy, $filterValue)
            ->select('route.routecode');
    }

    private function routeScopeQuery(int $fillBy, int $filterValue)
    {
        $query = DB::table('routemaster as route')
            ->when(Schema::hasColumn('routemaster', 'routetmpl'), fn ($builder) => $builder->where('route.routetmpl', 0))
            ->when(Schema::hasColumn('routemaster', 'activestatus'), fn ($builder) => $builder->where('route.activestatus', 1));

        app(AccessScopeService::class)->scopeQuery(request()->user(), $query, 'route', 'route.routecode');

        switch ($fillBy) {
            case self::FILL_BY_REGION:
                if ($filterValue > 0) {
                    $query->where('route.regionmstcode', $filterValue);
                }
                break;

            case self::FILL_BY_DEPOT:
                $query->leftJoin('regionmaster as reg', 'reg.regionmstcode', '=', 'route.regionmstcode')
                    ->leftJoin('depotmaster as depot', 'depot.regionmstcode', '=', 'reg.regionmstcode');
                if ($filterValue > 0) {
                    $query->where('depot.depotcode', $filterValue);
                }
                break;

            case self::FILL_BY_AREA:
            default:
                $query->leftJoin('subareamaster as sub', 'sub.subareacode', '=', 'route.subareacode')
                    ->leftJoin('areamaster as armst', 'armst.areacode', '=', 'sub.areacode');
                if ($filterValue > 0) {
                    $query->where('armst.areacode', $filterValue);
                }
                break;
        }

        return $query;
    }

    private function transformRoutes(Collection $routes): array
    {
        return $routes
            ->map(fn ($route) => [
                'id' => (int) $route->id,
                'label' => trim($route->id . ' -- ' . ($route->routename ?? '')),
            ])
            ->values()
            ->all();
    }

    private function filterTable(int $fillBy): string
    {
        return match ($fillBy) {
            self::FILL_BY_REGION => 'regionmaster',
            self::FILL_BY_DEPOT => 'depotmaster',
            self::FILL_BY_AREA => 'areamaster',
        };
    }

    private function filterColumn(int $fillBy): string
    {
        return match ($fillBy) {
            self::FILL_BY_REGION => 'regionmstcode',
            self::FILL_BY_DEPOT => 'depotcode',
            self::FILL_BY_AREA => 'areacode',
        };
    }

    private function hasRequiredTables(): bool
    {
        return Schema::hasTable('routemaster')
            && Schema::hasTable('routeitemgrp')
            && Schema::hasTable('regionmaster')
            && Schema::hasTable('depotmaster')
            && Schema::hasTable('areamaster')
            && Schema::hasTable('subareamaster')
            && Schema::hasColumn('routemaster', 'routeitemgrpcode');
    }

    private function assertFilterAccess(Request $request, int $fillBy, int $filterValue): void
    {
        if ($filterValue <= 0) {
            return;
        }

        $level = match ($fillBy) {
            self::FILL_BY_REGION => 'region',
            self::FILL_BY_DEPOT => 'depot',
            default => 'area',
        };

        abort_unless(app(AccessScopeService::class)->allows($request->user(), $level, $filterValue), 403);
    }
}
