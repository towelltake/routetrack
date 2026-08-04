<?php

namespace App\Http\Controllers\Inventory;

use App\Http\Controllers\Controller;
use App\Services\AccessScopeService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class TargetCommissionController extends Controller
{
    public function index(Request $request): Response
    {
        $scope = app(AccessScopeService::class);
        $user = $request->user();
        $allowedPerPage = [10, 25, 50, 100];
        $perPage = (int) $request->input('per_page', 10);
        $search = trim((string) $request->input('search', ''));
        $year = (int) $request->input('year', now()->year);
        $routeGoalAlias = DB::getTablePrefix() . 'rg';
        $routeAlias = DB::getTablePrefix() . 'route';
        $salesmanAlias = DB::getTablePrefix() . 'sales';

        if (!in_array($perPage, $allowedPerPage, true)) {
            $perPage = 10;
        }

        if ($year < 2000 || $year > 2100) {
            $year = (int) now()->year;
        }

        $startOfYear = Carbon::create($year, 1, 1)->startOfDay()->toDateString();
        $endOfYear = Carbon::create($year, 12, 31)->endOfDay()->toDateString();

        $query = DB::table('routegoal as rg')
            ->join('routemaster as route', 'route.routecode', '=', 'rg.routecode')
            ->join('salesman as sales', 'sales.salesmancode', '=', 'rg.salesmancode')
            ->whereDate('rg.fromdate', '<=', $endOfYear)
            ->whereDate('rg.todate', '>=', $startOfYear)
            ->when($search !== '', function ($builder) use ($search) {
                $builder->where(function ($inner) use ($search) {
                    $inner
                        ->where('sales.salesmancode', 'like', "%{$search}%")
                        ->orWhere('sales.salesmanname1', 'like', "%{$search}%")
                        ->orWhere('sales.arbsalesmanname1', 'like', "%{$search}%")
                        ->orWhere('route.routecode', 'like', "%{$search}%")
                        ->orWhere('route.routename', 'like', "%{$search}%")
                        ->orWhere('route.arbroutename', 'like', "%{$search}%");
                });
            })
            ->selectRaw("
                MIN({$routeGoalAlias}.primary_key) as primary_key,
                {$routeGoalAlias}.salesmancode,
                {$routeGoalAlias}.routecode,
                MAX({$salesmanAlias}.salesmanname1) as salesmanname1,
                MAX({$salesmanAlias}.arbsalesmanname1) as arbsalesmanname1,
                MAX({$routeAlias}.routename) as routename,
                MAX({$routeAlias}.arbroutename) as arbroutename,
                COUNT(*) as target_count,
                MIN({$routeGoalAlias}.fromdate) as fromdate,
                MAX({$routeGoalAlias}.todate) as todate
            ")
            ->groupBy('rg.salesmancode', 'rg.routecode')
            ->orderBy('rg.salesmancode');

        $scope->scopeQuery($user, $query, 'route', 'rg.routecode');

        $records = $query
            ->paginate($perPage)
            ->withQueryString()
            ->through(fn ($record) => [
                'primary_key' => (int) $record->primary_key,
                'salesmancode' => (int) $record->salesmancode,
                'routecode' => (int) $record->routecode,
                'salesmanname1' => $record->salesmanname1 ?? '',
                'arbsalesmanname1' => $record->arbsalesmanname1 ?? '',
                'routename' => $record->routename ?? '',
                'arbroutename' => $record->arbroutename ?? '',
                'target_count' => (int) $record->target_count,
                'fromdate' => $this->formatDate($record->fromdate),
                'todate' => $this->formatDate($record->todate),
            ]);

        return Inertia::render('inventory/targetcommission/Index', [
            'records' => $records,
            'filters' => [
                'search' => $search,
                'per_page' => $perPage,
                'year' => $year,
            ],
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('inventory/targetcommission/Create', $this->detailProps());
    }

    public function show(int $targetcommission): Response
    {
        $this->assertTargetCommissionAccess($targetcommission);

        return Inertia::render('inventory/targetcommission/View', $this->detailProps($targetcommission));
    }

    public function edit(int $targetcommission): Response
    {
        $this->assertTargetCommissionAccess($targetcommission);

        return Inertia::render('inventory/targetcommission/Edit', $this->detailProps($targetcommission));
    }

    public function store(): RedirectResponse
    {
        return redirect()->route('inventory.targetcommission.create');
    }

    public function update(int $targetcommission): RedirectResponse
    {
        return redirect()->route('inventory.targetcommission.edit', $targetcommission);
    }

    public function destroy(int $targetcommission): RedirectResponse
    {
        $record = DB::table('routegoal')->where('primary_key', $targetcommission)->first();
        abort_unless($record, 404);
        $this->assertRouteAccess((int) $record->routecode);

        DB::table('routegoal')
            ->where('salesmancode', $record->salesmancode)
            ->where('routecode', $record->routecode)
            ->delete();

        return redirect()
            ->route('inventory.targetcommission.index')
            ->with('success', 'Target & Commission removed successfully.');
    }

    public function salesmanMeta(int $salesman): JsonResponse
    {
        $context = $this->resolveSalesmanContext($salesman);

        $existing = DB::table('routegoal')
            ->where('salesmancode', $context['salesmancode'])
            ->where('routecode', $context['routecode'])
            ->orderByDesc('fromdate')
            ->orderBy('targettype')
            ->get();

        return response()->json([
            'route' => [
                'routecode' => $context['routecode'],
                'routename' => $context['routename'] ?? '',
                'arbroutename' => $context['arbroutename'] ?? '',
                'salesmantargetdays' => $context['salesmantargetdays'] ?? 1,
            ],
            'records_count' => $existing->count(),
            'targettype' => $existing->first()->targettype ?? null,
            'lines' => $this->lineRecords($context['salesmancode'], $context['routecode']),
        ]);
    }

    public function packageUpcStatus(int $package): JsonResponse
    {
        $count = DB::table('itemmaster')
            ->where('packagecode', $package)
            ->whereNotNull('unitspercase')
            ->distinct('unitspercase')
            ->count('unitspercase');

        return response()->json([
            'count' => $count,
            'allow_case' => $count === 1,
        ]);
    }

    public function storeLine(Request $request): JsonResponse
    {
        $data = $this->validateLineInput($request);
        $context = $this->resolveSalesmanContext(
            (int) $data['salesmancode'],
            isset($data['routecode']) ? (int) $data['routecode'] : null
        );
        $this->assertRouteAccess($context['routecode']);
        $targetType = (int) $data['targettype'];
        $packageNumber = (int) $data['packagenumber'];
        $fromDate = Carbon::parse($data['fromdate'])->startOfDay();
        $toDate = Carbon::parse($data['todate'])->startOfDay();

        $this->validateTargetGroupRules($targetType, $packageNumber);
        $this->guardDuplicateRange($context['salesmancode'], $context['routecode'], $targetType, $packageNumber, $fromDate, $toDate);

        $username = $this->username();
        $quantity = $this->normalizeQuantity(
            (float) $data['quantity'],
            $packageNumber,
            (bool) ($data['is_case'] ?? false),
        );

        DB::table('routegoal')->insert([
            'routecode' => $context['routecode'],
            'salesmancode' => $context['salesmancode'],
            'packagenumber' => $packageNumber,
            'fromdate' => $fromDate->toDateString(),
            'todate' => $toDate->toDateString(),
            'quantity' => $quantity,
            'achievequantity' => 0,
            'achieveamount' => 0,
            'targettype' => $targetType,
            'commision' => (float) $data['commision'],
            'insentivepercent' => (float) ($data['insentivepercent'] ?? 0),
            'insentive' => (float) ($data['insentive'] ?? 0),
            'created' => $username,
            'modified' => $username,
            'cdat' => now(),
            'mdat' => now(),
        ]);

        return response()->json([
            'lines' => $this->lineRecords($context['salesmancode'], $context['routecode']),
        ]);
    }

    public function updateLine(Request $request, int $targetcommission): JsonResponse
    {
        $record = DB::table('routegoal')->where('primary_key', $targetcommission)->first();
        abort_unless($record, 404);
        $this->assertRouteAccess((int) $record->routecode);

        $data = $request->validate([
            'fromdate' => ['required', 'date'],
            'todate' => ['required', 'date', 'after_or_equal:fromdate'],
            'quantity' => ['required', 'numeric'],
            'commision' => ['required', 'numeric'],
            'insentivepercent' => ['nullable', 'numeric'],
            'insentive' => ['nullable', 'numeric'],
        ]);

        $fromDate = Carbon::parse($data['fromdate'])->startOfDay();
        $toDate = Carbon::parse($data['todate'])->startOfDay();

        $this->guardDuplicateRange(
            (int) $record->salesmancode,
            (int) $record->routecode,
            (int) $record->targettype,
            (int) $record->packagenumber,
            $fromDate,
            $toDate,
            (int) $record->primary_key,
        );

        DB::table('routegoal')
            ->where('primary_key', $targetcommission)
            ->update([
                'fromdate' => $fromDate->toDateString(),
                'todate' => $toDate->toDateString(),
                'quantity' => (float) $data['quantity'],
                'commision' => (float) $data['commision'],
                'insentivepercent' => (float) ($data['insentivepercent'] ?? 0),
                'insentive' => (float) ($data['insentive'] ?? 0),
                'modified' => $this->username(),
                'mdat' => now(),
            ]);

        return response()->json([
            'lines' => $this->lineRecords((int) $record->salesmancode, (int) $record->routecode),
        ]);
    }

    public function destroyLine(int $targetcommission): JsonResponse
    {
        $record = DB::table('routegoal')->where('primary_key', $targetcommission)->first();
        abort_unless($record, 404);
        $this->assertRouteAccess((int) $record->routecode);

        DB::table('routegoal')->where('primary_key', $targetcommission)->delete();

        return response()->json([
            'lines' => $this->lineRecords((int) $record->salesmancode, (int) $record->routecode),
        ]);
    }

    private function detailProps(?int $primaryKey = null): array
    {
        $context = $primaryKey ? $this->existingContext($primaryKey) : null;

        return [
            'detailData' => [
                'context' => $context,
                'lines' => $context ? $this->lineRecords((int) $context['salesmancode']) : [],
            ],
            'lookupOptions' => [
                'salesmen' => $this->salesmanOptions(),
                'packages' => $this->packageOptions(),
                'targetTypes' => $this->targetTypeOptions(),
            ],
            'formMeta' => [
                'salesmanMetaUrl' => '/inventory/targetcommission/salesman-meta',
                'packageStatusUrl' => '/inventory/targetcommission/package-upc-status',
                'lineStoreUrl' => '/inventory/targetcommission/line',
                'lineUpdateBaseUrl' => '/inventory/targetcommission/line',
                'lineDestroyBaseUrl' => '/inventory/targetcommission/line',
            ],
        ];
    }

    private function salesmanOptions(): array
    {
        return DB::table('routemaster as rm')
            ->join('salesman as sm', 'sm.salesmancode', '=', 'rm.salesmancode')
            ->where('sm.activestatus', 1)
            ->tap(fn ($query) => app(AccessScopeService::class)->scopeQuery(request()->user(), $query, 'route', 'rm.routecode'))
            ->select('sm.salesmancode', 'sm.salesmanname1', 'sm.arbsalesmanname1')
            ->distinct()
            ->orderBy('sm.salesmancode')
            ->get()
            ->map(fn ($record) => [
                'id' => (int) $record->salesmancode,
                'label' => trim($record->salesmancode . ' - ' . ($record->salesmanname1 ?? '')),
                'salesmanname1' => $record->salesmanname1 ?? '',
                'arbsalesmanname1' => $record->arbsalesmanname1 ?? '',
            ])
            ->values()
            ->all();
    }

    private function packageOptions(): array
    {
        $packages = [[
            'id' => 1,
            'label' => 'No Group',
            'packagedescription' => 'No Group',
            'arbpackagedescription' => 'No Group',
        ]];

        $items = DB::table('itempackagemaster')
            ->orderBy('packagedescription')
            ->get(['packagecode', 'packagedescription', 'arbpackagedescription'])
            ->map(fn ($record) => [
                'id' => (int) $record->packagecode,
                'label' => $record->packagedescription ?? '',
                'packagedescription' => $record->packagedescription ?? '',
                'arbpackagedescription' => $record->arbpackagedescription ?? '',
            ])
            ->values()
            ->all();

        return [...$packages, ...$items];
    }

    private function targetTypeOptions(): array
    {
        return [
            ['id' => 1, 'label' => 'Sales Quantity'],
            ['id' => 2, 'label' => 'Sales Value'],
            ['id' => 3, 'label' => 'Collection'],
            ['id' => 4, 'label' => 'Total Visits'],
            ['id' => 5, 'label' => 'Total Selling Calls'],
            ['id' => 6, 'label' => 'Damage Against Sales'],
            ['id' => 7, 'label' => 'MSL'],
        ];
    }

    private function lineRecords(int $salesmanCode, int $routeCode): array
    {
        $this->assertRouteAccess($routeCode);

        return DB::table('routegoal as rg')
            ->leftJoin('itempackagemaster as ipm', 'ipm.packagecode', '=', 'rg.packagenumber')
            ->where('rg.salesmancode', $salesmanCode)
            ->where('rg.routecode', $routeCode)
            ->orderByDesc('rg.fromdate')
            ->orderBy('rg.targettype')
            ->get([
                'rg.primary_key',
                'rg.fromdate',
                'rg.todate',
                'rg.targettype',
                'rg.packagenumber',
                'rg.quantity',
                'rg.commision',
                'rg.insentivepercent',
                'rg.insentive',
                'rg.achieveamount',
                'ipm.packagedescription',
                'ipm.arbpackagedescription',
            ])
            ->map(fn ($record) => [
                'primary_key' => (int) $record->primary_key,
                'fromdate' => Carbon::parse($record->fromdate)->toDateString(),
                'todate' => Carbon::parse($record->todate)->toDateString(),
                'targettype' => (int) $record->targettype,
                'targettype_label' => $this->targetTypeLabel((int) $record->targettype),
                'packagenumber' => (int) $record->packagenumber,
                'packagedescription' => (int) $record->packagenumber === 1
                    ? 'No Group'
                    : ($record->packagedescription ?? ''),
                'arbpackagedescription' => (int) $record->packagenumber === 1
                    ? 'No Group'
                    : ($record->arbpackagedescription ?? ''),
                'quantity' => (float) ($record->quantity ?? 0),
                'commision' => (float) ($record->commision ?? 0),
                'insentivepercent' => (float) ($record->insentivepercent ?? 0),
                'insentive' => (float) ($record->insentive ?? 0),
                'achieveamount' => (float) ($record->achieveamount ?? 0),
            ])
            ->values()
            ->all();
    }

    private function existingContext(int $primaryKey): array
    {
        $record = DB::table('routegoal as rg')
            ->join('routemaster as route', 'route.routecode', '=', 'rg.routecode')
            ->join('salesman as sales', 'sales.salesmancode', '=', 'rg.salesmancode')
            ->where('rg.primary_key', $primaryKey)
            ->first([
                'rg.primary_key',
                'rg.routecode',
                'rg.salesmancode',
                'route.routename',
                'route.arbroutename',
                'route.salesmantargetdays',
                'sales.salesmanname1',
                'sales.arbsalesmanname1',
            ]);

        abort_unless($record, 404);
        $this->assertRouteAccess((int) $record->routecode);

        return [
            'primary_key' => (int) $record->primary_key,
            'routecode' => (int) $record->routecode,
            'salesmancode' => (int) $record->salesmancode,
            'routename' => $record->routename ?? '',
            'arbroutename' => $record->arbroutename ?? '',
            'salesmantargetdays' => (int) ($record->salesmantargetdays ?: 1),
            'salesmanname1' => $record->salesmanname1 ?? '',
            'arbsalesmanname1' => $record->arbsalesmanname1 ?? '',
        ];
    }

    private function resolveSalesmanContext(int $salesmanCode, ?int $routeCode = null): array
    {
        $routeQuery = DB::table('routemaster')
            ->where('salesmancode', $salesmanCode)
            ->orderBy('routecode');

        app(AccessScopeService::class)->scopeQuery(request()->user(), $routeQuery, 'route', 'routecode');

        $route = $routeCode
            ? (clone $routeQuery)->where('routecode', $routeCode)->first(['routecode', 'salesmancode', 'routename', 'arbroutename', 'salesmantargetdays'])
            : $routeQuery->first(['routecode', 'salesmancode', 'routename', 'arbroutename', 'salesmantargetdays']);

        if (!$route) {
            throw ValidationException::withMessages([
                'salesmancode' => 'Selected salesman does not have a valid route.',
            ]);
        }

        return [
            'routecode' => (int) $route->routecode,
            'salesmancode' => (int) $route->salesmancode,
            'routename' => $route->routename ?? '',
            'arbroutename' => $route->arbroutename ?? '',
            'salesmantargetdays' => (int) ($route->salesmantargetdays ?: 1),
        ];
    }

    private function validateLineInput(Request $request): array
    {
        $request->merge([
            'salesmancode' => $this->normalizeIntInput($request->input('salesmancode')),
            'routecode' => $this->normalizeIntInput($request->input('routecode')),
            'packagenumber' => $this->normalizeIntInput($request->input('packagenumber')),
            'targettype' => $this->normalizeIntInput($request->input('targettype')),
        ]);

        return $request->validate([
            'salesmancode' => ['required', 'integer', 'min:1'],
            'routecode' => ['nullable', 'integer', 'min:1'],
            'packagenumber' => ['required', 'integer', 'min:1'],
            'fromdate' => ['required', 'date'],
            'todate' => ['required', 'date', 'after_or_equal:fromdate'],
            'quantity' => ['required', 'numeric'],
            'is_case' => ['nullable', 'boolean'],
            'targettype' => ['required', 'integer', 'between:1,7'],
            'commision' => ['required', 'numeric'],
            'insentivepercent' => ['nullable', 'numeric'],
            'insentive' => ['nullable', 'numeric'],
        ]);
    }

    private function normalizeIntInput(mixed $value): mixed
    {
        if ($value === null || $value === '') {
            return null;
        }

        $normalized = (int) $value;

        return $normalized > 0 ? $normalized : null;
    }

    private function validateTargetGroupRules(int $targetType, int $packageNumber): void
    {
        if (in_array($targetType, [3, 4, 5], true) && $packageNumber !== 1) {
            throw ValidationException::withMessages([
                'packagenumber' => "Select Target Group as 'No Group'.",
            ]);
        }

        if (!in_array($targetType, [3, 4, 5], true) && $packageNumber === 1) {
            throw ValidationException::withMessages([
                'packagenumber' => 'Select Target Group.',
            ]);
        }
    }

    private function guardDuplicateRange(
        int $salesmanCode,
        int $routeCode,
        int $targetType,
        int $packageNumber,
        Carbon $fromDate,
        Carbon $toDate,
        ?int $ignorePrimaryKey = null,
    ): void {
        $query = DB::table('routegoal')
            ->where('salesmancode', $salesmanCode)
            ->where('routecode', $routeCode)
            ->where('targettype', $targetType)
            ->where('packagenumber', $packageNumber)
            ->where(function ($builder) use ($fromDate, $toDate) {
                $builder
                    ->whereBetween('fromdate', [$fromDate->toDateString(), $toDate->toDateString()])
                    ->orWhereBetween('todate', [$fromDate->toDateString(), $toDate->toDateString()])
                    ->orWhere(function ($cover) use ($fromDate, $toDate) {
                        $cover
                            ->whereDate('fromdate', '<=', $fromDate->toDateString())
                            ->whereDate('todate', '>=', $toDate->toDateString());
                    });
            });

        if ($ignorePrimaryKey) {
            $query->where('primary_key', '<>', $ignorePrimaryKey);
        }

        if ($query->exists()) {
            throw ValidationException::withMessages([
                'fromdate' => 'Duplicate target period already exists for this salesman, target type, and group.',
            ]);
        }
    }

    private function normalizeQuantity(float $quantity, int $packageNumber, bool $isCase): float
    {
        if (!$isCase || $packageNumber === 1) {
            return $quantity;
        }

        $upcValues = DB::table('itemmaster')
            ->where('packagecode', $packageNumber)
            ->whereNotNull('unitspercase')
            ->distinct()
            ->pluck('unitspercase')
            ->filter(fn ($value) => (float) $value > 0)
            ->values();

        if ($upcValues->count() === 1) {
            return $quantity * (float) $upcValues->first();
        }

        return $quantity;
    }

    private function targetTypeLabel(int $targetType): string
    {
        return match ($targetType) {
            1 => 'Sales Quantity',
            2 => 'Sales Value',
            3 => 'Collection',
            4 => 'Total Visits',
            5 => 'Total Selling Calls',
            6 => 'Damage Against Sales',
            default => 'MSL',
        };
    }

    private function formatDate(mixed $value): ?string
    {
        if (!$value) {
            return null;
        }

        return Carbon::parse($value)->format('Y-m-d');
    }

    private function username(): string
    {
        return auth()->user()?->username ?? auth()->user()?->name ?? 'SYSTEM';
    }

    private function assertRouteAccess(int|string|null $routecode): void
    {
        abort_unless(app(AccessScopeService::class)->allows(request()->user(), 'route', $routecode), 403);
    }

    private function assertTargetCommissionAccess(int $primaryKey): void
    {
        $routeCode = DB::table('routegoal')->where('primary_key', $primaryKey)->value('routecode');
        abort_unless($routeCode !== null, 404);

        $this->assertRouteAccess((int) $routeCode);
    }
}
