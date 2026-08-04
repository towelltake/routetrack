<?php

namespace App\Http\Controllers\Scheme;

use App\Http\Controllers\Controller;
use App\Models\CountryMaster;
use App\Models\ItemMaster;
use App\Models\PricingDetail1;
use App\Models\PricingPlanHeader1;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class SpecialPricePlanController extends Controller
{
    public function index(): Response
    {
        $search = request('search');
        $perPage = (int) request('per_page', 10);
        $filterType = (int) request('type', 0);
        $perPage = in_array($perPage, [10, 25, 50, 100], true) ? $perPage : 10;

        if ($this->hasPlanTables()) {
            $plans = PricingPlanHeader1::query()
                ->when($filterType > 0, fn ($query) => $query->where('type', $filterType))
                ->when($search, function ($query, $searchTerm) {
                    $query->where(function ($inner) use ($searchTerm) {
                        $inner->where('customerpricingkey', 'like', '%' . $searchTerm . '%')
                            ->orWhere('description', 'like', '%' . $searchTerm . '%')
                            ->orWhere('arbdescription', 'like', '%' . $searchTerm . '%');
                    });
                })
                ->orderBy('customerpricingkey')
                ->paginate($perPage)
                ->withQueryString();
        } else {
            $plans = new LengthAwarePaginator([], 0, $perPage, 1, [
                'path' => request()->url(),
                'query' => request()->query(),
            ]);
        }

        return Inertia::render('scheme/special-price-plan/Index', [
            'available' => $this->hasPlanTables(),
            'filters' => [
                'search' => $search,
                'per_page' => $perPage,
                'type' => $filterType,
            ],
            'plans' => $plans,
            'optionSets' => $this->optionSets(),
        ]);
    }

    public function create(): Response
    {
        abort_unless($this->hasPlanTables(), 404);

        return Inertia::render('scheme/special-price-plan/FormPage', [
            'mode' => 'create',
            'formMeta' => $this->formMeta(),
            'planData' => [
                'customerpricingkey' => $this->nextPlanNumber(),
                'description' => '',
                'arbdescription' => '',
                'type' => 1,
                'active' => 1,
                'country' => '',
                'items' => [],
            ],
        ]);
    }

    public function show(int $specialPricePlan): Response
    {
        abort_unless($this->hasPlanTables(), 404);

        return Inertia::render('scheme/special-price-plan/FormPage', [
            'mode' => 'view',
            'formMeta' => $this->formMeta(),
            'planData' => $this->planData($specialPricePlan),
        ]);
    }

    public function edit(int $specialPricePlan): Response
    {
        abort_unless($this->hasPlanTables(), 404);

        return Inertia::render('scheme/special-price-plan/FormPage', [
            'mode' => 'edit',
            'formMeta' => $this->formMeta(),
            'planData' => $this->planData($specialPricePlan),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        abort_unless($this->hasPlanTables(), 404);

        $data = $this->validatedData($request);
        $username = auth()->user()?->username ?? auth()->user()?->name ?? 'system';

        $plan = DB::transaction(function () use ($data, $username) {
            $plan = PricingPlanHeader1::query()->create([
                'description' => $data['description'],
                'arbdescription' => $data['arbdescription'],
                'type' => $data['type'],
                'active' => $data['active'],
                'country' => $data['country'],
            ]);

            $this->syncItems((int) $plan->customerpricingkey, $data['items'], $username);

            return $plan;
        });

        return redirect("/scheme/special-price/pricing-plan/{$plan->customerpricingkey}/edit")->with('success', 'Pricing plan created.');
    }

    public function update(Request $request, int $specialPricePlan): RedirectResponse
    {
        abort_unless($this->hasPlanTables(), 404);

        $plan = PricingPlanHeader1::query()->findOrFail($specialPricePlan);
        $data = $this->validatedData($request);
        $username = auth()->user()?->username ?? auth()->user()?->name ?? 'system';

        DB::transaction(function () use ($plan, $data, $username) {
            $plan->update([
                'description' => $data['description'],
                'arbdescription' => $data['arbdescription'],
                'type' => $data['type'],
                'active' => $data['active'],
                'country' => $data['country'],
            ]);

            $this->syncItems((int) $plan->customerpricingkey, $data['items'], $username);
        });

        return redirect("/scheme/special-price/pricing-plan/{$plan->customerpricingkey}/edit")->with('success', 'Pricing plan updated.');
    }

    public function destroy(int $specialPricePlan): RedirectResponse
    {
        abort_unless($this->hasPlanTables(), 404);

        if (Schema::hasTable('customerpricing1') && DB::table('customerpricing1')->where('customerpricingkey', $specialPricePlan)->exists()) {
            return back()->with('error', 'Cannot delete: record is in use.');
        }

        DB::transaction(function () use ($specialPricePlan) {
            PricingDetail1::query()->where('customerpricingkey', $specialPricePlan)->delete();
            PricingPlanHeader1::query()->where('customerpricingkey', $specialPricePlan)->delete();
        });

        return back()->with('success', 'Pricing plan deleted.');
    }

    private function validatedData(Request $request): array
    {
        $data = $request->validate([
            'description' => ['required', 'string', 'max:50'],
            'arbdescription' => ['nullable', 'string', 'max:50'],
            'type' => ['required', 'integer', Rule::in(array_keys($this->optionSets()['types']))],
            'active' => ['required', 'integer', Rule::in([0, 1])],
            'country' => ['required', 'integer', Rule::exists('country', 'countrycode')],
            'items' => ['nullable', 'array'],
            'items.*.itemcode' => ['required', 'integer', Rule::exists('itemmaster', 'actualitemcode')],
            'items.*.unitspercase' => ['nullable', 'numeric', 'min:0'],
            'items.*.salesprice' => ['nullable', 'numeric', 'min:0'],
            'items.*.salescaseprice' => ['nullable', 'numeric', 'min:0'],
            'items.*.returnprice' => ['nullable', 'numeric', 'min:0'],
            'items.*.returncaseprice' => ['nullable', 'numeric', 'min:0'],
            'items.*.stdsalesunitprice' => ['nullable', 'numeric', 'min:0'],
            'items.*.stdreturnunitprice' => ['nullable', 'numeric', 'min:0'],
            'items.*.stdsalescaseprice' => ['nullable', 'numeric', 'min:0'],
            'items.*.stdreturncaseprice' => ['nullable', 'numeric', 'min:0'],
        ]);

        $data['arbdescription'] = $data['arbdescription'] === '' ? null : $data['arbdescription'];
        $data['items'] = collect($data['items'] ?? [])
            ->map(function ($item) {
                return [
                    'itemcode' => (int) $item['itemcode'],
                    'unitspercase' => $item['unitspercase'] === '' ? null : $item['unitspercase'],
                    'salesprice' => $item['salesprice'] === '' ? null : $item['salesprice'],
                    'salescaseprice' => $item['salescaseprice'] === '' ? null : $item['salescaseprice'],
                    'returnprice' => $item['returnprice'] === '' ? null : $item['returnprice'],
                    'returncaseprice' => $item['returncaseprice'] === '' ? null : $item['returncaseprice'],
                    'stdsalesunitprice' => $item['stdsalesunitprice'] === '' ? null : $item['stdsalesunitprice'],
                    'stdreturnunitprice' => $item['stdreturnunitprice'] === '' ? null : $item['stdreturnunitprice'],
                    'stdsalescaseprice' => $item['stdsalescaseprice'] === '' ? null : $item['stdsalescaseprice'],
                    'stdreturncaseprice' => $item['stdreturncaseprice'] === '' ? null : $item['stdreturncaseprice'],
                ];
            })
            ->unique('itemcode')
            ->values()
            ->all();

        return $data;
    }

    private function syncItems(int $planNumber, array $items, string $username): void
    {
        PricingDetail1::query()->where('customerpricingkey', $planNumber)->delete();

        if ($items === []) {
            return;
        }

        $rows = array_map(fn ($item) => [
            'customerpricingkey' => $planNumber,
            'itemcode' => $item['itemcode'],
            'unitspercase' => $item['unitspercase'],
            'salesprice' => $item['salesprice'],
            'salescaseprice' => $item['salescaseprice'],
            'returnprice' => $item['returnprice'],
            'returncaseprice' => $item['returncaseprice'],
            'stdsalesunitprice' => $item['stdsalesunitprice'],
            'stdreturnunitprice' => $item['stdreturnunitprice'],
            'stdsalescaseprice' => $item['stdsalescaseprice'],
            'stdreturncaseprice' => $item['stdreturncaseprice'],
            'created' => $username,
            'cdat' => now(),
            'modified' => $username,
            'mdat' => now(),
        ], $items);

        PricingDetail1::query()->insert($rows);
    }

    private function planData(int $planNumber): array
    {
        $plan = PricingPlanHeader1::query()->findOrFail($planNumber);
        $itemAlias = DB::getTablePrefix() . 'im';

        $items = PricingDetail1::query()
            ->leftJoin('itemmaster as im', 'im.actualitemcode', '=', 'pricingdetail1.itemcode')
            ->where('pricingdetail1.customerpricingkey', $planNumber)
            ->orderBy('pricingdetail1.itemcode')
            ->get([
                'pricingdetail1.itemcode',
                'pricingdetail1.unitspercase',
                'pricingdetail1.salesprice',
                'pricingdetail1.salescaseprice',
                'pricingdetail1.returnprice',
                'pricingdetail1.returncaseprice',
                'pricingdetail1.stdsalesunitprice',
                'pricingdetail1.stdreturnunitprice',
                'pricingdetail1.stdsalescaseprice',
                'pricingdetail1.stdreturncaseprice',
                DB::raw("CONCAT(COALESCE(NULLIF({$itemAlias}.alternatecode, ''), {$itemAlias}.actualitemcode), ' - ', {$itemAlias}.itemshortdescription) as itemlabel"),
            ])
            ->map(fn ($item) => [
                'itemcode' => (int) $item->itemcode,
                'itemlabel' => $item->itemlabel,
                'unitspercase' => $item->unitspercase,
                'salesprice' => $item->salesprice,
                'salescaseprice' => $item->salescaseprice,
                'returnprice' => $item->returnprice,
                'returncaseprice' => $item->returncaseprice,
                'stdsalesunitprice' => $item->stdsalesunitprice,
                'stdreturnunitprice' => $item->stdreturnunitprice,
                'stdsalescaseprice' => $item->stdsalescaseprice,
                'stdreturncaseprice' => $item->stdreturncaseprice,
            ])
            ->all();

        return [
            'customerpricingkey' => (int) $plan->customerpricingkey,
            'description' => $plan->description,
            'arbdescription' => $plan->arbdescription,
            'type' => (int) $plan->type,
            'active' => (int) $plan->active,
            'country' => (int) $plan->country,
            'items' => $items,
        ];
    }

    private function hasPlanTables(): bool
    {
        return Schema::hasTable('pricingplanheader1') && Schema::hasTable('pricingdetail1');
    }

    private function nextPlanNumber(): int
    {
        return ((int) PricingPlanHeader1::query()->max('customerpricingkey')) + 1;
    }

    private function optionSets(): array
    {
        return [
            'types' => [
                1 => 'Special Price',
                2 => 'Customer',
                3 => 'Salesman',
                4 => 'Depot',
            ],
            'statusLabels' => [
                0 => 'Inactive',
                1 => 'Active',
            ],
        ];
    }

    private function formMeta(): array
    {
        $items = ItemMaster::query()
            ->where('activeitem', 1)
            ->orderBy('itemshortdescription')
            ->get([
                'actualitemcode',
                'alternatecode',
                'itemshortdescription',
                'unitspercase',
                'caseprice',
                'returncaseprice',
                'defaultsalesprice',
                'defaultgoodreturnprice',
            ]);

        return [
            'indexUrl' => '/scheme/special-price/pricing-plan',
            'baseUrl' => '/scheme/special-price/pricing-plan',
            'subtitle' => 'Manage special price plans and item-level pricing details',
            'optionSets' => $this->optionSets(),
            'countries' => CountryMaster::query()
                ->orderBy('countryname')
                ->get([
                    'countrycode as id',
                    DB::raw("CONCAT(countrycode, ' - ', countryname) as label"),
                ]),
            'itemOptions' => $items->map(fn ($item) => [
                'id' => (int) $item->actualitemcode,
                'label' => trim(($item->alternatecode ?: $item->actualitemcode) . ' - ' . $item->itemshortdescription),
                'meta' => [
                    'unitspercase' => $item->unitspercase,
                    'stdsalescaseprice' => $item->caseprice,
                    'stdreturncaseprice' => $item->returncaseprice,
                    'stdsalesunitprice' => $item->defaultsalesprice,
                    'stdreturnunitprice' => $item->defaultgoodreturnprice,
                ],
            ]),
        ];
    }
}
