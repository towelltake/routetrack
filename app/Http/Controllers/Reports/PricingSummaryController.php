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

class PricingSummaryController extends Controller
{
    private const SORT_COLUMNS = [
        'transactiondate_sort',
        'transactiontime_sort',
        'invoicenumber_sort',
        'reportcustcode',
        'customername_sort',
        'totalinvoiceamount',
        'totalfreesampleamount',
        'netamount',
    ];

    private const EXPORT_COLUMNS = [
        'Transaction Date' => 'transactiondate',
        'Transaction Time' => 'transactiontime',
        'Invoice Number' => 'invoicenumber',
        'Customer Code' => 'reportcustcode',
        'Customer Name' => 'customer_label',
        'Sales Amount' => 'totalinvoiceamount',
        'Free Amount' => 'totalfreesampleamount',
        'Net Amount' => 'netamount',
        'Item Code' => 'itemcode',
        'Item Description' => 'itemdescription',
        'Sales Qty' => 'salesqty',
        'Sales Case Price' => 'salescaseprice',
        'Sales Pcs Price' => 'salesprice',
        'Std. Case Price' => 'stdsalescaseprice',
        'Std. Pcs Price' => 'stdsalesprice',
    ];

    public function __construct(
        private readonly ReportScopeService $reportScopeService
    ) {}

