<?php

namespace App\Http\Controllers\Scheme;

use App\Http\Controllers\Controller;
use App\Models\LoyaltyPlanDetail;
use App\Models\LoyaltyPlanHeader;
use App\Models\ProductGroupHeader;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class LoyaltyPlanController extends Controller
{
    public function index(): Response
    {
        $search = request('search');
        $perPage = (int) request('per_page', 10);
        $perPage = in_array($perPage, [10, 25, 50, 100], true) ? $perPage : 10;

        if ($this->hasTables()) {
            $plans = LoyaltyPlanHeader::query()
                ->when($search, function ($query, $searchTerm) {
                    $query->where(function ($inner) use ($searchTerm) {
                        $inner->where('loyaltyplanid', 'like', '%' . $searchTerm . '%')
                            ->orWhere('description', 'like', '%' . $searchTerm . '%')
                            ->orWhere('arbdescription', 'like', '%' . $searchTerm . '%')
                            ->orWhere('remarks', 'like', '%' . $searchTerm . '%');
                    });
                })
                ->orderBy('loyaltyplanid')
                ->paginate($perPage)
                ->withQueryString();
        } else {
            $plans = new LengthAwarePaginator([], 0, $perPage, 1, [
                'path' => request()->url(),
                'query' => request()->query(),
            ]);
        }

        return Inertia::render('scheme/loyalty-plan/Index', [
            'available' => $this->hasTables(),
            'filters' => [
                'search' => $search,
                'per_page' => $perPage,
            ],
            'plans' => $plans,
            'optionSets' => $this->optionSets(),
        ]);
    }

    public function create(): Response
    {
        abort_unless($this->hasTables(), 404);

        return Inertia::render('scheme/loyalty-plan/FormPage', [
            'mode' => 'create',
            'formMeta' => $this->formMeta(),
            'planData' => [
                'loyaltyplanid' => $this->nextPlanNumber(),
                'description' => '',
                'arbdescription' => '',
                'active' => 1,
                'remarks' => '',
                'items' => [],
            ],
        ]);
    }

    public function show(int $loyaltyPlan): Response
    {
        abort_unless($this->hasTables(), 404);

        return Inertia::render('scheme/loyalty-plan/FormPage', [
            'mode' => 'view',
            'formMeta' => $this->formMeta(),
            'planData' => $this->planData($loyaltyPlan),
        ]);
    }

    public function edit(int $loyaltyPlan): Response
    {
        abort_unless($this->hasTables(), 404);

        return Inertia::render('scheme/loyalty-plan/FormPage', [
            'mode' => 'edit',
            'formMeta' => $this->formMeta(),
            'planData' => $this->planData($loyaltyPlan),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        abort_unless($this->hasTables(), 404);

        $data = $this->validatedData($request);
        $username = auth()->user()?->username ?? auth()->user()?->name ?? 'system';

        $plan = DB::transaction(function () use ($data, $username) {
            $plan = LoyaltyPlanHeader::query()->create([
                'description' => $data['description'],
                'arbdescription' => $data['arbdescription'],
                'active' => $data['active'],
                'remarks' => $data['remarks'],
                'created' => $username,
                'cdat' => now(),
                'modified' => $username,
                'mdat' => now(),
            ]);

            $this->syncItems((int) $plan->loyaltyplanid, $data['items'], $username);

            return $plan;
        });

        return redirect("/scheme/loyalty/loyalty-plan/{$plan->loyaltyplanid}/edit")->with('success', 'Loyalty plan created.');
    }

    public function update(Request $request, int $loyaltyPlan): RedirectResponse
    {
        abort_unless($this->hasTables(), 404);

        $plan = LoyaltyPlanHeader::query()->findOrFail($loyaltyPlan);
        $data = $this->validatedData($request);
        $username = auth()->user()?->username ?? auth()->user()?->name ?? 'system';

        DB::transaction(function () use ($plan, $data, $username) {
            $plan->update([
                'description' => $data['description'],
                'arbdescription' => $data['arbdescription'],
                'active' => $data['active'],
                'remarks' => $data['remarks'],
                'modified' => $username,
                'mdat' => now(),
            ]);

            $this->syncItems((int) $plan->loyaltyplanid, $data['items'], $username);
        });

        return redirect("/scheme/loyalty/loyalty-plan/{$plan->loyaltyplanid}/edit")->with('success', 'Loyalty plan updated.');
    }

    public function destroy(int $loyaltyPlan): RedirectResponse
    {
        abort_unless($this->hasTables(), 404);

        if (Schema::hasTable('loyaltykeydetail') && DB::table('loyaltykeydetail')->where('loyaltyplanid', $loyaltyPlan)->exists()) {
            return back()->with('error', 'Cannot delete: record is in use.');
        }

        DB::transaction(function () use ($loyaltyPlan) {
            LoyaltyPlanDetail::query()->where('loyaltyplanid', $loyaltyPlan)->delete();
            LoyaltyPlanHeader::query()->where('loyaltyplanid', $loyaltyPlan)->delete();
        });

        return back()->with('success', 'Loyalty plan deleted.');
    }

    private function validatedData(Request $request): array
    {
        $data = $request->validate([
            'description' => ['required', 'string', 'max:50'],
            'arbdescription' => ['nullable', 'string', 'max:50'],
            'active' => ['required', 'integer', Rule::in([0, 1])],
            'remarks' => ['nullable', 'string', 'max:50'],
            'items' => ['nullable', 'array'],
            'items.*.qualificationgroup' => ['required', 'integer', Rule::exists('productgroupheader', 'groupnumber')],
            'items.*.type' => ['required', 'integer', Rule::in(array_keys($this->optionSets()['types']))],
            'items.*.value' => ['nullable', 'numeric', 'min:0'],
            'items.*.points' => ['nullable', 'numeric', 'min:0'],
        ]);

        $data['arbdescription'] = $data['arbdescription'] === '' ? null : $data['arbdescription'];
        $data['remarks'] = $data['remarks'] === '' ? null : $data['remarks'];
        $data['items'] = collect($data['items'] ?? [])
            ->map(fn ($item) => [
                'qualificationgroup' => (int) $item['qualificationgroup'],
                'qualificationlabel' => $item['qualificationlabel'] ?? null,
                'type' => (int) $item['type'],
                'value' => $item['value'] === '' ? null : $item['value'],
                'points' => $item['points'] === '' ? null : $item['points'],
            ])
            ->unique(fn ($item) => $item['qualificationgroup'] . ':' . $item['type'])
            ->values()
            ->all();

        return $data;
    }

    private function syncItems(int $planNumber, array $items, string $username): void
    {
        LoyaltyPlanDetail::query()->where('loyaltyplanid', $planNumber)->delete();

        if ($items === []) {
            return;
        }

        $rows = array_map(fn ($item) => [
            'loyaltyplanid' => $planNumber,
            'qualificationgroup' => $item['qualificationgroup'],
            'type' => $item['type'],
            'value' => $item['value'],
            'points' => $item['points'],
            'memo1' => null,
            'created' => $username,
            'cdat' => now(),
            'modified' => $username,
            'mdat' => now(),
        ], $items);

        LoyaltyPlanDetail::query()->insert($rows);
    }

    private function planData(int $planNumber): array
    {
        $plan = LoyaltyPlanHeader::query()->findOrFail($planNumber);
        $loyaltyPlanDetailRawAlias = DB::getTablePrefix() . 'loyaltyplandetail';
        $groupHeaderRawAlias = DB::getTablePrefix() . 'group_header';

        $items = LoyaltyPlanDetail::query()
            ->leftJoin('productgroupheader as group_header', 'group_header.groupnumber', '=', 'loyaltyplandetail.qualificationgroup')
            ->where('loyaltyplandetail.loyaltyplanid', $planNumber)
            ->orderBy('loyaltyplandetail.primarykey')
            ->get([
                'loyaltyplandetail.qualificationgroup',
                'loyaltyplandetail.type',
                'loyaltyplandetail.value',
                'loyaltyplandetail.points',
                DB::raw("COALESCE({$groupHeaderRawAlias}.groupdescription, {$loyaltyPlanDetailRawAlias}.qualificationgroup) as qualificationlabel"),
            ])
            ->map(fn ($item) => [
                'qualificationgroup' => (int) $item->qualificationgroup,
                'qualificationlabel' => (string) $item->qualificationlabel,
                'type' => (int) $item->type,
                'value' => $item->value,
                'points' => $item->points,
            ])
            ->all();

        return [
            'loyaltyplanid' => (int) $plan->loyaltyplanid,
            'description' => $plan->description,
            'arbdescription' => $plan->arbdescription,
            'active' => (int) $plan->active,
            'remarks' => $plan->remarks,
            'items' => $items,
        ];
    }

    private function hasTables(): bool
    {
        return Schema::hasTable('loyaltyplanheader') && Schema::hasTable('loyaltyplandetail');
    }

    private function nextPlanNumber(): int
    {
        return ((int) LoyaltyPlanHeader::query()->max('loyaltyplanid')) + 1;
    }

    private function optionSets(): array
    {
        return [
            'types' => [
                0 => 'Amount',
                1 => 'Qty',
            ],
            'statusLabels' => [
                0 => 'Inactive',
                1 => 'Active',
            ],
        ];
    }

    private function formMeta(): array
    {
        return [
            'indexUrl' => '/scheme/loyalty/loyalty-plan',
            'baseUrl' => '/scheme/loyalty/loyalty-plan',
            'subtitle' => 'Manage loyalty plans, qualification rules, and point values',
            'optionSets' => $this->optionSets(),
            'qualificationGroups' => ProductGroupHeader::query()
                ->where('grouptype', 1)
                ->orderBy('groupnumber')
                ->get([
                    'groupnumber as id',
                    DB::raw("CONCAT(groupnumber, ' - ', groupdescription) as label"),
                ]),
        ];
    }
}
