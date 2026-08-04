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

class RouteDepositSummaryController extends Controller
{
    private const SORT_COLUMNS = [
        'routeenddate_sort',
        'routecode',
        'type',
        'paytype',
        'invoicenumber_sort',
        'transactiontime_sort',
        'againstinv_sort',
        'saleinvoiceddate_sort',
        'salesmancode',
        'reportcustcode',
        'customername_sort',
        'bankname_sort',
        'checknumber_sort',
        'amount',
    ];

    private const EXPORT_COLUMNS = [
        'Route End Date' => 'routeenddate',
        'Route Code' => 'route_label',
        'Invoice Type' => 'type',
        'Payment Type' => 'paytype',
        'Invoice Number' => 'invoicenumber',
        'Transaction Time' => 'transactiontime',
        'Against Invoice' => 'againstinv',
        'Invoiced Date' => 'saleinvoiceddate',
        'Invoiced Salesman' => 'salesmancode',
        'Customer Code' => 'reportcustcode',
        'Customer Name' => 'customer_label',
        'Bank Name' => 'bankname',
        'Check Number' => 'checknumber',
        'Draw Date' => 'drawdate',
        'Amount' => 'amount',
    ];

    public function __construct(
        private readonly ReportScopeService $reportScopeService
    ) {}

    public function __invoke(Request $request): Response
    {
        $context = $this->resolveContext($request, true);
        $rows = $this->loadAllRows($context['filters'], $context['sort_by'], $context['sort_dir']);
        $paginator = $this->paginateRows($rows, $context['filters']['per_page'], $context['page'], $request);

        return Inertia::render('reports/daily-report/RouteDepositSummary', [
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
            'route-deposit-summary-' . now()->format('Ymd_His') . '.xls',
            array_keys(self::EXPORT_COLUMNS),
            $exportRows,
            'Route Deposit Summary'
        );
    }

    public function exportPdf(Request $request): View
    {
        $context = $this->resolveContext($request, false);
        $rows = $this->loadAllRows($context['filters'], $context['sort_by'], $context['sort_dir']);

        return view('reports.route-deposit-summary-pdf', [
            'rows' => $rows,
            'totals' => $this->totals($rows),
            'selectedFilters' => $this->selectedFilterLabels($context['filters'], $context['scope']),
            'amountPrecision' => AmountPrecision::get(),
        ]);
    }

    private function resolveContext(Request $request, bool $withPagination): array
    {
        $user = $request->user();
        abort_unless($user?->hasFormPermission('route deposit summary'), 403);

        $rules = [
            'route_end_date_from' => ['nullable', 'date'],
            'route_end_date_to' => ['nullable', 'date'],
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
            'route_end_date_from' => $validated['route_end_date_from'] ?? $today,
            'route_end_date_to' => $validated['route_end_date_to'] ?? $today,
            'cmpycode' => $this->nullableInt($validated['cmpycode'] ?? null),
            'regionmstcode' => $this->nullableInt($validated['regionmstcode'] ?? null),
            'depotcode' => $this->nullableInt($validated['depotcode'] ?? null),
            'areacode' => $this->nullableInt($validated['areacode'] ?? null),
            'subareacode' => $this->nullableInt($validated['subareacode'] ?? null),
            'routecode' => $this->nullableInt($validated['routecode'] ?? null),
            'per_page' => $withPagination ? (int) ($validated['per_page'] ?? 25) : 100000,
        ];

        if (($filters['route_end_date_from'] ?? null) && ($filters['route_end_date_to'] ?? null)
            && $filters['route_end_date_from'] > $filters['route_end_date_to']) {
            [$filters['route_end_date_from'], $filters['route_end_date_to']] = [$filters['route_end_date_to'], $filters['route_end_date_from']];
        }

        $scope = $this->reportScopeService->resolve($user, $filters);
        $filters = $this->normalizeFiltersAgainstScope($filters, $scope['rows']);
        $scope = $this->reportScopeService->resolve($user, $filters);
        $filters['routecodes'] = $scope['routecodes'];
        $filters['scope_limited'] = $scope['limited'];

        $requestedSortBy = $validated['sort_by'] ?? 'routeenddate_sort';

        return [
            'filters' => $filters,
            'scope' => $scope,
            'sort_by' => in_array($requestedSortBy, self::SORT_COLUMNS, true) ? $requestedSortBy : 'routeenddate_sort',
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

                foreach (['routeenddate_sort', 'routecode', 'type', 'paytype', 'invoicenumber_sort'] as $fallback) {
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

        if ($this->hasTables(['invoiceheader', 'cashcheckdetail', 'customermaster', 'routemaster', 'salesman', 'startendday'])) {
            $parts[] = $this->invoiceQuery($filters);
        }

        if ($this->hasTables(['arheader', 'ardetail', 'cashcheckdetail', 'customermaster', 'routemaster', 'salesman', 'startendday'])) {
            $parts[] = $this->arQuery($filters);
            $parts[] = $this->advancePaymentQuery($filters);
        }

        return $parts;
    }

    private function invoiceQuery(array $filters)
    {
        $sed = $this->qualifiedAlias('sed');
        $ih = $this->qualifiedAlias('ih');
        $ccd = $this->qualifiedAlias('ccd');
        $cm = $this->qualifiedAlias('cm');
        $rm = $this->qualifiedAlias('rm');
        $sm = $this->qualifiedAlias('sm');
        $bm = $this->qualifiedAlias('bm');

        return DB::table('startendday as sed')
            ->join('invoiceheader as ih', function ($join) {
                $join->on('ih.routekey', '=', 'sed.routekey')
                    ->where('ih.voidflag', 0);
            })
            ->join('cashcheckdetail as ccd', function ($join) {
                $join->on('ccd.routekey', '=', 'ih.routekey')
                    ->on('ccd.visitkey', '=', 'ih.visitkey');
            })
            ->join('customermaster as cm', 'cm.customercode', '=', 'ih.customercode')
            ->join('routemaster as rm', 'rm.routecode', '=', 'ih.routecode')
            ->join('salesman as sm', 'sm.salesmancode', '=', 'ih.salesmancode')
            ->leftJoin('bankmaster as bm', 'bm.bankcode', '=', 'ccd.bankcode')
            ->when(
                $filters['route_end_date_from'],
                fn ($builder, $date) => $builder->whereDate('sed.routeenddate', '>=', $date)
            )
            ->when(
                $filters['route_end_date_to'],
                fn ($builder, $date) => $builder->whereDate('sed.routeenddate', '<=', $date)
            )
            ->when(
                $filters['routecodes'] !== [],
                fn ($builder) => $builder->whereIn('ih.routecode', $filters['routecodes'])
            )
            ->selectRaw("
                'INVOICE' as type,
                DATE_FORMAT({$sed}.routeenddate, '%d %b %Y') as routeenddate,
                DATE_FORMAT({$sed}.routeenddate, '%Y-%m-%d') as routeenddate_sort,
                {$ih}.routekey,
                {$ih}.visitkey,
                {$ih}.invoicenumber,
                CAST({$ih}.invoicenumber AS CHAR) as invoicenumber_sort,
                DATE_FORMAT({$ih}.actualtransactiondate, '%d %b %Y') as transactiondate,
                CAST({$ih}.transactiontime AS CHAR) as transactiontime,
                CAST({$ih}.transactiontime AS CHAR) as transactiontime_sort,
                {$ih}.salesmancode as invoicedby,
                '' as againstinv,
                '' as againstinv_sort,
                DATE_FORMAT({$ih}.actualtransactiondate, '%d %b %Y') as saleinvoiceddate,
                DATE_FORMAT({$ih}.actualtransactiondate, '%Y-%m-%d') as saleinvoiceddate_sort,
                {$ih}.customercode,
                {$cm}.reportcustcode,
                {$cm}.customername,
                {$cm}.arbcustomername,
                {$ih}.routecode,
                {$rm}.routename,
                {$rm}.arbroutename,
                {$ih}.salesmancode,
                {$sm}.salesmanname1,
                {$sm}.arbsalesmanname1,
                {$ccd}.typecode,
                CASE WHEN {$ccd}.typecode = 0 THEN 'CASH' ELSE 'CHEQUE' END as paytype,
                CASE WHEN {$ccd}.typecode = 0 THEN '' ELSE CAST({$ccd}.checknumber AS CHAR) END as checknumber,
                CASE WHEN {$ccd}.typecode = 0 THEN '' ELSE CAST({$ccd}.checknumber AS CHAR) END as checknumber_sort,
                CASE WHEN {$ccd}.typecode = 0 THEN '' ELSE DATE_FORMAT({$ccd}.checkdate, '%d %b %Y') END as checkdate,
                CASE WHEN {$ccd}.typecode = 0 THEN '' ELSE COALESCE({$bm}.bankname, '') END as bankname,
                CASE WHEN {$ccd}.typecode = 0 THEN '' ELSE COALESCE({$bm}.arbbankname, '') END as arbbankname,
                CASE WHEN {$ccd}.typecode = 0 THEN '' ELSE COALESCE({$bm}.bankname, '') END as bankname_sort,
                COALESCE({$ccd}.amount, 0) as amount
            ");
    }

    private function arQuery(array $filters)
    {
        $sed = $this->qualifiedAlias('sed');
        $arh = $this->qualifiedAlias('arh');
        $ard = $this->qualifiedAlias('ard');
        $ccd = $this->qualifiedAlias('ccd');
        $cm = $this->qualifiedAlias('cm');
        $rm = $this->qualifiedAlias('rm');
        $sm = $this->qualifiedAlias('sm');
        $bm = $this->qualifiedAlias('bm');
        $ih1 = $this->qualifiedAlias('ih1');

        return DB::table('startendday as sed')
            ->join('arheader as arh', function ($join) {
                $join->on('arh.routekey', '=', 'sed.routekey')
                    ->where('arh.voidflag', 0)
                    ->where('arh.advancepaymentflag', 0);
            })
            ->join('ardetail as ard', function ($join) {
                $join->on('ard.routekey', '=', 'arh.routekey')
                    ->on('ard.visitkey', '=', 'arh.visitkey');
            })
            ->join('cashcheckdetail as ccd', function ($join) {
                $join->on('ccd.routekey', '=', 'arh.routekey')
                    ->on('ccd.visitkey', '=', 'arh.visitkey');
            })
            ->join('customermaster as cm', 'cm.customercode', '=', 'arh.customercode')
            ->join('routemaster as rm', 'rm.routecode', '=', 'arh.routecode')
            ->join('salesman as sm', 'sm.salesmancode', '=', 'arh.salesmancode')
            ->leftJoin('bankmaster as bm', 'bm.bankcode', '=', 'ccd.bankcode')
            ->when(
                $filters['route_end_date_from'],
                fn ($builder, $date) => $builder->whereDate('sed.routeenddate', '>=', $date)
            )
            ->when(
                $filters['route_end_date_to'],
                fn ($builder, $date) => $builder->whereDate('sed.routeenddate', '<=', $date)
            )
            ->when(
                $filters['routecodes'] !== [],
                fn ($builder) => $builder->whereIn('arh.routecode', $filters['routecodes'])
            )
            ->selectRaw("
                'AR' as type,
                DATE_FORMAT({$sed}.routeenddate, '%d %b %Y') as routeenddate,
                DATE_FORMAT({$sed}.routeenddate, '%Y-%m-%d') as routeenddate_sort,
                {$arh}.routekey,
                {$arh}.visitkey,
                {$arh}.invoicenumber,
                CAST({$arh}.invoicenumber AS CHAR) as invoicenumber_sort,
                DATE_FORMAT({$arh}.transactiondate, '%d %b %Y') as transactiondate,
                CAST({$arh}.transactiontime AS CHAR) as transactiontime,
                CAST({$arh}.transactiontime AS CHAR) as transactiontime_sort,
                (
                    SELECT {$ih1}.salesmancode
                    FROM {$this->qualifiedTable('invoiceheader')} {$ih1}
                    WHERE {$ih1}.invoicenumber = {$ard}.invoicenumber
                    LIMIT 1
                ) as invoicedby,
                {$ard}.alternateinvoicenumber as againstinv,
                CAST({$ard}.alternateinvoicenumber AS CHAR) as againstinv_sort,
                DATE_FORMAT({$ard}.invoicedate, '%d %b %Y') as saleinvoiceddate,
                DATE_FORMAT({$ard}.invoicedate, '%Y-%m-%d') as saleinvoiceddate_sort,
                {$arh}.customercode,
                {$cm}.reportcustcode,
                {$cm}.customername,
                {$cm}.arbcustomername,
                {$arh}.routecode,
                {$rm}.routename,
                {$rm}.arbroutename,
                {$arh}.salesmancode,
                {$sm}.salesmanname1,
                {$sm}.arbsalesmanname1,
                {$ccd}.typecode,
                CASE WHEN {$ccd}.typecode = 0 THEN 'CASH' ELSE 'CHEQUE' END as paytype,
                CASE WHEN {$ccd}.typecode = 0 THEN '' ELSE CAST({$ccd}.checknumber AS CHAR) END as checknumber,
                CASE WHEN {$ccd}.typecode = 0 THEN '' ELSE CAST({$ccd}.checknumber AS CHAR) END as checknumber_sort,
                CASE WHEN {$ccd}.typecode = 0 THEN '' ELSE DATE_FORMAT({$ccd}.checkdate, '%d %b %Y') END as checkdate,
                CASE WHEN {$ccd}.typecode = 0 THEN '' ELSE COALESCE({$bm}.bankname, '') END as bankname,
                CASE WHEN {$ccd}.typecode = 0 THEN '' ELSE COALESCE({$bm}.arbbankname, '') END as arbbankname,
                CASE WHEN {$ccd}.typecode = 0 THEN '' ELSE COALESCE({$bm}.bankname, '') END as bankname_sort,
                COALESCE({$ard}.amountpaid, 0) as amount
            ");
    }

    private function advancePaymentQuery(array $filters)
    {
        $sed = $this->qualifiedAlias('sed');
        $arh = $this->qualifiedAlias('arh');
        $ccd = $this->qualifiedAlias('ccd');
        $cm = $this->qualifiedAlias('cm');
        $rm = $this->qualifiedAlias('rm');
        $sm = $this->qualifiedAlias('sm');
        $bm = $this->qualifiedAlias('bm');

        return DB::table('startendday as sed')
            ->join('arheader as arh', function ($join) {
                $join->on('arh.routekey', '=', 'sed.routekey')
                    ->where('arh.voidflag', 0)
                    ->where('arh.advancepaymentflag', '>', 0);
            })
            ->join('cashcheckdetail as ccd', function ($join) {
                $join->on('ccd.routekey', '=', 'arh.routekey')
                    ->on('ccd.visitkey', '=', 'arh.visitkey');
            })
            ->join('customermaster as cm', 'cm.customercode', '=', 'arh.customercode')
            ->join('routemaster as rm', 'rm.routecode', '=', 'arh.routecode')
            ->join('salesman as sm', 'sm.salesmancode', '=', 'arh.salesmancode')
            ->leftJoin('bankmaster as bm', 'bm.bankcode', '=', 'ccd.bankcode')
            ->when(
                $filters['route_end_date_from'],
                fn ($builder, $date) => $builder->whereDate('sed.routeenddate', '>=', $date)
            )
            ->when(
                $filters['route_end_date_to'],
                fn ($builder, $date) => $builder->whereDate('sed.routeenddate', '<=', $date)
            )
            ->when(
                $filters['routecodes'] !== [],
                fn ($builder) => $builder->whereIn('arh.routecode', $filters['routecodes'])
            )
            ->selectRaw("
                'ADVANCE PAYMENT' as type,
                DATE_FORMAT({$sed}.routeenddate, '%d %b %Y') as routeenddate,
                DATE_FORMAT({$sed}.routeenddate, '%Y-%m-%d') as routeenddate_sort,
                {$arh}.routekey,
                {$arh}.visitkey,
                {$arh}.invoicenumber,
                CAST({$arh}.invoicenumber AS CHAR) as invoicenumber_sort,
                DATE_FORMAT({$arh}.transactiondate, '%d %b %Y') as transactiondate,
                CAST({$arh}.transactiontime AS CHAR) as transactiontime,
                CAST({$arh}.transactiontime AS CHAR) as transactiontime_sort,
                '' as invoicedby,
                '' as againstinv,
                '' as againstinv_sort,
                '' as saleinvoiceddate,
                '' as saleinvoiceddate_sort,
                {$arh}.customercode,
                {$cm}.reportcustcode,
                {$cm}.customername,
                {$cm}.arbcustomername,
                {$arh}.routecode,
                {$rm}.routename,
                {$rm}.arbroutename,
                {$arh}.salesmancode,
                {$sm}.salesmanname1,
                {$sm}.arbsalesmanname1,
                {$ccd}.typecode,
                CASE WHEN {$ccd}.typecode = 0 THEN 'CASH' ELSE 'CHEQUE' END as paytype,
                CASE WHEN {$ccd}.typecode = 0 THEN '' ELSE CAST({$ccd}.checknumber AS CHAR) END as checknumber,
                CASE WHEN {$ccd}.typecode = 0 THEN '' ELSE CAST({$ccd}.checknumber AS CHAR) END as checknumber_sort,
                CASE WHEN {$ccd}.typecode = 0 THEN '' ELSE DATE_FORMAT({$ccd}.checkdate, '%d %b %Y') END as checkdate,
                CASE WHEN {$ccd}.typecode = 0 THEN '' ELSE COALESCE({$bm}.bankname, '') END as bankname,
                CASE WHEN {$ccd}.typecode = 0 THEN '' ELSE COALESCE({$bm}.arbbankname, '') END as arbbankname,
                CASE WHEN {$ccd}.typecode = 0 THEN '' ELSE COALESCE({$bm}.bankname, '') END as bankname_sort,
                COALESCE({$arh}.amountpaid, 0) as amount
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
        $bankName = $isArabic
            ? ($row['arbbankname'] ?? $row['bankname'] ?? '')
            : ($row['bankname'] ?? $row['arbbankname'] ?? '');

        return [
            'routeenddate' => (string) ($row['routeenddate'] ?? ''),
            'routeenddate_sort' => (string) ($row['routeenddate_sort'] ?? ''),
            'routekey' => (int) ($row['routekey'] ?? 0),
            'visitkey' => (int) ($row['visitkey'] ?? 0),
            'invoicenumber' => $this->identifier($row['invoicenumber'] ?? ''),
            'invoicenumber_sort' => (string) ($row['invoicenumber_sort'] ?? ''),
            'routecode' => (int) ($row['routecode'] ?? 0),
            'route_label' => trim(((int) ($row['routecode'] ?? 0)) . ' - ' . $routeName),
            'type' => (string) ($row['type'] ?? ''),
            'paytype' => (string) ($row['paytype'] ?? ''),
            'transactiontime' => $this->formatTime($row['transactiontime'] ?? null),
            'transactiontime_sort' => (string) ($row['transactiontime_sort'] ?? ''),
            'againstinv' => $this->identifier($row['againstinv'] ?? ''),
            'againstinv_sort' => (string) ($row['againstinv_sort'] ?? ''),
            'saleinvoiceddate' => (string) ($row['saleinvoiceddate'] ?? ''),
            'saleinvoiceddate_sort' => (string) ($row['saleinvoiceddate_sort'] ?? ''),
            'salesmancode' => $this->identifier($row['salesmancode'] ?? ''),
            'reportcustcode' => $this->identifier($row['reportcustcode'] ?? ''),
            'customer_label' => $customerName,
            'customername_sort' => mb_strtolower($customerName),
            'bankname' => $bankName,
            'bankname_sort' => mb_strtolower($bankName),
            'checknumber' => $this->identifier($row['checknumber'] ?? ''),
            'checknumber_sort' => (string) ($row['checknumber_sort'] ?? ''),
            'drawdate' => '',
            'amount' => (float) ($row['amount'] ?? 0),
        ];
    }

    private function totals(Collection $rows): array
    {
        return [
            'amount' => (float) $rows->sum('amount'),
        ];
    }

    private function mapExportRow(array $row): array
    {
        $export = [];

        foreach (self::EXPORT_COLUMNS as $label => $key) {
            $value = $row[$key] ?? '';
            if ($key === 'amount') {
                $value = AmountPrecision::format($value);
            }
            $export[$label] = $value;
        }

        return $export;
    }

    private function totalsExportRow(array $totals): array
    {
        $row = array_fill_keys(array_keys(self::EXPORT_COLUMNS), '');
        $row['Draw Date'] = 'Total';
        $row['Amount'] = AmountPrecision::format($totals['amount']);

        return $row;
    }

    private function selectedFilterLabels(array $filters, array $scope): array
    {
        return [
            'Route End Date - From' => $filters['route_end_date_from'] ?: 'All',
            'Route End Date - To' => $filters['route_end_date_to'] ?: 'All',
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

    private function qualifiedTable(string $table): string
    {
        return DB::getTablePrefix() . $table;
    }
}
