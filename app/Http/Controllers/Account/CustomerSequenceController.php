<?php

namespace App\Http\Controllers\Account;

use App\Http\Controllers\Controller;
use App\Models\CustomerMaster;
use App\Models\RouteMaster;
use App\Models\RouteSequence;
use App\Services\AccessScopeService;
use Carbon\Carbon;
use App\Support\ExcelXmlWorkbook;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\Response as HttpResponse;

class CustomerSequenceController extends Controller
{
    private const WEEKDAY_SEQUENCE_MAP = [
        1 => ['label' => 'Monday', 'seq' => 'monseq'],
        2 => ['label' => 'Tuesday', 'seq' => 'tueseq'],
        3 => ['label' => 'Wednesday', 'seq' => 'wedseq'],
        4 => ['label' => 'Thursday', 'seq' => 'thuseq'],
        5 => ['label' => 'Friday', 'seq' => 'friseq'],
        6 => ['label' => 'Saturday', 'seq' => 'satseq'],
        7 => ['label' => 'Sunday', 'seq' => 'sunseq'],
    ];

    public function index(): Response
    {
        return Inertia::render('account/customersequence/Index', [
            'defaults' => [
                'week' => $this->defaultWeek(),
            ],
            'optionSets' => [
                'routeOptions' => $this->routeOptions(),
                'weekOptions' => $this->weekOptions(),
            ],
        ]);
    }

    public function salesCalendar(Request $request): Response
    {
        $year = (int) ($request->integer('year') ?: now()->year);
        $year = max(1900, min(2200, $year));
        $weekStartDay = $this->weekStartDay();
        $rows = $this->salesCalendarRows($year);

        return Inertia::render('account/customersequence/SalesCalendar', [
            'filters' => [
                'year' => $year,
                'weekStartDay' => $weekStartDay,
            ],
            'yearOptions' => collect(range(1900, 2200))
                ->map(fn ($value) => ['id' => $value, 'label' => (string) $value])
                ->all(),
            'rows' => $rows,
            'mode' => count($rows) > 0 ? 'loaded' : 'default',
        ]);
    }

    public function storeSalesCalendar(Request $request): Response|RedirectResponse
    {
        $payload = $request->validate([
            'year' => ['required', 'integer', 'between:1900,2200'],
            'action' => ['required', 'string', Rule::in(['load', 'auto', 'save', 'delete'])],
        ]);

        $year = (int) $payload['year'];
        $action = $payload['action'];

        if ($action === 'load') {
            return redirect()
                ->route('account.customer-sequence.sales-calendar', ['year' => $year]);
        }

        if ($action === 'auto') {
            return Inertia::render('account/customersequence/SalesCalendar', [
                'filters' => [
                    'year' => $year,
                    'weekStartDay' => $this->weekStartDay(),
                ],
                'yearOptions' => collect(range(1900, 2200))
                    ->map(fn ($value) => ['id' => $value, 'label' => (string) $value])
                    ->all(),
                'rows' => $this->buildSalesCalendarRows($year, $this->weekStartDay()),
                'mode' => 'generated',
            ]);
        }

        if ($action === 'delete') {
            $deleted = DB::table('salescalender')->where('salesyear', $year)->delete();

            return redirect()
                ->route('account.customer-sequence.sales-calendar', ['year' => $year])
                ->with($deleted > 0 ? 'success' : 'error', $deleted > 0 ? 'Sales calendar deleted.' : 'No record found for the selected year.');
        }

        $existingCount = DB::table('salescalender')->where('salesyear', $year)->count();

        if ($existingCount > 0) {
            return redirect()
                ->route('account.customer-sequence.sales-calendar', ['year' => $year])
                ->with('error', 'Duplicate record.');
        }

        $rows = $this->buildSalesCalendarRows($year, $this->weekStartDay());
        $username = auth()->user()?->username ?? auth()->user()?->name ?? 'system';
        $timestamp = now();

        DB::table('salescalender')->insert(
            collect($rows)
                ->map(fn ($row) => [
                    'salesyear' => $year,
                    'weeknumber' => $row['weeknumber'],
                    'weekstartdate' => Carbon::createFromFormat('d-m-Y', $row['weekstartdate'])->startOfDay(),
                    'weekenddate' => Carbon::createFromFormat('d-m-Y', $row['weekenddate'])->startOfDay(),
                    'rp32weeknumber' => $row['rp32weeknumber'],
                    'salesperiod' => $row['salesperiod'],
                    'created' => $username,
                    'modified' => $username,
                    'cdat' => $timestamp,
                    'mdat' => $timestamp,
                ])
                ->all()
        );

        return redirect()
            ->route('account.customer-sequence.sales-calendar', ['year' => $year])
            ->with('success', 'New record.');
    }

