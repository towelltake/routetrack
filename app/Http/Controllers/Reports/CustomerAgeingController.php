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

class CustomerAgeingController extends Controller
{
    private const CUSTOMER_TYPE_OPTIONS = [
        ['id' => 1, 'label' => '1 - Normal'],
        ['id' => 2, 'label' => '2 - Branch'],
        ['id' => 3, 'label' => '3 - Head Office'],
    ];

    private const SORT_COLUMNS = [
        'type_label',
        'customer_label',
        'creditlimitdays',
        'transactiondate_sort',
        'invoicenumber_sort',
        'routecode_sort',
        'salesmancode_sort',
        'age',
        'age31',
        'age61',
        'age91',
        'age121',
        'invoicebalance',
    ];

    private const EXPORT_COLUMNS = [
        'Type' => 'type_label',
        'Customer' => 'customer_label',
        'Credit Days' => 'creditlimitdays',
        'Transaction Date' => 'transactiondate',
        'Invoice No' => 'invoicenumber',
        'Route Code' => 'routecode_display',
        'Salesman Code' => 'salesmancode_display',
        '1-30' => 'age',
        '31-60' => 'age31',
        '61-90' => 'age61',
        '91-120' => 'age91',
        'Above 120' => 'age121',
        'Total' => 'invoicebalance',
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

        return Inertia::render('reports/accounts-report/CustomerAgeing', [
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
            'customerTypeOptions' => self::CUSTOMER_TYPE_OPTIONS,
            'customerOptions' => $context['customer_options'],
            'rows' => $pageRows->values()->all(),
            'totals' => $this->totals($pageRows),
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
            'customer-ageing-' . now()->format('Ymd_His') . '.xls',
            array_keys(self::EXPORT_COLUMNS),
            $exportRows,
            'Customer Ageing'
        );
    }

    public function exportPdf(Request $request): View
    {
        $context = $this->resolveContext($request, false);
        $rows = $this->loadAllRows($context['filters'], $context['sort_by'], $context['sort_dir']);

        return view('reports.customer-ageing-pdf', [
            'rows' => $rows,
            'totals' => $this->totals($rows),
            'selectedFilters' => $this->selectedFilterLabels($context['filters'], $context['scope'], $context['customer_options']),
            'amountPrecision' => AmountPrecision::get(),
        ]);
    }

    private function resolveContext(Request $request, bool $withPagination): array
    {
        $user = $request->user();
        abort_unless($user?->hasFormPermission('customer ageing'), 403);

        $rules = [
            'cmpycode' => ['nullable', 'integer'],
            'regionmstcode' => ['nullable', 'integer'],
            'depotcode' => ['nullable', 'integer'],
            'areacode' => ['nullable', 'integer'],
            'subareacode' => ['nullable', 'integer'],
            'routecode' => ['nullable', 'integer'],
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
            'customer_type' => $this->nullableInt($validated['customer_type'] ?? null),
            'customer_code' => $this->normalizeIdentifier($validated['customer_code'] ?? null),
            'per_page' => $withPagination ? (int) ($validated['per_page'] ?? 25) : 100000,
        ];

        $scope = $this->reportScopeService->resolve($user, $filters);
        $filters = $this->normalizeFiltersAgainstScope($filters, $scope['rows']);
        $scope = $this->reportScopeService->resolve($user, $filters);
        $filters['routecodes'] = $scope['routecodes'];
        $filters['scope_limited'] = $scope['limited'];

        $customerOptions = $this->customerOptions($filters);
        if ($filters['customer_code'] !== null && !collect($customerOptions)->contains(fn (array $option) => (string) $option['id'] === $filters['customer_code'])) {
            $filters['customer_code'] = null;
            $customerOptions = $this->customerOptions($filters);
        }

        $requestedSortBy = $validated['sort_by'] ?? 'type_label';

        return [
            'filters' => $filters,
            'scope' => $scope,
            'customer_options' => $customerOptions,
            'sort_by' => in_array($requestedSortBy, self::SORT_COLUMNS, true) ? $requestedSortBy : 'type_label',
            'sort_dir' => $validated['sort_dir'] ?? 'asc',
            'page' => max((int) ($validated['page'] ?? 1), 1),
        ];
    }

    private function loadAllRows(array $filters, string $sortBy, string $sortDir): Collection
    {
        if (!$this->hasTables(['customerinvoice', 'customermaster', 'routemaster', 'salesman'])) {
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

                foreach (['type_label', 'customer_label', 'transactiondate_sort', 'invoicenumber_sort'] as $fallback) {
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
        $cm = $this->qualifiedAlias('cm');
        $rm = $this->qualifiedAlias('rm');
        $sm = $this->qualifiedAlias('sm');
        $ho = $this->qualifiedAlias('ho');

        $customerCodeExpression = $this->hasColumn('customermaster', 'reportcustcode')
            ? "NULLIF(TRIM(CAST({$cm}.reportcustcode AS CHAR)), '')"
            : ($this->hasColumn('customermaster', 'alternatecode')
                ? "NULLIF(TRIM({$cm}.alternatecode), '')"
                : "CAST({$cm}.customercode AS CHAR)");
        $routeCodeExpression = $this->hasColumn('routemaster', 'alternateroutecode')
            ? "COALESCE(NULLIF(TRIM({$rm}.alternateroutecode), ''), CAST({$rm}.routecode AS CHAR))"
            : "CAST({$rm}.routecode AS CHAR)";
        $salesmanCodeExpression = $this->hasColumn('salesman', 'alternatesalesmancode')
            ? "COALESCE(NULLIF(TRIM({$sm}.alternatesalesmancode), ''), CAST({$sm}.salesmancode AS CHAR))"
            : "CAST({$sm}.salesmancode AS CHAR)";
        $creditDaysExpression = $this->hasColumn('customermaster', 'creditlimitdays')
            ? "COALESCE({$cm}.creditlimitdays, 0)"
            : '0';
        $invoiceBalanceExpression = "COALESCE({$ci}.invoicebalance, 0)";
        $customerTypeExpression = $this->hasColumn('customermaster', 'type')
            ? "COALESCE({$cm}.type, 1)"
            : '1';
        $headOfficeCodeExpression = $this->hasColumn('customermaster', 'headofficecode')
            ? "COALESCE({$cm}.headofficecode, 0)"
            : '0';
        $headOfficeNameExpression = $this->hasColumn('customermaster', 'headofficecode')
            ? "CASE
                    WHEN COALESCE({$cm}.headofficecode, 0) > 0 THEN COALESCE({$ho}.customername, {$cm}.customername, '')
                    ELSE COALESCE({$cm}.customername, '')
               END"
            : "COALESCE({$cm}.customername, '')";
        $headOfficeArabicNameExpression = $this->hasColumn('customermaster', 'headofficecode')
            ? "CASE
                    WHEN COALESCE({$cm}.headofficecode, 0) > 0 THEN COALESCE({$ho}.arbcustomername, {$cm}.arbcustomername, '')
                    ELSE COALESCE({$cm}.arbcustomername, '')
               END"
            : "COALESCE({$cm}.arbcustomername, '')";

        return DB::table('customerinvoice as ci')
            ->join('customermaster as cm', 'cm.customercode', '=', 'ci.customercode')
            ->join('routemaster as rm', 'rm.routecode', '=', 'ci.routecode')
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
                {$ci}.transactionkey,
                {$customerTypeExpression} as customertype,
                {$headOfficeCodeExpression} as headofficecode_value,
                {$headOfficeNameExpression} as headofficename,
                {$headOfficeArabicNameExpression} as arbheadofficename,
                DATE_FORMAT({$ci}.transactiondate, '%d %b %Y') as transactiondate,
                DATE({$ci}.transactiondate) as transactiondate_sort,
                {$ci}.invoicenumber,
                CAST({$ci}.invoicenumber AS CHAR) as invoicenumber_sort,
                {$ci}.routecode as routecode_value,
                {$routeCodeExpression} as routecode_display,
                {$routeCodeExpression} as routecode_sort,
                {$ci}.salesmancode as salesmancode_value,
                {$salesmanCodeExpression} as salesmancode_display,
                {$salesmanCodeExpression} as salesmancode_sort,
                {$ci}.customercode as customercode_value,
                COALESCE({$customerCodeExpression}, CAST({$ci}.customercode AS CHAR)) as customercode_display,
                COALESCE({$cm}.customername, '') as customername,
                COALESCE({$cm}.arbcustomername, '') as arbcustomername,
                {$creditDaysExpression} as creditlimitdays,
                {$invoiceBalanceExpression} as invoicebalance,
                CASE WHEN DATEDIFF(CURRENT_DATE(), {$ci}.transactiondate) > 0 AND DATEDIFF(CURRENT_DATE(), {$ci}.transactiondate) <= 30 THEN {$invoiceBalanceExpression} ELSE 0 END as age,
                CASE WHEN DATEDIFF(CURRENT_DATE(), {$ci}.transactiondate) > 30 AND DATEDIFF(CURRENT_DATE(), {$ci}.transactiondate) <= 60 THEN {$invoiceBalanceExpression} ELSE 0 END as age31,
                CASE WHEN DATEDIFF(CURRENT_DATE(), {$ci}.transactiondate) > 60 AND DATEDIFF(CURRENT_DATE(), {$ci}.transactiondate) <= 90 THEN {$invoiceBalanceExpression} ELSE 0 END as age61,
                CASE WHEN DATEDIFF(CURRENT_DATE(), {$ci}.transactiondate) > 90 AND DATEDIFF(CURRENT_DATE(), {$ci}.transactiondate) <= 120 THEN {$invoiceBalanceExpression} ELSE 0 END as age91,
                CASE WHEN DATEDIFF(CURRENT_DATE(), {$ci}.transactiondate) > 120 THEN {$invoiceBalanceExpression} ELSE 0 END as age121
            ");
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

        $ci = $this->qualifiedAlias('ci');
        $cm = $this->qualifiedAlias('cm');
        $ho = $this->qualifiedAlias('ho');

        $query = DB::table('customerinvoice as ci')
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
            );

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

    private function transformRow(array $row): array
    {
        $isArabic = app()->getLocale() === 'ar';
        $customerName = $isArabic
            ? ($row['arbcustomername'] ?? $row['customername'] ?? '')
            : ($row['customername'] ?? $row['arbcustomername'] ?? '');
        $headOfficeName = $isArabic
            ? ($row['arbheadofficename'] ?? $row['headofficename'] ?? '')
            : ($row['headofficename'] ?? $row['arbheadofficename'] ?? '');

        return [
            'transactionkey' => (int) ($row['transactionkey'] ?? 0),
            'type_label' => $this->typeLabel(
                (int) ($row['customertype'] ?? 1),
                $this->normalizeIdentifier($row['headofficecode_value'] ?? null),
                $headOfficeName
            ),
            'customer_label' => trim(($row['customercode_display'] ?? $row['customercode_value'] ?? '') . ' - ' . $customerName),
            'creditlimitdays' => (int) ($row['creditlimitdays'] ?? 0),
            'transactiondate' => (string) ($row['transactiondate'] ?? ''),
            'transactiondate_sort' => (string) ($row['transactiondate_sort'] ?? ''),
            'invoicenumber' => $this->identifier($row['invoicenumber'] ?? ''),
            'invoicenumber_sort' => (string) ($row['invoicenumber_sort'] ?? ''),
            'routecode_display' => (string) ($row['routecode_display'] ?? $row['routecode_value'] ?? ''),
            'routecode_sort' => (string) ($row['routecode_sort'] ?? $row['routecode_display'] ?? ''),
            'salesmancode_display' => (string) ($row['salesmancode_display'] ?? $row['salesmancode_value'] ?? ''),
            'salesmancode_sort' => (string) ($row['salesmancode_sort'] ?? $row['salesmancode_display'] ?? ''),
            'age' => (float) ($row['age'] ?? 0),
            'age31' => (float) ($row['age31'] ?? 0),
            'age61' => (float) ($row['age61'] ?? 0),
            'age91' => (float) ($row['age91'] ?? 0),
            'age121' => (float) ($row['age121'] ?? 0),
            'invoicebalance' => (float) ($row['invoicebalance'] ?? 0),
        ];
    }

    private function totals(Collection $rows): array
    {
        return [
            'age' => (float) $rows->sum('age'),
            'age31' => (float) $rows->sum('age31'),
            'age61' => (float) $rows->sum('age61'),
            'age91' => (float) $rows->sum('age91'),
            'age121' => (float) $rows->sum('age121'),
            'invoicebalance' => (float) $rows->sum('invoicebalance'),
        ];
    }

    private function mapExportRow(array $row): array
    {
        $export = [];

        foreach (self::EXPORT_COLUMNS as $label => $key) {
            $value = $row[$key] ?? '';
            if (in_array($key, ['age', 'age31', 'age61', 'age91', 'age121', 'invoicebalance'], true)) {
                $value = AmountPrecision::format($value);
            }
            $export[$label] = $value;
        }

        return $export;
    }

    private function totalsExportRow(array $totals): array
    {
        $row = array_fill_keys(array_keys(self::EXPORT_COLUMNS), '');
        $row['Credit Days'] = 'Total';
        $row['1-30'] = AmountPrecision::format($totals['age']);
        $row['31-60'] = AmountPrecision::format($totals['age31']);
        $row['61-90'] = AmountPrecision::format($totals['age61']);
        $row['91-120'] = AmountPrecision::format($totals['age91']);
        $row['Above 120'] = AmountPrecision::format($totals['age121']);
        $row['Total'] = AmountPrecision::format($totals['invoicebalance']);

        return $row;
    }

    private function selectedFilterLabels(array $filters, array $scope, array $customerOptions): array
    {
        return [
            'Company' => $this->selectedOptionLabel($scope['options']['companies'], $filters['cmpycode']),
            'Region' => $this->selectedOptionLabel($scope['options']['regions'], $filters['regionmstcode']),
            'Branch / Depot' => $this->selectedOptionLabel($scope['options']['depots'], $filters['depotcode']),
            'Area' => $this->selectedOptionLabel($scope['options']['areas'], $filters['areacode']),
            'Sub Area' => $this->selectedOptionLabel($scope['options']['subAreas'], $filters['subareacode']),
            'Route' => $this->selectedOptionLabel($scope['options']['routes'], $filters['routecode']),
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

    private function typeLabel(int $type, ?string $headOfficeCode, string $headOfficeName): string
    {
        if ($type === 3 && $headOfficeCode !== null && $headOfficeCode !== '') {
            return trim($headOfficeCode . ' - ' . $headOfficeName);
        }

        return match ($type) {
            2 => '2 - Branch',
            3 => '3 - Head Office',
            default => '1 - Normal',
        };
    }

    private function qualifiedAlias(string $alias): string
    {
        return DB::getTablePrefix() . $alias;
    }
}
