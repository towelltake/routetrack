<?php

namespace App\Http\Controllers\Merchandizing;

use App\Http\Controllers\Controller;
use App\Models\CustomerSurveyDefinition;
use App\Models\LookupIndexDetail;
use App\Models\LookupIndexHeader;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class SurveyController extends Controller
{
    public function index(): Response
    {
        $search = request('search');
        $perPage = (int) request('per_page', 10);
        $perPage = in_array($perPage, [10, 25, 50, 100], true) ? $perPage : 10;

        if ($this->hasSurveyTables()) {
            $surveys = CustomerSurveyDefinition::query()
                ->when($search, function ($query, $searchTerm) {
                    $query->where(function ($inner) use ($searchTerm) {
                        $inner->where('surveydefkey', 'like', '%' . $searchTerm . '%')
                            ->orWhere('surveyindex', 'like', '%' . $searchTerm . '%')
                            ->orWhere('surveyprompt', 'like', '%' . $searchTerm . '%')
                            ->orWhere('arbsurveyprompt', 'like', '%' . $searchTerm . '%');
                    });
                })
                ->orderBy('surveydefkey')
                ->paginate($perPage)
                ->withQueryString()
                ->through(fn (CustomerSurveyDefinition $survey) => $this->transformSurveyRow($survey));
        } else {
            $surveys = new LengthAwarePaginator([], 0, $perPage, 1, [
                'path' => request()->url(),
                'query' => request()->query(),
            ]);
        }

        return Inertia::render('merchandizing/survey/Index', [
            'available' => $this->hasSurveyTables(),
            'filters' => [
                'search' => $search,
                'per_page' => $perPage,
            ],
            'surveys' => $surveys,
            'formMeta' => $this->formMeta(),
        ]);
    }

    public function create(Request $request): Response
    {
        abort_unless($this->hasSurveyTables(), 404);

        return Inertia::render('merchandizing/survey/FormPage', [
            'mode' => 'create',
            'formMeta' => $this->formMeta(),
            'surveyData' => $this->draftSurveyData($request),
        ]);
    }

    public function show(CustomerSurveyDefinition $survey): Response
    {
        abort_unless($this->hasSurveyTables(), 404);

        return Inertia::render('merchandizing/survey/FormPage', [
            'mode' => 'view',
            'formMeta' => $this->formMeta(),
            'surveyData' => $this->surveyData($survey),
        ]);
    }

    public function edit(Request $request, CustomerSurveyDefinition $survey): Response
    {
        abort_unless($this->hasSurveyTables(), 404);

        $surveyData = $request->boolean('restore_lookup_context')
            ? $this->draftSurveyData($request, $survey)
            : $this->surveyData($survey);

        return Inertia::render('merchandizing/survey/FormPage', [
            'mode' => 'edit',
            'formMeta' => $this->formMeta(),
            'surveyData' => $surveyData,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        abort_unless($this->hasSurveyTables(), 404);

        $data = $this->validatedSurveyData($request);
        $username = auth()->user()?->username ?? auth()->user()?->name ?? 'system';

        CustomerSurveyDefinition::query()->create([
            'surveyindex' => $data['surveyindex'],
            'lineindex' => 0,
            'surveyrectype' => $data['surveyrectype'],
            'surveyprompt' => $data['surveyprompt'],
            'arbsurveyprompt' => $data['arbsurveyprompt'],
            'responselength' => $data['responselength'],
            'responsedecimalpos' => $data['responsedecimalpos'],
            'lookuptype' => $data['lookuptype'],
            'lookupindex' => $data['lookupindex'],
            'retainvalue' => 0,
            'activestatus' => $data['activestatus'],
            'created' => $username,
            'cdat' => now(),
            'modified' => $username,
            'mdat' => now(),
        ]);

        return redirect($this->formMeta()['indexUrl'])->with('success', 'Survey definition created.');
    }

    public function update(Request $request, CustomerSurveyDefinition $survey): RedirectResponse
    {
        abort_unless($this->hasSurveyTables(), 404);

        $data = $this->validatedSurveyData($request);
        $username = auth()->user()?->username ?? auth()->user()?->name ?? 'system';

        $survey->update([
            'surveyindex' => $data['surveyindex'],
            'surveyrectype' => $data['surveyrectype'],
            'surveyprompt' => $data['surveyprompt'],
            'arbsurveyprompt' => $data['arbsurveyprompt'],
            'responselength' => $data['responselength'],
            'responsedecimalpos' => $data['responsedecimalpos'],
            'lookuptype' => $data['lookuptype'],
            'lookupindex' => $data['lookupindex'],
            'activestatus' => $data['activestatus'],
            'modified' => $username,
            'mdat' => now(),
        ]);

        return redirect($this->formMeta()['indexUrl'])->with('success', 'Survey definition updated.');
    }

    public function destroy(CustomerSurveyDefinition $survey): RedirectResponse
    {
        abort_unless($this->hasSurveyTables(), 404);

        if (Schema::hasTable('customersurveydefassign')
            && DB::table('customersurveydefassign')->where('surveydefkey', $survey->surveydefkey)->exists()) {
            return back()->with('error', 'Cannot delete: record is in use.');
        }

        $survey->delete();

        return back()->with('success', 'Survey definition deleted.');
    }

    public function lookupIndexManagerCreate(Request $request): Response
    {
        abort_unless($this->hasLookupTables(), 404);

        return Inertia::render('merchandizing/survey/LookupIndexManager', [
            'mode' => 'create',
            'managerMeta' => $this->managerMeta(),
            'lookupHeader' => [
                'transactionkey' => $this->nextLookupIndexNumber(),
                'description' => '',
                'arbdescription' => '',
            ],
            'lookupDetails' => [],
            'returnContext' => $this->returnContext($request),
        ]);
    }

    public function lookupIndexManagerEdit(Request $request, LookupIndexHeader $lookupIndex): Response
    {
        abort_unless($this->hasLookupTables(), 404);

        return Inertia::render('merchandizing/survey/LookupIndexManager', [
            'mode' => 'edit',
            'managerMeta' => $this->managerMeta(),
            'lookupHeader' => [
                'transactionkey' => (int) $lookupIndex->transactionkey,
                'description' => $lookupIndex->description ?? '',
                'arbdescription' => $lookupIndex->arbdescription ?? '',
            ],
            'lookupDetails' => $this->lookupDetails((int) $lookupIndex->transactionkey),
            'returnContext' => $this->returnContext($request),
        ]);
    }

    public function lookupIndexManagerStore(Request $request): RedirectResponse
    {
        abort_unless($this->hasLookupTables(), 404);

        $data = $request->validate([
            'description' => ['required', 'string', 'max:50'],
            'arbdescription' => ['nullable', 'string', 'max:50'],
        ]);

        $username = auth()->user()?->username ?? auth()->user()?->name ?? 'system';

        $lookupIndex = LookupIndexHeader::query()->create([
            'description' => $data['description'],
            'arbdescription' => $data['arbdescription'] === '' ? null : $data['arbdescription'],
            'response' => 0,
            'created' => $username,
            'cdat' => now(),
            'modified' => $username,
            'mdat' => now(),
        ]);

        return redirect()->route('merchandizing.survey.lookup-index.edit', array_merge(
            ['lookupIndex' => $lookupIndex->transactionkey],
            $this->returnContext($request)
        ))->with('success', 'Lookup index created.');
    }

    public function lookupIndexManagerUpdate(Request $request, LookupIndexHeader $lookupIndex): RedirectResponse
    {
        abort_unless($this->hasLookupTables(), 404);

        $data = $request->validate([
            'description' => ['required', 'string', 'max:50'],
            'arbdescription' => ['nullable', 'string', 'max:50'],
        ]);

        $username = auth()->user()?->username ?? auth()->user()?->name ?? 'system';

        $lookupIndex->update([
            'description' => $data['description'],
            'arbdescription' => $data['arbdescription'] === '' ? null : $data['arbdescription'],
            'modified' => $username,
            'mdat' => now(),
        ]);

        return back()->with('success', 'Lookup index updated.');
    }

    public function lookupIndexDetailStore(Request $request, LookupIndexHeader $lookupIndex): RedirectResponse
    {
        abort_unless($this->hasLookupTables(), 404);

        $data = $request->validate([
            'description' => ['required', 'string', 'max:50'],
            'arbdescription' => ['nullable', 'string', 'max:50'],
        ]);

        $username = auth()->user()?->username ?? auth()->user()?->name ?? 'system';

        LookupIndexDetail::query()->create([
            'transactionkey' => $lookupIndex->transactionkey,
            'description' => $data['description'],
            'arbdescription' => $data['arbdescription'] === '' ? null : $data['arbdescription'],
            'created' => $username,
            'cdat' => now(),
            'modified' => $username,
            'mdat' => now(),
        ]);

        return back()->with('success', 'Lookup detail added.');
    }

    public function lookupIndexDetailUpdate(Request $request, LookupIndexHeader $lookupIndex, LookupIndexDetail $detail): RedirectResponse
    {
        abort_unless($this->hasLookupTables(), 404);
        abort_unless((int) $detail->transactionkey === (int) $lookupIndex->transactionkey, 404);

        $data = $request->validate([
            'description' => ['required', 'string', 'max:50'],
            'arbdescription' => ['nullable', 'string', 'max:50'],
        ]);

        $username = auth()->user()?->username ?? auth()->user()?->name ?? 'system';

        $detail->update([
            'description' => $data['description'],
            'arbdescription' => $data['arbdescription'] === '' ? null : $data['arbdescription'],
            'modified' => $username,
            'mdat' => now(),
        ]);

        return back()->with('success', 'Lookup detail updated.');
    }

    public function lookupIndexDetailDestroy(LookupIndexHeader $lookupIndex, LookupIndexDetail $detail): RedirectResponse
    {
        abort_unless($this->hasLookupTables(), 404);
        abort_unless((int) $detail->transactionkey === (int) $lookupIndex->transactionkey, 404);

        $detail->delete();

        return back()->with('success', 'Lookup detail deleted.');
    }

    private function validatedSurveyData(Request $request): array
    {
        $surveyTypes = array_keys($this->surveyTypes());
        $lookupTypes = array_keys($this->lookupTypes());

        $data = $request->validate([
            'surveyindex' => ['required', 'integer', 'min:0'],
            'surveyprompt' => ['required', 'string', 'max:50'],
            'arbsurveyprompt' => ['nullable', 'string', 'max:200'],
            'surveyrectype' => ['required', 'integer', Rule::in($surveyTypes)],
            'responselength' => ['nullable', 'integer', 'min:0', 'max:5'],
            'responsedecimalpos' => ['nullable', 'integer', 'min:0', 'max:3'],
            'lookuptype' => ['nullable', 'integer', Rule::in($lookupTypes)],
            'lookupindex' => ['nullable', 'integer'],
            'activestatus' => ['required', 'integer', Rule::in([0, 1])],
        ]);

        $data['arbsurveyprompt'] = $data['arbsurveyprompt'] === '' ? null : $data['arbsurveyprompt'];

        if ((int) $data['surveyrectype'] === 1) {
            validator($data, [
                'responselength' => ['required', 'integer', 'min:1', 'max:5'],
                'responsedecimalpos' => ['required', 'integer', 'min:0', 'max:3'],
            ])->validate();
        } elseif ((int) $data['surveyrectype'] === 2) {
            validator($data, [
                'responselength' => ['required', 'integer', 'min:1', 'max:5'],
            ])->validate();
            $data['responsedecimalpos'] = 0;
        } else {
            $data['responselength'] = 0;
            $data['responsedecimalpos'] = 0;
        }

        if ((int) $data['surveyrectype'] === 7) {
            validator($data, [
                'lookuptype' => ['required', 'integer', Rule::in($lookupTypes)],
            ])->validate();

            if ((int) $data['lookuptype'] === 0) {
                validator($data, [
                    'lookupindex' => ['required', 'integer', Rule::exists('lookupindexheader', 'transactionkey')],
                ])->validate();
            } else {
                $data['lookupindex'] = 0;
            }
        } else {
            $data['lookuptype'] = 1000;
            $data['lookupindex'] = 0;
        }

        return $data;
    }

    private function surveyData(CustomerSurveyDefinition $survey): array
    {
        return [
            'surveydefkey' => (int) $survey->surveydefkey,
            'surveyindex' => (int) ($survey->surveyindex ?? 0),
            'surveyprompt' => $survey->surveyprompt ?? '',
            'arbsurveyprompt' => $survey->arbsurveyprompt ?? '',
            'surveyrectype' => (int) ($survey->surveyrectype ?? 0),
            'responselength' => (int) ($survey->responselength ?? 0),
            'responsedecimalpos' => (int) ($survey->responsedecimalpos ?? 0),
            'lookuptype' => (int) ($survey->lookuptype ?? 1000),
            'lookupindex' => (int) ($survey->lookupindex ?? 0),
            'activestatus' => (int) ($survey->activestatus ?? 1),
        ];
    }

    private function draftSurveyData(Request $request, ?CustomerSurveyDefinition $survey = null): array
    {
        $base = $survey ? $this->surveyData($survey) : [
            'surveydefkey' => $this->nextSurveyNumber(),
            'surveyindex' => 0,
            'surveyprompt' => '',
            'arbsurveyprompt' => '',
            'surveyrectype' => '',
            'responselength' => 0,
            'responsedecimalpos' => 0,
            'lookuptype' => '',
            'lookupindex' => 0,
            'activestatus' => 1,
        ];

        if (! $request->boolean('addlookupindex') && ! $request->boolean('restore_lookup_context')) {
            return $base;
        }

        return array_merge($base, [
            'surveyindex' => (int) $request->query('surveyindex', $base['surveyindex']),
            'surveyprompt' => (string) $request->query('surveyprompt', $base['surveyprompt']),
            'arbsurveyprompt' => (string) $request->query('arbsurveyprompt', $base['arbsurveyprompt']),
            'surveyrectype' => $request->query('surveyrectype', $base['surveyrectype']),
            'responselength' => (int) $request->query('responselength', $base['responselength']),
            'responsedecimalpos' => (int) $request->query('responsedecimalpos', $base['responsedecimalpos']),
            'lookuptype' => $request->query('lookuptype', $base['lookuptype']),
            'lookupindex' => (int) $request->query('lookupindex', $base['lookupindex']),
            'activestatus' => (int) $request->query('activestatus', $base['activestatus']),
        ]);
    }

    private function transformSurveyRow(CustomerSurveyDefinition $survey): array
    {
        return [
            'surveydefkey' => (int) $survey->surveydefkey,
            'surveyprompt' => $survey->surveyprompt,
            'surveyindex' => $survey->surveyindex,
            'surveyrectype_label' => $this->surveyTypes()[(int) $survey->surveyrectype] ?? $survey->surveyrectype,
            'lookuptype_label' => $this->lookupTypeLabel((int) $survey->lookuptype),
            'activestatus' => (int) $survey->activestatus,
        ];
    }

    private function lookupDetails(int $transactionkey): array
    {
        return LookupIndexDetail::query()
            ->where('transactionkey', $transactionkey)
            ->orderBy('primary_key')
            ->get()
            ->map(fn (LookupIndexDetail $detail) => [
                'primary_key' => (int) $detail->primary_key,
                'description' => $detail->description ?? '',
                'arbdescription' => $detail->arbdescription ?? '',
            ])
            ->all();
    }

    private function returnContext(Request $request): array
    {
        return [
            'return_mode' => $request->input('return_mode', $request->query('return_mode')),
            'return_id' => $request->input('return_id', $request->query('return_id')),
            'surveyindex' => $request->input('surveyindex', $request->query('surveyindex')),
            'surveyprompt' => $request->input('surveyprompt', $request->query('surveyprompt')),
            'arbsurveyprompt' => $request->input('arbsurveyprompt', $request->query('arbsurveyprompt')),
            'surveyrectype' => $request->input('surveyrectype', $request->query('surveyrectype')),
            'responselength' => $request->input('responselength', $request->query('responselength')),
            'responsedecimalpos' => $request->input('responsedecimalpos', $request->query('responsedecimalpos')),
            'lookuptype' => $request->input('lookuptype', $request->query('lookuptype')),
            'lookupindex' => $request->input('lookupindex', $request->query('lookupindex')),
            'activestatus' => $request->input('activestatus', $request->query('activestatus')),
        ];
    }

    private function nextSurveyNumber(): int
    {
        return ((int) CustomerSurveyDefinition::query()->max('surveydefkey')) + 1;
    }

    private function nextLookupIndexNumber(): int
    {
        return ((int) LookupIndexHeader::query()->max('transactionkey')) + 1;
    }

    private function surveyTypes(): array
    {
        return [
            0 => 'Title',
            1 => 'Numeric Input',
            2 => 'Alpha-Num Input Response',
            3 => 'Date Input',
            4 => 'Time Input',
            5 => 'Yes/No Option',
            6 => 'Check Box',
            7 => 'Lookup Field',
            8 => 'Subtitle',
        ];
    }

    private function lookupTypes(): array
    {
        return [
            0 => 'Normal Lookup',
            1 => 'Item Lookup',
            1000 => '-',
        ];
    }

    private function lookupTypeLabel(int $lookuptype): string
    {
        return match ($lookuptype) {
            0 => 'Normal Lookup',
            1 => 'Item Lookup',
            default => '-',
        };
    }

    private function hasSurveyTables(): bool
    {
        return Schema::hasTable('customersurveydefinition');
    }

    private function hasLookupTables(): bool
    {
        return Schema::hasTable('lookupindexheader') && Schema::hasTable('lookupindexdetail');
    }

    private function formMeta(): array
    {
        return [
            'indexUrl' => '/merchandizing/survey',
            'baseUrl' => '/merchandizing/survey',
            'subtitle' => 'Maintain survey definitions, response formats, and lookup-driven prompts',
            'surveyTypes' => $this->surveyTypes(),
            'lookupTypes' => $this->lookupTypes(),
        ];
    }

    private function managerMeta(): array
    {
        return [
            'baseUrl' => '/merchandizing/survey/lookup-index',
            'title' => 'Survey Lookup',
            'subtitle' => 'Maintain lookup headers and selectable values for survey questions',
        ];
    }
}
