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
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\Response as HttpResponse;

class PosTrackingController extends Controller
{
    private const SORT_COLUMNS = [
        'route_label',
        'salesman_label',
        'customer_code',
        'customer_name',
        'visit_date_sort',
        'visit_time_sort',
        'pos_description',
        'quantity',
        'serial_number',
        'pos_instruction',
        'pos_status',
    ];

    private const EXPORT_COLUMNS = [
        'Route' => 'route_label',
        'Salesman' => 'salesman_label',
        'Customer Code' => 'customer_code',
        'Customer Name' => 'customer_name',
        'Date' => 'visit_date',
        'Time' => 'visit_time',
        'POS Description' => 'pos_description',
        'POS Qty' => 'quantity',
        'POS Serial' => 'serial_number',
        'POS Instruction' => 'pos_instruction',
        'POS Status' => 'pos_status',
    ];

    public function __construct(
        private readonly ReportScopeService $reportScopeService
    ) {}

    public function __invoke(Request $request): Response
    {
        $context = $this->resolveContext($request, true);
        $rows = $this->loadAllRows($context['filters'], $context['sort_by'], $context['sort_dir']);
        $paginator = $this->paginateRows($rows, $context['filters']['per_page'], $context['page'], $request);

        return Inertia::render('reports/merchandizing-report/PosTracking', [
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
            'pos-tracking-' . now()->format('Ymd_His') . '.xls',
            array_keys(self::EXPORT_COLUMNS),
            $exportRows,
            'POS Tracking'
        );
    }

    public function exportPdf(Request $request): View
    {
        $context = $this->resolveContext($request, false);
        $rows = $this->loadAllRows($context['filters'], $context['sort_by'], $context['sort_dir']);

        return view('reports.pos-tracking-pdf', [
            'rows' => $rows,
            'selectedFilters' => $this->selectedFilterLabels($context['filters'], $context['scope']),
        ]);
    }

    private function resolveContext(Request $request, bool $withPagination): array
    {
        $user = $request->user();
        abort_unless($user?->hasFormPermission('pos tracking'), 403);

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
        if (!$this->hasTables(['customeroperationscontrol', 'posequipmentchangedetail', 'salesman', 'routemaster', 'customermaster', 'posmaster', 'posinstructions'])) {
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
        $pos = $this->qualifiedAlias('pos');
        $sm = $this->qualifiedAlias('sm');
        $rm = $this->qualifiedAlias('rm');
        $cm = $this->qualifiedAlias('cm');
        $pm = $this->qualifiedAlias('pm');
        $poi = $this->qualifiedAlias('poi');

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
            ->join('posequipmentchangedetail as pos', function ($join) {
                $join->on('coc.routekey', '=', 'pos.routekey')
                    ->on('coc.visitkey', '=', 'pos.visitkey');
            })
            ->join('salesman as sm', 'coc.salesmancode', '=', 'sm.salesmancode')
            ->join('routemaster as rm', 'coc.routecode', '=', 'rm.routecode')
            ->join('customermaster as cm', 'coc.customercode', '=', 'cm.customercode')
            ->join('posmaster as pm', 'pos.itemcode', '=', 'pm.itemcode')
            ->leftJoin('posinstructions as poi', 'poi.posinstructioncode', '=', 'pos.instructioncode')
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
                CAST({$pos}.itemcode AS CHAR) as pos_code,
                COALESCE({$pm}.itemdescription, '') as itemdescription,
                COALESCE({$pm}.arbitemdescription, '') as arbitemdescription,
                COALESCE({$pos}.quantity, 0) as quantity,
                COALESCE({$pos}.serialnumber, '') as serialnumber,
                COALESCE({$poi}.posinstructionname, '') as posinstructionname,
                COALESCE({$poi}.arbposinstructionname, '') as arbposinstructionname,
                COALESCE({$pos}.posaction, 0) as posaction
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
        $posDescription = $isArabic
            ? ($row['arbitemdescription'] ?? $row['itemdescription'] ?? '')
            : ($row['itemdescription'] ?? $row['arbitemdescription'] ?? '');
        $instruction = $isArabic
            ? ($row['arbposinstructionname'] ?? $row['posinstructionname'] ?? '')
            : ($row['posinstructionname'] ?? $row['arbposinstructionname'] ?? '');

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
            'pos_code' => (string) ($row['pos_code'] ?? ''),
            'pos_description' => (string) $posDescription,
            'quantity' => (float) ($row['quantity'] ?? 0),
            'serial_number' => (string) ($row['serialnumber'] ?? ''),
            'pos_instruction' => (string) $instruction,
            'pos_status' => $this->statusLabel((int) ($row['posaction'] ?? 0)),
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

    private function statusLabel(int $action): string
    {
        return match ($action) {
            1 => 'ADDED POS',
            2 => 'DELETED POS',
            default => 'POS CHECKED',
        };
    }

    private function qualifiedAlias(string $alias): string
    {
        return DB::getTablePrefix() . $alias;
    }
}
