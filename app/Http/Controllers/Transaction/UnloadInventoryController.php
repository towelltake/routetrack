<?php

namespace App\Http\Controllers\Transaction;

use App\Http\Controllers\Controller;
use App\Services\AccessScopeService;
use Carbon\Carbon;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Inertia\Inertia;
use Inertia\Response;

class UnloadInventoryController extends Controller
{
    private const LINE_TYPES = [11, 14, 5];

    public function index(Request $request): Response
    {
        $scope = app(AccessScopeService::class);
        $user = $request->user();
        $headerAlias = 'header';
        $routeAlias = 'route';
        $salesmanAlias = 'salesman';
        $qualifiedHeaderAlias = DB::getTablePrefix() . $headerAlias;
        $qualifiedRouteAlias = DB::getTablePrefix() . $routeAlias;
        $qualifiedSalesmanAlias = DB::getTablePrefix() . $salesmanAlias;
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
            return Inertia::render('transaction/unload-inventory/Index', [
                'documents' => $this->emptyPaginator($request, $perPage),
                'routeOptions' => $this->routeOptions(),
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

        $query = DB::table("inventorytransactionheader as {$headerAlias}")
            ->leftJoin("routemaster as {$routeAlias}", "{$routeAlias}.routecode", '=', "{$headerAlias}.routecode")
            ->leftJoin("salesman as {$salesmanAlias}", "{$salesmanAlias}.salesmancode", '=', "{$headerAlias}.salesmancode")
            ->selectRaw('
                ' . $qualifiedHeaderAlias . '.detailkey,
                ' . $qualifiedHeaderAlias . '.routecode,
                COALESCE(' . $qualifiedRouteAlias . '.routename, "") as routename,
                COALESCE(' . $qualifiedRouteAlias . '.arbroutename, "") as arbroutename,
                COALESCE(' . $qualifiedSalesmanAlias . '.salesmanname1, "") as salesmanname1,
                COALESCE(' . $qualifiedSalesmanAlias . '.arbsalesmanname1, "") as arbsalesmanname1,
                COALESCE(' . $qualifiedHeaderAlias . '.documentnumber, "") as documentnumber,
                ' . $this->transactionTimeExpression($qualifiedHeaderAlias) . ' as transactiontime
            ')
            ->whereDate("{$headerAlias}.transactiondate", $selectedDate)
            ->whereExists(function ($subQuery) {
                $subQuery->select(DB::raw(1))
                    ->from('inventorytransactiondetail as detail')
                    ->whereColumn('detail.detailkey', 'header.detailkey');

                if (Schema::hasColumn('inventorytransactiondetail', 'transactiontypecode')) {
                    $subQuery->whereIn('detail.transactiontypecode', self::LINE_TYPES);
                }
            });

        $scope->scopeQuery($user, $query, 'route', "{$headerAlias}.routecode");

        if (Schema::hasColumn('inventorytransactionheader', 'transactiontype')) {
            $query->where("{$headerAlias}.transactiontype", 3);
        }

        if ($selectedRoute > 0) {
            $query->where("{$headerAlias}.routecode", $selectedRoute);
        }

        if ($search !== '') {
            $query->where(function ($searchQuery) use ($search, $headerAlias, $routeAlias, $salesmanAlias) {
                $like = '%' . $search . '%';

                $searchQuery
                    ->where("{$headerAlias}.routecode", 'like', $like)
                    ->orWhere("{$headerAlias}.documentnumber", 'like', $like)
                    ->orWhere("{$routeAlias}.routename", 'like', $like)
                    ->orWhere("{$routeAlias}.arbroutename", 'like', $like)
                    ->orWhere("{$salesmanAlias}.salesmanname1", 'like', $like)
                    ->orWhere("{$salesmanAlias}.arbsalesmanname1", 'like', $like);
            });
        }

        $documents = $query
            ->orderBy($this->sortColumn($sortBy), $sortDir)
            ->orderBy("{$headerAlias}.detailkey", 'desc')
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

        return Inertia::render('transaction/unload-inventory/Index', [
            'documents' => $documents,
            'routeOptions' => $this->routeOptions(),
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

    public function show(Request $request, int $detailkey): Response
    {
        abort_unless(Schema::hasTable('inventorytransactionheader') && Schema::hasTable('inventorytransactiondetail'), 404);
        $headerAlias = 'header';
        $routeAlias = 'route';
        $salesmanAlias = 'salesman';
        $detailAlias = 'detail';
        $itemAlias = 'item';
        $qualifiedHeaderAlias = DB::getTablePrefix() . $headerAlias;
        $qualifiedRouteAlias = DB::getTablePrefix() . $routeAlias;
        $qualifiedSalesmanAlias = DB::getTablePrefix() . $salesmanAlias;
        $qualifiedDetailAlias = DB::getTablePrefix() . $detailAlias;
        $qualifiedItemAlias = DB::getTablePrefix() . $itemAlias;

        $header = DB::table("inventorytransactionheader as {$headerAlias}")
            ->leftJoin("routemaster as {$routeAlias}", "{$routeAlias}.routecode", '=', "{$headerAlias}.routecode")
            ->leftJoin("salesman as {$salesmanAlias}", "{$salesmanAlias}.salesmancode", '=', "{$headerAlias}.salesmancode")
            ->where("{$headerAlias}.detailkey", $detailkey)
            ->selectRaw('
                ' . $qualifiedHeaderAlias . '.detailkey,
                ' . $qualifiedHeaderAlias . '.routecode,
                COALESCE(' . $qualifiedRouteAlias . '.routename, "") as routename,
                COALESCE(' . $qualifiedRouteAlias . '.arbroutename, "") as arbroutename,
                COALESCE(' . $qualifiedSalesmanAlias . '.salesmanname1, "") as salesmanname1,
                COALESCE(' . $qualifiedSalesmanAlias . '.arbsalesmanname1, "") as arbsalesmanname1,
                COALESCE(' . $qualifiedHeaderAlias . '.documentnumber, "") as documentnumber,
                ' . $qualifiedHeaderAlias . '.transactiondate,
                ' . $this->transactionTimeExpression($qualifiedHeaderAlias) . ' as transactiontime,
                ' . $this->columnExpression('inventorytransactionheader', 'routestartdate', $qualifiedHeaderAlias . '.routestartdate') . ' as routestartdate,
                ' . $this->columnExpression('inventorytransactionheader', 'accperiod', $qualifiedHeaderAlias . '.accperiod') . ' as accperiod,
                ' . $this->columnExpression('inventorytransactionheader', 'voidflag', $qualifiedHeaderAlias . '.voidflag') . ' as voidflag
            ')
            ->first();

        abort_unless($header, 404);
        abort_unless(app(AccessScopeService::class)->allows($request->user(), 'route', $header->routecode ?? null), 403);

        $useAlternateCode = $this->useAlternateCode();
        $casePriceExpression = Schema::hasColumn('inventorytransactiondetail', 'itemcaseprice')
            ? 'COALESCE(' . $qualifiedDetailAlias . '.itemcaseprice, 0)'
            : 'COALESCE(' . $qualifiedItemAlias . '.caseprice, 0)';
        $pcsPriceExpression = Schema::hasColumn('inventorytransactiondetail', 'itemprice')
            ? 'COALESCE(' . $qualifiedDetailAlias . '.itemprice, 0)'
            : 'COALESCE(' . $qualifiedItemAlias . '.defaultsalesprice, 0)';
        $quantityExpression = Schema::hasColumn('inventorytransactiondetail', 'quantity')
            ? 'COALESCE(' . $qualifiedDetailAlias . '.quantity, 0)'
            : '0';

        $lineQuery = DB::table("inventorytransactiondetail as {$detailAlias}")
            ->join("itemmaster as {$itemAlias}", "{$itemAlias}.actualitemcode", '=', "{$detailAlias}.itemcode")
            ->where("{$detailAlias}.detailkey", $detailkey)
            ->selectRaw('
                ' . $qualifiedDetailAlias . '.itemcode,
                COALESCE(' . $qualifiedItemAlias . '.alternatecode, "") as alternatecode,
                COALESCE(' . $qualifiedItemAlias . '.itemshortdescription, "") as itemshortdescription,
                COALESCE(' . $qualifiedItemAlias . '.unitspercase, 1) as unitspercase,
                ' . $casePriceExpression . ' as itemcaseprice,
                ' . $pcsPriceExpression . ' as itemprice,
                ' . $quantityExpression . ' as quantity
            ')
            ->orderBy("{$detailAlias}.itemcode");

        if (Schema::hasColumn('inventorytransactiondetail', 'transactiontypecode')) {
            $lineQuery->whereIn("{$detailAlias}.transactiontypecode", self::LINE_TYPES);
        }

        $lines = $lineQuery->get()->map(function ($row) use ($useAlternateCode) {
            $upc = max(1, (int) ($row->unitspercase ?? 1));
            $quantity = (int) ($row->quantity ?? 0);
            $cases = intdiv($quantity, $upc);
            $pieces = $quantity % $upc;
            $casePrice = (float) ($row->itemcaseprice ?? 0);
            $pcsPrice = (float) ($row->itemprice ?? 0);

            return [
                'itemcode' => (int) $row->itemcode,
                'display_code' => $useAlternateCode && filled($row->alternatecode)
                    ? (string) $row->alternatecode
                    : (string) $row->itemcode,
                'description' => $row->itemshortdescription,
                'upc' => $upc,
                'itemcaseprice' => $casePrice,
                'itemprice' => $pcsPrice,
                'cases' => $cases,
                'pieces' => $pieces,
                'total_amount' => ($casePrice * $cases) + ($pcsPrice * $pieces),
            ];
        })->values();

        return Inertia::render('transaction/unload-inventory/Show', [
            'header' => [
                'detailkey' => (int) $header->detailkey,
                'transactiondate' => $this->formatDate($header->transactiondate),
                'transactiontime' => $header->transactiontime,
                'routestartdate' => $this->formatDate($header->routestartdate),
                'documentnumber' => $header->documentnumber,
                'documentvalid' => $this->documentValidityLabel($header->voidflag),
                'routecode' => (int) $header->routecode,
                'routename' => $header->routename,
                'arbroutename' => $header->arbroutename,
                'accperiod' => $header->accperiod ?? '',
                'salesmanname1' => $header->salesmanname1,
                'arbsalesmanname1' => $header->arbsalesmanname1,
            ],
            'lines' => $lines,
            'filters' => [
                'date' => $request->input('date', ''),
                'routecode' => max(0, (int) $request->input('routecode', 0)),
                'search' => (string) $request->input('search', ''),
                'page' => max(1, (int) $request->input('page', 1)),
                'per_page' => max(10, (int) $request->input('per_page', 10)),
                'sort_by' => (string) $request->input('sort_by', 'transactiontime'),
                'sort_dir' => (string) $request->input('sort_dir', 'desc'),
            ],
        ]);
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

    private function routeOptions(): array
    {
        $query = DB::table('routemaster')
            ->select(['routecode', 'routename'])
            ->orderBy('routecode');

        if (Schema::hasColumn('routemaster', 'activestatus')) {
            $query->where('activestatus', 1);
        }

        app(AccessScopeService::class)->scopeQuery(request()->user(), $query, 'route', 'routecode');

        return $query->get()->map(fn ($route) => [
            'id' => (int) $route->routecode,
            'label' => trim($route->routecode . ' - ' . ($route->routename ?? '')),
        ])->values()->all();
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

    private function columnExpression(string $table, string $column, string $qualifiedColumn): string
    {
        return Schema::hasColumn($table, $column)
            ? 'COALESCE(' . $qualifiedColumn . ', "")'
            : '""';
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
            return '';
        }

        if (is_numeric($value)) {
            return (int) $value === 1 ? 'Void' : 'Valid';
        }

        return (string) $value;
    }
}
