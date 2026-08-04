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

class RouteInventoryController extends Controller
{
    private const SORT_COLUMNS = [
        'routecode',
        'majorcategorycode',
        'actualitemcode',
        'itemdescription',
        'damagevariancevalue',
        'truckstockvalue',
        'endstockvalue',
    ];

    private const EXPORT_COLUMNS = [
        'Route' => 'route_label',
        'Group' => 'group_label',
        'Item Code' => 'actualitemcode',
        'Item Description' => 'itemdescription',
        'Opening Case/Pcs' => 'beginstock',
        'Load Case/Pcs' => 'loadstock',
        'Tran.IN Case/Pcs' => 'loadaddstock',
        'Tran.OUT Case/Pcs' => 'loadcutstock',
        'Sales Case/Pcs' => 'salesstock',
        'Good Return Case/Pcs' => 'returnqstock',
        'Bad Return Case/Pcs' => 'damagedstock',
        'Free Case/Pcs' => 'freestock',
        'Damage Variance Qty./Pcs' => 'damagevariancestock',
        'Damage Variance Value' => 'damagevariancevalue',
        'Closing Case/Pcs' => 'endstock',
        'Load Value' => 'truckstockvalue',
        'Closing Value' => 'endstockvalue',
    ];

    public function __construct(
        private readonly ReportScopeService $reportScopeService
    ) {}

