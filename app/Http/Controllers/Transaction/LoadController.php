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

class LoadController extends Controller
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
            return Inertia::render('transaction/load/Index', [
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

        $query = DB::table('inventorytransactionheader as header')
            ->leftJoin('routemaster as route', 'route.routecode', '=', 'header.routecode')
            ->leftJoin('salesman as salesman', 'salesman.salesmancode', '=', 'header.salesmancode')
            ->selectRaw("
                {$headerAlias}.detailkey,
                {$headerAlias}.routecode,
                COALESCE({$routeAlias}.routename, '') as routename,
                COALESCE({$routeAlias}.arbroutename, '') as arbroutename,
                COALESCE({$salesmanAlias}.salesmanname1, '') as salesmanname1,
                COALESCE({$salesmanAlias}.arbsalesmanname1, '') as arbsalesmanname1,
                COALESCE({$headerAlias}.documentnumber, '') as documentnumber,
                {$this->transactionTimeExpression($headerAlias)} as transactiontime
            ")
            ->whereDate('header.transactiondate', $selectedDate)
            ->whereExists(function ($subQuery) {
                $subQuery->select(DB::raw(1))
                    ->from('inventorytransactiondetail as detail')
                    ->whereColumn('detail.detailkey', 'header.detailkey');

                if (Schema::hasColumn('inventorytransactiondetail', 'transactiontypecode')) {
                    $subQuery->where('detail.transactiontypecode', 1);
                }
            });

        $scope->scopeQuery($user, $query, 'route', 'header.routecode');

        if (Schema::hasColumn('inventorytransactionheader', 'transactiontype')) {
            $query->where('header.transactiontype', 1);
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

        return Inertia::render('transaction/load/Index', [
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

        $headerAlias = $this->prefixedAlias('header');
        $routeAlias = $this->prefixedAlias('route');
        $salesmanAlias = $this->prefixedAlias('salesman');
        $detailAlias = $this->prefixedAlias('detail');
        $itemAlias = $this->prefixedAlias('item');

        $header = DB::table('inventorytransactionheader as header')
            ->leftJoin('routemaster as route', 'route.routecode', '=', 'header.routecode')
            ->leftJoin('salesman as salesman', 'salesman.salesmancode', '=', 'header.salesmancode')
            ->where('header.detailkey', $detailkey)
            ->selectRaw("
                {$headerAlias}.detailkey,
                {$this->columnExpression('inventorytransactionheader', 'routekey', "{$headerAlias}.routekey")} as routekey,
                {$headerAlias}.routecode,
                COALESCE({$routeAlias}.routename, '') as routename,
                COALESCE({$routeAlias}.arbroutename, '') as arbroutename,
                COALESCE({$salesmanAlias}.salesmanname1, '') as salesmanname1,
                COALESCE({$salesmanAlias}.arbsalesmanname1, '') as arbsalesmanname1,
                COALESCE({$headerAlias}.documentnumber, '') as documentnumber,
                {$headerAlias}.transactiondate,
                {$this->transactionTimeExpression($headerAlias)} as transactiontime,
                {$this->columnExpression('inventorytransactionheader', 'routestartdate', "{$headerAlias}.routestartdate")} as routestartdate,
                {$this->columnExpression('inventorytransactionheader', 'accperiod', "{$headerAlias}.accperiod")} as accperiod,
                {$this->columnExpression('inventorytransactionheader', 'voidflag', "{$headerAlias}.voidflag")} as voidflag,
                {$this->columnExpression('inventorytransactionheader', 'loadnumber', "{$headerAlias}.loadnumber")} as loadnumber
            ")
            ->first();

        abort_unless($header, 404);
        abort_unless(app(AccessScopeService::class)->allows($request->user(), 'route', $header->routecode ?? null), 403);

        $useAlternateCode = $this->useAlternateCode();
        $casePriceExpression = Schema::hasColumn('inventorytransactiondetail', 'itemcaseprice')
            ? "COALESCE({$detailAlias}.itemcaseprice, 0)"
            : "COALESCE({$itemAlias}.caseprice, 0)";
        $pcsPriceExpression = Schema::hasColumn('inventorytransactiondetail', 'itemprice')
            ? "COALESCE({$detailAlias}.itemprice, 0)"
            : "COALESCE({$itemAlias}.defaultsalesprice, 0)";
        $quantityExpression = Schema::hasColumn('inventorytransactiondetail', 'quantity')
            ? "COALESCE({$detailAlias}.quantity, 0)"
            : '0';

        $lineQuery = DB::table('inventorytransactiondetail as detail')
            ->join('itemmaster as item', 'item.actualitemcode', '=', 'detail.itemcode')
            ->where('detail.detailkey', $detailkey)
            ->selectRaw("
                {$detailAlias}.itemcode,
                COALESCE({$itemAlias}.alternatecode, '') as alternatecode,
                COALESCE({$itemAlias}.itemshortdescription, '') as itemshortdescription,
                COALESCE({$itemAlias}.unitspercase, 1) as unitspercase,
                {$casePriceExpression} as itemcaseprice,
                {$pcsPriceExpression} as itemprice,
                {$quantityExpression} as quantity
            ")
            ->orderBy('detail.itemcode');

        if (Schema::hasColumn('inventorytransactiondetail', 'routekey') && filled($header->routekey)) {
            $lineQuery->where('detail.routekey', $header->routekey);
        }

        if (Schema::hasColumn('inventorytransactiondetail', 'transactiontypecode')) {
            $lineQuery->where('detail.transactiontypecode', 1);
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

        return Inertia::render('transaction/load/Show', [
            'header' => [
                'detailkey' => (int) $header->detailkey,
                'routekey' => (int) ($header->routekey ?? 0),
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
                'loadnumber' => $header->loadnumber ?? '',
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

    private function prefixedAlias(string $alias): string
    {
        return DB::getTablePrefix() . $alias;
    }
}
