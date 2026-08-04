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

class DiscountSummaryController extends Controller
{
    private const SORT_COLUMNS = [
        'trandate_sort',
        'transactiontime_sort',
        'invoicenumber_sort',
        'reportcustcode',
        'customername_sort',
        'salesamount',
        'goodreturnamount',
        'totaldamagedamount',
        'freeamount',
        'invoiceamount',
        'discountamount',
        'netamount',
    ];

    private const EXPORT_COLUMNS = [
        'Transaction Date' => 'transactiondate',
        'Transaction Time' => 'transactiontime',
        'Invoice Number' => 'invoicenumber',
        'Customer Code' => 'reportcustcode',
        'Customer Name' => 'customer_label',
        'Sales Amount' => 'salesamount',
        'Good Ret. Amount' => 'goodreturnamount',
        'Bad Ret. Amount' => 'totaldamagedamount',
        'Free Amount' => 'freeamount',
        'Invoice Amount' => 'invoiceamount',
        'Discount Amount' => 'discountamount',
        'Net Amount' => 'netamount',
    ];

    public function __construct(
        private readonly ReportScopeService $reportScopeService
    ) {}

    public function __invoke(Request $request): Response
    {
        $context = $this->resolveContext($request, true);
        $rows = $this->loadAllRows($context['filters'], $context['sort_by'], $context['sort_dir']);
        $paginator = $this->paginateRows($rows, $context['filters']['per_page'], $context['page'], $request);

        return Inertia::render('reports/daily-report/DiscountSummary', [
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
            'discount-summary-' . now()->format('Ymd_His') . '.xls',
            array_keys(self::EXPORT_COLUMNS),
            $exportRows,
            'Discount Summary'
        );
    }

    public function exportPdf(Request $request): View
    {
        $context = $this->resolveContext($request, false);
        $rows = $this->loadAllRows($context['filters'], $context['sort_by'], $context['sort_dir']);

        return view('reports.discount-summary-pdf', [
            'rows' => $rows,
            'totals' => $this->totals($rows),
            'selectedFilters' => $this->selectedFilterLabels($context['filters'], $context['scope']),
            'amountPrecision' => AmountPrecision::get(),
        ]);
    }

    private function resolveContext(Request $request, bool $withPagination): array
    {
        $user = $request->user();
        abort_unless($user?->hasFormPermission('discount summary'), 403);

        $rules = [
            'month' => ['nullable', 'integer', 'min:1', 'max:12'],
            'year' => ['nullable', 'integer', 'min:2000', 'max:2100'],
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
            'month' => (int) ($validated['month'] ?? now()->month),
            'year' => (int) ($validated['year'] ?? now()->year),
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

        $requestedSortBy = $validated['sort_by'] ?? 'trandate_sort';

        return [
            'filters' => $filters,
            'scope' => $scope,
            'sort_by' => in_array($requestedSortBy, self::SORT_COLUMNS, true) ? $requestedSortBy : 'trandate_sort',
            'sort_dir' => $validated['sort_dir'] ?? 'asc',
            'page' => max((int) ($validated['page'] ?? 1), 1),
        ];
    }

    private function loadAllRows(array $filters, string $sortBy, string $sortDir): Collection
    {
        if (!$this->hasTables(['invoiceheader', 'customermaster', 'salesman', 'routemaster'])) {
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

                foreach (['trandate_sort', 'transactiontime_sort', 'invoicenumber_sort'] as $fallback) {
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
        $cm = $this->qualifiedAlias('cm');

        return DB::table('invoiceheader as ih')
            ->join('customermaster as cm', 'cm.customercode', '=', 'ih.customercode')
            ->join('salesman as sm', 'sm.salesmancode', '=', 'ih.salesmancode')
            ->join('routemaster as rm', 'rm.routecode', '=', 'ih.routecode')
            ->where('ih.voidflag', 0)
            ->whereRaw("COALESCE({$ih}.totaldiscountamount, 0) + COALESCE({$ih}.totalfreesampleamount, 0) > 0")
            ->whereMonth('ih.actualtransactiondate', $filters['month'])
            ->whereYear('ih.actualtransactiondate', $filters['year'])
            ->when(
                $filters['routecodes'] !== [],
                fn ($builder) => $builder->whereIn('ih.routecode', $filters['routecodes'])
            )
            ->selectRaw("
                {$ih}.routekey,
                {$ih}.visitkey,
                DATE_FORMAT({$ih}.actualtransactiondate, '%d %b %Y') as transactiondate,
                DATE_FORMAT({$ih}.actualtransactiondate, '%Y-%m-%d') as trandate_sort,
                CAST({$ih}.transactiontime AS CHAR) as transactiontime,
                CAST({$ih}.transactiontime AS CHAR) as transactiontime_sort,
                {$ih}.invoicenumber,
                CAST({$ih}.invoicenumber AS CHAR) as invoicenumber_sort,
                {$cm}.reportcustcode,
                {$cm}.customername,
                {$cm}.arbcustomername,
                COALESCE({$ih}.totalsalesamount, 0) as salesamount,
                COALESCE({$ih}.totalreturnamount, 0) as goodreturnamount,
                COALESCE({$ih}.totaldamagedamount, 0) as totaldamagedamount,
                COALESCE({$ih}.totalfreesampleamount, 0) as freeamount,
                COALESCE({$ih}.totaldiscountamount, 0) as discountamount,
                COALESCE({$ih}.totalinvoiceamount, 0) as invoiceamount,
                COALESCE({$ih}.totalinvoiceamount, 0) - COALESCE({$ih}.totaldiscountamount, 0) as netamount
            ");
    }

    private function transformRow(array $row): array
    {
        $isArabic = app()->getLocale() === 'ar';
        $customerName = $isArabic
            ? ($row['arbcustomername'] ?? $row['customername'] ?? '')
            : ($row['customername'] ?? $row['arbcustomername'] ?? '');

        return [
            'routekey' => (int) ($row['routekey'] ?? 0),
            'visitkey' => (int) ($row['visitkey'] ?? 0),
            'transactiondate' => (string) ($row['transactiondate'] ?? ''),
            'trandate_sort' => (string) ($row['trandate_sort'] ?? ''),
            'transactiontime' => $this->formatTime($row['transactiontime'] ?? null),
            'transactiontime_sort' => (string) ($row['transactiontime_sort'] ?? ''),
            'invoicenumber' => $this->identifier($row['invoicenumber'] ?? ''),
            'invoicenumber_sort' => (string) ($row['invoicenumber_sort'] ?? ''),
            'reportcustcode' => $this->identifier($row['reportcustcode'] ?? ''),
            'customer_label' => $customerName,
            'customername_sort' => mb_strtolower($customerName),
            'salesamount' => (float) ($row['salesamount'] ?? 0),
            'goodreturnamount' => (float) ($row['goodreturnamount'] ?? 0),
            'totaldamagedamount' => (float) ($row['totaldamagedamount'] ?? 0),
            'freeamount' => (float) ($row['freeamount'] ?? 0),
            'invoiceamount' => (float) ($row['invoiceamount'] ?? 0),
            'discountamount' => (float) ($row['discountamount'] ?? 0),
            'netamount' => (float) ($row['netamount'] ?? 0),
        ];
    }

    private function totals(Collection $rows): array
    {
        return [
            'salesamount' => (float) $rows->sum('salesamount'),
            'goodreturnamount' => (float) $rows->sum('goodreturnamount'),
            'totaldamagedamount' => (float) $rows->sum('totaldamagedamount'),
            'freeamount' => (float) $rows->sum('freeamount'),
            'invoiceamount' => (float) $rows->sum('invoiceamount'),
            'discountamount' => (float) $rows->sum('discountamount'),
            'netamount' => (float) $rows->sum('netamount'),
        ];
    }

    private function mapExportRow(array $row): array
    {
        $export = [];

        foreach (self::EXPORT_COLUMNS as $label => $key) {
            $value = $row[$key] ?? '';
            if (in_array($key, ['salesamount', 'goodreturnamount', 'totaldamagedamount', 'freeamount', 'invoiceamount', 'discountamount', 'netamount'], true)) {
                $value = AmountPrecision::format($value);
            }
            $export[$label] = $value;
        }

        return $export;
    }

    private function totalsExportRow(array $totals): array
    {
        $row = array_fill_keys(array_keys(self::EXPORT_COLUMNS), '');
        $row['Customer Name'] = 'Total';
        $row['Sales Amount'] = AmountPrecision::format($totals['salesamount']);
        $row['Good Ret. Amount'] = AmountPrecision::format($totals['goodreturnamount']);
        $row['Bad Ret. Amount'] = AmountPrecision::format($totals['totaldamagedamount']);
        $row['Free Amount'] = AmountPrecision::format($totals['freeamount']);
        $row['Invoice Amount'] = AmountPrecision::format($totals['invoiceamount']);
        $row['Discount Amount'] = AmountPrecision::format($totals['discountamount']);
        $row['Net Amount'] = AmountPrecision::format($totals['netamount']);

        return $row;
    }

    private function selectedFilterLabels(array $filters, array $scope): array
    {
        return [
            'Month' => date('F', mktime(0, 0, 0, (int) $filters['month'], 1)),
            'Year' => (string) $filters['year'],
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
