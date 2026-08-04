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

class SalesSummaryController extends Controller
{
    private const SORT_COLUMNS = [
        'routecode',
        'transactiondate_sort',
        'transactiontime_sort',
        'invoicenumber_sort',
        'salesmancode',
        'customercode_sort',
        'customername_sort',
        'mop',
        'salesamount',
        'salescase',
        'salespcs',
        'goodreturnamount',
        'returncase',
        'returnpcs',
        'totaldamagedamount',
        'damagedcase',
        'damagedpcs',
        'freeamount',
        'freecase',
        'freepcs',
        'invoiceamount',
        'discountamount1',
        'netamount',
        'immediatepaid',
        'invoicebalance',
    ];

    private const EXPORT_COLUMNS = [
        'Route Code' => 'route_label',
        'Transaction Date' => 'transactiondate',
        'Transaction Time' => 'transactiontime',
        'Invoice Number' => 'invoicenumber',
        'Salesman Code' => 'salesmancode',
        'Customer Code' => 'customercode',
        'Customer Name' => 'customer_label',
        'Payment Type' => 'mop',
        'Sales Amount' => 'salesamount',
        'Sales Qty Cases' => 'salescase',
        'Sales Qty Pcs' => 'salespcs',
        'Good Return Amount' => 'goodreturnamount',
        'Good Return Qty Cases' => 'returncase',
        'Good Return Qty Pcs' => 'returnpcs',
        'Bad Return Amount' => 'totaldamagedamount',
        'Bad Return Qty Cases' => 'damagedcase',
        'Bad Return Qty Pcs' => 'damagedpcs',
        'Free Amount' => 'freeamount',
        'Free Qty Cases' => 'freecase',
        'Free Qty Pcs' => 'freepcs',
        'Invoice Amount' => 'invoiceamount',
        'Discount Amount' => 'discountamount1',
        'Net Amount' => 'netamount',
        'Immediate Paid' => 'immediatepaid',
        'Invoice Balance' => 'invoicebalance',
    ];

    public function __construct(
        private readonly ReportScopeService $reportScopeService
    ) {}

