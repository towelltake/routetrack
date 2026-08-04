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

class BadReturnSummaryController extends Controller
{
    private const SORT_COLUMNS = [
        'transactiondate_sort',
        'routecode',
        'majorcategorycode_sort',
        'itemcode_sort',
        'upc',
        'damagedqty',
        'damagevalue',
        'expiredqty',
        'expvalue',
        'otherdamagedqty',
        'otherdamagevalue',
        'totaldamage',
        'totaldamagevalue',
    ];

    private const EXPORT_COLUMNS = [
        'Transaction Date' => 'transactiondate',
        'Route Code' => 'route_label',
        'Item Group' => 'majorcategory_label',
        'Item Code' => 'itemcode',
        'Item Description' => 'itemdescription_label',
        'UPC' => 'upc',
        'Damaged Qty.' => 'damagedqty',
        'Damaged Value' => 'damagevalue',
        'Expired Qty.' => 'expiredqty',
        'Expired Value' => 'expvalue',
        'Other Damaged Qty.' => 'otherdamagedqty',
        'Other Damaged Value' => 'otherdamagevalue',
        'Total Damaged Qty.' => 'totaldamage',
        'Total Damaged Value' => 'totaldamagevalue',
    ];

    public function __construct(
        private readonly ReportScopeService $reportScopeService
    ) {}

