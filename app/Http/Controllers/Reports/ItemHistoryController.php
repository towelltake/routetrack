<?php

namespace App\Http\Controllers\Reports;

use App\Http\Controllers\Controller;
use App\Services\Reports\ReportScopeService;
use App\Support\AmountPrecision;
use App\Support\ExcelXmlWorkbook;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\View\View;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\Response as HttpResponse;

class ItemHistoryController extends Controller
{
    private const SORT_COLUMNS = [
        'trip_start_sort',
        'trip_end_sort',
        'routecode',
        'majorcategorycode',
        'itemcode_sort',
        'itemdescription_sort',
        'damagevariancevalue',
        'openingvalue',
        'loadvalue',
        'truckstockvalue',
        'endstockvalue',
    ];

    private const EXPORT_COLUMNS = [
        'Trip Start Date - Trip End Date' => 'trip_label',
        'Route' => 'route_label',
        'Group' => 'group_label',
        'Item Code' => 'itemcode',
        'Item Description' => 'itemdescription',
        'Opening Case/Unit' => 'openingqty',
        'Load Case/Unit' => 'loadqty',
        'Transfer IN Case/Unit' => 'transferinqty',
        'Transfer OUT Case/Unit' => 'transferoutqty',
        'Sales Case/Unit' => 'saleqty',
        'Good Return Case/Unit' => 'retqty',
        'Bad Return Case/Unit' => 'dmgqty',
        'Free Case/Unit' => 'freeqty',
        'Damage Variance Case/Unit' => 'damagevariancestock',
        'Damage Variance Value' => 'damagevariancevalue',
        'Closing Case/Unit' => 'vanstockqty',
        'Opening Stock Value' => 'openingvalue',
        'Daily Loaded Value' => 'loadvalue',
        'Truck Stock Value' => 'truckstockvalue',
        'Closing Value' => 'endstockvalue',
    ];

    public function __construct(
        private readonly ReportScopeService $reportScopeService
    ) {}

    public function __invoke(Request $request): Response
    {
        $context = $this->resolveContext($request, true);
        $rows = $this->loadAllRows($context['filters'], $context['sort_by'], $context['sort_dir']);
        $paginator = $this->paginateRows($rows, $context['filters']['per_page'], $context['page'], $request);

        return Inertia::render('reports/transaction-report/ItemHistory', [
            'filters' => $context['filters'],
            'sort' => [
                'by' => $context['sort_by'],
                'dir' => $context['sort_dir'],
            ],
            'filterScopeRows' => $context['scope']['rows']->values()->all(),
            'companyOptions' => $context['scope']['options']['companies'],
            'regionOptions' => $context['scope']['options']['regions'],
            'depotOptions' => $context['scope']['options']['depots'],
            'areaOptions' => $context['scope']['options']['areas'],
            'subAreaOptions' => $context['scope']['options']['subAreas'],
            'routeOptions' => $context['scope']['options']['routes'],
            'itemOptions' => $this->itemOptions(),
            'majorCategoryOptions' => $this->majorCategoryOptions(),
            'rows' => collect($paginator->items())->values()->all(),
            'totals' => $this->totals(collect($paginator->items())),
            'pagination' => [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
                'from' => $paginator->firstItem(),
                'to' => $paginator->lastItem(),
            ],
        ]);
    }

    public function exportExcel(Request $request): HttpResponse
    {
        $context = $this->resolveContext($request, false);
        $rows = $this->loadAllRows($context['filters'], $context['sort_by'], $context['sort_dir']);
        $exportRows = $rows
            ->map(fn (array $row) => $this->mapExportRow($row))
            ->push($this->totalsExportRow($this->totals($rows)))
            ->all();

        return ExcelXmlWorkbook::download(
            'item-history-' . now()->format('Ymd_His') . '.xls',
            array_keys(self::EXPORT_COLUMNS),
            $exportRows,
            'Item History Summary'
        );
    }

