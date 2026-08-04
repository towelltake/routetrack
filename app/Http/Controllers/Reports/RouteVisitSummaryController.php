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

class RouteVisitSummaryController extends Controller
{
    private const SORT_COLUMNS = [
        'routecode',
        'routestartdate_sort',
        'routeenddate_sort',
        'daytxt',
        'targettovisit',
        'targetvisits',
        'callexceptions',
        'nontargetvisits',
        'totalvisits',
        'schedulesale',
        'schedulenosale',
        'unschedsale',
        'unschedulenosale',
        'effectivevisit',
        'startkms',
        'endkms',
        'kmscovered',
    ];

    private const EXPORT_COLUMNS = [
        'Route' => 'route_label',
        'Route Start Date' => 'routestartdate',
        'Day' => 'daytxt',
        'Route End Date' => 'routeenddate',
        'Scheduled Targets To Visit' => 'targettovisit',
        'Scheduled Targets Visited' => 'targetvisits',
        'Scheduled Targets Not Visited' => 'callexceptions',
        'Un Scheduled Targets Visited' => 'nontargetvisits',
        'Total Visits' => 'totalvisits',
        'Shedule Sale' => 'schedulesale',
        'Shedule No Sale' => 'schedulenosale',
        'Un Shedule Sale' => 'unschedsale',
        'Un Shedule No Sale' => 'unschedulenosale',
        'Effective Visit' => 'effectivevisit',
        'Starting Kms' => 'startkms',
        'Ending Kms' => 'endkms',
        'Total Kms Covered' => 'kmscovered',
    ];

    public function __construct(
        private readonly ReportScopeService $reportScopeService
    ) {}