    public function arrange(Request $request): Response
    {
        $filters = $this->validatedRouteWeek($request);
        $customers = $this->arrangedCustomers($filters['routecode'], $filters['week']);

        return Inertia::render('account/customersequence/Arrange', [
            'filters' => $filters,
            'customers' => $customers,
            'optionSets' => [
                'routeOptions' => $this->routeOptions(),
                'weekOptions' => $this->weekOptions(),
                'dayOptions' => $this->dayOptions(),
            ],
        ]);
    }

    public function storeArrange(Request $request): RedirectResponse
    {
        $payload = $request->validate([
            'routecode' => ['required', 'integer', Rule::exists('routemaster', 'routecode')],
            'week' => ['required', 'integer', Rule::in($this->allowedWeeks())],
            'rows' => ['array'],
            'rows.*.customercode' => ['required', 'integer', Rule::exists('customermaster', 'customercode')],
            'rows.*.callrestrictiondays1' => ['required', 'integer', Rule::in([0, 1])],
            'rows.*.callrestrictiondays2' => ['required', 'integer', Rule::in([0, 1])],
            'rows.*.callrestrictiondays3' => ['required', 'integer', Rule::in([0, 1])],
            'rows.*.callrestrictiondays4' => ['required', 'integer', Rule::in([0, 1])],
            'rows.*.callrestrictiondays5' => ['required', 'integer', Rule::in([0, 1])],
            'rows.*.callrestrictiondays6' => ['required', 'integer', Rule::in([0, 1])],
            'rows.*.callrestrictiondays7' => ['required', 'integer', Rule::in([0, 1])],
            'remove_customers' => ['array'],
            'remove_customers.*' => ['integer', Rule::exists('customermaster', 'customercode')],
        ]);

        $routecode = (int) $payload['routecode'];
        $week = (int) $payload['week'];
        $rows = collect($payload['rows'] ?? []);
        $removeCustomers = collect($payload['remove_customers'] ?? [])->map(fn ($value) => (int) $value);

        DB::transaction(function () use ($routecode, $week, $rows, $removeCustomers) {
            if ($removeCustomers->isNotEmpty()) {
                RouteSequence::query()
                    ->where('routecode', $routecode)
                    ->where('rp32weeknumber', $week)
                    ->whereIn('customercode', $removeCustomers->all())
                    ->delete();
            }

            foreach ($rows as $row) {
                $customerCode = (int) $row['customercode'];

                if ($removeCustomers->contains($customerCode)) {
                    continue;
                }

                $existing = RouteSequence::query()
                    ->where('routecode', $routecode)
                    ->where('rp32weeknumber', $week)
                    ->where('customercode', $customerCode)
                    ->first();

                $sequenceValues = [
                    'monseq' => $existing?->monseq ?? 0,
                    'tueseq' => $existing?->tueseq ?? 0,
                    'wedseq' => $existing?->wedseq ?? 0,
                    'thuseq' => $existing?->thuseq ?? 0,
                    'friseq' => $existing?->friseq ?? 0,
                    'satseq' => $existing?->satseq ?? 0,
                    'sunseq' => $existing?->sunseq ?? 0,
                ];

                RouteSequence::query()->updateOrCreate(
                    [
                        'routecode' => $routecode,
                        'rp32weeknumber' => $week,
                        'customercode' => $customerCode,
                    ],
                    array_merge($sequenceValues, [
                        'callrestrictiondays1' => (int) $row['callrestrictiondays1'],
                        'callrestrictiondays2' => (int) $row['callrestrictiondays2'],
                        'callrestrictiondays3' => (int) $row['callrestrictiondays3'],
                        'callrestrictiondays4' => (int) $row['callrestrictiondays4'],
                        'callrestrictiondays5' => (int) $row['callrestrictiondays5'],
                        'callrestrictiondays6' => (int) $row['callrestrictiondays6'],
                        'callrestrictiondays7' => (int) $row['callrestrictiondays7'],
                    ])
                );
            }
        });

        return redirect()
            ->route('account.customer-sequence.arrange', ['routecode' => $routecode, 'week' => $week])
            ->with('success', 'Customer arrangement updated.');
    }

