<?php

namespace App\Http\Controllers\Reports;

use App\Http\Controllers\Controller;
use App\Services\Reports\ReportScopeService;
use App\Support\ExcelXmlWorkbook;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\View\View;
use Inertia\Response;
use Symfony\Component\HttpFoundation\Response as HttpResponse;

class SurveyTrackingController extends Controller
{
    private const SORT_COLUMNS = [
        'route_label',
        'salesman_label',
        'customer_code',
        'customer_name',
        'visit_date_sort',
        'visit_time_sort',
        'survey_description',
        'survey_response',
    ];

    private const EXPORT_COLUMNS = [
        'Route' => 'route_label',
        'Salesman' => 'salesman_label',
        'Customer Code' => 'customer_code',
        'Customer Name' => 'customer_name',
        'Date' => 'visit_date',
        'Time' => 'visit_time',
        'Survey Description' => 'survey_description',
        'Survey Response' => 'survey_response',
    ];

    public function __construct(
        private readonly ReportScopeService $reportScopeService
    ) {}

    public function __invoke(Request $request): Response
    {
        $context = $this->resolveContext($request, true);
        $rows = $this->loadAllRows($context['filters'], $context['sort_by'], $context['sort_dir']);
        $paginator = $this->paginateRows($rows, $context['filters']['per_page'], $context['page'], $request);

        return inertia('reports/merchandizing-report/SurveyTracking', [
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
        $exportRows = $rows->map(fn (array $row) => $this->mapExportRow($row))->all();

        return ExcelXmlWorkbook::download(
            'survey-tracking-' . now()->format('Ymd_His') . '.xls',
            array_keys(self::EXPORT_COLUMNS),
            $exportRows,
            'Survey Tracking'
        );
    }

    public function exportPdf(Request $request): View
    {
        $context = $this->resolveContext($request, false);
        $rows = $this->loadAllRows($context['filters'], $context['sort_by'], $context['sort_dir']);

        return view('reports.survey-tracking-pdf', [
            'rows' => $rows,
            'selectedFilters' => $this->selectedFilterLabels($context['filters'], $context['scope']),
        ]);
    }

    private function resolveContext(Request $request, bool $withPagination): array
    {
        $user = $request->user();
        abort_unless($user?->hasFormPermission('survey tracking'), 403);

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

        $requestedSortBy = $validated['sort_by'] ?? 'visit_date_sort';

        return [
            'filters' => $filters,
            'scope' => $scope,
            'sort_by' => in_array($requestedSortBy, self::SORT_COLUMNS, true) ? $requestedSortBy : 'visit_date_sort',
            'sort_dir' => $validated['sort_dir'] ?? 'desc',
            'page' => max((int) ($validated['page'] ?? 1), 1),
        ];
    }

    private function loadAllRows(array $filters, string $sortBy, string $sortDir): Collection
    {
        if (!$this->hasTables(['customeroperationscontrol', 'surveyauditdetail', 'customersurveydefinition', 'salesman', 'routemaster', 'customermaster'])) {
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

                foreach (['visit_date_sort', 'visit_time_sort', 'route_label', 'salesman_label', 'customer_code'] as $fallback) {
                    $fallbackCompare = $this->compare($left[$fallback] ?? null, $right[$fallback] ?? null);
                    if ($fallbackCompare !== 0) {
                        return $sortDir === 'desc' && in_array($fallback, ['visit_date_sort', 'visit_time_sort'], true)
                            ? -$fallbackCompare
                            : $fallbackCompare;
                    }
                }

                return 0;
            })
            ->values();
    }

    private function baseQuery(array $filters)
    {
        $coc = $this->qualifiedAlias('coc');
        $sad = $this->qualifiedAlias('sad');
        $csd = $this->qualifiedAlias('csd');
        $sm = $this->qualifiedAlias('sm');
        $rm = $this->qualifiedAlias('rm');
        $cm = $this->qualifiedAlias('cm');

        $routeCodeSelect = Schema::hasColumn('routemaster', 'alternateroutecode')
            ? "COALESCE(NULLIF(TRIM({$rm}.alternateroutecode), ''), CAST({$rm}.routecode AS CHAR))"
            : "CAST({$rm}.routecode AS CHAR)";
        $salesmanCodeSelect = Schema::hasColumn('salesman', 'alternatesalesmancode')
            ? "COALESCE(NULLIF(TRIM({$sm}.alternatesalesmancode), ''), CAST({$sm}.salesmancode AS CHAR))"
            : "CAST({$sm}.salesmancode AS CHAR)";
        $customerCodeSelect = Schema::hasColumn('customermaster', 'alternatecode')
            ? "REPLACE(COALESCE({$cm}.alternatecode, CAST({$cm}.customercode AS CHAR)), '-', '')"
            : "CAST({$cm}.customercode AS CHAR)";

        return DB::table('customeroperationscontrol as coc')
            ->join('surveyauditdetail as sad', function ($join) {
                $join->on('coc.routekey', '=', 'sad.routekey')
                    ->on('coc.visitkey', '=', 'sad.visitkey');
            })
            ->join('customersurveydefinition as csd', function ($join) {
                $join->on('sad.surveyindex', '=', 'csd.surveyindex')
                    ->on('sad.surveydefkey', '=', 'csd.surveydefkey');
            })
            ->join('salesman as sm', 'coc.salesmancode', '=', 'sm.salesmancode')
            ->join('routemaster as rm', 'coc.routecode', '=', 'rm.routecode')
            ->join('customermaster as cm', 'coc.customercode', '=', 'cm.customercode')
            ->when(
                $filters['transaction_date_from'],
                fn ($builder, $date) => $builder->whereDate('coc.visitstartdate', '>=', $date)
            )
            ->when(
                $filters['transaction_date_to'],
                fn ($builder, $date) => $builder->whereDate('coc.visitstartdate', '<=', $date)
            )
            ->when(
                $filters['routecodes'] !== [],
                fn ($builder) => $builder->whereIn('coc.routecode', $filters['routecodes'])
            )
            ->selectRaw("
                {$coc}.routekey,
                {$coc}.visitkey,
                {$coc}.routecode as routecode_value,
                {$routeCodeSelect} as routecode_display,
                COALESCE({$rm}.routename, '') as routename,
                COALESCE({$rm}.arbroutename, '') as arbroutename,
                {$coc}.salesmancode as salesmancode_value,
                {$salesmanCodeSelect} as salesmancode_display,
                COALESCE({$sm}.salesmanname1, '') as salesmanname1,
                COALESCE({$sm}.arbsalesmanname1, '') as arbsalesmanname1,
                {$coc}.customercode as customercode_value,
                {$customerCodeSelect} as customercode_display,
                COALESCE({$cm}.customername, '') as customername,
                COALESCE({$cm}.arbcustomername, '') as arbcustomername,
                DATE_FORMAT({$coc}.visitstartdate, '%d %b %Y') as visit_date,
                DATE({$coc}.visitstartdate) as visit_date_sort,
                COALESCE({$coc}.visitstarttime, DATE_FORMAT({$coc}.visitstartdate, '%H:%i:%s')) as visit_time,
                TIME(COALESCE({$coc}.visitstartdate, CURRENT_TIMESTAMP)) as visit_time_sort,
                COALESCE({$csd}.surveyprompt, '') as surveydescription,
                COALESCE({$csd}.arbsurveyprompt, '') as arbsurveyprompt,
                COALESCE({$sad}.surveyrectype, {$csd}.surveyrectype, 0) as surveyrectype,
                COALESCE({$sad}.surveyresponse, '') as surveyresponse
            ");
    }

    private function transformRow(array $row): array
    {
        $isArabic = app()->getLocale() === 'ar';
        $routeName = $isArabic
            ? ($row['arbroutename'] ?? $row['routename'] ?? '')
            : ($row['routename'] ?? $row['arbroutename'] ?? '');
        $salesmanName = $isArabic
            ? ($row['arbsalesmanname1'] ?? $row['salesmanname1'] ?? '')
            : ($row['salesmanname1'] ?? $row['arbsalesmanname1'] ?? '');
        $customerName = $isArabic
            ? ($row['arbcustomername'] ?? $row['customername'] ?? '')
            : ($row['customername'] ?? $row['arbcustomername'] ?? '');
        $surveyDescription = $isArabic
            ? ($row['arbsurveyprompt'] ?? $row['surveydescription'] ?? '')
            : ($row['surveydescription'] ?? $row['arbsurveyprompt'] ?? '');

        return [
            'routekey' => (int) ($row['routekey'] ?? 0),
            'visitkey' => (int) ($row['visitkey'] ?? 0),
            'route_label' => trim(($row['routecode_display'] ?? $row['routecode_value'] ?? '') . ' - ' . $routeName),
            'salesman_label' => trim(($row['salesmancode_display'] ?? $row['salesmancode_value'] ?? '') . ' - ' . $salesmanName),
            'customer_code' => (string) ($row['customercode_display'] ?? $row['customercode_value'] ?? ''),
            'customer_name' => (string) $customerName,
            'visit_date' => (string) ($row['visit_date'] ?? ''),
            'visit_date_sort' => (string) ($row['visit_date_sort'] ?? ''),
            'visit_time' => (string) ($row['visit_time'] ?? ''),
            'visit_time_sort' => (string) ($row['visit_time_sort'] ?? ''),
            'survey_description' => (string) $surveyDescription,
            'survey_response' => $this->formatSurveyResponse((int) ($row['surveyrectype'] ?? 0), (string) ($row['surveyresponse'] ?? '')),
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

    private function formatSurveyResponse(int $type, string $response): string
    {
        if ($type === 9) {
            return ((float) $response) === 0.0 ? 'NO' : 'YES';
        }

        return $response;
    }

    private function qualifiedAlias(string $alias): string
    {
        return DB::getTablePrefix() . $alias;
    }
}
