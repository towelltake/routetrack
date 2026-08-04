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

class SpecialPriceController extends Controller
{
    private const FILL_BY_REGION = 1;
    private const FILL_BY_DEPOT = 2;
    private const FILL_BY_AREA = 3;
    private const FILL_BY_ROUTE = 4;

    public function index(): Response
    {
        return Inertia::render('links/special-price/Index', [
            'available' => $this->hasRequiredTables(),
            'formMeta' => [
                'title' => 'Special Price',
                'subtitle' => 'Assign a pricing key to customers by selected region, depot, area, or route',
                'indexUrl' => '/links/special-price',
                'loadUrl' => '/links/special-price/load',
                'saveUrl' => '/links/special-price/save',
                'permission' => 'special price link',
            ],
            'optionSets' => [
                'pricingKeyOptions' => $this->pricingKeyOptions(),
                'fillByOptions' => $this->fillByOptions(),
                'regionOptions' => $this->regionOptions(),
                'depotOptions' => $this->depotOptions(),
                'areaOptions' => $this->areaOptions(),
                'routeOptions' => $this->routeOptions(),
                'categoryOptions' => $this->categoryOptions(),
            ],
        ]);
    }

    public function load(Request $request): JsonResponse
    {
        abort_unless($this->hasRequiredTables(), 404);

        $data = $this->validatedPayload($request, false);
        $useAlternateCode = $this->useAlternateCode();

        $allCustomers = $this->customerQuery(
            (int) $data['pricing_key'],
            (int) $data['fill_by'],
            (int) $data['filter_value'],
            (int) $data['category_id'],
            false
        )->get();

        $selectedCustomers = $this->customerQuery(
            (int) $data['pricing_key'],
            (int) $data['fill_by'],
            (int) $data['filter_value'],
            (int) $data['category_id'],
            true
        )->get();

        return response()->json([
            'customers' => $this->transformCustomers($allCustomers, $useAlternateCode),
            'selectedCustomers' => $this->transformCustomers($selectedCustomers, $useAlternateCode),
            'selectedCustomerIds' => $selectedCustomers->pluck('id')->map(fn ($value) => (int) $value)->all(),
        ]);
    }

    public function save(Request $request): RedirectResponse
    {
        abort_unless($this->hasRequiredTables(), 404);

        $data = $this->validatedPayload($request, true);

        $pricingKey = (int) $data['pricing_key'];
        $fillBy = (int) $data['fill_by'];
        $filterValue = (int) $data['filter_value'];
        $categoryId = (int) $data['category_id'];
        $customerIds = collect($data['customers'] ?? [])
            ->map(fn ($value) => (int) $value)
            ->unique()
            ->values();

        DB::transaction(function () use ($pricingKey, $fillBy, $filterValue, $categoryId, $customerIds) {
            // Match the legacy NMWC workflow: clear the pricing key only inside the selected filter scope.
            $this->customerScopeQuery($fillBy, $filterValue, $categoryId)
                ->where('cust.pricingkey', $pricingKey)
                ->update(['pricingkey' => null]);

            if ($customerIds->isNotEmpty()) {
                DB::table('customermaster')
                    ->whereIn('customercode', $customerIds->all())
                    ->update(['pricingkey' => $pricingKey]);
            }
        });

        return redirect('/links/special-price')->with('success', 'Update Record');
    }

    private function validatedPayload(Request $request, bool $withCustomers): array
    {
        $rules = [
            'pricing_key' => ['required', 'integer', Rule::exists('customerpricingplanheader1', 'pricingplankey')],
            'fill_by' => ['required', 'integer', Rule::in([
                self::FILL_BY_REGION,
                self::FILL_BY_DEPOT,
                self::FILL_BY_AREA,
                self::FILL_BY_ROUTE,
            ])],
            'filter_value' => ['required', 'integer', 'min:0'],
            'category_id' => ['required', 'integer', 'min:0'],
        ];

        if ($withCustomers) {
            $rules['customers'] = ['array'];
            $rules['customers.*'] = ['integer', Rule::exists('customermaster', 'customercode')];
        }

        $data = $request->validate($rules);
        $this->assertFilterAccess($request, (int) $data['fill_by'], (int) $data['filter_value']);

        $fillBy = (int) $data['fill_by'];
        $filterValue = (int) $data['filter_value'];
        $categoryId = (int) $data['category_id'];

        if ($filterValue > 0) {
            validator(
                ['filter_value' => $filterValue],
                ['filter_value' => [Rule::exists($this->filterTable($fillBy), $this->filterColumn($fillBy))]]
            )->validate();
        }

        if ($categoryId > 0) {
            validator(
                ['category_id' => $categoryId],
                ['category_id' => [Rule::exists('categorymaster', 'categoryid')]]
            )->validate();
        }

        return $data;
    }

    private function pricingKeyOptions(): array
    {
        $query = DB::table('customerpricingplanheader1');

        if (Schema::hasColumn('customerpricingplanheader1', 'activeindicator')) {
            $query->where('activeindicator', 1);
        }

        return $query
            ->orderBy('pricingplankey')
            ->get(['pricingplankey', 'description'])
            ->map(fn ($record) => [
                'id' => (int) $record->pricingplankey,
                'label' => trim($record->pricingplankey . ' -- ' . ($record->description ?? '')),
            ])
            ->all();
    }

