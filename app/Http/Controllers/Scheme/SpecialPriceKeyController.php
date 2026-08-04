<?php

namespace App\Http\Controllers\Scheme;

use App\Http\Controllers\Controller;
use App\Models\CustomerPricing1;
use App\Models\CustomerPricingPlanHeader1;
use App\Models\PricingPlanHeader1;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class SpecialPriceKeyController extends Controller
{
    public function index(): Response
    {
        $search = request('search');
        $perPage = (int) request('per_page', 10);
        $perPage = in_array($perPage, [10, 25, 50, 100], true) ? $perPage : 10;
        $customerPricingAlias = DB::getTablePrefix() . 'customerpricing1';

        if ($this->hasKeyTables()) {
            $keys = CustomerPricingPlanHeader1::query()
                ->leftJoin('customerpricing1', 'customerpricing1.pricingplankey', '=', 'customerpricingplanheader1.pricingplankey')
                ->when($search, function ($query, $searchTerm) {
                    $query->where(function ($inner) use ($searchTerm) {
                        $inner->where('customerpricingplanheader1.pricingplankey', 'like', '%' . $searchTerm . '%')
                            ->orWhere('customerpricingplanheader1.description', 'like', '%' . $searchTerm . '%')
                            ->orWhere('customerpricingplanheader1.arbdescription', 'like', '%' . $searchTerm . '%');
                    });
                })
                ->groupBy(
                    'customerpricingplanheader1.pricingplankey',
                    'customerpricingplanheader1.description',
                    'customerpricingplanheader1.arbdescription',
                    'customerpricingplanheader1.activeindicator'
                )
                ->orderBy('customerpricingplanheader1.pricingplankey')
                ->select([
                    'customerpricingplanheader1.pricingplankey',
                    'customerpricingplanheader1.description',
                    'customerpricingplanheader1.arbdescription',
                    'customerpricingplanheader1.activeindicator',
                    DB::raw("COUNT({$customerPricingAlias}.primary_key) as plan_count"),
                    DB::raw("MIN({$customerPricingAlias}.startdate) as first_startdate"),
                    DB::raw("MAX({$customerPricingAlias}.enddate) as last_enddate"),
                ])
                ->paginate($perPage)
                ->through(function ($record) {
                    $record->first_startdate = $this->formatDate($record->first_startdate);
                    $record->last_enddate = $this->formatDate($record->last_enddate);

                    return $record;
                })
                ->withQueryString();
        } else {
            $keys = new LengthAwarePaginator([], 0, $perPage, 1, [
                'path' => request()->url(),
                'query' => request()->query(),
            ]);
        }

        return Inertia::render('scheme/special-price-key/Index', [
            'available' => $this->hasKeyTables(),
            'filters' => [
                'search' => $search,
                'per_page' => $perPage,
            ],
            'keys' => $keys,
            'optionSets' => [
                'statusLabels' => [
                    0 => 'Inactive',
                    1 => 'Active',
                ],
            ],
        ]);
    }

    public function create(): Response
    {
        abort_unless($this->hasKeyTables(), 404);

        return Inertia::render('scheme/special-price-key/FormPage', [
            'mode' => 'create',
            'formMeta' => $this->formMeta(),
            'keyData' => [
                'pricingplankey' => $this->nextPricingKey(),
                'description' => '',
                'arbdescription' => '',
                'activeindicator' => 1,
                'assignedPlans' => [],
            ],
        ]);
    }

    public function show(int $specialPriceKey): Response
    {
        abort_unless($this->hasKeyTables(), 404);

        return Inertia::render('scheme/special-price-key/FormPage', [
            'mode' => 'view',
            'formMeta' => $this->formMeta($specialPriceKey),
            'keyData' => $this->keyFormData($specialPriceKey),
        ]);
    }

    public function edit(int $specialPriceKey): Response
    {
        abort_unless($this->hasKeyTables(), 404);

        return Inertia::render('scheme/special-price-key/FormPage', [
            'mode' => 'edit',
            'formMeta' => $this->formMeta($specialPriceKey),
            'keyData' => $this->keyFormData($specialPriceKey),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        abort_unless($this->hasKeyTables(), 404);

        $data = $request->validate([
            'description' => ['required', 'string', 'max:50'],
            'arbdescription' => ['nullable', 'string', 'max:50'],
            'activeindicator' => ['required', 'integer', Rule::in([0, 1])],
        ]);

        $key = CustomerPricingPlanHeader1::query()->create([
            'description' => $data['description'],
            'arbdescription' => $data['arbdescription'] === '' ? null : $data['arbdescription'],
            'activeindicator' => $data['activeindicator'],
        ]);

        return redirect("/scheme/special-price/pricing-key/{$key->pricingplankey}/edit")
            ->with('success', 'Pricing key created. Add pricing plans to complete the workflow.');
    }

    public function update(Request $request, int $specialPriceKey): RedirectResponse
    {
        abort_unless($this->hasKeyTables(), 404);

        $header = CustomerPricingPlanHeader1::query()->findOrFail($specialPriceKey);
        $username = auth()->user()?->username ?? auth()->user()?->name ?? 'system';

        $data = $request->validate([
            'description' => ['required', 'string', 'max:50'],
            'arbdescription' => ['nullable', 'string', 'max:50'],
            'activeindicator' => ['required', 'integer', Rule::in([0, 1])],
            'selected_plan_ids' => ['nullable', 'array'],
            'selected_plan_ids.*' => ['integer', Rule::exists('pricingplanheader1', 'customerpricingkey')],
            'assigned_plans' => ['nullable', 'array'],
            'assigned_plans.*.primary_key' => ['nullable', 'integer'],
            'assigned_plans.*.customerpricingkey' => ['required', 'integer', Rule::exists('pricingplanheader1', 'customerpricingkey')],
            'assigned_plans.*.startdate' => ['required', 'date'],
            'assigned_plans.*.enddate' => ['required', 'date'],
        ]);

        $assignedPlans = collect($data['assigned_plans'] ?? [])->map(fn ($row) => [
            'primary_key' => $row['primary_key'] ? (int) $row['primary_key'] : null,
            'customerpricingkey' => (int) $row['customerpricingkey'],
            'startdate' => $row['startdate'],
            'enddate' => $row['enddate'],
            'contractno' => $row['contractno'] ?? null,
        ])->values()->all();

        DB::transaction(function () use ($header, $data, $assignedPlans, $username) {
            $header->update([
                'description' => $data['description'],
                'arbdescription' => $data['arbdescription'] === '' ? null : $data['arbdescription'],
                'activeindicator' => $data['activeindicator'],
            ]);

            $this->syncAssignedPlans((int) $header->pricingplankey, $assignedPlans, (int) $data['activeindicator'], $username);
        });

        return redirect("/scheme/special-price/pricing-key/{$header->pricingplankey}/edit")
            ->with('success', 'Pricing key updated.');
    }

    public function destroy(int $specialPriceKey): RedirectResponse
    {
        abort_unless($this->hasKeyTables(), 404);

        if ($this->keyInUse($specialPriceKey)) {
            return back()->with('error', 'Cannot delete: record is in use.');
        }

        DB::transaction(function () use ($specialPriceKey) {
            CustomerPricing1::query()->where('pricingplankey', $specialPriceKey)->delete();
            CustomerPricingPlanHeader1::query()->where('pricingplankey', $specialPriceKey)->delete();
        });

        return back()->with('success', 'Pricing key deleted.');
    }

    private function syncAssignedPlans(int $pricingPlanKey, array $assignedPlans, int $activeIndicator, string $username): void
    {
        $existing = CustomerPricing1::query()
            ->where('pricingplankey', $pricingPlanKey)
            ->get()
            ->keyBy(fn ($row) => (int) $row->customerpricingkey);

        $assignedPlansByKey = collect($assignedPlans)
            ->keyBy(fn ($row) => (int) $row['customerpricingkey']);

        $keptPlanNumbers = $assignedPlansByKey->keys()->map(fn ($id) => (int) $id)->all();

        foreach ($assignedPlansByKey as $row) {
            $planKey = (int) $row['customerpricingkey'];

            $detail = $existing->get($planKey);
            if (! $detail) {
                continue;
            }

            $detail->update([
                'startdate' => $row['startdate'],
                'enddate' => $row['enddate'],
                'active' => $activeIndicator,
                'modified' => $username,
                'mdat' => now(),
            ]);
        }

        $newPlanIds = $assignedPlansByKey->keys()
            ->reject(fn ($id) => $existing->has((int) $id))
            ->map(fn ($id) => (int) $id)
            ->values();

        if ($newPlanIds->isNotEmpty()) {
            $plans = PricingPlanHeader1::query()
                ->whereIn('customerpricingkey', $newPlanIds->all())
                ->orderBy('customerpricingkey')
                ->get();

            foreach ($plans as $plan) {
                $assignedRow = $assignedPlansByKey->get((int) $plan->customerpricingkey, []);

                CustomerPricing1::query()->create([
                    'pricingplankey' => $pricingPlanKey,
                    'customerpricingkey' => $plan->customerpricingkey,
                    'description' => $plan->description,
                    'arbdescription' => $plan->arbdescription,
                    'startdate' => $assignedRow['startdate'] ?? now()->toDateString(),
                    'enddate' => $assignedRow['enddate'] ?? now()->addYear()->toDateString(),
                    'contractno' => $assignedRow['contractno'] ?? null,
                    'active' => $activeIndicator,
                    'created' => $username,
                    'cdat' => now(),
                    'modified' => $username,
                    'mdat' => now(),
                ]);
            }
        }

        if ($keptPlanNumbers === []) {
            CustomerPricing1::query()->where('pricingplankey', $pricingPlanKey)->delete();
        } else {
            CustomerPricing1::query()
                ->where('pricingplankey', $pricingPlanKey)
                ->whereNotIn('customerpricingkey', $keptPlanNumbers)
                ->delete();
        }
    }

    private function keyFormData(int $specialPriceKey): array
    {
        $header = CustomerPricingPlanHeader1::query()->findOrFail($specialPriceKey);

        return [
            'pricingplankey' => (int) $header->pricingplankey,
            'description' => $header->description,
            'arbdescription' => $header->arbdescription,
            'activeindicator' => (int) $header->activeindicator,
            'assignedPlans' => $this->assignedPlans($specialPriceKey),
        ];
    }

    private function assignedPlans(int $specialPriceKey): array
    {
        return CustomerPricing1::query()
            ->where('pricingplankey', $specialPriceKey)
            ->orderBy('customerpricingkey')
            ->get([
                'primary_key',
                'customerpricingkey',
                'description',
                'arbdescription',
                'startdate',
                'enddate',
                'contractno',
                'active',
            ])
            ->map(fn ($row) => [
                'primary_key' => (int) $row->primary_key,
                'customerpricingkey' => (int) $row->customerpricingkey,
                'description' => $row->description,
                'arbdescription' => $row->arbdescription,
                'startdate' => $this->formatDate($row->startdate),
                'enddate' => $this->formatDate($row->enddate),
                'contractno' => $row->contractno,
                'active' => (int) $row->active,
            ])
            ->all();
    }

    private function availablePlans(?int $pricingPlanKey = null): array
    {
        if (! Schema::hasTable('pricingplanheader1')) {
            return [];
        }

        $assigned = [];
        if ($pricingPlanKey) {
            $assigned = CustomerPricing1::query()
                ->where('pricingplankey', $pricingPlanKey)
                ->pluck('customerpricingkey')
                ->map(fn ($id) => (int) $id)
                ->all();
        }

        return PricingPlanHeader1::query()
            ->when($assigned !== [], fn ($query) => $query->whereNotIn('customerpricingkey', $assigned))
            ->orderByDesc('customerpricingkey')
            ->get(['customerpricingkey', 'description', 'arbdescription', 'type', 'active'])
            ->map(fn ($plan) => [
                'id' => (int) $plan->customerpricingkey,
                'label' => $plan->customerpricingkey . ' - ' . ($plan->description ?: '-'),
                'description' => $plan->description,
                'arbdescription' => $plan->arbdescription,
                'type' => (int) $plan->type,
                'typeLabel' => $this->planTypeLabel((int) $plan->type),
                'active' => (int) $plan->active,
            ])
            ->all();
    }

    private function keyInUse(int $pricingPlanKey): bool
    {
        if (Schema::hasTable('customermaster')) {
            if (DB::table('customermaster')->where('pricingkey', $pricingPlanKey)->exists()) {
                return true;
            }
        }

        return false;
    }

    private function hasKeyTables(): bool
    {
        return Schema::hasTable('customerpricingplanheader1') && Schema::hasTable('customerpricing1');
    }

    private function nextPricingKey(): int
    {
        return ((int) CustomerPricingPlanHeader1::query()->max('pricingplankey')) + 1;
    }

    private function formatDate(mixed $value): string
    {
        if (! $value) {
            return '';
        }

        return Carbon::parse($value)->format('Y-m-d');
    }

    private function formMeta(?int $pricingPlanKey = null): array
    {
        return [
            'indexUrl' => '/scheme/special-price/pricing-key',
            'baseUrl' => '/scheme/special-price/pricing-key',
            'subtitle' => 'Manage special price keys and assigned pricing plans',
            'availablePlans' => $this->availablePlans($pricingPlanKey),
            'supportsPlans' => Schema::hasTable('pricingplanheader1'),
        ];
    }

    private function planTypeLabel(int $type): string
    {
        return match ($type) {
            1 => 'Special Price',
            2 => 'Customer',
            3 => 'Salesman',
            4 => 'Depot',
            default => (string) $type,
        };
    }
}
