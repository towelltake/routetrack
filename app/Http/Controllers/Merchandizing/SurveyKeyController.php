<?php

namespace App\Http\Controllers\Merchandizing;

use App\Http\Controllers\Controller;
use App\Models\CustomerSurveyKey;
use App\Models\CustomerSurveyPlan;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class SurveyKeyController extends Controller
{
    public function index(): Response
    {
        $search = request('search');
        $perPage = (int) request('per_page', 10);
        $perPage = in_array($perPage, [10, 25, 50, 100], true) ? $perPage : 10;

        if ($this->hasTables()) {
            $keys = CustomerSurveyKey::query()
                ->leftJoin('customersurveyplan as survey_plan', 'survey_plan.surveyplankey', '=', 'customersurveykey.surveyplankey')
                ->when($search, function ($query, $searchTerm) {
                    $query->where(function ($inner) use ($searchTerm) {
                        $inner->where('customersurveykey.surveykey', 'like', '%' . $searchTerm . '%')
                            ->orWhere('customersurveykey.surveydescription', 'like', '%' . $searchTerm . '%')
                            ->orWhere('customersurveykey.arbsurveydescription', 'like', '%' . $searchTerm . '%')
                            ->orWhere('survey_plan.surveydescription', 'like', '%' . $searchTerm . '%');
                    });
                })
                ->orderBy('customersurveykey.surveykey')
                ->paginate($perPage, [
                    'customersurveykey.*',
                    'survey_plan.surveydescription as plan_description',
                ])
                ->withQueryString()
                ->through(fn ($key) => $this->transformKeyRow($key));
        } else {
            $keys = new LengthAwarePaginator([], 0, $perPage, 1, [
                'path' => request()->url(),
                'query' => request()->query(),
            ]);
        }

        return Inertia::render('merchandizing/survey-key/Index', [
            'available' => $this->hasTables(),
            'filters' => [
                'search' => $search,
                'per_page' => $perPage,
            ],
            'keys' => $keys,
            'formMeta' => $this->formMeta(),
        ]);
    }

    public function create(): Response
    {
        abort_unless($this->hasTables(), 404);

        return Inertia::render('merchandizing/survey-key/FormPage', [
            'mode' => 'create',
            'formMeta' => $this->formMeta(),
            'keyData' => [
                'surveykey' => $this->nextKeyNumber(),
                'surveydescription' => '',
                'arbsurveydescription' => '',
                'surveyplankey' => '',
                'activestatus' => 1,
                'items' => [],
            ],
        ]);
    }

    public function show(CustomerSurveyKey $surveyKey): Response
    {
        abort_unless($this->hasTables(), 404);

        return Inertia::render('merchandizing/survey-key/FormPage', [
            'mode' => 'view',
            'formMeta' => $this->formMeta(),
            'keyData' => $this->keyData($surveyKey),
        ]);
    }

    public function edit(CustomerSurveyKey $surveyKey): Response
    {
        abort_unless($this->hasTables(), 404);

        return Inertia::render('merchandizing/survey-key/FormPage', [
            'mode' => 'edit',
            'formMeta' => $this->formMeta(),
            'keyData' => $this->keyData($surveyKey),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        abort_unless($this->hasTables(), 404);

        $data = $this->validatedData($request);
        $username = auth()->user()?->username ?? auth()->user()?->name ?? 'system';

        $surveyKey = DB::transaction(function () use ($data, $username) {
            $surveyKey = CustomerSurveyKey::query()->create([
                'surveydescription' => $data['surveydescription'],
                'arbsurveydescription' => $data['arbsurveydescription'],
                'surveyplankey' => $data['surveyplankey'],
                'activestatus' => $data['activestatus'],
                'created' => $username,
                'cdat' => now(),
                'modified' => $username,
                'mdat' => now(),
            ]);

            $this->syncPlanSnapshot((int) $surveyKey->surveykey, (int) $data['surveyplankey'], $username);

            return $surveyKey;
        });

        return redirect("/merchandizing/survey-key/{$surveyKey->surveykey}/edit")->with('success', 'Survey key created.');
    }

    public function update(Request $request, CustomerSurveyKey $surveyKey): RedirectResponse
    {
        abort_unless($this->hasTables(), 404);

        $data = $this->validatedData($request);
        $username = auth()->user()?->username ?? auth()->user()?->name ?? 'system';

        DB::transaction(function () use ($surveyKey, $data, $username) {
            $surveyKey->update([
                'surveydescription' => $data['surveydescription'],
                'arbsurveydescription' => $data['arbsurveydescription'],
                'surveyplankey' => $data['surveyplankey'],
                'activestatus' => $data['activestatus'],
                'modified' => $username,
                'mdat' => now(),
            ]);

            $this->syncPlanSnapshot((int) $surveyKey->surveykey, (int) $data['surveyplankey'], $username);
        });

        return redirect("/merchandizing/survey-key/{$surveyKey->surveykey}/edit")->with('success', 'Survey key updated.');
    }

    public function destroy(CustomerSurveyKey $surveyKey): RedirectResponse
    {
        abort_unless($this->hasTables(), 404);

        DB::transaction(function () use ($surveyKey) {
            DB::table('customersurveykeyplan')->where('surveykey', $surveyKey->surveykey)->delete();
            DB::table('customersurveycontrol')->where('SurveyKey', $surveyKey->surveykey)->delete();
            DB::table('customersurveycontrolheader')->where('SurveyKey', $surveyKey->surveykey)->delete();
            $surveyKey->delete();
        });

        return back()->with('success', 'Survey key deleted.');
    }

    private function validatedData(Request $request): array
    {
        $data = $request->validate([
            'surveydescription' => ['required', 'string', 'max:50'],
            'arbsurveydescription' => ['nullable', 'string', 'max:200'],
            'surveyplankey' => ['required', 'integer', Rule::exists('customersurveyplan', 'surveyplankey')],
            'activestatus' => ['required', 'integer', Rule::in([0, 1])],
        ]);

        $data['arbsurveydescription'] = $data['arbsurveydescription'] === '' ? null : $data['arbsurveydescription'];

        return $data;
    }

    private function syncPlanSnapshot(int $surveyKey, int $surveyPlanKey, string $username): void
    {
        $plan = CustomerSurveyPlan::query()->findOrFail($surveyPlanKey);

        DB::table('customersurveykeyplan')->where('surveykey', $surveyKey)->delete();
        DB::table('customersurveykeyplan')->insert([
            'surveyplankey' => $surveyPlanKey,
            'surveykey' => $surveyKey,
            'created' => $username,
            'cdat' => now(),
            'modified' => $username,
            'mdat' => now(),
        ]);

        DB::table('customersurveycontrolheader')->where('SurveyKey', $surveyKey)->delete();
        DB::table('customersurveycontrolheader')->insert([
            'SurveyKey' => $surveyKey,
            'SurveyDescription' => $plan->surveydescription,
            'ArbSurveyDescription' => $plan->arbsurveydescription,
            'Created' => $username,
            'CDat' => now(),
            'Modified' => $username,
            'MDat' => now(),
            'activestatus' => 1,
        ]);

        DB::table('customersurveycontrol')->where('SurveyKey', $surveyKey)->delete();

        $definitions = DB::table('customersurveydefassign as assign')
            ->join('customersurveydefinition as definition', 'definition.surveydefkey', '=', 'assign.surveydefkey')
            ->where('assign.surveyplankey', $surveyPlanKey)
            ->orderBy('definition.surveyindex')
            ->orderBy('definition.surveydefkey')
            ->get([
                'definition.surveyindex',
                'definition.surveyprompt',
                'definition.arbsurveyprompt',
            ]);

        if ($definitions->isEmpty()) {
            return;
        }

        $nextIndex = ((int) DB::table('customersurveycontrol')->max('SurveyIndex')) + 1;
        $rows = [];

        foreach ($definitions as $definition) {
            $rows[] = [
                'SurveyIndex' => $nextIndex++,
                'SurveyKey' => $surveyKey,
                'SurveySequenceNumber' => $definition->surveyindex,
                'SurveyMandatory' => $plan->surveymandatory,
                'SurveyDescription' => $definition->surveyprompt,
                'ArbSurveyDescription' => $definition->arbsurveyprompt,
            ];
        }

        DB::table('customersurveycontrol')->insert($rows);
    }

    private function keyData(CustomerSurveyKey $surveyKey): array
    {
        $plan = CustomerSurveyPlan::query()->find($surveyKey->surveyplankey);

        $items = DB::table('customersurveycontrol')
            ->where('SurveyKey', $surveyKey->surveykey)
            ->orderBy('SurveySequenceNumber')
            ->orderBy('SurveyIndex')
            ->get()
            ->map(fn ($item) => [
                'surveyindex' => (int) $item->SurveyIndex,
                'surveysequencenumber' => $item->SurveySequenceNumber !== null ? (int) $item->SurveySequenceNumber : null,
                'surveymandatory' => (int) ($item->SurveyMandatory ?? 0),
                'surveydescription' => $item->SurveyDescription ?? '',
                'arbsurveydescription' => $item->ArbSurveyDescription ?? '',
            ])
            ->all();

        return [
            'surveykey' => (int) $surveyKey->surveykey,
            'surveydescription' => $surveyKey->surveydescription ?? '',
            'arbsurveydescription' => $surveyKey->arbsurveydescription ?? '',
            'surveyplankey' => $surveyKey->surveyplankey !== null ? (int) $surveyKey->surveyplankey : '',
            'surveyplanlabel' => $plan ? trim(collect([$plan->surveyplankey, $plan->surveydescription])->implode(' - ')) : '',
            'activestatus' => (int) ($surveyKey->activestatus ?? 1),
            'items' => $items,
        ];
    }

    private function transformKeyRow($surveyKey): array
    {
        return [
            'surveykey' => (int) $surveyKey->surveykey,
            'surveydescription' => $surveyKey->surveydescription ?? '',
            'plan_description' => $surveyKey->plan_description ?? '',
            'activestatus' => (int) ($surveyKey->activestatus ?? 1),
        ];
    }

    private function hasTables(): bool
    {
        return Schema::hasTable('customersurveykey')
            && Schema::hasTable('customersurveyplan')
            && Schema::hasTable('customersurveycontrolheader')
            && Schema::hasTable('customersurveycontrol')
            && Schema::hasTable('customersurveydefassign');
    }

    private function nextKeyNumber(): int
    {
        return ((int) CustomerSurveyKey::query()->max('surveykey')) + 1;
    }

    private function formMeta(): array
    {
        return [
            'indexUrl' => '/merchandizing/survey-key',
            'baseUrl' => '/merchandizing/survey-key',
            'subtitle' => 'Maintain survey keys and apply survey plan snapshots',
            'surveyPlanOptions' => Schema::hasTable('customersurveyplan')
                ? CustomerSurveyPlan::query()
                    ->orderBy('surveyplankey')
                    ->get([
                        'surveyplankey',
                        'surveydescription',
                    ])
                    ->map(fn (CustomerSurveyPlan $plan) => [
                        'id' => (int) $plan->surveyplankey,
                        'label' => trim(collect([$plan->surveyplankey, $plan->surveydescription])->implode(' - ')),
                    ])
                : [],
        ];
    }
}
