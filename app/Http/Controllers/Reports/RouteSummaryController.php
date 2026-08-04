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
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\Response as HttpResponse;
use Illuminate\View\View;

class RouteSummaryController extends Controller
{
    private const SORT_COLUMNS = [
        'routecode',
        'routestartdate',
        'routestarttime',
        'routeenddate',
        'routeendtime',
        'totalinvdocuments',
        'totalinvretdocuments',
        'totalcashsales',
        'totalgcsales',
        'totaltcsales',
        'totalinvoiceamount',
        'totalardocuments',
        'totalacctsreceivable',
        'totalcash',
        'totalchecks',
        'totalorderamount',
        'totalexpenses',
        'inventoryvariance',
        'cashvariance',
    ];

    private const EXPORT_COLUMNS = [
        'Route (Salesman)' => 'route_label',
        'Route Start Date' => 'routestartdate',
        'Route Start Time' => 'routestarttime',
        'Route End Date' => 'routeenddate',
        'Route End Time' => 'routeendtime',
        'Total Sales Documents' => 'totalinvdocuments',
        'Total Return Documents' => 'totalinvretdocuments',
        'Total Cash Sales' => 'totalcashsales',
        'Total GC Sales' => 'totalgcsales',
        'Total TC Sales' => 'totaltcsales',
        'Total Invoiced Amount' => 'totalinvoiceamount',
        'Total Receipt Documents' => 'totalardocuments',
        'Total Receipt Amount' => 'totalacctsreceivable',
        'Total Cash' => 'totalcash',
        'Total Cheques' => 'totalchecks',
        'Total Order Amount' => 'totalorderamount',
        'Total Expenses' => 'totalexpenses',
        'Inventory Variance' => 'inventoryvariance',
        'Cash Variance' => 'cashvariance',
    ];

    public function __construct(
        private readonly ReportScopeService $reportScopeService
    ) {}

