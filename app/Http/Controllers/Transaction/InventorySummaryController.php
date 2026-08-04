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

class InventorySummaryController extends Controller
{
    public function index(Request $request): Response
    {
        $scope = app(AccessScopeService::class);
        $user = $request->user();
        $allowedPerPage = [10, 25, 50, 100];
        $allowedSorts = ['routecode', 'routename', 'salesmancode', 'routestartdate', 'routeenddate', 'routeclosed'];
        $perPage = (int) $request->input('per_page', 10);
        $sortBy = (string) $request->input('sort_by', 'routestartdate');
        $sortDir = $request->input('sort_dir', 'desc') === 'asc' ? 'asc' : 'desc';
        $selectedDate = $this->selectedDate($request)->toDateString();
        $search = trim((string) $request->input('search', ''));
        $dayAlias = DB::getTablePrefix() . 'day';
        $routeAlias = DB::getTablePrefix() . 'route';

        if (!in_array($perPage, $allowedPerPage, true)) {
            $perPage = 10;
        }

        if (!in_array($sortBy, $allowedSorts, true)) {
            $sortBy = 'routestartdate';
        }

        if (!$this->hasOverviewTables()) {
            return Inertia::render('transaction/inventory-summary/Index', [
                'documents' => $this->emptyPaginator($request, $perPage),
                'filters' => [
                    'date' => $selectedDate,
                    'search' => $search,
                    'per_page' => $perPage,
                    'sort_by' => $sortBy,
                    'sort_dir' => $sortDir,
                ],
            ]);
        }

        $query = DB::table('startendday as day')
            ->leftJoin('routemaster as route', 'route.routecode', '=', 'day.routecode')
            ->selectRaw('
                ' . $dayAlias . '.routekey,
                ' . $dayAlias . '.routecode,
                COALESCE(' . $routeAlias . '.routename, "") as routename,
                COALESCE(' . $routeAlias . '.arbroutename, "") as arbroutename,
                COALESCE(' . $dayAlias . '.salesmancode, 0) as salesmancode,
                DATE(' . $dayAlias . '.routestartdate) as routestartdate,
                DATE(' . $dayAlias . '.routeenddate) as routeenddate,
                ' . $this->numericColumnExpression('startendday', 'routeclosed', $dayAlias . '.routeclosed') . ' as routeclosed
            ')
            ->whereDate('day.routestartdate', $selectedDate);

        $scope->scopeQuery($user, $query, 'route', 'day.routecode');

        if ($search !== '') {
            $query->where(function ($searchQuery) use ($search) {
                $like = '%' . $search . '%';

                $searchQuery
                    ->where('day.routecode', 'like', $like)
                    ->orWhere('route.routename', 'like', $like)
                    ->orWhere('route.arbroutename', 'like', $like)
                    ->orWhere('day.salesmancode', 'like', $like);
            });
        }

        $documents = $query
            ->orderBy($this->sortColumn($sortBy), $sortDir)
            ->orderBy('day.routekey', 'desc')
            ->paginate($perPage)
            ->withQueryString()
            ->through(fn ($row) => [
                'routekey' => (int) $row->routekey,
                'routecode' => (int) ($row->routecode ?? 0),
                'routename' => $row->routename,
                'arbroutename' => $row->arbroutename,
                'salesmancode' => (int) ($row->salesmancode ?? 0),
                'routestartdate' => $this->formatDate($row->routestartdate),
                'routeenddate' => $this->formatDate($row->routeenddate),
                'routeclosed' => ((int) ($row->routeclosed ?? 0)) === 1 ? 'Yes' : 'No',
            ]);

        return Inertia::render('transaction/inventory-summary/Index', [
            'documents' => $documents,
            'filters' => [
                'date' => $selectedDate,
                'search' => $search,
                'per_page' => $perPage,
                'sort_by' => $sortBy,
                'sort_dir' => $sortDir,
            ],
        ]);
    }

    public function show(Request $request, int $routekey): Response
    {
        abort_unless($this->hasOverviewTables(), 404);
        $dayAlias = DB::getTablePrefix() . 'day';
        $routeAlias = DB::getTablePrefix() . 'route';

        $header = DB::table('startendday as day')
            ->leftJoin('routemaster as route', 'route.routecode', '=', 'day.routecode')
            ->where('day.routekey', $routekey)
            ->selectRaw('
                ' . $dayAlias . '.routekey,
                ' . $dayAlias . '.routecode,
                COALESCE(' . $routeAlias . '.routename, "") as routename,
                COALESCE(' . $routeAlias . '.arbroutename, "") as arbroutename,
                COALESCE(' . $dayAlias . '.salesmancode, 0) as salesmancode,
                DATE(' . $dayAlias . '.routestartdate) as routestartdate,
                DATE(' . $dayAlias . '.routeenddate) as routeenddate,
                ' . $this->numericColumnExpression('startendday', 'routeclosed', $dayAlias . '.routeclosed') . ' as routeclosed
            ')
            ->first();

        abort_unless($header, 404);
        abort_unless(app(AccessScopeService::class)->allows($request->user(), 'route', $header->routecode ?? null), 403);

        $lines = collect();
        if ($this->hasDetailTables()) {
            $useAlternateCode = $this->useAlternateCode();
            $detailAlias = DB::getTablePrefix() . 'detail';
            $itemAlias = DB::getTablePrefix() . 'item';

            $lines = DB::table('inventorysummarydetail as detail')
                ->join('itemmaster as item', 'item.actualitemcode', '=', 'detail.itemcode')
                ->where('detail.routekey', $routekey)
                ->selectRaw('
                    ' . $detailAlias . '.itemcode,
                    COALESCE(' . $itemAlias . '.alternatecode, "") as alternatecode,
                    ' . $this->itemDescriptionExpression($itemAlias) . ' as description,
                    ' . $this->unitsPerCaseExpression($itemAlias) . ' as upc,
                    ' . $this->numericColumnExpression('inventorysummarydetail', 'beginstockqty', $detailAlias . '.beginstockqty') . ' as beginstockqty,
                    ' . $this->numericColumnExpression('inventorysummarydetail', 'loadqty', $detailAlias . '.loadqty') . ' as loadqty,
                    ' . $this->numericColumnExpression('inventorysummarydetail', 'loadaddqty', $detailAlias . '.loadaddqty') . ' as loadaddqty,
                    ' . $this->numericColumnExpression('inventorysummarydetail', 'loadcutqty', $detailAlias . '.loadcutqty') . ' as loadcutqty,
                    ' . $this->numericColumnExpression('inventorysummarydetail', 'saleqty', $detailAlias . '.saleqty') . ' as saleqty,
                    ' . $this->numericColumnExpression('inventorysummarydetail', 'returnqty', $detailAlias . '.returnqty') . ' as returnqty,
                    ' . $this->numericColumnExpression('inventorysummarydetail', 'damageqty', $detailAlias . '.damageqty') . ' as damageqty,
                    ' . $this->numericColumnExpression('inventorysummarydetail', 'promoqty', $detailAlias . '.promoqty') . ' as promoqty,
                    ' . $this->numericColumnExpression('inventorysummarydetail', 'freesampleqty', $detailAlias . '.freesampleqty') . ' as freesampleqty,
                    ' . $this->numericColumnExpression('inventorysummarydetail', 'vanstock', $detailAlias . '.vanstock') . ' as vanstock,
                    ' . $this->numericColumnExpression('inventorysummarydetail', 'endstockqty', $detailAlias . '.endstockqty') . ' as endstockqty,
                    ' . $this->numericColumnExpression('inventorysummarydetail', 'unloadqty', $detailAlias . '.unloadqty') . ' as unloadqty,
                    ' . $this->numericColumnExpression('inventorysummarydetail', 'stdsalesprice', $detailAlias . '.stdsalesprice') . ' as stdsalesprice,
                    ' . $this->numericColumnExpression('inventorysummarydetail', 'stdsalescaseprice', $detailAlias . '.stdsalescaseprice') . ' as stdsalescaseprice
                ')
                ->orderBy('detail.itemcode')
                ->get()
                ->map(function ($row) use ($useAlternateCode) {
                    $upc = max(1, (int) ($row->upc ?? 1));
                    $casePrice = (float) ($row->stdsalescaseprice ?? 0);
                    $pcsPrice = (float) ($row->stdsalesprice ?? 0);
                    $closingQty = (int) (($row->endstockqty ?? 0) + ($row->unloadqty ?? 0));

                    return [
                        'itemcode' => (int) $row->itemcode,
                        'display_code' => $useAlternateCode && filled($row->alternatecode)
                            ? (string) $row->alternatecode
                            : (string) $row->itemcode,
                        'description' => $row->description ?? '',
                        'opening' => $this->quantityPair((int) ($row->beginstockqty ?? 0), $upc),
                        'load' => $this->quantityPair((int) ($row->loadqty ?? 0), $upc),
                        'transfer_in' => $this->quantityPair((int) ($row->loadaddqty ?? 0), $upc),
                        'transfer_out' => $this->quantityPair((int) ($row->loadcutqty ?? 0), $upc),
                        'sales' => $this->quantityPair((int) ($row->saleqty ?? 0), $upc),
                        'good_return' => $this->quantityPair((int) ($row->returnqty ?? 0), $upc),
                        'damaged' => $this->quantityPair((int) ($row->damageqty ?? 0), $upc),
                        'promo' => $this->quantityPair((int) ($row->promoqty ?? 0), $upc),
                        'free' => $this->quantityPair((int) ($row->freesampleqty ?? 0), $upc),
                        'closing' => $this->quantityPair($closingQty, $upc),
                        'opening_value' => $this->quantityValue((int) ($row->beginstockqty ?? 0), $upc, $casePrice, $pcsPrice),
                        'loaded_value' => $this->quantityValue((int) ($row->loadqty ?? 0), $upc, $casePrice, $pcsPrice),
                        'truck_stock_value' => $this->quantityValue((int) ($row->vanstock ?? 0), $upc, $casePrice, $pcsPrice),
                        'closing_value' => $this->quantityValue($closingQty, $upc, $casePrice, $pcsPrice),
                    ];
                })
                ->values();
        }

        return Inertia::render('transaction/inventory-summary/Show', [
            'header' => [
                'routekey' => (int) $header->routekey,
                'routecode' => (int) ($header->routecode ?? 0),
                'routename' => $header->routename,
                'arbroutename' => $header->arbroutename,
                'salesmancode' => (int) ($header->salesmancode ?? 0),
                'routestartdate' => $this->formatDate($header->routestartdate),
                'routeenddate' => $this->formatDate($header->routeenddate),
                'routeclosed' => ((int) ($header->routeclosed ?? 0)) === 1 ? 'Yes' : 'No',
            ],
            'lines' => $lines,
            'filters' => [
                'date' => (string) $request->input('date', ''),
                'search' => (string) $request->input('search', ''),
                'page' => max(1, (int) $request->input('page', 1)),
                'per_page' => max(10, (int) $request->input('per_page', 10)),
                'sort_by' => (string) $request->input('sort_by', 'routestartdate'),
                'sort_dir' => (string) $request->input('sort_dir', 'desc'),
            ],
        ]);
    }

    private function hasOverviewTables(): bool
    {
        return Schema::hasTable('startendday') && Schema::hasTable('routemaster');
    }

    private function hasDetailTables(): bool
    {
        return Schema::hasTable('inventorysummarydetail') && Schema::hasTable('itemmaster');
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
            'routecode' => 'day.routecode',
            'routename' => 'route.routename',
            'salesmancode' => 'day.salesmancode',
            'routeenddate' => 'day.routeenddate',
            'routeclosed' => 'day.routeclosed',
            default => 'day.routestartdate',
        };
    }

    private function numericColumnExpression(string $table, string $column, string $qualifiedColumn): string
    {
        return Schema::hasColumn($table, $column)
            ? 'COALESCE(' . $qualifiedColumn . ', 0)'
            : '0';
    }

    private function itemDescriptionExpression(string $alias): string
    {
        $descriptionColumns = [];

        if (Schema::hasColumn('itemmaster', 'itemshortdescription')) {
            $descriptionColumns[] = $alias . '.itemshortdescription';
        }

        if (Schema::hasColumn('itemmaster', 'itemdescription')) {
            $descriptionColumns[] = $alias . '.itemdescription';
        }

        if (empty($descriptionColumns)) {
            return '""';
        }

        return 'COALESCE(' . implode(', ', $descriptionColumns) . ', "")';
    }

    private function unitsPerCaseExpression(string $alias): string
    {
        return Schema::hasColumn('itemmaster', 'unitspercase')
            ? 'COALESCE(' . $alias . '.unitspercase, 1)'
            : '1';
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

    private function quantityPair(int $quantity, int $upc): array
    {
        return [
            'cases' => intdiv($quantity, $upc),
            'pieces' => $quantity % $upc,
        ];
    }

    private function quantityValue(int $quantity, int $upc, float $casePrice, float $pcsPrice): float
    {
        $cases = intdiv($quantity, $upc);
        $pieces = $quantity % $upc;

        return ($cases * $casePrice) + ($pieces * $pcsPrice);
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
}