    public function __invoke(Request $request): Response
    {
        $context = $this->resolveContext($request, true);
        $rows = $this->loadAllRows($context['filters'], $context['sort_by'], $context['sort_dir']);
        $paginator = $this->paginateRows($rows, $context['filters']['per_page'], $context['page'], $request);

        return Inertia::render('reports/daily-report/PricingSummary', [
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
            ->flatMap(fn (array $row) => $this->mapExportRows($row))
            ->push($this->totalsExportRow($this->totals($rows)))
            ->all();

        return ExcelXmlWorkbook::download(
            'pricing-summary-' . now()->format('Ymd_His') . '.xls',
            array_keys(self::EXPORT_COLUMNS),
            $exportRows,
            'Pricing Summary'
        );
    }

    public function exportPdf(Request $request): View
    {
        $context = $this->resolveContext($request, false);
        $rows = $this->loadAllRows($context['filters'], $context['sort_by'], $context['sort_dir']);

        return view('reports.pricing-summary-pdf', [
            'rows' => $rows,
            'totals' => $this->totals($rows),
            'selectedFilters' => $this->selectedFilterLabels($context['filters'], $context['scope']),
            'amountPrecision' => AmountPrecision::get(),
        ]);
    }

    private function resolveContext(Request $request, bool $withPagination): array
    {
        $user = $request->user();
        abort_unless($user?->hasFormPermission('pricing summary'), 403);

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

        $requestedSortBy = $validated['sort_by'] ?? 'transactiondate_sort';

        return [
            'filters' => $filters,
            'scope' => $scope,
            'sort_by' => in_array($requestedSortBy, self::SORT_COLUMNS, true) ? $requestedSortBy : 'transactiondate_sort',
            'sort_dir' => $validated['sort_dir'] ?? 'asc',
            'page' => max((int) ($validated['page'] ?? 1), 1),
        ];
    }

    private function loadAllRows(array $filters, string $sortBy, string $sortDir): Collection
    {
        if (!$this->hasTables(['invoiceheader', 'invoicedetail', 'itemmaster', 'customermaster', 'routemaster'])) {
            return collect();
        }

        if (($filters['scope_limited'] ?? false) && $filters['routecodes'] === []) {
            return collect();
        }

        $summaries = $this->summaryQuery($filters)
            ->get()
            ->map(fn ($row) => $this->transformSummaryRow((array) $row))
            ->keyBy('transactionkey');

        if ($summaries->isEmpty()) {
            return collect();
        }

        $details = $this->detailQuery($filters, $summaries->keys()->all())
            ->get()
            ->groupBy('transactionkey')
            ->map(fn (Collection $group) => $group->map(fn ($row) => $this->transformDetailRow((array) $row))->values()->all());

        return $summaries
            ->map(function (array $row, int|string $transactionKey) use ($details) {
                $row['details'] = $details->get($transactionKey, []);
                return $row;
            })
            ->sort(function (array $left, array $right) use ($sortBy, $sortDir) {
                $baseCompare = $this->compare($left[$sortBy] ?? null, $right[$sortBy] ?? null);
                if ($baseCompare !== 0) {
                    return $sortDir === 'desc' ? -$baseCompare : $baseCompare;
                }

                foreach (['transactiondate_sort', 'transactiontime_sort', 'invoicenumber_sort'] as $fallback) {
                    $fallbackCompare = $this->compare($left[$fallback] ?? null, $right[$fallback] ?? null);
                    if ($fallbackCompare !== 0) {
                        return $fallbackCompare;
                    }
                }

                return 0;
            })
            ->values();
    }

    private function summaryQuery(array $filters)
    {
        $ih = $this->qualifiedAlias('ih');
        $cm = $this->qualifiedAlias('cm');

        return DB::table('invoiceheader as ih')
            ->join('customermaster as cm', 'cm.customercode', '=', 'ih.customercode')
            ->join('routemaster as rm', 'rm.routecode', '=', 'ih.routecode')
            ->whereExists(function ($query) {
                $query->selectRaw('1')
                    ->from('invoicedetail as id')
                    ->join('itemmaster as im', 'im.actualitemcode', '=', 'id.itemcode')
                    ->whereColumn('id.transactionkey', 'ih.transactionkey')
                    ->whereColumn('id.salesprice', '!=', 'id.stdsalesprice');
            })
            ->when(
                $filters['transaction_date_from'],
                fn ($builder, $date) => $builder->whereDate('ih.transactiondate', '>=', $date)
            )
            ->when(
                $filters['transaction_date_to'],
                fn ($builder, $date) => $builder->whereDate('ih.transactiondate', '<=', $date)
            )
            ->when(
                $filters['routecodes'] !== [],
                fn ($builder) => $builder->whereIn('ih.routecode', $filters['routecodes'])
            )
            ->selectRaw("
                {$ih}.transactionkey,
                {$ih}.routekey,
                {$ih}.visitkey,
                DATE_FORMAT({$ih}.transactiondate, '%d %b %Y') as transactiondate,
                DATE_FORMAT({$ih}.transactiondate, '%Y-%m-%d') as transactiondate_sort,
                CAST({$ih}.transactiontime AS CHAR) as transactiontime,
                CAST({$ih}.transactiontime AS CHAR) as transactiontime_sort,
                {$ih}.invoicenumber,
                CAST({$ih}.invoicenumber AS CHAR) as invoicenumber_sort,
                {$cm}.alternatecode as reportcustcode,
                {$cm}.customername,
                {$cm}.arbcustomername,
                COALESCE({$ih}.totalinvoiceamount, 0) as totalinvoiceamount,
                COALESCE({$ih}.totalfreesampleamount, 0) as totalfreesampleamount,
                COALESCE({$ih}.totalinvoiceamount, 0) - COALESCE({$ih}.totalfreesampleamount, 0) as netamount
            ");
    }

    private function detailQuery(array $filters, array $transactionKeys)
    {
        $ih = $this->qualifiedAlias('ih');
        $id = $this->qualifiedAlias('id');
        $im = $this->qualifiedAlias('im');

        return DB::table('invoiceheader as ih')
            ->join('invoicedetail as id', 'id.transactionkey', '=', 'ih.transactionkey')
            ->join('itemmaster as im', 'im.actualitemcode', '=', 'id.itemcode')
            ->whereIn('ih.transactionkey', $transactionKeys)
            ->whereColumn('id.salesprice', '!=', 'id.stdsalesprice')
            ->selectRaw("
                {$ih}.transactionkey,
                {$im}.alternatecode as itemcode,
                {$im}.itemdescription,
                {$im}.arbitemdescription,
                COALESCE({$id}.salesqty, 0) as salesqty,
                COALESCE({$id}.salescaseprice, 0) as salescaseprice,
                COALESCE({$id}.salesprice, 0) as salesprice,
                COALESCE({$id}.stdsalescaseprice, 0) as stdsalescaseprice,
                COALESCE({$id}.stdsalesprice, 0) as stdsalesprice
            ")
            ->orderBy('ih.transactiondate')
            ->orderBy('ih.transactiontime')
            ->orderBy('ih.invoicenumber')
            ->orderBy('im.alternatecode');
    }

    private function transformSummaryRow(array $row): array
    {
        $isArabic = app()->getLocale() === 'ar';
        $customerName = $isArabic
            ? ($row['arbcustomername'] ?? $row['customername'] ?? '')
            : ($row['customername'] ?? $row['arbcustomername'] ?? '');

        return [
            'transactionkey' => (int) ($row['transactionkey'] ?? 0),
            'routekey' => (int) ($row['routekey'] ?? 0),
            'visitkey' => (int) ($row['visitkey'] ?? 0),
            'transactiondate' => (string) ($row['transactiondate'] ?? ''),
            'transactiondate_sort' => (string) ($row['transactiondate_sort'] ?? ''),
            'transactiontime' => $this->formatTime($row['transactiontime'] ?? null),
            'transactiontime_sort' => (string) ($row['transactiontime_sort'] ?? ''),
            'invoicenumber' => $this->identifier($row['invoicenumber'] ?? ''),
            'invoicenumber_sort' => (string) ($row['invoicenumber_sort'] ?? ''),
            'reportcustcode' => $this->identifier($row['reportcustcode'] ?? ''),
            'customer_label' => $customerName,
            'customername_sort' => mb_strtolower($customerName),
            'totalinvoiceamount' => (float) ($row['totalinvoiceamount'] ?? 0),
            'totalfreesampleamount' => (float) ($row['totalfreesampleamount'] ?? 0),
            'netamount' => (float) ($row['netamount'] ?? 0),
            'details' => [],
        ];
    }

    private function transformDetailRow(array $row): array
    {
        $isArabic = app()->getLocale() === 'ar';
        $itemDescription = $isArabic
            ? ($row['arbitemdescription'] ?? $row['itemdescription'] ?? '')
            : ($row['itemdescription'] ?? $row['arbitemdescription'] ?? '');

        return [
            'itemcode' => $this->identifier($row['itemcode'] ?? ''),
            'itemdescription' => $itemDescription,
            'salesqty' => (float) ($row['salesqty'] ?? 0),
            'salescaseprice' => (float) ($row['salescaseprice'] ?? 0),
            'salesprice' => (float) ($row['salesprice'] ?? 0),
            'stdsalescaseprice' => (float) ($row['stdsalescaseprice'] ?? 0),
            'stdsalesprice' => (float) ($row['stdsalesprice'] ?? 0),
        ];
    }

    private function totals(Collection $rows): array
    {
        return [
            'totalinvoiceamount' => (float) $rows->sum('totalinvoiceamount'),
            'totalfreesampleamount' => (float) $rows->sum('totalfreesampleamount'),
            'netamount' => (float) $rows->sum('netamount'),
        ];
    }

    private function mapExportRows(array $row): array
    {
        $details = $row['details'] ?? [];
        if ($details === []) {
            return [$this->mapExportRow($row, [])];
        }

        return collect($details)
            ->map(fn (array $detail) => $this->mapExportRow($row, $detail))
            ->all();
    }

    private function mapExportRow(array $row, array $detail): array
    {
        $export = [];
        $merged = [...$row, ...$detail];

        foreach (self::EXPORT_COLUMNS as $label => $key) {
            $value = $merged[$key] ?? '';
            if (in_array($key, ['totalinvoiceamount', 'totalfreesampleamount', 'netamount', 'salesqty', 'salescaseprice', 'salesprice', 'stdsalescaseprice', 'stdsalesprice'], true)) {
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
        $row['Sales Amount'] = AmountPrecision::format($totals['totalinvoiceamount']);
        $row['Free Amount'] = AmountPrecision::format($totals['totalfreesampleamount']);
        $row['Net Amount'] = AmountPrecision::format($totals['netamount']);

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