    public function __invoke(Request $request): Response
    {
        $context = $this->resolveContext($request, true);

        $paginator = $this->loadFallbackReport(
            $context['filters'],
            $context['sort_by'],
            $context['sort_dir'],
            $context['page']
        );

        $rows = collect($paginator->items())
            ->map(fn ($row) => $this->transformRow((array) $row))
            ->values();

        return Inertia::render('reports/daily-report/RouteSummary', [
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
            'scope' => [
                'limited' => $context['scope']['limited'],
                'access_type' => $context['scope']['access_type'],
                'route_count' => count($context['scope']['routecodes']),
            ],
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
        $totals = $this->totals($rows);
        $exportRows = $rows
            ->map(fn (array $row) => $this->mapExportRow($row))
            ->push($this->totalsExportRow($totals))
            ->all();

        return ExcelXmlWorkbook::download(
            'route-summary-' . now()->format('Ymd_His') . '.xls',
            array_keys(self::EXPORT_COLUMNS),
            $exportRows,
            'Route Summary'
        );
    }

    public function exportPdf(Request $request): View
    {
        $context = $this->resolveContext($request, false);
        $rows = $this->loadAllRows($context['filters'], $context['sort_by'], $context['sort_dir']);

        return view('reports.route-summary-pdf', [
            'rows' => $rows,
            'totals' => $this->totals($rows),
            'filters' => $context['filters'],
            'selectedFilters' => $this->selectedFilterLabels($context['filters'], $context['scope']),
            'amountPrecision' => AmountPrecision::get(),
        ]);
    }

    private function loadFallbackReport(array $filters, string $sortBy, string $sortDir, int $page): LengthAwarePaginator
    {
        return $this->reportQuery($filters)
            ->orderBy($sortBy, $sortDir)
            ->paginate(
                $filters['per_page'],
                ['*'],
                'page',
                $page
            )->withQueryString();
    }

    private function loadAllRows(array $filters, string $sortBy, string $sortDir): Collection
    {
        return $this->reportQuery($filters)
            ->orderBy($sortBy, $sortDir)
            ->get()
            ->map(fn ($row) => $this->transformRow((array) $row))
            ->values();
    }

    private function reportQuery(array $filters)
    {
        $invoiceHeaderTable = $this->qualifiedTable('invoiceheader');
        $customerMasterTable = $this->qualifiedTable('customermaster');
        $arHeaderTable = $this->qualifiedTable('arheader');
        $cashCheckDetailTable = $this->qualifiedTable('cashcheckdetail');
        $salesOrderHeaderTable = $this->qualifiedTable('salesorderheader');
        $sed = $this->qualifiedAlias('sed');
        $rm = $this->qualifiedAlias('rm');
        $sm = $this->qualifiedAlias('sm');
        $ih = $this->qualifiedAlias('ih');
        $cm = $this->qualifiedAlias('cm');
        $arh = $this->qualifiedAlias('arh');
        $ccd = $this->qualifiedAlias('ccd');
        $ar = $this->qualifiedAlias('ar');
        $soh = $this->qualifiedAlias('soh');

        return DB::table('startendday as sed')
            ->join('routemaster as rm', 'rm.routecode', '=', 'sed.routecode')
            ->join('salesman as sm', 'sm.salesmancode', '=', 'sed.salesmancode')
            ->selectRaw("
                {$sed}.routekey,
                {$rm}.routecode,
                {$rm}.routename,
                {$rm}.arbroutename,
                {$sm}.salesmancode,
                {$sm}.salesmanname1,
                {$sm}.arbsalesmanname1,
                DATE_FORMAT({$sed}.routestartdate, '%d %b %Y') as routestartdate,
                {$sed}.routestarttime,
                DATE_FORMAT({$sed}.routeenddate, '%d %b %Y') as routeenddate,
                {$sed}.routeendtime,
                COALESCE((
                    SELECT COUNT({$ih}.invoicenumber)
                    FROM {$invoiceHeaderTable} {$ih}
                    WHERE {$ih}.routekey = {$sed}.routekey
                      AND {$ih}.voidflag = 0
                      AND {$ih}.totalsalesamount + {$ih}.totalfreesampleamount + {$ih}.totalmanualfree > 0
                ), 0) as totalinvdocuments,
                COALESCE((
                    SELECT COUNT({$ih}.invoicenumber)
                    FROM {$invoiceHeaderTable} {$ih}
                    WHERE {$ih}.routekey = {$sed}.routekey
                      AND {$ih}.voidflag = 0
                      AND {$ih}.totalsalesamount + {$ih}.totalfreesampleamount + {$ih}.totalmanualfree = 0
                ), 0) as totalinvretdocuments,
                COALESCE((
                    SELECT SUM({$ih}.totalinvoiceamount)
                    FROM {$invoiceHeaderTable} {$ih}
                    INNER JOIN {$customerMasterTable} {$cm}
                        ON {$cm}.customercode = {$ih}.customercode
                       AND {$cm}.invoicepaymentterms < 2
                    WHERE {$ih}.routekey = {$sed}.routekey
                      AND {$ih}.voidflag = 0
                ), 0) as totalcashsales,
                COALESCE((
                    SELECT SUM({$ih}.totalinvoiceamount)
                    FROM {$invoiceHeaderTable} {$ih}
                    INNER JOIN {$customerMasterTable} {$cm}
                        ON {$cm}.customercode = {$ih}.customercode
                       AND {$cm}.invoicepaymentterms = 2
                    WHERE {$ih}.routekey = {$sed}.routekey
                      AND {$ih}.voidflag = 0
                ), 0) as totalgcsales,
                COALESCE((
                    SELECT SUM({$ih}.totalinvoiceamount)
                    FROM {$invoiceHeaderTable} {$ih}
                    INNER JOIN {$customerMasterTable} {$cm}
                        ON {$cm}.customercode = {$ih}.customercode
                       AND {$cm}.invoicepaymentterms > 2
                    WHERE {$ih}.routekey = {$sed}.routekey
                      AND {$ih}.voidflag = 0
                ), 0) as totaltcsales,
                COALESCE((
                    SELECT SUM({$ih}.totalinvoiceamount)
                    FROM {$invoiceHeaderTable} {$ih}
                    WHERE {$ih}.routekey = {$sed}.routekey
                      AND {$ih}.voidflag = 0
                ), 0) as totalinvoiceamount,
                COALESCE((
                    SELECT COUNT({$arh}.invoicenumber)
                    FROM {$arHeaderTable} {$arh}
                    WHERE {$arh}.routekey = {$sed}.routekey
                      AND {$arh}.amountpaid <> 0
                      AND {$arh}.voidflag = 0
                ), 0) as totalardocuments,
                COALESCE((
                    SELECT SUM({$arh}.totalinvoiceamount)
                    FROM {$arHeaderTable} {$arh}
                    WHERE {$arh}.routekey = {$sed}.routekey
                      AND {$arh}.voidflag = 0
                ), 0) as totalacctsreceivable,
                (
                    COALESCE((
                        SELECT SUM({$ccd}.amount)
                        FROM {$cashCheckDetailTable} {$ccd}
                        INNER JOIN {$invoiceHeaderTable} {$ih}
                            ON {$ih}.routekey = {$ccd}.routekey
                           AND {$ih}.visitkey = {$ccd}.visitkey
                           AND {$ih}.voidflag = 0
                        WHERE {$ih}.immediatepaid <> 0
                          AND {$ccd}.routekey = {$sed}.routekey
                          AND {$ccd}.typecode = 0
                    ), 0)
                    +
                    COALESCE((
                        SELECT SUM({$ccd}.amount)
                        FROM {$cashCheckDetailTable} {$ccd}
                        INNER JOIN {$arHeaderTable} {$ar}
                            ON {$ar}.routekey = {$ccd}.routekey
                           AND {$ar}.visitkey = {$ccd}.visitkey
                           AND {$ar}.voidflag = 0
                        WHERE {$ccd}.routekey = {$sed}.routekey
                          AND {$ccd}.typecode = 0
                    ), 0)
                ) as totalcash,
                (
                    COALESCE((
                        SELECT SUM({$ccd}.amount)
                        FROM {$cashCheckDetailTable} {$ccd}
                        INNER JOIN {$invoiceHeaderTable} {$ih}
                            ON {$ih}.routekey = {$ccd}.routekey
                           AND {$ih}.visitkey = {$ccd}.visitkey
                           AND {$ih}.voidflag = 0
                        WHERE {$ccd}.routekey = {$sed}.routekey
                          AND {$ccd}.typecode = 1
                    ), 0)
                    +
                    COALESCE((
                        SELECT SUM({$ccd}.amount)
                        FROM {$cashCheckDetailTable} {$ccd}
                        INNER JOIN {$arHeaderTable} {$ar}
                            ON {$ar}.routekey = {$ccd}.routekey
                           AND {$ar}.visitkey = {$ccd}.visitkey
                           AND {$ar}.voidflag = 0
                        WHERE {$ccd}.routekey = {$sed}.routekey
                          AND {$ccd}.typecode = 1
                    ), 0)
                ) as totalchecks,
                COALESCE((
                    SELECT SUM({$soh}.totalinvoiceamount)
                    FROM {$salesOrderHeaderTable} {$soh}
                    WHERE {$soh}.routekey = {$sed}.routekey
                      AND {$soh}.voidflag = 0
                ), 0) as totalorderamount,
                COALESCE({$sed}.totalexpenses, 0) as totalexpenses,
                COALESCE({$sed}.inventoryvariance, 0) as inventoryvariance,
                COALESCE({$sed}.cashvariance, 0) as cashvariance
            ")
            ->when(
                $filters['route_end_date'],
                fn ($builder, $routeEndDate) => $builder->whereDate('sed.routeenddate', $routeEndDate)
            )
            ->when(
                ($filters['scope_limited'] ?? false) && $filters['routecodes'] === [],
                fn ($builder) => $builder->whereRaw('1 = 0')
            )
            ->when(
                $filters['routecodes'] !== [],
                fn ($builder) => $builder->whereIn('sed.routecode', $filters['routecodes'])
            );
    }

    private function qualifiedAlias(string $alias): string
    {
        return DB::getTablePrefix() . $alias;
    }

    private function qualifiedTable(string $table): string
    {
        return DB::getTablePrefix() . $table;
    }

    private function nullableInt(mixed $value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }

        return (int) $value;
    }

    private function transformRow(array $row): array
    {
        $routeCode = (int) ($row['routecode'] ?? 0);
        $salesmanCode = (int) ($row['salesmancode'] ?? 0);
        $isArabic = app()->getLocale() === 'ar';
        $routeName = $isArabic
            ? ($row['arbroutename'] ?? $row['routename'] ?? '')
            : ($row['routename'] ?? $row['arbroutename'] ?? '');
        $salesmanName = $row['salesmanname1'] ?? $row['arbsalesmanname1'] ?? '';
        if ($isArabic) {
            $salesmanName = $row['arbsalesmanname1'] ?? $row['salesmanname1'] ?? '';
        }

        return [
            'routekey' => (int) ($row['routekey'] ?? 0),
            'routecode' => $routeCode,
            'route_label' => trim($routeCode . ' - ' . $routeName . ($salesmanName !== '' ? ' (' . $salesmanName . ')' : '')),
            'routestartdate' => $row['routestartdate'] ?? '',
            'routestarttime' => $row['routestarttime'] ?? '',
            'routeenddate' => $row['routeenddate'] ?? '',
            'routeendtime' => $row['routeendtime'] ?? '',
            'totalinvdocuments' => (int) ($row['totalinvdocuments'] ?? 0),
            'totalinvretdocuments' => (int) ($row['totalinvretdocuments'] ?? 0),
            'totalcashsales' => (float) ($row['totalcashsales'] ?? 0),
            'totalgcsales' => (float) ($row['totalgcsales'] ?? 0),
            'totaltcsales' => (float) ($row['totaltcsales'] ?? 0),
            'totalinvoiceamount' => (float) ($row['totalinvoiceamount'] ?? 0),
            'totalardocuments' => (int) ($row['totalardocuments'] ?? 0),
            'totalacctsreceivable' => (float) ($row['totalacctsreceivable'] ?? 0),
            'totalcash' => (float) ($row['totalcash'] ?? 0),
            'totalchecks' => (float) ($row['totalchecks'] ?? 0),
            'totalorderamount' => (float) ($row['totalorderamount'] ?? 0),
            'totalexpenses' => (float) ($row['totalexpenses'] ?? 0),
            'inventoryvariance' => (float) ($row['inventoryvariance'] ?? 0),
            'cashvariance' => (float) ($row['cashvariance'] ?? 0),
            'salesmancode' => $salesmanCode,
        ];
    }

    private function normalizeFiltersAgainstScope(array $filters, Collection $scopeRows): array
    {
        $checks = [
            'cmpycode',
            'regionmstcode',
            'depotcode',
            'areacode',
            'subareacode',
            'routecode',
        ];

        foreach ($checks as $key) {
            $value = $filters[$key] ?? null;

            if ($value === null || $value === '') {
                continue;
            }

            if (!$scopeRows->contains($key, (int) $value)) {
                $filters[$key] = null;
            }
        }

        return $filters;
    }

    private function totals(Collection $rows): array
    {
        return [
            'totalinvdocuments' => (int) $rows->sum('totalinvdocuments'),
            'totalinvretdocuments' => (int) $rows->sum('totalinvretdocuments'),
            'totalcashsales' => (float) $rows->sum('totalcashsales'),
            'totalgcsales' => (float) $rows->sum('totalgcsales'),
            'totaltcsales' => (float) $rows->sum('totaltcsales'),
            'totalinvoiceamount' => (float) $rows->sum('totalinvoiceamount'),
            'totalardocuments' => (int) $rows->sum('totalardocuments'),
            'totalacctsreceivable' => (float) $rows->sum('totalacctsreceivable'),
            'totalcash' => (float) $rows->sum('totalcash'),
            'totalchecks' => (float) $rows->sum('totalchecks'),
            'totalorderamount' => (float) $rows->sum('totalorderamount'),
            'totalexpenses' => (float) $rows->sum('totalexpenses'),
            'inventoryvariance' => (float) $rows->sum('inventoryvariance'),
            'cashvariance' => (float) $rows->sum('cashvariance'),
        ];
    }

    private function resolveContext(Request $request, bool $withPagination): array
    {
        $user = $request->user();

        abort_unless($user?->hasFormPermission('reports'), 403);

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
            'per_page' => $withPagination ? (int) ($validated['per_page'] ?? 25) : null,
        ];

        $scope = $this->reportScopeService->resolve($user, $filters);
        $filters = $this->normalizeFiltersAgainstScope($filters, $scope['rows']);
        $scope = $this->reportScopeService->resolve($user, $filters);
        $filters['routecodes'] = $scope['routecodes'];
        $filters['scope_limited'] = $scope['limited'];

        $requestedSortBy = $validated['sort_by'] ?? 'routecode';
        $sortBy = in_array($requestedSortBy, self::SORT_COLUMNS, true)
            ? $requestedSortBy
            : 'routecode';

        return [
            'filters' => $filters,
            'scope' => $scope,
            'sort_by' => $sortBy,
            'sort_dir' => $validated['sort_dir'] ?? 'asc',
            'page' => max((int) ($validated['page'] ?? 1), 1),
        ];
    }

    private function mapExportRow(array $row): array
    {
        $export = [];

        foreach (self::EXPORT_COLUMNS as $label => $key) {
            $value = $row[$key] ?? '';

            if (is_float($value) || in_array($key, [
                'totalcashsales',
                'totalgcsales',
                'totaltcsales',
                'totalinvoiceamount',
                'totalacctsreceivable',
                'totalcash',
                'totalchecks',
                'totalorderamount',
                'totalexpenses',
                'inventoryvariance',
                'cashvariance',
            ], true)) {
                $value = AmountPrecision::format($value);
            }

            $export[$label] = $value;
        }

        return $export;
    }

    private function totalsExportRow(array $totals): array
    {
        $row = array_fill_keys(array_keys(self::EXPORT_COLUMNS), '');
        $row['Route (Salesman)'] = 'Total';

        foreach (self::EXPORT_COLUMNS as $label => $key) {
            if (!array_key_exists($key, $totals)) {
                continue;
            }

            $row[$label] = is_float($totals[$key])
                ? AmountPrecision::format($totals[$key])
                : (string) $totals[$key];
        }

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

}