    public function downloadArrangeTemplate(Request $request): HttpResponse
    {
        $filters = $this->validatedRouteWeek($request);
        $rows = collect($this->arrangedCustomers($filters['routecode'], $filters['week']))
            ->map(function ($row) {
                return [
                    'customercode' => $row->customercode ?? $row['customercode'] ?? '',
                    'alternatecode' => $row->alternatecode ?? $row['alternatecode'] ?? '',
                    'customername' => $row->customername ?? $row['customername'] ?? '',
                    'monday' => $row->callrestrictiondays1 ?? $row['callrestrictiondays1'] ?? 0,
                    'tuesday' => $row->callrestrictiondays2 ?? $row['callrestrictiondays2'] ?? 0,
                    'wednesday' => $row->callrestrictiondays3 ?? $row['callrestrictiondays3'] ?? 0,
                    'thursday' => $row->callrestrictiondays4 ?? $row['callrestrictiondays4'] ?? 0,
                    'friday' => $row->callrestrictiondays5 ?? $row['callrestrictiondays5'] ?? 0,
                    'saturday' => $row->callrestrictiondays6 ?? $row['callrestrictiondays6'] ?? 0,
                    'sunday' => $row->callrestrictiondays7 ?? $row['callrestrictiondays7'] ?? 0,
                ];
            })
            ->all();

        return ExcelXmlWorkbook::download(
            'arrange-customer-template.xls',
            $this->arrangeImportHeaders(),
            $rows,
            'ArrangeCustomer'
        );
    }

    public function bulkImportArrange(Request $request): RedirectResponse
    {
        $request->validate([
            'routecode' => ['required', 'integer', Rule::exists('routemaster', 'routecode')],
            'week' => ['required', 'integer', Rule::in($this->allowedWeeks())],
            'file' => ['required', 'file', 'max:5120'],
        ]);

        $routecode = (int) $request->integer('routecode');
        $week = (int) $request->integer('week');

        try {
            $rows = ExcelXmlWorkbook::parseFile($request->file('file')->getRealPath());
        } catch (\RuntimeException $exception) {
            return back()->withErrors(['file' => $exception->getMessage()]);
        }

        if ($rows === []) {
            return back()->withErrors(['file' => 'The uploaded file does not contain any customer rows.']);
        }

        DB::transaction(function () use ($rows, $routecode, $week) {
            foreach ($rows as $index => $row) {
                $payload = $this->mapArrangeImportRow($row);

                try {
                    $validated = Validator::make($payload, [
                        'customercode' => ['required', 'integer', Rule::exists('customermaster', 'customercode')],
                        'callrestrictiondays1' => ['required', 'integer', Rule::in([0, 1])],
                        'callrestrictiondays2' => ['required', 'integer', Rule::in([0, 1])],
                        'callrestrictiondays3' => ['required', 'integer', Rule::in([0, 1])],
                        'callrestrictiondays4' => ['required', 'integer', Rule::in([0, 1])],
                        'callrestrictiondays5' => ['required', 'integer', Rule::in([0, 1])],
                        'callrestrictiondays6' => ['required', 'integer', Rule::in([0, 1])],
                        'callrestrictiondays7' => ['required', 'integer', Rule::in([0, 1])],
                    ])->validate();
                } catch (\Illuminate\Validation\ValidationException $exception) {
                    $messages = collect($exception->errors())->flatten()->implode(' ');

                    throw \Illuminate\Validation\ValidationException::withMessages([
                        'file' => 'Row ' . ($index + 2) . ': ' . $messages,
                    ]);
                }

                $existing = RouteSequence::query()
                    ->where('routecode', $routecode)
                    ->where('rp32weeknumber', $week)
                    ->where('customercode', (int) $validated['customercode'])
                    ->first();

                RouteSequence::query()->updateOrCreate(
                    [
                        'routecode' => $routecode,
                        'rp32weeknumber' => $week,
                        'customercode' => (int) $validated['customercode'],
                    ],
                    [
                        'callrestrictiondays1' => (int) $validated['callrestrictiondays1'],
                        'callrestrictiondays2' => (int) $validated['callrestrictiondays2'],
                        'callrestrictiondays3' => (int) $validated['callrestrictiondays3'],
                        'callrestrictiondays4' => (int) $validated['callrestrictiondays4'],
                        'callrestrictiondays5' => (int) $validated['callrestrictiondays5'],
                        'callrestrictiondays6' => (int) $validated['callrestrictiondays6'],
                        'callrestrictiondays7' => (int) $validated['callrestrictiondays7'],
                        'monseq' => $existing?->monseq ?? 0,
                        'tueseq' => $existing?->tueseq ?? 0,
                        'wedseq' => $existing?->wedseq ?? 0,
                        'thuseq' => $existing?->thuseq ?? 0,
                        'friseq' => $existing?->friseq ?? 0,
                        'satseq' => $existing?->satseq ?? 0,
                        'sunseq' => $existing?->sunseq ?? 0,
                    ]
                );
            }
        });

        return redirect()
            ->route('account.customer-sequence.arrange', ['routecode' => $routecode, 'week' => $week])
            ->with('success', count($rows) . ' customer arrangement row(s) imported successfully.');
    }

