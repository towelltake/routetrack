<?php

namespace App\Http\Controllers\Reports;

use App\Http\Controllers\Controller;
use App\Services\Reports\ReportScopeService;
use App\Support\AmountPrecision;
use App\Support\ExcelXmlWorkbook;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\View\View;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\Response as HttpResponse;

class RouteAgeingController extends Controller
{
    private const SORT_COLUMNS = [
        'route_label',
        'salesman_label',
        'transactiondate_sort',
        'invoicenumber_sort',
        'customercode_sort',
        'customername_sort',
        'creditlimitdays',
        'age',
        'age31',
        'age61',
        'age91',
        'age121',
        'pdcamount',
        'pdcdate_sort',
        'invoicebalance',
    ];

    private const EXPORT_COLUMNS = [
        'Route' => 'route_label',
        'Salesman' => 'salesman_label',
        'Transaction Date' => 'transactiondate',
        'Invoice No' => 'invoicenumber',
        'Customer Code' => 'customercode',
        'Customer Name' => 'customername',
        'Credit Days' => 'creditlimitdays',
        '1-30' => 'age',
        '31-60' => 'age31',
        '61-90' => 'age61',
        '91-120' => 'age91',
        'Above 120' => 'age121',
        'PDC Amount' => 'pdcamount',
        'PDC Date' => 'pdcdate',
        'Total' => 'invoicebalance',
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
            $context['filters']['per_page'],
            $context['page'],
            $request
        );
        $pageRows = collect($pageData['items']);

        return Inertia::render('reports/accounts-report/RouteAgeing', [
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
            'rows' => $pageRows->values()->all(),
            'totals' => $this->totals($pageRows),
            'pagination' => $pageData['pagination'],
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
            'route-ageing-' . now()->format('Ymd_His') . '.xls',
            array_keys(self::EXPORT_COLUMNS),
            $exportRows,
            'Route Ageing'
        );
    }

    public function exportPdf(Request $request): View
    {
        $context = $this->resolveContext($request, false);
        $rows = $this->loadAllRows($context['filters'], $context['sort_by'], $context['sort_dir']);

        return view('reports.route-ageing-pdf', [
            'rows' => $rows,
            'totals' => $this->totals($rows),
            'selectedFilters' => $this->selectedFilterLabels($context['filters'], $context['scope']),
            'amountPrecision' => AmountPrecision::get(),
        ]);
    }

    private function resolveContext(Request $request, bool $withPagination): array
    {
        $user = $request->user();
        abort_unless($user?->hasFormPermission('route ageing'), 403);

        $rules = [
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

        $requestedSortBy = $validated['sort_by'] ?? 'route_label';

        return [
            'filters' => $filters,
            'scope' => $scope,
            'sort_by' => in_array($requestedSortBy, self::SORT_COLUMNS, true) ? $requestedSortBy : 'route_label',
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

                foreach (['route_label', 'salesman_label', 'transactiondate_sort', 'invoicenumber_sort'] as $fallback) {
                    $fallbackCompare = $this->compare($left[$fallback] ?? null, $right[$fallback] ?? null);
                    if ($fallbackCompare !== 0) {
                        return $fallbackCompare;
                    }
                }

                return 0;
            })
            ->values();
    }

    private function loadPageRows(
        array $filters,
        string $sortBy,
        string $sortDir,
        int $perPage,
        int $page,
        Request $request
    ): array {
        if (!$this->hasTables(['customerinvoice', 'customermaster', 'routemaster', 'salesman'])) {
            return [
                'items' => [],
                'pagination' => $this->paginationPayload([], $perPage, max($page, 1), false),
            ];
        }

        if (($filters['scope_limited'] ?? false) && $filters['routecodes'] === []) {
            return [
                'items' => [],
                'pagination' => $this->paginationPayload([], $perPage, max($page, 1), false),
            ];
        }

        $currentPage = max($page, 1);

        $paginator = $this->applySqlSorting($this->baseQuery($filters), $sortBy, $sortDir)
            ->simplePaginate($perPage, ['*'], 'page', $currentPage)
            ->withQueryString();

        $items = collect($paginator->items())
            ->map(fn ($row) => $this->transformRow((array) $row))
            ->all();

        return [
            'items' => $items,
            'pagination' => $this->paginationPayload($items, $perPage, $currentPage, $paginator->hasMorePages()),
        ];
    }

