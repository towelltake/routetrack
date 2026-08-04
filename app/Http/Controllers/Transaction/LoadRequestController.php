<?php

namespace App\Http\Controllers\Transaction;

use App\Http\Controllers\Controller;
use App\Services\AccessScopeService;
use Carbon\Carbon;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class LoadRequestController extends Controller
{
    public function index(Request $request): Response
    {
        $scope = app(AccessScopeService::class);
        $user = $request->user();
        $headerAlias = $this->prefixedAlias('header');
        $routeAlias = $this->prefixedAlias('route');
        $salesmanAlias = $this->prefixedAlias('salesman');

        $allowedPerPage = [10, 25, 50, 100];
        $allowedSorts = ['routecode', 'salesmanname1', 'routename', 'documentnumber', 'transactiontime'];
        $perPage = (int) $request->input('per_page', 10);
        $sortBy = (string) $request->input('sort_by', 'transactiontime');
        $sortDir = $request->input('sort_dir', 'desc') === 'asc' ? 'asc' : 'desc';
        $selectedDate = $this->selectedDate($request)->toDateString();
        $selectedRoute = max(0, (int) $request->input('routecode', 0));
        $search = trim((string) $request->input('search', ''));

        if (!in_array($perPage, $allowedPerPage, true)) {
            $perPage = 10;
        }

        if (!in_array($sortBy, $allowedSorts, true)) {
            $sortBy = 'transactiontime';
        }

        if (!Schema::hasTable('inventorytransactionheader') || !Schema::hasTable('inventorytransactiondetail')) {
            return Inertia::render('transaction/load-request/Index', [
                'documents' => $this->emptyPaginator($request, $perPage),
                'routeOptions' => $this->requestRouteOptions(),
                'filters' => [
                    'date' => $selectedDate,
                    'routecode' => $selectedRoute,
                    'search' => $search,
                    'per_page' => $perPage,
                    'sort_by' => $sortBy,
                    'sort_dir' => $sortDir,
                ],
            ]);
        }

        $query = DB::table('inventorytransactionheader as header')
            ->leftJoin('routemaster as route', 'route.routecode', '=', 'header.routecode')
            ->leftJoin('salesman as salesman', 'salesman.salesmancode', '=', 'header.salesmancode')
            ->selectRaw('
                ' . $headerAlias . '.detailkey,
                ' . $headerAlias . '.routecode,
                COALESCE(' . $routeAlias . '.routename, "") as routename,
                COALESCE(' . $routeAlias . '.arbroutename, "") as arbroutename,
                COALESCE(' . $salesmanAlias . '.salesmanname1, "") as salesmanname1,
                COALESCE(' . $salesmanAlias . '.arbsalesmanname1, "") as arbsalesmanname1,
                COALESCE(' . $headerAlias . '.documentnumber, "") as documentnumber,
                ' . $this->transactionTimeExpression($headerAlias) . ' as transactiontime
            ')
            ->whereDate('header.transactiondate', $selectedDate)
            ->whereExists(function ($subQuery) {
                $subQuery->select(DB::raw(1))
                    ->from('inventorytransactiondetail as detail')
                    ->whereColumn('detail.detailkey', 'header.detailkey');

                if (Schema::hasColumn('inventorytransactiondetail', 'transactiontypecode')) {
                    $subQuery->where('detail.transactiontypecode', 8);
                }
            });

        $scope->scopeQuery($user, $query, 'route', 'header.routecode');

        if (Schema::hasColumn('inventorytransactionheader', 'transactiontype')) {
            $query->where('header.transactiontype', 4);
        }

        if ($selectedRoute > 0) {
            $query->where('header.routecode', $selectedRoute);
        }

        if ($search !== '') {
            $query->where(function ($searchQuery) use ($search) {
                $like = '%' . $search . '%';

                $searchQuery
                    ->where('header.routecode', 'like', $like)
                    ->orWhere('header.documentnumber', 'like', $like)
                    ->orWhere('route.routename', 'like', $like)
                    ->orWhere('route.arbroutename', 'like', $like)
                    ->orWhere('salesman.salesmanname1', 'like', $like)
                    ->orWhere('salesman.arbsalesmanname1', 'like', $like);
            });
        }

        $documents = $query
            ->orderBy($this->sortColumn($sortBy), $sortDir)
            ->orderBy('header.detailkey', 'desc')
            ->paginate($perPage)
            ->withQueryString()
            ->through(fn ($row) => [
                'detailkey' => (int) $row->detailkey,
                'routecode' => (int) $row->routecode,
                'routename' => $row->routename,
                'arbroutename' => $row->arbroutename,
                'salesmanname1' => $row->salesmanname1,
                'arbsalesmanname1' => $row->arbsalesmanname1,
                'documentnumber' => $row->documentnumber,
                'transactiontime' => $row->transactiontime,
            ]);

        return Inertia::render('transaction/load-request/Index', [
            'documents' => $documents,
            'routeOptions' => $this->requestRouteOptions(),
            'filters' => [
                'date' => $selectedDate,
                'routecode' => $selectedRoute,
                'search' => $search,
                'per_page' => $perPage,
                'sort_by' => $sortBy,
                'sort_dir' => $sortDir,
            ],
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('transaction/load-request/Form', $this->formProps());
    }

    public function show(int $detailkey): Response
    {
        abort_unless(app(AccessScopeService::class)->allows(request()->user(), 'route', DB::table('inventorytransactionheader')->where('detailkey', $detailkey)->value('routecode')), 403);

        return Inertia::render('transaction/load-request/Form', $this->formProps($detailkey));
    }

    public function routeMeta(Request $request): JsonResponse
    {
        $data = $request->validate([
            'routecode' => ['required', 'integer', Rule::exists('routemaster', 'routecode')],
        ]);
        abort_unless(app(AccessScopeService::class)->allows($request->user(), 'route', $data['routecode']), 403);

        $route = $this->routeLookupRecord((int) $data['routecode']);

        return response()->json([
            'route' => $route,
            'items' => $this->routeItemOptions((int) $data['routecode']),
            'warning' => empty($route['salesmancode'])
                ? 'Selected route has no salesman assigned.'
                : null,
        ]);
    }

    public function itemMeta(Request $request): JsonResponse
    {
        $data = $request->validate([
            'routecode' => ['required', 'integer', Rule::exists('routemaster', 'routecode')],
            'itemcode' => ['required', 'integer', Rule::exists('itemmaster', 'actualitemcode')],
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
            ]);

        abort_unless($item, 404);

        return response()->json([
            'itemcode' => (int) $item->actualitemcode,
            'display_code' => $this->useAlternateCode() && filled($item->alternatecode)
                ? (string) $item->alternatecode
                : (string) $item->actualitemcode,
            'description' => $item->itemshortdescription ?? '',
            'upc' => max(1, (int) ($item->unitspercase ?? 1)),
            'caseprice' => (float) ($item->caseprice ?? 0),
            'salesprice' => (float) ($item->defaultsalesprice ?? 0),
        ]);
    }

    public function storeLine(Request $request): JsonResponse
    {
        $data = $request->validate([
            'detailkey' => ['nullable', 'integer'],
            'routecode' => ['required', 'integer', Rule::exists('routemaster', 'routecode')],
            'salesmancode' => ['nullable', 'integer'],
            'requestdate' => ['required', 'date'],
            'depotroute' => ['nullable', 'integer', Rule::exists('routemaster', 'routecode')],
            'isurgent' => ['nullable', 'boolean'],
            'itemcode' => ['required', 'integer', Rule::exists('itemmaster', 'actualitemcode')],
            'cases' => ['nullable', 'integer', 'min:0'],
            'pieces' => ['nullable', 'integer', 'min:0'],
        ]);
        abort_unless(app(AccessScopeService::class)->allows($request->user(), 'route', $data['routecode']), 403);

        $cases = max(0, (int) ($data['cases'] ?? 0));
        $pieces = max(0, (int) ($data['pieces'] ?? 0));
        abort_if($cases === 0 && $pieces === 0, 422, 'Please enter cases or pieces.');

        $detailkey = isset($data['detailkey']) ? (int) $data['detailkey'] : 0;
        $route = $this->routeLookupRecord((int) $data['routecode']);
        $salesmancode = !empty($route['salesmancode'])
            ? (int) $route['salesmancode']
            : (isset($data['salesmancode']) ? (int) $data['salesmancode'] : 0);

        abort_if($salesmancode <= 0, 422, 'Selected route has no salesman assigned.');
        abort_unless(
            DB::table('salesman')->where('salesmancode', $salesmancode)->exists(),
            422,
            'Selected route has no valid salesman assigned.'
        );

        DB::transaction(function () use (&$detailkey, $data, $cases, $pieces, $salesmancode) {
            $header = $detailkey > 0
                ? DB::table('inventorytransactionheader')->where('detailkey', $detailkey)->lockForUpdate()->first()
                : null;

            if (!$header) {
                $detailkey = $this->createHeader($data, $salesmancode);
                $header = DB::table('inventorytransactionheader')->where('detailkey', $detailkey)->lockForUpdate()->first();
            }

            abort_if((int) ($header->transmitindicator ?? 0) === 1, 422, "Request processed by warehouse can't be edited.");

            $item = DB::table('itemmaster')
                ->where('actualitemcode', (int) $data['itemcode'])
                ->first([
                    'actualitemcode',
                    'unitspercase',
                    'caseprice',
                    'defaultsalesprice',
                ]);

            abort_unless($item, 404);

            $duplicate = DB::table('inventorytransactiondetail')
                ->where('detailkey', $detailkey)
                ->where('transactiontypecode', 8)
                ->where('itemcode', (int) $data['itemcode'])
                ->exists();

            abort_if($duplicate, 422, 'Item already added to the load request.');

            $upc = max(1, (int) ($item->unitspercase ?? 1));
            $quantity = ($cases * $upc) + $pieces;

            DB::table('inventorytransactiondetail')->insert([
                'routekey' => (int) $data['routecode'],
                'detailkey' => $detailkey,
                'transactiontypecode' => 8,
                'itemcode' => (int) $data['itemcode'],
                'quantity' => $quantity,
                'requestedqty' => $quantity,
                'weighted' => 0,
                'itemprice' => (float) ($item->defaultsalesprice ?? 0),
                'batchdetailkey' => 0,
                'itemcaseprice' => (float) ($item->caseprice ?? 0),
                'currencycode' => 0,
                'record_flag' => '1',
                'expirydate' => '2099-12-31',
                'reasoncode' => 0,
                'upc' => $upc,
            ]);
        });

        return response()->json($this->requestPayload($detailkey));
    }

    public function updateLine(Request $request, int $line): JsonResponse
    {
        $data = $request->validate([
            'cases' => ['nullable', 'integer', 'min:0'],
            'pieces' => ['nullable', 'integer', 'min:0'],
        ]);

        $record = DB::table('inventorytransactiondetail')->where('primary_key', $line)->first();
        abort_unless($record, 404);

        $header = DB::table('inventorytransactionheader')->where('detailkey', (int) $record->detailkey)->first();
        abort_unless($header, 404);
        abort_unless(app(AccessScopeService::class)->allows($request->user(), 'route', $header->routecode ?? null), 403);
        abort_if((int) ($header->transmitindicator ?? 0) === 1, 422, "Request processed by warehouse can't be edited.");

        $cases = max(0, (int) ($data['cases'] ?? 0));
        $pieces = max(0, (int) ($data['pieces'] ?? 0));

        $upc = max(1, (int) ($record->upc ?? 1));
        $quantity = ($cases * $upc) + $pieces;

        DB::table('inventorytransactiondetail')
            ->where('primary_key', $line)
            ->update([
                'quantity' => $quantity,
                'requestedqty' => $quantity,
            ]);

        return response()->json($this->requestPayload((int) $record->detailkey));
    }

    public function updateHeader(Request $request, int $detailkey): JsonResponse
    {
        $data = $request->validate([
            'requestdate' => ['required', 'date'],
            'depotroute' => ['nullable', 'integer', Rule::exists('routemaster', 'routecode')],
            'isurgent' => ['nullable', 'boolean'],
        ]);

        $header = DB::table('inventorytransactionheader')->where('detailkey', $detailkey)->first();
        abort_unless($header, 404);
        abort_unless(app(AccessScopeService::class)->allows($request->user(), 'route', $header->routecode ?? null), 403);
        abort_if((int) ($header->transmitindicator ?? 0) === 1, 422, "Request processed by warehouse can't be edited.");

        DB::table('inventorytransactionheader')
            ->where('detailkey', $detailkey)
            ->update([
                'requestdate' => Carbon::parse($data['requestdate'])->startOfDay(),
                'transferlocationcode' => $data['depotroute'] ?? 0,
                'isurgent' => !empty($data['isurgent']) ? 1 : 0,
            ]);

        return response()->json($this->requestPayload($detailkey));
    }

    private function formProps(?int $detailkey = null): array
    {
        $header = $detailkey ? $this->requestHeader($detailkey) : [
            'detailkey' => null,
            'transactiondate' => now()->format('d-m-Y'),
            'transactiontime' => now()->format('H:i:s'),
            'routestartdate' => '',
            'documentnumber' => '',
            'documentvalid' => 'Valid',
            'routecode' => '',
            'routename' => '',
            'arbroutename' => '',
            'salesmancode' => '',
            'salesmanname1' => '',
            'arbsalesmanname1' => '',
            'accperiod' => '',
            'requestdate' => now()->toDateString(),
            'depotroute' => '',
            'isurgent' => 0,
            'transmitindicator' => 0,
            'receivedtime' => '',
        ];

        return [
            'mode' => $detailkey ? 'show' : 'create',
            'loadRequestData' => [
                'header' => $header,
                'lines' => $detailkey ? $this->requestLines($detailkey) : [],
            ],
            'lookupOptions' => [
                'routes' => $this->requestRouteOptions(),
                'depotRoutes' => $this->depotRouteOptions(),
                'items' => $detailkey && $header['routecode'] ? $this->routeItemOptions((int) $header['routecode']) : [],
            ],
            'formMeta' => [
                'routeMetaUrl' => route('transaction.load-request.route-meta'),
                'itemMetaUrl' => route('transaction.load-request.item-meta'),
                'lineStoreUrl' => route('transaction.load-request.lines.store'),
                'lineUpdateBaseUrl' => '/transaction/load-request/line',
                'headerUpdateBaseUrl' => '/transaction/load-request',
                'backUrl' => $this->overviewUrl(),
            ],
        ];
    }

    private function requestPayload(int $detailkey): array
    {
        return [
            'header' => $this->requestHeader($detailkey),
            'lines' => $this->requestLines($detailkey),
        ];
    }

    private function requestHeader(int $detailkey): array
    {
        $header = DB::table('inventorytransactionheader as header')
            ->leftJoin('routemaster as route', 'route.routecode', '=', 'header.routecode')
            ->leftJoin('salesman as salesman', 'salesman.salesmancode', '=', 'header.salesmancode')
            ->where('header.detailkey', $detailkey)
            ->first([
                'header.detailkey',
                'header.routecode',
                'header.salesmancode',
                'header.documentnumber',
                'header.transactiondate',
                'header.transactiontime',
                'header.requestdate',
                'header.transferlocationcode',
                'header.isurgent',
                'header.transmitindicator',
                'header.receivedtime',
                'header.voidflag',
                'route.routename',
                'route.arbroutename',
                'salesman.salesmanname1',
                'salesman.arbsalesmanname1',
            ]);

        abort_unless($header, 404);
        abort_unless(app(AccessScopeService::class)->allows(request()->user(), 'route', $header->routecode ?? null), 403);

        return [
            'detailkey' => (int) $header->detailkey,
            'transactiondate' => $this->formatDate($header->transactiondate),
            'transactiontime' => $header->transactiontime ?? '',
            'routestartdate' => '',
            'documentnumber' => (string) ($header->documentnumber ?? ''),
            'documentvalid' => $this->documentValidityLabel($header->voidflag),
            'routecode' => (int) $header->routecode,
            'routename' => $header->routename ?? '',
            'arbroutename' => $header->arbroutename ?? '',
            'salesmancode' => (int) $header->salesmancode,
            'salesmanname1' => $header->salesmanname1 ?? '',
            'arbsalesmanname1' => $header->arbsalesmanname1 ?? '',
            'accperiod' => '',
            'requestdate' => $header->requestdate ? Carbon::parse($header->requestdate)->toDateString() : now()->toDateString(),
            'depotroute' => $header->transferlocationcode !== null && (int) $header->transferlocationcode > 0 ? (int) $header->transferlocationcode : '',
            'isurgent' => (int) ($header->isurgent ?? 0),
            'transmitindicator' => (int) ($header->transmitindicator ?? 0),
            'receivedtime' => $header->receivedtime ? Carbon::parse($header->receivedtime)->format('d-m-Y H:i:s') : '',
        ];
    }

    private function requestLines(int $detailkey): array
    {
        $useAlternateCode = $this->useAlternateCode();

        return DB::table('inventorytransactiondetail as detail')
            ->join('itemmaster as item', 'item.actualitemcode', '=', 'detail.itemcode')
            ->where('detail.detailkey', $detailkey)
            ->where('detail.transactiontypecode', 8)
            ->orderBy('detail.primary_key')
            ->get([
                'detail.primary_key',
                'detail.itemcode',
                'detail.quantity',
                'detail.requestedqty',
                'detail.itemcaseprice',
                'detail.itemprice',
                'detail.upc',
                'item.alternatecode',
                'item.itemshortdescription',
            ])
            ->map(function ($line) use ($useAlternateCode) {
                $upc = max(1, (int) ($line->upc ?? 1));
                $quantity = (int) ($line->quantity ?? $line->requestedqty ?? 0);
                $displayCode = $useAlternateCode && filled($line->alternatecode)
                    ? $line->alternatecode
                    : $line->itemcode;

                return [
                    'primary_key' => (int) $line->primary_key,
                    'itemcode' => (int) $line->itemcode,
                    'display_code' => (string) $displayCode,
                    'description' => $line->itemshortdescription ?? '',
                    'upc' => $upc,
                    'itemcaseprice' => (float) ($line->itemcaseprice ?? 0),
                    'itemprice' => (float) ($line->itemprice ?? 0),
                    'cases' => intdiv($quantity, $upc),
                    'pieces' => $quantity % $upc,
                    'total_amount' => ((float) ($line->itemcaseprice ?? 0) * intdiv($quantity, $upc)) + ((float) ($line->itemprice ?? 0) * ($quantity % $upc)),
                ];
            })
            ->values()
            ->all();
    }

    private function requestRouteOptions(): array
    {
        $query = $this->actualRouteQuery('route')
            ->leftJoin('salesman as salesman', 'salesman.salesmancode', '=', 'route.salesmancode')
            ->select([
                'route.routecode',
                'route.routename',
                'route.arbroutename',
                'route.salesmancode',
                'route.enableloadrequest',
                'route.depotrouteflag',
                'salesman.salesmanname1',
                'salesman.arbsalesmanname1',
            ])
            ->orderBy('route.routecode');

        if (Schema::hasColumn('routemaster', 'activestatus')) {
            $query->where('route.activestatus', 1);
        }

        if (Schema::hasColumn('routemaster', 'depotrouteflag')) {
            $query->where('route.depotrouteflag', 0);
        }

        $routes = $query->get();
        $enabledRoutes = $routes->filter(fn ($route) => (int) ($route->enableloadrequest ?? 0) === 1);
        $source = $enabledRoutes->isNotEmpty() ? $enabledRoutes : $routes;

        return $source->map(fn ($route) => [
            'id' => (int) $route->routecode,
            'label' => trim($route->routecode . ' - ' . ($route->routename ?? '')),
            'salesmancode' => $route->salesmancode !== null ? (int) $route->salesmancode : null,
            'salesmanname' => $route->salesmanname1 ?? '',
            'arbsalesmanname' => $route->arbsalesmanname1 ?? '',
        ])->values()->all();
    }

    private function depotRouteOptions(): array
    {
        $query = $this->actualRouteQuery()
            ->select(['routecode', 'routename'])
            ->orderBy('routecode');

        if (Schema::hasColumn('routemaster', 'activestatus')) {
            $query->where('activestatus', 1);
        }

        if (Schema::hasColumn('routemaster', 'depotrouteflag')) {
            $query->where('depotrouteflag', 1);
        }

        app(AccessScopeService::class)->scopeQuery(request()->user(), $query, 'route', 'routecode');

        return $query->get()->map(fn ($route) => [
            'id' => (int) $route->routecode,
            'label' => trim($route->routecode . ' - ' . ($route->routename ?? '')),
        ])->values()->all();
    }

    private function routeLookupRecord(int $routecode): array
    {
        abort_unless(app(AccessScopeService::class)->allows(request()->user(), 'route', $routecode), 403);

        $route = $this->actualRouteQuery('route')
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
            'routename' => $route->routename ?? '',
            'salesmancode' => $route->salesmancode !== null ? (int) $route->salesmancode : null,
            'salesmanname' => $route->salesmanname1 ?? '',
        ];
    }

    private function routeItemOptions(int $routecode): array
    {
        abort_unless(app(AccessScopeService::class)->allows(request()->user(), 'route', $routecode), 403);

        $routeItemGroupCode = (int) $this->actualRouteQuery()
            ->where('routecode', $routecode)
            ->value('routeitemgrpcode');

        $query = DB::table('itemmaster as item')
            ->select([
                'item.actualitemcode',
                'item.alternatecode',
                'item.itemshortdescription',
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
                'id' => (int) $item->actualitemcode,
                'label' => trim($displayCode . ' - ' . ($item->itemshortdescription ?? '')),
            ];
        })->values()->all();
    }

    private function createHeader(array $data, int $salesmancode): int
    {
        $route = $this->actualRouteQuery()
            ->where('routecode', (int) $data['routecode'])
            ->lockForUpdate()
            ->first([
                'routecode',
                'hhcloadseq',
            ]);

        abort_unless($route, 404);

        $sequence = max(1, (int) ($route->hhcloadseq ?? 1));
        $documentNumber = (int) sprintf('%d%06d', (int) $route->routecode, $sequence);

        if (Schema::hasColumn('routemaster', 'hhcloadseq')) {
            DB::table('routemaster')
                ->where('routecode', (int) $route->routecode)
                ->update([
                    'hhcloadseq' => $sequence + 1,
                ]);
        }

        $detailkey = (int) DB::table('inventorytransactionheader')->insertGetId([
            'inventorykey' => 0,
            'routekey' => (int) $data['routecode'],
            'transactiontype' => 4,
            'routecode' => (int) $data['routecode'],
            'salesmancode' => $salesmancode,
            'transactiondate' => now()->toDateString(),
            'transactiontime' => now()->format('H:i:s'),
            'documentnumber' => $documentNumber,
            'requestdate' => Carbon::parse($data['requestdate'])->startOfDay(),
            'transmitindicator' => 0,
            'voidflag' => 0,
            'loadnumber' => 0,
            'currencycode' => 0,
            'record_flag' => '1',
            'isurgent' => !empty($data['isurgent']) ? 1 : 0,
            'transferlocationcode' => $data['depotroute'] ?? 0,
            'actualtransactiondate' => now(),
        ]);

        DB::table('inventorytransactionheader')
            ->where('detailkey', $detailkey)
            ->update([
                'inventorykey' => $detailkey,
            ]);

        return $detailkey;
    }

    private function actualRouteQuery(?string $alias = null)
    {
        $table = $alias ? 'routemaster as ' . $alias : 'routemaster';
        $query = DB::table($table);
        $prefix = $alias ? $alias . '.' : '';

        if (Schema::hasColumn('routemaster', 'routetmpl')) {
            $query->where(function ($routeQuery) use ($prefix) {
                $routeQuery->whereNull($prefix . 'routetmpl')
                    ->orWhere($prefix . 'routetmpl', 0);
            });
        }

        app(AccessScopeService::class)->scopeQuery(request()->user(), $query, 'route', $prefix . 'routecode');

        return $query;
    }

    private function overviewUrl(): string
    {
        $query = request()->only(['date', 'routecode', 'search', 'page', 'per_page', 'sort_by', 'sort_dir']);
        $query = array_filter($query, fn ($value) => $value !== null && $value !== '');

        return route('transaction.load-request') . (empty($query) ? '' : '?' . http_build_query($query));
    }

    private function selectedDate(Request $request): Carbon
    {
        $date = (string) $request->input('date', '');

        try {
            return $date !== '' ? Carbon::parse($date) : now();
        } catch (\Throwable) {
            return now();
        }
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

    private function emptyPaginator(Request $request, int $perPage): LengthAwarePaginator
    {
        return new \Illuminate\Pagination\LengthAwarePaginator(
            [],
            0,
            $perPage,
            max(1, (int) $request->input('page', 1)),
            [
                'path' => $request->url(),
                'query' => $request->query(),
            ]
        );
    }

    private function sortColumn(string $sortBy): string
    {
        return match ($sortBy) {
            'routecode' => 'header.routecode',
            'salesmanname1' => 'salesman.salesmanname1',
            'routename' => 'route.routename',
            'documentnumber' => 'header.documentnumber',
            default => Schema::hasColumn('inventorytransactionheader', 'transactiontime')
                ? 'header.transactiontime'
                : 'header.transactiondate',
        };
    }

    private function transactionTimeExpression(string $alias): string
    {
        if (Schema::hasColumn('inventorytransactionheader', 'transactiontime')) {
            return 'COALESCE(' . $alias . '.transactiontime, "")';
        }

        return 'DATE_FORMAT(' . $alias . '.transactiondate, "%H:%i:%s")';
    }

    private function formatDate(mixed $value): string
    {
        if (!$value) {
            return '';
        }

        try {
            return Carbon::parse($value)->format('d-m-Y');
        } catch (\Throwable) {
            return (string) $value;
        }
    }

    private function documentValidityLabel(mixed $value): string
    {
        if ($value === null || $value === '') {
            return 'Valid';
        }

        if (is_numeric($value)) {
            return (int) $value === 1 ? 'Void' : 'Valid';
        }

        return (string) $value;
    }

    private function prefixedAlias(string $alias): string
    {
        return DB::getTablePrefix() . $alias;
    }
}