    public function add(Request $request): Response
    {
        $filters = $this->validatedRouteWeek($request);
        $sourceRoute = (int) ($request->integer('source_routecode') ?: $filters['routecode']);

        return Inertia::render('account/customersequence/Add', [
            'filters' => array_merge($filters, ['source_routecode' => $sourceRoute]),
            'customers' => $this->availableCustomers($filters['routecode'], $filters['week'], $sourceRoute),
            'optionSets' => [
                'routeOptions' => $this->routeOptions(),
                'weekOptions' => $this->weekOptions(),
            ],
        ]);
    }

    public function storeAdd(Request $request): RedirectResponse
    {
        $payload = $request->validate([
            'routecode' => ['required', 'integer', Rule::exists('routemaster', 'routecode')],
            'week' => ['required', 'integer', Rule::in($this->allowedWeeks())],
            'customers' => ['required', 'array', 'min:1'],
            'customers.*' => ['integer', Rule::exists('customermaster', 'customercode')],
        ]);

        $routecode = (int) $payload['routecode'];
        $week = (int) $payload['week'];

        DB::transaction(function () use ($payload, $routecode, $week) {
            foreach ($payload['customers'] as $customerCode) {
                RouteSequence::query()->firstOrCreate(
                    [
                        'routecode' => $routecode,
                        'rp32weeknumber' => $week,
                        'customercode' => (int) $customerCode,
                    ],
                    [
                        'callrestrictiondays1' => 0,
                        'callrestrictiondays2' => 0,
                        'callrestrictiondays3' => 0,
                        'callrestrictiondays4' => 0,
                        'callrestrictiondays5' => 0,
                        'callrestrictiondays6' => 0,
                        'callrestrictiondays7' => 0,
                        'monseq' => 0,
                        'tueseq' => 0,
                        'wedseq' => 0,
                        'thuseq' => 0,
                        'friseq' => 0,
                        'satseq' => 0,
                        'sunseq' => 0,
                    ]
                );
            }
        });

        return redirect()
            ->route('account.customer-sequence.arrange', ['routecode' => $routecode, 'week' => $week])
            ->with('success', 'Customers added to the sequence.');
    }

    public function routeSequence(Request $request): Response
    {
        $filters = $this->validatedRouteWeek($request);
        $dayMap = $this->routeSequenceDayMap();
        $day = (int) ($request->integer('day') ?: 1);
        $day = array_key_exists($day, $dayMap) ? $day : 1;

        return Inertia::render('account/customersequence/RouteSequence', [
            'filters' => array_merge($filters, ['day' => $day]),
            'customers' => $this->sequencedCustomers($filters['routecode'], $filters['week'], $day),
            'optionSets' => [
                'routeOptions' => $this->routeOptions(),
                'weekOptions' => $this->weekOptions(),
                'dayOptions' => $this->dayOptions(),
            ],
        ]);
    }

