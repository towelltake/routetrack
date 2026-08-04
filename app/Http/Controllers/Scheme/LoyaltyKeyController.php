<?php

namespace App\Http\Controllers\Scheme;

use App\Http\Controllers\Controller;
use App\Models\LoyaltyKeyDetail;
use App\Models\LoyaltyKeyHeader;
use App\Models\LoyaltyPlanHeader;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class LoyaltyKeyController extends Controller
{
    public function index(): Response
    {
        $search = request('search');
        $perPage = (int) request('per_page', 10);
        $perPage = in_array($perPage, [10, 25, 50, 100], true) ? $perPage : 10;
        $loyaltyKeyDetailAlias = DB::getTablePrefix() . 'loyaltykeydetail';

        if ($this->hasTables()) {
            $keys = LoyaltyKeyHeader::query()
                ->leftJoin('loyaltykeydetail', 'loyaltykeydetail.loyaltykeyid', '=', 'loyaltykeyheader.loyaltykeyid')
                ->when($search, function ($query, $searchTerm) {
                    $query->where(function ($inner) use ($searchTerm) {
                        $inner->where('loyaltykeyheader.loyaltykeyid', 'like', '%' . $searchTerm . '%')
                            ->orWhere('loyaltykeyheader.description', 'like', '%' . $searchTerm . '%')
                            ->orWhere('loyaltykeyheader.arabicdescription', 'like', '%' . $searchTerm . '%')
                            ->orWhere('loyaltykeyheader.remarks', 'like', '%' . $searchTerm . '%');
                    });
                })
                ->groupBy(
                    'loyaltykeyheader.loyaltykeyid',
                    'loyaltykeyheader.description',
                    'loyaltykeyheader.arabicdescription',
                    'loyaltykeyheader.active',
                    'loyaltykeyheader.remarks'
                )
                ->orderBy('loyaltykeyheader.loyaltykeyid')
                ->select([
                    'loyaltykeyheader.loyaltykeyid',
                    'loyaltykeyheader.description',
                    'loyaltykeyheader.arabicdescription',
                    'loyaltykeyheader.active',
                    'loyaltykeyheader.remarks',
                    DB::raw("COUNT({$loyaltyKeyDetailAlias}.primarykey) as plan_count"),
                    DB::raw("MIN({$loyaltyKeyDetailAlias}.startdate) as first_startdate"),
                    DB::raw("MAX({$loyaltyKeyDetailAlias}.enddate) as last_enddate"),
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

        return Inertia::render('scheme/loyalty-key/Index', [
            'available' => $this->hasTables(),
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
        abort_unless($this->hasTables(), 404);

        return Inertia::render('scheme/loyalty-key/FormPage', [
            'mode' => 'create',
            'formMeta' => $this->formMeta(),
            'keyData' => [
                'loyaltykeyid' => $this->nextKeyNumber(),
                'description' => '',
                'arabicdescription' => '',
                'active' => 1,
                'remarks' => '',
                'assignedPlans' => [],
            ],
        ]);
    }

    public function show(int $loyaltyKey): Response
    {
        abort_unless($this->hasTables(), 404);

        return Inertia::render('scheme/loyalty-key/FormPage', [
            'mode' => 'view',
            'formMeta' => $this->formMeta($loyaltyKey),
            'keyData' => $this->keyData($loyaltyKey),
        ]);
    }

    public function edit(int $loyaltyKey): Response
    {
        abort_unless($this->hasTables(), 404);

        return Inertia::render('scheme/loyalty-key/FormPage', [
            'mode' => 'edit',
            'formMeta' => $this->formMeta($loyaltyKey),
            'keyData' => $this->keyData($loyaltyKey),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        abort_unless($this->hasTables(), 404);

        $data = $request->validate([
            'description' => ['required', 'string', 'max:50'],
            'arabicdescription' => ['nullable', 'string', 'max:50'],
            'active' => ['required', 'integer', Rule::in([0, 1])],
            'remarks' => ['nullable', 'string', 'max:50'],
        ]);

        $username = auth()->user()?->username ?? auth()->user()?->name ?? 'system';

        $key = LoyaltyKeyHeader::query()->create([
            'description' => $data['description'],
            'arabicdescription' => $data['arabicdescription'] === '' ? null : $data['arabicdescription'],
            'active' => $data['active'],
            'remarks' => $data['remarks'] === '' ? null : $data['remarks'],
            'created' => $username,
            'cdat' => now(),
            'modified' => $username,
            'mdat' => now(),
        ]);

        return redirect("/scheme/loyalty/loyalty-key/{$key->loyaltykeyid}/edit")->with('success', 'Loyalty key created.');
    }

    public function update(Request $request, int $loyaltyKey): RedirectResponse
    {
        abort_unless($this->hasTables(), 404);

        $header = LoyaltyKeyHeader::query()->findOrFail($loyaltyKey);
        $username = auth()->user()?->username ?? auth()->user()?->name ?? 'system';

        $data = $request->validate([
            'description' => ['required', 'string', 'max:50'],
            'arabicdescription' => ['nullable', 'string', 'max:50'],
            'active' => ['required', 'integer', Rule::in([0, 1])],
            'remarks' => ['nullable', 'string', 'max:50'],
            'selected_plan_ids' => ['nullable', 'array'],
            'selected_plan_ids.*' => ['integer', Rule::exists('loyaltyplanheader', 'loyaltyplanid')],
            'assigned_plans' => ['nullable', 'array'],
            'assigned_plans.*.primarykey' => ['nullable', 'integer'],
            'assigned_plans.*.loyaltyplanid' => ['required', 'integer', Rule::exists('loyaltyplanheader', 'loyaltyplanid')],
            'assigned_plans.*.startdate' => ['required', 'date'],
            'assigned_plans.*.enddate' => ['required', 'date'],
        ]);

        $selectedPlanIds = collect($data['selected_plan_ids'] ?? [])->map(fn ($id) => (int) $id)->unique()->values()->all();
        $assignedPlans = collect($data['assigned_plans'] ?? [])->map(fn ($row) => [
            'primarykey' => $row['primarykey'] ? (int) $row['primarykey'] : null,
            'loyaltyplanid' => (int) $row['loyaltyplanid'],
            'startdate' => $row['startdate'],
            'enddate' => $row['enddate'],
        ])->values()->all();

        DB::transaction(function () use ($header, $data, $selectedPlanIds, $assignedPlans, $username) {
            $header->update([
                'description' => $data['description'],
                'arabicdescription' => $data['arabicdescription'] === '' ? null : $data['arabicdescription'],
                'active' => $data['active'],
                'remarks' => $data['remarks'] === '' ? null : $data['remarks'],
                'modified' => $username,
                'mdat' => now(),
            ]);

            $this->syncAssignedPlans((int) $header->loyaltykeyid, $selectedPlanIds, $assignedPlans);
        });

        return redirect("/scheme/loyalty/loyalty-key/{$header->loyaltykeyid}/edit")->with('success', 'Loyalty key updated.');
    }

    public function destroy(int $loyaltyKey): RedirectResponse
    {
        abort_unless($this->hasTables(), 404);

        DB::transaction(function () use ($loyaltyKey) {
            LoyaltyKeyDetail::query()->where('loyaltykeyid', $loyaltyKey)->delete();
            LoyaltyKeyHeader::query()->where('loyaltykeyid', $loyaltyKey)->delete();
        });

        return back()->with('success', 'Loyalty key deleted.');
    }

    private function syncAssignedPlans(int $keyNumber, array $selectedPlanIds, array $assignedPlans): void
    {
        $existing = LoyaltyKeyDetail::query()
            ->where('loyaltykeyid', $keyNumber)
            ->get()
            ->keyBy(fn ($row) => (int) $row->loyaltyplanid);

        $keptPlanIds = [];

        foreach ($assignedPlans as $plan) {
            $planId = (int) $plan['loyaltyplanid'];
            $keptPlanIds[] = $planId;

            $detail = $existing->get($planId);
            if ($detail) {
                $detail->update([
                    'startdate' => $plan['startdate'],
                    'enddate' => $plan['enddate'],
                ]);

                continue;
            }

            LoyaltyKeyDetail::query()->create([
                'loyaltykeyid' => $keyNumber,
                'loyaltyplanid' => $planId,
                'startdate' => $plan['startdate'],
                'enddate' => $plan['enddate'],
                'memo1' => null,
            ]);
        }

        $newPlanIds = collect($selectedPlanIds)
            ->reject(fn ($id) => in_array((int) $id, $keptPlanIds, true))
            ->reject(fn ($id) => $existing->has((int) $id))
            ->values()
            ->all();

        foreach ($newPlanIds as $planId) {
            LoyaltyKeyDetail::query()->create([
                'loyaltykeyid' => $keyNumber,
                'loyaltyplanid' => $planId,
                'startdate' => now()->toDateString(),
                'enddate' => now()->toDateString(),
                'memo1' => null,
            ]);
        }

        $keptPlanIds = array_merge($keptPlanIds, $newPlanIds);

        if ($keptPlanIds === []) {
            LoyaltyKeyDetail::query()->where('loyaltykeyid', $keyNumber)->delete();
        } else {
            LoyaltyKeyDetail::query()
                ->where('loyaltykeyid', $keyNumber)
                ->whereNotIn('loyaltyplanid', $keptPlanIds)
                ->delete();
        }
    }

    private function keyData(int $keyNumber): array
    {
        $header = LoyaltyKeyHeader::query()->findOrFail($keyNumber);

        return [
            'loyaltykeyid' => (int) $header->loyaltykeyid,
            'description' => $header->description,
            'arabicdescription' => $header->arabicdescription,
            'active' => (int) $header->active,
            'remarks' => $header->remarks,
            'assignedPlans' => $this->assignedPlans($keyNumber),
        ];
    }

    private function assignedPlans(int $keyNumber): array
    {
        return LoyaltyKeyDetail::query()
            ->leftJoin('loyaltyplanheader', 'loyaltyplanheader.loyaltyplanid', '=', 'loyaltykeydetail.loyaltyplanid')
            ->where('loyaltykeydetail.loyaltykeyid', $keyNumber)
            ->orderBy('loyaltykeydetail.loyaltyplanid')
            ->get([
                'loyaltykeydetail.primarykey as primarykey',
                'loyaltykeydetail.loyaltyplanid as loyaltyplanid',
                'loyaltykeydetail.startdate as startdate',
                'loyaltykeydetail.enddate as enddate',
                'loyaltyplanheader.description as description',
                'loyaltyplanheader.arbdescription as arbdescription',
                'loyaltyplanheader.active as active',
            ])
            ->map(fn ($row) => [
                'primarykey' => (int) $row->primarykey,
                'loyaltyplanid' => (int) $row->loyaltyplanid,
                'description' => $row->description,
                'arbdescription' => $row->arbdescription,
                'startdate' => $this->formatDate($row->startdate),
                'enddate' => $this->formatDate($row->enddate),
                'active' => (int) $row->active,
            ])
            ->all();
    }

    private function availablePlans(?int $keyNumber = null): array
    {
        $assigned = [];
        if ($keyNumber) {
            $assigned = LoyaltyKeyDetail::query()
                ->where('loyaltykeyid', $keyNumber)
                ->pluck('loyaltyplanid')
                ->map(fn ($id) => (int) $id)
                ->all();
        }

        return LoyaltyPlanHeader::query()
            ->when($assigned !== [], fn ($query) => $query->whereNotIn('loyaltyplanid', $assigned))
            ->orderByDesc('loyaltyplanid')
            ->get(['loyaltyplanid', 'description', 'arbdescription', 'active'])
            ->map(fn ($plan) => [
                'id' => (int) $plan->loyaltyplanid,
                'description' => $plan->description,
                'arbdescription' => $plan->arbdescription,
                'active' => (int) $plan->active,
            ])
            ->all();
    }

    private function hasTables(): bool
    {
        return Schema::hasTable('loyaltykeyheader') && Schema::hasTable('loyaltykeydetail') && Schema::hasTable('loyaltyplanheader');
    }

    private function nextKeyNumber(): int
    {
        return ((int) LoyaltyKeyHeader::query()->max('loyaltykeyid')) + 1;
    }

    private function formatDate(mixed $value): string
    {
        if (! $value) {
            return '';
        }

        return Carbon::parse($value)->format('Y-m-d');
    }

    private function formMeta(?int $keyNumber = null): array
    {
        return [
            'indexUrl' => '/scheme/loyalty/loyalty-key',
            'baseUrl' => '/scheme/loyalty/loyalty-key',
            'subtitle' => 'Manage loyalty keys and assigned loyalty plans',
            'availablePlans' => $this->availablePlans($keyNumber),
            'supportsPlans' => Schema::hasTable('loyaltyplanheader'),
        ];
    }
}