    public function __invoke(Request $request): Response
    {
        $context = $this->resolveContext($request, true);
        $rows = $this->loadAllRows($context['filters'], $context['sort_by'], $context['sort_dir']);
        $paginator = $this->paginateRows($rows, $context['filters']['per_page'], $context['page'], $request);

        return Inertia::render('reports/transaction-report/RouteVisitSummary', [
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
            'route-visit-summary-' . now()->format('Ymd_His') . '.xls',
            array_keys(self::EXPORT_COLUMNS),
            $exportRows,
            'Route Visit Summary'
        );
    }

    public function exportPdf(Request $request): View
    {
        $context = $this->resolveContext($request, false);
        $rows = $this->loadAllRows($context['filters'], $context['sort_by'], $context['sort_dir']);

        return view('reports.route-visit-summary-pdf', [
            'rows' => $rows,
            'totals' => $this->totals($rows),
            'selectedFilters' => $this->selectedFilterLabels($context['filters'], $context['scope']),
            'amountPrecision' => AmountPrecision::get(),
        ]);
    }

    private function resolveContext(Request $request, bool $withPagination): array
    {
        $user = $request->user();
        abort_unless($user?->hasFormPermission('route visit summary'), 403);

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
        if (!$this->hasTables(['startendday', 'routemaster', 'salesman', 'routesequencecustomerstatus', 'invoiceheader'])) {
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

                foreach (['routecode', 'routestartdate_sort', 'routeenddate_sort'] as $fallback) {
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
        $sed = $this->qualifiedAlias('sed');
        $rm = $this->qualifiedAlias('rm');
        $sm = $this->qualifiedAlias('sm');

        $scheduledCount = "(SELECT COUNT(*) FROM " . DB::getTablePrefix() . "routesequencecustomerstatus rsc WHERE rsc.routekey = {$sed}.routekey AND COALESCE(rsc.schelduledflag, 0) = 1)";
        $scheduledVisited = "(SELECT COUNT(*) FROM " . DB::getTablePrefix() . "routesequencecustomerstatus rsc WHERE rsc.routekey = {$sed}.routekey AND COALESCE(rsc.schelduledflag, 0) = 1 AND COALESCE(rsc.servicedflag, 0) <> 0)";
        $unscheduledVisited = "(SELECT COUNT(*) FROM " . DB::getTablePrefix() . "routesequencecustomerstatus rsc WHERE rsc.routekey = {$sed}.routekey AND COALESCE(rsc.schelduledflag, 0) = 0 AND COALESCE(rsc.servicedflag, 0) <> 0)";
        $scheduledSale = "(SELECT COUNT(*) FROM " . DB::getTablePrefix() . "routesequencecustomerstatus rsc WHERE rsc.routekey = {$sed}.routekey AND COALESCE(rsc.schelduledflag, 0) = 1 AND COALESCE(rsc.servicedflag, 0) = 1)";
        $unscheduledSale = "(SELECT COUNT(*) FROM " . DB::getTablePrefix() . "routesequencecustomerstatus rsc WHERE rsc.routekey = {$sed}.routekey AND COALESCE(rsc.schelduledflag, 0) = 0 AND COALESCE(rsc.servicedflag, 0) = 1)";
        $scheduledNoSale = "(SELECT COUNT(*) FROM " . DB::getTablePrefix() . "routesequencecustomerstatus rsc WHERE rsc.routekey = {$sed}.routekey AND COALESCE(rsc.schelduledflag, 0) = 1 AND COALESCE(rsc.servicedflag, 0) = 2)";
        $unscheduledNoSale = "(SELECT COUNT(*) FROM " . DB::getTablePrefix() . "routesequencecustomerstatus rsc WHERE rsc.routekey = {$sed}.routekey AND COALESCE(rsc.schelduledflag, 0) = 0 AND COALESCE(rsc.servicedflag, 0) = 2)";
        $unscheduledInvoiceCustomers = "(SELECT COUNT(ih.invoicenumber) FROM " . DB::getTablePrefix() . "invoiceheader ih WHERE ih.routekey = {$sed}.routekey AND ih.voidflag = 0 AND ih.customercode NOT IN (SELECT DISTINCT rsc2.customercode FROM " . DB::getTablePrefix() . "routesequencecustomerstatus rsc2 WHERE rsc2.routekey = {$sed}.routekey))";

        return DB::table('startendday as sed')
            ->join('routemaster as rm', 'sed.routecode', '=', 'rm.routecode')
            ->join('salesman as sm', 'sed.salesmancode', '=', 'sm.salesmancode')
            ->when(
                $filters['transaction_date_from'],
                fn ($builder, $date) => $builder->whereDate('sed.routeenddate', '>=', $date)
            )
            ->when(
                $filters['transaction_date_to'],
                fn ($builder, $date) => $builder->whereDate('sed.routeenddate', '<=', $date)
            )
            ->when(
                $filters['routecodes'] !== [],
                fn ($builder) => $builder->whereIn('sed.routecode', $filters['routecodes'])
            )
            ->selectRaw("
                {$sed}.routekey,
                {$rm}.routecode,
                {$rm}.routename,
                {$rm}.arbroutename,
                DATE_FORMAT({$sed}.routestartdate, '%d-%b-%Y') as routestartdate,
                DATE({$sed}.routestartdate) as routestartdate_sort,
                DATE_FORMAT({$sed}.routeenddate, '%d-%b-%Y') as routeenddate,
                DATE({$sed}.routeenddate) as routeenddate_sort,
                DATE_FORMAT({$sed}.routeenddate, '%W') as daytxt,
                {$scheduledCount} as targettovisit,
                {$scheduledVisited} as targetvisits,
                (({$unscheduledInvoiceCustomers}) + ({$unscheduledVisited})) as nontargetvisits,
                {$scheduledSale} as schedulesale,
                {$scheduledNoSale} as schedulenosale,
                (({$unscheduledInvoiceCustomers}) + ({$unscheduledSale})) as unschedsale,
                {$unscheduledNoSale} as unschedulenosale,
                COALESCE({$sed}.routestartodometer, 0) as startkms,
                COALESCE({$sed}.routeendodometer, 0) as endkms
            ");
    }

    private function transformRow(array $row): array
    {
        $isArabic = app()->getLocale() === 'ar';
        $routeName = $isArabic
            ? ($row['arbroutename'] ?? $row['routename'] ?? '')
            : ($row['routename'] ?? $row['arbroutename'] ?? '');

        $targetToVisit = (float) ($row['targettovisit'] ?? 0);
        $targetVisits = (float) ($row['targetvisits'] ?? 0);
        $nonTargetVisits = (float) ($row['nontargetvisits'] ?? 0);
        $scheduleSale = (float) ($row['schedulesale'] ?? 0);
        $scheduleNoSale = (float) ($row['schedulenosale'] ?? 0);
        $unschedSale = (float) ($row['unschedsale'] ?? 0);
        $unschedNoSale = (float) ($row['unschedulenosale'] ?? 0);
        $startKms = (float) ($row['startkms'] ?? 0);
        $endKms = (float) ($row['endkms'] ?? 0);
        $kmsCovered = max($endKms - $startKms, 0);

        return [
            'routekey' => (int) ($row['routekey'] ?? 0),
            'routecode' => (int) ($row['routecode'] ?? 0),
            'route_label' => trim(((int) ($row['routecode'] ?? 0)) . ' - ' . $routeName),
            'routestartdate' => (string) ($row['routestartdate'] ?? ''),
            'routestartdate_sort' => (string) ($row['routestartdate_sort'] ?? ''),
            'daytxt' => (string) ($row['daytxt'] ?? ''),
            'routeenddate' => (string) ($row['routeenddate'] ?? ''),
            'routeenddate_sort' => (string) ($row['routeenddate_sort'] ?? ''),
            'targettovisit' => $targetToVisit,
            'targetvisits' => $targetVisits,
            'callexceptions' => max($targetToVisit - $targetVisits, 0),
            'nontargetvisits' => $nonTargetVisits,
            'totalvisits' => $targetVisits + $nonTargetVisits,
            'schedulesale' => $scheduleSale,
            'schedulenosale' => $scheduleNoSale,
            'unschedsale' => $unschedSale,
            'unschedulenosale' => $unschedNoSale,
            'effectivevisit' => $scheduleSale + $unschedSale,
            'startkms' => $startKms,
            'endkms' => $endKms,
            'kmscovered' => $kmsCovered,
        ];
    }

    private function totals(Collection $rows): array
    {
        return [
            'targettovisit' => (float) $rows->sum('targettovisit'),
            'targetvisits' => (float) $rows->sum('targetvisits'),
            'callexceptions' => (float) $rows->sum('callexceptions'),
            'nontargetvisits' => (float) $rows->sum('nontargetvisits'),
            'totalvisits' => (float) $rows->sum('totalvisits'),
            'schedulesale' => (float) $rows->sum('schedulesale'),
            'schedulenosale' => (float) $rows->sum('schedulenosale'),
            'unschedsale' => (float) $rows->sum('unschedsale'),
            'unschedulenosale' => (float) $rows->sum('unschedulenosale'),
            'effectivevisit' => (float) $rows->sum('effectivevisit'),
            'startkms' => (float) $rows->sum('startkms'),
            'endkms' => (float) $rows->sum('endkms'),
            'kmscovered' => (float) $rows->sum('kmscovered'),
        ];
    }

    private function mapExportRow(array $row): array
    {
        $export = [];

        foreach (self::EXPORT_COLUMNS as $label => $key) {
            $export[$label] = $row[$key] ?? '';
        }

        return $export;
    }

    private function totalsExportRow(array $totals): array
    {
        $row = array_fill_keys(array_keys(self::EXPORT_COLUMNS), '');
        $row['Route End Date'] = 'Total';
        foreach (['Scheduled Targets To Visit' => 'targettovisit', 'Scheduled Targets Visited' => 'targetvisits', 'Scheduled Targets Not Visited' => 'callexceptions', 'Un Scheduled Targets Visited' => 'nontargetvisits', 'Total Visits' => 'totalvisits', 'Shedule Sale' => 'schedulesale', 'Shedule No Sale' => 'schedulenosale', 'Un Shedule Sale' => 'unschedsale', 'Un Shedule No Sale' => 'unschedulenosale', 'Effective Visit' => 'effectivevisit', 'Starting Kms' => 'startkms', 'Ending Kms' => 'endkms', 'Total Kms Covered' => 'kmscovered'] as $label => $key) {
            $row[$label] = $totals[$key];
        }

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

    private function qualifiedAlias(string $alias): string
    {
        return DB::getTablePrefix() . $alias;
    }
}