    public function __invoke(Request $request): Response
    {
        $context = $this->resolveContext($request, true);
        $rows = $this->loadAllRows($context['filters'], $context['sort_by'], $context['sort_dir']);
        $paginator = $this->paginateRows($rows, $context['filters']['per_page'], $context['page'], $request);

        return Inertia::render('reports/transaction-report/SalesSummary', [
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
            'sales-summary-' . now()->format('Ymd_His') . '.xls',
            array_keys(self::EXPORT_COLUMNS),
            $exportRows,
            'Sales Summary'
        );
    }

    public function exportPdf(Request $request): View
    {
        $context = $this->resolveContext($request, false);
        $rows = $this->loadAllRows($context['filters'], $context['sort_by'], $context['sort_dir']);

        return view('reports.sales-summary-pdf', [
            'rows' => $rows,
            'totals' => $this->totals($rows),
            'selectedFilters' => $this->selectedFilterLabels($context['filters'], $context['scope']),
            'amountPrecision' => AmountPrecision::get(),
        ]);
    }

    private function resolveContext(Request $request, bool $withPagination): array
    {
        $user = $request->user();
        abort_unless($user?->hasFormPermission('sales summary'), 403);

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
        if (!$this->hasTables(['invoiceheader', 'invoicedetail', 'itemmaster', 'customermaster', 'routemaster', 'salesman'])) {
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

                foreach (['routecode', 'transactiondate_sort', 'transactiontime_sort', 'invoicenumber_sort'] as $fallback) {
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
        $ih = $this->qualifiedAlias('ih');
        $id = $this->qualifiedAlias('id');
        $im = $this->qualifiedAlias('im');
        $cm = $this->qualifiedAlias('cm');
        $rm = $this->qualifiedAlias('rm');
        $sm = $this->qualifiedAlias('sm');

        return DB::table('invoiceheader as ih')
            ->join('invoicedetail as id', function ($join) {
                $join->on('ih.routekey', '=', 'id.routekey')
                    ->on('ih.visitkey', '=', 'id.visitkey');
            })
            ->join('itemmaster as im', 'id.itemcode', '=', 'im.actualitemcode')
            ->join('customermaster as cm', 'cm.customercode', '=', 'ih.customercode')
            ->join('routemaster as rm', 'rm.routecode', '=', 'ih.routecode')
            ->join('salesman as sm', 'sm.salesmancode', '=', 'ih.salesmancode')
            ->where('ih.voidflag', 0)
            ->when(
                $filters['transaction_date_from'],
                fn ($builder, $date) => $builder->whereDate('ih.actualtransactiondate', '>=', $date)
            )
            ->when(
                $filters['transaction_date_to'],
                fn ($builder, $date) => $builder->whereDate('ih.actualtransactiondate', '<=', $date)
            )
            ->when(
                $filters['routecodes'] !== [],
                fn ($builder) => $builder->whereIn('ih.routecode', $filters['routecodes'])
            )
            ->groupBy([
                'ih.routekey',
                'ih.visitkey',
                'ih.actualtransactiondate',
                'ih.transactiontime',
                'ih.invoicenumber',
                'cm.invoicepaymentterms',
                'cm.alternatecode',
                'cm.customername',
                'cm.arbcustomername',
                'rm.routecode',
                'rm.routename',
                'rm.arbroutename',
                'ih.salesmancode',
                'sm.salesmanname1',
                'sm.arbsalesmanname1',
                'ih.totalsalesamount',
                'ih.totalreturnamount',
                'ih.totaldamagedamount',
                'ih.totalexpiryamount',
                'ih.totalfreesampleamount',
                'ih.totalmanualfree',
                'ih.totalpromoamount',
                'ih.totaldiscountamount',
                'ih.totalinvoiceamount',
                'ih.immediatepaid',
                'ih.amountpaid',
                'ih.invoicebalance',
            ])
            ->selectRaw("
                {$ih}.routekey,
                {$ih}.visitkey,
                DATE_FORMAT({$ih}.actualtransactiondate, '%d-%b-%Y') as transactiondate,
                DATE_FORMAT({$ih}.actualtransactiondate, '%Y-%m-%d') as transactiondate_sort,
                CAST({$ih}.transactiontime AS CHAR) as transactiontime,
                CAST({$ih}.transactiontime AS CHAR) as transactiontime_sort,
                {$ih}.invoicenumber,
                CAST({$ih}.invoicenumber AS CHAR) as invoicenumber_sort,
                CASE
                    WHEN {$cm}.invoicepaymentterms < 2 THEN 'CASH'
                    WHEN {$cm}.invoicepaymentterms = 2 THEN 'CREDIT'
                    ELSE 'TC'
                END as mop,
                {$cm}.alternatecode as customercode,
                {$cm}.customername,
                {$cm}.arbcustomername,
                {$rm}.routecode,
                {$rm}.routename,
                {$rm}.arbroutename,
                {$ih}.salesmancode,
                {$sm}.salesmanname1 as salesman,
                {$sm}.arbsalesmanname1,
                COALESCE({$ih}.totalsalesamount, 0) as salesamount,
                -COALESCE({$ih}.totalreturnamount, 0) as goodreturnamount,
                COALESCE({$ih}.totaldamagedamount, 0) + COALESCE({$ih}.totalexpiryamount, 0) as totaldamagedamount,
                COALESCE({$ih}.totalexpiryamount, 0) as totalexpiryamount,
                COALESCE({$ih}.totalfreesampleamount, 0) + COALESCE({$ih}.totalmanualfree, 0) as freeamount,
                COALESCE({$ih}.totaldiscountamount, 0) + COALESCE({$ih}.totalpromoamount, 0) as discountamount1,
                COALESCE({$ih}.totalinvoiceamount, 0) as invoiceamount,
                COALESCE({$ih}.immediatepaid, 0) as immediatepaid,
                COALESCE({$ih}.amountpaid, 0) as amountpaid,
                COALESCE({$ih}.invoicebalance, 0) as invoicebalance,
                SUM(FLOOR(COALESCE({$id}.salesqty, 0) / NULLIF({$im}.unitspercase, 0))) as salescase,
                SUM(MOD(COALESCE({$id}.salesqty, 0), NULLIF({$im}.unitspercase, 0))) as salespcs,
                SUM(FLOOR(COALESCE({$id}.returnqty, 0) / NULLIF({$im}.unitspercase, 0))) as returncase,
                SUM(MOD(COALESCE({$id}.returnqty, 0), NULLIF({$im}.unitspercase, 0))) as returnpcs,
                SUM(FLOOR(COALESCE({$id}.damagedqty, 0) / NULLIF({$im}.unitspercase, 0))) + SUM(FLOOR(COALESCE({$id}.expiryqty, 0) / NULLIF({$im}.unitspercase, 0))) as damagedcase,
                SUM(MOD(COALESCE({$id}.damagedqty, 0), NULLIF({$im}.unitspercase, 0))) + SUM(MOD(COALESCE({$id}.expiryqty, 0), NULLIF({$im}.unitspercase, 0))) as damagedpcs,
                SUM(FLOOR((COALESCE({$id}.freesampleqty, 0) + COALESCE({$id}.manualfreeqty, 0)) / NULLIF({$im}.unitspercase, 0))) as freecase,
                SUM(MOD((COALESCE({$id}.freesampleqty, 0) + COALESCE({$id}.manualfreeqty, 0)), NULLIF({$im}.unitspercase, 0))) as freepcs
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

        $invoiceAmount = (float) ($row['invoiceamount'] ?? 0);
        $discountAmount = (float) ($row['discountamount1'] ?? 0);

        return [
            'routekey' => (int) ($row['routekey'] ?? 0),
            'visitkey' => (int) ($row['visitkey'] ?? 0),
            'routecode' => (int) ($row['routecode'] ?? 0),
            'route_label' => trim(((int) ($row['routecode'] ?? 0)) . ' - ' . $routeName),
            'transactiondate' => (string) ($row['transactiondate'] ?? ''),
            'transactiondate_sort' => (string) ($row['transactiondate_sort'] ?? ''),
            'transactiontime' => $this->formatTime($row['transactiontime'] ?? null),
            'transactiontime_sort' => (string) ($row['transactiontime_sort'] ?? ''),
            'invoicenumber' => $this->identifier($row['invoicenumber'] ?? ''),
            'invoicenumber_sort' => (string) ($row['invoicenumber_sort'] ?? ''),
            'salesmancode' => $this->identifier($row['salesmancode'] ?? ''),
            'customercode' => $this->identifier($row['customercode'] ?? ''),
            'customercode_sort' => (string) ($row['customercode'] ?? ''),
            'customer_label' => $customerName,
            'customername_sort' => mb_strtolower($customerName),
            'mop' => (string) ($row['mop'] ?? ''),
            'salesamount' => (float) ($row['salesamount'] ?? 0),
            'salescase' => (float) ($row['salescase'] ?? 0),
            'salespcs' => (float) ($row['salespcs'] ?? 0),
            'goodreturnamount' => (float) ($row['goodreturnamount'] ?? 0),
            'returncase' => (float) ($row['returncase'] ?? 0),
            'returnpcs' => (float) ($row['returnpcs'] ?? 0),
            'totaldamagedamount' => (float) ($row['totaldamagedamount'] ?? 0),
            'damagedcase' => (float) ($row['damagedcase'] ?? 0),
            'damagedpcs' => (float) ($row['damagedpcs'] ?? 0),
            'freeamount' => (float) ($row['freeamount'] ?? 0),
            'freecase' => (float) ($row['freecase'] ?? 0),
            'freepcs' => (float) ($row['freepcs'] ?? 0),
            'invoiceamount' => $invoiceAmount,
            'discountamount1' => $discountAmount,
            'netamount' => $invoiceAmount - $discountAmount,
            'immediatepaid' => (float) ($row['immediatepaid'] ?? 0),
            'invoicebalance' => (float) ($row['invoicebalance'] ?? 0),
        ];
    }

    private function totals(Collection $rows): array
    {
        $keys = [
            'salesamount',
            'salescase',
            'salespcs',
            'goodreturnamount',
            'returncase',
            'returnpcs',
            'totaldamagedamount',
            'damagedcase',
            'damagedpcs',
            'freeamount',
            'freecase',
            'freepcs',
            'invoiceamount',
            'discountamount1',
            'netamount',
            'immediatepaid',
            'invoicebalance',
        ];

        $totals = [];
        foreach ($keys as $key) {
            $totals[$key] = (float) $rows->sum($key);
        }

        return $totals;
    }

    private function mapExportRow(array $row): array
    {
        $export = [];

        foreach (self::EXPORT_COLUMNS as $label => $key) {
            $value = $row[$key] ?? '';
            if (in_array($key, [
                'salesamount', 'salescase', 'salespcs', 'goodreturnamount', 'returncase', 'returnpcs',
                'totaldamagedamount', 'damagedcase', 'damagedpcs', 'freeamount', 'freecase', 'freepcs',
                'invoiceamount', 'discountamount1', 'netamount', 'immediatepaid', 'invoicebalance',
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
        $row['Payment Type'] = 'Total';

        foreach (self::EXPORT_COLUMNS as $label => $key) {
            if (array_key_exists($key, $totals)) {
                $row[$label] = AmountPrecision::format($totals[$key]);
            }
        }

        return $row;
    }

    private function selectedFilterLabels(array $filters, array $scope): array
    {
        return [
            'Transaction Date - From' => $filters['transaction_date_from'] ?: 'All',
            'Transaction Date - To' => $filters['transaction_date_to'] ?: 'All',
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

    private function compare(mixed $left, mixed $right): int
    {
        if (is_numeric($left) && is_numeric($right)) {
            return (float) $left <=> (float) $right;
        }

        return strcasecmp((string) $left, (string) $right);
    }

    private function formatTime(mixed $value): string
    {
        if ($value === null || $value === '') {
            return '';
        }

        try {
            return \Carbon\Carbon::createFromFormat('H:i:s', substr((string) $value, 0, 8))->format('H:i');
        } catch (\Throwable) {
            try {
                return \Carbon\Carbon::parse((string) $value)->format('H:i');
            } catch (\Throwable) {
                return (string) $value;
            }
        }
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
}
