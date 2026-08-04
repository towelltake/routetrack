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

class DamageReturnController extends Controller
{
    public function index(Request $request): Response
    {
        $scope = app(AccessScopeService::class);
        $user = $request->user();
        $allowedPerPage = [10, 25, 50, 100];
        $allowedSorts = ['routecode', 'customername', 'invoicenumber', 'documentnumber', 'transactiontime'];
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

        if (!$this->hasHeaderTables()) {
            return Inertia::render('transaction/damage-return/Index', [
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

        $headerAlias = $this->qualifiedAlias('header');
        $routeAlias = $this->qualifiedAlias('route');
        $salesmanAlias = $this->qualifiedAlias('salesman');
        $customerAlias = $this->qualifiedAlias('customer');

        $query = DB::table('invoiceheader as header')
            ->leftJoin('routemaster as route', 'route.routecode', '=', 'header.routecode')
            ->leftJoin('salesman as salesman', 'salesman.salesmancode', '=', 'header.salesmancode')
            ->leftJoin('customermaster as customer', 'customer.customercode', '=', 'header.customercode')
            ->selectRaw(
                "
                {$headerAlias}.transactionkey,
                {$headerAlias}.routecode,
                COALESCE({$routeAlias}.routename, '') as routename,
                COALESCE({$routeAlias}.arbroutename, '') as arbroutename,
                COALESCE({$salesmanAlias}.salesmanname1, '') as salesmanname1,
                COALESCE({$salesmanAlias}.arbsalesmanname1, '') as arbsalesmanname1,
                COALESCE({$headerAlias}.documentnumber, '') as documentnumber,
                COALESCE({$headerAlias}.invoicenumber, '') as invoicenumber,
                {$headerAlias}.customercode,
                COALESCE({$customerAlias}.alternatecode, '') as alternatecode,
                COALESCE({$customerAlias}.customername, '') as customername,
                COALESCE({$customerAlias}.arbcustomername, '') as arbcustomername,
                "
                . $this->transactionTimeExpression('header') . " as transactiontime,
                "
                . $this->numericColumnExpression('invoiceheader', 'totaldamagedamount', 'header.totaldamagedamount') . " as totaldamagedamount
            "
            )
            ->whereDate('header.transactiondate', $selectedDate)
            ->where(function ($damageQuery) {
                if (Schema::hasColumn('invoiceheader', 'totaldamagedamount')) {
                    $damageQuery->where('header.totaldamagedamount', '>', 0);
                }

                if (Schema::hasTable('invoicedetail') && (Schema::hasColumn('invoicedetail', 'damagedqty') || Schema::hasColumn('invoicedetail', 'expiryqty'))) {
                    $damageQuery->orWhereExists(function ($subQuery) {
                        $subQuery->select(DB::raw(1))
                            ->from('invoicedetail as detail')
                            ->whereColumn('detail.transactionkey', 'header.transactionkey')
                            ->whereRaw($this->damageQuantityExpression('detail') . ' > 0');
                    });
                }
            });

        $scope->scopeQuery($user, $query, 'route', 'header.routecode');

        if (Schema::hasColumn('invoiceheader', 'record_flag')) {
            $query->where('header.record_flag', '1');
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
                    ->orWhere('header.invoicenumber', 'like', $like)
                    ->orWhere('header.customercode', 'like', $like)
                    ->orWhere('route.routename', 'like', $like)
                    ->orWhere('route.arbroutename', 'like', $like)
                    ->orWhere('salesman.salesmanname1', 'like', $like)
                    ->orWhere('salesman.arbsalesmanname1', 'like', $like)
                    ->orWhere('customer.alternatecode', 'like', $like)
                    ->orWhere('customer.customername', 'like', $like)
                    ->orWhere('customer.arbcustomername', 'like', $like);
            });
        }

        $documents = $query
            ->orderBy($this->sortColumn($sortBy), $sortDir)
            ->orderBy('header.transactionkey', 'desc')
            ->paginate($perPage)
            ->withQueryString()
            ->through(fn ($row) => [
                'transactionkey' => (int) $row->transactionkey,
                'routecode' => (int) ($row->routecode ?? 0),
                'routename' => $row->routename,
                'arbroutename' => $row->arbroutename,
                'salesmanname1' => $row->salesmanname1,
                'arbsalesmanname1' => $row->arbsalesmanname1,
                'documentnumber' => $this->identifier($row->documentnumber),
                'invoicenumber' => $this->identifier($row->invoicenumber),
                'customercode' => (int) ($row->customercode ?? 0),
                'alternatecode' => $row->alternatecode,
                'customername' => $row->customername,
                'arbcustomername' => $row->arbcustomername,
                'transactiontime' => $row->transactiontime,
                'totaldamagedamount' => (float) ($row->totaldamagedamount ?? 0),
            ]);

        return Inertia::render('transaction/damage-return/Index', [
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

    public function show(Request $request, int $transactionkey): Response
    {
        abort_unless($this->hasHeaderTables(), 404);

        $headerAlias = $this->qualifiedAlias('header');
        $routeAlias = $this->qualifiedAlias('route');
        $salesmanAlias = $this->qualifiedAlias('salesman');
        $customerAlias = $this->qualifiedAlias('customer');

        $header = DB::table('invoiceheader as header')
            ->leftJoin('routemaster as route', 'route.routecode', '=', 'header.routecode')
            ->leftJoin('salesman as salesman', 'salesman.salesmancode', '=', 'header.salesmancode')
            ->leftJoin('customermaster as customer', 'customer.customercode', '=', 'header.customercode')
            ->where('header.transactionkey', $transactionkey)
            ->selectRaw(
                "
                {$headerAlias}.transactionkey,
                {$headerAlias}.transactiondate,
                "
                . $this->transactionTimeExpression('header') . " as transactiontime,
                COALESCE({$headerAlias}.documentnumber, '') as documentnumber,
                COALESCE({$headerAlias}.invoicenumber, '') as invoicenumber,
                {$headerAlias}.routecode,
                COALESCE({$routeAlias}.routename, '') as routename,
                COALESCE({$routeAlias}.arbroutename, '') as arbroutename,
                COALESCE({$salesmanAlias}.salesmanname1, '') as salesmanname1,
                COALESCE({$salesmanAlias}.arbsalesmanname1, '') as arbsalesmanname1,
                COALESCE({$customerAlias}.customername, '') as customername,
                COALESCE({$customerAlias}.arbcustomername, '') as arbcustomername,
                "
                . $this->stringColumnExpression('invoiceheader', 'routestartdate', 'header.routestartdate') . " as routestartdate,
                "
                . $this->stringColumnExpression('invoiceheader', 'accperiod', 'header.accperiod') . " as accperiod,
                "
                . $this->numericColumnExpression('invoiceheader', 'voidflag', 'header.voidflag') . " as voidflag,
                "
                . $this->numericColumnExpression('invoiceheader', 'totaldamagedamount', 'header.totaldamagedamount') . " as totaldamagedamount
            "
            )
            ->first();

        abort_unless($header, 404);
        abort_unless(app(AccessScopeService::class)->allows($request->user(), 'route', $header->routecode ?? null), 403);

        $useAlternateCode = $this->useAlternateCode();
        $lines = collect();

        if ($this->hasDetailTables()) {
            $detailAlias = $this->qualifiedAlias('detail');
            $itemAlias = $this->qualifiedAlias('item');
            $lineHeaderAlias = $this->qualifiedAlias('header');

            $lines = DB::table('invoicedetail as detail')
                ->join('itemmaster as item', 'item.actualitemcode', '=', 'detail.itemcode')
                ->where('detail.transactionkey', $transactionkey)
                ->whereRaw($this->damageQuantityExpression('detail') . ' > 0')
                ->selectRaw(
                    "
                    {$detailAlias}.itemcode,
                    COALESCE({$itemAlias}.alternatecode, '') as alternatecode,
                    "
                    . $this->itemDescriptionExpression('item') . " as itemdescription,
                    "
                    . $this->arabicItemDescriptionExpression('item') . " as arbitemdescription,
                    "
                    . $this->unitsPerCaseExpression('item') . " as upc,
                    COALESCE({$lineHeaderAlias}.invoicenumber, '') as invoicenumber,
                    "
                    . $this->numericColumnExpression('invoicedetail', 'returncaseprice', 'detail.returncaseprice') . " as returncaseprice,
                    "
                    . $this->numericColumnExpression('invoicedetail', 'returnprice', 'detail.returnprice') . " as returnprice,
                    "
                    . $this->damageQuantityExpression('detail') . " as quantity,
                    "
                    . $this->damageAmountExpression('detail', 'item') . " as damaged_amount
                "
                )
                ->join('invoiceheader as header', 'header.transactionkey', '=', 'detail.transactionkey')
                ->orderBy('detail.itemcode')
                ->get()
                ->map(function ($row) use ($useAlternateCode) {
                    $upc = max(1, (int) ($row->upc ?? 1));
                    $quantity = (int) ($row->quantity ?? 0);

                    return [
                        'itemcode' => (int) $row->itemcode,
                        'display_code' => $useAlternateCode && filled($row->alternatecode)
                            ? (string) $row->alternatecode
                            : (string) $row->itemcode,
                        'description' => $row->itemdescription,
                        'arbdescription' => $row->arbitemdescription,
                        'invoicenumber' => $this->identifier($row->invoicenumber),
                        'upc' => $upc,
                        'returncaseprice' => (float) ($row->returncaseprice ?? 0),
                        'returnprice' => (float) ($row->returnprice ?? 0),
                        'quantity' => $quantity,
                        'cases' => intdiv($quantity, $upc),
                        'pieces' => $quantity % $upc,
                        'damaged_amount' => (float) ($row->damaged_amount ?? 0),
                    ];
                })
                ->values();
        }

        return Inertia::render('transaction/damage-return/Show', [
            'header' => [
                'transactionkey' => (int) $header->transactionkey,
                'transactiondate' => $this->formatDate($header->transactiondate),
                'transactiontime' => $header->transactiontime,
                'routestartdate' => $this->formatDate($header->routestartdate),
                'documentnumber' => $this->identifier($header->documentnumber),
                'invoicenumber' => $this->identifier($header->invoicenumber),
                'documentvalid' => $this->documentValidityLabel($header->voidflag),
                'routecode' => (int) ($header->routecode ?? 0),
                'routename' => $header->routename,
                'arbroutename' => $header->arbroutename,
                'accperiod' => $header->accperiod ?? '',
                'salesmanname1' => $header->salesmanname1,
                'arbsalesmanname1' => $header->arbsalesmanname1,
                'customername' => $header->customername,
                'arbcustomername' => $header->arbcustomername,
                'totaldamagedamount' => (float) ($header->totaldamagedamount ?? 0),
            ],
            'lines' => $lines,
            'filters' => [
                'date' => (string) $request->input('date', ''),
                'routecode' => max(0, (int) $request->input('routecode', 0)),
                'search' => (string) $request->input('search', ''),
                'page' => max(1, (int) $request->input('page', 1)),
                'per_page' => max(10, (int) $request->input('per_page', 10)),
                'sort_by' => (string) $request->input('sort_by', 'transactiontime'),
                'sort_dir' => (string) $request->input('sort_dir', 'desc'),
            ],
        ]);
    }

    private function hasHeaderTables(): bool
    {
        return Schema::hasTable('invoiceheader')
            && Schema::hasTable('routemaster')
            && Schema::hasTable('salesman')
            && Schema::hasTable('customermaster');
    }

    private function hasDetailTables(): bool
    {
        return Schema::hasTable('invoicedetail') && Schema::hasTable('itemmaster');
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
        if (!Schema::hasTable('routemaster')) {
            return [];
        }

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
            'customername' => 'customer.customername',
            'invoicenumber' => 'header.invoicenumber',
            'documentnumber' => 'header.documentnumber',
            default => Schema::hasColumn('invoiceheader', 'transactiontime')
                ? 'header.transactiontime'
                : 'header.transactiondate',
        };
    }

    private function transactionTimeExpression(string $alias): string
    {
        $qualifiedAlias = $this->qualifiedAlias($alias);

        if (Schema::hasColumn('invoiceheader', 'transactiontime')) {
            return 'COALESCE(' . $qualifiedAlias . '.transactiontime, "")';
        }

        return 'DATE_FORMAT(' . $qualifiedAlias . '.transactiondate, "%H:%i:%s")';
    }

    private function itemDescriptionExpression(string $alias): string
    {
        $qualifiedAlias = $this->qualifiedAlias($alias);
        $descriptionColumns = [];

        if (Schema::hasColumn('itemmaster', 'itemshortdescription')) {
            $descriptionColumns[] = $qualifiedAlias . '.itemshortdescription';
        }

        if (Schema::hasColumn('itemmaster', 'itemdescription')) {
            $descriptionColumns[] = $qualifiedAlias . '.itemdescription';
        }

        if (empty($descriptionColumns)) {
            return '""';
        }

        return 'COALESCE(' . implode(', ', $descriptionColumns) . ', "")';
    }

    private function arabicItemDescriptionExpression(string $alias): string
    {
        $qualifiedAlias = $this->qualifiedAlias($alias);
        $descriptionColumns = [];

        if (Schema::hasColumn('itemmaster', 'arbitemshortdescription')) {
            $descriptionColumns[] = $qualifiedAlias . '.arbitemshortdescription';
        }

        if (Schema::hasColumn('itemmaster', 'arbitemdescription')) {
            $descriptionColumns[] = $qualifiedAlias . '.arbitemdescription';
        }

        if (empty($descriptionColumns)) {
            return '""';
        }

        return 'COALESCE(' . implode(', ', $descriptionColumns) . ', "")';
    }

    private function unitsPerCaseExpression(string $alias): string
    {
        $qualifiedAlias = $this->qualifiedAlias($alias);

        return Schema::hasColumn('itemmaster', 'unitspercase')
            ? 'COALESCE(' . $qualifiedAlias . '.unitspercase, 1)'
            : '1';
    }

    private function damageQuantityExpression(string $detailAlias): string
    {
        $qualifiedAlias = $this->qualifiedAlias($detailAlias);
        $parts = [];

        if (Schema::hasColumn('invoicedetail', 'damagedqty')) {
            $parts[] = 'COALESCE(' . $qualifiedAlias . '.damagedqty, 0)';
        }

        if (Schema::hasColumn('invoicedetail', 'expiryqty')) {
            $parts[] = 'COALESCE(' . $qualifiedAlias . '.expiryqty, 0)';
        }

        if (empty($parts)) {
            return '0';
        }

        return '(' . implode(' + ', $parts) . ')';
    }

    private function damageAmountExpression(string $detailAlias, string $itemAlias): string
    {
        $qualifiedDetailAlias = $this->qualifiedAlias($detailAlias);

        if (Schema::hasColumn('invoicedetail', 'return_amount')) {
            return 'COALESCE(' . $qualifiedDetailAlias . '.return_amount, 0)';
        }

        return '('
            . 'FLOOR(' . $this->damageQuantityExpression($detailAlias) . ' / ' . $this->unitsPerCaseExpression($itemAlias) . ') * COALESCE(' . $qualifiedDetailAlias . '.returncaseprice, 0)'
            . ' + MOD(' . $this->damageQuantityExpression($detailAlias) . ', ' . $this->unitsPerCaseExpression($itemAlias) . ') * COALESCE(' . $qualifiedDetailAlias . '.returnprice, 0)'
            . ')';
    }

    private function numericColumnExpression(string $table, string $column, string $qualifiedColumn): string
    {
        return Schema::hasColumn($table, $column)
            ? 'COALESCE(' . $this->qualifiedColumn($qualifiedColumn) . ', 0)'
            : '0';
    }

    private function stringColumnExpression(string $table, string $column, string $qualifiedColumn): string
    {
        return Schema::hasColumn($table, $column)
            ? 'COALESCE(' . $this->qualifiedColumn($qualifiedColumn) . ', "")'
            : '""';
    }

    private function qualifiedAlias(string $alias): string
    {
        return DB::getTablePrefix() . $alias;
    }

    private function qualifiedColumn(string $qualifiedColumn): string
    {
        if (!str_contains($qualifiedColumn, '.')) {
            return $qualifiedColumn;
        }

        [$alias, $column] = explode('.', $qualifiedColumn, 2);

        return $this->qualifiedAlias($alias) . '.' . $column;
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

    private function identifier(mixed $value): string
    {
        $string = trim((string) ($value ?? ''));

        if ($string === '') {
            return '';
        }

        if (preg_match('/^\d+\.0+$/', $string) === 1) {
            return strstr($string, '.', true) ?: $string;
        }

        return $string;
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
