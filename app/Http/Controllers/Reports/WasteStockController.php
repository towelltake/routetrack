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

class WasteStockController extends Controller
{
    private const SORT_COLUMNS = [
        'route_label',
        'visit_date_sort',
        'visit_time_sort',
        'customer_code',
        'customer_name',
        'item_code',
        'item_description',
        'upc',
        'case_price',
        'unit_price',
        'location1_qty',
        'location2_qty',
        'location3_qty',
        'expiry_sort',
    ];

    private const EXPORT_COLUMNS = [
        'Route' => 'route_label',
        'Visit Date' => 'visit_date',
        'Visit Time' => 'visit_time',
        'Customer Code' => 'customer_code',
        'Customer Name' => 'customer_name',
        'Item Code' => 'item_code',
        'Item' => 'item_description',
        'UPC' => 'upc',
        'Case Price' => 'case_price',
        'Unit Price' => 'unit_price',
        'Location1 Qty' => 'location1_qty',
        'Location2 Qty' => 'location2_qty',
        'Location3 Qty' => 'location3_qty',
        'Expiry' => 'expiry',
    ];

    public function __construct(
        private readonly ReportScopeService $reportScopeService
    ) {}

    public function __invoke(Request $request): Response
    {
        $context = $this->resolveContext($request, true);
        $rows = $this->loadAllRows($context['filters'], $context['sort_by'], $context['sort_dir']);
        $paginator = $this->paginateRows($rows, $context['filters']['per_page'], $context['page'], $request);

        return Inertia::render('reports/merchandizing-report/WasteStock', [
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
            'customerOptions' => $context['customer_options'],
            'rows' => collect($paginator->items())->values()->all(),
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
        $exportRows = $rows->map(fn (array $row) => $this->mapExportRow($row))->all();

        return ExcelXmlWorkbook::download(
            'waste-stock-' . now()->format('Ymd_His') . '.xls',
            array_keys(self::EXPORT_COLUMNS),
            $exportRows,
            'Waste Stock'
        );
    }

    public function exportPdf(Request $request): View
    {
        $context = $this->resolveContext($request, false);
        $rows = $this->loadAllRows($context['filters'], $context['sort_by'], $context['sort_dir']);

        return view('reports.waste-stock-pdf', [
            'rows' => $rows,
            'selectedFilters' => $this->selectedFilterLabels($context['filters'], $context['scope'], $context['customer_options']),
            'amountPrecision' => AmountPrecision::get(),
        ]);
    }

    private function resolveContext(Request $request, bool $withPagination): array
    {
        $user = $request->user();
        abort_unless($user?->hasFormPermission('waste stock'), 403);

        $rules = [
            'transaction_date_from' => ['nullable', 'date'],
            'transaction_date_to' => ['nullable', 'date'],
            'cmpycode' => ['nullable', 'integer'],
            'regionmstcode' => ['nullable', 'integer'],
            'depotcode' => ['nullable', 'integer'],
            'areacode' => ['nullable', 'integer'],
            'subareacode' => ['nullable', 'integer'],
            'routecode' => ['nullable', 'integer'],
            'customercode' => ['nullable', 'integer'],
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
            'transaction_date_from' => $validated['transaction_date_from'] ?? $today,
            'transaction_date_to' => $validated['transaction_date_to'] ?? $today,
            'cmpycode' => $this->nullableInt($validated['cmpycode'] ?? null),
            'regionmstcode' => $this->nullableInt($validated['regionmstcode'] ?? null),
            'depotcode' => $this->nullableInt($validated['depotcode'] ?? null),
            'areacode' => $this->nullableInt($validated['areacode'] ?? null),
            'subareacode' => $this->nullableInt($validated['subareacode'] ?? null),
            'routecode' => $this->nullableInt($validated['routecode'] ?? null),
            'customercode' => $this->nullableInt($validated['customercode'] ?? null),
            'per_page' => $withPagination ? (int) ($validated['per_page'] ?? 25) : 100000,
        ];

        if (($filters['transaction_date_from'] ?? null) && ($filters['transaction_date_to'] ?? null)
            && $filters['transaction_date_from'] > $filters['transaction_date_to']) {
            [$filters['transaction_date_from'], $filters['transaction_date_to']] = [$filters['transaction_date_to'], $filters['transaction_date_from']];
        }

        $scope = $this->reportScopeService->resolve($user, $filters);
        $filters = $this->normalizeFiltersAgainstScope($filters, $scope['rows']);
        $scope = $this->reportScopeService->resolve($user, $filters);
        $filters['routecodes'] = $scope['routecodes'];
        $filters['scope_limited'] = $scope['limited'];

        $customerOptions = $this->customerOptions($filters);
        if ($filters['customercode'] !== null && !collect($customerOptions)->contains('id', $filters['customercode'])) {
            $filters['customercode'] = null;
        }

        $requestedSortBy = $validated['sort_by'] ?? 'visit_date_sort';

        return [
            'filters' => $filters,
            'scope' => $scope,
            'customer_options' => $customerOptions,
            'sort_by' => in_array($requestedSortBy, self::SORT_COLUMNS, true) ? $requestedSortBy : 'visit_date_sort',
            'sort_dir' => $validated['sort_dir'] ?? 'asc',
            'page' => max((int) ($validated['page'] ?? 1), 1),
        ];
    }

    private function loadAllRows(array $filters, string $sortBy, string $sortDir): Collection
    {
        if (!$this->hasRequiredTables()) {
            return collect();
        }

        if (($filters['scope_limited'] ?? false) && $filters['routecodes'] === []) {
            return collect();
        }

        return $this->baseQuery($filters, false)
            ->get()
            ->map(fn ($row) => $this->transformRow((array) $row))
            ->sort(function (array $left, array $right) use ($sortBy, $sortDir) {
                $baseCompare = $this->compare($left[$sortBy] ?? null, $right[$sortBy] ?? null);
                if ($baseCompare !== 0) {
                    return $sortDir === 'desc' ? -$baseCompare : $baseCompare;
                }

                foreach (['route_label', 'visit_date_sort', 'visit_time_sort', 'customer_code', 'item_code'] as $fallback) {
                    $fallbackCompare = $this->compare($left[$fallback] ?? null, $right[$fallback] ?? null);
                    if ($fallbackCompare !== 0) {
                        return $sortDir === 'desc' && in_array($fallback, ['visit_date_sort', 'visit_time_sort', 'expiry_sort'], true)
                            ? -$fallbackCompare
                            : $fallbackCompare;
                    }
                }

                return 0;
            })
            ->values();
    }

    private function baseQuery(array $filters, bool $forCustomerOptions)
    {
        $sourceAlias = $this->sourceAlias();
        $source = $this->qualifiedAlias($sourceAlias);
        $coc = $this->qualifiedAlias('coc');
        $cm = $this->qualifiedAlias('cm');
        $im = $this->qualifiedAlias('im');
        $rm = $this->qualifiedAlias('rm');

        $customerCodeSelect = Schema::hasColumn('customermaster', 'alternatecode')
            ? "REPLACE(COALESCE({$cm}.alternatecode, CAST({$cm}.customercode AS CHAR)), '-', '')"
            : "CAST({$cm}.customercode AS CHAR)";
        $routeCodeSelect = Schema::hasColumn('routemaster', 'alternateroutecode')
            ? "COALESCE(NULLIF(TRIM({$rm}.alternateroutecode), ''), CAST({$rm}.routecode AS CHAR))"
            : "CAST({$rm}.routecode AS CHAR)";
        $expirySelect = $this->usesInventoryCheck()
            ? "DATE_FORMAT({$source}.expiry_date, '%d %b %Y')"
            : "''";
        $expirySortSelect = $this->usesInventoryCheck()
            ? "DATE({$source}.expiry_date)"
            : 'NULL';

        $query = DB::table('customeroperationscontrol as coc')
            ->join($this->sourceTable() . ' as ' . $sourceAlias, function ($join) use ($sourceAlias) {
                $join->on('coc.routekey', '=', $sourceAlias . '.routekey')
                    ->on('coc.visitkey', '=', $sourceAlias . '.visitkey');
            })
            ->join('customermaster as cm', 'coc.customercode', '=', 'cm.customercode')
            ->join('itemmaster as im', 'im.actualitemcode', '=', $sourceAlias . '.itemcode')
            ->join('routemaster as rm', 'rm.routecode', '=', 'coc.routecode')
            ->when(
                $filters['transaction_date_from'],
                fn ($builder, $date) => $builder->whereDate('coc.visitstartdate', '>=', $date)
            )
            ->when(
                $filters['transaction_date_to'],
                fn ($builder, $date) => $builder->whereDate('coc.visitstartdate', '<=', $date)
            )
            ->when(
                $filters['routecodes'] !== [],
                fn ($builder) => $builder->whereIn('coc.routecode', $filters['routecodes'])
            )
            ->when(
                !$forCustomerOptions && $filters['customercode'] !== null,
                fn ($builder) => $builder->where('coc.customercode', $filters['customercode'])
            );

        if ($forCustomerOptions) {
            return $query->selectRaw("
                DISTINCT {$coc}.customercode as customercode_value,
                {$customerCodeSelect} as customercode_display,
                COALESCE({$cm}.customername, '') as customername,
                COALESCE({$cm}.arbcustomername, '') as arbcustomername
            ");
        }

        return $query->selectRaw("
            {$coc}.routekey,
            {$coc}.visitkey,
            {$coc}.routecode as routecode_value,
            {$routeCodeSelect} as routecode_display,
            COALESCE({$rm}.routename, '') as routename,
            COALESCE({$rm}.arbroutename, '') as arbroutename,
            {$coc}.customercode as customercode_value,
            {$customerCodeSelect} as customercode_display,
            COALESCE({$cm}.customername, '') as customername,
            COALESCE({$cm}.arbcustomername, '') as arbcustomername,
            DATE_FORMAT({$coc}.visitstartdate, '%d %b %Y') as visit_date,
            DATE({$coc}.visitstartdate) as visit_date_sort,
            COALESCE({$coc}.visitstarttime, DATE_FORMAT({$coc}.visitstartdate, '%H:%i:%s')) as visit_time,
            TIME(COALESCE({$coc}.visitstartdate, CURRENT_TIMESTAMP)) as visit_time_sort,
            CAST({$im}.actualitemcode AS CHAR) as item_code,
            COALESCE({$im}.itemdescription, '') as itemdescription,
            COALESCE({$im}.arbitemdescription, '') as arbitemdescription,
            COALESCE({$im}.unitspercase, 0) as upc,
            COALESCE({$im}.caseprice, 0) as case_price,
            COALESCE({$im}.defaultsalesprice, 0) as unit_price,
            COALESCE({$source}.qtyloc1each, 0) as location1_qty,
            COALESCE({$source}.qtyloc2each, 0) as location2_qty,
            COALESCE({$source}.qtyloc3each, 0) as location3_qty,
            {$expirySelect} as expiry,
            {$expirySortSelect} as expiry_sort
        ");
    }

    private function transformRow(array $row): array
    {
        $isArabic = app()->getLocale() === 'ar';
        $routeName = $isArabic
            ? ($row['arbroutename'] ?? $row['routename'] ?? '')
            : ($row['routename'] ?? $row['arbroutename'] ?? '');
        $customerName = $isArabic
            ? ($row['arbcustomername'] ?? $row['customername'] ?? '')
            : ($row['customername'] ?? $row['arbcustomername'] ?? '');
        $itemDescription = $isArabic
            ? ($row['arbitemdescription'] ?? $row['itemdescription'] ?? '')
            : ($row['itemdescription'] ?? $row['arbitemdescription'] ?? '');

        return [
            'routekey' => (int) ($row['routekey'] ?? 0),
            'visitkey' => (int) ($row['visitkey'] ?? 0),
            'route_label' => trim(($row['routecode_display'] ?? $row['routecode_value'] ?? '') . ' - ' . $routeName),
            'visit_date' => (string) ($row['visit_date'] ?? ''),
            'visit_date_sort' => (string) ($row['visit_date_sort'] ?? ''),
            'visit_time' => (string) ($row['visit_time'] ?? ''),
            'visit_time_sort' => (string) ($row['visit_time_sort'] ?? ''),
            'customer_code' => (string) ($row['customercode_display'] ?? $row['customercode_value'] ?? ''),
            'customer_name' => (string) $customerName,
            'item_code' => (string) ($row['item_code'] ?? ''),
            'item_description' => (string) $itemDescription,
            'upc' => (int) ($row['upc'] ?? 0),
            'case_price' => (float) ($row['case_price'] ?? 0),
            'unit_price' => (float) ($row['unit_price'] ?? 0),
            'location1_qty' => (float) ($row['location1_qty'] ?? 0),
            'location2_qty' => (float) ($row['location2_qty'] ?? 0),
            'location3_qty' => (float) ($row['location3_qty'] ?? 0),
            'expiry' => (string) ($row['expiry'] ?? ''),
            'expiry_sort' => (string) ($row['expiry_sort'] ?? ''),
        ];
    }

    private function customerOptions(array $filters): array
    {
        if (!$this->hasRequiredTables()) {
            return [];
        }

        if (($filters['scope_limited'] ?? false) && $filters['routecodes'] === []) {
            return [];
        }

        return $this->baseQuery($filters, true)
            ->orderBy('customercode_display')
            ->get()
            ->map(function ($row) {
                $isArabic = app()->getLocale() === 'ar';
                $name = $isArabic
                    ? ($row->arbcustomername ?: $row->customername)
                    : ($row->customername ?: $row->arbcustomername);

                return [
                    'id' => (int) ($row->customercode_value ?? 0),
                    'label' => trim((string) ($row->customercode_display ?? '') . ' - ' . trim((string) $name)),
                ];
            })
            ->filter(fn (array $option) => $option['id'] > 0)
            ->unique('id')
            ->values()
            ->all();
    }

    private function mapExportRow(array $row): array
    {
        $export = [];

        foreach (self::EXPORT_COLUMNS as $label => $key) {
            $value = $row[$key] ?? '';
            if (in_array($key, ['case_price', 'unit_price'], true)) {
                $value = AmountPrecision::format($value);
            }
            $export[$label] = $value;
        }

        return $export;
    }

    private function selectedFilterLabels(array $filters, array $scope, array $customerOptions): array
    {
        return [
            'Start Date' => $filters['transaction_date_from'] ?: 'All',
            'End Date' => $filters['transaction_date_to'] ?: 'All',
            'Company' => $this->selectedOptionLabel($scope['options']['companies'], $filters['cmpycode']),
            'Region' => $this->selectedOptionLabel($scope['options']['regions'], $filters['regionmstcode']),
            'Branch / Depot' => $this->selectedOptionLabel($scope['options']['depots'], $filters['depotcode']),
            'Area' => $this->selectedOptionLabel($scope['options']['areas'], $filters['areacode']),
            'Sub Area' => $this->selectedOptionLabel($scope['options']['subAreas'], $filters['subareacode']),
            'Route' => $this->selectedOptionLabel($scope['options']['routes'], $filters['routecode']),
            'Customer' => $this->selectedOptionLabel($customerOptions, $filters['customercode']),
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

    private function hasRequiredTables(): bool
    {
        if (!Schema::hasTable('customeroperationscontrol') || !Schema::hasTable('customermaster') || !Schema::hasTable('itemmaster') || !Schema::hasTable('routemaster')) {
            return false;
        }

        return Schema::hasTable('customerinventorycheck') || Schema::hasTable('customerinventorydetail');
    }

    private function usesInventoryCheck(): bool
    {
        return Schema::hasTable('customerinventorycheck');
    }

    private function sourceTable(): string
    {
        return $this->usesInventoryCheck() ? 'customerinventorycheck' : 'customerinventorydetail';
    }

    private function sourceAlias(): string
    {
        return $this->usesInventoryCheck() ? 'cic' : 'cid';
    }

    private function nullableInt(mixed $value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }

        return (int) $value;
    }

    private function compare(mixed $left, mixed $right): int
    {
        if (is_numeric($left) && is_numeric($right)) {
            return $left <=> $right;
        }

        return strcmp((string) $left, (string) $right);
    }

    private function qualifiedAlias(string $alias): string
    {
        return DB::getTablePrefix() . $alias;
    }
}
