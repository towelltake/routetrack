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

class RouteActivityController extends Controller
{
    private const SORT_COLUMNS = [
        'trandate',
        'routecode',
        'customercode',
        'distance',
        'visitstarttime',
        'visitendtime',
        'trantype',
        'tranamount',
        'receiptpaid',
    ];

    private const EXPORT_COLUMNS = [
        'Transaction Date' => 'trandate',
        'Route' => 'route_label',
        'Customer' => 'customer_label',
        'Address' => 'address',
        'Standard GPS' => 'standardgps',
        'Actual GPS' => 'actualgps',
        'Difference (Meter)' => 'distance',
        'Visit Start Time' => 'visitstarttime',
        'Visit End Time' => 'visitendtime',
        'Duration hh:mm' => 'duration',
        'Visit Interval Time' => 'visit_interval',
        'Transaction' => 'trantype',
        'Order Collected' => 'tranamount',
        'Receipt Collected' => 'receiptpaid',
    ];

    public function __construct(
        private readonly ReportScopeService $reportScopeService
    ) {}

    public function __invoke(Request $request): Response
    {
        $context = $this->resolveContext($request, true);
        $paginator = $this->paginateRows(
            $this->loadAllRows($context['filters'], $context['sort_by'], $context['sort_dir']),
            $context['filters']['per_page'],
            $context['page'],
            $request
        );

        return Inertia::render('reports/daily-report/RouteActivity', [
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
            'route-activity-' . now()->format('Ymd_His') . '.xls',
            array_keys(self::EXPORT_COLUMNS),
            $exportRows,
            'Route Activity'
        );
    }

    public function exportPdf(Request $request): View
    {
        $context = $this->resolveContext($request, false);
        $rows = $this->loadAllRows($context['filters'], $context['sort_by'], $context['sort_dir']);

        return view('reports.route-activity-pdf', [
            'rows' => $rows,
            'totals' => $this->totals($rows),
            'selectedFilters' => $this->selectedFilterLabels($context['filters'], $context['scope']),
            'amountPrecision' => AmountPrecision::get(),
        ]);
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
        $rows = collect();

        if (($filters['scope_limited'] ?? false) && $filters['routecodes'] === []) {
            return $rows;
        }

        foreach ($this->activityQueryParts($filters) as $part) {
            $rows = $rows->concat(
                $part->get()->map(fn ($row) => (array) $row)
            );
        }

        $rows = $rows
            ->map(fn (array $row) => $this->transformRow($row))
            ->sort(function (array $left, array $right) use ($sortBy, $sortDir) {
                $baseCompare = $this->compare($left[$sortBy] ?? null, $right[$sortBy] ?? null);
                if ($baseCompare !== 0) {
                    return $sortDir === 'desc' ? -$baseCompare : $baseCompare;
                }

                foreach (['routecode', 'visitstartdate_sort', 'visitstarttime_sort', 'documentno'] as $fallback) {
                    $fallbackCompare = $this->compare($left[$fallback] ?? null, $right[$fallback] ?? null);
                    if ($fallbackCompare !== 0) {
                        return $fallbackCompare;
                    }
                }

                return 0;
            })
            ->values();

        return $this->applyVisitIntervals($rows);
    }

    private function activityQueryParts(array $filters): array
    {
        $parts = [];

        if (Schema::hasTable('inventorytransactionheader')) {
            $parts[] = $this->inventoryQuery($filters);
        }

        if ($this->hasTables(['customeroperationscontrol', 'customermaster'])) {
            $parts[] = $this->nonServiceQuery($filters);
        }

        if ($this->hasTables(['customeroperationscontrol', 'invoiceheader', 'customermaster'])) {
            $parts[] = $this->salesQuery($filters);
        }

        if ($this->hasTables(['customeroperationscontrol', 'salesorderheader', 'customermaster'])) {
            $parts[] = $this->orderQuery($filters);
        }

        if ($this->hasTables(['customeroperationscontrol', 'arheader', 'customermaster'])) {
            $parts[] = $this->receiptQuery($filters);
        }

        return $parts;
    }

    private function inventoryQuery(array $filters)
    {
        $ith = $this->qualifiedAlias('ith');
        $sed = $this->qualifiedAlias('sed');
        $rm = $this->qualifiedAlias('rm');
        $sm = $this->qualifiedAlias('sm');

        return $this->startDayBaseQuery($filters)
            ->join('inventorytransactionheader as ith', 'ith.routekey', '=', 'sed.routekey')
            ->selectRaw("
                0 as visitkey,
                {$ith}.routekey,
                '' as customercode,
                'INVENTORY' as customername,
                '' as arbcustomername,
                {$rm}.routecode,
                {$rm}.routename,
                {$rm}.arbroutename,
                {$sm}.salesmancode,
                {$sm}.salesmanname1,
                {$sm}.arbsalesmanname1,
                '' as customeraddress1,
                '' as customeraddress2,
                '' as customeraddress3,
                DATE_FORMAT({$ith}.actualtransactiondate, '%d %b %Y') as visitstartdate,
                DATE_FORMAT({$ith}.actualtransactiondate, '%Y-%m-%d') as visitstartdate_sort,
                CAST({$ith}.transactiontime AS CHAR) as visitstarttime,
                DATE_FORMAT({$ith}.actualtransactiondate, '%d %b %Y') as visitenddate,
                CAST({$ith}.transactiontime AS CHAR) as visitendtime,
                '' as visited,
                CASE
                    WHEN {$ith}.transactiontype = 3 THEN 'UNLOAD'
                    WHEN {$ith}.transactiontype = 2 THEN 'LOAD TRANSFER'
                    WHEN {$ith}.transactiontype = 1 THEN 'LOAD'
                    ELSE 'LOAD REQUEST'
                END as trantype,
                {$ith}.documentnumber as documentno,
                0 as tranamount,
                0 as receiptpaid,
                DATE_FORMAT({$ith}.actualtransactiondate, '%d %b %Y') as trandate,
                0 as standardgps,
                0 as actualgps,
                0 as distance
            ");
    }

    private function nonServiceQuery(array $filters)
    {
        $coc = $this->qualifiedAlias('coc');
        $cm = $this->qualifiedAlias('cm');
        $rm = $this->qualifiedAlias('rm');
        $sm = $this->qualifiedAlias('sm');

        return $this->startDayBaseQuery($filters)
            ->join('customeroperationscontrol as coc', function ($join) {
                $join->on('coc.routekey', '=', 'sed.routekey')
                    ->where('coc.reasoncode', '>', 0);
            })
            ->join('customermaster as cm', 'cm.customercode', '=', 'coc.customercode')
            ->selectRaw("
                {$coc}.visitkey,
                {$coc}.routekey,
                {$cm}.alternatecode as customercode,
                {$cm}.customername,
                {$cm}.arbcustomername,
                {$rm}.routecode,
                {$rm}.routename,
                {$rm}.arbroutename,
                {$sm}.salesmancode,
                {$sm}.salesmanname1,
                {$sm}.arbsalesmanname1,
                {$cm}.customeraddress1,
                {$cm}.customeraddress2,
                {$cm}.customeraddress3,
                DATE_FORMAT({$coc}.visitstartdate, '%d %b %Y') as visitstartdate,
                DATE_FORMAT({$coc}.visitstartdate, '%Y-%m-%d') as visitstartdate_sort,
                CAST({$coc}.visitstarttime AS CHAR) as visitstarttime,
                DATE_FORMAT({$coc}.visitenddate, '%d %b %Y') as visitenddate,
                CAST({$coc}.visitendtime AS CHAR) as visitendtime,
                (
                    SELECT {$this->qualifiedAlias('nsr')}.description
                    FROM {$this->qualifiedTable('nosalesheader')} {$this->qualifiedAlias('nsh')}
                    INNER JOIN {$this->qualifiedTable('nonservreasons')} {$this->qualifiedAlias('nsr')}
                        ON {$this->qualifiedAlias('nsr')}.code = {$this->qualifiedAlias('nsh')}.nosalereasoncode
                    WHERE {$this->qualifiedAlias('nsh')}.routekey = {$coc}.routekey
                      AND {$this->qualifiedAlias('nsh')}.visitkey = {$coc}.visitkey
                    LIMIT 1
                ) as visited,
                'NON SERVICE' as trantype,
                CONCAT({$coc}.routekey, {$coc}.visitkey) as documentno,
                0 as tranamount,
                0 as receiptpaid,
                DATE_FORMAT({$coc}.visitstartdate, '%d %b %Y') as trandate,
                CONCAT(COALESCE({$cm}.fixedlatitude, 0), ',', COALESCE({$cm}.fixedlongitude, 0)) as standardgps,
                CONCAT(COALESCE({$coc}.latitude, 0), ',', COALESCE({$coc}.longitude, 0)) as actualgps,
                {$this->gpsDistanceExpression('cm', 'coc')} as distance
            ");
    }

    private function salesQuery(array $filters)
    {
        $coc = $this->qualifiedAlias('coc');
        $cm = $this->qualifiedAlias('cm');
        $rm = $this->qualifiedAlias('rm');
        $sm = $this->qualifiedAlias('sm');
        $ih = $this->qualifiedAlias('ih');

        return $this->startDayBaseQuery($filters)
            ->join('customeroperationscontrol as coc', 'coc.routekey', '=', 'sed.routekey')
            ->join('invoiceheader as ih', function ($join) {
                $join->on('ih.routekey', '=', 'coc.routekey')
                    ->on('ih.visitkey', '=', 'coc.visitkey');
            })
            ->join('customermaster as cm', 'cm.customercode', '=', 'coc.customercode')
            ->selectRaw("
                {$coc}.visitkey,
                {$coc}.routekey,
                {$cm}.alternatecode as customercode,
                {$cm}.customername,
                {$cm}.arbcustomername,
                {$rm}.routecode,
                {$rm}.routename,
                {$rm}.arbroutename,
                {$sm}.salesmancode,
                {$sm}.salesmanname1,
                {$sm}.arbsalesmanname1,
                {$cm}.customeraddress1,
                {$cm}.customeraddress2,
                {$cm}.customeraddress3,
                DATE_FORMAT({$coc}.visitstartdate, '%d %b %Y') as visitstartdate,
                DATE_FORMAT({$coc}.visitstartdate, '%Y-%m-%d') as visitstartdate_sort,
                CAST({$coc}.visitstarttime AS CHAR) as visitstarttime,
                DATE_FORMAT({$coc}.visitenddate, '%d %b %Y') as visitenddate,
                CAST({$coc}.visitendtime AS CHAR) as visitendtime,
                '' as visited,
                CONCAT('SALES', CASE WHEN {$ih}.voidflag = 0 THEN '' ELSE ' *VOIDED' END) as trantype,
                {$ih}.documentnumber as documentno,
                CASE WHEN {$ih}.voidflag = 1 THEN 0 ELSE {$ih}.totalinvoiceamount END as tranamount,
                0 as receiptpaid,
                DATE_FORMAT({$ih}.actualtransactiondate, '%d %b %Y') as trandate,
                CONCAT(COALESCE({$cm}.fixedlatitude, 0), ',', COALESCE({$cm}.fixedlongitude, 0)) as standardgps,
                CONCAT(COALESCE({$coc}.latitude, 0), ',', COALESCE({$coc}.longitude, 0)) as actualgps,
                {$this->gpsDistanceExpression('cm', 'coc')} as distance
            ");
    }

    private function orderQuery(array $filters)
    {
        $coc = $this->qualifiedAlias('coc');
        $cm = $this->qualifiedAlias('cm');
        $rm = $this->qualifiedAlias('rm');
        $sm = $this->qualifiedAlias('sm');
        $soh = $this->qualifiedAlias('soh');

        return $this->startDayBaseQuery($filters)
            ->join('customeroperationscontrol as coc', 'coc.routekey', '=', 'sed.routekey')
            ->join('salesorderheader as soh', function ($join) {
                $join->on('soh.routekey', '=', 'coc.routekey')
                    ->on('soh.visitkey', '=', 'coc.visitkey');
            })
            ->join('customermaster as cm', 'cm.customercode', '=', 'coc.customercode')
            ->selectRaw("
                {$coc}.visitkey,
                {$coc}.routekey,
                {$cm}.alternatecode as customercode,
                {$cm}.customername,
                {$cm}.arbcustomername,
                {$rm}.routecode,
                {$rm}.routename,
                {$rm}.arbroutename,
                {$sm}.salesmancode,
                {$sm}.salesmanname1,
                {$sm}.arbsalesmanname1,
                {$cm}.customeraddress1,
                {$cm}.customeraddress2,
                {$cm}.customeraddress3,
                DATE_FORMAT({$coc}.visitstartdate, '%d %b %Y') as visitstartdate,
                DATE_FORMAT({$coc}.visitstartdate, '%Y-%m-%d') as visitstartdate_sort,
                CAST({$coc}.visitstarttime AS CHAR) as visitstarttime,
                DATE_FORMAT({$coc}.visitenddate, '%d %b %Y') as visitenddate,
                CAST({$coc}.visitendtime AS CHAR) as visitendtime,
                '' as visited,
                CONCAT('ORDER', CASE WHEN {$soh}.voidflag = 0 THEN '' ELSE ' *VOIDED' END) as trantype,
                {$soh}.documentnumber as documentno,
                CASE WHEN {$soh}.voidflag = 1 THEN 0 ELSE {$soh}.totalinvoiceamount END as tranamount,
                0 as receiptpaid,
                DATE_FORMAT({$soh}.actualtransactiondate, '%d %b %Y') as trandate,
                CONCAT(COALESCE({$cm}.fixedlatitude, 0), ',', COALESCE({$cm}.fixedlongitude, 0)) as standardgps,
                CONCAT(COALESCE({$coc}.latitude, 0), ',', COALESCE({$coc}.longitude, 0)) as actualgps,
                {$this->gpsDistanceExpression('cm', 'coc')} as distance
            ");
    }

    private function receiptQuery(array $filters)
    {
        $coc = $this->qualifiedAlias('coc');
        $cm = $this->qualifiedAlias('cm');
        $rm = $this->qualifiedAlias('rm');
        $sm = $this->qualifiedAlias('sm');
        $ah = $this->qualifiedAlias('ah');

        return $this->startDayBaseQuery($filters)
            ->join('customeroperationscontrol as coc', 'coc.routekey', '=', 'sed.routekey')
            ->join('arheader as ah', function ($join) {
                $join->on('ah.routekey', '=', 'coc.routekey')
                    ->on('ah.visitkey', '=', 'coc.visitkey');
            })
            ->join('customermaster as cm', 'cm.customercode', '=', 'coc.customercode')
            ->selectRaw("
                {$coc}.visitkey,
                {$coc}.routekey,
                {$cm}.alternatecode as customercode,
                {$cm}.customername,
                {$cm}.arbcustomername,
                {$rm}.routecode,
                {$rm}.routename,
                {$rm}.arbroutename,
                {$sm}.salesmancode,
                {$sm}.salesmanname1,
                {$sm}.arbsalesmanname1,
                {$cm}.customeraddress1,
                {$cm}.customeraddress2,
                {$cm}.customeraddress3,
                DATE_FORMAT({$coc}.visitstartdate, '%d %b %Y') as visitstartdate,
                DATE_FORMAT({$coc}.visitstartdate, '%Y-%m-%d') as visitstartdate_sort,
                CAST({$coc}.visitstarttime AS CHAR) as visitstarttime,
                DATE_FORMAT({$coc}.visitenddate, '%d %b %Y') as visitenddate,
                CAST({$coc}.visitendtime AS CHAR) as visitendtime,
                '' as visited,
                CONCAT('RECEIPT', CASE WHEN {$ah}.voidflag = 0 THEN '' ELSE ' *VOIDED' END) as trantype,
                {$ah}.documentnumber as documentno,
                0 as tranamount,
                CASE WHEN {$ah}.voidflag = 1 THEN 0 ELSE {$ah}.amountpaid END as receiptpaid,
                DATE_FORMAT({$ah}.transactiondate, '%d %b %Y') as trandate,
                CONCAT(COALESCE({$cm}.fixedlatitude, 0), ',', COALESCE({$cm}.fixedlongitude, 0)) as standardgps,
                CONCAT(COALESCE({$coc}.latitude, 0), ',', COALESCE({$coc}.longitude, 0)) as actualgps,
                {$this->gpsDistanceExpression('cm', 'coc')} as distance
            ");
    }

    private function startDayBaseQuery(array $filters)
    {
        return DB::table('startendday as sed')
            ->join('routemaster as rm', 'rm.routecode', '=', 'sed.routecode')
            ->join('salesman as sm', 'sm.salesmancode', '=', 'sed.salesmancode')
            ->when(
                $filters['route_end_date'],
                fn ($builder, $routeEndDate) => $builder->whereDate('sed.routeenddate', $routeEndDate)
            )
            ->when(
                $filters['routecodes'] !== [],
                fn ($builder) => $builder->whereIn('sed.routecode', $filters['routecodes'])
            );
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

        $address = collect([
            $row['customeraddress1'] ?? '',
            $row['customeraddress2'] ?? '',
            $row['customeraddress3'] ?? '',
        ])->filter(fn ($value) => trim((string) $value) !== '')->implode(' - ');

        return [
            'visitkey' => (int) ($row['visitkey'] ?? 0),
            'routekey' => (int) ($row['routekey'] ?? 0),
            'routecode' => (int) ($row['routecode'] ?? 0),
            'route_label' => trim(((int) ($row['routecode'] ?? 0)) . '-' . $routeName),
            'trandate' => $row['trandate'] ?? '',
            'customercode' => (string) ($row['customercode'] ?? ''),
            'customer_label' => trim(((string) ($row['customercode'] ?? '')) . (((string) ($row['customercode'] ?? '')) !== '' && $customerName !== '' ? '-' : '') . $customerName),
            'address' => $address,
            'standardgps' => (string) ($row['standardgps'] ?? ''),
            'actualgps' => (string) ($row['actualgps'] ?? ''),
            'distance' => (float) ($row['distance'] ?? 0),
            'visitstartdate_sort' => $row['visitstartdate_sort'] ?? '',
            'visitstarttime' => substr((string) ($row['visitstarttime'] ?? ''), 0, 5),
            'visitstarttime_sort' => substr((string) ($row['visitstarttime'] ?? ''), 0, 8),
            'visitendtime' => substr((string) ($row['visitendtime'] ?? ''), 0, 5),
            'duration' => $this->timeDiff(
                substr((string) ($row['visitstarttime'] ?? ''), 0, 5),
                substr((string) ($row['visitendtime'] ?? ''), 0, 5)
            ),
            'visit_interval' => '00:00',
            'trantype' => (string) ($row['trantype'] ?? ''),
            'tranamount' => (float) ($row['tranamount'] ?? 0),
            'receiptpaid' => (float) ($row['receiptpaid'] ?? 0),
            'documentno' => (string) ($row['documentno'] ?? ''),
        ];
    }

    private function applyVisitIntervals(Collection $rows): Collection
    {
        return $rows->values()->map(function (array $row, int $index) use ($rows) {
            if ($index === 0) {
                return $row;
            }

            $previous = $rows[$index - 1];
            $row['visit_interval'] = $this->timeDiff(
                (string) ($previous['visitendtime'] ?? ''),
                (string) ($row['visitstarttime'] ?? '')
            );

            return $row;
        });
    }

    private function paginateRows(Collection $rows, int $perPage, int $page, Request $request): LengthAwarePaginator
    {
        $total = $rows->count();
        $page = max($page, 1);
        $items = $rows->forPage($page, $perPage)->values()->all();

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

    private function totals(Collection $rows): array
    {
        return [
            'tranamount' => (float) $rows->sum('tranamount'),
            'receiptpaid' => (float) $rows->sum('receiptpaid'),
        ];
    }

    private function mapExportRow(array $row): array
    {
        $export = [];

        foreach (self::EXPORT_COLUMNS as $label => $key) {
            $value = $row[$key] ?? '';

            if (in_array($key, ['tranamount', 'receiptpaid'], true)) {
                $value = AmountPrecision::format($value);
            }

            if ($key === 'distance') {
                $value = number_format((float) $value, 0, '.', '');
            }

            $export[$label] = $value;
        }

        return $export;
    }

    private function totalsExportRow(array $totals): array
    {
        $row = array_fill_keys(array_keys(self::EXPORT_COLUMNS), '');
        $row['Transaction'] = 'Total';
        $row['Order Collected'] = AmountPrecision::format($totals['tranamount']);
        $row['Receipt Collected'] = AmountPrecision::format($totals['receiptpaid']);

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

    private function compare(mixed $left, mixed $right): int
    {
        if (is_numeric($left) && is_numeric($right)) {
            return $left <=> $right;
        }

        return strcmp((string) $left, (string) $right);
    }

    private function gpsDistanceExpression(string $customerAlias, string $visitAlias): string
    {
        $customer = $this->qualifiedAlias($customerAlias);
        $visit = $this->qualifiedAlias($visitAlias);

        return "ROUND((6371 * 2 * ASIN(SQRT(
            POWER(SIN((COALESCE({$customer}.fixedlatitude, 0) - ABS(COALESCE({$visit}.latitude, 0))) * PI()/180 / 2), 2) +
            COS(COALESCE({$customer}.fixedlatitude, 0) * PI()/180) *
            COS(ABS(COALESCE({$visit}.latitude, 0)) * PI()/180) *
            POWER(SIN((COALESCE({$customer}.fixedlongitude, 0) - COALESCE({$visit}.longitude, 0)) * PI()/180 / 2), 2)
        ))) * 1000)";
    }

    private function timeDiff(string $start, string $end): string
    {
        if ($start === '' || $end === '') {
            return '00:00';
        }

        [$startHour, $startMinute] = array_pad(explode(':', $start), 2, '0');
        [$endHour, $endMinute] = array_pad(explode(':', $end), 2, '0');

        $startMinutes = ((int) $startHour * 60) + (int) $startMinute;
        $endMinutes = ((int) $endHour * 60) + (int) $endMinute;

        if ($startMinutes > $endMinutes) {
            $endMinutes += 24 * 60;
        }

        $diff = max($endMinutes - $startMinutes, 0);

        return sprintf('%02d:%02d', intdiv($diff, 60), $diff % 60);
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

    private function hasTables(array $tables): bool
    {
        foreach ($tables as $table) {
            if (!Schema::hasTable($table)) {
                return false;
            }
        }

        return true;
    }
}