    public function storeRouteSequence(Request $request): RedirectResponse
    {
        $dayMap = $this->routeSequenceDayMap();

        $payload = $request->validate([
            'routecode' => ['required', 'integer', Rule::exists('routemaster', 'routecode')],
            'week' => ['required', 'integer', Rule::in($this->allowedWeeks())],
            'day' => ['required', 'integer', Rule::in(array_keys($dayMap))],
            'customer_order' => ['array'],
            'customer_order.*' => ['integer', Rule::exists('customermaster', 'customercode')],
        ]);

        $day = (int) $payload['day'];
        $seqColumn = $dayMap[$day]['seq'];
        $flagColumn = $dayMap[$day]['flag'];
        $routecode = (int) $payload['routecode'];
        $week = (int) $payload['week'];
        $order = collect($payload['customer_order'] ?? []);

        DB::transaction(function () use ($routecode, $week, $seqColumn, $flagColumn, $order) {
            RouteSequence::query()
                ->where('routecode', $routecode)
                ->where('rp32weeknumber', $week)
                ->update([$seqColumn => 0]);

            foreach ($order->values() as $index => $customerCode) {
                RouteSequence::query()
                    ->where('routecode', $routecode)
                    ->where('rp32weeknumber', $week)
                    ->where('customercode', (int) $customerCode)
                    ->where($flagColumn, 1)
                    ->update([$seqColumn => $index + 1]);
            }
        });

        return redirect()
            ->route('account.customer-sequence.route-sequence', [
                'routecode' => $routecode,
                'week' => $week,
                'day' => $day,
            ])
            ->with('success', 'Route sequence updated.');
    }

    public function copySequence(Request $request): Response
    {
        $routecode = (int) $request->integer('routecode');
        abort_unless($routecode > 0, 404);
        abort_unless(app(AccessScopeService::class)->allows($request->user(), 'route', $routecode), 403);

        return Inertia::render('account/customersequence/CopySequence', [
            'filters' => ['routecode' => $routecode],
            'optionSets' => [
                'weekOptions' => $this->weekOptions(),
                'dayOptions' => array_merge([['id' => 8, 'label' => 'All']], $this->dayOptions()),
            ],
        ]);
    }

    public function storeCopySequence(Request $request): RedirectResponse
    {
        $payload = $request->validate([
            'routecode' => ['required', 'integer', Rule::exists('routemaster', 'routecode')],
            'from_week' => ['required', 'integer', Rule::in($this->allowedWeeks())],
            'to_week' => ['required', 'integer', Rule::in($this->allowedWeeks())],
            'from_day' => ['required', 'integer', Rule::in([1, 2, 3, 4, 5, 6, 7, 8])],
            'to_day' => ['required', 'integer', Rule::in([1, 2, 3, 4, 5, 6, 7, 8])],
        ]);

        if (
            (int) $payload['from_week'] === (int) $payload['to_week'] &&
            (int) $payload['from_day'] === (int) $payload['to_day']
        ) {
            return back()->with('error', 'Invalid week/day selection.');
        }

        if ((int) $payload['from_day'] === 8 && (int) $payload['to_day'] < 8) {
            return back()->with('error', 'Invalid week/day selection.');
        }

        $this->performCopySequence(
            (int) $payload['routecode'],
            (int) $payload['from_week'],
            (int) $payload['to_week'],
            (int) $payload['from_day'],
            (int) $payload['to_day'],
        );

        return redirect()
            ->route('account.customer-sequence.copy-sequence', ['routecode' => (int) $payload['routecode']])
            ->with('success', 'Customer sequence copied.');
    }

