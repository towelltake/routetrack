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

class PaymentSummaryController extends Controller
{
    private const SORT_COLUMNS = [
        'routecode',
        'type_sort',
        'transactiondate_sort',
        'transactiontime_sort',
        'salesmancode',
        'customercode_sort',
        'customername_sort',
        'invoicenumber_sort',
        'mop',
        'totalinvoiceamount',
        'immediatecash',
        'immediatecheck',
        'arcash',
        'archeck',
        'outstandingbalanceamount',
    ];

    private const EXPORT_COLUMNS = [
        'Route' => 'route_label',
        'Type' => 'type_label',
        'Transaction Date' => 'transactiondate',
        'Transaction Time' => 'transactiontime',
        'Salesman Code' => 'salesmancode',
        'Customer Code' => 'customercode',
        'Customer Name' => 'customer_label',
        'Invoice Number' => 'invoicenumber',
        'MOP' => 'mop',
        'Total Invoice Amount' => 'totalinvoiceamount',
        'Immediate CASH Payment' => 'immediatecash',
        'Immediate Cheque Payment' => 'immediatecheck',
        'AR CASH Collection' => 'arcash',
        'AR Cheque Collection' => 'archeck',
        'Outstanding Balance Amount' => 'outstandingbalanceamount',
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
            $context['page']
        );
        $pageRows = collect($pageData['items']);

        return Inertia::render('reports/transaction-report/PaymentSummary', [
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
            'payment-summary-' . now()->format('Ymd_His') . '.xls',
            array_keys(self::EXPORT_COLUMNS),
            $exportRows,
            'Payment Summary'
        );
    }

    public function exportPdf(Request $request): View
    {
        $context = $this->resolveContext($request, false);
        $rows = $this->loadAllRows($context['filters'], $context['sort_by'], $context['sort_dir']);

        return view('reports.payment-summary-pdf', [
            'rows' => $rows,
            'totals' => $this->totals($rows),
            'selectedFilters' => $this->selectedFilterLabels($context['filters'], $context['scope']),
            'amountPrecision' => AmountPrecision::get(),
        ]);
    }

