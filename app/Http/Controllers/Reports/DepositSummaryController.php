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

class DepositSummaryController extends Controller
{
    private const SORT_COLUMNS = [
        'routecode',
        'transactiondate_sort',
        'customer_sort',
        'documentnumber_sort',
        'type_sort',
        'immediatecash',
        'immediatecheck',
        'total',
    ];

    private const EXPORT_COLUMNS = [
        'Route' => 'route_label',
        'Transaction Date' => 'transactiondate',
        'Customer' => 'customer_label',
        'Receipt' => 'documentnumber',
        'Type' => 'type',
        'Immediate Cash' => 'immediatecash',
        'Immediate Cheque' => 'immediatecheck',
        'Total' => 'total',
    ];

    public function __construct(
        private readonly ReportScopeService $reportScopeService
    ) {}

    public function __invoke(Request $request): Response
    {
        $context = $this->resolveContext($request, true);
        $rows = $this->loadAllRows($context['filters'], $context['sort_by'], $context['sort_dir']);
        $paginator = $this->paginateRows($rows, $context['filters']['per_page'], $context['page'], $request);

        return Inertia::render('reports/transaction-report/DepositSummary', [
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
            'deposit-summary-' . now()->format('Ymd_His') . '.xls',
            array_keys(self::EXPORT_COLUMNS),
            $exportRows,
            'Deposit Summary'
        );
    }

    public function exportPdf(Request $request): View
    {
        $context = $this->resolveContext($request, false);
        $rows = $this->loadAllRows($context['filters'], $context['sort_by'], $context['sort_dir']);

        return view('reports.deposit-summary-pdf', [
            'rows' => $rows,
            'totals' => $this->totals($rows),
            'selectedFilters' => $this->selectedFilterLabels($context['filters'], $context['scope']),
            'amountPrecision' => AmountPrecision::get(),
        ]);
    }

    private function resolveContext(Request $request, bool $withPagination): array
    {
        $user = $request->user();
        abort_unless($user?->hasFormPermission('deposit summary'), 403);

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
        if (($filters['scope_limited'] ?? false) && $filters['routecodes'] === []) {
            return collect();
        }

        $rows = collect();

        foreach ($this->queryParts($filters) as $part) {
            $rows = $rows->concat($part->get()->map(fn ($row) => (array) $row));
        }

        return $rows
            ->map(fn (array $row) => $this->transformRow($row))
            ->sort(function (array $left, array $right) use ($sortBy, $sortDir) {
                $baseCompare = $this->compare($left[$sortBy] ?? null, $right[$sortBy] ?? null);
                if ($baseCompare !== 0) {
                    return $sortDir === 'desc' ? -$baseCompare : $baseCompare;
                }

                foreach (['routecode', 'transactiondate_sort', 'customer_sort', 'documentnumber_sort'] as $fallback) {
                    $fallbackCompare = $this->compare($left[$fallback] ?? null, $right[$fallback] ?? null);
                    if ($fallbackCompare !== 0) {
                        return $fallbackCompare;
                    }
                }

                return 0;
            })
            ->values();
    }

    private function queryParts(array $filters): array
    {
        $parts = [];

        if ($this->hasTables(['invoiceheader', 'cashcheckdetail', 'customermaster', 'routemaster'])) {
            $parts[] = $this->invoiceQuery($filters);
        }

        if ($this->hasTables(['arheader', 'cashcheckdetail', 'customermaster', 'routemaster'])) {
            $parts[] = $this->receiptQuery($filters);
        }

        return $parts;
    }

    private function invoiceQuery(array $filters)
    {
        $ih = $this->qualifiedAlias('ih');
        $ccd = $this->qualifiedAlias('ccd');
        $cm = $this->qualifiedAlias('cm');
        $rm = $this->qualifiedAlias('rm');

        $customerCodeColumn = $this->hasColumn('customermaster', 'reportcustcode')
            ? "{$cm}.reportcustcode"
            : "{$cm}.alternatecode";

        return DB::table('invoiceheader as ih')
            ->join('cashcheckdetail as ccd', function ($join) {
                $join->on('ccd.routekey', '=', 'ih.routekey')
                    ->on('ccd.visitkey', '=', 'ih.visitkey');
            })
            ->join('customermaster as cm', 'cm.customercode', '=', 'ih.customercode')
            ->join('routemaster as rm', 'rm.routecode', '=', 'ih.routecode')
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
            ->selectRaw("
                'INVOICE' as type,
                'INVOICE' as type_sort,
                DATE({$ih}.actualtransactiondate) as transactiondate_sort,
                DATE_FORMAT({$ih}.actualtransactiondate, '%d-%b-%Y') as transactiondate,
                {$ih}.routekey,
                {$ih}.visitkey,
                {$ih}.routecode,
                {$rm}.routename,
                {$rm}.arbroutename,
                {$customerCodeColumn} as customercode,
                {$cm}.customername,
                {$cm}.arbcustomername,
                CAST({$ih}.invoicenumber AS CHAR) as documentnumber,
                CAST({$ih}.invoicenumber AS CHAR) as documentnumber_sort,
                CASE WHEN {$ccd}.typecode = 0 THEN COALESCE({$ccd}.amount, 0) ELSE 0 END as immediatecash,
                CASE WHEN {$ccd}.typecode = 1 THEN COALESCE({$ccd}.amount, 0) ELSE 0 END as immediatecheck,
                COALESCE({$ccd}.amount, 0) as total
            ");
    }

    private function receiptQuery(array $filters)
    {
        $arh = $this->qualifiedAlias('arh');
        $ccd = $this->qualifiedAlias('ccd');
        $cm = $this->qualifiedAlias('cm');
        $rm = $this->qualifiedAlias('rm');

        $customerCodeColumn = $this->hasColumn('customermaster', 'reportcustcode')
            ? "{$cm}.reportcustcode"
            : "{$cm}.alternatecode";

        return DB::table('arheader as arh')
            ->join('cashcheckdetail as ccd', function ($join) {
                $join->on('ccd.routekey', '=', 'arh.routekey')
                    ->on('ccd.visitkey', '=', 'arh.visitkey');
            })
            ->join('customermaster as cm', 'cm.customercode', '=', 'arh.customercode')
            ->join('routemaster as rm', 'rm.routecode', '=', 'arh.routecode')
            ->where('arh.voidflag', 0)
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
                CASE WHEN COALESCE({$arh}.advancepaymentflag, 0) > 0 THEN 'ADVANCE PAYMENT' ELSE 'RECEIPT' END as type,
                CASE WHEN COALESCE({$arh}.advancepaymentflag, 0) > 0 THEN 'ADVANCE PAYMENT' ELSE 'RECEIPT' END as type_sort,
                DATE({$arh}.transactiondate) as transactiondate_sort,
                DATE_FORMAT({$arh}.transactiondate, '%d-%b-%Y') as transactiondate,
                {$arh}.routekey,
                {$arh}.visitkey,
                {$arh}.routecode,
                {$rm}.routename,
                {$rm}.arbroutename,
                {$customerCodeColumn} as customercode,
                {$cm}.customername,
                {$cm}.arbcustomername,
                CAST({$arh}.invoicenumber AS CHAR) as documentnumber,
                CAST({$arh}.invoicenumber AS CHAR) as documentnumber_sort,
                CASE WHEN {$ccd}.typecode = 0 THEN COALESCE({$ccd}.amount, 0) ELSE 0 END as immediatecash,
                CASE WHEN {$ccd}.typecode = 1 THEN COALESCE({$ccd}.amount, 0) ELSE 0 END as immediatecheck,
                COALESCE({$ccd}.amount, 0) as total
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
        $customerCode = $this->identifier($row['customercode'] ?? '');

        return [
            'routekey' => (int) ($row['routekey'] ?? 0),
            'visitkey' => (int) ($row['visitkey'] ?? 0),
            'routecode' => (int) ($row['routecode'] ?? 0),
            'route_label' => trim(((int) ($row['routecode'] ?? 0)) . ' - ' . $routeName),
            'transactiondate' => (string) ($row['transactiondate'] ?? ''),
            'transactiondate_sort' => (string) ($row['transactiondate_sort'] ?? ''),
            'customer_label' => trim($customerCode . ($customerName !== '' ? ' - ' . $customerName : '')),
            'customer_sort' => mb_strtolower(trim($customerCode . ' ' . $customerName)),
            'documentnumber' => $this->identifier($row['documentnumber'] ?? ''),
            'documentnumber_sort' => (string) ($row['documentnumber_sort'] ?? ''),
            'type' => (string) ($row['type'] ?? ''),
            'type_sort' => (string) ($row['type_sort'] ?? ''),
            'immediatecash' => (float) ($row['immediatecash'] ?? 0),
            'immediatecheck' => (float) ($row['immediatecheck'] ?? 0),
            'total' => (float) ($row['total'] ?? 0),
        ];
    }

    private function totals(Collection $rows): array
    {
        return [
            'immediatecash' => (float) $rows->sum('immediatecash'),
            'immediatecheck' => (float) $rows->sum('immediatecheck'),
            'total' => (float) $rows->sum('total'),
        ];
    }

    private function mapExportRow(array $row): array
    {
        $export = [];

        foreach (self::EXPORT_COLUMNS as $label => $key) {
            $value = $row[$key] ?? '';
            if (in_array($key, ['immediatecash', 'immediatecheck', 'total'], true)) {
                $value = AmountPrecision::format($value);
            }
            $export[$label] = $value;
        }

        return $export;
    }

    private function totalsExportRow(array $totals): array
    {
        $row = array_fill_keys(array_keys(self::EXPORT_COLUMNS), '');
        $row['Type'] = 'Total';
        $row['Immediate Cash'] = AmountPrecision::format($totals['immediatecash']);
        $row['Immediate Cheque'] = AmountPrecision::format($totals['immediatecheck']);
        $row['Total'] = AmountPrecision::format($totals['total']);

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
}