    private function performCopySequence(int $routecode, int $fromWeek, int $toWeek, int $fromDay, int $toDay): void
    {
        $dayMap = $this->routeSequenceDayMap();

        DB::transaction(function () use ($routecode, $fromWeek, $toWeek, $fromDay, $toDay, $dayMap) {
            if ($fromDay === 8 && $toDay === 8) {
                $sourceRows = RouteSequence::query()
                    ->where('routecode', $routecode)
                    ->where('rp32weeknumber', $fromWeek)
                    ->get();

                RouteSequence::query()
                    ->where('routecode', $routecode)
                    ->where('rp32weeknumber', $toWeek)
                    ->delete();

                foreach ($sourceRows as $row) {
                    RouteSequence::query()->create([
                        'rp32weeknumber' => $toWeek,
                        'routecode' => $routecode,
                        'customercode' => $row->customercode,
                        'callrestrictiondays1' => $row->callrestrictiondays1,
                        'callrestrictiondays2' => $row->callrestrictiondays2,
                        'callrestrictiondays3' => $row->callrestrictiondays3,
                        'callrestrictiondays4' => $row->callrestrictiondays4,
                        'callrestrictiondays5' => $row->callrestrictiondays5,
                        'callrestrictiondays6' => $row->callrestrictiondays6,
                        'callrestrictiondays7' => $row->callrestrictiondays7,
                        'monseq' => $row->monseq,
                        'tueseq' => $row->tueseq,
                        'wedseq' => $row->wedseq,
                        'thuseq' => $row->thuseq,
                        'friseq' => $row->friseq,
                        'satseq' => $row->satseq,
                        'sunseq' => $row->sunseq,
                        'referenceno' => $row->referenceno,
                        'oldcustcode' => $row->oldcustcode,
                    ]);
                }

                return;
            }

            $sourceFlag = $dayMap[$fromDay]['flag'];
            $sourceSeq = $dayMap[$fromDay]['seq'];
            $sourceRows = RouteSequence::query()
                ->where('routecode', $routecode)
                ->where('rp32weeknumber', $fromWeek)
                ->where($sourceFlag, 1)
                ->orderBy($sourceSeq)
                ->orderBy('customercode')
                ->get();

            if ($toDay === 8) {
                RouteSequence::query()
                    ->where('routecode', $routecode)
                    ->where('rp32weeknumber', $toWeek)
                    ->update([
                        'callrestrictiondays1' => 0,
                        'callrestrictiondays2' => 0,
                        'callrestrictiondays3' => 0,
                        'callrestrictiondays4' => 0,
                        'callrestrictiondays5' => 0,
                        'callrestrictiondays6' => 0,
                        'callrestrictiondays7' => 0,
                        'monseq' => 0,
                        'tueseq' => 0,
                        'wedseq' => 0,
                        'thuseq' => 0,
                        'friseq' => 0,
                        'satseq' => 0,
                        'sunseq' => 0,
                    ]);

                foreach ($sourceRows->values() as $index => $row) {
                    RouteSequence::query()->updateOrCreate(
                        [
                            'routecode' => $routecode,
                            'rp32weeknumber' => $toWeek,
                            'customercode' => $row->customercode,
                        ],
                        [
                            'callrestrictiondays1' => 1,
                            'callrestrictiondays2' => 1,
                            'callrestrictiondays3' => 1,
                            'callrestrictiondays4' => 1,
                            'callrestrictiondays5' => 1,
                            'callrestrictiondays6' => 1,
                            'callrestrictiondays7' => 1,
                            'monseq' => $index + 1,
                            'tueseq' => $index + 1,
                            'wedseq' => $index + 1,
                            'thuseq' => $index + 1,
                            'friseq' => $index + 1,
                            'satseq' => $index + 1,
                            'sunseq' => $index + 1,
                        ]
                    );
                }

                return;
            }

            $targetFlag = $dayMap[$toDay]['flag'];
            $targetSeq = $dayMap[$toDay]['seq'];

            RouteSequence::query()
                ->where('routecode', $routecode)
                ->where('rp32weeknumber', $toWeek)
                ->update([
                    $targetFlag => 0,
                    $targetSeq => 0,
                ]);

            foreach ($sourceRows->values() as $index => $row) {
                $existing = RouteSequence::query()->firstOrCreate(
                    [
                        'routecode' => $routecode,
                        'rp32weeknumber' => $toWeek,
                        'customercode' => $row->customercode,
                    ],
                    [
                        'callrestrictiondays1' => 0,
                        'callrestrictiondays2' => 0,
                        'callrestrictiondays3' => 0,
                        'callrestrictiondays4' => 0,
                        'callrestrictiondays5' => 0,
                        'callrestrictiondays6' => 0,
                        'callrestrictiondays7' => 0,
                        'monseq' => 0,
                        'tueseq' => 0,
                        'wedseq' => 0,
                        'thuseq' => 0,
                        'friseq' => 0,
                        'satseq' => 0,
                        'sunseq' => 0,
                    ]
                );

                $existing->update([
                    $targetFlag => 1,
                    $targetSeq => $index + 1,
                ]);
            }
        });
    }

    private function arrangedCustomers(int $routecode, int $week): array
    {
        return RouteSequence::query()
            ->join('customermaster', 'customermaster.customercode', '=', 'routesequence.customercode')
            ->leftJoin('routemaster as route', 'route.routecode', '=', 'customermaster.routecode')
            ->where('routesequence.routecode', $routecode)
            ->where('routesequence.rp32weeknumber', $week)
            ->orderBy('customermaster.customercode')
            ->get([
                'customermaster.customercode',
                'customermaster.alternatecode',
                'customermaster.customername',
                'customermaster.arbcustomername',
                'route.routename as sourceroute',
                'routesequence.callrestrictiondays1',
                'routesequence.callrestrictiondays2',
                'routesequence.callrestrictiondays3',
                'routesequence.callrestrictiondays4',
                'routesequence.callrestrictiondays5',
                'routesequence.callrestrictiondays6',
                'routesequence.callrestrictiondays7',
            ])
            ->toArray();
    }

