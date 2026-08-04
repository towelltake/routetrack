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

class SalesFreeSummaryController extends Controller
{
    private const SORT_COLUMNS = [
        'transactiondate_sort',
        'routecode_sort',
        'majorcategorycode_sort',
        'itemcode_sort',
        'upc',
        'salesqty',
        'freeqty',
        'damagedqty',
        'returnqty',
        'sales_std_price',
        'sales_inv_price',
        'bad_ret_std_price',
        'bad_ret_inv_price',
        'good_ret_std_price',
        'good_ret_inv_price',
        'inv_discount_breakup',
        'item_discount_breakup',
        'free_goods_std_price',
        'price_difference',
        'total_foc',
        'netqty',
        'netamount',
    ];

    private const EXPORT_COLUMNS = [
        'Transaction Date' => 'transactiondate',
        'Route' => 'route_label',
        'Item Group' => 'majorcategory_label',
        'Item Code' => 'itemcode',
        'Item Description' => 'itemdescription',
        'UPC' => 'upc',
        'Sales Qty' => 'salesqty',
        'Free Qty' => 'freeqty',
        'Bad Ret. Qty' => 'damagedqty',
        'Good Ret. Qty' => 'returnqty',
        'Sales @ Std. Price' => 'sales_std_price',
        'Sales @ Inv. Price' => 'sales_inv_price',
        'Bad Ret @ Std. Price' => 'bad_ret_std_price',
        'Bad Ret @ Inv. Price' => 'bad_ret_inv_price',
        'Good Return @ Std. Price' => 'good_ret_std_price',
        'Good Return @ Inv. Price' => 'good_ret_inv_price',
        'Inv. Discount Break Up' => 'inv_discount_breakup',
        'Item Discount Break Up' => 'item_discount_breakup',
        'Free Goods @ Std. Price' => 'free_goods_std_price',
        'Price Difference' => 'price_difference',
        'Total FOC' => 'total_foc',
        'Net Sales Qty' => 'netqty',
        'Net Sales Amount' => 'netamount',
    ];

    public function __construct(
        private readonly ReportScopeService $reportScopeService
    ) {}