    public function __invoke(Request $request): Response
    {
        $context = $this->resolveContext($request, true);
        $rows = $this->loadAllRows($context['filters'], $context['sort_by'], $context['sort_dir']);
        $paginator = $this->paginateRows($rows, $context['filters']['per_page'], $context['page'], $request);

        return Inertia::render('reports/transaction-report/BadReturnSummary', [
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
            'bad-return-summary-' . now()->format('Ymd_His') . '.xls',
            array_keys(self::EXPORT_COLUMNS),
            $exportRows,
            'Bad Return Summary'
        );
    }

    public function exportPdf(Request $request): View
    {
        $context = $this->resolveContext($request, false);
        $rows = $this->loadAllRows($context['filters'], $context['sort_by'], $context['sort_dir']);

        return view('reports.bad-return-summary-pdf', [
            'rows' => $rows,
            'totals' => $this->totals($rows),
            'selectedFilters' => $this->selectedFilterLabels($context['filters'], $context['scope']),
            'amountPrecision' => AmountPrecision::get(),
        ]);
    }

    private function resolveContext(Request $request, bool $withPagination): array
    {
        $user = $request->user();
        abort_unless($user?->hasFormPermission('bad return summary'), 403);

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
        if (!$this->hasTables(['invoiceheader', 'invoicedetail', 'itemmaster', 'routemaster'])) {
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

                foreach (['transactiondate_sort', 'routecode', 'majorcategorycode_sort', 'itemcode_sort'] as $fallback) {
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
        $rm = $this->qualifiedAlias('rm');
        $mc = $this->qualifiedAlias('mc');

        $upcExpression = "GREATEST(COALESCE({$im}.unitspercase, 1), 1)";
        $damagedQtyExpression = "COALESCE({$id}.damagedqty, 0)";
        $expiredQtyExpression = "COALESCE({$id}.expiryqty, 0)";
        $otherDamagedQtyExpression = $this->hasColumn('invoicedetail', 'otherdamagedqty')
            ? "COALESCE({$id}.otherdamagedqty, 0)"
            : '0';

        $damageValueExpression = $this->valueExpression($damagedQtyExpression, $upcExpression, $id);
        $expiredValueExpression = $this->valueExpression($expiredQtyExpression, $upcExpression, $id);
        $otherDamageValueExpression = $this->valueExpression($otherDamagedQtyExpression, $upcExpression, $id);
        $totalDamageExpression = "({$damagedQtyExpression} + {$expiredQtyExpression} + {$otherDamagedQtyExpression})";
        $totalDamageValueExpression = "({$damageValueExpression} + {$expiredValueExpression} + {$otherDamageValueExpression})";

        return DB::table('invoiceheader as ih')
            ->join('invoicedetail as id', 'id.transactionkey', '=', 'ih.transactionkey')
            ->join('itemmaster as im', 'im.actualitemcode', '=', 'id.itemcode')
            ->join('routemaster as rm', 'rm.routecode', '=', 'ih.routecode')
            ->leftJoin('itemgroup as ig', 'ig.itemgroupcode', '=', 'im.itemgroupcode')
            ->leftJoin('submajorcategory as smc', 'smc.submajorcategorycode', '=', 'ig.submajorcategorycode')
            ->leftJoin('majorcategory as mc', 'mc.majorcategorycode', '=', 'smc.majorcategorycode')
            ->where('ih.voidflag', 0)
            ->whereRaw("{$totalDamageExpression} > 0")
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
                DATE_FORMAT({$ih}.transactiondate, '%d-%b-%Y') as transactiondate,
                DATE_FORMAT({$ih}.transactiondate, '%Y-%m-%d') as transactiondate_sort,
                {$rm}.routecode,
                {$rm}.routename,
                {$rm}.arbroutename,
                COALESCE({$mc}.majorcategorycode, 0) as majorcategorycode_sort,
                COALESCE({$mc}.description, '') as majorcategory,
                COALESCE({$mc}.arbdescription, '') as arbmajorcategory,
                {$im}.actualitemcode as itemcode,
                {$im}.itemdescription,
                {$im}.arbitemdescription,
                {$upcExpression} as upc,
                SUM({$damagedQtyExpression}) as damagedqty,
                SUM({$damageValueExpression}) as damagevalue,
                SUM({$expiredQtyExpression}) as expiredqty,
                SUM({$expiredValueExpression}) as expvalue,
                SUM({$otherDamagedQtyExpression}) as otherdamagedqty,
                SUM({$otherDamageValueExpression}) as otherdamagevalue,
                SUM({$totalDamageExpression}) as totaldamage,
                SUM({$totalDamageValueExpression}) as totaldamagevalue
            ")
            ->groupBy([
                'ih.transactiondate',
                'rm.routecode',
                'rm.routename',
                'rm.arbroutename',
                'mc.majorcategorycode',
                'mc.description',
                'mc.arbdescription',
                'im.actualitemcode',
                'im.itemdescription',
                'im.arbitemdescription',
                'im.unitspercase',
            ]);
    }

    private function transformRow(array $row): array
    {
        $isArabic = app()->getLocale() === 'ar';
        $routeName = $isArabic
            ? ($row['arbroutename'] ?? $row['routename'] ?? '')
            : ($row['routename'] ?? $row['arbroutename'] ?? '');
        $majorCategory = $isArabic
            ? ($row['arbmajorcategory'] ?? $row['majorcategory'] ?? '')
            : ($row['majorcategory'] ?? $row['arbmajorcategory'] ?? '');
        $itemDescription = $isArabic
            ? ($row['arbitemdescription'] ?? $row['itemdescription'] ?? '')
            : ($row['itemdescription'] ?? $row['arbitemdescription'] ?? '');

        return [
            'transactiondate' => (string) ($row['transactiondate'] ?? ''),
            'transactiondate_sort' => (string) ($row['transactiondate_sort'] ?? ''),
            'routecode' => (int) ($row['routecode'] ?? 0),
            'route_label' => trim(((int) ($row['routecode'] ?? 0)) . ' - ' . $routeName),
            'majorcategorycode_sort' => (int) ($row['majorcategorycode_sort'] ?? 0),
            'majorcategory_label' => trim((((int) ($row['majorcategorycode_sort'] ?? 0)) > 0 ? ((int) ($row['majorcategorycode_sort'] ?? 0)) . ' - ' : '') . $majorCategory),
            'itemcode' => $this->identifier($row['itemcode'] ?? ''),
            'itemcode_sort' => (string) ($row['itemcode'] ?? ''),
            'itemdescription_label' => $itemDescription,
            'upc' => (int) ($row['upc'] ?? 0),
            'damagedqty' => (float) ($row['damagedqty'] ?? 0),
            'damagevalue' => (float) ($row['damagevalue'] ?? 0),
            'expiredqty' => (float) ($row['expiredqty'] ?? 0),
            'expvalue' => (float) ($row['expvalue'] ?? 0),
            'otherdamagedqty' => (float) ($row['otherdamagedqty'] ?? 0),
            'otherdamagevalue' => (float) ($row['otherdamagevalue'] ?? 0),
            'totaldamage' => (float) ($row['totaldamage'] ?? 0),
            'totaldamagevalue' => (float) ($row['totaldamagevalue'] ?? 0),
        ];
    }

    private function totals(Collection $rows): array
    {
        return [
            'damagedqty' => (float) $rows->sum('damagedqty'),
            'damagevalue' => (float) $rows->sum('damagevalue'),
            'expiredqty' => (float) $rows->sum('expiredqty'),
            'expvalue' => (float) $rows->sum('expvalue'),
            'otherdamagedqty' => (float) $rows->sum('otherdamagedqty'),
            'otherdamagevalue' => (float) $rows->sum('otherdamagevalue'),
            'totaldamage' => (float) $rows->sum('totaldamage'),
            'totaldamagevalue' => (float) $rows->sum('totaldamagevalue'),
        ];
    }

    private function mapExportRow(array $row): array
    {
        $export = [];

        foreach (self::EXPORT_COLUMNS as $label => $key) {
            $value = $row[$key] ?? '';
            if (in_array($key, ['damagevalue', 'expvalue', 'otherdamagevalue', 'totaldamagevalue'], true)) {
                $value = AmountPrecision::format($value);
            }
            $export[$label] = $value;
        }

        return $export;
    }

    private function totalsExportRow(array $totals): array
    {
        $row = array_fill_keys(array_keys(self::EXPORT_COLUMNS), '');
        $row['Item Description'] = 'Total';
        $row['Damaged Qty.'] = $totals['damagedqty'];
        $row['Damaged Value'] = AmountPrecision::format($totals['damagevalue']);
        $row['Expired Qty.'] = $totals['expiredqty'];
        $row['Expired Value'] = AmountPrecision::format($totals['expvalue']);
        $row['Other Damaged Qty.'] = $totals['otherdamagedqty'];
        $row['Other Damaged Value'] = AmountPrecision::format($totals['otherdamagevalue']);
        $row['Total Damaged Qty.'] = $totals['totaldamage'];
        $row['Total Damaged Value'] = AmountPrecision::format($totals['totaldamagevalue']);

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

    private function hasColumn(string $table, string $column): bool
    {
        return Schema::hasTable($table) && Schema::hasColumn($table, $column);
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

    private function identifier(mixed $value): string
    {
        if ($value === null || $value === '') {
            return '';
        }

        return (string) $value;
    }

    private function valueExpression(string $quantityExpression, string $upcExpression, string $detailAlias): string
    {
        return '((FLOOR((' . $quantityExpression . ') / ' . $upcExpression . ') * COALESCE(' . $detailAlias . '.returncaseprice, 0))'
            . ' + (MOD((' . $quantityExpression . '), ' . $upcExpression . ') * COALESCE(' . $detailAlias . '.returnprice, 0)))';
    }

    private function qualifiedAlias(string $alias): string
    {
        return DB::getTablePrefix() . $alias;
    }
}