    private function arrangeImportHeaders(): array
    {
        return [
            'customercode',
            'alternatecode',
            'customername',
            'monday',
            'tuesday',
            'wednesday',
            'thursday',
            'friday',
            'saturday',
            'sunday',
        ];
    }

    private function mapArrangeImportRow(array $row): array
    {
        $row = collect($row)
            ->mapWithKeys(fn ($value, $key) => [$this->normalizeImportHeader($key) => $value])
            ->all();

        return [
            'customercode' => $this->integerOrNull($row['customercode'] ?? null),
            'callrestrictiondays1' => $this->normalizeFlag($row['monday'] ?? null),
            'callrestrictiondays2' => $this->normalizeFlag($row['tuesday'] ?? null),
            'callrestrictiondays3' => $this->normalizeFlag($row['wednesday'] ?? null),
            'callrestrictiondays4' => $this->normalizeFlag($row['thursday'] ?? null),
            'callrestrictiondays5' => $this->normalizeFlag($row['friday'] ?? null),
            'callrestrictiondays6' => $this->normalizeFlag($row['saturday'] ?? null),
            'callrestrictiondays7' => $this->normalizeFlag($row['sunday'] ?? null),
        ];
    }

    private function normalizeImportHeader(string $header): string
    {
        $header = strtolower(trim($header));
        $header = preg_replace('/[^a-z0-9]+/', '_', $header) ?? $header;

        return trim($header, '_');
    }

    private function integerOrNull(mixed $value): ?int
    {
        $value = trim((string) ($value ?? ''));

        return $value === '' ? null : (int) $value;
    }

    private function normalizeFlag(mixed $value): int
    {
        $value = strtolower(trim((string) ($value ?? '')));

        return match ($value) {
            '1', 'true', 'yes', 'y', 'active' => 1,
            default => 0,
        };
    }

    private function availableCustomers(int $routecode, int $week, int $sourceRoute): array
    {
        return CustomerMaster::query()
            ->where('routecode', $sourceRoute)
            ->whereNotIn('customercode', function ($query) use ($routecode, $week) {
                $query->select('customercode')
                    ->from('routesequence')
                    ->where('routecode', $routecode)
                    ->where('rp32weeknumber', $week);
            })
            ->orderBy('customername')
            ->get([
                'customercode',
                'alternatecode',
                'customername',
                'arbcustomername',
                'customeraddress1',
            ])
            ->toArray();
    }

    private function sequencedCustomers(int $routecode, int $week, int $day): array
    {
        $dayMap = $this->routeSequenceDayMap();
        $flagColumn = $dayMap[$day]['flag'];
        $seqColumn = $dayMap[$day]['seq'];
        $routeSequenceAlias = DB::getTablePrefix() . 'routesequence';

        return RouteSequence::query()
            ->join('customermaster', 'customermaster.customercode', '=', 'routesequence.customercode')
            ->where('routesequence.routecode', $routecode)
            ->where('routesequence.rp32weeknumber', $week)
            ->where("routesequence.$flagColumn", 1)
            ->orderBy("routesequence.$seqColumn")
            ->orderBy('customermaster.customername')
            ->get([
                'customermaster.customercode',
                'customermaster.alternatecode',
                'customermaster.customername',
                'customermaster.arbcustomername',
                'customermaster.customeraddress1',
                DB::raw("{$routeSequenceAlias}.{$seqColumn} as dayseq"),
            ])
            ->map(function ($row, $index) {
                if (!$row->dayseq) {
                    $row->dayseq = $index + 1;
                }

                return $row;
            })
            ->toArray();
    }

    private function validatedRouteWeek(Request $request): array
    {
        $data = $request->validate([
            'routecode' => ['required', 'integer', Rule::exists('routemaster', 'routecode')],
            'week' => ['required', 'integer', Rule::in($this->allowedWeeks())],
        ]);

        abort_unless(app(AccessScopeService::class)->allows($request->user(), 'route', $data['routecode']), 403);

        return $data;
    }

