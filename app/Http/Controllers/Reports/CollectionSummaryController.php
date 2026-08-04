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

class CollectionSummaryController extends Controller
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
        $rows = $this->loadAllRows($context['filters'], $context['sort_by'], $context['sort_dir']);
        $paginator = $this->paginateRows($rows, $context['filters']['per_page'], $context['page'], $request);

        return Inertia::render('reports/transaction-report/CollectionSummary', [
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
            'collection-summary-' . now()->format('Ymd_His') . '.xls',
            array_keys(self::EXPORT_COLUMNS),
            $exportRows,
            'Collection Summary'
        );
    }

    public function exportPdf(Request $request): View
    {
        $context = $this->resolveContext($request, false);
        $rows = $this->loadAllRows($context['filters'], $context['sort_by'], $context['sort_dir']);

        return view('reports.collection-summary-pdf', [
            'rows' => $rows,
            'totals' => $this->totals($rows),
            'selectedFilters' => $this->selectedFilterLabels($context['filters'], $context['scope']),
            'amountPrecision' => AmountPrecision::get(),
        ]);
    }

    private function resolveContext(Request $request, bool $withPagination): array
    {
        $user = $request->user();
        abort_unless($user?->hasFormPermission('collection summary'), 403);

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

    private function baseQuery(array $filters)
    {
        $arh = $this->qualifiedAlias('arh');
        $cm = $this->qualifiedAlias('cm');
        $rm = $this->qualifiedAlias('rm');
        $ard = $this->qualifiedAlias('ard');
        $ccd = $this->qualifiedAlias('ccd');

        $customerCodeColumn = $this->hasColumn('customermaster', 'reportcustcode')
            ? "{$cm}.reportcustcode"
            : "{$cm}.alternatecode";
        $pdcBalanceExpression = $this->hasColumn('ardetail', 'pdcbalance')
            ? "COALESCE({$ard}.pdcbalance, 0)"
            : '0';
        $invoicedByExpression = $this->hasTables(['customerinvoice']) && $this->hasColumn('customerinvoice', 'erpreferencenumber')
            ? "COALESCE((SELECT ci.salesmancode FROM " . DB::getTablePrefix() . "customerinvoice ci WHERE ci.erpreferencenumber = {$ard}.alternateinvoicenumber AND {$ard}.alternateinvoicenumber != '' AND {$ard}.alternateinvoicenumber IS NOT NULL LIMIT 1), 0)"
            : '0';

        return DB::table('arheader as arh')
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

    private function identifier(mixed $value): string
    {
        if ($value === null || $value === '') {
            return '';
        }

        return (string) $value;
    }

    private function qualifiedAlias(string $alias): string
    {
        return DB::getTablePrefix() . $alias;
    }
}
