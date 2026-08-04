<?php

namespace App\Http\Controllers\Account;

use App\Http\Controllers\Controller;
use App\Models\AutoJpPlanHeader;
use App\Models\RouteSequence;
use App\Services\AccessScopeService;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class AutoJpManagementController extends Controller
{
    public function index(Request $request): Response
    {
        $scope = app(AccessScopeService::class);
        $user = $request->user();
        $recentPlansPerPage = $this->recentPlansPerPage($request);

        $filters = [
            'routecode' => $request->integer('routecode') ?: null,
            'week' => $request->integer('week') ?: $this->defaultWeek(),
            'external_routecode' => $request->integer('external_routecode') ?: null,
            'customer_search' => trim((string) $request->input('customer_search', '')),
            'plan_id' => $request->integer('plan_id') ?: null,
            'recent_rows' => $recentPlansPerPage,
        ];

        $selectedRoute = $filters['routecode'] ? $this->routeRecord($filters['routecode']) : null;
        $homeCustomers = collect();
        $candidateCustomers = collect();
        $plan = null;

        if ($selectedRoute !== null) {
            $homeCustomers = $this->customerListForRoute((int) $selectedRoute->routecode, $filters['routecode']);
            $candidateCustomers = $this->candidateCustomers(
                (int) $selectedRoute->routecode,
                $filters['external_routecode'],
                $filters['customer_search']
            );
        }

        if ($filters['plan_id']) {
            $plan = $this->planPayload((int) $filters['plan_id']);
        }

        $recentPlans = $scope->scopeQuery($user, AutoJpPlanHeader::query(), 'route', 'routecode')
            ->latest('id')
            ->paginate($recentPlansPerPage, ['*'], 'recent_page')
            ->through(fn (AutoJpPlanHeader $planHeader) => [
                'id' => $planHeader->id,
                'routecode' => $planHeader->routecode,
                'week_number' => $planHeader->week_number,
                'status' => $planHeader->status,
                'generated_at' => optional($planHeader->generated_at)->format('d-m-Y H:i'),
                'published_at' => optional($planHeader->published_at)->format('d-m-Y H:i'),
            ])
            ->withQueryString();

        return Inertia::render('account/autojp/Index', [
            'filters' => $filters,
            'mapConfig' => [
                'googleMapsApiKey' => $this->googleMapsApiKey(),
            ],
            'optionSets' => [
                'routeOptions' => $this->routeOptions(),
                'weekOptions' => $this->weekOptions(),
                'externalRouteOptions' => $this->externalRouteOptions($filters['routecode']),
            ],
            'selectedRoute' => $selectedRoute ? $this->routeMeta($selectedRoute) : null,
            'homeCustomers' => $homeCustomers->values()->all(),
            'candidateCustomers' => $candidateCustomers->values()->all(),
            'plan' => $plan,
            'recentPlans' => $recentPlans,
        ]);
    }

    private function recentPlansPerPage(Request $request): int
    {
        $allowed = [10, 25, 50, 100, 200];
        $selected = $request->integer('recent_rows');

        return in_array($selected, $allowed, true) ? $selected : 10;
    }

    public function generate(Request $request): RedirectResponse
    {
        $payload = $request->validate([
            'routecode' => ['required', 'integer', Rule::exists('routemaster', 'routecode')],
            'week' => ['required', 'integer', Rule::in($this->allowedWeeks())],
            'customer_codes' => ['required', 'array', 'min:1'],
            'customer_codes.*' => ['integer', Rule::exists('customermaster', 'customercode')],
        ]);

        $route = $this->routeRecord((int) $payload['routecode']);
        abort_if($route === null, 404);

        $selectedCustomerCodes = collect($payload['customer_codes'])
            ->map(fn ($value) => (int) $value)
            ->unique()
            ->values();

        $customers = $this->customersByCodes($selectedCustomerCodes->all(), (int) $route->routecode);

        if ($customers->isEmpty()) {
            return back()->with('error', 'No eligible customers were selected for Auto JP generation.');
        }

        $workingDays = $this->parseWorkingDays($route->autojp_working_days ?? null);
        $routeStart = $this->timeToMinutes($route->autojp_work_start_time ?: '08:00');
        $routeEnd = $this->timeToMinutes($route->autojp_work_end_time ?: '17:00');
        $planRows = $this->buildPlanRows($customers, $workingDays, $routeStart, $routeEnd, $route);

        if ($planRows === []) {
            return back()->with('error', 'Auto JP draft requires customers with valid geo coordinates.');
        }

        if (! collect($planRows)->contains(fn (array $row) => ! empty($row['assigned_weekday']) && ! empty($row['planned_start_time']) && ! empty($row['planned_end_time']))) {
            return back()->with('error', 'No customers could be placed within the selected route planning setup.');
        }

        $username = auth()->user()?->username ?? auth()->user()?->name ?? 'system';

        $plan = DB::transaction(function () use ($payload, $route, $workingDays, $routeStart, $routeEnd, $planRows, $username) {
            $plan = AutoJpPlanHeader::create([
                'routecode' => (int) $route->routecode,
                'week_number' => (int) $payload['week'],
                'route_type' => (int) ($route->routetype ?? 0),
                'work_start_time' => $this->minutesToTime($routeStart),
                'work_end_time' => $this->minutesToTime($routeEnd),
                'working_days' => implode(',', $workingDays),
                'lookback_weeks' => 8,
                'status' => 'draft',
                'customer_count' => count($planRows),
                'external_customer_count' => collect($planRows)->where('source', 'external')->count(),
                'generated_by' => $username,
                'generated_at' => now(),
            ]);

            $plan->items()->createMany($planRows);

            return $plan;
        });

        return redirect()
            ->route('account.auto-jp.index', [
                'routecode' => $payload['routecode'],
                'week' => $payload['week'],
                'plan_id' => $plan->id,
            ])
            ->with('success', 'Auto JP draft generated.');
    }

    public function publish(AutoJpPlanHeader $plan): RedirectResponse
    {
        abort_unless(app(AccessScopeService::class)->allows(request()->user(), 'route', $plan->routecode), 403);

        $items = $plan->items()
            ->whereNotNull('assigned_weekday')
            ->where('assigned_weekday', '>', 0)
            ->orderBy('assigned_weekday')
            ->orderBy('assigned_sequence')
            ->get();

        if ($items->isEmpty()) {
            return back()->with('error', 'The selected draft has no customers to publish.');
        }

        $username = auth()->user()?->username ?? auth()->user()?->name ?? 'system';

        DB::transaction(function () use ($plan, $items, $username) {
            RouteSequence::query()
                ->where('routecode', $plan->routecode)
                ->where('rp32weeknumber', $plan->week_number)
                ->delete();

            foreach ($items as $item) {
                $days = [
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
                ];

                $dayMap = [
                    1 => ['flag' => 'callrestrictiondays1', 'seq' => 'monseq'],
                    2 => ['flag' => 'callrestrictiondays2', 'seq' => 'tueseq'],
                    3 => ['flag' => 'callrestrictiondays3', 'seq' => 'wedseq'],
                    4 => ['flag' => 'callrestrictiondays4', 'seq' => 'thuseq'],
                    5 => ['flag' => 'callrestrictiondays5', 'seq' => 'friseq'],
                    6 => ['flag' => 'callrestrictiondays6', 'seq' => 'satseq'],
                    7 => ['flag' => 'callrestrictiondays7', 'seq' => 'sunseq'],
                ];

                $assignedDay = (int) $item->assigned_weekday;
                if (isset($dayMap[$assignedDay])) {
                    $days[$dayMap[$assignedDay]['flag']] = 1;
                    $days[$dayMap[$assignedDay]['seq']] = (int) $item->assigned_sequence;
                }

                RouteSequence::query()->create(array_merge($days, [
                    'rp32weeknumber' => (int) $plan->week_number,
                    'routecode' => (int) $plan->routecode,
                    'customercode' => (int) $item->customercode,
                    'referenceno' => 'AUTO-JP-' . $plan->id,
                ]));
            }

            $plan->update([
                'status' => 'published',
                'published_by' => $username,
                'published_at' => now(),
            ]);
        });

        return redirect()
            ->route('account.auto-jp.index', [
                'routecode' => $plan->routecode,
                'week' => $plan->week_number,
                'plan_id' => $plan->id,
            ])
            ->with('success', 'Auto JP draft published to route sequence.');
    }

    private function routeOptions(): array
    {
        return app(AccessScopeService::class)->scopeQuery(request()->user(), DB::table('routemaster'), 'route', 'routecode')
            ->when(Schema::hasColumn('routemaster', 'routetmpl'), fn ($query) => $query->where('routetmpl', 0))
            ->when(Schema::hasColumn('routemaster', 'activestatus'), fn ($query) => $query->where('activestatus', 1))
            ->orderBy('routename')
            ->get(['routecode as id', 'routename as label'])
            ->all();
    }

    private function externalRouteOptions(?int $selectedRouteCode): array
    {
        return app(AccessScopeService::class)->scopeQuery(request()->user(), DB::table('routemaster'), 'route', 'routecode')
            ->when($selectedRouteCode, fn ($query) => $query->where('routecode', '<>', $selectedRouteCode))
            ->when(Schema::hasColumn('routemaster', 'routetmpl'), fn ($query) => $query->where('routetmpl', 0))
            ->when(Schema::hasColumn('routemaster', 'activestatus'), fn ($query) => $query->where('activestatus', 1))
            ->orderBy('routename')
            ->get(['routecode as id', 'routename as label'])
            ->all();
    }

    private function routeRecord(int $routeCode): ?object
    {
        if (! app(AccessScopeService::class)->allows(request()->user(), 'route', $routeCode)) {
            return null;
        }

        return DB::table('routemaster')->where('routecode', $routeCode)->first();
    }

    private function routeMeta(object $route): array
    {
        return [
            'routecode' => (int) $route->routecode,
            'routename' => (string) ($route->routename ?? ''),
            'routetype' => (int) ($route->routetype ?? 0),
            'autojp_enabled' => (int) ($route->autojp_enabled ?? 0),
            'autojp_work_start_time' => $this->formatTime($route->autojp_work_start_time ?? null) ?? '08:00',
            'autojp_work_end_time' => $this->formatTime($route->autojp_work_end_time ?? null) ?? '17:00',
            'autojp_working_days' => $this->parseWorkingDays($route->autojp_working_days ?? null),
            'autojp_working_day_labels' => collect($this->parseWorkingDays($route->autojp_working_days ?? null))
                ->map(fn ($day) => $this->weekdayLabel($day))
                ->all(),
        ];
    }

    private function customerListForRoute(int $routeCode, ?int $selectedRouteCode = null): Collection
    {
        return $this->enrichCustomers(
            DB::table('customermaster as customer')
                ->leftJoin('routemaster as route', 'route.routecode', '=', 'customer.routecode')
                ->where('customer.routecode', $routeCode)
                ->when(Schema::hasColumn('customermaster', 'activecustomer'), fn ($query) => $query->where('customer.activecustomer', 1))
                ->orderBy('customer.customername')
                ->get([
                    'customer.customercode',
                    'customer.routecode',
                    'customer.alternatecode',
                    'customer.customername',
                    'customer.fixedlatitude',
                    'customer.fixedlongitude',
                    'customer.delivery_slot_from',
                    'customer.delivery_slot_to',
                    'customer.autojp_priority',
                    'customer.allow_cross_route_jp',
                    'route.routename',
                ]),
            $selectedRouteCode ?? $routeCode
        )->filter(fn (array $customer) => ! empty($customer['has_geo_coordinates']))
            ->values();
    }

    private function candidateCustomers(int $selectedRouteCode, ?int $externalRouteCode, string $search): Collection
    {
        if (! $externalRouteCode && $search === '') {
            return collect();
        }

        return $this->enrichCustomers(
            DB::table('customermaster as customer')
                ->leftJoin('routemaster as route', 'route.routecode', '=', 'customer.routecode')
                ->where('customer.routecode', '<>', $selectedRouteCode)
                ->when(Schema::hasColumn('customermaster', 'allow_cross_route_jp'), fn ($query) => $query->where('customer.allow_cross_route_jp', 1))
                ->when(Schema::hasColumn('customermaster', 'activecustomer'), fn ($query) => $query->where('customer.activecustomer', 1))
                ->when($externalRouteCode, fn ($query) => $query->where('customer.routecode', $externalRouteCode))
                ->when($search !== '', function ($query) use ($search) {
                    $query->where(function ($inner) use ($search) {
                        $inner->where('customer.customername', 'like', '%' . $search . '%')
                            ->orWhere('customer.alternatecode', 'like', '%' . $search . '%')
                            ->orWhere('customer.customercode', 'like', '%' . $search . '%');
                    });
                })
                ->orderBy('route.routename')
                ->orderBy('customer.customername')
                ->limit(100)
                ->get([
                    'customer.customercode',
                    'customer.routecode',
                    'customer.alternatecode',
                    'customer.customername',
                    'customer.fixedlatitude',
                    'customer.fixedlongitude',
                    'customer.delivery_slot_from',
                    'customer.delivery_slot_to',
                    'customer.autojp_priority',
                    'customer.allow_cross_route_jp',
                    'route.routename',
                ]),
            $selectedRouteCode
        )->filter(fn (array $customer) => ! empty($customer['has_geo_coordinates']))
            ->values();
    }

    private function customersByCodes(array $customerCodes, int $selectedRouteCode): Collection
    {
        return $this->enrichCustomers(
            DB::table('customermaster as customer')
                ->leftJoin('routemaster as route', 'route.routecode', '=', 'customer.routecode')
                ->whereIn('customer.customercode', $customerCodes)
                ->when(Schema::hasColumn('customermaster', 'activecustomer'), fn ($query) => $query->where('customer.activecustomer', 1))
                ->get([
                    'customer.customercode',
                    'customer.routecode',
                    'customer.alternatecode',
                    'customer.customername',
                    'customer.fixedlatitude',
                    'customer.fixedlongitude',
                    'customer.delivery_slot_from',
                    'customer.delivery_slot_to',
                    'customer.autojp_priority',
                    'customer.allow_cross_route_jp',
                    'route.routename',
                ]),
            $selectedRouteCode
        )->sortByDesc('score')->values();
    }

    private function enrichCustomers(Collection $rows, int $selectedRouteCode): Collection
    {
        $customerCodes = $rows->pluck('customercode')->map(fn ($value) => (int) $value)->all();

        if ($customerCodes === []) {
            return collect();
        }

        $invoiceDateColumn = Schema::hasTable('invoiceheader') && Schema::hasColumn('invoiceheader', 'actualtransactiondate')
            ? 'actualtransactiondate'
            : 'transactiondate';

        $lastInvoices = Schema::hasTable('invoiceheader')
            ? DB::table('invoiceheader')
                ->selectRaw("customercode, MAX({$invoiceDateColumn}) as last_invoice_date")
                ->whereIn('customercode', $customerCodes)
                ->when(Schema::hasColumn('invoiceheader', 'voidflag'), fn ($query) => $query->where('voidflag', 0))
                ->groupBy('customercode')
                ->pluck('last_invoice_date', 'customercode')
            : collect();

        $lastOrders = Schema::hasTable('salesorderheader')
            ? DB::table('salesorderheader')
                ->selectRaw('customercode, MAX(transactiondate) as last_order_date')
                ->whereIn('customercode', $customerCodes)
                ->when(Schema::hasColumn('salesorderheader', 'voidflag'), fn ($query) => $query->where('voidflag', 0))
                ->groupBy('customercode')
                ->pluck('last_order_date', 'customercode')
            : collect();

        $serviceStats = collect();
        $weekdayStats = collect();
        if (Schema::hasTable('customeroperationscontrol')) {
            $serviceStats = DB::table('customeroperationscontrol')
                ->selectRaw("
                    customercode,
                    COUNT(*) as serviced_visits,
                    AVG(CASE
                        WHEN visitstarttime IS NULL OR visitendtime IS NULL THEN NULL
                        ELSE GREATEST(TIMESTAMPDIFF(MINUTE, CAST(visitstarttime AS TIME), CAST(visitendtime AS TIME)), 1)
                    END) as avg_visit_duration_minutes,
                    AVG(TIME_TO_SEC(CAST(visitstarttime AS TIME))) as avg_start_seconds
                ")
                ->whereIn('customercode', $customerCodes)
                ->groupBy('customercode')
                ->get()
                ->keyBy('customercode');

            $weekdayStats = DB::table('customeroperationscontrol')
                ->selectRaw('customercode, DAYOFWEEK(visitstartdate) as mysql_day, COUNT(*) as visits_count')
                ->whereIn('customercode', $customerCodes)
                ->whereNotNull('visitstartdate')
                ->groupBy('customercode', 'mysql_day')
                ->orderByDesc('visits_count')
                ->get()
                ->groupBy('customercode');
        }

        $scheduledStats = collect();
        if (Schema::hasTable('routesequencecustomerstatus') && Schema::hasTable('startendday')) {
            $scheduledStats = DB::table('routesequencecustomerstatus')
                ->join('startendday', 'startendday.routekey', '=', 'routesequencecustomerstatus.routekey')
                ->select('customercode')
                ->selectRaw('COUNT(*) as scheduled_visits')
                ->whereIn('customercode', $customerCodes)
                ->where('schelduledflag', 1)
                ->groupBy('customercode')
                ->pluck('scheduled_visits', 'customercode');
        }

        return $rows->map(function ($row) use ($selectedRouteCode, $lastInvoices, $lastOrders, $serviceStats, $weekdayStats, $scheduledStats) {
            $customerCode = (int) $row->customercode;
            $serviceRow = $serviceStats->get($customerCode);
            $preferredWeekday = $this->preferredWeekdayFromRows($weekdayStats->get($customerCode, collect()));
            $avgDuration = max((int) round((float) ($serviceRow->avg_visit_duration_minutes ?? 20)), 10);
            $avgStartSeconds = $serviceRow?->avg_start_seconds !== null ? (int) round((float) $serviceRow->avg_start_seconds) : null;
            $lastInvoiceDate = $lastInvoices->get($customerCode);
            $lastOrderDate = $lastOrders->get($customerCode);

            return [
                'customercode' => $customerCode,
                'routecode' => (int) ($row->routecode ?? 0),
                'routename' => (string) ($row->routename ?? ''),
                'alternatecode' => (string) ($row->alternatecode ?? ''),
                'customername' => (string) ($row->customername ?? ''),
                'fixedlatitude' => $this->normalizeCoordinate($row->fixedlatitude ?? null),
                'fixedlongitude' => $this->normalizeCoordinate($row->fixedlongitude ?? null),
                'has_geo_coordinates' => $this->hasGeoCoordinates(
                    $row->fixedlatitude ?? null,
                    $row->fixedlongitude ?? null
                ),
                'delivery_slot_from' => $this->formatTime($row->delivery_slot_from ?? null),
                'delivery_slot_to' => $this->formatTime($row->delivery_slot_to ?? null),
                'autojp_priority' => (int) ($row->autojp_priority ?? 0),
                'allow_cross_route_jp' => (int) ($row->allow_cross_route_jp ?? 0),
                'source' => (int) ($row->routecode ?? 0) === $selectedRouteCode ? 'home' : 'external',
                'last_invoice_date' => $lastInvoiceDate ? Carbon::parse($lastInvoiceDate)->format('Y-m-d') : null,
                'last_order_date' => $lastOrderDate ? Carbon::parse($lastOrderDate)->format('Y-m-d') : null,
                'serviced_visits' => (int) ($serviceRow->serviced_visits ?? 0),
                'scheduled_visits' => (int) ($scheduledStats->get($customerCode) ?? 0),
                'avg_visit_start_time' => $avgStartSeconds !== null ? $this->minutesToTime((int) floor($avgStartSeconds / 60)) : null,
                'avg_visit_duration_minutes' => $avgDuration,
                'preferred_weekday' => $preferredWeekday,
                'preferred_weekday_label' => $preferredWeekday ? $this->weekdayLabel($preferredWeekday) : null,
                'score' => $this->customerScore([
                    'last_invoice_date' => $lastInvoiceDate,
                    'last_order_date' => $lastOrderDate,
                    'serviced_visits' => (int) ($serviceRow->serviced_visits ?? 0),
                    'scheduled_visits' => (int) ($scheduledStats->get($customerCode) ?? 0),
                    'autojp_priority' => (int) ($row->autojp_priority ?? 0),
                    'source' => (int) ($row->routecode ?? 0) === $selectedRouteCode ? 'home' : 'external',
                    'has_geo_coordinates' => $this->hasGeoCoordinates(
                        $row->fixedlatitude ?? null,
                        $row->fixedlongitude ?? null
                    ),
                ]),
            ];
        });
    }

    private function buildPlanRows(Collection $customers, array $workingDays, int $routeStart, int $routeEnd, object $route): array
    {
        $customers = $customers
            ->filter(fn (array $customer) => ! empty($customer['has_geo_coordinates']))
            ->values();

        if ($routeEnd <= $routeStart) {
            return [];
        }

        $selectedRouteCode = (int) $route->routecode;
        $historyContext = $this->routeHistoryContext(
            $selectedRouteCode,
            $customers->pluck('customercode')->map(fn ($value) => (int) $value)->all()
        );
        $routeAnchor = $this->routeAnchorCoordinates($route);
        $sortedCustomers = $customers->sortByDesc('score')->values();
        $dayBuckets = $this->assignCustomersToDays(
            $sortedCustomers,
            $workingDays,
            $routeStart,
            $routeEnd,
            $routeAnchor,
            $historyContext
        );
        $unscheduledRows = [];

        $rows = [];
        foreach ($workingDays as $day) {
            $dayCustomers = collect($dayBuckets[$day] ?? [])->values();

            if ($dayCustomers->isEmpty()) {
                continue;
            }

            $optimizedSequence = $this->optimizeDaySequence(
                $dayCustomers,
                $routeAnchor,
                $historyContext
            );
            $daySchedule = $this->scheduleDaySequence(
                $optimizedSequence,
                $day,
                $routeStart,
                $routeEnd,
                $routeAnchor,
                $historyContext
            );

            if (! $daySchedule['feasible']) {
                foreach ($dayCustomers as $customer) {
                    $unscheduledRows[] = $this->unscheduledPlanRow(
                        $customer,
                        $selectedRouteCode,
                        sprintf(
                            'Preferred day %s, slot %s-%s, duration %d min, geo %s/%s, unscheduled after day optimization: %s, score %.2f',
                            $customer['preferred_weekday_label'] ?? 'n/a',
                            $customer['delivery_slot_from'] ?? '--:--',
                            $customer['delivery_slot_to'] ?? '--:--',
                            $customer['avg_visit_duration_minutes'],
                            $customer['fixedlatitude'] ?? 'n/a',
                            $customer['fixedlongitude'] ?? 'n/a',
                            $daySchedule['reason'] ?? 'route became infeasible',
                            $customer['score']
                        )
                    );
                }

                continue;
            }

            foreach ($daySchedule['entries'] as $index => $entry) {
                $customer = $entry['customer'];
                $rows[] = [
                    'customercode' => $customer['customercode'],
                    'home_routecode' => $customer['routecode'],
                    'assigned_routecode' => $selectedRouteCode,
                    'assigned_weekday' => $day,
                    'assigned_sequence' => $index + 1,
                    'delivery_slot_from' => $customer['delivery_slot_from'],
                    'delivery_slot_to' => $customer['delivery_slot_to'],
                    'planned_start_time' => $this->minutesToTime($entry['start']),
                    'planned_end_time' => $this->minutesToTime($entry['end']),
                    'last_invoice_date' => $customer['last_invoice_date'],
                    'last_order_date' => $customer['last_order_date'],
                    'serviced_visits' => $customer['serviced_visits'],
                    'scheduled_visits' => $customer['scheduled_visits'],
                    'avg_visit_start_time' => $customer['avg_visit_start_time'],
                    'avg_visit_duration_minutes' => $customer['avg_visit_duration_minutes'],
                    'preferred_weekday' => $customer['preferred_weekday'],
                    'score' => $customer['score'],
                    'source' => $customer['source'],
                    'generation_notes' => sprintf(
                        'Preferred day %s, slot %s-%s, duration %d min, geo %s/%s, optimized for %s sequence using %d min %s gap, score %.2f',
                        $customer['preferred_weekday_label'] ?? 'n/a',
                        $customer['delivery_slot_from'] ?? '--:--',
                        $customer['delivery_slot_to'] ?? '--:--',
                        $customer['avg_visit_duration_minutes'],
                        $customer['fixedlatitude'] ?? 'n/a',
                        $customer['fixedlongitude'] ?? 'n/a',
                        $this->weekdayLabel($day),
                        $entry['gap_minutes'],
                        $entry['gap_source'],
                        $customer['score']
                    ),
                ];
            }
        }

        foreach ($dayBuckets['_unscheduled'] ?? [] as $customer) {
            $unscheduledRows[] = $this->unscheduledPlanRow(
                $customer,
                $selectedRouteCode,
                sprintf(
                    'Preferred day %s, slot %s-%s, duration %d min, geo %s/%s, unscheduled: no feasible day after route optimization, score %.2f',
                    $customer['preferred_weekday_label'] ?? 'n/a',
                    $customer['delivery_slot_from'] ?? '--:--',
                    $customer['delivery_slot_to'] ?? '--:--',
                    $customer['avg_visit_duration_minutes'],
                    $customer['fixedlatitude'] ?? 'n/a',
                    $customer['fixedlongitude'] ?? 'n/a',
                    $customer['score']
                )
            );
        }

        return array_merge($rows, $unscheduledRows);
    }

    private function assignCustomersToDays(
        Collection $customers,
        array $workingDays,
        int $routeStart,
        int $routeEnd,
        ?array $routeAnchor,
        array $historyContext
    ): array {
        $dayBuckets = collect($workingDays)->mapWithKeys(fn ($day) => [$day => []])->all();
        $dayBuckets['_unscheduled'] = [];

        foreach ($customers as $customer) {
            $preferredDay = (int) ($customer['preferred_weekday'] ?? 0);
            $candidateDays = $this->orderedCandidateDays($workingDays, $preferredDay);
            $bestAssignment = null;

            foreach ($candidateDays as $day) {
                $tentativeCustomers = collect(array_merge($dayBuckets[$day], [$customer]))->values();
                $optimizedSequence = $this->optimizeDaySequence(
                    $tentativeCustomers,
                    $routeAnchor,
                    $historyContext
                );
                $daySchedule = $this->scheduleDaySequence(
                    $optimizedSequence,
                    $day,
                    $routeStart,
                    $routeEnd,
                    $routeAnchor,
                    $historyContext
                );

                if (! $daySchedule['feasible']) {
                    continue;
                }

                $score = $daySchedule['travel_minutes'] + $daySchedule['total_minutes'];

                if (
                    $bestAssignment === null
                    || $score < $bestAssignment['score']
                    || ($score === $bestAssignment['score'] && $day === $preferredDay)
                ) {
                    $bestAssignment = [
                        'day' => $day,
                        'score' => $score,
                        'customers' => $optimizedSequence->values()->all(),
                    ];
                }
            }

            if ($bestAssignment === null) {
                $dayBuckets['_unscheduled'][] = $customer;
                continue;
            }

            $dayBuckets[$bestAssignment['day']] = $bestAssignment['customers'];
        }

        return $dayBuckets;
    }

    private function optimizeDaySequence(
        Collection $customers,
        ?array $routeAnchor,
        array $historyContext
    ): Collection {
        $customers = $customers->values();

        if ($customers->count() <= 2) {
            return $customers->count() <= 1
                ? $customers
                : $this->buildNearestNeighborSequence($customers, $routeAnchor, $historyContext);
        }

        $seedSequence = $this->buildNearestNeighborSequence($customers, $routeAnchor, $historyContext);

        return $this->improveSequenceWithTwoOpt($seedSequence, $routeAnchor, $historyContext);
    }

    private function buildNearestNeighborSequence(
        Collection $customers,
        ?array $routeAnchor,
        array $historyContext
    ): Collection {
        $remaining = $customers->values();

        if ($remaining->isEmpty()) {
            return collect();
        }

        $startCustomer = $this->chooseSequenceStartCustomer($remaining, $routeAnchor, $historyContext);
        $sequence = collect([$startCustomer]);
        $remaining = $remaining
            ->reject(fn (array $customer) => (int) $customer['customercode'] === (int) $startCustomer['customercode'])
            ->values();

        while ($remaining->isNotEmpty()) {
            $previous = $sequence->last();
            $nextCustomer = $remaining
                ->sortBy(fn (array $customer) => $this->customerToCustomerTravelMinutes($previous, $customer, $historyContext))
                ->first();

            $sequence->push($nextCustomer);
            $remaining = $remaining
                ->reject(fn (array $customer) => (int) $customer['customercode'] === (int) $nextCustomer['customercode'])
                ->values();
        }

        return $sequence->values();
    }

    private function chooseSequenceStartCustomer(
        Collection $customers,
        ?array $routeAnchor,
        array $historyContext
    ): array {
        if ($routeAnchor !== null) {
            return $customers
                ->sortBy(fn (array $customer) => $this->firstStopTravelMinutes($customer, $routeAnchor, $historyContext))
                ->first();
        }

        return $customers
            ->sortBy(function (array $candidate) use ($customers, $historyContext) {
                $sum = $customers
                    ->reject(fn (array $customer) => (int) $customer['customercode'] === (int) $candidate['customercode'])
                    ->sum(fn (array $customer) => $this->customerToCustomerTravelMinutes($candidate, $customer, $historyContext));

                return sprintf('%010d-%020.2f', $sum, -1 * ((float) $candidate['score']));
            })
            ->first();
    }

    private function improveSequenceWithTwoOpt(
        Collection $sequence,
        ?array $routeAnchor,
        array $historyContext
    ): Collection {
        $best = $sequence->values();
        $bestCost = $this->sequenceTravelMinutes($best, $routeAnchor, $historyContext);
        $improved = true;

        while ($improved) {
            $improved = false;
            $count = $best->count();

            for ($i = 1; $i < $count - 1; $i++) {
                for ($j = $i + 1; $j < $count; $j++) {
                    $candidate = $this->twoOptSwap($best, $i, $j);
                    $candidateCost = $this->sequenceTravelMinutes($candidate, $routeAnchor, $historyContext);

                    if ($candidateCost < $bestCost) {
                        $best = $candidate;
                        $bestCost = $candidateCost;
                        $improved = true;
                    }
                }
            }
        }

        return $best->values();
    }

    private function twoOptSwap(Collection $sequence, int $start, int $end): Collection
    {
        return collect(array_merge(
            $sequence->slice(0, $start)->all(),
            array_reverse($sequence->slice($start, $end - $start + 1)->values()->all()),
            $sequence->slice($end + 1)->all()
        ))->values();
    }

    private function scheduleDaySequence(
        Collection $sequence,
        int $day,
        int $routeStart,
        int $routeEnd,
        ?array $routeAnchor,
        array $historyContext
    ): array {
        $entries = [];
        $currentTime = $routeStart;
        $previousCustomer = null;
        $travelMinutes = 0;

        foreach ($sequence as $customer) {
            $gapContext = $previousCustomer === null
                ? $this->firstStopGapContext($customer, $routeAnchor, $historyContext)
                : $this->transitionGapContext(
                    (int) $previousCustomer['customercode'],
                    isset($previousCustomer['fixedlatitude']) ? (float) $previousCustomer['fixedlatitude'] : null,
                    isset($previousCustomer['fixedlongitude']) ? (float) $previousCustomer['fixedlongitude'] : null,
                    $customer,
                    $historyContext
                );

            $slotStart = $this->timeToMinutes($customer['delivery_slot_from'] ?? null);
            $slotEnd = $this->timeToMinutes($customer['delivery_slot_to'] ?? null);
            $duration = max((int) ($customer['avg_visit_duration_minutes'] ?? 20), 10);
            $start = max($currentTime + $gapContext['minutes'], $routeStart, $slotStart ?? $routeStart);
            $allowedEnd = min($routeEnd, $slotEnd ?? $routeEnd);
            $end = $start + $duration;

            if ($start >= $allowedEnd || $end > $allowedEnd) {
                return [
                    'feasible' => false,
                    'entries' => [],
                    'travel_minutes' => $travelMinutes,
                    'total_minutes' => max($currentTime - $routeStart, 0),
                    'reason' => sprintf(
                        'customer %s does not fit on %s within slot %s-%s',
                        $customer['customercode'],
                        $this->weekdayLabel($day),
                        $customer['delivery_slot_from'] ?? '--:--',
                        $customer['delivery_slot_to'] ?? '--:--'
                    ),
                ];
            }

            $entries[] = [
                'customer' => $customer,
                'start' => $start,
                'end' => $end,
                'gap_minutes' => $gapContext['minutes'],
                'gap_source' => $gapContext['source'],
            ];

            $travelMinutes += (int) $gapContext['minutes'];
            $currentTime = $end;
            $previousCustomer = $customer;
        }

        return [
            'feasible' => true,
            'entries' => $entries,
            'travel_minutes' => $travelMinutes,
            'total_minutes' => max($currentTime - $routeStart, 0),
            'reason' => null,
        ];
    }

    private function sequenceTravelMinutes(
        Collection $sequence,
        ?array $routeAnchor,
        array $historyContext
    ): int {
        $previousCustomer = null;
        $total = 0;

        foreach ($sequence as $customer) {
            $total += $previousCustomer === null
                ? $this->firstStopTravelMinutes($customer, $routeAnchor, $historyContext)
                : $this->customerToCustomerTravelMinutes($previousCustomer, $customer, $historyContext);

            $previousCustomer = $customer;
        }

        return $total;
    }

    private function firstStopTravelMinutes(array $customer, ?array $routeAnchor, array $historyContext): int
    {
        return $this->firstStopGapContext($customer, $routeAnchor, $historyContext)['minutes'];
    }

    private function customerToCustomerTravelMinutes(array $fromCustomer, array $toCustomer, array $historyContext): int
    {
        return $this->transitionGapContext(
            (int) $fromCustomer['customercode'],
            isset($fromCustomer['fixedlatitude']) ? (float) $fromCustomer['fixedlatitude'] : null,
            isset($fromCustomer['fixedlongitude']) ? (float) $fromCustomer['fixedlongitude'] : null,
            $toCustomer,
            $historyContext
        )['minutes'];
    }

    private function firstStopGapContext(array $customer, ?array $routeAnchor, array $historyContext): array
    {
        if ($routeAnchor !== null) {
            $coordinateGap = $this->coordinateGapMinutes(
                $routeAnchor['latitude'],
                $routeAnchor['longitude'],
                isset($customer['fixedlatitude']) ? (float) $customer['fixedlatitude'] : null,
                isset($customer['fixedlongitude']) ? (float) $customer['fixedlongitude'] : null
            );

            if ($coordinateGap !== null) {
                return [
                    'minutes' => $coordinateGap,
                    'source' => 'route-anchor coordinate-estimated',
                ];
            }
        }

        $customerCode = (int) $customer['customercode'];

        return [
            'minutes' => (int) ($historyContext['first_customer_gap_minutes'][$customerCode] ?? $historyContext['default_gap_minutes'] ?? 10),
            'source' => 'historical first-stop',
        ];
    }

    private function routeAnchorCoordinates(object $route): ?array
    {
        $candidates = [
            ['latitude' => $route->latitude ?? null, 'longitude' => $route->longitude ?? null],
            ['latitude' => $route->fixedlatitude ?? null, 'longitude' => $route->fixedlongitude ?? null],
            ['latitude' => $route->routelatitude ?? null, 'longitude' => $route->routelongitude ?? null],
        ];

        foreach ($candidates as $candidate) {
            $latitude = $this->normalizeCoordinate($candidate['latitude']);
            $longitude = $this->normalizeCoordinate($candidate['longitude']);

            if ($this->validCoordinatePair($latitude, $longitude)) {
                return [
                    'latitude' => $latitude,
                    'longitude' => $longitude,
                ];
            }
        }

        return null;
    }

    private function unscheduledPlanRow(array $customer, int $selectedRouteCode, string $notes): array
    {
        return [
            'customercode' => $customer['customercode'],
            'home_routecode' => $customer['routecode'],
            'assigned_routecode' => $selectedRouteCode,
            'assigned_weekday' => null,
            'assigned_sequence' => 0,
            'delivery_slot_from' => $customer['delivery_slot_from'],
            'delivery_slot_to' => $customer['delivery_slot_to'],
            'planned_start_time' => null,
            'planned_end_time' => null,
            'last_invoice_date' => $customer['last_invoice_date'],
            'last_order_date' => $customer['last_order_date'],
            'serviced_visits' => $customer['serviced_visits'],
            'scheduled_visits' => $customer['scheduled_visits'],
            'avg_visit_start_time' => $customer['avg_visit_start_time'],
            'avg_visit_duration_minutes' => $customer['avg_visit_duration_minutes'],
            'preferred_weekday' => $customer['preferred_weekday'],
            'score' => $customer['score'],
            'source' => $customer['source'],
            'generation_notes' => $notes,
        ];
    }

    private function findAssignmentForCustomer(
        array $candidateDays,
        array &$capacityByDay,
        int $routeStart,
        int $routeEnd,
        ?int $slotStart,
        ?int $slotEnd,
        int $duration,
        array $customer,
        array $historyContext
    ): array {
        $customerCode = (int) $customer['customercode'];

        foreach ($candidateDays as $day) {
            $dayState = $capacityByDay[$day];
            $transitionGap = $this->transitionGapContext(
                $dayState['last_customer_code'],
                $dayState['last_customer_latitude'],
                $dayState['last_customer_longitude'],
                $customer,
                $historyContext
            );
            $start = max($dayState['cursor'] + $transitionGap['minutes'], $routeStart, $slotStart ?? $routeStart);
            $allowedEnd = min($routeEnd, $slotEnd ?? $routeEnd);
            $end = $start + $duration;

            if ($start >= $allowedEnd) {
                continue;
            }

            if ($end <= $allowedEnd) {
                $capacityByDay[$day]['cursor'] = $end;
                $capacityByDay[$day]['last_customer_code'] = $customerCode;
                $capacityByDay[$day]['last_customer_latitude'] = $customer['fixedlatitude'] ?? null;
                $capacityByDay[$day]['last_customer_longitude'] = $customer['fixedlongitude'] ?? null;

                return [
                    'day' => $day,
                    'start' => $start,
                    'end' => $end,
                    'notes' => sprintf(
                        'scheduled on %s within route window %s-%s using %d min %s gap',
                        $this->weekdayLabel($day),
                        $this->minutesToTime($routeStart) ?? '--:--',
                        $this->minutesToTime($routeEnd) ?? '--:--',
                        $transitionGap['minutes'],
                        $transitionGap['source']
                    ),
                ];
            }
        }

        return [
            'day' => null,
            'start' => null,
            'end' => null,
            'notes' => sprintf(
                'unscheduled: no remaining capacity within route window %s-%s',
                $this->minutesToTime($routeStart) ?? '--:--',
                $this->minutesToTime($routeEnd) ?? '--:--'
            ),
        ];
    }

    private function routeHistoryContext(int $routeCode, array $customerCodes): array
    {
        $routeKeys = collect();
        if (Schema::hasTable('startendday')) {
            $routeKeys = DB::table('startendday')
                ->where('routecode', $routeCode)
                ->whereNotNull('routestartdate')
                ->orderByDesc('routestartdate')
                ->limit(180)
                ->pluck('routekey');
        }

        if ($routeKeys->isEmpty()) {
            $routeKeys = DB::table('customeroperationscontrol')
                ->where('routecode', $routeCode)
                ->orderByDesc('visitstartdate')
                ->limit(180)
                ->pluck('routekey')
                ->unique()
                ->values();
        }

        if ($routeKeys->isEmpty()) {
            return [
                'first_customer_gap_minutes' => [],
                'pair_gap_minutes' => [],
                'default_gap_minutes' => 10,
            ];
        }

        $startByRouteKey = Schema::hasTable('startendday')
            ? DB::table('startendday')
                ->whereIn('routekey', $routeKeys->all())
                ->pluck('routestarttime', 'routekey')
            : collect();

        $visitRows = DB::table('customeroperationscontrol')
            ->whereIn('routekey', $routeKeys->all())
            ->where('routecode', $routeCode)
            ->when(Schema::hasColumn('customeroperationscontrol', 'voidflag'), fn ($query) => $query->where('voidflag', 0))
            ->whereNotNull('visitstartdate')
            ->whereNotNull('visitstarttime')
            ->whereNotNull('visitendtime')
            ->orderBy('routekey')
            ->orderBy('visitstartdate')
            ->orderBy('visitkey')
            ->get([
                'routekey',
                'visitkey',
                'customercode',
                'visitstartdate',
                'visitstarttime',
                'visitendtime',
            ])
            ->groupBy('routekey');

        $firstCustomerGapSamples = [];
        $pairGapSamples = [];
        $fallbackGapSamples = [];

        foreach ($visitRows as $routeKey => $routeVisits) {
            $orderedVisits = $routeVisits
                ->sortBy(function ($visit) {
                    return sprintf(
                        '%s-%010d',
                        $this->normalizedTimeSortableKey($visit->visitstarttime ?? null),
                        (int) ($visit->visitkey ?? 0)
                    );
                })
                ->values();

            if ($orderedVisits->isEmpty()) {
                continue;
            }

            $firstVisit = $orderedVisits->first();
            $routeStartTime = $startByRouteKey->get($routeKey);
            $firstGap = $this->timeDifferenceMinutes($routeStartTime, $firstVisit->visitstarttime ?? null);
            if ($firstGap !== null && $firstGap >= 0) {
                $firstCustomerGapSamples[(int) $firstVisit->customercode][] = $firstGap;
                $fallbackGapSamples[] = $firstGap;
            }

            for ($index = 1; $index < $orderedVisits->count(); $index++) {
                $previous = $orderedVisits[$index - 1];
                $current = $orderedVisits[$index];
                $gap = $this->timeDifferenceMinutes($previous->visitendtime ?? null, $current->visitstarttime ?? null);

                if ($gap === null || $gap < 0) {
                    continue;
                }

                $pairGapSamples[$this->pairGapKey((int) $previous->customercode, (int) $current->customercode)][] = $gap;
                $fallbackGapSamples[] = $gap;
            }
        }

        return [
            'first_customer_gap_minutes' => collect($firstCustomerGapSamples)
                ->map(fn ($samples) => $this->medianMinutes($samples))
                ->all(),
            'pair_gap_minutes' => collect($pairGapSamples)
                ->map(fn ($samples) => $this->medianMinutes($samples))
                ->all(),
            'default_gap_minutes' => $this->medianMinutes($fallbackGapSamples, 10),
        ];
    }

    private function transitionGapMinutes(?int $previousCustomerCode, int $customerCode, array $historyContext): int
    {
        if ($previousCustomerCode === null) {
            return (int) ($historyContext['first_customer_gap_minutes'][$customerCode] ?? $historyContext['default_gap_minutes'] ?? 10);
        }

        return (int) ($historyContext['pair_gap_minutes'][$this->pairGapKey($previousCustomerCode, $customerCode)] ?? $historyContext['default_gap_minutes'] ?? 10);
    }

    private function transitionGapContext(
        ?int $previousCustomerCode,
        ?float $previousLatitude,
        ?float $previousLongitude,
        array $customer,
        array $historyContext
    ): array {
        $customerCode = (int) $customer['customercode'];

        if ($previousCustomerCode === null) {
            return [
                'minutes' => (int) ($historyContext['first_customer_gap_minutes'][$customerCode] ?? $historyContext['default_gap_minutes'] ?? 10),
                'source' => 'historical first-stop',
            ];
        }

        $coordinateGap = $this->coordinateGapMinutes(
            $previousLatitude,
            $previousLongitude,
            isset($customer['fixedlatitude']) ? (float) $customer['fixedlatitude'] : null,
            isset($customer['fixedlongitude']) ? (float) $customer['fixedlongitude'] : null
        );

        if ($coordinateGap !== null) {
            return [
                'minutes' => $coordinateGap,
                'source' => 'coordinate-estimated',
            ];
        }

        $pairGap = $historyContext['pair_gap_minutes'][$this->pairGapKey($previousCustomerCode, $customerCode)] ?? null;
        if ($pairGap !== null) {
            return [
                'minutes' => (int) $pairGap,
                'source' => 'historical customer-to-customer fallback',
            ];
        }

        return [
            'minutes' => (int) ($historyContext['default_gap_minutes'] ?? 10),
            'source' => 'route median fallback',
        ];
    }

    private function pairGapKey(int $fromCustomerCode, int $toCustomerCode): string
    {
        return $fromCustomerCode . ':' . $toCustomerCode;
    }

    private function timeDifferenceMinutes(mixed $fromTime, mixed $toTime): ?int
    {
        $fromMinutes = $this->timeToMinutes($fromTime !== null ? (string) $fromTime : null);
        $toMinutes = $this->timeToMinutes($toTime !== null ? (string) $toTime : null);

        if ($fromMinutes === null || $toMinutes === null) {
            return null;
        }

        return $toMinutes - $fromMinutes;
    }

    private function normalizedTimeSortableKey(mixed $time): string
    {
        $minutes = $this->timeToMinutes($time !== null ? (string) $time : null);

        return $minutes === null ? '9999' : sprintf('%04d', $minutes);
    }

    private function medianMinutes(array $samples, int $default = 10): int
    {
        $values = collect($samples)
            ->filter(fn ($value) => is_numeric($value) && (int) $value >= 0)
            ->map(fn ($value) => (int) round((float) $value))
            ->sort()
            ->values();

        if ($values->isEmpty()) {
            return $default;
        }

        $count = $values->count();
        $middle = intdiv($count, 2);

        if ($count % 2 === 1) {
            return (int) $values[$middle];
        }

        return (int) round((((int) $values[$middle - 1]) + ((int) $values[$middle])) / 2);
    }

    private function coordinateGapMinutes(
        ?float $fromLatitude,
        ?float $fromLongitude,
        ?float $toLatitude,
        ?float $toLongitude
    ): ?int {
        if (
            ! $this->validCoordinatePair($fromLatitude, $fromLongitude)
            || ! $this->validCoordinatePair($toLatitude, $toLongitude)
        ) {
            return null;
        }

        $distanceKm = $this->haversineDistanceKm($fromLatitude, $fromLongitude, $toLatitude, $toLongitude);
        $averageSpeedKmPerHour = 25.0;
        $stopBufferMinutes = 3;
        $travelMinutes = (int) ceil(($distanceKm / $averageSpeedKmPerHour) * 60);

        return max($travelMinutes + $stopBufferMinutes, $stopBufferMinutes);
    }

    private function validCoordinatePair(?float $latitude, ?float $longitude): bool
    {
        if ($latitude === null || $longitude === null) {
            return false;
        }

        return $latitude >= -90
            && $latitude <= 90
            && $longitude >= -180
            && $longitude <= 180
            && ! ($latitude === 0.0 && $longitude === 0.0);
    }

    private function haversineDistanceKm(float $fromLatitude, float $fromLongitude, float $toLatitude, float $toLongitude): float
    {
        $earthRadiusKm = 6371.0;
        $latitudeDelta = deg2rad($toLatitude - $fromLatitude);
        $longitudeDelta = deg2rad($toLongitude - $fromLongitude);

        $a = sin($latitudeDelta / 2) ** 2
            + cos(deg2rad($fromLatitude)) * cos(deg2rad($toLatitude))
            * sin($longitudeDelta / 2) ** 2;

        return $earthRadiusKm * (2 * atan2(sqrt($a), sqrt(1 - $a)));
    }

    private function orderedCandidateDays(array $workingDays, int $preferredDay): array
    {
        if ($preferredDay === 0 || ! in_array($preferredDay, $workingDays, true)) {
            return $workingDays;
        }

        $remaining = array_values(array_filter($workingDays, fn ($day) => $day !== $preferredDay));

        return array_merge([$preferredDay], $remaining);
    }

    private function planPayload(int $planId): ?array
    {
        $plan = AutoJpPlanHeader::query()->with('items')->find($planId);
        if (! $plan) {
            return null;
        }

        $customerMeta = DB::table('customermaster as customer')
            ->leftJoin('routemaster as route', 'route.routecode', '=', 'customer.routecode')
            ->whereIn('customer.customercode', $plan->items->pluck('customercode')->all())
            ->get([
                'customer.customercode',
                'customer.alternatecode',
                'customer.customername',
                'customer.fixedlatitude',
                'customer.fixedlongitude',
                'route.routename',
            ])
            ->keyBy('customercode');

        return [
            'id' => $plan->id,
            'routecode' => $plan->routecode,
            'week_number' => $plan->week_number,
            'status' => $plan->status,
            'generated_at' => optional($plan->generated_at)->format('d-m-Y H:i'),
            'published_at' => optional($plan->published_at)->format('d-m-Y H:i'),
            'items' => $plan->items
                ->sortBy([
                    ['assigned_weekday', 'asc'],
                    ['assigned_sequence', 'asc'],
                ])
                ->values()
                ->map(function ($item) use ($customerMeta) {
                    $customer = $customerMeta->get($item->customercode);

                    return [
                        'customercode' => (int) $item->customercode,
                        'alternatecode' => (string) ($customer->alternatecode ?? ''),
                        'customername' => (string) ($customer->customername ?? ''),
                        'home_routename' => (string) ($customer->routename ?? ''),
                        'fixedlatitude' => $this->normalizeCoordinate($customer->fixedlatitude ?? null),
                        'fixedlongitude' => $this->normalizeCoordinate($customer->fixedlongitude ?? null),
                        'assigned_weekday' => $item->assigned_weekday !== null ? (int) $item->assigned_weekday : null,
                        'assigned_weekday_label' => $item->assigned_weekday !== null
                            ? $this->weekdayLabel((int) $item->assigned_weekday)
                            : 'Unscheduled',
                        'assigned_sequence' => (int) $item->assigned_sequence,
                        'delivery_slot_from' => $this->formatTime($item->delivery_slot_from),
                        'delivery_slot_to' => $this->formatTime($item->delivery_slot_to),
                        'planned_start_time' => $this->formatTime($item->planned_start_time),
                        'planned_end_time' => $this->formatTime($item->planned_end_time),
                        'last_invoice_date' => $item->last_invoice_date,
                        'last_order_date' => $item->last_order_date,
                        'serviced_visits' => (int) $item->serviced_visits,
                        'scheduled_visits' => (int) $item->scheduled_visits,
                        'avg_visit_start_time' => $this->formatTime($item->avg_visit_start_time),
                        'avg_visit_duration_minutes' => (int) $item->avg_visit_duration_minutes,
                        'score' => (float) $item->score,
                        'source' => (string) $item->source,
                        'generation_notes' => (string) ($item->generation_notes ?? ''),
                    ];
                })
                ->all(),
        ];
    }

    private function googleMapsApiKey(): string
    {
        return (string) (env('GOOGLE_MAPS_API_KEY')
            ?: env('GOOGLE_MAP_API_KEY')
            ?: env('VITE_GOOGLE_MAPS_API_KEY')
            ?: '');
    }

    private function weekOptions(): array
    {
        return collect($this->allowedWeeks())
            ->map(fn ($week) => ['id' => $week, 'label' => 'Week ' . $week])
            ->all();
    }

    private function allowedWeeks(): array
    {
        $flag = (int) (DB::table('setup')->value('routesequenceplanflag') ?? 1);

        return $flag === 1 ? [9] : [1, 2, 3, 4];
    }

    private function defaultWeek(): int
    {
        $weeks = $this->allowedWeeks();

        return $weeks[0];
    }

    private function parseWorkingDays(?string $value): array
    {
        $days = collect(explode(',', (string) $value))
            ->map(fn ($day) => (int) trim($day))
            ->filter(fn ($day) => $day >= 1 && $day <= 7)
            ->unique()
            ->values()
            ->all();

        return $days !== [] ? $days : [7, 1, 2, 3, 4];
    }

    private function preferredWeekdayFromRows(Collection $rows): ?int
    {
        $first = $rows->first();
        if (! $first || ! isset($first->mysql_day)) {
            return null;
        }

        return match ((int) $first->mysql_day) {
            1 => 7,
            2 => 1,
            3 => 2,
            4 => 3,
            5 => 4,
            6 => 5,
            7 => 6,
            default => null,
        };
    }

    private function customerScore(array $data): float
    {
        $score = 0.0;

        if (! empty($data['last_invoice_date'])) {
            $score += max(60 - Carbon::parse($data['last_invoice_date'])->diffInDays(now()), 0);
        }

        if (! empty($data['last_order_date'])) {
            $score += max(40 - Carbon::parse($data['last_order_date'])->diffInDays(now()), 0);
        }

        $score += min((int) ($data['serviced_visits'] ?? 0), 12) * 3;
        $score += min((int) ($data['scheduled_visits'] ?? 0), 12) * 2;
        $score += (int) ($data['autojp_priority'] ?? 0) * 1.5;
        $score += ($data['source'] ?? 'home') === 'home' ? 25 : 10;
        $score += ! empty($data['has_geo_coordinates']) ? 15 : -100;

        return round($score, 2);
    }

    private function hasGeoCoordinates(mixed $latitude, mixed $longitude): bool
    {
        $lat = $this->normalizeCoordinate($latitude);
        $lng = $this->normalizeCoordinate($longitude);

        if ($lat === null || $lng === null) {
            return false;
        }

        return $lat >= -90
            && $lat <= 90
            && $lng >= -180
            && $lng <= 180
            && ! ($lat === 0.0 && $lng === 0.0);
    }

    private function normalizeCoordinate(mixed $value): ?float
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (! is_numeric($value)) {
            return null;
        }

        return round((float) $value, 6);
    }

    private function timeToMinutes(?string $time): ?int
    {
        $time = $this->formatTime($time);
        if ($time === null) {
            return null;
        }

        [$hours, $minutes] = array_map('intval', explode(':', $time));

        return ($hours * 60) + $minutes;
    }

    private function minutesToTime(?int $minutes): ?string
    {
        if ($minutes === null) {
            return null;
        }

        $hours = (int) floor($minutes / 60);
        $mins = $minutes % 60;

        return sprintf('%02d:%02d', $hours, $mins);
    }

    private function formatTime(mixed $value): ?string
    {
        $value = trim((string) ($value ?? ''));

        return $value === '' ? null : substr($value, 0, 5);
    }

    private function weekdayLabel(int $day): string
    {
        return match ($day) {
            1 => 'Monday',
            2 => 'Tuesday',
            3 => 'Wednesday',
            4 => 'Thursday',
            5 => 'Friday',
            6 => 'Saturday',
            7 => 'Sunday',
            default => 'Unknown',
        };
    }
}