    private function fillByOptions(): array
    {
        return [
            ['id' => self::FILL_BY_REGION, 'label' => 'By Region'],
            ['id' => self::FILL_BY_DEPOT, 'label' => 'By Depot'],
            ['id' => self::FILL_BY_AREA, 'label' => 'By Area'],
            ['id' => self::FILL_BY_ROUTE, 'label' => 'By Route'],
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

    private function routeOptions(): array
    {
        $query = DB::table('routemaster');

        if (Schema::hasColumn('routemaster', 'activestatus')) {
            $query->where('activestatus', 1);
        }

        if (Schema::hasColumn('routemaster', 'routetmpl')) {
            $query->where('routetmpl', 0);
        }

        app(AccessScopeService::class)->scopeQuery(request()->user(), $query, 'route', 'routecode');

        $options = $query
            ->orderBy('routecode')
            ->get(['routecode', 'routename'])
            ->map(fn ($record) => [
                'id' => (int) $record->routecode,
                'label' => trim($record->routecode . ' -- ' . ($record->routename ?? '')),
            ])
            ->all();

        array_unshift($options, ['id' => 0, 'label' => '--- ALL ---']);

        return $options;
    }

    private function categoryOptions(): array
    {
        $query = DB::table('categorymaster');

        if (Schema::hasColumn('categorymaster', 'activestatus')) {
            $query->where('activestatus', 1);
        }

        $options = $query
            ->orderBy('categoryid')
            ->get(['categoryid', 'categoryname'])
            ->map(fn ($record) => [
                'id' => (int) $record->categoryid,
                'label' => trim($record->categoryid . ' -- ' . ($record->categoryname ?? '')),
            ])
            ->all();

        array_unshift($options, ['id' => 0, 'label' => '--- ALL ---']);

        return $options;
    }

    private function customerQuery(int $pricingKey, int $fillBy, int $filterValue, int $categoryId, bool $selectedOnly)
    {
        $query = $this->customerScopeQuery($fillBy, $filterValue, $categoryId)
            ->distinct()
            ->orderBy('cust.customercode')
            ->select([
                'cust.customercode as id',
                'cust.alternatecode',
                'cust.customername',
            ]);

        if ($selectedOnly) {
            $query->where('cust.pricingkey', $pricingKey);
        }

        return $query;
    }

    private function customerScopeQuery(int $fillBy, int $filterValue, int $categoryId)
    {
        $query = DB::table('customermaster as cust')
            ->when(Schema::hasColumn('customermaster', 'activecustomer'), fn ($builder) => $builder->where('cust.activecustomer', 1))
            ->when(Schema::hasColumn('customermaster', 'templateindicator'), fn ($builder) => $builder->where('cust.templateindicator', 0))
            ->when($categoryId > 0, fn ($builder) => $builder->where('cust.customercategory', $categoryId));

        app(AccessScopeService::class)->scopeQuery(request()->user(), $query, 'route', 'cust.routecode');

        switch ($fillBy) {
            case self::FILL_BY_REGION:
                $query->leftJoin('routemaster as route', 'route.routecode', '=', 'cust.routecode');
                if ($filterValue > 0) {
                    $query->where('route.regionmstcode', $filterValue);
                }
                break;

            case self::FILL_BY_DEPOT:
                $query->leftJoin('routemaster as route', 'route.routecode', '=', 'cust.routecode')
                    ->leftJoin('regionmaster as reg', 'reg.regionmstcode', '=', 'route.regionmstcode')
                    ->leftJoin('depotmaster as depot', 'depot.regionmstcode', '=', 'reg.regionmstcode');
                if ($filterValue > 0) {
                    $query->where('depot.depotcode', $filterValue);
                }
                break;

            case self::FILL_BY_AREA:
                $query->leftJoin('routemaster as route', 'route.routecode', '=', 'cust.routecode')
                    ->leftJoin('subareamaster as sub', 'sub.subareacode', '=', 'route.subareacode')
                    ->leftJoin('areamaster as armst', 'armst.areacode', '=', 'sub.areacode');
                if ($filterValue > 0) {
                    $query->where('armst.areacode', $filterValue);
                }
                break;

            case self::FILL_BY_ROUTE:
            default:
                if ($filterValue > 0) {
                    $query->where('cust.routecode', $filterValue);
                }
                break;
        }

        return $query;
    }

    private function transformCustomers(Collection $customers, bool $useAlternateCode): array
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

    private function filterTable(int $fillBy): string
    {
        return match ($fillBy) {
            self::FILL_BY_REGION => 'regionmaster',
            self::FILL_BY_DEPOT => 'depotmaster',
            self::FILL_BY_AREA => 'areamaster',
            self::FILL_BY_ROUTE => 'routemaster',
        };
    }

    private function filterColumn(int $fillBy): string
    {
        return match ($fillBy) {
            self::FILL_BY_REGION => 'regionmstcode',
            self::FILL_BY_DEPOT => 'depotcode',
            self::FILL_BY_AREA => 'areacode',
            self::FILL_BY_ROUTE => 'routecode',
        };
    }

    private function useAlternateCode(): bool
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
            && Schema::hasTable('customerpricingplanheader1')
            && Schema::hasTable('categorymaster')
            && Schema::hasTable('routemaster')
            && Schema::hasTable('regionmaster')
            && Schema::hasTable('depotmaster')
            && Schema::hasTable('areamaster')
            && Schema::hasTable('subareamaster');
    }

    private function assertFilterAccess(Request $request, int $fillBy, int $filterValue): void
    {
        if ($filterValue <= 0) {
            return;
        }

        $level = match ($fillBy) {
            self::FILL_BY_REGION => 'region',
            self::FILL_BY_DEPOT => 'depot',
            self::FILL_BY_AREA => 'area',
            default => 'route',
        };

        abort_unless(app(AccessScopeService::class)->allows($request->user(), $level, $filterValue), 403);
    }
}