    public function exportPdf(Request $request): View
    {
        $context = $this->resolveContext($request, false);
        $rows = $this->loadAllRows($context['filters'], $context['sort_by'], $context['sort_dir']);

        return view('reports.item-history-pdf', [
            'rows' => $rows,
            'totals' => $this->totals($rows),
            'selectedFilters' => $this->selectedFilterLabels($context['filters'], $context['scope']),
            'amountPrecision' => AmountPrecision::get(),
        ]);
    }

    private function resolveContext(Request $request, bool $withPagination): array
    {
        $user = $request->user();
        abort_unless($user?->hasFormPermission('item history'), 403);

        $rules = [
            'route_end_date_from' => ['nullable', 'date'],
            'route_end_date_to' => ['nullable', 'date'],
            'cmpycode' => ['nullable', 'integer'],
            'regionmstcode' => ['nullable', 'integer'],
            'depotcode' => ['nullable', 'integer'],
            'areacode' => ['nullable', 'integer'],
            'subareacode' => ['nullable', 'integer'],
            'routecode' => ['nullable', 'integer'],
            'itemcode' => ['nullable', 'string'],
            'majorcategorycode' => ['nullable', 'integer'],
            'sort_by' => ['nullable', 'string'],
            'sort_dir' => ['nullable', 'in:asc,desc'],
        ];

        if ($withPagination) {
            $rules['per_page'] = ['nullable', 'integer', 'min:10', 'max:100'];
            $rules['page'] = ['nullable', 'integer', 'min:1'];
        }

        $validated = $request->validate($rules);
        $today = now()->toDateString();

        $filters = [
            'route_end_date_from' => $validated['route_end_date_from'] ?? $today,
            'route_end_date_to' => $validated['route_end_date_to'] ?? $today,
            'cmpycode' => $this->nullableInt($validated['cmpycode'] ?? null),
            'regionmstcode' => $this->nullableInt($validated['regionmstcode'] ?? null),
            'depotcode' => $this->nullableInt($validated['depotcode'] ?? null),
            'areacode' => $this->nullableInt($validated['areacode'] ?? null),
            'subareacode' => $this->nullableInt($validated['subareacode'] ?? null),
            'routecode' => $this->nullableInt($validated['routecode'] ?? null),
            'itemcode' => $this->nullableString($validated['itemcode'] ?? null),
            'majorcategorycode' => $this->nullableInt($validated['majorcategorycode'] ?? null),
            'per_page' => $withPagination ? (int) ($validated['per_page'] ?? 25) : 100000,
        ];

        if (($filters['route_end_date_from'] ?? null) && ($filters['route_end_date_to'] ?? null)
            && $filters['route_end_date_from'] > $filters['route_end_date_to']) {
            [$filters['route_end_date_from'], $filters['route_end_date_to']] = [$filters['route_end_date_to'], $filters['route_end_date_from']];
        }

        $scope = $this->reportScopeService->resolve($user, $filters);
        $filters = $this->normalizeFiltersAgainstScope($filters, $scope['rows']);
        $scope = $this->reportScopeService->resolve($user, $filters);
        $filters['routecodes'] = $scope['routecodes'];
        $filters['scope_limited'] = $scope['limited'];

        $requestedSortBy = $validated['sort_by'] ?? 'trip_start_sort';

        return [
            'filters' => $filters,
            'scope' => $scope,
            'sort_by' => in_array($requestedSortBy, self::SORT_COLUMNS, true) ? $requestedSortBy : 'trip_start_sort',
            'sort_dir' => $validated['sort_dir'] ?? 'desc',
            'page' => max((int) ($validated['page'] ?? 1), 1),
        ];
    }