    public function __invoke(Request $request): Response
    {
        $context = $this->resolveContext($request, true);
        $rows = $this->loadAllRows($context['filters'], $context['sort_by'], $context['sort_dir']);
        $paginator = $this->paginateRows($rows, $context['filters']['per_page'], $context['page'], $request);

        return Inertia::render('reports/data-analysis/SalesFreeSummary', [
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
            'itemOptions' => $this->itemOptions(),
            'majorCategoryOptions' => $this->majorCategoryOptions(),
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
            'sales-free-summary-' . now()->format('Ymd_His') . '.xls',
            array_keys(self::EXPORT_COLUMNS),
            $exportRows,
            'Sales Free Summary'
        );
    }

    public function exportPdf(Request $request): View
    {
        $context = $this->resolveContext($request, false);
        $rows = $this->loadAllRows($context['filters'], $context['sort_by'], $context['sort_dir']);

        return view('reports.sales-free-summary-pdf', [
            'rows' => $rows,
            'totals' => $this->totals($rows),
            'selectedFilters' => $this->selectedFilterLabels($context['filters'], $context['scope']),
            'amountPrecision' => AmountPrecision::get(),
        ]);
    }

    private function resolveContext(Request $request, bool $withPagination): array
    {
        $user = $request->user();
        abort_unless($user?->hasFormPermission('sales free summary'), 403);

        $rules = [
            'transaction_date_from' => ['nullable', 'date'],
            'transaction_date_to' => ['nullable', 'date'],
            'cmpycode' => ['nullable', 'integer'],
            'regionmstcode' => ['nullable', 'integer'],
            'depotcode' => ['nullable', 'integer'],
            'areacode' => ['nullable', 'integer'],
            'subareacode' => ['nullable', 'integer'],
            'routecode' => ['nullable', 'integer'],
            'itemcode' => ['nullable', 'string'],
            'majorcategorycode' => ['nullable', 'integer'],
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
            'itemcode' => $this->nullableString($validated['itemcode'] ?? null),
            'majorcategorycode' => $this->nullableInt($validated['majorcategorycode'] ?? null),
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

        $requestedSortBy = $validated['sort_by'] ?? 'routecode_sort';

        return [
            'filters' => $filters,
            'scope' => $scope,
            'sort_by' => in_array($requestedSortBy, self::SORT_COLUMNS, true) ? $requestedSortBy : 'routecode_sort',
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

        $lines = $this->baseQuery($filters)->get()->map(fn ($row) => (array) $row);
        if ($lines->isEmpty()) {
            return collect();
        }

        $invoiceSalesTotals = $lines
            ->groupBy('transactionkey')
            ->map(fn (Collection $group) => (float) $group->sum('sales_inv_base'));

        $grouped = [];

        foreach ($lines as $line) {
            $row = $this->transformLine($line, (float) ($invoiceSalesTotals[$line['transactionkey']] ?? 0));
            $key = implode('|', [
                $row['transactiondate_sort'],
                $row['routecode_sort'],
                $row['majorcategorycode_sort'],
                $row['itemcode_sort'],
            ]);

            if (!isset($grouped[$key])) {
                $grouped[$key] = $row;
                continue;
            }

            foreach ([
                'salesqty',
                'freeqty',
                'damagedqty',
                'returnqty',
                'sales_std_price',
                'sales_inv_price',
                'bad_ret_std_price',
                'bad_ret_inv_price',
                'good_ret_std_price',
                'good_ret_inv_price',
                'inv_discount_breakup',
                'item_discount_breakup',
                'free_goods_std_price',
                'price_difference',
                'total_foc',
                'netqty',
                'netamount',
            ] as $column) {
                $grouped[$key][$column] += (float) ($row[$column] ?? 0);
            }
        }

        return collect(array_values($grouped))
            ->sort(function (array $left, array $right) use ($sortBy, $sortDir) {
                $baseCompare = $this->compare($left[$sortBy] ?? null, $right[$sortBy] ?? null);
                if ($baseCompare !== 0) {
                    return $sortDir === 'desc' ? -$baseCompare : $baseCompare;
                }

                foreach (['transactiondate_sort', 'routecode_sort', 'majorcategorycode_sort', 'itemcode_sort'] as $fallback) {
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

        $dateColumn = $this->hasColumn('invoiceheader', 'actualtransactiondate')
            ? "{$ih}.actualtransactiondate"
            : "{$ih}.transactiondate";
        $routeDisplayExpression = $this->hasColumn('routemaster', 'alternateroutecode')
            ? "COALESCE(NULLIF(TRIM({$rm}.alternateroutecode), ''), CAST({$rm}.routecode AS CHAR))"
            : "CAST({$rm}.routecode AS CHAR)";
        $itemCodeExpression = $this->hasColumn('itemmaster', 'alternatecode')
            ? "COALESCE(NULLIF(TRIM({$im}.alternatecode), ''), CAST({$im}.actualitemcode AS CHAR))"
            : "CAST({$im}.actualitemcode AS CHAR)";

        $joinWithTransactionKey = $this->hasColumn('invoiceheader', 'transactionkey') && $this->hasColumn('invoicedetail', 'transactionkey');
        $query = DB::table('invoiceheader as ih');

        if ($joinWithTransactionKey) {
            $query->join('invoicedetail as id', 'id.transactionkey', '=', 'ih.transactionkey');
        } else {
            $query->join('invoicedetail as id', function ($join) {
                $join->on('ih.routekey', '=', 'id.routekey')
                    ->on('ih.visitkey', '=', 'id.visitkey');
            });
        }

        $freeQtyExpression = "COALESCE({$id}.freesampleqty, 0) + COALESCE({$id}.manualfreeqty, 0)";
        $salesStdValueExpression = $this->valueExpression("COALESCE({$id}.salesqty, 0)", $id, $im, 'stdsalescaseprice', 'stdsalesprice');
        $salesInvValueExpression = $this->valueExpression("COALESCE({$id}.salesqty, 0)", $id, $im, 'salescaseprice', 'salesprice');
        $damageStdValueExpression = $this->valueExpression("COALESCE({$id}.damagedqty, 0)", $id, $im, 'stdsalescaseprice', 'stdsalesprice');
        $damageInvValueExpression = $this->valueExpression("COALESCE({$id}.damagedqty, 0)", $id, $im, 'salescaseprice', 'salesprice');
        $goodReturnStdValueExpression = $this->valueExpression(
            "COALESCE({$id}.returnqty, 0)",
            $id,
            $im,
            $this->hasColumn('invoicedetail', 'stdgoodreturncaseprice') ? 'stdgoodreturncaseprice' : 'stdsalescaseprice',
            $this->hasColumn('invoicedetail', 'stdgoodreturnprice') ? 'stdgoodreturnprice' : 'stdsalesprice'
        );
        $goodReturnInvValueExpression = $this->valueExpression(
            "COALESCE({$id}.returnqty, 0)",
            $id,
            $im,
            $this->hasColumn('invoicedetail', 'goodreturncaseprice') ? 'goodreturncaseprice' : 'salescaseprice',
            $this->hasColumn('invoicedetail', 'goodreturnprice') ? 'goodreturnprice' : 'salesprice'
        );
        $freeGoodsStdExpression = $this->valueExpression($freeQtyExpression, $id, $im, 'stdsalescaseprice', 'stdsalesprice');
        $itemDiscountHeaderExpression = $this->hasColumn('invoiceheader', 'totalpromoamount')
            ? "COALESCE({$ih}.totalpromoamount, 0)"
            : '0';
        $invoiceDiscountHeaderExpression = $this->hasColumn('invoiceheader', 'totaldiscountamount')
            ? "COALESCE({$ih}.totaldiscountamount, 0)"
            : '0';

        return $query
            ->join('itemmaster as im', 'im.actualitemcode', '=', 'id.itemcode')
            ->join('routemaster as rm', 'rm.routecode', '=', 'ih.routecode')
            ->leftJoin('itemgroup as ig', 'ig.itemgroupcode', '=', 'im.itemgroupcode')
            ->leftJoin('submajorcategory as smc', 'smc.submajorcategorycode', '=', 'ig.submajorcategorycode')
            ->leftJoin('majorcategory as mc', 'mc.majorcategorycode', '=', 'smc.majorcategorycode')
            ->when(
                $this->hasColumn('invoiceheader', 'voidflag'),
                fn ($builder) => $builder->where('ih.voidflag', 0)
            )
            ->when(
                $filters['transaction_date_from'],
                fn ($builder, $date) => $builder->whereDate(DB::raw($dateColumn), '>=', $date)
            )
            ->when(
                $filters['transaction_date_to'],
                fn ($builder, $date) => $builder->whereDate(DB::raw($dateColumn), '<=', $date)
            )
            ->when(
                $filters['routecodes'] !== [],
                fn ($builder) => $builder->whereIn('ih.routecode', $filters['routecodes'])
            )
            ->when(
                !$filters['scope_limited'] && ($filters['routecodes'] ?? []) === [],
                fn ($builder) => $builder->where('ih.routecode', '>', 0)
            )
            ->when(
                $filters['majorcategorycode'],
                fn ($builder, $majorCategoryCode) => $builder->where('mc.majorcategorycode', $majorCategoryCode)
            )
            ->when(
                $filters['itemcode'],
                fn ($builder, $itemCode) => $builder->where('im.actualitemcode', $itemCode)
            )
            ->selectRaw("
                " . ($joinWithTransactionKey ? "{$ih}.transactionkey" : 'CONCAT(CAST(' . $ih . '.routekey AS CHAR), \'-\', CAST(' . $ih . '.visitkey AS CHAR)) as transactionkey') . ",
                DATE_FORMAT({$dateColumn}, '%d %b %Y') as transactiondate,
                DATE_FORMAT({$dateColumn}, '%Y-%m-%d') as transactiondate_sort,
                {$rm}.routecode as routecode_sort,
                {$routeDisplayExpression} as routecode_display,
                COALESCE({$rm}.routename, '') as routename,
                COALESCE({$rm}.arbroutename, '') as arbroutename,
                COALESCE({$mc}.majorcategorycode, 0) as majorcategorycode_sort,
                COALESCE({$mc}.description, '') as majorcategory,
                COALESCE({$mc}.arbdescription, '') as arbmajorcategory,
                {$im}.actualitemcode as itemcode_sort,
                {$itemCodeExpression} as itemcode_display,
                COALESCE({$im}.itemdescription, '') as itemdescription,
                COALESCE({$im}.arbitemdescription, '') as arbitemdescription,
                GREATEST(COALESCE({$im}.unitspercase, 1), 1) as upc,
                COALESCE({$id}.salesqty, 0) as salesqty,
                {$freeQtyExpression} as freeqty,
                COALESCE({$id}.damagedqty, 0) as damagedqty,
                COALESCE({$id}.returnqty, 0) as returnqty,
                {$salesStdValueExpression} as sales_std_price,
                {$salesInvValueExpression} as sales_inv_price,
                {$salesInvValueExpression} as sales_inv_base,
                {$damageStdValueExpression} as damage_std_base,
                {$damageInvValueExpression} as damage_inv_base,
                {$goodReturnStdValueExpression} as good_return_std_base,
                {$goodReturnInvValueExpression} as good_return_inv_base,
                {$freeGoodsStdExpression} as free_goods_std_price,
                {$invoiceDiscountHeaderExpression} as header_invoice_discount,
                {$itemDiscountHeaderExpression} as header_item_discount
            ")
            ->orderByRaw("{$dateColumn} asc")
            ->orderBy('rm.routecode')
            ->orderBy('mc.majorcategorycode')
            ->orderBy('im.actualitemcode');
    }

    private function transformLine(array $row, float $invoiceSalesTotal): array
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

        $salesInvPrice = (float) ($row['sales_inv_price'] ?? 0);
        $allocationRatio = $invoiceSalesTotal > 0 ? ($salesInvPrice / $invoiceSalesTotal) : 0.0;
        $invoiceDiscountBreakup = (float) ($row['header_invoice_discount'] ?? 0) * $allocationRatio;
        $itemDiscountBreakup = (float) ($row['header_item_discount'] ?? 0) * $allocationRatio;
        $salesStdPrice = (float) ($row['sales_std_price'] ?? 0);
        $badRetStdPrice = -1 * (float) ($row['damage_std_base'] ?? 0);
        $badRetInvPrice = -1 * (float) ($row['damage_inv_base'] ?? 0);
        $goodRetStdPrice = -1 * (float) ($row['good_return_std_base'] ?? 0);
        $goodRetInvPrice = -1 * (float) ($row['good_return_inv_base'] ?? 0);
        $freeGoodsStdPrice = (float) ($row['free_goods_std_price'] ?? 0);

        return [
            'transactiondate' => (string) ($row['transactiondate'] ?? ''),
            'transactiondate_sort' => (string) ($row['transactiondate_sort'] ?? ''),
            'routecode_sort' => (int) ($row['routecode_sort'] ?? 0),
            'route_label' => trim(($row['routecode_display'] ?? '') . ' - ' . $routeName, ' -'),
            'majorcategorycode_sort' => (int) ($row['majorcategorycode_sort'] ?? 0),
            'majorcategory_label' => trim((((int) ($row['majorcategorycode_sort'] ?? 0)) > 0 ? ((int) ($row['majorcategorycode_sort'] ?? 0)) . ' - ' : '') . $majorCategory),
            'itemcode' => $this->identifier($row['itemcode_display'] ?? ''),
            'itemcode_sort' => (string) ($row['itemcode_sort'] ?? ''),
            'itemdescription' => $itemDescription,
            'upc' => (int) ($row['upc'] ?? 1),
            'salesqty' => (float) ($row['salesqty'] ?? 0),
            'freeqty' => (float) ($row['freeqty'] ?? 0),
            'damagedqty' => (float) ($row['damagedqty'] ?? 0),
            'returnqty' => (float) ($row['returnqty'] ?? 0),
            'sales_std_price' => $salesStdPrice,
            'sales_inv_price' => $salesInvPrice,
            'bad_ret_std_price' => $badRetStdPrice,
            'bad_ret_inv_price' => $badRetInvPrice,
            'good_ret_std_price' => $goodRetStdPrice,
            'good_ret_inv_price' => $goodRetInvPrice,
            'inv_discount_breakup' => $invoiceDiscountBreakup,
            'item_discount_breakup' => $itemDiscountBreakup,
            'free_goods_std_price' => $freeGoodsStdPrice,
            'price_difference' => $salesStdPrice - $salesInvPrice,
            'total_foc' => $freeGoodsStdPrice + $invoiceDiscountBreakup + $itemDiscountBreakup,
            'netqty' => (float) ($row['salesqty'] ?? 0) + (float) ($row['freeqty'] ?? 0) - (float) ($row['damagedqty'] ?? 0) - (float) ($row['returnqty'] ?? 0),
            'netamount' => $salesInvPrice + $badRetInvPrice + $goodRetInvPrice + $invoiceDiscountBreakup + $itemDiscountBreakup,
        ];
    }

    private function totals(Collection $rows): array
    {
        $totals = [];
        foreach (array_values(self::EXPORT_COLUMNS) as $key) {
            if (in_array($key, ['transactiondate', 'route_label', 'majorcategory_label', 'itemcode', 'itemdescription'], true)) {
                continue;
            }
            $totals[$key] = (float) $rows->sum($key);
        }

        return $totals;
    }

    private function mapExportRow(array $row): array
    {
        $export = [];

        foreach (self::EXPORT_COLUMNS as $label => $key) {
            $value = $row[$key] ?? '';
            if (!in_array($key, ['transactiondate', 'route_label', 'majorcategory_label', 'itemcode', 'itemdescription'], true)) {
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

        foreach (self::EXPORT_COLUMNS as $label => $key) {
            if (isset($totals[$key])) {
                $row[$label] = AmountPrecision::format($totals[$key]);
            }
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
            'Items' => $this->selectedOptionLabel($this->itemOptions(), $filters['itemcode'], true),
            'Major Category' => $this->selectedOptionLabel($this->majorCategoryOptions(), $filters['majorcategorycode']),
        ];
    }

    private function selectedOptionLabel(array $options, mixed $value, bool $stringMatch = false): string
    {
        if ($value === null || $value === '') {
            return 'All';
        }

        $match = collect($options)->first(function (array $option) use ($value, $stringMatch) {
            return $stringMatch
                ? (string) ($option['id'] ?? '') === (string) $value
                : (int) ($option['id'] ?? 0) === (int) $value;
        });

        return (string) ($match['label'] ?? $value);
    }

    private function itemOptions(): array
    {
        if (!$this->hasTables(['itemmaster'])) {
            return [];
        }

        return DB::table('itemmaster')
            ->selectRaw("CAST(actualitemcode AS CHAR) as id, CONCAT(COALESCE(alternatecode, actualitemcode), ' - ', COALESCE(itemdescription, '')) as label")
            ->orderBy('alternatecode')
            ->limit(5000)
            ->get()
            ->map(fn ($row) => ['id' => (string) $row->id, 'label' => (string) $row->label])
            ->all();
    }

    private function majorCategoryOptions(): array
    {
        if (!$this->hasTables(['majorcategory'])) {
            return [];
        }

        return DB::table('majorcategory')
            ->selectRaw('majorcategorycode as id, CONCAT(majorcategorycode, \' - \', COALESCE(description, \'\')) as label')
            ->orderBy('majorcategorycode')
            ->get()
            ->map(fn ($row) => ['id' => (int) $row->id, 'label' => (string) $row->label])
            ->all();
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

    private function valueExpression(string $qtyExpression, string $detailAlias, string $itemAlias, string $caseColumn, string $pieceColumn): string
    {
        $upcExpression = "GREATEST(COALESCE({$itemAlias}.unitspercase, 0), 1)";
        $casePriceExpression = $this->hasColumn('invoicedetail', $caseColumn)
            ? "COALESCE({$detailAlias}.{$caseColumn}, 0)"
            : '0';
        $piecePriceExpression = $this->hasColumn('invoicedetail', $pieceColumn)
            ? "COALESCE({$detailAlias}.{$pieceColumn}, 0)"
            : '0';

        return "(FLOOR({$qtyExpression} / {$upcExpression}) * {$casePriceExpression}) + (MOD({$qtyExpression}, {$upcExpression}) * {$piecePriceExpression})";
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

    private function nullableString(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        return (string) $value;
    }

    private function qualifiedAlias(string $alias): string
    {
        return DB::getTablePrefix() . $alias;
    }
}
