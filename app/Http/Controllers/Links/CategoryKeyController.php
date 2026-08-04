<?php

namespace App\Http\Controllers\Links;

use App\Http\Controllers\Controller;
use App\Models\CustomerMaster;
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

class CategoryKeyController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('links/category-key/Index', [
            'available' => $this->hasRequiredTables(),
            'formMeta' => [
                'title' => 'Category Key',
                'subtitle' => 'Assign a customer category to multiple customers for a selected route',
                'indexUrl' => '/links/category-key',
                'loadUrl' => '/links/category-key/load',
                'saveUrl' => '/links/category-key/save',
                'permission' => 'category key',
            ],
            'optionSets' => [
                'categoryOptions' => $this->categoryOptions(),
                'routeOptions' => $this->routeOptions(),
            ],
        ]);
    }

    public function load(Request $request): JsonResponse
    {
        abort_unless($this->hasRequiredTables(), 404);

        $data = $request->validate([
            'category_id' => ['required', 'integer', Rule::exists('categorymaster', 'categoryid')],
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
        $allCustomers = $this->customerQuery($routeId)->get();
        $selectedCustomers = $this->customerQuery($routeId)
            ->where('customercategory', (int) $data['category_id'])
            ->get();

        return response()->json([
            'customers' => $this->transformCustomers($allCustomers, $useAlternateCode),
            'selectedCustomers' => $this->transformCustomers($selectedCustomers, $useAlternateCode),
            'selectedCustomerIds' => $selectedCustomers->pluck('id')->map(fn ($value) => (int) $value)->all(),
        ]);
    }

    public function save(Request $request): RedirectResponse
    {
        abort_unless($this->hasRequiredTables(), 404);

        $data = $request->validate([
            'category_id' => ['required', 'integer', Rule::exists('categorymaster', 'categoryid')],
            'route_id' => ['required', 'integer'],
            'customers' => ['array'],
            'customers.*' => ['integer', Rule::exists('customermaster', 'customercode')],
        ]);

        $categoryId = (int) $data['category_id'];
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

        DB::transaction(function () use ($categoryId, $routeId, $customerIds, $user) {
            // Preserve the legacy NMWC workflow exactly, including the route-based flush behavior.
            CustomerMaster::query()
                ->where('customercategory', $categoryId)
                ->where('routecode', $routeId)
                ->update(['customercategory' => 0]);

            if ($customerIds->isNotEmpty()) {
                CustomerMaster::query()
                    ->whereIn('customercode', $customerIds->all())
                    ->tap(fn ($query) => app(AccessScopeService::class)->scopeQuery($user, $query, 'route', 'routecode'))
                    ->update(['customercategory' => $categoryId]);
            }
        });

        return redirect('/links/category-key')->with('success', 'Update Record');
    }

    private function categoryOptions(): array
    {
        if (!Schema::hasTable('categorymaster')) {
            return [];
        }

        $query = DB::table('categorymaster');

        if (Schema::hasColumn('categorymaster', 'activestatus')) {
            $query->where('activestatus', 1);
        }

        return $query
            ->orderBy('categoryid')
            ->get(['categoryid', 'categoryname'])
            ->map(fn ($category) => [
                'id' => (int) $category->categoryid,
                'label' => trim($category->categoryid . ' -- ' . ($category->categoryname ?? '')),
            ])
            ->all();
    }

    protected function routeOptions(): array
    {
        if (!Schema::hasTable('routemaster')) {
            return [];
        }

        $query = DB::table('routemaster');

        if (Schema::hasColumn('routemaster', 'activestatus')) {
            $query->where('activestatus', 1);
        }

        if (Schema::hasColumn('routemaster', 'routetmpl')) {
            $query->where('routetmpl', 0);
        }

        app(AccessScopeService::class)->scopeQuery(request()->user(), $query, 'route', 'routecode');

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

    protected function customerQuery(int $routeId)
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

        app(AccessScopeService::class)->scopeQuery(request()->user(), $query, 'route', 'routecode');

        return $query;
    }

    protected function transformCustomers(Collection $customers, bool $useAlternateCode): array
    {
        return $customers
            ->map(function ($customer) use ($useAlternateCode) {
                $displayCode = $useAlternateCode && filled($customer->alternatecode)
                    ? $customer->alternatecode
                    : $customer->id;

                return [
                    'id' => (int) $customer->id,
                    'label' => trim($displayCode . ' -- ' . ($customer->customername ?? '')),
                ];
            })
            ->values()
            ->all();
    }

    protected function useAlternateCode(): bool
    {
        if (!Schema::hasTable('controlpanel')) {
            return false;
        }

        return (int) DB::table('controlpanel')
            ->where('flagname', 'Use Alternate Code')
            ->value('status') === 1;
    }

    private function hasRequiredTables(): bool
    {
        return Schema::hasTable('customermaster')
            && Schema::hasTable('categorymaster')
            && Schema::hasTable('routemaster');
    }

    protected function assertRouteFilterAccess(Request $request, int $routeId): void
    {
        if ($routeId > 0) {
            abort_unless(app(AccessScopeService::class)->allows($request->user(), 'route', $routeId), 403);
        }
    }
}