    private function routeOptions()
    {
        return app(AccessScopeService::class)->scopeQuery(request()->user(), RouteMaster::query(), 'route', 'routecode')
            ->where('routetmpl', 0)
            ->orderBy('routename')
            ->get(['routecode as id', 'routename as label']);
    }

    private function weekOptions(): array
    {
        return collect($this->allowedWeeks())
            ->map(fn ($week) => ['id' => $week, 'label' => (string) $week])
            ->all();
    }

    private function dayOptions(): array
    {
        return collect($this->routeSequenceDayMap())
            ->map(fn ($config, $id) => ['id' => $id, 'label' => $config['label']])
            ->values()
            ->all();
    }

    private function allowedWeeks(): array
    {
        return $this->routeSequencePlanFlag() === 1 ? [9] : [1, 2, 3, 4];
    }

    private function defaultWeek(): int
    {
        return $this->allowedWeeks()[0];
    }

    private function routeSequencePlanFlag(): int
    {
        $flag = (int) (DB::table('setup')->value('routesequenceplanflag') ?? 1);

        return in_array($flag, [1, 2], true) ? $flag : 1;
    }

    private function routeSequenceDayMap(): array
    {
        $startDay = $this->weekStartDay();
        $days = [];

        for ($slot = 1; $slot <= 7; $slot++) {
            $weekday = (($startDay + $slot - 2) % 7) + 1;
            $weekdayConfig = self::WEEKDAY_SEQUENCE_MAP[$weekday];

            $days[$slot] = [
                'label' => $weekdayConfig['label'],
                'flag' => 'callrestrictiondays' . $slot,
                'seq' => $weekdayConfig['seq'],
            ];
        }

        return $days;
    }

    private function salesCalendarRows(int $year): array
    {
        return DB::table('salescalender')
            ->where('salesyear', $year)
            ->orderBy('weeknumber')
            ->get([
                DB::raw("DATE_FORMAT(weekstartdate, '%d-%m-%Y') as weekstartdate"),
                DB::raw("DATE_FORMAT(weekenddate, '%d-%m-%Y') as weekenddate"),
                'weeknumber',
                'salesperiod',
                'rp32weeknumber',
            ])
            ->map(fn ($row) => (array) $row)
            ->all();
    }

    private function weekStartDay(): int
    {
        $day = (int) (DB::table('setup')->value('weekstartday') ?? 1);

        return $day >= 1 && $day <= 7 ? $day : 1;
    }

    private function buildSalesCalendarRows(int $year, int $weekStartDay): array
    {
        $dayNames = [
            1 => 'monday',
            2 => 'tuesday',
            3 => 'wednesday',
            4 => 'thursday',
            5 => 'friday',
            6 => 'saturday',
            7 => 'sunday',
        ];

        $normalizedStartDay = $weekStartDay - 1;
        $normalizedStartDay = $normalizedStartDay === 0 ? 7 : $normalizedStartDay;
        $dayName = $dayNames[$normalizedStartDay] ?? 'monday';

        $selectedDate = Carbon::create($year, 1, 1, 0, 0, 0);
        $first = $selectedDate->copy();

        if ((int) $selectedDate->format('N') === $normalizedStartDay) {
            $second = $selectedDate->copy();
        } else {
            $second = $selectedDate->copy()->next($dayName);
        }

        $rows = [];
        $salesWeek = 1;
        $calendarWeek = 1;

        while ((int) $first->format('Y') <= $year) {
            $currentFirst = $first->copy();
            $currentSecond = $second->copy();

            if ((int) $currentSecond->format('Y') > $year) {
                $currentSecond = Carbon::create($year, 12, 31, 0, 0, 0);
                if ($calendarWeek === 1) {
                    $calendarWeek = 53;
                }
            }

            $rows[] = [
                'weekstartdate' => $currentFirst->format('d-m-Y'),
                'weekenddate' => $currentSecond->format('d-m-Y'),
                'weeknumber' => $calendarWeek,
                'salesperiod' => (int) $currentFirst->format('m'),
                'rp32weeknumber' => $salesWeek,
            ];

            if ((int) $currentSecond->format('Y') > $year) {
                break;
            }

            $first = $currentSecond->copy()->addDay();
            $second = $first->copy()->addDays(6);
            $calendarWeek++;
            $salesWeek = $salesWeek === 4 ? 1 : $salesWeek + 1;
        }

        return $rows;
    }
}
