<?php

namespace App\Http\Controllers\Scheme;

use App\Http\Controllers\Controller;
use App\Models\ProductGroupHeader;
use App\Models\PromoKeyDetail;
use App\Models\PromoPlanDetail;
use App\Models\PromoPlanHeader;
use App\Models\PromotionAssignment;
use App\Models\PromotionAssignmentAdvanced;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class PromoPlanController extends Controller
{
    public function index(): Response
    {
        $search = request('search');
        $perPage = (int) request('per_page', 10);
        $perPage = in_array($perPage, [10, 25, 50, 100], true) ? $perPage : 10;
        $headerAlias = DB::getTablePrefix() . 'header';

        if ($this->hasPlanTables()) {
            $query = PromoPlanDetail::query()
                ->join('promoplanheader as header', 'header.plannumber', '=', 'promoplandetail.plannumber')
                ->when($search, function ($query, $searchTerm) {
                    $query->where(function ($inner) use ($searchTerm) {
                        $inner->where('promoplandetail.plannumber', 'like', '%' . $searchTerm . '%')
                            ->orWhere('promoplandetail.plandescription', 'like', '%' . $searchTerm . '%')
                            ->orWhere('promoplandetail.arbplandescription', 'like', '%' . $searchTerm . '%');
                    });
                })
                ->groupBy(
                    'promoplandetail.plannumber',
                    'promoplandetail.plandescription',
                    'promoplandetail.arbplandescription',
                    DB::raw("{$headerAlias}.activeindicator")
                )
                ->orderBy('promoplandetail.plannumber')
                ->select([
                    'promoplandetail.plannumber',
                    'promoplandetail.plandescription',
                    'promoplandetail.arbplandescription',
                    DB::raw("{$headerAlias}.activeindicator as activeindicator"),
                ]);

            $plans = $query
                ->paginate($perPage)
                ->withQueryString();
        } else {
            $plans = new LengthAwarePaginator(
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

        return Inertia::render('scheme/promo-plan/Index', [
            'available' => $this->hasPlanTables(),
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
        abort_unless($this->hasPlanTables(), 404);
        $nextPlanNumber = $this->nextPlanNumber();

        return Inertia::render('scheme/promo-plan/FormPage', [
            'mode' => 'create',
            'formMeta' => $this->formMeta(),
            'planData' => [
                'plannumber' => $nextPlanNumber,
                'plandescription' => '',
                'arbplandescription' => '',
                'promotiontypecode' => 5,
                'rangebasis' => 0,
                'amountbasis' => 0,
                'exclusionoption' => 0,
                'qualificationgroup' => '',
                'assignmentgroup' => '',
                'assignmentnumber' => $nextPlanNumber,
                'activeindicator' => 1,
                'iscase' => 0,
                'onetimeuse' => 0,
                'repeatrange' => 0,
                'enforcepromotion' => 0,
                'casepromotion' => 0,
                'ranges' => [
                    [
                        'rangelow' => '',
                        'rangehigh' => '',
                        'repeatingrange' => '',
                        'promotionamount' => '',
                    ],
                ],
            ],
        ]);
    }

    public function show(int $promoPlan): Response
    {
        abort_unless($this->hasPlanTables(), 404);

        return Inertia::render('scheme/promo-plan/FormPage', [
            'mode' => 'view',
            'formMeta' => $this->formMeta(),
            'planData' => $this->planFormData($promoPlan),
        ]);
    }

    public function edit(int $promoPlan): Response
    {
        abort_unless($this->hasPlanTables(), 404);

        return Inertia::render('scheme/promo-plan/FormPage', [
            'mode' => 'edit',
            'formMeta' => $this->formMeta(),
            'planData' => $this->planFormData($promoPlan),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        abort_unless($this->hasPlanTables(), 404);

        $data = $this->validatedData($request);
        $username = auth()->user()?->username ?? auth()->user()?->name ?? 'system';
        $planNumber = $this->nextPlanNumber();

        DB::transaction(function () use ($data, $username, $planNumber) {
            PromoPlanDetail::query()->create([
                'plannumber' => $planNumber,
                'qualificationgroup' => $data['qualificationgroup'],
                'assignmentgroup' => $data['assignmentgroup'],
                'performcriteriakey' => 0,
                'rangebasis' => $data['rangebasis'],
                'amountbasis' => $data['amountbasis'],
                'exclusionoption' => $data['exclusionoption'],
                'assignmentnumber' => $planNumber,
                'plandescription' => $data['plandescription'],
                'arbplandescription' => $data['arbplandescription'],
                'promotiontypecode' => $data['promotiontypecode'],
                'rentindicator' => 0,
                'iscase' => $data['iscase'],
                'onetimeuse' => $data['onetimeuse'],
                'enforcepromotion' => $data['enforcepromotion'],
                'repeatrange' => $data['repeatrange'],
                'created' => $username,
                'cdat' => now(),
                'modified' => $username,
                'mdat' => now(),
                'alternatecode' => '0',
                'memo1' => (string) ($data['casepromotion'] ?? 0),
                'divison' => 0,
            ]);

            PromoPlanHeader::query()->create([
                'plannumber' => $planNumber,
                'plandescription' => $data['plandescription'],
                'arbplandescription' => $data['arbplandescription'],
                'plantypecode' => $data['promotiontypecode'],
                'activeindicator' => $data['activeindicator'],
                'created' => $username,
                'cdat' => now(),
                'modified' => $username,
                'mdat' => now(),
                'alternatecode' => '0',
                'divison' => 0,
            ]);

            $this->syncPromoKeyDetail($planNumber, $data, $username);
            $this->syncRanges($planNumber, $planNumber, $data['ranges'], $username);
        });

        return redirect('/scheme/promotion/promo-plan')->with('success', 'Promo plan created.');
    }

    public function update(Request $request, int $promoPlan): RedirectResponse
    {
        abort_unless($this->hasPlanTables(), 404);

        $plan = PromoPlanDetail::query()->findOrFail($promoPlan);
        $header = PromoPlanHeader::query()->findOrFail($promoPlan);
        $data = $this->validatedData($request);
        $username = auth()->user()?->username ?? auth()->user()?->name ?? 'system';

        DB::transaction(function () use ($plan, $header, $data, $username) {
            $plan->update([
                'qualificationgroup' => $data['qualificationgroup'],
                'assignmentgroup' => $data['assignmentgroup'],
                'rangebasis' => $data['rangebasis'],
                'amountbasis' => $data['amountbasis'],
                'exclusionoption' => $data['exclusionoption'],
                'plandescription' => $data['plandescription'],
                'arbplandescription' => $data['arbplandescription'],
                'promotiontypecode' => $data['promotiontypecode'],
                'iscase' => $data['iscase'],
                'onetimeuse' => $data['onetimeuse'],
                'enforcepromotion' => $data['enforcepromotion'],
                'repeatrange' => $data['repeatrange'],
                'memo1' => (string) ($data['casepromotion'] ?? 0),
                'modified' => $username,
                'mdat' => now(),
            ]);

            $header->update([
                'plandescription' => $data['plandescription'],
                'arbplandescription' => $data['arbplandescription'],
                'plantypecode' => $data['promotiontypecode'],
                'activeindicator' => $data['activeindicator'],
                'modified' => $username,
                'mdat' => now(),
            ]);

            $this->syncPromoKeyDetail((int) $plan->plannumber, $data, $username);
            $this->syncRanges((int) $plan->plannumber, (int) $plan->assignmentnumber, $data['ranges'], $username);
        });

        return redirect('/scheme/promotion/promo-plan')->with('success', 'Promo plan updated.');
    }

    public function destroy(int $promoPlan): RedirectResponse
    {
        abort_unless($this->hasPlanTables(), 404);

        if ($this->planInUse($promoPlan)) {
            return back()->with('error', 'Cannot delete: record is in use.');
        }

        try {
            DB::transaction(function () use ($promoPlan) {
                $this->deleteRanges($promoPlan, $promoPlan);

                if (Schema::hasTable('promokeydetail')) {
                    PromoKeyDetail::query()->where('plannumber', $promoPlan)->delete();
                }

                PromoPlanDetail::query()->where('plannumber', $promoPlan)->delete();
                PromoPlanHeader::query()->where('plannumber', $promoPlan)->delete();
            });
        } catch (\Throwable $e) {
            return back()->with('error', 'Cannot delete: record is in use.');
        }

        return back()->with('success', 'Promo plan deleted.');
    }

    private function validatedData(Request $request): array
    {
        $data = $request->validate([
            'plandescription' => ['required', 'string', 'max:50'],
            'arbplandescription' => ['nullable', 'string', 'max:200'],
            'promotiontypecode' => ['required', 'integer', Rule::in(array_keys($this->optionSets()['promotionTypes']))],
            'rangebasis' => ['required', 'integer', Rule::in(array_keys($this->optionSets()['rangeBasis']))],
            'amountbasis' => ['required', 'integer', Rule::in(array_keys($this->optionSets()['amountBasis']))],
            'exclusionoption' => ['required', 'integer', Rule::in(array_keys($this->optionSets()['exclusionOptions']))],
            'qualificationgroup' => ['required', 'integer', Rule::exists('productgroupheader', 'groupnumber')],
            'assignmentgroup' => ['nullable', 'integer', Rule::exists('productgroupheader', 'groupnumber')],
            'activeindicator' => ['required', 'integer', Rule::in([0, 1])],
            'iscase' => ['nullable', 'boolean'],
            'onetimeuse' => ['nullable', 'boolean'],
            'repeatrange' => ['nullable', 'boolean'],
            'enforcepromotion' => ['nullable', 'boolean'],
            'casepromotion' => ['nullable', 'boolean'],
            'ranges' => ['nullable', 'array'],
            'ranges.*.rangelow' => ['nullable', 'numeric', 'min:0'],
            'ranges.*.rangehigh' => ['nullable', 'numeric'],
            'ranges.*.repeatingrange' => ['nullable', 'integer', Rule::in([0, 1])],
            'ranges.*.promotionamount' => ['nullable'],
        ]);

        $data['arbplandescription'] = $data['arbplandescription'] === '' ? null : $data['arbplandescription'];
        $data['assignmentgroup'] = ($data['promotiontypecode'] ?? null) === 0
            ? 0
            : ($data['assignmentgroup'] === '' ? null : $data['assignmentgroup']);
        $data['iscase'] = (int) ($data['iscase'] ?? 0);
        $data['onetimeuse'] = (int) ($data['onetimeuse'] ?? 0);
        $data['repeatrange'] = (int) ($data['repeatrange'] ?? 0);
        $data['enforcepromotion'] = (int) ($data['enforcepromotion'] ?? 0);
        $data['casepromotion'] = (int) (($data['promotiontypecode'] ?? 0) === 7 ? ($data['casepromotion'] ?? 0) : 0);
        $data['ranges'] = collect($data['ranges'] ?? [])
            ->map(function ($range) {
                $promotionAmount = $range['promotionamount'] ?? '';

                return [
                    'rangelow' => $range['rangelow'],
                    'rangehigh' => $range['rangehigh'] === '' ? null : $range['rangehigh'],
                    'repeatingrange' => $range['repeatingrange'] === '' ? 0 : (int) $range['repeatingrange'],
                    'promotionamount' => $promotionAmount === '' ? null : $promotionAmount,
                ];
            })
            ->filter(function ($range) {
                return $range['rangelow'] !== null && $range['rangelow'] !== '';
            })
            ->values()
            ->all();

        if (($data['promotiontypecode'] ?? 0) !== 0 && ($data['assignmentgroup'] ?? null) === null) {
            throw ValidationException::withMessages([
                'assignmentgroup' => 'Assignment Group is required.',
            ]);
        }

        foreach ($data['ranges'] as $index => $range) {
            if (($data['rangebasis'] ?? 0) === 3) {
                break;
            }

            if (($data['promotiontypecode'] ?? 0) === 0) {
                if (! is_numeric($range['promotionamount'] ?? null)) {
                    throw ValidationException::withMessages([
                        "ranges.{$index}.promotionamount" => 'Assignment is required.',
                    ]);
                }
            } elseif (! is_numeric($range['promotionamount'] ?? null)) {
                throw ValidationException::withMessages([
                    "ranges.{$index}.promotionamount" => 'Promo Value is required.',
                ]);
            }

            if (($range['repeatingrange'] ?? 0) === 0 && $range['rangehigh'] !== null && (float) $range['rangehigh'] < (float) $range['rangelow']) {
                throw ValidationException::withMessages([
                    "ranges.{$index}.rangehigh" => 'Range High must be greater than Range Low.',
                ]);
            }

            if (($range['repeatingrange'] ?? 0) === 1) {
                $data['ranges'][$index]['rangehigh'] = 0;
            }
        }

        return $data;
    }

    private function planFormData(int $planNumber): array
    {
        $plan = PromoPlanDetail::query()
            ->join('promoplanheader as header', 'header.plannumber', '=', 'promoplandetail.plannumber')
            ->where('promoplandetail.plannumber', $planNumber)
            ->select([
                'promoplandetail.plannumber',
                'promoplandetail.qualificationgroup',
                'promoplandetail.assignmentgroup',
                'promoplandetail.rangebasis',
                'promoplandetail.amountbasis',
                'promoplandetail.exclusionoption',
                'promoplandetail.assignmentnumber',
                'promoplandetail.plandescription',
                'promoplandetail.arbplandescription',
                'promoplandetail.promotiontypecode',
                'promoplandetail.iscase',
                'promoplandetail.onetimeuse',
                'promoplandetail.repeatrange',
                'promoplandetail.enforcepromotion',
                'header.activeindicator',
            ])
            ->firstOrFail();

        $ranges = $this->rangeRows($planNumber, (int) $plan->assignmentnumber);

        if ($ranges === []) {
            $ranges = [[
                'rangelow' => '',
                'rangehigh' => '',
                'repeatingrange' => '',
                'promotionamount' => '',
            ]];
        }

        return [
            'plannumber' => $plan->plannumber,
            'plandescription' => $plan->plandescription,
            'arbplandescription' => $plan->arbplandescription,
            'promotiontypecode' => (int) $plan->promotiontypecode,
            'rangebasis' => (int) $plan->rangebasis,
            'amountbasis' => (int) $plan->amountbasis,
            'exclusionoption' => (int) $plan->exclusionoption,
            'qualificationgroup' => (int) $plan->qualificationgroup,
            'assignmentgroup' => (int) $plan->assignmentgroup,
            'assignmentnumber' => (int) $plan->assignmentnumber,
            'activeindicator' => (int) $plan->activeindicator,
            'iscase' => (int) $plan->iscase,
            'onetimeuse' => (int) $plan->onetimeuse,
            'repeatrange' => (int) $plan->repeatrange,
            'enforcepromotion' => (int) $plan->enforcepromotion,
            'casepromotion' => (int) ($plan->memo1 ?? 0),
            'ranges' => $ranges,
        ];
    }

    private function syncPromoKeyDetail(int $planNumber, array $data, string $username): void
    {
        if (! Schema::hasTable('promokeydetail')) {
            return;
        }

        $existing = PromoKeyDetail::query()->where('plannumber', $planNumber)->orderBy('primary_key')->first();

        $payload = [
            'plannumber' => $planNumber,
            'promotiontypecode' => $data['promotiontypecode'],
            'qualificationgroup' => $data['qualificationgroup'],
            'assignmentgroup' => $data['assignmentgroup'] ?? 0,
            'assignmentnumber' => $planNumber,
            'performcriteriakey' => 0,
            'rangebasis' => $data['rangebasis'],
            'amountbasis' => $data['amountbasis'],
            'exclusionoption' => $data['exclusionoption'],
            'active' => $existing?->active ?? 1,
            'iscase' => $data['iscase'],
            'modified' => $username,
            'mdat' => now(),
            'alternatecode' => '0',
            'divison' => 0,
            'memo1' => (string) ($data['casepromotion'] ?? 0),
        ];

        if ($existing) {
            $existing->update($payload);

            return;
        }

        PromoKeyDetail::query()->create($payload + [
            'promotionkey' => 0,
            'startdate' => null,
            'enddate' => null,
            'created' => $username,
            'cdat' => now(),
        ]);
    }

    private function syncRanges(int $planNumber, int $assignmentNumber, array $ranges, string $username): void
    {
        if (! $this->supportsRanges()) {
            return;
        }

        $this->deleteRanges($planNumber, $assignmentNumber);

        if ($ranges === []) {
            return;
        }

        if (Schema::hasTable('promotionassignmentadvanced')) {
            $rows = array_map(fn ($range) => [
                'plannumber' => $planNumber,
                'assignmentnumber' => $assignmentNumber,
                'rangelow' => $range['rangelow'],
                'rangehigh' => $range['rangehigh'],
                'repeatingrange' => $range['repeatingrange'],
                'promotionamount' => $range['promotionamount'],
                'created' => $username,
                'cdat' => now(),
                'modified' => $username,
                'mdat' => now(),
                'alternatecode' => '0',
                'divison' => 0,
            ], $ranges);

            PromotionAssignmentAdvanced::query()->insert($rows);

            return;
        }

        $rows = array_map(fn ($range) => [
            'assignmentnumber' => $assignmentNumber,
            'rangelow' => $range['rangelow'],
            'rangehigh' => $range['rangehigh'],
            'repeatingrange' => $range['repeatingrange'],
            'promotionamount' => $range['promotionamount'],
        ], $ranges);

        PromotionAssignment::query()->insert($rows);
    }

    private function planInUse(int $planNumber): bool
    {
        if (Schema::hasTable('promokeydetail')) {
            $count = PromoKeyDetail::query()->where('plannumber', $planNumber)->count();
            if ($count > 1) {
                return true;
            }
        }

        if (Schema::hasTable('promotiondetail_temp')) {
            if (DB::table('promotiondetail_temp')->where('promotionplannumber', $planNumber)->exists()) {
                return true;
            }
        }

        return false;
    }

    private function hasPlanTables(): bool
    {
        return Schema::hasTable('promoplandetail') && Schema::hasTable('promoplanheader');
    }

    private function supportsRanges(): bool
    {
        return Schema::hasTable('promotionassignmentadvanced') || Schema::hasTable('promotionassignment');
    }

    private function rangeRows(int $planNumber, int $assignmentNumber): array
    {
        if (Schema::hasTable('promotionassignmentadvanced')) {
            return PromotionAssignmentAdvanced::query()
                ->where('plannumber', $planNumber)
                ->orderBy('rangelow')
                ->get(['rangelow', 'rangehigh', 'repeatingrange', 'promotionamount'])
                ->map(fn ($row) => [
                    'rangelow' => $row->rangelow,
                    'rangehigh' => $row->rangehigh,
                    'repeatingrange' => $row->repeatingrange,
                    'promotionamount' => $row->promotionamount,
                ])
                ->all();
        }

        if (Schema::hasTable('promotionassignment')) {
            return PromotionAssignment::query()
                ->where('assignmentnumber', $assignmentNumber)
                ->orderBy('rangelow')
                ->get(['rangelow', 'rangehigh', 'repeatingrange', 'promotionamount'])
                ->map(fn ($row) => [
                    'rangelow' => $row->rangelow,
                    'rangehigh' => $row->rangehigh,
                    'repeatingrange' => $row->repeatingrange,
                    'promotionamount' => $row->promotionamount,
                ])
                ->all();
        }

        return [];
    }

    private function deleteRanges(int $planNumber, int $assignmentNumber): void
    {
        if (Schema::hasTable('promotionassignmentadvanced')) {
            PromotionAssignmentAdvanced::query()->where('plannumber', $planNumber)->delete();

            return;
        }

        if (Schema::hasTable('promotionassignment')) {
            PromotionAssignment::query()->where('assignmentnumber', $assignmentNumber)->delete();
        }
    }

    private function nextPlanNumber(): int
    {
        return ((int) PromoPlanDetail::query()->max('plannumber')) + 1;
    }

    private function formatDate(mixed $value): string
    {
        if (! $value) {
            return '';
        }

        return Carbon::parse($value)->format('Y-m-d');
    }

    private function formMeta(): array
    {
        $qualificationGroups = ProductGroupHeader::query()
            ->where('grouptype', 1)
            ->where('groupnumber', '<>', 1)
            ->orderBy('groupnumber')
            ->get([
                'groupnumber as id',
                DB::raw("CONCAT(groupnumber, ' - ', groupdescription) as label"),
            ])
            ->all();

        array_unshift($qualificationGroups, (object) [
            'id' => 1,
            'label' => '1 - ALL ITEMS -',
        ]);

        $assignmentGroups = ProductGroupHeader::query()
            ->where('grouptype', 2)
            ->where('groupnumber', '<>', 1)
            ->orderBy('groupnumber')
            ->get([
                'groupnumber as id',
                DB::raw("CONCAT(groupnumber, ' - ', groupdescription) as label"),
            ])
            ->all();

        array_unshift($assignmentGroups, (object) [
            'id' => 1,
            'label' => '1 - ALL ITEMS -',
        ]);

        return [
            'indexUrl' => '/scheme/promotion/promo-plan',
            'baseUrl' => '/scheme/promotion/promo-plan',
            'subtitle' => 'Maintain promotion plan setup using the legacy workflow.',
            'qualificationGroups' => $qualificationGroups,
            'assignmentGroups' => $assignmentGroups,
            'optionSets' => $this->optionSets(),
            'supportsRanges' => $this->supportsRanges(),
        ];
    }

    private function workflowMeta(): array
    {
        $flags = [
            'fixedQualificationEnabled' => false,
            'rangedQualificationEnabled' => false,
        ];

        if (! Schema::hasTable('controlpanel')) {
            return $flags;
        }

        $rows = DB::table('controlpanel')
            ->whereIn('flagname', [
                'Fixed Qualification/Fixed Assignment',
                'Ranged Qualification on Fixed Assignment',
            ])
            ->pluck('status', 'flagname');

        $flags['fixedQualificationEnabled'] = (int) ($rows['Fixed Qualification/Fixed Assignment'] ?? 0) === 1;
        $flags['rangedQualificationEnabled'] = (int) ($rows['Ranged Qualification on Fixed Assignment'] ?? 0) === 1;

        return $flags;
    }

    private function optionSets(): array
    {
        $workflow = $this->workflowMeta();

        $rangeBasis = [
            0 => 'No Qualification (Default)',
            1 => 'Qualification On Quantity',
            2 => 'Qualification On Amount',
        ];

        if ($workflow['fixedQualificationEnabled']) {
            $rangeBasis[3] = 'Fixed Qualification And Fixed Assignment';
        }

        if ($workflow['rangedQualificationEnabled']) {
            $rangeBasis[4] = 'Ranged Qualification on Fixed Assignment';
        }

        return [
            'promotionTypes' => [
                1 => '1 - Amount On Item',
                2 => '2 - Percentage On Item',
                0 => '3 - Net Price/ Basket Price',
                5 => '5 - Amount On Invoice',
                6 => '6 - Percentage On Invoice',
                7 => '7 - Free',
            ],
            'rangeBasis' => $rangeBasis,
            'amountBasis' => [
                0 => 'Not Applicable (Default)',
                1 => 'Wholesale Price',
                2 => 'Current Net Price',
            ],
            'exclusionOptions' => [
                0 => 'Not Applicable (Default)',
                1 => 'Exclude Item in Assignment Group From Further Promotion',
            ],
            'statusLabels' => [
                0 => 'Inactive',
                1 => 'Active',
            ],
            'fixedQualificationOptionEnabled' => $workflow['fixedQualificationEnabled'],
            'rangedFixedAssignmentOptionEnabled' => $workflow['rangedQualificationEnabled'],
        ];
    }
}
