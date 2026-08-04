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

class FinalDepositController extends Controller
{
    private const SORT_COLUMNS = [
        'routecode',
        'documentnumber_sort',
        'transactiondate_sort',
        'customercode_sort',
        'arcash',
        'archeck',
        'totalinvoiceamount',
        'alternateinvoicenumber_sort',
        'invoicedby_sort',
        'invoicedate_sort',
        'amountpaid',
    ];

    private const EXPORT_COLUMNS = [
        'Route' => 'route_label',
        'Receipt Number' => 'documentnumber',
        'Transaction Date' => 'transactiondate',
        'Customer' => 'customer_label',
        'Amount Paid in CASH' => 'arcash',
        'Amount Paid in CHEQUE' => 'archeck',
        'Total Amount' => 'totalinvoiceamount',
        'Against Invoice' => 'alternateinvoicenumber',
        'Invoiced By' => 'invoicedby',
        'Invoiced Date' => 'invoicedate',
        'Paid Amount' => 'amountpaid',
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

        return Inertia::render('reports/transaction-report/FinalDeposit', [
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
            'final-deposit-' . now()->format('Ymd_His') . '.xls',
            array_keys(self::EXPORT_COLUMNS),
            $exportRows,
            'Final Deposit'
        );
    }

    public function exportPdf(Request $request): View
    {
        $context = $this->resolveContext($request, false);
        $rows = $this->loadAllRows($context['filters'], $context['sort_by'], $context['sort_dir']);

        return view('reports.final-deposit-pdf', [
            'rows' => $rows,
            'totals' => $this->totals($rows),
            'selectedFilters' => $this->selectedFilterLabels($context['filters'], $context['scope']),
            'amountPrecision' => AmountPrecision::get(),
        ]);
    }

    private function resolveContext(Request $request, bool $withPagination): array
    {
        $user = $request->user();
        abort_unless($user?->hasFormPermission('final deposit'), 403);

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
            'transaction_date_to' => $validated['transaction_date_to'] ?? $today,
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
        if (!$this->hasTables(['arheader', 'ardetail', 'cashcheckdetail', 'customermaster', 'routemaster'])) {
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

                foreach (['routecode', 'documentnumber_sort', 'transactiondate_sort'] as $fallback) {
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
        if (!$this->hasTables(['arheader', 'ardetail', 'cashcheckdetail', 'customermaster', 'routemaster'])) {
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
        $arh = $this->qualifiedAlias('arh');
        $cm = $this->qualifiedAlias('cm');
        $rm = $this->qualifiedAlias('rm');
        $ard = $this->qualifiedAlias('ard');
        $ccd = $this->qualifiedAlias('ccd');
        $invoiceRef = $this->qualifiedAlias('invoice_reference_summary');

        $customerCodeColumn = $this->hasColumn('customermaster', 'reportcustcode')
            ? "{$cm}.reportcustcode"
            : "{$cm}.alternatecode";
        $pdcBalanceExpression = $this->hasColumn('ardetail', 'pdcbalance')
            ? "COALESCE({$ard}.pdcbalance, 0)"
            : '0';
        $invoicedByExpression = $this->hasTables(['customerinvoice']) && $this->hasColumn('customerinvoice', 'erpreferencenumber')
            ? "COALESCE({$invoiceRef}.salesmancode, 0)"
            : '0';

        $query = DB::table('arheader as arh')
            ->join('customermaster as cm', 'arh.customercode', '=', 'cm.customercode')
            ->join('routemaster as rm', 'arh.routecode', '=', 'rm.routecode')
            ->join('ardetail as ard', function ($join) {
                $join->on('ard.routekey', '=', 'arh.routekey')
                    ->on('ard.visitkey', '=', 'arh.visitkey')
                    ->on('ard.transactionkey', '=', 'arh.transactionkey');
            })
            ->join('cashcheckdetail as ccd', function ($join) {
                $join->on('ccd.routekey', '=', 'arh.routekey')
                    ->on('ccd.visitkey', '=', 'arh.visitkey');
            })
            ->where('arh.voidflag', 0)
            ->whereRaw("COALESCE({$ard}.chequestatusindicator, 0) = 0")
            ->whereRaw("COALESCE({$ard}.amountpaid, 0) + {$pdcBalanceExpression} <> 0")
            ->when(
                $filters['transaction_date_from'],
                fn ($builder, $date) => $builder->whereDate('arh.transactiondate', '>=', $date)
            )
            ->when(
                $filters['transaction_date_to'],
                fn ($builder, $date) => $builder->whereDate('arh.transactiondate', '<=', $date)
            )
            ->when(
                $filters['routecodes'] !== [],
                fn ($builder) => $builder->whereIn('arh.routecode', $filters['routecodes'])
            )
            ->selectRaw("
                DATE({$arh}.transactiondate) as transactiondate_sort,
                DATE_FORMAT({$arh}.transactiondate, '%d-%b-%Y') as transactiondate,
                {$arh}.transactionkey,
                {$arh}.invoicenumber as documentnumber,
                CAST({$arh}.invoicenumber AS CHAR) as documentnumber_sort,
                {$customerCodeColumn} as customercode,
                CAST({$customerCodeColumn} AS CHAR) as customercode_sort,
                {$cm}.customername,
                {$cm}.arbcustomername,
                {$arh}.routecode,
                {$rm}.routename,
                {$rm}.arbroutename,
                COALESCE({$arh}.totalinvoiceamount, 0) as totalinvoiceamount,
                COALESCE({$ard}.alternateinvoicenumber, '') as alternateinvoicenumber,
                CAST(COALESCE({$ard}.alternateinvoicenumber, '') AS CHAR) as alternateinvoicenumber_sort,
                DATE({$ard}.invoicedate) as invoicedate_sort,
                DATE_FORMAT({$ard}.invoicedate, '%d-%b-%Y') as invoicedate,
                {$invoicedByExpression} as invoicedby,
                CAST({$invoicedByExpression} AS CHAR) as invoicedby_sort,
                COALESCE({$ard}.amountpaid, 0) as amountpaid,
                CASE WHEN {$ccd}.typecode = 0 THEN COALESCE({$ard}.amountpaid, 0) ELSE 0 END as arcash,
                CASE WHEN {$ccd}.typecode = 1 THEN COALESCE({$ard}.amountpaid, 0) ELSE 0 END as archeck
            ");

        if ($invoiceReferenceSummary = $this->invoiceReferenceSummaryQuery()) {
            $query->leftJoinSub($invoiceReferenceSummary, 'invoice_reference_summary', function ($join) {
                $join->on('invoice_reference_summary.erpreferencenumber', '=', 'ard.alternateinvoicenumber');
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

        return [
            'transactionkey' => (int) ($row['transactionkey'] ?? 0),
            'routecode' => (int) ($row['routecode'] ?? 0),
            'route_label' => trim(((int) ($row['routecode'] ?? 0)) . ' - ' . $routeName),
            'documentnumber' => $this->identifier($row['documentnumber'] ?? ''),
            'documentnumber_sort' => (string) ($row['documentnumber_sort'] ?? ''),
            'transactiondate' => (string) ($row['transactiondate'] ?? ''),
            'transactiondate_sort' => (string) ($row['transactiondate_sort'] ?? ''),
            'customercode' => $this->identifier($row['customercode'] ?? ''),
            'customercode_sort' => (string) ($row['customercode_sort'] ?? ''),
            'customer_label' => trim($this->identifier($row['customercode'] ?? '') . ' - ' . $customerName),
            'arcash' => (float) ($row['arcash'] ?? 0),
            'archeck' => (float) ($row['archeck'] ?? 0),
            'totalinvoiceamount' => (float) ($row['totalinvoiceamount'] ?? 0),
            'alternateinvoicenumber' => $this->identifier($row['alternateinvoicenumber'] ?? ''),
            'alternateinvoicenumber_sort' => (string) ($row['alternateinvoicenumber_sort'] ?? ''),
            'invoicedby' => $this->identifier($row['invoicedby'] ?? ''),
            'invoicedby_sort' => (string) ($row['invoicedby_sort'] ?? ''),
            'invoicedate' => (string) ($row['invoicedate'] ?? ''),
            'invoicedate_sort' => (string) ($row['invoicedate_sort'] ?? ''),
            'amountpaid' => (float) ($row['amountpaid'] ?? 0),
        ];
    }

    private function totals(Collection $rows): array
    {
        return [
            'arcash' => (float) $rows->sum('arcash'),
            'archeck' => (float) $rows->sum('archeck'),
            'totalinvoiceamount' => (float) $rows->sum('totalinvoiceamount'),
        ];
    }

    private function mapExportRow(array $row): array
    {
        $export = [];

        foreach (self::EXPORT_COLUMNS as $label => $key) {
            $value = $row[$key] ?? '';
            if (in_array($key, ['arcash', 'archeck', 'totalinvoiceamount', 'amountpaid'], true)) {
                $value = AmountPrecision::format($value);
            }
            $export[$label] = $value;
        }

        return $export;
    }

    private function totalsExportRow(array $totals): array
    {
        $row = array_fill_keys(array_keys(self::EXPORT_COLUMNS), '');
        $row['Customer'] = 'Total';
        $row['Amount Paid in CASH'] = AmountPrecision::format($totals['arcash']);
        $row['Amount Paid in CHEQUE'] = AmountPrecision::format($totals['archeck']);
        $row['Total Amount'] = AmountPrecision::format($totals['totalinvoiceamount']);

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
            'documentnumber_sort' => $query->orderBy('documentnumber_sort', $direction),
            'transactiondate_sort' => $query->orderBy('transactiondate_sort', $direction),
            'customercode_sort' => $query->orderBy('customercode_sort', $direction),
            'arcash' => $query->orderBy('arcash', $direction),
            'archeck' => $query->orderBy('archeck', $direction),
            'totalinvoiceamount' => $query->orderBy('totalinvoiceamount', $direction),
            'alternateinvoicenumber_sort' => $query->orderBy('alternateinvoicenumber_sort', $direction),
            'invoicedby_sort' => $query->orderBy('invoicedby_sort', $direction),
            'invoicedate_sort' => $query->orderBy('invoicedate_sort', $direction),
            'amountpaid' => $query->orderBy('amountpaid', $direction),
            default => $query->orderBy('routecode'),
        };

        return $sortedQuery
            ->orderBy('routecode')
            ->orderBy('documentnumber_sort')
            ->orderBy('transactiondate_sort');
    }

    private function invoiceReferenceSummaryQuery()
    {
        if (!$this->hasTables(['customerinvoice']) || !$this->hasColumn('customerinvoice', 'erpreferencenumber')) {
            return null;
        }

        $ci = $this->qualifiedAlias('ci');

        return DB::table('customerinvoice as ci')
            ->selectRaw("
                {$ci}.erpreferencenumber,
                MIN({$ci}.salesmancode) as salesmancode
            ")
            ->whereNotNull('ci.erpreferencenumber')
            ->where('ci.erpreferencenumber', '<>', '')
            ->groupBy(DB::raw("{$ci}.erpreferencenumber"));
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

    private function compare(mixed $left, mixed $right): int
    {
        if (is_numeric($left) && is_numeric($right)) {
            return (float) $left <=> (float) $right;
        }

        return strcasecmp((string) $left, (string) $right);
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

    private function qualifiedAlias(string $alias): string
    {
        return DB::getTablePrefix() . $alias;
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
