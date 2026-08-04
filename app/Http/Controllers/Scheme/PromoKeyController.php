<?php

namespace App\Http\Controllers\Scheme;

use App\Http\Controllers\Controller;
use App\Models\PromoKeyDetail;
use App\Models\PromoKeyHeader;
use App\Models\PromoPlanDetail;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class PromoKeyController extends Controller
{
    public function index(): Response
    {
        $search = request('search');
        $perPage = (int) request('per_page', 10);
        $perPage = in_array($perPage, [10, 25, 50, 100], true) ? $perPage : 10;

        if ($this->hasKeyTables()) {
            $keys = PromoKeyHeader::query()
                ->when($search, function ($query, $searchTerm) {
                    $query->where(function ($inner) use ($searchTerm) {
                        $inner->where('promokeyheader.promotionkey', 'like', '%' . $searchTerm . '%')
                            ->orWhere('promokeyheader.description', 'like', '%' . $searchTerm . '%')
                            ->orWhere('promokeyheader.arbdescription', 'like', '%' . $searchTerm . '%');
                    });
                })
                ->orderBy('promokeyheader.promotionkey')
                ->select([
                    'promokeyheader.promotionkey',
                    'promokeyheader.description',
                    'promokeyheader.arbdescription',
                    'promokeyheader.activeindicator',
                ])
                ->paginate($perPage)
                ->withQueryString();
        } else {
            $keys = new LengthAwarePaginator(
                [],
                0,
                $perPage,
                1,
                [
                    'path' => request()->url(),
                    'query' => request()->query(),
                ],
            );
        }

        return Inertia::render('scheme/promo-key/Index', [
            'available' => $this->hasKeyTables(),
            'filters' => [
                'search' => $search,
                'per_page' => $perPage,
            ],
            'keys' => $keys,
            'optionSets' => $this->optionSets(),
        ]);
    }

    public function create(): Response
    {
        abort_unless($this->hasKeyTables(), 404);

        return Inertia::render('scheme/promo-key/FormPage', [
            'mode' => 'create',
            'formMeta' => $this->formMeta(),
            'keyData' => [
                'promotionkey' => $this->nextPromotionKey(),
                'description' => '',
                'arbdescription' => '',
                'type' => 1,
                'activeindicator' => 1,
                'assignedPlans' => [],
            ],
        ]);
    }

    public function show(int $promoKey): Response
    {
        abort_unless($this->hasKeyTables(), 404);

        return Inertia::render('scheme/promo-key/FormPage', [
            'mode' => 'view',
            'formMeta' => $this->formMeta($promoKey),
            'keyData' => $this->keyFormData($promoKey),
        ]);
    }

    public function edit(int $promoKey): Response
    {
        abort_unless($this->hasKeyTables(), 404);

        return Inertia::render('scheme/promo-key/FormPage', [
            'mode' => 'edit',
            'formMeta' => $this->formMeta($promoKey),
            'keyData' => $this->keyFormData($promoKey),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        abort_unless($this->hasKeyTables(), 404);

        $data = $this->validatedHeader($request);
        $username = auth()->user()?->username ?? auth()->user()?->name ?? 'system';

        $key = PromoKeyHeader::query()->create([
            'description' => $data['description'],
            'arbdescription' => $data['arbdescription'],
            'activeindicator' => $data['activeindicator'],
            'type' => $data['type'],
            'created' => $username,
            'cdat' => now(),
            'modified' => $username,
            'mdat' => now(),
            'alternatecode' => '0',
        ]);

        return redirect("/scheme/promotion/promo-key/{$key->promotionkey}/edit")
            ->with('success', 'Promo key created. Add promo plans to complete the workflow.');
    }

    public function update(Request $request, int $promoKey): RedirectResponse
    {
        abort_unless($this->hasKeyTables(), 404);

        $header = PromoKeyHeader::query()->findOrFail($promoKey);
        $data = $this->validatedHeader($request);
        $planPayload = $this->validatedPlans($request);
        $username = auth()->user()?->username ?? auth()->user()?->name ?? 'system';

        DB::transaction(function () use ($header, $data, $planPayload, $username) {
            $header->update([
                'description' => $data['description'],
                'arbdescription' => $data['arbdescription'],
                'activeindicator' => $data['activeindicator'],
                'type' => $data['type'],
                'modified' => $username,
                'mdat' => now(),
            ]);

            $this->syncAssignedPlans((int) $header->promotionkey, $planPayload['selectedPlanIds'], $planPayload['assignedPlans'], $username);
        });

        return redirect("/scheme/promotion/promo-key/{$header->promotionkey}/edit")
            ->with('success', 'Promo key updated.');
    }

    public function destroy(int $promoKey): RedirectResponse
    {
        abort_unless($this->hasKeyTables(), 404);

        if ($this->keyInUse($promoKey)) {
            return back()->with('error', 'Cannot delete: record is in use.');
        }

        DB::transaction(function () use ($promoKey) {
            if (Schema::hasTable('promokeydetail')) {
                PromoKeyDetail::query()->where('promotionkey', $promoKey)->delete();
            }

            PromoKeyHeader::query()->where('promotionkey', $promoKey)->delete();
        });

        return back()->with('success', 'Promo key deleted.');
    }

    private function validatedHeader(Request $request): array
    {
        $rules = [
            'description' => ['required', 'string', 'max:50'],
            'arbdescription' => ['nullable', 'string', 'max:50'],
            'type' => ['required', 'integer', Rule::in(array_keys($this->optionSets()['promotionTypes']))],
            'activeindicator' => ['required', 'integer', Rule::in([0, 1])],
        ];

        $data = $request->validate($rules);
        $data['arbdescription'] = $data['arbdescription'] === '' ? null : $data['arbdescription'];

        return $data;
    }

    private function validatedPlans(Request $request): array
    {
        $validated = $request->validate([
            'selected_plan_ids' => ['nullable', 'array'],
            'selected_plan_ids.*' => ['integer', Rule::exists('promoplandetail', 'plannumber')],
            'assigned_plans' => ['nullable', 'array'],
            'assigned_plans.*.primary_key' => ['nullable', 'integer'],
            'assigned_plans.*.plannumber' => ['required', 'integer', Rule::exists('promoplandetail', 'plannumber')],
            'assigned_plans.*.startdate' => ['required', 'date'],
            'assigned_plans.*.enddate' => ['required', 'date'],
        ]);

        return [
            'selectedPlanIds' => collect($validated['selected_plan_ids'] ?? [])->map(fn ($id) => (int) $id)->unique()->values()->all(),
            'assignedPlans' => collect($validated['assigned_plans'] ?? [])
                ->map(fn ($row) => [
                    'primary_key' => $row['primary_key'] ? (int) $row['primary_key'] : null,
                    'plannumber' => (int) $row['plannumber'],
                    'startdate' => $row['startdate'],
                    'enddate' => $row['enddate'],
                ])
                ->values()
                ->all(),
        ];
    }

    private function syncAssignedPlans(int $promotionKey, array $selectedPlanIds, array $assignedPlans, string $username): void
    {
        $existing = PromoKeyDetail::query()
            ->where('promotionkey', $promotionKey)
            ->get()
            ->keyBy(fn ($row) => (int) $row->plannumber);
        $planLookup = PromoPlanDetail::query()
            ->whereIn(
                'plannumber',
                collect($assignedPlans)
                    ->pluck('plannumber')
                    ->merge($selectedPlanIds)
                    ->map(fn ($id) => (int) $id)
                    ->unique()
                    ->values()
                    ->all()
            )
            ->get()
            ->keyBy(fn ($plan) => (int) $plan->plannumber);

        $keptPlanNumbers = [];

        foreach ($assignedPlans as $row) {
            $planNumber = (int) $row['plannumber'];
            $keptPlanNumbers[] = $planNumber;

            $detail = $existing->get($planNumber);
            if (! $detail) {
                $plan = $planLookup->get($planNumber);
                if ($plan) {
                    $this->createPromoKeyDetailFromPlan(
                        $promotionKey,
                        $plan,
                        $row['startdate'],
                        $row['enddate'],
                        $username
                    );
                }
                continue;
            }

            $detail->update([
                'startdate' => $row['startdate'],
                'enddate' => $row['enddate'],
                'modified' => $username,
                'mdat' => now(),
            ]);
        }

        $newPlanIds = collect($selectedPlanIds)
            ->reject(fn ($planNumber) => in_array((int) $planNumber, $keptPlanNumbers, true))
            ->reject(fn ($planNumber) => $existing->has((int) $planNumber))
            ->values();

        if ($newPlanIds->isNotEmpty()) {
            $plans = $planLookup
                ->only($newPlanIds->map(fn ($id) => (int) $id)->all())
                ->sortBy(fn ($plan) => (int) $plan->plannumber);

            foreach ($plans as $plan) {
                $this->createPromoKeyDetailFromPlan(
                    $promotionKey,
                    $plan,
                    now()->toDateString(),
                    now()->toDateString(),
                    $username
                );
            }

            $keptPlanNumbers = array_merge($keptPlanNumbers, $newPlanIds->map(fn ($id) => (int) $id)->all());
        }

        PromoKeyDetail::query()
            ->where('promotionkey', $promotionKey)
            ->when($keptPlanNumbers !== [], fn ($query) => $query->whereNotIn('promokeydetail.plannumber', $keptPlanNumbers))
            ->when($keptPlanNumbers === [], fn ($query) => $query)
            ->delete();
    }

    private function createPromoKeyDetailFromPlan(
        int $promotionKey,
        PromoPlanDetail $plan,
        string $startDate,
        string $endDate,
        string $username
    ): void {
        PromoKeyDetail::query()->create([
            'plannumber' => $plan->plannumber,
            'promotionkey' => $promotionKey,
            'startdate' => $startDate,
            'enddate' => $endDate,
            'promotiontypecode' => $plan->promotiontypecode,
            'qualificationgroup' => $plan->qualificationgroup,
            'assignmentgroup' => $plan->assignmentgroup,
            'assignmentnumber' => $plan->assignmentnumber,
            'performcriteriakey' => $plan->performcriteriakey ?? 0,
            'rangebasis' => $plan->rangebasis ?? 0,
            'amountbasis' => $plan->amountbasis ?? 0,
            'exclusionoption' => $plan->exclusionoption ?? 0,
            'active' => 1,
            'iscase' => $plan->iscase ?? 0,
            'created' => $username,
            'cdat' => now(),
            'modified' => $username,
            'mdat' => now(),
            'alternatecode' => '0',
            'divison' => 0,
            'memo1' => '0',
        ]);
    }

    private function keyFormData(int $promoKey): array
    {
        $header = PromoKeyHeader::query()->findOrFail($promoKey);

        return [
            'promotionkey' => (int) $header->promotionkey,
            'description' => $header->description,
            'arbdescription' => $header->arbdescription,
            'type' => (int) $header->type,
            'activeindicator' => (int) $header->activeindicator,
            'assignedPlans' => $this->assignedPlans($promoKey),
        ];
    }

    private function assignedPlans(int $promoKey): array
    {
        if (! Schema::hasTable('promokeydetail')) {
            return [];
        }

        $promoKeyDetailAlias = DB::getTablePrefix() . 'pkd';
        $promoPlanAlias = DB::getTablePrefix() . 'plan';
        $qualificationAlias = DB::getTablePrefix() . 'qualification';
        $assignmentAlias = DB::getTablePrefix() . 'assignment';

        return PromoKeyDetail::query()
            ->from('promokeydetail as pkd')
            ->where('pkd.promotionkey', $promoKey)
            ->join('promoplandetail as plan', 'plan.plannumber', '=', 'pkd.plannumber')
            ->leftJoin('productgroupheader as qualification', 'qualification.groupnumber', '=', 'pkd.qualificationgroup')
            ->leftJoin('productgroupheader as assignment', 'assignment.groupnumber', '=', 'pkd.assignmentgroup')
            ->orderBy('pkd.plannumber')
            ->get([
                DB::raw("{$promoKeyDetailAlias}.primary_key as primary_key"),
                DB::raw("{$promoKeyDetailAlias}.plannumber as plannumber"),
                DB::raw("{$promoKeyDetailAlias}.startdate as startdate"),
                DB::raw("{$promoKeyDetailAlias}.enddate as enddate"),
                DB::raw("{$promoKeyDetailAlias}.active as active"),
                DB::raw("{$promoPlanAlias}.plandescription as plandescription"),
                DB::raw("{$promoPlanAlias}.arbplandescription as arbplandescription"),
                DB::raw("CONCAT({$promoKeyDetailAlias}.qualificationgroup, ' - ', COALESCE({$qualificationAlias}.groupdescription, '')) as qualification_label"),
                DB::raw("CONCAT({$promoKeyDetailAlias}.assignmentgroup, ' - ', COALESCE({$assignmentAlias}.groupdescription, '')) as assignment_label"),
            ])
            ->map(fn ($row) => [
                'primary_key' => (int) $row->primary_key,
                'plannumber' => (int) $row->plannumber,
                'startdate' => $this->formatDate($row->startdate),
                'enddate' => $this->formatDate($row->enddate),
                'active' => (int) $row->active,
                'plandescription' => $row->plandescription,
                'arbplandescription' => $row->arbplandescription,
                'qualification_label' => $row->qualification_label,
                'assignment_label' => $row->assignment_label,
            ])
            ->all();
    }

    private function availablePlans(?int $promoKey = null): array
    {
        if (! Schema::hasTable('promoplandetail')) {
            return [];
        }

        $assigned = [];
        if ($promoKey && Schema::hasTable('promokeydetail')) {
            $assigned = PromoKeyDetail::query()
                ->where('promotionkey', $promoKey)
                ->pluck('plannumber')
                ->map(fn ($id) => (int) $id)
                ->all();
        }

        $promoPlanAlias = DB::getTablePrefix() . 'plan';
        $qualificationAlias = DB::getTablePrefix() . 'qualification';
        $assignmentAlias = DB::getTablePrefix() . 'assignment';

        return PromoPlanDetail::query()
            ->from('promoplandetail as plan')
            ->leftJoin('productgroupheader as qualification', 'qualification.groupnumber', '=', 'plan.qualificationgroup')
            ->leftJoin('productgroupheader as assignment', 'assignment.groupnumber', '=', 'plan.assignmentgroup')
            ->when($assigned !== [], fn ($query) => $query->whereNotIn('plan.plannumber', $assigned))
            ->orderBy('plan.plannumber')
            ->get([
                DB::raw("{$promoPlanAlias}.plannumber as plannumber"),
                DB::raw("{$promoPlanAlias}.plandescription as plandescription"),
                DB::raw("{$promoPlanAlias}.arbplandescription as arbplandescription"),
                DB::raw("CONCAT({$promoPlanAlias}.qualificationgroup, ' - ', COALESCE({$qualificationAlias}.groupdescription, '')) as qualification_label"),
                DB::raw("CONCAT({$promoPlanAlias}.assignmentgroup, ' - ', COALESCE({$assignmentAlias}.groupdescription, '')) as assignment_label"),
            ])
            ->map(fn ($plan) => [
                'id' => (int) $plan->plannumber,
                'plannumber' => (int) $plan->plannumber,
                'description' => $plan->plandescription,
                'arbdescription' => $plan->arbplandescription,
                'qualification_label' => $plan->qualification_label,
                'assignment_label' => $plan->assignment_label,
            ])
            ->all();
    }

    private function keyInUse(int $promoKey): bool
    {
        if (Schema::hasTable('customermaster')) {
            if (DB::table('customermaster')->where('promotionkey', $promoKey)->exists()) {
                return true;
            }
        }

        if (Schema::hasTable('promotioncontrol')) {
            if (DB::table('promotioncontrol')->where('promotionkey', $promoKey)->exists()) {
                return true;
            }
        }

        return false;
    }

    private function hasKeyTables(): bool
    {
        return Schema::hasTable('promokeyheader') && Schema::hasTable('promokeydetail');
    }

    private function nextPromotionKey(): int
    {
        return ((int) PromoKeyHeader::query()->max('promotionkey')) + 1;
    }

    private function formatDate(mixed $value): string
    {
        if (! $value) {
            return '';
        }

        return Carbon::parse($value)->format('Y-m-d');
    }

    private function optionSets(): array
    {
        return [
            'promotionTypes' => [
                1 => 'Standard Promotion',
                2 => 'Fixed Promotion',
            ],
            'statusLabels' => [
                0 => 'Inactive',
                1 => 'Active',
            ],
        ];
    }

    private function formMeta(?int $promoKey = null): array
    {
        return [
            'indexUrl' => '/scheme/promotion/promo-key',
            'baseUrl' => '/scheme/promotion/promo-key',
            'subtitle' => 'Maintain promo key records and attach promo plans using the legacy key workflow',
            'availablePlans' => $this->availablePlans($promoKey),
            'optionSets' => $this->optionSets(),
            'supportsPlans' => Schema::hasTable('promoplandetail'),
        ];
    }
}
