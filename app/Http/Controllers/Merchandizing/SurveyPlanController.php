<?php

namespace App\Http\Controllers\Merchandizing;

use App\Http\Controllers\Controller;
use App\Models\CustomerSurveyDefinition;
use App\Models\CustomerSurveyPlan;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class SurveyPlanController extends Controller
{
    public function index(): Response
    {
        $search = request('search');
        $perPage = (int) request('per_page', 10);
        $perPage = in_array($perPage, [10, 25, 50, 100], true) ? $perPage : 10;

        if ($this->hasTables()) {
            $plans = CustomerSurveyPlan::query()
                ->when($search, function ($query, $searchTerm) {
                    $query->where(function ($inner) use ($searchTerm) {
                        $inner->where('surveyplankey', 'like', '%' . $searchTerm . '%')
                            ->orWhere('surveydescription', 'like', '%' . $searchTerm . '%')
                            ->orWhere('arbsurveydescription', 'like', '%' . $searchTerm . '%')
                            ->orWhere('remarks', 'like', '%' . $searchTerm . '%');
                    });
                })
                ->orderBy('surveyplankey')
                ->paginate($perPage)
                ->withQueryString()
                ->through(fn (CustomerSurveyPlan $plan) => $this->transformPlanRow($plan));
        } else {
            $plans = new LengthAwarePaginator([], 0, $perPage, 1, [
                'path' => request()->url(),
                'query' => request()->query(),
            ]);
        }

        return Inertia::render('merchandizing/survey-plan/Index', [
            'available' => $this->hasTables(),
            'filters' => [
                'search' => $search,
                'per_page' => $perPage,
            ],
            'plans' => $plans,
            'formMeta' => $this->formMeta(),
        ]);
    }

    public function create(): Response
    {
        abort_unless($this->hasTables(), 404);

        return Inertia::render('merchandizing/survey-plan/FormPage', [
            'mode' => 'create',
            'formMeta' => $this->formMeta(),
            'planData' => [
                'surveyplankey' => $this->nextPlanNumber(),
                'surveysequencenumber' => 0,
                'surveymandatory' => 0,
                'surveydescription' => '',
                'arbsurveydescription' => '',
                'remarks' => '',
                'items' => [],
            ],
        ]);
    }

    public function show(CustomerSurveyPlan $surveyPlan): Response
    {
        abort_unless($this->hasTables(), 404);

        return Inertia::render('merchandizing/survey-plan/FormPage', [
            'mode' => 'view',
            'formMeta' => $this->formMeta(),
            'planData' => $this->planData($surveyPlan),
        ]);
    }

    public function edit(CustomerSurveyPlan $surveyPlan): Response
    {
        abort_unless($this->hasTables(), 404);

        return Inertia::render('merchandizing/survey-plan/FormPage', [
            'mode' => 'edit',
            'formMeta' => $this->formMeta(),
            'planData' => $this->planData($surveyPlan),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        abort_unless($this->hasTables(), 404);

        $data = $this->validatedData($request);
        $username = auth()->user()?->username ?? auth()->user()?->name ?? 'system';

        $plan = DB::transaction(function () use ($data, $username) {
            $plan = CustomerSurveyPlan::query()->create([
                'surveysequencenumber' => $data['surveysequencenumber'],
                'surveymandatory' => $data['surveymandatory'],
                'surveydescription' => $data['surveydescription'],
                'arbsurveydescription' => $data['arbsurveydescription'],
                'remarks' => $data['remarks'],
                'created' => $username,
                'cdat' => now(),
                'modified' => $username,
                'mdat' => now(),
            ]);

            $this->syncItems((int) $plan->surveyplankey, $data['items'], $username);

            return $plan;
        });

        return redirect("/merchandizing/survey-plan/{$plan->surveyplankey}/edit")->with('success', 'Survey plan created.');
    }

    public function update(Request $request, CustomerSurveyPlan $surveyPlan): RedirectResponse
    {
        abort_unless($this->hasTables(), 404);

        $data = $this->validatedData($request);
        $username = auth()->user()?->username ?? auth()->user()?->name ?? 'system';

        DB::transaction(function () use ($surveyPlan, $data, $username) {
            $surveyPlan->update([
                'surveysequencenumber' => $data['surveysequencenumber'],
                'surveymandatory' => $data['surveymandatory'],
                'surveydescription' => $data['surveydescription'],
                'arbsurveydescription' => $data['arbsurveydescription'],
                'remarks' => $data['remarks'],
                'modified' => $username,
                'mdat' => now(),
            ]);

            $this->syncItems((int) $surveyPlan->surveyplankey, $data['items'], $username);
        });

        return redirect("/merchandizing/survey-plan/{$surveyPlan->surveyplankey}/edit")->with('success', 'Survey plan updated.');
    }

    public function destroy(CustomerSurveyPlan $surveyPlan): RedirectResponse
    {
        abort_unless($this->hasTables(), 404);

        if (Schema::hasTable('customersurveykeyplan')
            && DB::table('customersurveykeyplan')->where('surveyplankey', $surveyPlan->surveyplankey)->exists()) {
            return back()->with('error', 'Cannot delete: record is in use.');
        }

        DB::transaction(function () use ($surveyPlan) {
            DB::table('customersurveydefassign')->where('surveyplankey', $surveyPlan->surveyplankey)->delete();
            $surveyPlan->delete();
        });

        return back()->with('success', 'Survey plan deleted.');
    }

    private function validatedData(Request $request): array
    {
        $data = $request->validate([
            'surveysequencenumber' => ['required', 'integer', 'min:0'],
            'surveymandatory' => ['required', 'integer', Rule::in([0, 1])],
            'surveydescription' => ['required', 'string', 'max:50'],
            'arbsurveydescription' => ['nullable', 'string', 'max:200'],
            'remarks' => ['nullable', 'string', 'max:50'],
            'items' => ['nullable', 'array'],
            'items.*.surveydefkey' => ['required', 'integer', Rule::exists('customersurveydefinition', 'surveydefkey')],
        ]);

        $data['arbsurveydescription'] = $data['arbsurveydescription'] === '' ? null : $data['arbsurveydescription'];
        $data['remarks'] = $data['remarks'] === '' ? null : $data['remarks'];
        $data['items'] = collect($data['items'] ?? [])
            ->map(fn ($item) => ['surveydefkey' => (int) $item['surveydefkey']])
            ->unique('surveydefkey')
            ->values()
            ->all();

        return $data;
    }

    private function syncItems(int $planNumber, array $items, string $username): void
    {
        DB::table('customersurveydefassign')->where('surveyplankey', $planNumber)->delete();

        if ($items === []) {
            return;
        }

        $rows = array_map(fn ($item) => [
            'surveyplankey' => $planNumber,
            'surveydefkey' => $item['surveydefkey'],
            'created' => $username,
            'cdat' => now(),
            'modified' => $username,
            'mdat' => now(),
        ], $items);

        DB::table('customersurveydefassign')->insert($rows);
    }

    private function planData(CustomerSurveyPlan $plan): array
    {
        $items = DB::table('customersurveydefassign as assign')
            ->leftJoin('customersurveydefinition as definition', 'definition.surveydefkey', '=', 'assign.surveydefkey')
            ->where('assign.surveyplankey', $plan->surveyplankey)
            ->orderBy('definition.surveyindex')
            ->orderBy('definition.surveydefkey')
            ->get([
                'assign.surveydefkey',
                'definition.surveyindex',
                'definition.surveyprompt',
            ])
            ->map(fn ($item) => [
                'surveydefkey' => (int) $item->surveydefkey,
                'surveyindex' => $item->surveyindex !== null ? (int) $item->surveyindex : null,
                'surveyprompt' => $item->surveyprompt ?? '',
                'label' => trim(collect([$item->surveyindex, $item->surveyprompt])->filter(fn ($value) => $value !== null && $value !== '')->implode(' - ')),
            ])
            ->all();

        return [
            'surveyplankey' => (int) $plan->surveyplankey,
            'surveysequencenumber' => (int) ($plan->surveysequencenumber ?? 0),
            'surveymandatory' => (int) ($plan->surveymandatory ?? 0),
            'surveydescription' => $plan->surveydescription ?? '',
            'arbsurveydescription' => $plan->arbsurveydescription ?? '',
            'remarks' => $plan->remarks ?? '',
            'items' => $items,
        ];
    }

    private function transformPlanRow(CustomerSurveyPlan $plan): array
    {
        return [
            'surveyplankey' => (int) $plan->surveyplankey,
            'surveydescription' => $plan->surveydescription ?? '',
            'surveysequencenumber' => (int) ($plan->surveysequencenumber ?? 0),
            'surveymandatory' => (int) ($plan->surveymandatory ?? 0),
            'remarks' => $plan->remarks ?? '',
            'assigned_count' => Schema::hasTable('customersurveydefassign')
                ? DB::table('customersurveydefassign')->where('surveyplankey', $plan->surveyplankey)->count()
                : 0,
        ];
    }

    private function hasTables(): bool
    {
        return Schema::hasTable('customersurveyplan')
            && Schema::hasTable('customersurveydefassign')
            && Schema::hasTable('customersurveydefinition');
    }

    private function nextPlanNumber(): int
    {
        return ((int) CustomerSurveyPlan::query()->max('surveyplankey')) + 1;
    }

    private function formMeta(): array
    {
        return [
            'indexUrl' => '/merchandizing/survey-plan',
            'baseUrl' => '/merchandizing/survey-plan',
            'subtitle' => 'Maintain survey plans and linked survey definitions',
            'surveyDefinitionOptions' => Schema::hasTable('customersurveydefinition')
                ? CustomerSurveyDefinition::query()
                    ->where('activestatus', 1)
                    ->orderBy('surveyindex')
                    ->orderBy('surveydefkey')
                    ->get([
                        'surveydefkey',
                        'surveyindex',
                        'surveyprompt',
                    ])
                    ->map(fn (CustomerSurveyDefinition $definition) => [
                        'id' => (int) $definition->surveydefkey,
                        'label' => trim(collect([
                            $definition->surveyindex,
                            $definition->surveyprompt,
                        ])->filter(fn ($value) => $value !== null && $value !== '')->implode(' - ')),
                        'surveyindex' => $definition->surveyindex !== null ? (int) $definition->surveyindex : null,
                        'surveyprompt' => $definition->surveyprompt ?? '',
                    ])
                : [],
        ];
    }
}
