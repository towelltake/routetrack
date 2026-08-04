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

class RouteTripAnalysisController extends Controller
{
    private const SORT_COLUMNS = [
        'routecode',
        'dayname',
        'visitsequence',
        'reportcustcode',
        'customername_sort',
        'mop',
        'visitsheduled',
        'visitstarttime_sort',
        'visitendtime_sort',
        'visitstatus',
        'totalinvoiceamount',
        'lastsales_sort',
        'lastorder_sort',
    ];

    private const EXPORT_COLUMNS = [
        'Route' => 'route_label',
        'Week Day' => 'dayname',
        'Visit Sequ.' => 'visitsequence',
        'Customer Code' => 'reportcustcode',
        'Customer' => 'customer_label',
        'Payment Mode' => 'mop',
        'Customer Visit Schedule' => 'visitsheduled',
        'Visit Start Time' => 'visitstarttime',
        'Visit End Time' => 'visitendtime',
        'Visit Status' => 'visitstatus',
        'Transaction Amount' => 'totalinvoiceamount',
        'No Transaction Reason' => 'reason',
        'Last Invoiced Date' => 'lastsales',
        'Last Order Taken On' => 'lastorder',
    ];

    public function __construct(
        private readonly ReportScopeService $reportScopeService
    ) {}

    public function __invoke(Request $request): Response
    {
        $context = $this->resolveContext($request, true);
        $rows = $this->loadAllRows($context['filters'], $context['sort_by'], $context['sort_dir']);
        $paginator = $this->paginateRows($rows, $context['filters']['per_page'], $context['page'], $request);

        return Inertia::render('reports/daily-report/RouteTripAnalysis', [
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
            'route-trip-analysis-' . now()->format('Ymd_His') . '.xls',
            array_keys(self::EXPORT_COLUMNS),
            $exportRows,
            'Route Trip Analysis'
        );
    }

    public function exportPdf(Request $request): View
    {
        $context = $this->resolveContext($request, false);
        $rows = $this->loadAllRows($context['filters'], $context['sort_by'], $context['sort_dir']);

        return view('reports.route-trip-analysis-pdf', [
            'rows' => $rows,
            'totals' => $this->totals($rows),
            'selectedFilters' => $this->selectedFilterLabels($context['filters'], $context['scope']),
            'amountPrecision' => AmountPrecision::get(),
        ]);
    }

    private function resolveContext(Request $request, bool $withPagination): array
    {
        $user = $request->user();
        abort_unless($user?->hasFormPermission('route trip analysis'), 403);

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
            'per_page' => $withPagination ? (int) ($validated['per_page'] ?? 25) : 100000,
        ];

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
        if (!$this->canBuildReport()) {
            return collect();
        }

        if (($filters['scope_limited'] ?? false) && $filters['routecodes'] === []) {
            return collect();
        }

        $query = $this->baseQuery($filters);

        if ($query === null) {
            return collect();
        }

        return $query
            ->get()
            ->map(fn ($row) => $this->transformRow((array) $row))
            ->sort(function (array $left, array $right) use ($sortBy, $sortDir) {
                $baseCompare = $this->compare($left[$sortBy] ?? null, $right[$sortBy] ?? null);
                if ($baseCompare !== 0) {
                    return $sortDir === 'desc' ? -$baseCompare : $baseCompare;
                }

                foreach (['routecode', 'visitstartdate_sort', 'visitstarttime_sort', 'visitkey', 'trantype'] as $fallback) {
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
        $visitQuery = $this->visitUnionQuery();

        if ($visitQuery === null) {
            return null;
        }

        $sed = $this->qualifiedAlias('sed');
        $rm = $this->qualifiedAlias('rm');
        $sm = $this->qualifiedAlias('sm');
        $coc = $this->qualifiedAlias('coc');
        $rs = $this->qualifiedAlias('rs');
        $cm = $this->qualifiedAlias('cm');
        $visit = $this->qualifiedAlias('visit');
        $ihLast = $this->qualifiedAlias('ih_last');
        $sohLast = $this->qualifiedAlias('soh_last');
        $lastSalesSelect = Schema::hasTable('invoiceheader')
            ? "(
                    SELECT MAX({$ihLast}.transactiondate)
                    FROM {$this->qualifiedTable('invoiceheader')} {$ihLast}
                    WHERE {$ihLast}.voidflag = 0
                      AND {$ihLast}.customercode = {$cm}.customercode
                      AND {$ihLast}.routekey < {$sed}.routekey
                )"
            : "NULL";
        $lastOrderSelect = Schema::hasTable('salesorderheader')
            ? "(
                    SELECT MAX({$sohLast}.transactiondate)
                    FROM {$this->qualifiedTable('salesorderheader')} {$sohLast}
                    WHERE {$sohLast}.voidflag = 0
                      AND {$sohLast}.customercode = {$cm}.customercode
                      AND {$sohLast}.routekey < {$sed}.routekey
                )"
            : "NULL";

        return DB::table('startendday as sed')
            ->join('routemaster as rm', 'rm.routecode', '=', 'sed.routecode')
            ->join('salesman as sm', 'sm.salesmancode', '=', 'sed.salesmancode')
            ->join('customeroperationscontrol as coc', 'coc.routekey', '=', 'sed.routekey')
            ->join('routesequencecustomerstatus as rs', function ($join) {
                $join->on('rs.routekey', '=', 'coc.routekey')
                    ->on('rs.customercode', '=', 'coc.customercode');
            })
            ->join('customermaster as cm', 'cm.customercode', '=', 'coc.customercode')
            ->joinSub($visitQuery, 'visit', function ($join) {
                $join->on('visit.routekey', '=', 'coc.routekey')
                    ->on('visit.visitkey', '=', 'coc.visitkey');
            })
            ->when(
                $filters['route_end_date'],
                fn ($builder, $date) => $builder->whereDate('sed.routeenddate', $date)
            )
            ->when(
                $filters['routecodes'] !== [],
                fn ($builder) => $builder->whereIn('sed.routecode', $filters['routecodes'])
            )
            ->selectRaw("
                {$sed}.routekey,
                {$visit}.visitkey,
                {$visit}.trantype,
                {$visit}.invoicenumber,
                {$coc}.visitstartdate as currentday,
                DAYNAME({$coc}.visitstartdate) as dayname,
                COALESCE({$rs}.seqweeknumber, {$rs}.seqweekday, 0) as sequenceweekday,
                1 as startdayorder,
                {$visit}.visitkey as visitsequence,
                DATE_FORMAT({$sed}.routestartdate, '%d %b %Y') as routestartdate,
                DATE_FORMAT({$sed}.routeenddate, '%d %b %Y') as routeenddate,
                {$sed}.routecode,
                {$rm}.routename,
                {$rm}.arbroutename,
                {$sed}.salesmancode,
                {$sm}.salesmanname1,
                {$sm}.arbsalesmanname1,
                {$cm}.alternatecode as reportcustcode,
                {$cm}.customername,
                {$cm}.arbcustomername,
                CASE
                    WHEN COALESCE({$cm}.invoicepaymentterms, 0) < 2 THEN 'CASH'
                    WHEN COALESCE({$cm}.invoicepaymentterms, 0) = 2 THEN 'CREDIT'
                    ELSE 'TC'
                END as mop,
                COALESCE({$rs}.schelduledflag, 0) as schelduledflag,
                {$lastSalesSelect} as lastsales,
                {$lastOrderSelect} as lastorder,
                CASE WHEN COALESCE({$rs}.schelduledflag, 0) = 0 THEN 'NO' ELSE 'YES' END as visitsheduled,
                DATE_FORMAT({$coc}.visitstartdate, '%d %b %Y') as visitstartdate,
                DATE_FORMAT({$coc}.visitstartdate, '%Y-%m-%d') as visitstartdate_sort,
                DATE_FORMAT({$coc}.visitenddate, '%d %b %Y') as visitenddate,
                CAST({$coc}.visitstarttime AS CHAR) as visitstarttime,
                CAST({$coc}.visitendtime AS CHAR) as visitendtime,
                CAST({$coc}.visitstarttime AS CHAR) as visitstarttime_sort,
                CAST({$coc}.visitendtime AS CHAR) as visitendtime_sort,
                TIMEDIFF(CAST({$coc}.visitendtime AS TIME), CAST({$coc}.visitstarttime AS TIME)) as timespend,
                COALESCE({$coc}.totaltransactions, 0) as totaltransactions,
                {$visit}.visitstatus,
                '' as reason,
                CASE WHEN COALESCE({$visit}.voidflag, 0) = 0 THEN ROUND(COALESCE({$visit}.amount, 0), 2) ELSE 0 END as totalinvoiceamount
            ");
    }

    private function visitUnionQuery()
    {
        $queries = [];

        if (Schema::hasTable('invoiceheader')) {
            $ih = $this->qualifiedAlias('ih');
            $queries[] = DB::table('invoiceheader as ih')
                ->selectRaw("
                    {$ih}.routekey,
                    {$ih}.visitkey,
                    'SALES' as trantype,
                    {$ih}.voidflag,
                    CONCAT('SALES', CASE WHEN {$ih}.voidflag = 0 THEN ' ' ELSE ' *VOIDED*' END) as visitstatus,
                    COALESCE({$ih}.totalinvoiceamount, 0) as amount,
                    {$ih}.invoicenumber
                ");
        }

        if (Schema::hasTable('arheader')) {
            $arh = $this->qualifiedAlias('arh');
            $queries[] = DB::table('arheader as arh')
                ->selectRaw("
                    {$arh}.routekey,
                    {$arh}.visitkey,
                    'RECEIPT' as trantype,
                    {$arh}.voidflag,
                    CONCAT('RECEIPT', CASE WHEN {$arh}.voidflag = 0 THEN ' ' ELSE ' *VOIDED*' END) as visitstatus,
                    COALESCE({$arh}.totalinvoiceamount, 0) as amount,
                    {$arh}.invoicenumber
                ");
        }

        if (Schema::hasTable('salesorderheader')) {
            $soh = $this->qualifiedAlias('soh');
            $queries[] = DB::table('salesorderheader as soh')
                ->selectRaw("
                    {$soh}.routekey,
                    {$soh}.visitkey,
                    'ORDER' as trantype,
                    {$soh}.voidflag,
                    CONCAT('ORDER', CASE WHEN {$soh}.voidflag = 0 THEN ' ' ELSE ' *VOIDED*' END) as visitstatus,
                    COALESCE({$soh}.totalinvoiceamount, 0) as amount,
                    {$soh}.invoicenumber
                ");
        }

        if (Schema::hasTable('surveyauditdetail')) {
            $sad = $this->qualifiedAlias('sad');
            $queries[] = DB::table('surveyauditdetail as sad')
                ->selectRaw("
                    {$sad}.routekey,
                    {$sad}.visitkey,
                    'MERCHANDISING' as trantype,
                    0 as voidflag,
                    'MERCHANDISING' as visitstatus,
                    0 as amount,
                    CONCAT({$sad}.routekey, {$sad}.visitkey) as invoicenumber
                ");
        }

        if (Schema::hasTable('posequipmentchangedetail')) {
            $ped = $this->qualifiedAlias('ped');
            $queries[] = DB::table('posequipmentchangedetail as ped')
                ->selectRaw("
                    {$ped}.routekey,
                    {$ped}.visitkey,
                    'MERCHANDISING' as trantype,
                    0 as voidflag,
                    'MERCHANDISING' as visitstatus,
                    0 as amount,
                    CONCAT({$ped}.routekey, {$ped}.visitkey) as invoicenumber
                ");
        }

        if (Schema::hasTable('customeroperationscontrol') && Schema::hasTable('nonservreasons')) {
            $coc1 = $this->qualifiedAlias('coc1');
            $ns = $this->qualifiedAlias('ns');
            $queries[] = DB::table('customeroperationscontrol as coc1')
                ->join('nonservreasons as ns', 'ns.code', '=', 'coc1.reasoncode')
                ->where('coc1.reasoncode', '>', 0)
                ->selectRaw("
                    {$coc1}.routekey,
                    {$coc1}.visitkey,
                    'NO SALE' as trantype,
                    0 as voidflag,
                    {$ns}.description as visitstatus,
                    0 as amount,
                    CONCAT({$coc1}.routekey, {$coc1}.visitkey) as invoicenumber
                ");
        }

        if ($queries === []) {
            return null;
        }

        $query = array_shift($queries);

        foreach ($queries as $unionPart) {
            $query->unionAll($unionPart);
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
            'routekey' => (int) ($row['routekey'] ?? 0),
            'visitkey' => (int) ($row['visitkey'] ?? 0),
            'trantype' => (string) ($row['trantype'] ?? ''),
            'invoicenumber' => (string) ($row['invoicenumber'] ?? ''),
            'dayname' => (string) ($row['dayname'] ?? ''),
            'visitsequence' => (string) ($row['visitsequence'] ?? ''),
            'routecode' => (int) ($row['routecode'] ?? 0),
            'route_label' => trim(((int) ($row['routecode'] ?? 0)) . ' - ' . $routeName),
            'salesmancode' => (string) ($row['salesmancode'] ?? ''),
            'salesmanname1' => $isArabic
                ? ($row['arbsalesmanname1'] ?? $row['salesmanname1'] ?? '')
                : ($row['salesmanname1'] ?? $row['arbsalesmanname1'] ?? ''),
            'reportcustcode' => (string) ($row['reportcustcode'] ?? ''),
            'customer_label' => $customerName,
            'customername_sort' => mb_strtolower($customerName),
            'mop' => (string) ($row['mop'] ?? ''),
            'visitsheduled' => (string) ($row['visitsheduled'] ?? ''),
            'visitstartdate' => (string) ($row['visitstartdate'] ?? ''),
            'visitstartdate_sort' => (string) ($row['visitstartdate_sort'] ?? ''),
            'visitenddate' => (string) ($row['visitenddate'] ?? ''),
            'visitstarttime' => $this->formatTime($row['visitstarttime'] ?? null),
            'visitendtime' => $this->formatTime($row['visitendtime'] ?? null),
            'visitstarttime_sort' => (string) ($row['visitstarttime_sort'] ?? ''),
            'visitendtime_sort' => (string) ($row['visitendtime_sort'] ?? ''),
            'visitstatus' => trim((string) ($row['visitstatus'] ?? '')),
            'reason' => trim((string) ($row['reason'] ?? '')),
            'totalinvoiceamount' => (float) ($row['totalinvoiceamount'] ?? 0),
            'lastsales' => $this->formatDate($row['lastsales'] ?? null),
            'lastsales_sort' => (string) ($row['lastsales'] ?? ''),
            'lastorder' => $this->formatDate($row['lastorder'] ?? null),
            'lastorder_sort' => (string) ($row['lastorder'] ?? ''),
        ];
    }

    private function totals(Collection $rows): array
    {
        return [
            'totalinvoiceamount' => (float) $rows->sum('totalinvoiceamount'),
        ];
    }

    private function mapExportRow(array $row): array
    {
        $export = [];

        foreach (self::EXPORT_COLUMNS as $label => $key) {
            $value = $row[$key] ?? '';
            if ($key === 'totalinvoiceamount') {
                $value = AmountPrecision::format($value);
            }
            $export[$label] = $value;
        }

        return $export;
    }

    private function totalsExportRow(array $totals): array
    {
        $row = array_fill_keys(array_keys(self::EXPORT_COLUMNS), '');
        $row['Visit Status'] = 'Total';
        $row['Transaction Amount'] = AmountPrecision::format($totals['totalinvoiceamount']);

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

    private function canBuildReport(): bool
    {
        return $this->hasTables([
            'startendday',
            'routemaster',
            'salesman',
            'customeroperationscontrol',
            'routesequencecustomerstatus',
            'customermaster',
        ]);
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

    private function formatDate(mixed $value): string
    {
        if (empty($value) || $value === '0000-00-00' || $value === '0000-00-00 00:00:00') {
            return '';
        }

        try {
            return \Carbon\Carbon::parse($value)->format('d-M-Y');
        } catch (\Throwable) {
            return (string) $value;
        }
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

    private function qualifiedTable(string $table): string
    {
        return DB::getTablePrefix() . $table;
    }
}