    public function __invoke(Request $request): Response
    {
        $context = $this->resolveContext($request, true);
        $paginator = $this->loadReport(
            $context['filters'],
            $context['sort_by'],
            $context['sort_dir'],
            $context['page']
        );

        $rows = collect($paginator->items())
            ->map(fn ($row) => $this->transformRow((array) $row))
            ->values();

        return Inertia::render('reports/daily-report/RouteInventory', [
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
            'rows' => $rows,
            'totals' => $this->totals($rows),
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
            'route-inventory-' . now()->format('Ymd_His') . '.xls',
            array_keys(self::EXPORT_COLUMNS),
            $exportRows,
            'Route Inventory'
        );
    }

    public function exportPdf(Request $request): View
    {
        $context = $this->resolveContext($request, false);
        $rows = $this->loadAllRows($context['filters'], $context['sort_by'], $context['sort_dir']);

        return view('reports.route-inventory-pdf', [
            'rows' => $rows,
            'totals' => $this->totals($rows),
            'selectedFilters' => $this->selectedFilterLabels($context['filters'], $context['scope']),
            'amountPrecision' => AmountPrecision::get(),
        ]);
    }

    private function resolveContext(Request $request, bool $withPagination): array
    {
        $user = $request->user();
        abort_unless($user?->hasFormPermission('route inventory'), 403);

        $rules = [
            'route_end_date' => ['nullable', 'date'],
            'cmpycode' => ['nullable', 'integer'],
            'regionmstcode' => ['nullable', 'integer'],
            'depotcode' => ['nullable', 'integer'],
            'areacode' => ['nullable', 'integer'],
            'subareacode' => ['nullable', 'integer'],
            'routecode' => ['nullable', 'integer'],
            'sort_by' => ['nullable', 'string'],
            'sort_dir' => ['nullable', 'in:asc,desc'],
        ];

        if ($withPagination) {
            $rules['per_page'] = ['nullable', 'integer', 'min:10', 'max:100'];
            $rules['page'] = ['nullable', 'integer', 'min:1'];
        }

        $validated = $request->validate($rules);

        $filters = [
            'route_end_date' => $validated['route_end_date'] ?? now()->toDateString(),
            'cmpycode' => $this->nullableInt($validated['cmpycode'] ?? null),
            'regionmstcode' => $this->nullableInt($validated['regionmstcode'] ?? null),
            'depotcode' => $this->nullableInt($validated['depotcode'] ?? null),
            'areacode' => $this->nullableInt($validated['areacode'] ?? null),
            'subareacode' => $this->nullableInt($validated['subareacode'] ?? null),
            'routecode' => $this->nullableInt($validated['routecode'] ?? null),
            'per_page' => $withPagination ? (int) ($validated['per_page'] ?? 25) : 100000,
        ];

        $scope = $this->reportScopeService->resolve($user, $filters);
        $filters = $this->normalizeFiltersAgainstScope($filters, $scope['rows']);
        $scope = $this->reportScopeService->resolve($user, $filters);
        $filters['routecodes'] = $scope['routecodes'];
        $filters['scope_limited'] = $scope['limited'];

        $requestedSortBy = $validated['sort_by'] ?? 'routecode';

        return [
            'filters' => $filters,
            'scope' => $scope,
            'sort_by' => in_array($requestedSortBy, self::SORT_COLUMNS, true) ? $requestedSortBy : 'routecode',
            'sort_dir' => $validated['sort_dir'] ?? 'asc',
            'page' => max((int) ($validated['page'] ?? 1), 1),
        ];
    }

    private function loadReport(array $filters, string $sortBy, string $sortDir, int $page): LengthAwarePaginator
    {
        $query = $this->baseQuery($filters)->orderBy($sortBy, $sortDir);

        return $query->paginate($filters['per_page'], ['*'], 'page', $page)->withQueryString();
    }

    private function loadAllRows(array $filters, string $sortBy, string $sortDir): Collection
    {
        return $this->baseQuery($filters)
            ->orderBy($sortBy, $sortDir)
            ->get()
            ->map(fn ($row) => $this->transformRow((array) $row))
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
                $filters['route_end_date'],
                fn ($builder, $date) => $builder->whereDate('sed.routeenddate', $date)
            )
            ->when(
                ($filters['scope_limited'] ?? false) && $filters['routecodes'] === [],
                fn ($builder) => $builder->whereRaw('1 = 0')
            )
            ->when(
                $filters['routecodes'] !== [],
                fn ($builder) => $builder->whereIn('sed.routecode', $filters['routecodes'])
            )
            ->selectRaw("
                {$sed}.routekey,
                {$sed}.routecode,
                {$rm}.routename,
                {$rm}.arbroutename,
                {$sed}.salesmancode,
                {$sm}.salesmanname1 as salesman,
                {$sm}.arbsalesmanname1,
                {$mc}.majorcategorycode,
                {$mc}.description as majorcategroy,
                {$mc}.arbdescription as arbmajorcatdes,
                {$im}.actualitemcode,
                {$im}.alternatecode as itemcode,
                {$im}.itemdescription,
                {$im}.arbitemdescription,
                COALESCE({$im}.unitspercase, 1) as upc,
                COALESCE({$isd}.beginstockqty, 0) as beginstockqty,
                COALESCE({$isd}.loadqty, 0) as loadqty,
                COALESCE({$isd}.loadaddqty, 0) as loadaddqty,
                COALESCE({$isd}.loadcutqty, 0) as loadcutqty,
                COALESCE({$isd}.saleqty, 0) as salesqty,
                COALESCE({$isd}.returnqty, 0) as returnqty,
                COALESCE({$isd}.damageqty, 0) as damagedqty,
                COALESCE({$isd}.freesampleqty, 0) as freeqty,
                (COALESCE({$isd}.endstockqty, 0) + COALESCE({$isd}.unloadqty, 0)) as endqty,
                COALESCE({$isd}.vanstockvalue, 0) as truckstockvalue,
                (COALESCE({$isd}.damageqty, 0) - COALESCE({$isd}.damagedunloadqty, 0)) as damagevarianceqty,
                COALESCE({$isd}.stdsalescaseprice, 0) as stdsalescaseprice,
                COALESCE({$isd}.stdsalesprice, 0) as stdsalesprice
            ");
    }

    private function transformRow(array $row): array
    {
        $isArabic = app()->getLocale() === 'ar';
        $routeName = $isArabic
            ? ($row['arbroutename'] ?? $row['routename'] ?? '')
            : ($row['routename'] ?? $row['arbroutename'] ?? '');
        $groupName = $isArabic
            ? ($row['arbmajorcatdes'] ?? $row['majorcategroy'] ?? '')
            : ($row['majorcategroy'] ?? $row['arbmajorcatdes'] ?? '');
        $itemDescription = $isArabic
            ? ($row['arbitemdescription'] ?? $row['itemdescription'] ?? '')
            : ($row['itemdescription'] ?? $row['arbitemdescription'] ?? '');

        $upc = max((int) ($row['upc'] ?? 1), 1);
        $endQty = (float) ($row['endqty'] ?? 0);
        $stdCase = (float) ($row['stdsalescaseprice'] ?? 0);
        $stdPiece = (float) ($row['stdsalesprice'] ?? 0);
        $damageVarianceQty = (float) ($row['damagevarianceqty'] ?? 0);

        return [
            'routekey' => (int) ($row['routekey'] ?? 0),
            'routecode' => (int) ($row['routecode'] ?? 0),
            'route_label' => trim(((int) ($row['routecode'] ?? 0)) . ' - ' . $routeName),
            'majorcategorycode' => (int) ($row['majorcategorycode'] ?? 0),
            'group_label' => trim(((int) ($row['majorcategorycode'] ?? 0)) . ' - ' . $groupName),
            'actualitemcode' => (string) ($row['actualitemcode'] ?? ''),
            'itemdescription' => $itemDescription,
            'beginstock' => $this->casePcs($row['beginstockqty'] ?? 0, $upc),
            'loadstock' => $this->casePcs($row['loadqty'] ?? 0, $upc),
            'loadaddstock' => $this->casePcs($row['loadaddqty'] ?? 0, $upc),
            'loadcutstock' => $this->casePcs($row['loadcutqty'] ?? 0, $upc),
            'salesstock' => $this->casePcs($row['salesqty'] ?? 0, $upc),
            'returnqstock' => $this->casePcs($row['returnqty'] ?? 0, $upc),
            'damagedstock' => $this->casePcs($row['damagedqty'] ?? 0, $upc),
            'freestock' => $this->casePcs($row['freeqty'] ?? 0, $upc),
            'damagevariancestock' => $this->casePcs($damageVarianceQty, $upc),
            'damagevariancevalue' => $this->stockValue($damageVarianceQty, $upc, $stdCase, $stdPiece),
            'endstock' => $this->casePcs($endQty, $upc),
            'truckstockvalue' => (float) ($row['truckstockvalue'] ?? 0),
            'endstockvalue' => $this->stockValue($endQty, $upc, $stdCase, $stdPiece),
        ];
    }

    private function totals(Collection $rows): array
    {
        return [
            'damagevariancevalue' => (float) $rows->sum('damagevariancevalue'),
            'truckstockvalue' => (float) $rows->sum('truckstockvalue'),
            'endstockvalue' => (float) $rows->sum('endstockvalue'),
        ];
    }

    private function mapExportRow(array $row): array
    {
        $export = [];

        foreach (self::EXPORT_COLUMNS as $label => $key) {
            $value = $row[$key] ?? '';
            if (in_array($key, ['damagevariancevalue', 'truckstockvalue', 'endstockvalue'], true)) {
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
        $row['Load Value'] = AmountPrecision::format($totals['truckstockvalue']);
        $row['Closing Value'] = AmountPrecision::format($totals['endstockvalue']);

        return $row;
    }

    private function selectedFilterLabels(array $filters, array $scope): array
    {
        return [
            'Route End Date' => $filters['route_end_date'] ?: 'All',
            'Company' => $this->selectedOptionLabel($scope['options']['companies'], $filters['cmpycode']),
            'Region' => $this->selectedOptionLabel($scope['options']['regions'], $filters['regionmstcode']),
            'Branch / Depot' => $this->selectedOptionLabel($scope['options']['depots'], $filters['depotcode']),
            'Area' => $this->selectedOptionLabel($scope['options']['areas'], $filters['areacode']),
            'Sub Area' => $this->selectedOptionLabel($scope['options']['subAreas'], $filters['subareacode']),
            'Route' => $this->selectedOptionLabel($scope['options']['routes'], $filters['routecode']),
        ];
    }

    private function selectedOptionLabel(array $options, ?int $value): string
    {
        if ($value === null) {
            return 'All';
        }

        $match = collect($options)->firstWhere('id', $value);
        return (string) ($match['label'] ?? $value);
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

    private function nullableInt(mixed $value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }

        return (int) $value;
    }

    private function qualifiedAlias(string $alias): string
    {
        return DB::getTablePrefix() . $alias;
    }
}