    private function resolveContext(Request $request, bool $withPagination): array
    {
        $user = $request->user();
        abort_unless($user?->hasFormPermission('payment summary'), 403);

        $rules = [
            'transaction_date_from' => ['nullable', 'date'],
            'transaction_date_to' => ['nullable', 'date'],
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
        $today = now()->toDateString();

        $filters = [
            'transaction_date_from' => $validated['transaction_date_from'] ?? $today,
            'transaction_date_to' => $validated['transaction_date_to'] ?? null,
            'cmpycode' => $this->nullableInt($validated['cmpycode'] ?? null),
            'regionmstcode' => $this->nullableInt($validated['regionmstcode'] ?? null),
            'depotcode' => $this->nullableInt($validated['depotcode'] ?? null),
            'areacode' => $this->nullableInt($validated['areacode'] ?? null),
            'subareacode' => $this->nullableInt($validated['subareacode'] ?? null),
            'routecode' => $this->nullableInt($validated['routecode'] ?? null),
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

        $requestedSortBy = $validated['sort_by'] ?? 'routecode';

        return [
            'filters' => $filters,
            'scope' => $scope,
            'sort_by' => in_array($requestedSortBy, self::SORT_COLUMNS, true) ? $requestedSortBy : 'routecode',
            'sort_dir' => $validated['sort_dir'] ?? 'asc',
            'page' => max((int) ($validated['page'] ?? 1), 1),
        ];
    }

    private function loadAllRows(array $filters, string $sortBy, string $sortDir): Collection
    {
        if (!$this->hasTables(['invoiceheader', 'customermaster', 'routemaster', 'salesman', 'cashcheckdetail', 'ardetail'])) {
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

                foreach (['routecode', 'type_sort', 'transactiondate_sort', 'invoicenumber_sort'] as $fallback) {
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
        int $page
    ): array {
        if (!$this->hasTables(['invoiceheader', 'customermaster', 'routemaster', 'salesman', 'cashcheckdetail', 'ardetail'])) {
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
        $ih = $this->qualifiedAlias('ih');
        $cm = $this->qualifiedAlias('cm');
        $ho = $this->qualifiedAlias('ho');
        $rm = $this->qualifiedAlias('rm');
        $sm = $this->qualifiedAlias('sm');
        $immediate = $this->qualifiedAlias('immediate_payment_summary');
        $outstanding = $this->qualifiedAlias('outstanding_balance_summary');
        $arPayment = $this->qualifiedAlias('ar_payment_summary');

        $customerCodeExpression = $this->hasColumn('customermaster', 'reportcustcode')
            ? "{$cm}.reportcustcode"
            : "{$cm}.alternatecode";
        $headOfficeCodeExpression = $this->hasColumn('customermaster', 'reportcustcode')
            ? "{$ho}.reportcustcode"
            : "{$ho}.alternatecode";
        $transactionDateColumn = $this->hasColumn('invoiceheader', 'actualtransactiondate')
            ? "{$ih}.actualtransactiondate"
            : "{$ih}.transactiondate";
        $paymentTypeColumn = $this->hasColumn('invoiceheader', 'paymenttype')
            ? "{$ih}.paymenttype"
            : ($this->hasColumn('customermaster', 'invoicepaymentterms') ? "{$cm}.invoicepaymentterms" : '0');

        $query = DB::table('invoiceheader as ih')
            ->join('customermaster as cm', 'cm.customercode', '=', 'ih.customercode')
            ->leftJoin('customermaster as ho', 'ho.customercode', '=', 'cm.headofficecode')
            ->join('routemaster as rm', 'rm.routecode', '=', 'ih.routecode')
            ->join('salesman as sm', 'sm.salesmancode', '=', 'ih.salesmancode')
            ->where('ih.voidflag', 0)
            ->when(
                $filters['transaction_date_from'],
                fn ($builder, $date) => $builder->whereDate(DB::raw($transactionDateColumn), '>=', $date)
            )
            ->when(
                $filters['transaction_date_to'],
                fn ($builder, $date) => $builder->whereDate(DB::raw($transactionDateColumn), '<=', $date)
            )
            ->when(
                $filters['routecodes'] !== [],
                fn ($builder) => $builder->whereIn('ih.routecode', $filters['routecodes'])
            )
            ->selectRaw("
                {$ih}.routekey,
                {$ih}.visitkey,
                {$rm}.routecode,
                {$rm}.routename,
                {$rm}.arbroutename,
                DATE_FORMAT({$transactionDateColumn}, '%d-%b-%Y') as transactiondate,
                DATE_FORMAT({$transactionDateColumn}, '%Y-%m-%d') as transactiondate_sort,
                CAST({$ih}.transactiontime AS CHAR) as transactiontime,
                CAST({$ih}.transactiontime AS CHAR) as transactiontime_sort,
                {$ih}.salesmancode,
                {$sm}.salesmanname1,
                {$sm}.arbsalesmanname1,
                {$customerCodeExpression} as customercode,
                CAST({$customerCodeExpression} AS CHAR) as customercode_sort,
                {$cm}.customername,
                {$cm}.arbcustomername,
                {$cm}.customername as customername_sort,
                {$ih}.invoicenumber,
                CAST({$ih}.invoicenumber AS CHAR) as invoicenumber_sort,
                CASE
                    WHEN {$paymentTypeColumn} < 2 THEN 'CASH'
                    WHEN {$paymentTypeColumn} = 2 THEN 'CREDIT'
                    ELSE 'TC'
                END as mop,
                CASE
                    WHEN {$cm}.type = 1 THEN '0'
                    WHEN {$cm}.type = 2 THEN COALESCE({$headOfficeCodeExpression}, '0')
                    ELSE '1'
                END as hocode,
                CASE
                    WHEN {$cm}.type = 1 THEN 'NORMAL CUSTOMERS'
                    WHEN {$cm}.type = 2 THEN COALESCE({$ho}.customername, '-')
                    ELSE '-'
                END as honame,
                CASE
                    WHEN {$cm}.type = 1 THEN 'العملاء العاديون'
                    WHEN {$cm}.type = 2 THEN COALESCE({$ho}.arbcustomername, {$ho}.customername, '-')
                    ELSE '-'
                END as arbhoname,
                CASE
                    WHEN {$cm}.type = 1 THEN '0'
                    WHEN {$cm}.type = 2 THEN COALESCE({$headOfficeCodeExpression}, '0')
                    ELSE '1'
                END as type_sort,
                COALESCE({$ih}.totalinvoiceamount, 0) as totalinvoiceamount,
                COALESCE({$immediate}.immediatecash, 0) as immediatecash,
                COALESCE({$immediate}.immediatecheck, 0) as immediatecheck,
                COALESCE({$outstanding}.outstandingbalanceamount, 0) as outstandingbalanceamount,
                COALESCE({$arPayment}.arcash, 0) as arcash,
                COALESCE({$arPayment}.archeck, 0) as archeck
            ");

        if ($immediatePaymentSummary = $this->immediatePaymentSummaryQuery()) {
            $query->leftJoinSub($immediatePaymentSummary, 'immediate_payment_summary', function ($join) {
                $join->on('immediate_payment_summary.routekey', '=', 'ih.routekey')
                    ->on('immediate_payment_summary.visitkey', '=', 'ih.visitkey');
            });
        }

        if ($outstandingBalanceSummary = $this->outstandingBalanceSummaryQuery()) {
            $query->leftJoinSub($outstandingBalanceSummary, 'outstanding_balance_summary', function ($join) {
                $join->on('outstanding_balance_summary.alternateinvoicenumber', '=', DB::raw('CAST(' . $this->qualifiedAlias('ih') . '.invoicenumber AS CHAR)'));
            });
        }

        if ($arPaymentSummary = $this->arPaymentSummaryQuery()) {
            $query->leftJoinSub($arPaymentSummary, 'ar_payment_summary', function ($join) {
                $join->on('ar_payment_summary.invoicenumber', '=', 'ih.invoicenumber');
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
        $customerName = $isArabic
            ? ($row['arbcustomername'] ?? $row['customername'] ?? '')
            : ($row['customername'] ?? $row['arbcustomername'] ?? '');
        $typeName = $isArabic
            ? ($row['arbhoname'] ?? $row['honame'] ?? '')
            : ($row['honame'] ?? $row['arbhoname'] ?? '');

        return [
            'routekey' => (int) ($row['routekey'] ?? 0),
            'visitkey' => (int) ($row['visitkey'] ?? 0),
            'routecode' => (int) ($row['routecode'] ?? 0),
            'route_label' => trim(((int) ($row['routecode'] ?? 0)) . ' - ' . $routeName),
            'type_label' => trim($this->identifier($row['hocode'] ?? '') . ' - ' . $typeName, ' -'),
            'type_sort' => (string) ($row['type_sort'] ?? ''),
            'transactiondate' => (string) ($row['transactiondate'] ?? ''),
            'transactiondate_sort' => (string) ($row['transactiondate_sort'] ?? ''),
            'transactiontime' => (string) ($row['transactiontime'] ?? ''),
            'transactiontime_sort' => (string) ($row['transactiontime_sort'] ?? ''),
            'salesmancode' => $this->identifier($row['salesmancode'] ?? ''),
            'customercode' => $this->identifier($row['customercode'] ?? ''),
            'customercode_sort' => (string) ($row['customercode_sort'] ?? ''),
            'customername_sort' => (string) ($row['customername_sort'] ?? ''),
            'customer_label' => trim($this->identifier($row['customercode'] ?? '') . ' - ' . $customerName),
            'invoicenumber' => $this->identifier($row['invoicenumber'] ?? ''),
            'invoicenumber_sort' => (string) ($row['invoicenumber_sort'] ?? ''),
            'mop' => (string) ($row['mop'] ?? ''),
            'totalinvoiceamount' => (float) ($row['totalinvoiceamount'] ?? 0),
            'immediatecash' => (float) ($row['immediatecash'] ?? 0),
            'immediatecheck' => (float) ($row['immediatecheck'] ?? 0),
            'arcash' => (float) ($row['arcash'] ?? 0),
            'archeck' => (float) ($row['archeck'] ?? 0),
            'outstandingbalanceamount' => (float) ($row['outstandingbalanceamount'] ?? 0),
        ];
    }

    private function totals(Collection $rows): array
    {
        return [
            'totalinvoiceamount' => (float) $rows->sum('totalinvoiceamount'),
            'immediatecash' => (float) $rows->sum('immediatecash'),
            'immediatecheck' => (float) $rows->sum('immediatecheck'),
            'arcash' => (float) $rows->sum('arcash'),
            'archeck' => (float) $rows->sum('archeck'),
            'outstandingbalanceamount' => (float) $rows->sum('outstandingbalanceamount'),
        ];
    }

    private function mapExportRow(array $row): array
    {
        $export = [];

        foreach (self::EXPORT_COLUMNS as $label => $key) {
            $value = $row[$key] ?? '';
            if (in_array($key, ['totalinvoiceamount', 'immediatecash', 'immediatecheck', 'arcash', 'archeck', 'outstandingbalanceamount'], true)) {
                $value = AmountPrecision::format($value);
            }
            $export[$label] = $value;
        }

        return $export;
    }

    private function totalsExportRow(array $totals): array
    {
        $row = array_fill_keys(array_keys(self::EXPORT_COLUMNS), '');
        $row['MOP'] = 'Total';
        $row['Total Invoice Amount'] = AmountPrecision::format($totals['totalinvoiceamount']);
        $row['Immediate CASH Payment'] = AmountPrecision::format($totals['immediatecash']);
        $row['Immediate Cheque Payment'] = AmountPrecision::format($totals['immediatecheck']);
        $row['AR CASH Collection'] = AmountPrecision::format($totals['arcash']);
        $row['AR Cheque Collection'] = AmountPrecision::format($totals['archeck']);
        $row['Outstanding Balance Amount'] = AmountPrecision::format($totals['outstandingbalanceamount']);

        return $row;
    }

    private function selectedFilterLabels(array $filters, array $scope): array
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
            'routecode' => $query->orderBy('routecode', $direction),
            'type_sort' => $query->orderBy('type_sort', $direction),
            'transactiondate_sort' => $query->orderBy('transactiondate_sort', $direction),
            'transactiontime_sort' => $query->orderBy('transactiontime_sort', $direction),
            'salesmancode' => $query->orderBy('salesmancode', $direction),
            'customercode_sort' => $query->orderBy('customercode_sort', $direction),
            'customername_sort' => $query->orderBy('customername_sort', $direction),
            'invoicenumber_sort' => $query->orderBy('invoicenumber_sort', $direction),
            'mop' => $query->orderBy('mop', $direction),
            'totalinvoiceamount' => $query->orderBy('totalinvoiceamount', $direction),
            'immediatecash' => $query->orderBy('immediatecash', $direction),
            'immediatecheck' => $query->orderBy('immediatecheck', $direction),
            'arcash' => $query->orderBy('arcash', $direction),
            'archeck' => $query->orderBy('archeck', $direction),
            'outstandingbalanceamount' => $query->orderBy('outstandingbalanceamount', $direction),
            default => $query->orderBy('routecode'),
        };

        return $sortedQuery
            ->orderBy('routecode')
            ->orderBy('type_sort')
            ->orderBy('transactiondate_sort')
            ->orderBy('invoicenumber_sort');
    }

    private function immediatePaymentSummaryQuery()
    {
        if (!$this->hasTables(['cashcheckdetail'])) {
            return null;
        }

        $ccd = $this->qualifiedAlias('ccd');

        return DB::table('cashcheckdetail as ccd')
            ->selectRaw("
                {$ccd}.routekey,
                {$ccd}.visitkey,
                SUM(CASE WHEN {$ccd}.typecode = 0 THEN COALESCE({$ccd}.amount, 0) ELSE 0 END) as immediatecash,
                SUM(CASE WHEN {$ccd}.typecode = 1 THEN COALESCE({$ccd}.amount, 0) ELSE 0 END) as immediatecheck
            ")
            ->groupBy(DB::raw("{$ccd}.routekey"), DB::raw("{$ccd}.visitkey"));
    }

    private function outstandingBalanceSummaryQuery()
    {
        if (!$this->hasTables(['ardetail']) || !$this->hasColumn('ardetail', 'alternateinvoicenumber')) {
            return null;
        }

        $ard = $this->qualifiedAlias('ard');

        return DB::table('ardetail as ard')
            ->selectRaw("
                {$ard}.alternateinvoicenumber,
                SUM(COALESCE({$ard}.amountpaid, 0)) as outstandingbalanceamount
            ")
            ->whereNotNull('ard.alternateinvoicenumber')
            ->where('ard.alternateinvoicenumber', '<>', '')
            ->groupBy(DB::raw("{$ard}.alternateinvoicenumber"));
    }

    private function arPaymentSummaryQuery()
    {
        if (!$this->hasTables(['ardetail', 'cashcheckdetail'])) {
            return null;
        }

        $ard = $this->qualifiedAlias('ard');
        $ccd = $this->qualifiedAlias('ccd');

        return DB::table('ardetail as ard')
            ->join('cashcheckdetail as ccd', function ($join) {
                $join->on('ccd.routekey', '=', 'ard.routekey')
                    ->on('ccd.visitkey', '=', 'ard.visitkey');
            })
            ->selectRaw("
                {$ard}.invoicenumber,
                SUM(CASE WHEN {$ccd}.typecode = 0 THEN COALESCE({$ard}.amountpaid, 0) ELSE 0 END) as arcash,
                SUM(CASE WHEN {$ccd}.typecode = 1 THEN COALESCE({$ard}.amountpaid, 0) ELSE 0 END) as archeck
            ")
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

        return strcasecmp((string) $left, (string) $right);
    }

    private function identifier(mixed $value): string
    {
        if ($value === null || $value === '') {
            return '';
        }

        return trim((string) $value);
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
