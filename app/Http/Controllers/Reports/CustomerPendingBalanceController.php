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

class CustomerPendingBalanceController extends Controller
{
    private const CUSTOMER_TYPE_OPTIONS = [
        ['id' => 1, 'label' => '1 - Normal'],
        ['id' => 2, 'label' => '2 - Branch'],
        ['id' => 3, 'label' => '3 - Head Office'],
    ];

    private const BASE_SORT_COLUMNS = [
        'hocode_label',
        'customer_label',
        'transactiondate_sort',
        'salesmancode_sort',
        'invoicenumber_sort',
        'comments',
        'total',
    ];

    public function __construct(
        private readonly ReportScopeService $reportScopeService
    ) {}

    public function __invoke(Request $request): Response
    {
        $context = $this->resolveContext($request, true);
        $pageData = $this->loadPageRows(
            $context['filters'],
            $context['sort_by'],
            $context['sort_dir'],
            $context['period_columns'],
            $context['filters']['per_page'],
            $context['page']
        );
        $pageRows = collect($pageData['items']);

        return Inertia::render('reports/accounts-report/CustomerPendingBalance', [
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
            'customerTypeOptions' => self::CUSTOMER_TYPE_OPTIONS,
            'customerOptions' => $context['customer_options'],
            'periodColumns' => $context['period_columns'],
            'rows' => $pageRows->values()->all(),
            'totals' => $this->totals($pageRows, $context['period_columns']),
            'pagination' => $pageData['pagination'],
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
            'customer-pending-balance-' . now()->format('Ymd_His') . '.xls',
            $headers,
            $exportRows,
            'Customer Pending Balance'
        );
    }

    public function exportPdf(Request $request): View
    {
        $context = $this->resolveContext($request, false);
        $rows = $this->loadAllRows($context['filters'], $context['sort_by'], $context['sort_dir']);

        return view('reports.customer-pending-balance-pdf', [
            'rows' => $rows,
            'periodColumns' => $context['period_columns'],
            'totals' => $this->totals($rows, $context['period_columns']),
            'selectedFilters' => $this->selectedFilterLabels($context['filters'], $context['scope'], $context['year_options'], $context['customer_options']),
            'amountPrecision' => AmountPrecision::get(),
        ]);
    }

    private function resolveContext(Request $request, bool $withPagination): array
    {
        $user = $request->user();
        abort_unless($user?->hasFormPermission('customer pending balance'), 403);

        $rules = [
            'cmpycode' => ['nullable', 'integer'],
            'regionmstcode' => ['nullable', 'integer'],
            'depotcode' => ['nullable', 'integer'],
            'areacode' => ['nullable', 'integer'],
            'subareacode' => ['nullable', 'integer'],
            'routecode' => ['nullable', 'integer'],
            'year' => ['nullable', 'digits:4'],
            'customer_type' => ['nullable', 'integer', 'in:1,2,3'],
            'customer_code' => ['nullable', 'string'],
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
            'customer_type' => $this->nullableInt($validated['customer_type'] ?? null),
            'customer_code' => $this->normalizeIdentifier($validated['customer_code'] ?? null),
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

        $customerOptions = $this->customerOptions($filters);
        if ($filters['customer_code'] !== null && !collect($customerOptions)->contains(fn (array $option) => (string) $option['id'] === $filters['customer_code'])) {
            $filters['customer_code'] = null;
            $customerOptions = $this->customerOptions($filters);
        }

        $periodColumns = $this->periodColumns($filters);
        $requestedSortBy = $validated['sort_by'] ?? 'hocode_label';
        $sortColumns = array_merge(self::BASE_SORT_COLUMNS, array_map(fn (string $period) => 'period:' . $period, $periodColumns));

        return [
            'filters' => $filters,
            'scope' => $scope,
            'year_options' => $yearOptions,
            'customer_options' => $customerOptions,
            'period_columns' => $periodColumns,
            'sort_by' => in_array($requestedSortBy, $sortColumns, true) ? $requestedSortBy : 'hocode_label',
            'sort_dir' => $validated['sort_dir'] ?? 'asc',
            'page' => max((int) ($validated['page'] ?? 1), 1),
        ];
    }

    private function loadAllRows(array $filters, string $sortBy, string $sortDir): Collection
    {
        return collect($this->pivotRows($filters, $sortBy, $sortDir));
    }

    private function loadPageRows(
        array $filters,
        string $sortBy,
        string $sortDir,
        array $periodColumns,
        int $perPage,
        int $page
    ): array {
        $pivoted = $this->pivotRowsRaw($filters, $sortBy, $sortDir);
        $currentPage = max($page, 1);
        $total = count($pivoted);
        $offset = ($currentPage - 1) * $perPage;
        $pageItems = array_slice($pivoted, $offset, $perPage, true);
        $items = array_values($pageItems);

        return [
            'items' => $items,
            'pagination' => [
                'current_page' => $currentPage,
                'last_page' => max((int) ceil($total / max($perPage, 1)), 1),
                'per_page' => $perPage,
                'total' => $total,
                'from' => $total > 0 ? $offset + 1 : null,
                'to' => $total > 0 ? min($offset + count($items), $total) : null,
            ],
        ];
    }

    private function pivotRows(array $filters, string $sortBy, string $sortDir): array
    {
        return array_values($this->pivotRowsRaw($filters, $sortBy, $sortDir));
    }

    private function pivotRowsRaw(array $filters, string $sortBy, string $sortDir): array
    {
        if (!$this->hasTables(['customerinvoice', 'customermaster', 'salesman'])) {
            return [];
        }

        if (($filters['scope_limited'] ?? false) && $filters['routecodes'] === []) {
            return [];
        }

        if ($this->periodColumns($filters) === []) {
            return [];
        }

        $pivoted = [];
        foreach ($this->baseQuery($filters)->get() as $row) {
            $periodKey = $this->periodKey((int) ($row->period_year ?? 0), (int) ($row->period_month ?? 0));
            $customerLabel = $this->labelValue($row->customercode_display ?? $row->customercode_value, $row->customername ?? '', $row->arbcustomername ?? '');
            $headOfficeLabel = $this->headOfficeLabel($row);
            $salesmanCode = (string) ($row->salesmancode_display ?? $row->salesmancode_value ?? '');
            $salesmanSort = (string) ($row->salesmancode_sort ?? $salesmanCode);
            $invoiceNumber = $this->identifier($row->invoicenumber ?? '');
            $comments = trim((string) ($row->comments ?? ''));
            $rowKey = implode('|', [
                $headOfficeLabel,
                $customerLabel,
                (string) ($row->transactiondate_sort ?? ''),
                $salesmanSort,
                $invoiceNumber,
                $comments,
            ]);

            if (!isset($pivoted[$rowKey])) {
                $pivoted[$rowKey] = [
                    'hocode_label' => $headOfficeLabel,
                    'customer_label' => $customerLabel,
                    'transactiondate' => (string) ($row->transactiondate ?? ''),
                    'transactiondate_sort' => (string) ($row->transactiondate_sort ?? ''),
                    'salesmancode_display' => $salesmanCode,
                    'salesmancode_sort' => $salesmanSort,
                    'invoicenumber' => $invoiceNumber,
                    'invoicenumber_sort' => (string) ($row->invoicenumber_sort ?? $invoiceNumber),
                    'comments' => $comments,
                    'total' => 0.0,
                ];
            }

            $amount = (float) ($row->invoicebalance ?? 0);
            $periodColumn = 'period:' . $periodKey;
            $pivoted[$rowKey][$periodColumn] = ($pivoted[$rowKey][$periodColumn] ?? 0.0) + $amount;
            $pivoted[$rowKey]['total'] += $amount;
        }

        uasort($pivoted, function (array $left, array $right) use ($sortBy, $sortDir) {
            $baseCompare = $this->compare($left[$sortBy] ?? null, $right[$sortBy] ?? null);
            if ($baseCompare !== 0) {
                return $sortDir === 'desc' ? -$baseCompare : $baseCompare;
            }

            foreach (['hocode_label', 'customer_label', 'transactiondate_sort', 'salesmancode_sort', 'invoicenumber_sort'] as $fallback) {
                $fallbackCompare = $this->compare($left[$fallback] ?? null, $right[$fallback] ?? null);
                if ($fallbackCompare !== 0) {
                    return $fallbackCompare;
                }
            }

            return 0;
        });

        return $pivoted;
    }

    private function baseQuery(array $filters)
    {
        $ci = $this->qualifiedAlias('ci');
        $cm = $this->qualifiedAlias('cm');
        $sm = $this->qualifiedAlias('sm');
        $ho = $this->qualifiedAlias('ho');

        $customerCodeExpression = $this->hasColumn('customermaster', 'reportcustcode')
            ? "NULLIF(TRIM(CAST({$cm}.reportcustcode AS CHAR)), '')"
            : ($this->hasColumn('customermaster', 'alternatecode')
                ? "NULLIF(TRIM({$cm}.alternatecode), '')"
                : "CAST({$cm}.customercode AS CHAR)");
        $salesmanCodeExpression = $this->hasColumn('salesman', 'alternatesalesmancode')
            ? "COALESCE(NULLIF(TRIM({$sm}.alternatesalesmancode), ''), CAST({$sm}.salesmancode AS CHAR))"
            : "CAST({$sm}.salesmancode AS CHAR)";
        $headOfficeCodeExpression = $this->hasColumn('customermaster', 'headofficecode')
            ? "COALESCE({$cm}.headofficecode, 0)"
            : '0';
        $headOfficeNameExpression = $this->hasColumn('customermaster', 'headofficecode')
            ? "COALESCE({$ho}.customername, '')"
            : "''";
        $headOfficeArabicNameExpression = $this->hasColumn('customermaster', 'headofficecode')
            ? "COALESCE({$ho}.arbcustomername, '')"
            : "''";
        $customerTypeExpression = $this->hasColumn('customermaster', 'type')
            ? "COALESCE({$cm}.type, 1)"
            : '1';
        $commentsExpression = $this->commentExpression($ci);

        return DB::table('customerinvoice as ci')
            ->join('customermaster as cm', 'cm.customercode', '=', 'ci.customercode')
            ->join('salesman as sm', 'sm.salesmancode', '=', 'ci.salesmancode')
            ->when(
                $this->hasColumn('customermaster', 'headofficecode'),
                fn ($builder) => $builder->leftJoin('customermaster as ho', 'ho.customercode', '=', 'cm.headofficecode')
            )
            ->when(
                $this->hasColumn('customerinvoice', 'voidflag'),
                fn ($builder) => $builder->where('ci.voidflag', 0)
            )
            ->where('ci.invoicebalance', '<>', 0)
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
            ->when(
                $filters['customer_type'] === 1 && $this->hasColumn('customermaster', 'type'),
                fn ($builder) => $builder->where('cm.type', 1)
            )
            ->when(
                $filters['customer_type'] === 2 && $this->hasColumn('customermaster', 'type'),
                fn ($builder) => $builder->where('cm.type', 2)
            )
            ->when(
                $filters['customer_type'] === 3 && $this->hasColumn('customermaster', 'type'),
                fn ($builder) => $builder->whereIn('cm.type', [2, 3])
            )
            ->when(
                $filters['customer_code'] !== null && $filters['customer_type'] === 3 && $this->hasColumn('customermaster', 'headofficecode'),
                fn ($builder) => $builder->where('cm.headofficecode', $filters['customer_code'])
            )
            ->when(
                $filters['customer_code'] !== null && $filters['customer_type'] !== 3,
                fn ($builder) => $builder->where('cm.customercode', $filters['customer_code'])
            )
            ->selectRaw("
                {$customerTypeExpression} as customertype,
                {$headOfficeCodeExpression} as headofficecode_value,
                {$headOfficeNameExpression} as headofficename,
                {$headOfficeArabicNameExpression} as arbheadofficename,
                {$ci}.customercode as customercode_value,
                COALESCE({$customerCodeExpression}, CAST({$ci}.customercode AS CHAR)) as customercode_display,
                COALESCE({$cm}.customername, '') as customername,
                COALESCE({$cm}.arbcustomername, '') as arbcustomername,
                DATE_FORMAT({$ci}.transactiondate, '%d %b %Y') as transactiondate,
                DATE({$ci}.transactiondate) as transactiondate_sort,
                DATE_FORMAT({$ci}.transactiondate, '%Y') as period_year,
                DATE_FORMAT({$ci}.transactiondate, '%m') as period_month,
                {$ci}.salesmancode as salesmancode_value,
                {$salesmanCodeExpression} as salesmancode_display,
                {$salesmanCodeExpression} as salesmancode_sort,
                {$ci}.invoicenumber,
                CAST({$ci}.invoicenumber AS CHAR) as invoicenumber_sort,
                {$commentsExpression} as comments,
                COALESCE({$ci}.invoicebalance, 0) as invoicebalance
            ");
    }

    private function yearOptions(array $filters): array
    {
        if (!$this->hasTables(['customerinvoice', 'customermaster'])) {
            return [];
        }

        $ci = $this->qualifiedAlias('ci');

        return $this->baseFilterQuery($filters)
            ->selectRaw("DATE_FORMAT({$ci}.transactiondate, '%Y') as year")
            ->groupByRaw("DATE_FORMAT({$ci}.transactiondate, '%Y')")
            ->orderBy('year', 'desc')
            ->get()
            ->map(fn ($row) => ['id' => (string) $row->year, 'label' => (string) $row->year])
            ->values()
            ->all();
    }

    private function customerOptions(array $filters): array
    {
        if (
            !$this->hasTables(['customerinvoice', 'customermaster'])
            || !in_array($filters['customer_type'], [1, 2, 3], true)
            || !$this->hasColumn('customermaster', 'type')
        ) {
            return [];
        }

        $cm = $this->qualifiedAlias('cm');
        $ho = $this->qualifiedAlias('ho');
        $query = $this->baseFilterQuery($filters);

        if ($filters['customer_type'] === 3 && $this->hasColumn('customermaster', 'headofficecode')) {
            return $query
                ->whereIn('cm.type', [2, 3])
                ->where('cm.headofficecode', '>', 0)
                ->selectRaw("
                    CAST({$cm}.headofficecode AS CHAR) as id,
                    CONCAT(
                        CAST({$cm}.headofficecode AS CHAR),
                        ' - ',
                        COALESCE(NULLIF(TRIM({$ho}.customername), ''), NULLIF(TRIM({$cm}.customername), ''), CAST({$cm}.headofficecode AS CHAR))
                    ) as label
                ")
                ->groupBy(DB::raw("{$cm}.headofficecode"), DB::raw("{$ho}.customername"), DB::raw("{$cm}.customername"))
                ->orderBy(DB::raw("{$cm}.headofficecode"))
                ->get()
                ->map(fn ($row) => ['id' => (string) $row->id, 'label' => (string) $row->label])
                ->values()
                ->all();
        }

        return $query
            ->where('cm.type', $filters['customer_type'])
            ->selectRaw("
                CAST({$cm}.customercode AS CHAR) as id,
                CONCAT(
                    CAST({$cm}.customercode AS CHAR),
                    ' - ',
                    COALESCE(NULLIF(TRIM({$cm}.customername), ''), CAST({$cm}.customercode AS CHAR))
                ) as label
            ")
            ->groupBy(DB::raw("{$cm}.customercode"), DB::raw("{$cm}.customername"))
            ->orderBy(DB::raw("{$cm}.customercode"))
            ->get()
            ->map(fn ($row) => ['id' => (string) $row->id, 'label' => (string) $row->label])
            ->values()
            ->all();
    }

    private function baseFilterQuery(array $filters)
    {
        return DB::table('customerinvoice as ci')
            ->join('customermaster as cm', 'cm.customercode', '=', 'ci.customercode')
            ->when(
                $this->hasColumn('customermaster', 'headofficecode'),
                fn ($builder) => $builder->leftJoin('customermaster as ho', 'ho.customercode', '=', 'cm.headofficecode')
            )
            ->when(
                $this->hasColumn('customerinvoice', 'voidflag'),
                fn ($builder) => $builder->where('ci.voidflag', 0)
            )
            ->where('ci.invoicebalance', '<>', 0)
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
                fn ($builder) => $builder->whereYear('ci.transactiondate', $filters['year'])
            );
    }

    private function periodColumns(array $filters): array
    {
        if (!$this->hasTables(['customerinvoice'])) {
            return [];
        }

        $ci = $this->qualifiedAlias('ci');

        return $this->baseFilterQuery($filters)
            ->when(
                $filters['customer_type'] === 1 && $this->hasColumn('customermaster', 'type'),
                fn ($builder) => $builder->where('cm.type', 1)
            )
            ->when(
                $filters['customer_type'] === 2 && $this->hasColumn('customermaster', 'type'),
                fn ($builder) => $builder->where('cm.type', 2)
            )
            ->when(
                $filters['customer_type'] === 3 && $this->hasColumn('customermaster', 'type'),
                fn ($builder) => $builder->whereIn('cm.type', [2, 3])
            )
            ->when(
                $filters['customer_code'] !== null && $filters['customer_type'] === 3 && $this->hasColumn('customermaster', 'headofficecode'),
                fn ($builder) => $builder->where('cm.headofficecode', $filters['customer_code'])
            )
            ->when(
                $filters['customer_code'] !== null && $filters['customer_type'] !== 3,
                fn ($builder) => $builder->where('cm.customercode', $filters['customer_code'])
            )
            ->selectRaw("
                DATE_FORMAT({$ci}.transactiondate, '%Y') as period_year,
                DATE_FORMAT({$ci}.transactiondate, '%m') as period_month
            ")
            ->groupByRaw("
                DATE_FORMAT({$ci}.transactiondate, '%Y'),
                DATE_FORMAT({$ci}.transactiondate, '%m')
            ")
            ->orderBy('period_year')
            ->orderBy('period_month')
            ->get()
            ->map(fn ($row) => $this->periodKey((int) $row->period_year, (int) $row->period_month))
            ->values()
            ->all();
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
        return array_merge(
            ['HO Code', 'Customer', 'Transaction Date', 'Salesman Code', 'Invoice Number', 'Comments'],
            $periodColumns,
            ['Total']
        );
    }

    private function mapExportRow(array $row, array $periodColumns): array
    {
        $export = [
            'HO Code' => $row['hocode_label'] ?? '',
            'Customer' => $row['customer_label'] ?? '',
            'Transaction Date' => $row['transactiondate'] ?? '',
            'Salesman Code' => $row['salesmancode_display'] ?? '',
            'Invoice Number' => $row['invoicenumber'] ?? '',
            'Comments' => $row['comments'] ?? '',
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
            'HO Code' => '',
            'Customer' => '',
            'Transaction Date' => '',
            'Salesman Code' => '',
            'Invoice Number' => '',
            'Comments' => 'Total',
        ];

        foreach ($periodColumns as $period) {
            $row[$period] = AmountPrecision::format($totals['period:' . $period] ?? 0);
        }

        $row['Total'] = AmountPrecision::format($totals['total'] ?? 0);

        return $row;
    }

    private function selectedFilterLabels(array $filters, array $scope, array $yearOptions, array $customerOptions): array
    {
        return [
            'Company' => $this->selectedOptionLabel($scope['options']['companies'], $filters['cmpycode']),
            'Region' => $this->selectedOptionLabel($scope['options']['regions'], $filters['regionmstcode']),
            'Branch / Depot' => $this->selectedOptionLabel($scope['options']['depots'], $filters['depotcode']),
            'Area' => $this->selectedOptionLabel($scope['options']['areas'], $filters['areacode']),
            'Sub Area' => $this->selectedOptionLabel($scope['options']['subAreas'], $filters['subareacode']),
            'Route' => $this->selectedOptionLabel($scope['options']['routes'], $filters['routecode']),
            'Year' => $this->selectedStringOptionLabel($yearOptions, $filters['year']),
            'Customer Type' => $this->selectedOptionLabel(self::CUSTOMER_TYPE_OPTIONS, $filters['customer_type']),
            'Customer' => $this->selectedStringOptionLabel($customerOptions, $filters['customer_code']),
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

    private function headOfficeLabel(object $row): string
    {
        $headOfficeCode = $this->normalizeIdentifier($row->headofficecode_value ?? null);
        if ($headOfficeCode === null || $headOfficeCode === '' || $headOfficeCode === '0') {
            return '';
        }

        return $this->labelValue($headOfficeCode, (string) ($row->headofficename ?? ''), (string) ($row->arbheadofficename ?? ''));
    }

    private function labelValue(mixed $code, string $name, string $arabicName): string
    {
        $isArabic = app()->getLocale() === 'ar';
        $label = trim($isArabic ? ($arabicName ?: $name) : ($name ?: $arabicName));
        $code = trim((string) $code);

        if ($code === '') {
            return $label;
        }

        return $label === '' ? $code : trim($code . ' - ' . $label);
    }

    private function periodKey(int $year, int $month): string
    {
        if ($year <= 0 || $month <= 0) {
            return '';
        }

        return now()->setDate($year, $month, 1)->format('M Y');
    }

    private function commentExpression(string $ci): string
    {
        foreach (['comments', 'comment', 'remarks'] as $column) {
            if ($this->hasColumn('customerinvoice', $column)) {
                return "COALESCE({$ci}.{$column}, '')";
            }
        }

        return "''";
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

    private function normalizeIdentifier(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        return trim((string) $value);
    }

    private function identifier(mixed $value): string
    {
        if ($value === null || $value === '') {
            return '';
        }

        if (is_numeric($value)) {
            return (string) (strpos((string) $value, '.') !== false ? (float) $value + 0 : (int) $value);
        }

        return trim((string) $value);
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
