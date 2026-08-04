<?php

namespace App\Http\Controllers\Inventory;

use App\Http\Controllers\Controller;
use App\Services\AccessScopeService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class DailySalesmanLoadController extends Controller
{
    public function index(Request $request): Response
    {
        $scope = app(AccessScopeService::class);
        $user = $request->user();
        $allowedPerPage = [10, 25, 50, 100];
        $allowedSorts = ['ddate', 'routecode', 'routename', 'salesmancode', 'salesmanname', 'loadperiodnumber', 'status', 'itemcount', 'totunits'];
        $loadDate = $request->filled('load_date')
            ? Carbon::parse((string) $request->input('load_date'))->toDateString()
            : Carbon::today()->toDateString();
        $perPage = (int) $request->input('per_page', 10);
        $sortBy = $request->input('sort_by', 'ddate');
        $sortDir = $request->input('sort_dir', 'desc') === 'asc' ? 'asc' : 'desc';
        $loadAlias = DB::getTablePrefix() . 'load';
        $routeAlias = DB::getTablePrefix() . 'route';
        $salesmanAlias = DB::getTablePrefix() . 'salesman';

        if (!in_array($perPage, $allowedPerPage, true)) {
            $perPage = 10;
        }

        if (!in_array($sortBy, $allowedSorts, true)) {
            $sortBy = 'ddate';
        }

        $query = DB::table('startingloaddetail as load')
            ->join('routemaster as route', 'route.routecode', '=', 'load.routecode')
            ->leftJoin('salesman as salesman', 'salesman.salesmancode', '=', 'load.salesmancode')
            ->select([
                'load.ddate',
                'load.routecode',
                'route.routename',
                'route.arbroutename',
                'load.salesmancode',
                'salesman.salesmanname1',
                'salesman.arbsalesmanname1',
                'load.loadperiodnumber',
                DB::raw("MAX(COALESCE({$loadAlias}.status, 0)) as status"),
                DB::raw('COUNT(*) as itemcount'),
                DB::raw("SUM(COALESCE({$loadAlias}.totunits, 0)) as totunits"),
                DB::raw("SUM(COALESCE({$loadAlias}.cases, 0)) as totalcases"),
                DB::raw("SUM(COALESCE({$loadAlias}.units, 0)) as totalunits"),
            ])
            ->groupBy([
                'load.ddate',
                'load.routecode',
                'route.routename',
                'route.arbroutename',
                'load.salesmancode',
                'salesman.salesmanname1',
                'salesman.arbsalesmanname1',
                'load.loadperiodnumber',
            ]);

        if ($request->filled('search')) {
            $search = trim((string) $request->input('search'));
            $query->havingRaw(
                "(CAST({$loadAlias}.routecode AS CHAR) like ? or CAST({$loadAlias}.salesmancode AS CHAR) like ? or {$routeAlias}.routename like ? or {$routeAlias}.arbroutename like ? or {$salesmanAlias}.salesmanname1 like ? or {$salesmanAlias}.arbsalesmanname1 like ? or CAST({$loadAlias}.loadperiodnumber AS CHAR) like ?)",
                ["%{$search}%", "%{$search}%", "%{$search}%", "%{$search}%", "%{$search}%", "%{$search}%", "%{$search}%"]
            );
        }

        $query->whereDate('load.ddate', $loadDate);
        $scope->scopeQuery($user, $query, 'route', 'load.routecode');

        $documents = $query
            ->orderBy($sortBy === 'routename' ? 'route.routename' : ($sortBy === 'salesmanname' ? 'salesman.salesmanname1' : $sortBy), $sortDir)
            ->paginate($perPage)
            ->withQueryString()
            ->through(fn ($record) => [
                'documentKey' => $this->documentKey(
                    (string) $record->ddate,
                    (int) $record->routecode,
                    (int) $record->salesmancode,
                    (int) ($record->loadperiodnumber ?? 0)
                ),
                'ddate' => $record->ddate,
                'routecode' => (int) $record->routecode,
                'routename' => $record->routename,
                'arbroutename' => $record->arbroutename,
                'salesmancode' => (int) $record->salesmancode,
                'salesmanname1' => $record->salesmanname1,
                'arbsalesmanname1' => $record->arbsalesmanname1,
                'loadperiodnumber' => (int) ($record->loadperiodnumber ?? 0),
                'status' => (int) ($record->status ?? 0),
                'statuslabel' => $this->loadUsageLabel((int) ($record->status ?? 0)),
                'itemcount' => (int) ($record->itemcount ?? 0),
                'totunits' => (int) ($record->totunits ?? 0),
                'totalcases' => (int) ($record->totalcases ?? 0),
                'totalunits' => (int) ($record->totalunits ?? 0),
            ]);

        return Inertia::render('inventory/dailysalesmanload/Index', [
            'documents' => $documents,
            'filters' => [
                'search' => $request->input('search', ''),
                'load_date' => $loadDate,
                'per_page' => $perPage,
                'sort_by' => $sortBy,
                'sort_dir' => $sortDir,
            ],
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('inventory/dailysalesmanload/Create', $this->formProps(null, request()->user()));
    }

    public function creationMeta(Request $request): JsonResponse
    {
        $data = $request->validate([
            'routecode' => ['required', 'integer', Rule::exists('routemaster', 'routecode')],
            'load_date' => ['required', 'date'],
        ]);
        abort_unless(app(AccessScopeService::class)->allows($request->user(), 'route', $data['routecode']), 403);

        $loadDate = Carbon::parse($data['load_date'])->toDateString();
        $route = $this->routeLookupRecord((int) $data['routecode']);
        $loadPeriodNumber = $this->nextLoadPeriodNumber((int) $data['routecode'], $loadDate);

        return response()->json([
            'route' => $route,
            'loadperiodnumber' => $loadPeriodNumber,
            'itemOptions' => $this->routeItemOptions((int) $data['routecode']),
            'lines' => $this->documentLines([
                'ddate' => $loadDate,
                'routecode' => (int) $data['routecode'],
                'salesmancode' => (int) ($route['salesmancode'] ?? 0),
                'loadperiodnumber' => $loadPeriodNumber,
            ], false),
        ]);
    }

    public function itemMeta(Request $request): JsonResponse
    {
        $data = $request->validate([
            'routecode' => ['required', 'integer', Rule::exists('routemaster', 'routecode')],
            'itemcode' => ['required', 'integer', Rule::exists('itemmaster', 'actualitemcode')],
            'load_date' => ['required', 'date'],
        ]);
        abort_unless(app(AccessScopeService::class)->allows($request->user(), 'route', $data['routecode']), 403);

        $item = DB::table('itemmaster')
            ->where('actualitemcode', (int) $data['itemcode'])
            ->first([
                'actualitemcode',
                'alternatecode',
                'itemshortdescription',
                'unitspercase',
                'caseprice',
                'defaultsalesprice',
                'defaultreturnprice',
                'returncaseprice',
                'allowbatchentry',
            ]);

        abort_unless($item, 404);

        $qty = $this->prefillQuantityForMethod(
            (int) $data['routecode'],
            (int) $data['itemcode'],
            Carbon::parse($data['load_date'])->toDateString(),
            $this->startingLoadMethod()
        );

        return response()->json([
            'itemcode' => (int) $item->actualitemcode,
            'display_code' => $this->useAlternateCode() && filled($item->alternatecode) ? (string) $item->alternatecode : (string) $item->actualitemcode,
            'description' => $item->itemshortdescription ?? '',
            'upc' => max(1, (int) ($item->unitspercase ?? 1)),
            'caseprice' => (float) ($item->caseprice ?? 0),
            'salesprice' => (float) ($item->defaultsalesprice ?? 0),
            'returnprice' => (float) ($item->defaultreturnprice ?? 0),
            'returncaseprice' => (float) ($item->returncaseprice ?? 0),
            'allowbatchentry' => (int) ($item->allowbatchentry ?? 0),
            'prefill_cases' => $qty['cases'],
            'prefill_units' => $qty['units'],
        ]);
    }

    public function storeLine(Request $request): JsonResponse
    {
        $data = $request->validate([
            'load_date' => ['required', 'date'],
            'routecode' => ['required', 'integer', Rule::exists('routemaster', 'routecode')],
            'salesmancode' => ['required', 'integer', Rule::exists('salesman', 'salesmancode')],
            'loadperiodnumber' => ['required', 'integer', 'min:1'],
            'erpreferencenumber' => ['nullable', 'string', 'max:30'],
            'itemcode' => ['required', 'integer', Rule::exists('itemmaster', 'actualitemcode')],
            'cases' => ['nullable', 'integer', 'min:0'],
            'units' => ['nullable', 'integer', 'min:0'],
            'batchnumber' => ['nullable', 'string', 'max:50'],
            'expirydate' => ['nullable', 'date'],
        ]);
        abort_unless(app(AccessScopeService::class)->allows($request->user(), 'route', $data['routecode']), 403);

        $cases = max(0, (int) ($data['cases'] ?? 0));
        $units = max(0, (int) ($data['units'] ?? 0));
        abort_if($cases === 0 && $units === 0, 422, __('error.daily_salesman_load_no_items'));

        $header = [
            'ddate' => Carbon::parse($data['load_date'])->toDateString(),
            'routecode' => (int) $data['routecode'],
            'salesmancode' => (int) $data['salesmancode'],
            'loadperiodnumber' => (int) $data['loadperiodnumber'],
        ];

        $item = DB::table('itemmaster')
            ->where('actualitemcode', (int) $data['itemcode'])
            ->first([
                'actualitemcode',
                'unitspercase',
                'caseprice',
                'defaultsalesprice',
                'defaultreturnprice',
                'returncaseprice',
                'allowbatchentry',
            ]);

        abort_unless($item, 404);

        $batchNumber = $this->normalizedBatchNumber($item, $data['batchnumber'] ?? null);
        $expiryDate = $this->normalizedExpiryDate($item, $data['expirydate'] ?? null);

        $duplicate = DB::table('startingloaddetail')
            ->whereDate('ddate', $header['ddate'])
            ->where('routecode', $header['routecode'])
            ->where('loadperiodnumber', $header['loadperiodnumber'])
            ->where('itemcode', (int) $data['itemcode'])
            ->when($this->batchEnabled(), fn ($query) => $query->where('batchnumber', $batchNumber))
            ->exists();

        if ($duplicate) {
            return response()->json([
                'message' => 'Item already added to the load.',
            ], 422);
        }

        $upc = max(1, (int) ($item->unitspercase ?? 1));
        $totUnits = ($cases * $upc) + $units;

        DB::table('startingloaddetail')->insert([
            'itemcode' => (int) $data['itemcode'],
            'routecode' => $header['routecode'],
            'ddate' => $header['ddate'],
            'loadperiodnumber' => $header['loadperiodnumber'],
            'cases' => $cases,
            'caseprice' => (float) ($item->caseprice ?? 0),
            'units' => $units,
            'totunits' => $totUnits,
            'suggtotunits' => 0,
            'rcvdtotunits' => 0,
            'upc' => $upc,
            'loadtime' => now()->format('H:i:s'),
            'salesmancode' => $header['salesmancode'],
            'salesprice' => (float) ($item->defaultsalesprice ?? 0),
            'returnprice' => (float) ($item->defaultreturnprice ?? 0),
            'returncaseprice' => (float) ($item->returncaseprice ?? 0),
            'status' => 0,
            'transactiondate' => $header['ddate'],
            'erpreferencenumber' => $data['erpreferencenumber'] ?? '',
            'batchnumber' => $batchNumber,
            'expirydate' => $expiryDate,
            'currencycode' => 0,
            'warehouse' => 0,
            'warehousestock' => 0,
        ]);

        return response()->json([
            'lines' => $this->documentLines($header, false),
        ]);
    }

    public function destroyLine(int $line): JsonResponse
    {
        $record = DB::table('startingloaddetail')->where('loaddetailcode', $line)->first();
        abort_unless($record, 404);

        DB::table('startingloaddetail')->where('loaddetailcode', $line)->delete();

        return response()->json([
            'lines' => $this->documentLines([
                'ddate' => Carbon::parse($record->ddate)->toDateString(),
                'routecode' => (int) $record->routecode,
                'salesmancode' => (int) $record->salesmancode,
                'loadperiodnumber' => (int) $record->loadperiodnumber,
            ], false),
        ]);
    }

    public function populateLines(Request $request): JsonResponse
    {
        $data = $request->validate([
            'load_date' => ['required', 'date'],
            'routecode' => ['required', 'integer', Rule::exists('routemaster', 'routecode')],
            'salesmancode' => ['required', 'integer', Rule::exists('salesman', 'salesmancode')],
            'loadperiodnumber' => ['required', 'integer', 'min:1'],
        ]);
        abort_unless(app(AccessScopeService::class)->allows($request->user(), 'route', $data['routecode']), 403);

        $header = [
            'ddate' => Carbon::parse($data['load_date'])->toDateString(),
            'routecode' => (int) $data['routecode'],
            'salesmancode' => (int) $data['salesmancode'],
            'loadperiodnumber' => (int) $data['loadperiodnumber'],
        ];

        $inserted = $this->populateLegacyMethodRows($header, $this->startingLoadMethod());

        return response()->json([
            'inserted' => $inserted,
            'lines' => $this->documentLines($header, false),
        ]);
    }

    public function show(string $document): Response
    {
        return Inertia::render('inventory/dailysalesmanload/View', $this->formProps($document, request()->user()));
    }

    public function edit(string $document): Response
    {
        return Inertia::render('inventory/dailysalesmanload/Edit', $this->formProps($document, request()->user()));
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validatedData($request);
        $header = $this->normalizedHeader($data);

        $exists = DB::table('startingloaddetail')
            ->whereDate('ddate', $header['ddate'])
            ->where('routecode', $header['routecode'])
            ->where('salesmancode', $header['salesmancode'])
            ->where('loadperiodnumber', $header['loadperiodnumber'])
            ->exists();

        if ($exists) {
            return back()->withErrors([
                'load_date' => __('error.daily_salesman_load_exists'),
            ])->withInput();
        }

        $lines = $this->normalizedLines($data['lines'] ?? []);

        if ($lines->isEmpty()) {
            return back()->withErrors([
                'lines' => __('error.daily_salesman_load_no_items'),
            ])->withInput();
        }

        DB::transaction(function () use ($header, $lines) {
            DB::table('startingloaddetail')->insert(
                $this->buildRows($header, $lines)->all()
            );
        });

        return redirect()
            ->route('inventory.dailysalesmanload.index')
            ->with('success', __('success.daily_salesman_load_created'));
    }

    public function update(Request $request, string $document): RedirectResponse
    {
        $current = $this->documentHeader($document);
        $data = $this->validatedData($request);
        $header = $this->normalizedHeader($data);

        if (
            $header['ddate'] !== $current['ddate']
            || $header['routecode'] !== $current['routecode']
            || $header['salesmancode'] !== $current['salesmancode']
            || $header['loadperiodnumber'] !== $current['loadperiodnumber']
        ) {
            return back()->withErrors([
                'load_date' => __('error.daily_salesman_load_header_locked'),
            ])->withInput();
        }

        $lines = $this->normalizedLines($data['lines'] ?? []);

        if ($lines->isEmpty()) {
            return back()->withErrors([
                'lines' => __('error.daily_salesman_load_no_items'),
            ])->withInput();
        }

        DB::transaction(function () use ($current, $header, $lines) {
            $this->deleteDocumentRows($current);
            DB::table('startingloaddetail')->insert(
                $this->buildRows($header, $lines)->all()
            );
        });

        return redirect()
            ->route('inventory.dailysalesmanload.index')
            ->with('success', __('success.daily_salesman_load_updated'));
    }

    public function destroy(string $document): RedirectResponse
    {
        $header = $this->documentHeader($document);
        abort_unless(app(AccessScopeService::class)->allows(request()->user(), 'route', $header['routecode']), 403);

        DB::transaction(function () use ($header) {
            $this->deleteDocumentRows($header);
        });

        return back()->with('success', __('success.daily_salesman_load_deleted'));
    }

    public function routeItems(Request $request): JsonResponse
    {
        $data = $request->validate([
            'routecode' => ['required', 'integer', Rule::exists('routemaster', 'routecode')],
        ]);
        abort_unless(app(AccessScopeService::class)->allows($request->user(), 'route', $data['routecode']), 403);

        $route = $this->routeLookupRecord((int) $data['routecode']);

        return response()->json([
            'route' => $route,
            'lines' => $this->routeItemLines((int) $data['routecode']),
        ]);
    }

    private function formProps(?string $document = null, $user = null): array
    {
        $batchStatus = $this->batchEnabled() ? 1 : 0;
        $loadMethod = $this->startingLoadMethod();

        $header = $document ? $this->documentHeader($document) : [
            'ddate' => now()->toDateString(),
            'routecode' => '',
            'salesmancode' => '',
            'loadperiodnumber' => 0,
            'status' => 0,
            'statuslabel' => $this->loadUsageLabel(0),
        ];

        return [
            'loadData' => [
                'header' => $header,
                'lines' => $document ? $this->documentLines($header) : [],
            ],
            'lookupOptions' => [
                'routes' => $this->routeOptions($user),
                'salesmen' => $this->salesmanOptions(),
            ],
            'formMeta' => [
                'routeItemsUrl' => route('inventory.dailysalesmanload.route-items'),
                'headerLocked' => $document !== null,
                'creationMetaUrl' => route('inventory.dailysalesmanload.creation-meta'),
                'itemMetaUrl' => route('inventory.dailysalesmanload.item-meta'),
                'lineStoreUrl' => route('inventory.dailysalesmanload.lines.store'),
                'lineDestroyBaseUrl' => '/inventory/dailysalesmanload/lines',
                'populateUrl' => route('inventory.dailysalesmanload.populate'),
                'batchStatus' => $batchStatus,
                'loadMethod' => $loadMethod,
                'loadMethodLabel' => $this->startingLoadMethodLabel($loadMethod),
                'loadFromErp' => $this->loadFromErp(),
            ],
            'useAlternateCode' => $this->useAlternateCode(),
        ];
    }

    private function validatedData(Request $request): array
    {
        $data = $request->validate([
            'load_date' => ['required', 'date'],
            'routecode' => ['required', 'integer', Rule::exists('routemaster', 'routecode')],
            'salesmancode' => ['required', 'integer', Rule::exists('salesman', 'salesmancode')],
            'loadperiodnumber' => ['required', 'integer', 'min:0'],
            'lines' => ['array'],
            'lines.*.itemcode' => ['required', 'integer', Rule::exists('itemmaster', 'actualitemcode')],
            'lines.*.cases' => ['nullable', 'integer', 'min:0'],
            'lines.*.units' => ['nullable', 'integer', 'min:0'],
        ]);

        abort_unless(app(AccessScopeService::class)->allows($request->user(), 'route', $data['routecode']), 403);

        return $data;
    }

    private function normalizedHeader(array $data): array
    {
        return [
            'ddate' => $data['load_date'],
            'routecode' => (int) $data['routecode'],
            'salesmancode' => (int) $data['salesmancode'],
            'loadperiodnumber' => (int) $data['loadperiodnumber'],
        ];
    }

    private function normalizedLines(array $lines): Collection
    {
        return collect($lines)
            ->map(function ($line) {
                $cases = max(0, (int) ($line['cases'] ?? 0));
                $units = max(0, (int) ($line['units'] ?? 0));

                return [
                    'itemcode' => (int) ($line['itemcode'] ?? 0),
                    'cases' => $cases,
                    'units' => $units,
                ];
            })
            ->filter(fn ($line) => $line['itemcode'] > 0 && ($line['cases'] > 0 || $line['units'] > 0))
            ->values();
    }

    private function buildRows(array $header, Collection $lines): Collection
    {
        $items = DB::table('itemmaster')
            ->whereIn('actualitemcode', $lines->pluck('itemcode')->all())
            ->get()
            ->keyBy('actualitemcode');

        return $lines->map(function ($line) use ($header, $items) {
            $item = $items->get($line['itemcode']);
            $upc = max(1, (int) ($item->unitspercase ?? 1));
            $totUnits = ($line['cases'] * $upc) + $line['units'];

            return [
                'itemcode' => $line['itemcode'],
                'routecode' => $header['routecode'],
                'ddate' => $header['ddate'],
                'loadperiodnumber' => $header['loadperiodnumber'],
                'cases' => $line['cases'],
                'caseprice' => $item ? (float) ($item->caseprice ?? 0) : 0,
                'units' => $line['units'],
                'totunits' => $totUnits,
                'suggtotunits' => $totUnits,
                'rcvdtotunits' => $totUnits,
                'upc' => $upc,
                'loadtime' => now()->format('H:i'),
                'salesmancode' => $header['salesmancode'],
                'salesprice' => $item ? (float) ($item->defaultsalesprice ?? 0) : 0,
                'returnprice' => $item ? (float) ($item->defaultreturnprice ?? 0) : 0,
                'returncaseprice' => $item ? (float) ($item->returncaseprice ?? 0) : 0,
                'status' => 1,
                'transactiondate' => now(),
            ];
        });
    }

    private function routeOptions($user = null): array
    {
        $scope = app(AccessScopeService::class);
        $query = DB::table('routemaster as route')
            ->leftJoin('salesman as salesman', 'salesman.salesmancode', '=', 'route.salesmancode')
            ->select([
                'route.routecode',
                'route.routename',
                'route.arbroutename',
                'route.salesmancode',
                'salesman.salesmanname1',
                'salesman.arbsalesmanname1',
            ])
            ->orderBy('route.routename');

        if (Schema::hasColumn('routemaster', 'activestatus')) {
            $query->where('route.activestatus', 1);
        }

        $scope->scopeQuery($user, $query, 'route', 'route.routecode');

        return $query->get()->map(fn ($route) => [
            'id' => (int) $route->routecode,
            'label' => trim($route->routecode . ' -- ' . ($route->routename ?? '')),
            'salesmancode' => $route->salesmancode !== null ? (int) $route->salesmancode : null,
            'salesmanname' => $route->salesmanname1,
            'arbsalesmanname' => $route->arbsalesmanname1,
        ])->all();
    }

    private function salesmanOptions(): array
    {
        $query = DB::table('salesman')
            ->select(['salesmancode', 'salesmanname1'])
            ->orderBy('salesmanname1');

        if (Schema::hasColumn('salesman', 'activestatus')) {
            $query->where('activestatus', 1);
        }

        return $query->get()->map(fn ($salesman) => [
            'id' => (int) $salesman->salesmancode,
            'label' => trim($salesman->salesmancode . ' -- ' . ($salesman->salesmanname1 ?? '')),
        ])->all();
    }

    private function routeLookupRecord(int $routecode): array
    {
        abort_unless(app(AccessScopeService::class)->allows(request()->user(), 'route', $routecode), 403);

        $route = DB::table('routemaster as route')
            ->leftJoin('salesman as salesman', 'salesman.salesmancode', '=', 'route.salesmancode')
            ->where('route.routecode', $routecode)
            ->first([
                'route.routecode',
                'route.routename',
                'route.salesmancode',
                'salesman.salesmanname1',
            ]);

        abort_unless($route, 404);

        return [
            'routecode' => (int) $route->routecode,
            'routename' => $route->routename,
            'salesmancode' => $route->salesmancode !== null ? (int) $route->salesmancode : null,
            'salesmanname' => $route->salesmanname1,
        ];
    }

    private function routeItemLines(int $routecode): array
    {
        $routeItemGroupCode = (int) DB::table('routemaster')
            ->where('routecode', $routecode)
            ->value('routeitemgrpcode');

        $query = DB::table('itemmaster as item')
            ->select([
                'item.actualitemcode',
                'item.alternatecode',
                'item.itemshortdescription',
                'item.unitspercase',
                'item.caseprice',
                'item.defaultsalesprice',
            ])
            ->orderBy('item.actualitemcode');

        if (Schema::hasColumn('itemmaster', 'activeitem')) {
            $query->where('item.activeitem', 1);
        }

        if ($routeItemGroupCode > 0 && Schema::hasTable('routeitemmapping')) {
            $query->join('routeitemmapping as mapping', 'mapping.itemcode', '=', 'item.actualitemcode')
                ->where('mapping.routeitemgrpcode', $routeItemGroupCode);
        }

        $useAlternateCode = $this->useAlternateCode();

        return $query->get()->map(function ($item) use ($useAlternateCode) {
            $displayCode = $useAlternateCode && filled($item->alternatecode)
                ? $item->alternatecode
                : $item->actualitemcode;

            return [
                'itemcode' => (int) $item->actualitemcode,
                'display_code' => (string) $displayCode,
                'description' => $item->itemshortdescription ?? '',
                'upc' => max(1, (int) ($item->unitspercase ?? 1)),
                'caseprice' => (float) ($item->caseprice ?? 0),
                'salesprice' => (float) ($item->defaultsalesprice ?? 0),
                'cases' => 0,
                'units' => 0,
            ];
        })->values()->all();
    }

    private function documentLines(array $header, bool $abortIfMissing = true): array
    {
        $useAlternateCode = $this->useAlternateCode();

        $query = DB::table('startingloaddetail as load')
            ->join('itemmaster as item', 'item.actualitemcode', '=', 'load.itemcode')
            ->whereDate('load.ddate', $header['ddate'])
            ->where('load.routecode', $header['routecode'])
            ->where('load.salesmancode', $header['salesmancode'])
            ->where('load.loadperiodnumber', $header['loadperiodnumber'])
            ->orderBy('item.actualitemcode')
            ->get([
                'load.loaddetailcode',
                'load.itemcode',
                'load.cases',
                'load.units',
                'load.upc',
                'load.caseprice',
                'load.salesprice',
                'load.batchnumber',
                'load.expirydate',
                'item.alternatecode',
                'item.itemshortdescription',
            ]);

        if ($abortIfMissing) {
            abort_if($query->isEmpty(), 404);
        }

        return $query
            ->map(function ($line) use ($useAlternateCode) {
                $displayCode = $useAlternateCode && filled($line->alternatecode)
                    ? $line->alternatecode
                    : $line->itemcode;

                return [
                    'loaddetailcode' => (int) $line->loaddetailcode,
                    'itemcode' => (int) $line->itemcode,
                    'display_code' => (string) $displayCode,
                    'description' => $line->itemshortdescription ?? '',
                    'upc' => max(1, (int) ($line->upc ?? 1)),
                    'caseprice' => (float) ($line->caseprice ?? 0),
                    'salesprice' => (float) ($line->salesprice ?? 0),
                    'cases' => (int) ($line->cases ?? 0),
                    'units' => (int) ($line->units ?? 0),
                    'batchnumber' => $line->batchnumber,
                    'expirydate' => $line->expirydate ? Carbon::parse($line->expirydate)->toDateString() : null,
                ];
            })
            ->values()
            ->all();
    }

    private function documentHeader(string $document): array
    {
        preg_match('/^(\d{4}-\d{2}-\d{2})_(\d+)_(\d+)_(\d+)$/', $document, $matches);
        abort_if(count($matches) !== 5, 404);

        $header = [
            'ddate' => $matches[1],
            'routecode' => (int) $matches[2],
            'salesmancode' => (int) $matches[3],
            'loadperiodnumber' => (int) $matches[4],
        ];

        $exists = DB::table('startingloaddetail')
            ->whereDate('ddate', $header['ddate'])
            ->where('routecode', $header['routecode'])
            ->where('salesmancode', $header['salesmancode'])
            ->where('loadperiodnumber', $header['loadperiodnumber'])
            ->exists();

        abort_unless($exists, 404);

        $status = (int) (DB::table('startingloaddetail')
            ->whereDate('ddate', $header['ddate'])
            ->where('routecode', $header['routecode'])
            ->where('salesmancode', $header['salesmancode'])
            ->where('loadperiodnumber', $header['loadperiodnumber'])
            ->max('status') ?? 0);

        $header['status'] = $status;
        $header['statuslabel'] = $this->loadUsageLabel($status);

        abort_unless(app(AccessScopeService::class)->allows(request()->user(), 'route', $header['routecode']), 403);

        return $header;
    }

    private function loadUsageLabel(int $status): string
    {
        return $status === 1 ? 'Used' : 'Not Used';
    }

    private function documentKey(string $date, int $routecode, int $salesmancode, int $period): string
    {
        return "{$date}_{$routecode}_{$salesmancode}_{$period}";
    }

    private function deleteDocumentRows(array $header): void
    {
        DB::table('startingloaddetail')
            ->whereDate('ddate', $header['ddate'])
            ->where('routecode', $header['routecode'])
            ->where('salesmancode', $header['salesmancode'])
            ->where('loadperiodnumber', $header['loadperiodnumber'])
            ->delete();
    }

    private function useAlternateCode(): bool
    {
        if (!Schema::hasTable('controlpanel')) {
            return false;
        }

        return (int) DB::table('controlpanel')
            ->where('flagname', 'Use Alternate Code')
            ->value('status') === 1;
    }

    private function loadFromErp(): bool
    {
        if (!Schema::hasTable('controlpanel')) {
            return false;
        }

        return (int) DB::table('controlpanel')
            ->where('flagname', 'Load From ERP')
            ->value('status') === 1;
    }

    private function batchEnabled(): bool
    {
        if (!Schema::hasTable('controlpanel')) {
            return false;
        }

        return (int) DB::table('controlpanel')
            ->where('flagid', 1)
            ->value('status') === 1;
    }

    private function startingLoadMethod(): int
    {
        if (!Schema::hasTable('controlpanel')) {
            return 0;
        }

        return (int) DB::table('controlpanel')
            ->where('flagid', 60)
            ->value('status');
    }

    private function startingLoadMethodLabel(int $method): string
    {
        return match ($method) {
            1 => 'Load Imported From ERP',
            2 => 'Convert Load Request to Load',
            3 => 'Sales order to load',
            4 => 'Populate Previous day load',
            5 => 'Use Suggested Load',
            default => 'Create New Load',
        };
    }

    private function nextLoadPeriodNumber(int $routecode, string $loadDate): int
    {
        $max = DB::table('startingloaddetail')
            ->where('routecode', $routecode)
            ->whereDate('ddate', $loadDate)
            ->max('loadperiodnumber');

        return max(1, ((int) $max) + 1);
    }

    private function routeItemOptions(int $routecode): array
    {
        return collect($this->routeItemLines($routecode))->map(fn ($line) => [
            'id' => $line['itemcode'],
            'label' => trim($line['display_code'] . ' -- ' . $line['description']),
        ])->values()->all();
    }

    private function normalizedBatchNumber(object $item, ?string $batchNumber): string
    {
        if (!$this->batchEnabled() || (int) ($item->allowbatchentry ?? 0) !== 1) {
            return 'NONE';
        }

        return trim((string) $batchNumber) !== '' ? trim((string) $batchNumber) : 'NONE';
    }

    private function normalizedExpiryDate(object $item, ?string $expiryDate): string
    {
        if (!$this->batchEnabled() || (int) ($item->allowbatchentry ?? 0) !== 1 || blank($expiryDate)) {
            return '2099-12-31';
        }

        return Carbon::parse($expiryDate)->toDateString();
    }

    private function prefillQuantityForMethod(int $routecode, int $itemcode, string $loadDate, int $method): array
    {
        $quantity = 0;

        if ($method === 2 && Schema::hasTable('inventorytransactiondetail') && Schema::hasTable('inventorytransactionheader')) {
            $quantity = (int) DB::table('inventorytransactiondetail as detail')
                ->join('inventorytransactionheader as header', 'header.detailkey', '=', 'detail.detailkey')
                ->where('detail.transactiontypecode', 8)
                ->where('header.routecode', $routecode)
                ->whereDate('header.transactiondate', $loadDate)
                ->where('detail.itemcode', $itemcode)
                ->sum('detail.quantity');
        } elseif ($method === 3 && Schema::hasTable('salesorderdetail') && Schema::hasTable('salesorderheader')) {
            $quantity = (int) DB::table('salesorderdetail as detail')
                ->join('salesorderheader as header', 'header.transactionkey', '=', 'detail.transactionkey')
                ->where('header.routecode', $routecode)
                ->whereDate('header.orderdeliverydate', $loadDate)
                ->where('detail.itemcode', $itemcode)
                ->sum('detail.salesqty');
        }

        $upc = max(1, (int) DB::table('itemmaster')->where('actualitemcode', $itemcode)->value('unitspercase'));

        return [
            'cases' => $quantity > 0 ? intdiv($quantity, $upc) : 0,
            'units' => $quantity > 0 ? ($quantity % $upc) : 0,
        ];
    }

    private function populateLegacyMethodRows(array $header, int $method): int
    {
        $rows = match ($method) {
            2 => $this->loadRequestRows($header),
            3 => $this->salesOrderRows($header),
            4 => $this->previousDayRows($header),
            5 => $this->suggestedSalesRows($header),
            default => collect(),
        };

        if ($rows->isEmpty()) {
            return 0;
        }

        $existingItems = DB::table('startingloaddetail')
            ->whereDate('ddate', $header['ddate'])
            ->where('routecode', $header['routecode'])
            ->where('loadperiodnumber', $header['loadperiodnumber'])
            ->pluck('itemcode')
            ->map(fn ($value) => (int) $value)
            ->all();

        $rowsToInsert = $rows
            ->reject(fn ($row) => in_array((int) $row['itemcode'], $existingItems, true))
            ->map(function ($row) use ($header) {
                $upc = max(1, (int) $row['upc']);
                $totUnits = ((int) $row['cases'] * $upc) + (int) $row['units'];

                return [
                    'itemcode' => (int) $row['itemcode'],
                    'routecode' => $header['routecode'],
                    'ddate' => $header['ddate'],
                    'loadperiodnumber' => $header['loadperiodnumber'],
                    'cases' => (int) $row['cases'],
                    'caseprice' => (float) $row['caseprice'],
                    'units' => (int) $row['units'],
                    'totunits' => $totUnits,
                    'suggtotunits' => 0,
                    'rcvdtotunits' => 0,
                    'upc' => $upc,
                    'loadtime' => now()->format('H:i:s'),
                    'salesmancode' => $header['salesmancode'],
                    'salesprice' => (float) $row['salesprice'],
                    'returnprice' => (float) $row['returnprice'],
                    'returncaseprice' => (float) $row['returncaseprice'],
                    'status' => 0,
                    'transactiondate' => $header['ddate'],
                    'erpreferencenumber' => '',
                    'batchnumber' => 'NONE',
                    'expirydate' => '2099-12-31',
                    'currencycode' => 0,
                    'warehouse' => 0,
                    'warehousestock' => 0,
                ];
            })
            ->values();

        if ($rowsToInsert->isEmpty()) {
            return 0;
        }

        DB::table('startingloaddetail')->insert($rowsToInsert->all());

        return $rowsToInsert->count();
    }

    private function loadRequestRows(array $header): Collection
    {
        if (!Schema::hasTable('inventorytransactiondetail') || !Schema::hasTable('inventorytransactionheader')) {
            return collect();
        }

        return DB::table('inventorytransactiondetail as detail')
            ->join('inventorytransactionheader as head', 'head.detailkey', '=', 'detail.detailkey')
            ->join('itemmaster as item', 'item.actualitemcode', '=', 'detail.itemcode')
            ->where('detail.transactiontypecode', 8)
            ->where('head.routecode', $header['routecode'])
            ->whereDate('head.transactiondate', $header['ddate'])
            ->groupBy([
                'detail.itemcode',
                'item.unitspercase',
                'item.caseprice',
                'item.defaultsalesprice',
                'item.defaultreturnprice',
                'item.returncaseprice',
            ])
            ->selectRaw('detail.itemcode, SUM(detail.quantity) as qty, item.unitspercase as upc, item.caseprice, item.defaultsalesprice as salesprice, item.defaultreturnprice as returnprice, item.returncaseprice')
            ->get()
            ->map(fn ($row) => $this->quantityRowFromTotal($row));
    }

    private function salesOrderRows(array $header): Collection
    {
        if (!Schema::hasTable('salesorderdetail') || !Schema::hasTable('salesorderheader')) {
            return collect();
        }

        return DB::table('salesorderdetail as detail')
            ->join('salesorderheader as head', 'head.transactionkey', '=', 'detail.transactionkey')
            ->join('itemmaster as item', 'item.actualitemcode', '=', 'detail.itemcode')
            ->where('head.routecode', $header['routecode'])
            ->whereDate('head.orderdeliverydate', $header['ddate'])
            ->groupBy([
                'detail.itemcode',
                'item.unitspercase',
                'item.caseprice',
                'item.defaultsalesprice',
                'item.defaultreturnprice',
                'item.returncaseprice',
            ])
            ->selectRaw('detail.itemcode, SUM(detail.salesqty) as qty, item.unitspercase as upc, item.caseprice, item.defaultsalesprice as salesprice, item.defaultreturnprice as returnprice, item.returncaseprice')
            ->get()
            ->map(fn ($row) => $this->quantityRowFromTotal($row));
    }

    private function previousDayRows(array $header): Collection
    {
        return DB::table('startingloaddetail as load')
            ->join('itemmaster as item', 'item.actualitemcode', '=', 'load.itemcode')
            ->where('load.routecode', $header['routecode'])
            ->where('load.loadperiodnumber', $header['loadperiodnumber'])
            ->whereDate('load.ddate', Carbon::parse($header['ddate'])->subDay()->toDateString())
            ->groupBy([
                'load.itemcode',
                'item.unitspercase',
                'item.caseprice',
                'item.defaultsalesprice',
                'item.defaultreturnprice',
                'item.returncaseprice',
            ])
            ->selectRaw('load.itemcode, SUM((COALESCE(load.cases,0) * COALESCE(load.upc,1)) + COALESCE(load.units,0)) as qty, item.unitspercase as upc, item.caseprice, item.defaultsalesprice as salesprice, item.defaultreturnprice as returnprice, item.returncaseprice')
            ->get()
            ->map(fn ($row) => $this->quantityRowFromTotal($row));
    }

    private function suggestedSalesRows(array $header): Collection
    {
        if (!Schema::hasTable('suggestedsalesinvoice')) {
            return collect();
        }

        return DB::table('suggestedsalesinvoice as load')
            ->join('itemmaster as item', 'item.actualitemcode', '=', 'load.itemcode')
            ->where('load.routecode', $header['routecode'])
            ->whereDate('load.transactiondate', $header['ddate'])
            ->groupBy([
                'load.itemcode',
                'item.unitspercase',
                'item.caseprice',
                'item.defaultsalesprice',
                'item.defaultreturnprice',
                'item.returncaseprice',
            ])
            ->selectRaw('load.itemcode, SUM(load.currentvisitsales) as qty, item.unitspercase as upc, item.caseprice, item.defaultsalesprice as salesprice, item.defaultreturnprice as returnprice, item.returncaseprice')
            ->get()
            ->map(fn ($row) => $this->quantityRowFromTotal($row));
    }

    private function quantityRowFromTotal(object $row): array
    {
        $upc = max(1, (int) ($row->upc ?? 1));
        $qty = max(0, (int) ($row->qty ?? 0));

        return [
            'itemcode' => (int) $row->itemcode,
            'cases' => intdiv($qty, $upc),
            'units' => $qty % $upc,
            'upc' => $upc,
            'caseprice' => (float) ($row->caseprice ?? 0),
            'salesprice' => (float) ($row->salesprice ?? 0),
            'returnprice' => (float) ($row->returnprice ?? 0),
            'returncaseprice' => (float) ($row->returncaseprice ?? 0),
        ];
    }
}
