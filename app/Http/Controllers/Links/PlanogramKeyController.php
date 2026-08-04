<?php

namespace App\Http\Controllers\Links;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class PlanogramKeyController extends CategoryKeyController
{
    public function index(): Response
    {
        return Inertia::render('links/planogram-key/Index', [
            'available' => $this->hasPlanogramTables(),
            'formMeta' => [
                'title' => 'Planogram Key',
                'subtitle' => 'Assign a planogram key to multiple customers for a selected route',
                'indexUrl' => '/links/planogram-key',
                'loadUrl' => '/links/planogram-key/load',
                'saveUrl' => '/links/planogram-key/save',
                'permission' => 'planogram key',
            ],
            'optionSets' => [
                'planogramOptions' => $this->planogramOptions(),
                'routeOptions' => $this->planogramRouteOptions(),
            ],
        ]);
    }

    public function load(Request $request): JsonResponse
    {
        abort_unless($this->hasPlanogramTables(), 404);

        $data = $request->validate([
            'planogram_id' => ['required', 'integer', Rule::exists('visualheader', 'visualcode')],
            'route_id' => ['required', 'integer'],
        ]);

        $routeId = (int) $data['route_id'];
        $this->assertRouteFilterAccess($request, $routeId);

        if ($routeId !== 0) {
            validator(
                ['route_id' => $routeId],
                ['route_id' => [Rule::exists('routemaster', 'routecode')]]
            )->validate();
        }

        $useAlternateCode = $this->useAlternateCode();
        $allCustomers = $this->planogramCustomerQuery($routeId)->get();
        $selectedCustomers = $this->planogramCustomerQuery($routeId)
            ->where('visualcode', (int) $data['planogram_id'])
            ->get();

        return response()->json([
            'customers' => $this->transformCustomers($allCustomers, $useAlternateCode),
            'selectedCustomers' => $this->transformCustomers($selectedCustomers, $useAlternateCode),
            'selectedCustomerIds' => $selectedCustomers->pluck('id')->map(fn ($value) => (int) $value)->all(),
        ]);
    }

    public function save(Request $request): RedirectResponse
    {
        abort_unless($this->hasPlanogramTables(), 404);

        $data = $request->validate([
            'planogram_id' => ['required', 'integer', Rule::exists('visualheader', 'visualcode')],
            'route_id' => ['required', 'integer'],
            'customers' => ['array'],
            'customers.*' => ['integer', Rule::exists('customermaster', 'customercode')],
        ]);

        $planogramId = (int) $data['planogram_id'];
        $routeId = (int) $data['route_id'];
        $user = $request->user();
        $customerIds = collect($data['customers'] ?? [])
            ->map(fn ($value) => (int) $value)
            ->unique()
            ->values();
        $this->assertRouteFilterAccess($request, $routeId);

        if ($routeId !== 0) {
            validator(
                ['route_id' => $routeId],
                ['route_id' => [Rule::exists('routemaster', 'routecode')]]
            )->validate();
        }

        DB::transaction(function () use ($planogramId, $routeId, $customerIds, $user) {
            // Preserve the legacy NMWC workflow exactly, including the route-based flush behavior.
            DB::table('customermaster')
                ->where('visualcode', $planogramId)
                ->where('routecode', $routeId)
                ->update(['visualcode' => 0]);

            if ($customerIds->isNotEmpty()) {
                DB::table('customermaster')
                    ->whereIn('customercode', $customerIds->all())
                    ->tap(fn ($query) => app(\App\Services\AccessScopeService::class)->scopeQuery($user, $query, 'route', 'routecode'))
                    ->update(['visualcode' => $planogramId]);
            }
        });

        return redirect('/links/planogram-key')->with('success', 'Update Record');
    }

    private function planogramOptions(): array
    {
        return DB::table('visualheader')
            ->orderBy('visualcode')
            ->get(['visualcode', 'visualdescription'])
            ->map(fn ($record) => [
                'id' => (int) $record->visualcode,
                'label' => trim($record->visualcode . ' -- ' . ($record->visualdescription ?? '')),
            ])
            ->all();
    }

    private function planogramRouteOptions(): array
    {
        $query = DB::table('routemaster');

        if (Schema::hasColumn('routemaster', 'activestatus')) {
            $query->where('activestatus', 1);
        }

        if (Schema::hasColumn('routemaster', 'routetmpl')) {
            $query->where('routetmpl', 0);
        }

        app(\App\Services\AccessScopeService::class)->scopeQuery(request()->user(), $query, 'route', 'routecode');

        $routes = $query
            ->orderBy('routecode')
            ->get(['routecode', 'routename'])
            ->map(fn ($route) => [
                'id' => (int) $route->routecode,
                'label' => trim($route->routecode . ' -- ' . ($route->routename ?? '')),
            ])
            ->all();

        array_unshift($routes, ['id' => 0, 'label' => '--- ALL ---']);

        return $routes;
    }

    private function planogramCustomerQuery(int $routeId)
    {
        $query = DB::table('customermaster')
            ->when(Schema::hasColumn('customermaster', 'activecustomer'), fn ($query) => $query->where('activecustomer', 1))
            ->when(Schema::hasColumn('customermaster', 'templateindicator'), fn ($query) => $query->where('templateindicator', 0))
            ->when($routeId > 0, fn ($query) => $query->where('routecode', $routeId))
            ->orderBy('customercode')
            ->select([
                'customercode as id',
                'alternatecode',
                'customername',
            ]);

        app(\App\Services\AccessScopeService::class)->scopeQuery(request()->user(), $query, 'route', 'routecode');

        return $query;
    }

    private function hasPlanogramTables(): bool
    {
        return Schema::hasTable('customermaster')
            && Schema::hasTable('visualheader')
            && Schema::hasTable('routemaster')
            && Schema::hasColumn('customermaster', 'visualcode');
    }
}