    private function baseQuery(array $filters)
    {
        $ci = $this->qualifiedAlias('ci');
        $cm = $this->qualifiedAlias('cm');
        $rm = $this->qualifiedAlias('rm');
        $sm = $this->qualifiedAlias('sm');

        $customerCodeExpression = $this->hasColumn('customermaster', 'reportcustcode')
            ? (
                $this->hasColumn('customermaster', 'alternatecode')
                    ? "COALESCE(NULLIF(NULLIF(TRIM(CAST({$cm}.reportcustcode AS CHAR)), ''), '0'), NULLIF(NULLIF(TRIM({$cm}.alternatecode), ''), '0'), CAST({$cm}.customercode AS CHAR))"
                    : "COALESCE(NULLIF(NULLIF(TRIM(CAST({$cm}.reportcustcode AS CHAR)), ''), '0'), CAST({$cm}.customercode AS CHAR))"
            )
            : ($this->hasColumn('customermaster', 'alternatecode')
                ? "COALESCE(NULLIF(NULLIF(TRIM({$cm}.alternatecode), ''), '0'), CAST({$cm}.customercode AS CHAR))"
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
        $pdcAmountExpression = $this->hasColumn('customerinvoice', 'pdcbalance')
            ? "CASE
                    WHEN COALESCE({$ci}.pdcbalance, 0) != 0 THEN COALESCE({$ci}.pdcbalance, 0)
                    ELSE {$this->pdcBalanceFallbackSql($ci)}
               END"
            : $this->pdcBalanceFallbackSql($ci);
        $pdcDateExpression = $this->pdcDateExpression($ci);
        $pdcDateSortExpression = $this->pdcDateSortExpression($ci);

        $query = DB::table('customerinvoice as ci')
            ->join('customermaster as cm', 'cm.customercode', '=', 'ci.customercode')
            ->join('routemaster as rm', 'rm.routecode', '=', 'ci.routecode')
            ->join('salesman as sm', 'sm.salesmancode', '=', 'ci.salesmancode')
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
            ->selectRaw("
                {$ci}.transactionkey,
                DATE_FORMAT({$ci}.transactiondate, '%d %b %Y') as transactiondate,
                DATE({$ci}.transactiondate) as transactiondate_sort,
                {$ci}.invoicenumber,
                CAST({$ci}.invoicenumber AS CHAR) as invoicenumber_sort,
                {$ci}.routecode as routecode_value,
                {$routeCodeExpression} as routecode_display,
                COALESCE({$rm}.routename, '') as routename,
                COALESCE({$rm}.arbroutename, '') as arbroutename,
                {$ci}.salesmancode as salesmancode_value,
                {$salesmanCodeExpression} as salesmancode_display,
                COALESCE({$sm}.salesmanname1, '') as salesmanname1,
                COALESCE({$sm}.arbsalesmanname1, '') as arbsalesmanname1,
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
                CASE WHEN DATEDIFF(CURRENT_DATE(), {$ci}.transactiondate) > 120 THEN {$invoiceBalanceExpression} ELSE 0 END as age121,
                {$pdcAmountExpression} as pdcamount,
                {$pdcDateExpression} as pdcdate,
                {$pdcDateSortExpression} as pdcdate_sort
            ");

        if ($pdcBalanceSummary = $this->pdcBalanceSummaryQuery()) {
            $query->leftJoinSub($pdcBalanceSummary, 'pdc_balance_summary', function ($join) {
                $join->on('pdc_balance_summary.invoicenumber', '=', 'ci.invoicenumber');
            });
        }

        if ($pdcDateSummary = $this->pdcDateSummaryQuery()) {
            $query->leftJoinSub($pdcDateSummary, 'pdc_date_summary', function ($join) {
                $join->on('pdc_date_summary.invoicenumber', '=', 'ci.invoicenumber');
            });
        }

        return $query;
    }

    private function transformRow(array $row): array
    {
        $isArabic = app()->getLocale() === 'ar';
        $routeName = $isArabic
            ? ($row['arbroutename'] ?? $row['routename'] ?? '')
            : ($row['routename'] ?? $row['arbroutename'] ?? '');
        $salesmanName = $isArabic
            ? ($row['arbsalesmanname1'] ?? $row['salesmanname1'] ?? '')
            : ($row['salesmanname1'] ?? $row['arbsalesmanname1'] ?? '');
        $customerName = $isArabic
            ? ($row['arbcustomername'] ?? $row['customername'] ?? '')
            : ($row['customername'] ?? $row['arbcustomername'] ?? '');

        return [
            'transactionkey' => (int) ($row['transactionkey'] ?? 0),
            'route_label' => trim(($row['routecode_display'] ?? $row['routecode_value'] ?? '') . ' - ' . $routeName),
            'salesman_label' => trim(($row['salesmancode_display'] ?? $row['salesmancode_value'] ?? '') . ' - ' . $salesmanName),
            'transactiondate' => (string) ($row['transactiondate'] ?? ''),
            'transactiondate_sort' => (string) ($row['transactiondate_sort'] ?? ''),
            'invoicenumber' => $this->identifier($row['invoicenumber'] ?? ''),
            'invoicenumber_sort' => (string) ($row['invoicenumber_sort'] ?? ''),
            'customercode' => (string) ($row['customercode_display'] ?? $row['customercode_value'] ?? ''),
            'customercode_sort' => (string) ($row['customercode_display'] ?? $row['customercode_value'] ?? ''),
            'customername' => (string) $customerName,
            'customername_sort' => (string) $customerName,
            'creditlimitdays' => (int) ($row['creditlimitdays'] ?? 0),
            'age' => (float) ($row['age'] ?? 0),
            'age31' => (float) ($row['age31'] ?? 0),
            'age61' => (float) ($row['age61'] ?? 0),
            'age91' => (float) ($row['age91'] ?? 0),
            'age121' => (float) ($row['age121'] ?? 0),
            'pdcamount' => (float) ($row['pdcamount'] ?? 0),
            'pdcdate' => (string) ($row['pdcdate'] ?? ''),
            'pdcdate_sort' => (string) ($row['pdcdate_sort'] ?? ''),
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
            'pdcamount' => (float) $rows->sum('pdcamount'),
            'invoicebalance' => (float) $rows->sum('invoicebalance'),
        ];
    }

    private function mapExportRow(array $row): array
    {
        $export = [];

        foreach (self::EXPORT_COLUMNS as $label => $key) {
            $value = $row[$key] ?? '';
            if (in_array($key, ['age', 'age31', 'age61', 'age91', 'age121', 'pdcamount', 'invoicebalance'], true)) {
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
        $row['PDC Amount'] = AmountPrecision::format($totals['pdcamount']);
        $row['Total'] = AmountPrecision::format($totals['invoicebalance']);

        return $row;
    }

    private function selectedFilterLabels(array $filters, array $scope): array
    {
        return [
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

    private function applySqlSorting($query, string $sortBy, string $sortDir)
    {
        $direction = $sortDir === 'desc' ? 'desc' : 'asc';

        $sortedQuery = match ($sortBy) {
            'route_label' => $query
                ->orderBy('routecode_display', $direction)
                ->orderBy('routename')
                ->orderBy('arbroutename'),
            'salesman_label' => $query
                ->orderBy('salesmancode_display', $direction)
                ->orderBy('salesmanname1')
                ->orderBy('arbsalesmanname1'),
            'transactiondate_sort' => $query->orderBy('transactiondate_sort', $direction),
            'invoicenumber_sort' => $query->orderBy('invoicenumber_sort', $direction),
            'customercode_sort' => $query->orderBy('customercode_display', $direction),
            'customername_sort' => $query
                ->orderBy('customername', $direction)
                ->orderBy('arbcustomername'),
            'creditlimitdays' => $query->orderBy('creditlimitdays', $direction),
            'age' => $query->orderBy('age', $direction),
            'age31' => $query->orderBy('age31', $direction),
            'age61' => $query->orderBy('age61', $direction),
            'age91' => $query->orderBy('age91', $direction),
            'age121' => $query->orderBy('age121', $direction),
            'pdcamount' => $query->orderBy('pdcamount', $direction),
            'pdcdate_sort' => $query->orderBy('pdcdate_sort', $direction),
            'invoicebalance' => $query->orderBy('invoicebalance', $direction),
            default => $query->orderBy('routecode_display'),
        };

        return $sortedQuery
            ->orderBy('routecode_display')
            ->orderBy('salesmancode_display')
            ->orderBy('transactiondate_sort')
            ->orderBy('invoicenumber_sort');
    }

    private function pdcBalanceFallbackSql(string $ci): string
    {
        if (!$this->hasTables(['ardetail']) || !$this->hasColumn('ardetail', 'pdcbalance')) {
            return '0';
        }

        $summary = $this->qualifiedAlias('pdc_balance_summary');

        return "COALESCE({$summary}.fallback_pdcamount, 0)";
    }

    private function pdcDateExpression(string $ci): string
    {
        if ($this->hasColumn('customerinvoice', 'pdcdate')) {
            return "CASE
                WHEN {$ci}.pdcdate IS NOT NULL THEN DATE_FORMAT({$ci}.pdcdate, '%d %b %Y')
                ELSE {$this->pdcDateFallbackSql($ci, true)}
            END";
        }

        return $this->pdcDateFallbackSql($ci, true);
    }

    private function pdcDateSortExpression(string $ci): string
    {
        if ($this->hasColumn('customerinvoice', 'pdcdate')) {
            return "CASE
                WHEN {$ci}.pdcdate IS NOT NULL THEN DATE({$ci}.pdcdate)
                ELSE {$this->pdcDateFallbackSql($ci, false)}
            END";
        }

        return $this->pdcDateFallbackSql($ci, false);
    }

    private function pdcDateFallbackSql(string $ci, bool $formatted): string
    {
        if (!$this->hasTables(['ardetail', 'cashcheckdetail'])) {
            return $formatted ? "''" : 'NULL';
        }

        if (!$this->hasColumn('cashcheckdetail', 'checkdate')) {
            return $formatted ? "''" : 'NULL';
        }

        $summary = $this->qualifiedAlias('pdc_date_summary');

        return $formatted
            ? "CASE WHEN {$summary}.fallback_pdcdate IS NOT NULL THEN DATE_FORMAT({$summary}.fallback_pdcdate, '%d %b %Y') ELSE '' END"
            : "{$summary}.fallback_pdcdate";
    }

    private function pdcBalanceSummaryQuery()
    {
        if (!$this->hasTables(['ardetail']) || !$this->hasColumn('ardetail', 'pdcbalance')) {
            return null;
        }

        $ard = $this->qualifiedAlias('ard');

        return DB::table('ardetail as ard')
            ->selectRaw("{$ard}.invoicenumber, SUM(COALESCE({$ard}.pdcbalance, 0)) as fallback_pdcamount")
            ->where('ard.pdcbalance', '>', 0)
            ->groupBy(DB::raw("{$ard}.invoicenumber"));
    }

    private function pdcDateSummaryQuery()
    {
        if (!$this->hasTables(['ardetail', 'cashcheckdetail']) || !$this->hasColumn('cashcheckdetail', 'checkdate')) {
            return null;
        }

        $ard = $this->qualifiedAlias('ard');
        $ccd = $this->qualifiedAlias('ccd');

        return DB::table('ardetail as ard')
            ->join('cashcheckdetail as ccd', function ($join) {
                $join->on('ccd.routekey', '=', 'ard.routekey')
                    ->on('ccd.visitkey', '=', 'ard.visitkey');
            })
            ->selectRaw("{$ard}.invoicenumber, MIN({$ccd}.checkdate) as fallback_pdcdate")
            ->where('ard.pdcbalance', '>', 0)
            ->groupBy(DB::raw("{$ard}.invoicenumber"));
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

    private function qualifiedAlias(string $alias): string
    {
        return DB::getTablePrefix() . $alias;
    }

    private function qualifiedTable(string $table): string
    {
        return DB::getTablePrefix() . $table;
    }

    private function paginationPayload(array $items, int $perPage, int $currentPage, bool $hasMorePages): array
    {
        $count = count($items);
        $from = $count > 0 ? (($currentPage - 1) * $perPage) + 1 : null;
        $to = $count > 0 ? $from + $count - 1 : null;

        return [
            'current_page' => $currentPage,
            'last_page' => $hasMorePages ? $currentPage + 1 : max($currentPage, 1),
            'per_page' => $perPage,
            'total' => $hasMorePages ? null : (($currentPage - 1) * $perPage) + $count,
            'from' => $from,
            'to' => $to,
            'has_more_pages' => $hasMorePages,
        ];
    }
}