    private function loadAllRows(array $filters, string $sortBy, string $sortDir): Collection
    {
        if (!$this->hasTables([
            'startendday',
            'inventorysummarydetail',
            'itemmaster',
            'itemgroup',
            'submajorcategory',
            'majorcategory',
            'routemaster',
            'salesman',
        ])) {
            return collect();
        }

        if (($filters['scope_limited'] ?? false) && $filters['routecodes'] === []) {
            return collect();
        }

        return $this->baseQuery($filters)
            ->get()
            ->map(fn ($row) => $this->transformRow((array) $row))
            ->sort(function (array $left, array $right) use ($sortBy, $sortDir) {
                $baseCompare = $this->compare($left[$sortBy] ?? null, $right[$sortBy] ?? null);
                if ($baseCompare !== 0) {
                    return $sortDir === 'desc' ? -$baseCompare : $baseCompare;
                }

                foreach (['trip_start_sort', 'routecode', 'majorcategorycode', 'itemcode_sort'] as $fallback) {
                    $fallbackCompare = $this->compare($left[$fallback] ?? null, $right[$fallback] ?? null);
                    if ($fallbackCompare !== 0) {
                        return $fallbackCompare;
                    }
                }

                return 0;
            })
            ->values();
    }

    private function baseQuery(array $filters)
    {
        $sed = $this->qualifiedAlias('sed');
        $rm = $this->qualifiedAlias('rm');
        $sm = $this->qualifiedAlias('sm');
        $isd = $this->qualifiedAlias('isd');
        $im = $this->qualifiedAlias('im');
        $mc = $this->qualifiedAlias('mc');

        return DB::table('startendday as sed')
            ->join('routemaster as rm', 'rm.routecode', '=', 'sed.routecode')
            ->join('salesman as sm', 'sm.salesmancode', '=', 'sed.salesmancode')
            ->join('inventorysummarydetail as isd', 'isd.routekey', '=', 'sed.routekey')
            ->join('itemmaster as im', 'im.actualitemcode', '=', 'isd.itemcode')
            ->join('itemgroup as ig', 'ig.itemgroupcode', '=', 'im.itemgroupcode')
            ->join('submajorcategory as smc', 'smc.submajorcategorycode', '=', 'ig.submajorcategorycode')
            ->join('majorcategory as mc', 'mc.majorcategorycode', '=', 'smc.majorcategorycode')
            ->when(
                $filters['route_end_date_from'],
                fn ($builder, $date) => $builder->whereDate('sed.routeenddate', '>=', $date)
            )
            ->when(
                $filters['route_end_date_to'],
                fn ($builder, $date) => $builder->whereDate('sed.routeenddate', '<=', $date)
            )
            ->when(
                ($filters['scope_limited'] ?? false) && $filters['routecodes'] === [],
                fn ($builder) => $builder->whereRaw('1 = 0')
            )
            ->when(
                $filters['routecodes'] !== [],
                fn ($builder) => $builder->whereIn('sed.routecode', $filters['routecodes'])
            )
            ->when(
                $filters['majorcategorycode'],
                fn ($builder, $majorCategoryCode) => $builder->where('mc.majorcategorycode', $majorCategoryCode)
            )
            ->when(
                $filters['itemcode'],
                fn ($builder, $itemCode) => $builder->where('im.actualitemcode', $itemCode)
            )
            ->selectRaw("
                {$sed}.routekey,
                DATE_FORMAT({$sed}.routestartdate, '%d %b %Y') as trip_start_date,
                DATE_FORMAT({$sed}.routeenddate, '%d %b %Y') as trip_end_date,
                DATE_FORMAT({$sed}.routestartdate, '%Y-%m-%d') as trip_start_sort,
                DATE_FORMAT({$sed}.routeenddate, '%Y-%m-%d') as trip_end_sort,
                {$sed}.routecode,
                {$rm}.routename,
                {$rm}.arbroutename,
                {$sed}.salesmancode,
                {$sm}.salesmanname1 as salesman,
                {$sm}.arbsalesmanname1,
                {$mc}.majorcategorycode,
                {$mc}.description as majorcategory,
                {$mc}.arbdescription as arbmajorcategory,
                {$im}.actualitemcode,
                {$im}.alternatecode as itemcode,
                {$im}.itemdescription,
                {$im}.arbitemdescription,
                GREATEST(COALESCE({$im}.unitspercase, 1), 1) as upc,
                COALESCE({$isd}.beginstockqty, 0) as beginstockqty,
                COALESCE({$isd}.loadqty, 0) as loadqty_raw,
                COALESCE({$isd}.loadaddqty, 0) as transferin_raw,
                COALESCE({$isd}.loadcutqty, 0) as transferout_raw,
                COALESCE({$isd}.saleqty, 0) as salesqty,
                COALESCE({$isd}.returnqty, 0) as returnqty,
                COALESCE({$isd}.damageqty, 0) as damagedqty,
                COALESCE({$isd}.freesampleqty, 0) as freeqty_raw,
                (COALESCE({$isd}.endstockqty, 0) + COALESCE({$isd}.unloadqty, 0)) as vanstock_raw,
                (COALESCE({$isd}.damageqty, 0) - COALESCE({$isd}.damagedunloadqty, 0)) as damagevarianceqty,
                COALESCE({$isd}.stdsalescaseprice, 0) as stdsalescaseprice,
                COALESCE({$isd}.stdsalesprice, 0) as stdsalesprice,
                COALESCE({$isd}.vanstockvalue, 0) as truckstockvalue
            ");
    }

    private function transformRow(array $row): array
    {
        $isArabic = app()->getLocale() === 'ar';
        $routeName = $isArabic
            ? ($row['arbroutename'] ?? $row['routename'] ?? '')
            : ($row['routename'] ?? $row['arbroutename'] ?? '');
        $groupName = $isArabic
            ? ($row['arbmajorcategory'] ?? $row['majorcategory'] ?? '')
            : ($row['majorcategory'] ?? $row['arbmajorcategory'] ?? '');
        $itemDescription = $isArabic
            ? ($row['arbitemdescription'] ?? $row['itemdescription'] ?? '')
            : ($row['itemdescription'] ?? $row['arbitemdescription'] ?? '');

        $upc = max((int) ($row['upc'] ?? 1), 1);
        $beginstockqty = (float) ($row['beginstockqty'] ?? 0);
        $loadQty = (float) ($row['loadqty_raw'] ?? 0);
        $transferInQty = (float) ($row['transferin_raw'] ?? 0);
        $transferOutQty = (float) ($row['transferout_raw'] ?? 0);
        $salesQty = (float) ($row['salesqty'] ?? 0);
        $returnQty = (float) ($row['returnqty'] ?? 0);
        $damagedQty = (float) ($row['damagedqty'] ?? 0);
        $freeQty = (float) ($row['freeqty_raw'] ?? 0);
        $vanstockQty = (float) ($row['vanstock_raw'] ?? 0);
        $damageVarianceQty = (float) ($row['damagevarianceqty'] ?? 0);
        $stdCase = (float) ($row['stdsalescaseprice'] ?? 0);
        $stdPiece = (float) ($row['stdsalesprice'] ?? 0);

        return [
            'trip_label' => trim(($row['trip_start_date'] ?? '') . ' - ' . ($row['trip_end_date'] ?? '')),
            'trip_start_date' => (string) ($row['trip_start_date'] ?? ''),
            'trip_end_date' => (string) ($row['trip_end_date'] ?? ''),
            'trip_start_sort' => (string) ($row['trip_start_sort'] ?? ''),
            'trip_end_sort' => (string) ($row['trip_end_sort'] ?? ''),
            'routekey' => (int) ($row['routekey'] ?? 0),
            'routecode' => (int) ($row['routecode'] ?? 0),
            'route_label' => trim(((int) ($row['routecode'] ?? 0)) . ' - ' . $routeName),
            'majorcategorycode' => (int) ($row['majorcategorycode'] ?? 0),
            'group_label' => trim(((int) ($row['majorcategorycode'] ?? 0)) . ' - ' . $groupName),
            'itemcode' => $this->identifier($row['itemcode'] ?? ''),
            'itemcode_sort' => (string) ($row['itemcode'] ?? ''),
            'actualitemcode' => (string) ($row['actualitemcode'] ?? ''),
            'itemdescription' => $itemDescription,
            'itemdescription_sort' => mb_strtolower($itemDescription),
            'openingqty' => $this->casePcs($beginstockqty, $upc),
            'loadqty' => $this->casePcs($loadQty, $upc),
            'transferinqty' => $this->casePcs($transferInQty, $upc),
            'transferoutqty' => $this->casePcs($transferOutQty, $upc),
            'saleqty' => $this->casePcs($salesQty, $upc),
            'retqty' => $this->casePcs($returnQty, $upc),
            'dmgqty' => $this->casePcs($damagedQty, $upc),
            'freeqty' => $this->casePcs($freeQty, $upc),
            'damagevariancestock' => $this->casePcs($damageVarianceQty, $upc),
            'damagevariancevalue' => $this->stockValue($damageVarianceQty, $upc, $stdCase, $stdPiece),
            'vanstockqty' => $this->casePcs($vanstockQty, $upc),
            'openingvalue' => $this->stockValue($beginstockqty, $upc, $stdCase, $stdPiece),
            'loadvalue' => $this->stockValue($loadQty, $upc, $stdCase, $stdPiece),
            'truckstockvalue' => (float) ($row['truckstockvalue'] ?? 0),
            'endstockvalue' => $this->stockValue($vanstockQty, $upc, $stdCase, $stdPiece),
        ];
    }

    private function totals(Collection $rows): array
    {
        return [
            'damagevariancevalue' => (float) $rows->sum('damagevariancevalue'),
            'openingvalue' => (float) $rows->sum('openingvalue'),
            'loadvalue' => (float) $rows->sum('loadvalue'),
            'truckstockvalue' => (float) $rows->sum('truckstockvalue'),
            'endstockvalue' => (float) $rows->sum('endstockvalue'),
        ];
    }

    private function mapExportRow(array $row): array
    {
        $export = [];

        foreach (self::EXPORT_COLUMNS as $label => $key) {
            $value = $row[$key] ?? '';
            if (in_array($key, ['damagevariancevalue', 'openingvalue', 'loadvalue', 'truckstockvalue', 'endstockvalue'], true)) {
                $value = AmountPrecision::format($value);
            }
            $export[$label] = $value;
        }

        return $export;
    }

    private function totalsExportRow(array $totals): array
    {
        $row = array_fill_keys(array_keys(self::EXPORT_COLUMNS), '');
        $row['Item Description'] = 'Total';
        $row['Damage Variance Value'] = AmountPrecision::format($totals['damagevariancevalue']);
        $row['Opening Stock Value'] = AmountPrecision::format($totals['openingvalue']);
        $row['Daily Loaded Value'] = AmountPrecision::format($totals['loadvalue']);
        $row['Truck Stock Value'] = AmountPrecision::format($totals['truckstockvalue']);
        $row['Closing Value'] = AmountPrecision::format($totals['endstockvalue']);

        return $row;
    }

    private function selectedFilterLabels(array $filters, array $scope): array
    {
        return [
            'Start Date' => $filters['route_end_date_from'] ?: 'All',
            'End Date' => $filters['route_end_date_to'] ?: 'All',
            'Company' => $this->selectedOptionLabel($scope['options']['companies'], $filters['cmpycode']),
            'Region' => $this->selectedOptionLabel($scope['options']['regions'], $filters['regionmstcode']),
            'Branch / Depot' => $this->selectedOptionLabel($scope['options']['depots'], $filters['depotcode']),
            'Area' => $this->selectedOptionLabel($scope['options']['areas'], $filters['areacode']),
            'Sub Area' => $this->selectedOptionLabel($scope['options']['subAreas'], $filters['subareacode']),
            'Route' => $this->selectedOptionLabel($scope['options']['routes'], $filters['routecode']),
            'Items' => $this->selectedOptionLabel($this->itemOptions(), $filters['itemcode'], true),
            'Major Category' => $this->selectedOptionLabel($this->majorCategoryOptions(), $filters['majorcategorycode']),
        ];
    }

    private function selectedOptionLabel(array $options, mixed $value, bool $stringMatch = false): string
    {
        if ($value === null || $value === '') {
            return 'All';
        }

        $match = collect($options)->first(function (array $option) use ($value, $stringMatch) {
            return $stringMatch
                ? (string) ($option['id'] ?? '') === (string) $value
                : (int) ($option['id'] ?? 0) === (int) $value;
        });

        return (string) ($match['label'] ?? $value);
    }

    private function itemOptions(): array
    {
        if (!$this->hasTables(['itemmaster'])) {
            return [];
        }

        return DB::table('itemmaster')
            ->selectRaw("CAST(actualitemcode AS CHAR) as id, CONCAT(COALESCE(alternatecode, actualitemcode), ' - ', COALESCE(itemdescription, '')) as label")
            ->orderBy('alternatecode')
            ->limit(5000)
            ->get()
            ->map(fn ($row) => ['id' => (string) $row->id, 'label' => (string) $row->label])
            ->all();
    }

    private function majorCategoryOptions(): array
    {
        if (!$this->hasTables(['majorcategory'])) {
            return [];
        }

        return DB::table('majorcategory')
            ->selectRaw('majorcategorycode as id, CONCAT(majorcategorycode, \' - \', COALESCE(description, \'\')) as label')
            ->orderBy('majorcategorycode')
            ->get()
            ->map(fn ($row) => ['id' => (int) $row->id, 'label' => (string) $row->label])
            ->all();
    }

    private function normalizeFiltersAgainstScope(array $filters, Collection $scopeRows): array
    {
        foreach (['cmpycode', 'regionmstcode', 'depotcode', 'areacode', 'subareacode', 'routecode'] as $key) {
            $value = $filters[$key] ?? null;
            if ($value !== null && !$scopeRows->contains($key, (int) $value)) {
                $filters[$key] = null;
            }
        }

        return $filters;
    }

    private function paginateRows(Collection $rows, int $perPage, int $page, Request $request): LengthAwarePaginator
    {
        $total = $rows->count();
        $items = $rows->forPage($page, $perPage)->values();

        return new LengthAwarePaginator(
            $items,
            $total,
            $perPage,
            $page,
            [
                'path' => $request->url(),
                'query' => $request->query(),
            ]
        );
    }

    private function hasTables(array $tables): bool
    {
        foreach ($tables as $table) {
            if (!Schema::hasTable($table)) {
                return false;
            }
        }

        return true;
    }

    private function compare(mixed $left, mixed $right): int
    {
        if (is_numeric($left) && is_numeric($right)) {
            return (float) $left <=> (float) $right;
        }

        return strcasecmp((string) $left, (string) $right);
    }

    private function casePcs(float|int|string $qty, int $upc): string
    {
        $value = (int) round((float) $qty);
        $cases = intdiv($value, $upc);
        $pcs = $value % $upc;

        return $cases . '/' . $pcs;
    }

    private function stockValue(float $qty, int $upc, float $casePrice, float $piecePrice): float
    {
        $qtyInt = (int) round($qty);
        $cases = intdiv($qtyInt, $upc);
        $pcs = $qtyInt % $upc;

        return ($cases * $casePrice) + ($pcs * $piecePrice);
    }

    private function identifier(mixed $value): string
    {
        if ($value === null || $value === '' || $value === '0') {
            return '';
        }

        return (string) $value;
    }

    private function nullableInt(mixed $value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }

        return (int) $value;
    }

    private function nullableString(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        return (string) $value;
    }

    private function qualifiedAlias(string $alias): string
    {
        return DB::getTablePrefix() . $alias;
    }
}
