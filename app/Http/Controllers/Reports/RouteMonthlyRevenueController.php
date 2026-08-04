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

class RouteMonthlyRevenueController extends Controller
{
    private const MONTHS = [
        '01' => 'Jan',
        '02' => 'Feb',
        '03' => 'Mar',
        '04' => 'Apr',
        '05' => 'May',
        '06' => 'Jun',
        '07' => 'Jul',
        '08' => 'Aug',
        '09' => 'Sep',
        '10' => 'Oct',
        '11' => 'Nov',
        '12' => 'Dec',
    ];

    private const BASE_SORT_COLUMNS = [
        'route_label',
        'salesman_label',
        'total',
    ];

    public function __construct(
        private readonly ReportScopeService $reportScopeService
    ) {}

    public function __invoke(Request $request): Response
    {
        $context = $this->resolveContext($request, true);
        $rows = $this->loadAllRows($context['filters'], $context['sort_by'], $context['sort_dir']);
        $paginator = $this->paginateRows($rows, $context['filters']['per_page'], $context['page'], $request);
        $pageRows = collect($paginator->items());

        return Inertia::render('reports/data-analysis/RouteMonthlyRevenue', [
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
            'yearOptions' => $context['year_options'],
            'periodColumns' => $context['period_columns'],
            'rows' => $pageRows->values()->all(),
            'totals' => $this->totals($pageRows, $context['period_columns']),
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
        $headers = $this->exportHeaders($context['period_columns']);
        $exportRows = $rows
            ->map(fn (array $row) => $this->mapExportRow($row, $context['period_columns']))
            ->push($this->totalsExportRow($this->totals($rows, $context['period_columns']), $context['period_columns']))
            ->all();

        return ExcelXmlWorkbook::download(
            'route-monthly-revenue-' . now()->format('Ymd_His') . '.xls',
            $headers,
            $exportRows,
            'Route Monthly Revenue'
        );
    }

    public function exportPdf(Request $request): View
    {
        $context = $this->resolveContext($request, false);
        $rows = $this->loadAllRows($context['filters'], $context['sort_by'], $context['sort_dir']);

        return view('reports.route-monthly-revenue-pdf', [
            'rows' => $rows,
            'periodColumns' => $context['period_columns'],
            'totals' => $this->totals($rows, $context['period_columns']),
            'selectedFilters' => $this->selectedFilterLabels($context['filters'], $context['scope'], $context['year_options']),
            'amountPrecision' => AmountPrecision::get(),
        ]);
    }

    private function resolveContext(Request $request, bool $withPagination): array
    {
        $user = $request->user();
        abort_unless($user?->hasFormPermission('route monthly revenue'), 403);

        $rules = [
            'cmpycode' => ['nullable', 'integer'],
            'regionmstcode' => ['nullable', 'integer'],
            'depotcode' => ['nullable', 'integer'],
            'areacode' => ['nullable', 'integer'],
            'subareacode' => ['nullable', 'integer'],
            'routecode' => ['nullable', 'integer'],
            'year' => ['nullable', 'digits:4'],
            'sort_by' => ['nullable', 'string'],
            'sort_dir' => ['nullable', 'in:asc,desc'],
        ];

        if ($withPagination) {
            $rules['per_page'] = ['nullable', 'integer', 'min:10', 'max:100'];
            $rules['page'] = ['nullable', 'integer', 'min:1'];
        }

        $validated = $request->validate($rules);

        $filters = [
            'cmpycode' => $this->nullableInt($validated['cmpycode'] ?? null),
            'regionmstcode' => $this->nullableInt($validated['regionmstcode'] ?? null),
            'depotcode' => $this->nullableInt($validated['depotcode'] ?? null),
            'areacode' => $this->nullableInt($validated['areacode'] ?? null),
            'subareacode' => $this->nullableInt($validated['subareacode'] ?? null),
            'routecode' => $this->nullableInt($validated['routecode'] ?? null),
            'year' => $validated['year'] ?? null,
            'per_page' => $withPagination ? (int) ($validated['per_page'] ?? 25) : 100000,
        ];

        $scope = $this->reportScopeService->resolve($user, $filters);
        $filters = $this->normalizeFiltersAgainstScope($filters, $scope['rows']);
        $scope = $this->reportScopeService->resolve($user, $filters);
        $filters['routecodes'] = $scope['routecodes'];
        $filters['scope_limited'] = $scope['limited'];

        $yearOptions = $this->yearOptions($filters);
        if ($filters['year'] !== null && !collect($yearOptions)->contains(fn (array $option) => (string) $option['id'] === (string) $filters['year'])) {
            $filters['year'] = null;
            $yearOptions = $this->yearOptions($filters);
        }

        if ($filters['year'] === null) {
            $filters['year'] = $yearOptions[0]['id'] ?? null;
        }

        $periodColumns = $this->periodColumns();
        $requestedSortBy = $validated['sort_by'] ?? 'route_label';
        $sortColumns = array_merge(self::BASE_SORT_COLUMNS, array_map(fn (string $period) => 'period:' . $period, $periodColumns));

        return [
            'filters' => $filters,
            'scope' => $scope,
            'year_options' => $yearOptions,
            'period_columns' => $periodColumns,
            'sort_by' => in_array($requestedSortBy, $sortColumns, true) ? $requestedSortBy : 'route_label',
            'sort_dir' => $validated['sort_dir'] ?? 'asc',
            'page' => max((int) ($validated['page'] ?? 1), 1),
        ];
    }

    private function loadAllRows(array $filters, string $sortBy, string $sortDir): Collection
    {
        if (!$this->hasTables(['customerinvoice', 'routemaster', 'salesman'])) {
            return collect();
        }

        if (($filters['scope_limited'] ?? false) && $filters['routecodes'] === []) {
            return collect();
        }

        if (($filters['year'] ?? null) === null) {
            return collect();
        }

        $periodColumns = $this->periodColumns();
        $pivoted = [];

        foreach ($this->baseQuery($filters)->get() as $row) {
            $period = self::MONTHS[str_pad((string) ($row->period_month ?? ''), 2, '0', STR_PAD_LEFT)] ?? '';
            $routeLabel = $this->labelValue($row->routecode_display ?? $row->routecode, $row->routename ?? '', $row->arbroutename ?? '');
            $salesmanLabel = $this->labelValue($row->salesmancode_display ?? $row->salesmancode, $row->salesmanname1 ?? '', $row->arbsalesmanname1 ?? '');
            $rowKey = (string) ($row->routecode ?? '') . '|' . (string) ($row->salesmancode ?? '');

            if (!isset($pivoted[$rowKey])) {
                $periodValues = [];
                foreach ($periodColumns as $column) {
                    $periodValues[$column] = 0.0;
                }

                $pivoted[$rowKey] = [
                    'route_label' => $routeLabel,
                    'salesman_label' => $salesmanLabel,
                    'routecode_sort' => (string) ($row->routecode_display ?? $row->routecode ?? ''),
                    'salesmancode_sort' => (string) ($row->salesmancode_display ?? $row->salesmancode ?? ''),
                    'period_values' => $periodValues,
                    'total' => 0.0,
                ];
            }

            $amount = (float) ($row->revenue_amount ?? 0);
            if ($period !== '' && array_key_exists($period, $pivoted[$rowKey]['period_values'])) {
                $pivoted[$rowKey]['period_values'][$period] += $amount;
            }
            $pivoted[$rowKey]['total'] += $amount;
        }

        return collect(array_values($pivoted))
            ->map(function (array $row) {
                foreach ($row['period_values'] as $period => $amount) {
                    $row['period:' . $period] = $amount;
                }

                return $row;
            })
            ->sort(function (array $left, array $right) use ($sortBy, $sortDir) {
                $baseCompare = $this->compare($left[$sortBy] ?? null, $right[$sortBy] ?? null);
                if ($baseCompare !== 0) {
                    return $sortDir === 'desc' ? -$baseCompare : $baseCompare;
                }

                foreach (['routecode_sort', 'salesmancode_sort'] as $fallback) {
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
        $ci = $this->qualifiedAlias('ci');
        $rm = $this->qualifiedAlias('rm');
        $sm = $this->qualifiedAlias('sm');

        $routeCodeExpression = $this->hasColumn('routemaster', 'alternateroutecode')
            ? "COALESCE(NULLIF(TRIM({$rm}.alternateroutecode), ''), CAST({$rm}.routecode AS CHAR))"
            : "CAST({$rm}.routecode AS CHAR)";
        $salesmanCodeExpression = $this->hasColumn('salesman', 'alternatesalesmancode')
            ? "COALESCE(NULLIF(TRIM({$sm}.alternatesalesmancode), ''), CAST({$sm}.salesmancode AS CHAR))"
            : "CAST({$sm}.salesmancode AS CHAR)";
        $revenueExpression = $this->hasColumn('customerinvoice', 'totalinvoiceamount')
            ? "COALESCE({$ci}.totalinvoiceamount, 0)"
            : ($this->hasColumn('customerinvoice', 'invoicebalance')
                ? "COALESCE({$ci}.invoicebalance, 0)"
                : '0');

        return DB::table('customerinvoice as ci')
            ->join('routemaster as rm', 'rm.routecode', '=', 'ci.routecode')
            ->join('salesman as sm', 'sm.salesmancode', '=', 'ci.salesmancode')
            ->when(
                $this->hasColumn('customerinvoice', 'voidflag'),
                fn ($builder) => $builder->where('ci.voidflag', 0)
            )
            ->when(
                $this->hasColumn('customerinvoice', 'transactiontype'),
                fn ($builder) => $builder->where('ci.transactiontype', 1)
            )
            ->when(
                $filters['routecodes'] !== [],
                fn ($builder) => $builder->whereIn('ci.routecode', $filters['routecodes'])
            )
            ->when(
                !$filters['scope_limited'] && ($filters['routecodes'] ?? []) === [],
                fn ($builder) => $builder->where('ci.routecode', '>', 0)
            )
            ->when(
                $filters['year'] !== null,
                fn ($builder) => $builder->whereRaw("YEAR({$ci}.transactiondate) = ?", [$filters['year']])
            )
            ->selectRaw("
                DATE_FORMAT({$ci}.transactiondate, '%m') as period_month,
                {$ci}.routecode,
                {$routeCodeExpression} as routecode_display,
                COALESCE({$rm}.routename, '') as routename,
                COALESCE({$rm}.arbroutename, '') as arbroutename,
                {$ci}.salesmancode,
                {$salesmanCodeExpression} as salesmancode_display,
                COALESCE({$sm}.salesmanname1, '') as salesmanname1,
                COALESCE({$sm}.arbsalesmanname1, '') as arbsalesmanname1,
                SUM({$revenueExpression}) as revenue_amount
            ")
            ->groupByRaw("
                DATE_FORMAT({$ci}.transactiondate, '%m'),
                {$ci}.routecode,
                {$routeCodeExpression},
                {$rm}.routename,
                {$rm}.arbroutename,
                {$ci}.salesmancode,
                {$salesmanCodeExpression},
                {$sm}.salesmanname1,
                {$sm}.arbsalesmanname1
            ");
    }

    private function yearOptions(array $filters): array
    {
        if (!$this->hasTables(['customerinvoice'])) {
            return [];
        }

        $ci = $this->qualifiedAlias('ci');

        return DB::table('customerinvoice as ci')
            ->when(
                $this->hasColumn('customerinvoice', 'voidflag'),
                fn ($builder) => $builder->where('ci.voidflag', 0)
            )
            ->when(
                $this->hasColumn('customerinvoice', 'transactiontype'),
                fn ($builder) => $builder->where('ci.transactiontype', 1)
            )
            ->when(
                $filters['routecodes'] !== [],
                fn ($builder) => $builder->whereIn('ci.routecode', $filters['routecodes'])
            )
            ->when(
                !$filters['scope_limited'] && ($filters['routecodes'] ?? []) === [],
                fn ($builder) => $builder->where('ci.routecode', '>', 0)
            )
            ->selectRaw("DATE_FORMAT({$ci}.transactiondate, '%Y') as year")
            ->groupByRaw("DATE_FORMAT({$ci}.transactiondate, '%Y')")
            ->orderBy('year', 'desc')
            ->get()
            ->map(fn ($row) => ['id' => (string) $row->year, 'label' => (string) $row->year])
            ->values()
            ->all();
    }

    private function periodColumns(): array
    {
        return array_values(self::MONTHS);
    }

    private function totals(Collection $rows, array $periodColumns): array
    {
        $totals = ['total' => (float) $rows->sum('total')];
        foreach ($periodColumns as $period) {
            $totals['period:' . $period] = (float) $rows->sum('period:' . $period);
        }

        return $totals;
    }

    private function exportHeaders(array $periodColumns): array
    {
        return array_merge(['Route', 'Salesman'], $periodColumns, ['Total']);
    }

    private function mapExportRow(array $row, array $periodColumns): array
    {
        $export = [
            'Route' => $row['route_label'] ?? '',
            'Salesman' => $row['salesman_label'] ?? '',
        ];

        foreach ($periodColumns as $period) {
            $export[$period] = AmountPrecision::format($row['period:' . $period] ?? 0);
        }

        $export['Total'] = AmountPrecision::format($row['total'] ?? 0);

        return $export;
    }

    private function totalsExportRow(array $totals, array $periodColumns): array
    {
        $row = [
            'Route' => '',
            'Salesman' => 'Total',
        ];

        foreach ($periodColumns as $period) {
            $row[$period] = AmountPrecision::format($totals['period:' . $period] ?? 0);
        }

        $row['Total'] = AmountPrecision::format($totals['total'] ?? 0);

        return $row;
    }

    private function selectedFilterLabels(array $filters, array $scope, array $yearOptions): array
    {
        return [
            'Company' => $this->selectedOptionLabel($scope['options']['companies'], $filters['cmpycode']),
            'Region' => $this->selectedOptionLabel($scope['options']['regions'], $filters['regionmstcode']),
            'Branch / Depot' => $this->selectedOptionLabel($scope['options']['depots'], $filters['depotcode']),
            'Area' => $this->selectedOptionLabel($scope['options']['areas'], $filters['areacode']),
            'Sub Area' => $this->selectedOptionLabel($scope['options']['subAreas'], $filters['subareacode']),
            'Route' => $this->selectedOptionLabel($scope['options']['routes'], $filters['routecode']),
            'Year' => $this->selectedStringOptionLabel($yearOptions, $filters['year']),
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

    private function selectedStringOptionLabel(array $options, ?string $value): string
    {
        if ($value === null || $value === '') {
            return 'All';
        }

        $match = collect($options)->firstWhere('id', (string) $value);
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

    private function labelValue(mixed $code, string $name, string $arabicName): string
    {
        $isArabic = app()->getLocale() === 'ar';
        $label = trim($isArabic ? ($arabicName ?: $name) : ($name ?: $arabicName));
        $code = trim((string) $code);

        return $label === '' ? $code : trim($code . ' - ' . $label);
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

    private function hasColumn(string $table, string $column): bool
    {
        return Schema::hasTable($table) && Schema::hasColumn($table, $column);
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
