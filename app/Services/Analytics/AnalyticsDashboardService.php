<?php

namespace App\Services\Analytics;

use App\Models\User;
use App\Models\UserAccessCode;
use Carbon\Carbon;
use Carbon\CarbonPeriod;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class AnalyticsDashboardService
{
    private const ACCESS_TYPES = [
        1 => 'company',
        2 => 'country',
        3 => 'region',
        4 => 'depot',
        5 => 'area',
        6 => 'sub_area',
    ];

    public function build(User $user, string $board, array $input): array
    {
        $filters = $this->normalizeFilters($input);
        $scope = $this->resolveScope($user, $filters);

        if ($scope['matches_none']) {
            return [
                'filters' => $filters,
                'filterOptions' => $scope['options'],
                'scope' => $scope['summary'],
                'board' => $this->boardMeta($board),
                'cards' => [],
                'charts' => [],
                'tables' => [],
                'insights' => [$this->t('analytics_no_scope_data')],
            ];
        }

        return array_merge([
            'filters' => $filters,
            'filterOptions' => $scope['options'],
            'scope' => $scope['summary'],
            'board' => $this->boardMeta($board),
            'cards' => [],
            'charts' => [],
            'tables' => [],
            'insights' => [],
        ], match ($board) {
            'sales' => $this->salesBoard($filters, $scope),
            'collections' => $this->collectionsBoard($filters, $scope),
            'inventory' => $this->inventoryBoard($filters, $scope),
            default => $this->overviewBoard($filters, $scope),
        });
    }

    public function emptyState(string $board, ?string $message = null): array
    {
        return [
            'filters' => $this->normalizeFilters([]),
            'filterOptions' => [
                'companies' => [],
                'regions' => [],
                'depots' => [],
                'areas' => [],
                'subAreas' => [],
                'routes' => [],
            ],
            'scope' => [
                'limited' => true,
                'access_type' => $this->t('analytics_restricted'),
                'route_count' => 0,
                'badges' => [],
                'message' => $message ?? $this->t('analytics_no_dashboard_access'),
            ],
            'board' => $this->boardMeta($board),
            'cards' => [],
            'charts' => [],
            'tables' => [],
            'insights' => [],
        ];
    }

    private function overviewBoard(array $filters, array $scope): array
    {
        $invoice = $this->invoiceSummary($filters, $scope['routecodes']);
        $orders = $this->orderSummary($filters, $scope['routecodes']);
        $collections = $this->collectionSummary($filters, $scope['routecodes']);
        $inventory = $this->inventorySummary($filters, $scope['routecodes']);
        $trend = $this->overviewTrend($filters, $scope['routecodes']);

        $avgInvoice = $invoice['documents'] > 0
            ? $invoice['amount'] / $invoice['documents']
            : 0.0;

        $collectionRate = $collections['invoice_total'] > 0
            ? ($collections['amount'] / $collections['invoice_total']) * 100
            : 0.0;

        return [
            'cards' => [
                $this->metricCard($this->t('analytics_invoice_revenue'), $invoice['amount'], 'amount', $this->t('analytics_note_vs_billed_customers', ['count' => number_format($invoice['customers'])])),
                $this->metricCard($this->t('analytics_sales_orders'), $orders['amount'], 'amount', $this->t('analytics_note_order_documents', ['count' => number_format($orders['documents'])])),
                $this->metricCard($this->t('analytics_collections'), $collections['amount'], 'amount', $this->t('analytics_note_recovery_rate', ['value' => number_format($collectionRate, 1) . '%'])),
                $this->metricCard($this->t('analytics_active_routes'), max($invoice['routes'], $orders['routes'], $inventory['routes']), 'number', $this->t('analytics_note_within_current_scope')),
                $this->metricCard($this->t('analytics_customers_served'), $invoice['customers'], 'number', $this->t('analytics_note_distinct_invoiced_customers')),
                $this->metricCard($this->t('analytics_average_invoice'), $avgInvoice, 'amount', $this->t('analytics_note_invoice_documents', ['count' => number_format($invoice['documents'])])),
            ],
            'charts' => [
                $this->lineChart(
                    $this->t('analytics_chart_revenue_orders_collections'),
                    $trend['labels'],
                    [
                        ['label' => $this->t('analytics_invoices'), 'data' => $trend['invoice_amounts'], 'borderColor' => '#2563eb', 'backgroundColor' => 'rgba(37,99,235,0.16)'],
                        ['label' => $this->t('analytics_orders'), 'data' => $trend['order_amounts'], 'borderColor' => '#0f766e', 'backgroundColor' => 'rgba(15,118,110,0.14)'],
                        ['label' => $this->t('analytics_collections'), 'data' => $trend['collection_amounts'], 'borderColor' => '#ea580c', 'backgroundColor' => 'rgba(234,88,12,0.14)'],
                    ]
                ),
                $this->doughnutChart($this->t('analytics_chart_commercial_mix'), [
                    ['label' => $this->t('analytics_invoice_revenue'), 'value' => $invoice['amount'], 'color' => '#2563eb'],
                    ['label' => $this->t('analytics_sales_orders'), 'value' => $orders['amount'], 'color' => '#0f766e'],
                    ['label' => $this->t('analytics_collections'), 'value' => $collections['amount'], 'color' => '#ea580c'],
                    ['label' => $this->t('analytics_returns_damage'), 'value' => $invoice['returns'] + $invoice['damaged'], 'color' => '#dc2626'],
                ]),
                $this->barChart(
                    $this->t('analytics_chart_route_execution_pulse'),
                    $trend['labels'],
                    [
                        ['label' => $this->t('analytics_active_routes'), 'data' => $trend['active_routes'], 'backgroundColor' => 'rgba(71,85,105,0.82)'],
                        ['label' => $this->t('analytics_customers_served'), 'data' => $trend['customers'], 'backgroundColor' => 'rgba(14,165,233,0.72)'],
                    ]
                ),
            ],
            'tables' => [
                $this->topRoutesTable($filters, $scope['routecodes']),
                $this->topCustomersTable($filters, $scope['routecodes']),
            ],
            'insights' => [
                $this->t('analytics_insight_orders_share', ['value' => $this->ratioText($orders['amount'], max($invoice['amount'], 0.01))]),
                $this->t('analytics_insight_collection_efficiency', ['value' => number_format($collectionRate, 1) . '%']),
                $this->t('analytics_insight_inventory_volume', ['sold' => number_format($inventory['sold_qty']), 'loaded' => number_format($inventory['loaded_qty'])]),
            ],
        ];
    }

    private function salesBoard(array $filters, array $scope): array
    {
        $invoice = $this->invoiceSummary($filters, $scope['routecodes']);
        $orders = $this->orderSummary($filters, $scope['routecodes']);
        $trend = $this->salesTrend($filters, $scope['routecodes']);
        $mix = $this->paymentMix($filters, $scope['routecodes']);

        return [
            'cards' => [
                $this->metricCard($this->t('analytics_gross_invoice_revenue'), $invoice['gross_sales'], 'amount', $this->t('analytics_note_before_returns_damage')),
                $this->metricCard($this->t('analytics_net_invoice_revenue'), $invoice['amount'], 'amount', $this->t('analytics_note_invoices', ['count' => number_format($invoice['documents'])])),
                $this->metricCard($this->t('analytics_sales_order_value'), $orders['amount'], 'amount', $this->t('analytics_note_sales_orders', ['count' => number_format($orders['documents'])])),
                $this->metricCard($this->t('analytics_promotion_impact'), $invoice['promo'] + $invoice['discount'], 'amount', $this->t('analytics_note_promo_discount_exposure')),
                $this->metricCard($this->t('analytics_billed_customers'), $invoice['customers'], 'number', $this->t('analytics_note_distinct_invoice_customers')),
                $this->metricCard($this->t('analytics_return_and_damage'), $invoice['returns'] + $invoice['damaged'], 'amount', $this->t('analytics_note_return_plus_damage_value')),
            ],
            'charts' => [
                $this->lineChart(
                    $this->t('analytics_chart_daily_sales_trend'),
                    $trend['labels'],
                    [
                        ['label' => $this->t('analytics_invoice_revenue'), 'data' => $trend['invoice_amounts'], 'borderColor' => '#2563eb', 'backgroundColor' => 'rgba(37,99,235,0.16)'],
                        ['label' => $this->t('analytics_sales_order_value'), 'data' => $trend['order_amounts'], 'borderColor' => '#16a34a', 'backgroundColor' => 'rgba(22,163,74,0.14)'],
                    ]
                ),
                $this->doughnutChart($this->t('analytics_chart_payment_term_mix'), [
                    ['label' => $this->t('cash'), 'value' => $mix['cash'], 'color' => '#16a34a'],
                    ['label' => 'GC', 'value' => $mix['gc'], 'color' => '#2563eb'],
                    ['label' => 'TC', 'value' => $mix['tc'], 'color' => '#f59e0b'],
                ]),
                $this->barChart(
                    $this->t('analytics_chart_sales_document_velocity'),
                    $trend['labels'],
                    [
                        ['label' => $this->t('analytics_invoice_docs'), 'data' => $trend['invoice_docs'], 'backgroundColor' => 'rgba(37,99,235,0.82)'],
                        ['label' => $this->t('analytics_order_docs'), 'data' => $trend['order_docs'], 'backgroundColor' => 'rgba(22,163,74,0.72)'],
                    ]
                ),
            ],
            'tables' => [
                $this->topRoutesTable($filters, $scope['routecodes']),
                $this->topCustomersTable($filters, $scope['routecodes']),
                $this->salesmanPerformanceTable($filters, $scope['routecodes']),
            ],
            'insights' => [
                $this->t('analytics_insight_cash_business', ['value' => $this->ratioText($mix['cash'], max($invoice['amount'], 0.01))]),
                $this->t('analytics_insight_order_pipeline', ['value' => $this->ratioText($orders['amount'], max($invoice['amount'], 0.01))]),
                $this->t('analytics_insight_promo_discount_load', ['value' => $this->ratioText($invoice['promo'] + $invoice['discount'], max($invoice['gross_sales'], 0.01))]),
            ],
        ];
    }

    private function collectionsBoard(array $filters, array $scope): array
    {
        $collections = $this->collectionSummary($filters, $scope['routecodes']);
        $trend = $this->collectionTrend($filters, $scope['routecodes']);
        $mix = $this->collectionMix($filters, $scope['routecodes']);

        $checkShare = $collections['amount'] > 0
            ? ($mix['checks'] / $collections['amount']) * 100
            : 0.0;

        return [
            'cards' => [
                $this->metricCard($this->t('analytics_collected_amount'), $collections['amount'], 'amount', $this->t('analytics_note_ar_documents', ['count' => number_format($collections['documents'])])),
                $this->metricCard($this->t('analytics_cash_collections'), $mix['cash'], 'amount', $this->t('analytics_note_cash_channel')),
                $this->metricCard($this->t('analytics_cheque_collections'), $mix['checks'], 'amount', $this->t('analytics_note_cheque_channel')),
                $this->metricCard($this->t('analytics_excess_payments'), $collections['excess'], 'amount', $this->t('analytics_note_over_collected_amount')),
                $this->metricCard($this->t('analytics_customers_collected'), $collections['customers'], 'number', $this->t('analytics_note_distinct_collected_customers')),
                $this->metricCard($this->t('analytics_cheque_share'), $checkShare, 'percent', $this->t('analytics_note_of_total_collections')),
            ],
            'charts' => [
                $this->lineChart(
                    $this->t('analytics_chart_collection_trend'),
                    $trend['labels'],
                    [
                        ['label' => $this->t('analytics_collected'), 'data' => $trend['amounts'], 'borderColor' => '#ea580c', 'backgroundColor' => 'rgba(234,88,12,0.16)'],
                        ['label' => $this->t('analytics_ar_value'), 'data' => $trend['invoice_totals'], 'borderColor' => '#475569', 'backgroundColor' => 'rgba(71,85,105,0.14)'],
                    ]
                ),
                $this->doughnutChart($this->t('analytics_chart_collection_channel_split'), [
                    ['label' => $this->t('cash'), 'value' => $mix['cash'], 'color' => '#16a34a'],
                    ['label' => $this->t('cheque'), 'value' => $mix['checks'], 'color' => '#2563eb'],
                ]),
                $this->barChart(
                    $this->t('analytics_chart_daily_collection_documents'),
                    $trend['labels'],
                    [
                        ['label' => $this->t('analytics_collection_docs'), 'data' => $trend['documents'], 'backgroundColor' => 'rgba(234,88,12,0.8)'],
                    ]
                ),
            ],
            'tables' => [
                $this->collectionRoutesTable($filters, $scope['routecodes']),
                $this->collectionCustomersTable($filters, $scope['routecodes']),
            ],
            'insights' => [
                $this->t('analytics_insight_cash_collections', ['value' => $this->ratioText($mix['cash'], max($collections['amount'], 0.01))]),
                $this->t('analytics_insight_cheque_collections', ['value' => number_format($checkShare, 1) . '%']),
                $this->t('analytics_insight_average_collected', ['value' => number_format($collections['documents'] > 0 ? ($collections['amount'] / $collections['documents']) : 0, 2)]),
            ],
        ];
    }

    private function inventoryBoard(array $filters, array $scope): array
    {
        $inventory = $this->inventorySummary($filters, $scope['routecodes']);
        $trend = $this->inventoryTrend($filters, $scope['routecodes']);

        $sellThrough = $inventory['loaded_qty'] > 0
            ? ($inventory['sold_qty'] / $inventory['loaded_qty']) * 100
            : 0.0;

        return [
            'cards' => [
                $this->metricCard($this->t('analytics_loaded_quantity'), $inventory['loaded_qty'], 'number', $this->t('analytics_note_all_load_events')),
                $this->metricCard($this->t('analytics_sold_quantity'), $inventory['sold_qty'], 'number', $this->t('analytics_note_net_sold_volume')),
                $this->metricCard($this->t('analytics_return_quantity'), $inventory['return_qty'], 'number', $this->t('analytics_note_good_returns')),
                $this->metricCard($this->t('analytics_damage_quantity'), $inventory['damage_qty'], 'number', $this->t('analytics_note_damaged_stock')),
                $this->metricCard($this->t('analytics_closing_quantity'), $inventory['closing_qty'], 'number', $this->t('analytics_note_end_stock_plus_unload')),
                $this->metricCard($this->t('analytics_sell_through'), $sellThrough, 'percent', $this->t('analytics_note_sold_vs_loaded')),
            ],
            'charts' => [
                $this->barChart(
                    $this->t('analytics_chart_daily_stock_movements'),
                    $trend['labels'],
                    [
                        ['label' => $this->t('analytics_loaded'), 'data' => $trend['loaded'], 'backgroundColor' => 'rgba(37,99,235,0.82)'],
                        ['label' => $this->t('analytics_sold'), 'data' => $trend['sold'], 'backgroundColor' => 'rgba(22,163,74,0.78)'],
                        ['label' => $this->t('analytics_returned'), 'data' => $trend['returned'], 'backgroundColor' => 'rgba(245,158,11,0.78)'],
                        ['label' => $this->t('analytics_damaged'), 'data' => $trend['damaged'], 'backgroundColor' => 'rgba(220,38,38,0.72)'],
                    ]
                ),
                $this->lineChart(
                    $this->t('analytics_chart_route_count_variance_trend'),
                    $trend['labels'],
                    [
                        ['label' => $this->t('analytics_active_routes'), 'data' => $trend['active_routes'], 'borderColor' => '#0f766e', 'backgroundColor' => 'rgba(15,118,110,0.15)'],
                        ['label' => $this->t('analytics_inventory_variance'), 'data' => $trend['inventory_variance'], 'borderColor' => '#dc2626', 'backgroundColor' => 'rgba(220,38,38,0.12)'],
                    ]
                ),
                $this->doughnutChart($this->t('analytics_chart_movement_composition'), [
                    ['label' => $this->t('analytics_sold'), 'value' => $inventory['sold_qty'], 'color' => '#16a34a'],
                    ['label' => $this->t('analytics_returned'), 'value' => $inventory['return_qty'], 'color' => '#f59e0b'],
                    ['label' => $this->t('analytics_damaged'), 'value' => $inventory['damage_qty'], 'color' => '#dc2626'],
                    ['label' => $this->t('analytics_promo_free'), 'value' => $inventory['free_qty'], 'color' => '#8b5cf6'],
                ]),
            ],
            'tables' => [
                $this->inventoryRoutesTable($filters, $scope['routecodes']),
                $this->inventoryItemsTable($filters, $scope['routecodes']),
            ],
            'insights' => [
                $this->t('analytics_insight_sell_through', ['value' => number_format($sellThrough, 1) . '%']),
                $this->t('analytics_insight_damaged_quantity', ['value' => $this->ratioText($inventory['damage_qty'], max($inventory['loaded_qty'], 1))]),
                $this->t('analytics_insight_closing_quantity', ['value' => $this->ratioText($inventory['closing_qty'], max($inventory['loaded_qty'], 1))]),
            ],
        ];
    }

    private function normalizeFilters(array $input): array
    {
        $to = isset($input['to_date']) && $this->isValidDate($input['to_date'])
            ? Carbon::parse($input['to_date'])->toDateString()
            : now()->toDateString();

        $from = isset($input['from_date']) && $this->isValidDate($input['from_date'])
            ? Carbon::parse($input['from_date'])->toDateString()
            : Carbon::parse($to)->subDays(29)->toDateString();

        if ($from > $to) {
            $from = Carbon::parse($to)->subDays(29)->toDateString();
        }

        return [
            'from_date' => $from,
            'to_date' => $to,
            'cmpycode' => $this->nullableInt($input['cmpycode'] ?? null),
            'regionmstcode' => $this->nullableInt($input['regionmstcode'] ?? null),
            'depotcode' => $this->nullableInt($input['depotcode'] ?? null),
            'areacode' => $this->nullableInt($input['areacode'] ?? null),
            'subareacode' => $this->nullableInt($input['subareacode'] ?? null),
            'routecode' => $this->nullableInt($input['routecode'] ?? null),
        ];
    }

    private function resolveScope(User $user, array $filters): array
    {
        $routeRows = $this->routeScopeRows($user);

        $scopedRows = $routeRows
            ->when($filters['cmpycode'], fn (Collection $rows) => $rows->where('cmpycode', $filters['cmpycode']))
            ->when($filters['regionmstcode'], fn (Collection $rows) => $rows->where('regionmstcode', $filters['regionmstcode']))
            ->when($filters['depotcode'], fn (Collection $rows) => $rows->where('depotcode', $filters['depotcode']))
            ->when($filters['areacode'], fn (Collection $rows) => $rows->where('areacode', $filters['areacode']))
            ->when($filters['subareacode'], fn (Collection $rows) => $rows->where('subareacode', $filters['subareacode']))
            ->when($filters['routecode'], fn (Collection $rows) => $rows->where('routecode', $filters['routecode']))
            ->values();

        $limited = $this->userIsScoped($user);
        $routecodes = $scopedRows->pluck('routecode')->filter()->map(fn ($value) => (int) $value)->unique()->values()->all();
        $companyOptions = $this->companyOptions($user, $routeRows);

        return [
            'routecodes' => $routecodes,
            'matches_none' => $scopedRows->isEmpty(),
            'options' => [
                'companies' => $companyOptions,
                'regions' => $this->distinctOptions($routeRows, 'regionmstcode', 'region_label'),
                'depots' => $this->distinctOptions($routeRows, 'depotcode', 'depot_label'),
                'areas' => $this->distinctOptions($routeRows, 'areacode', 'area_label'),
                'subAreas' => $this->distinctOptions($routeRows, 'subareacode', 'subarea_label'),
                'routes' => $this->distinctOptions($routeRows, 'routecode', 'route_label'),
            ],
            'summary' => [
                'limited' => $limited,
                'access_type' => $this->accessTypeLabel((int) ($user->accesstypeid ?? 0)),
                'route_count' => count($routecodes),
                'badges' => [
                    ['label' => $this->t('analytics_companies'), 'value' => count($companyOptions)],
                    ['label' => $this->t('analytics_regions'), 'value' => $routeRows->pluck('regionmstcode')->filter()->unique()->count()],
                    ['label' => $this->t('analytics_depots'), 'value' => $routeRows->pluck('depotcode')->filter()->unique()->count()],
                    ['label' => $this->t('routes'), 'value' => count($routecodes)],
                ],
                'message' => $limited
                    ? $this->t('analytics_scope_message_limited')
                    : $this->t('analytics_scope_message_all'),
            ],
        ];
    }

    private function routeScopeRows(User $user): Collection
    {
        if (!Schema::hasTable('routemaster')) {
            return collect();
        }

        $route = $this->qualifiedAlias('route');
        $sub = $this->qualifiedAlias('sub');
        $area = $this->qualifiedAlias('area');
        $depot = $this->qualifiedAlias('depot');
        $routeCompany = $this->qualifiedAlias('route_company');
        $depotCompany = $this->qualifiedAlias('depot_company');
        $region = $this->qualifiedAlias('region');
        $salesman = $this->qualifiedAlias('salesman');

        $query = DB::table('routemaster as route')
            ->leftJoin('subareamaster as sub', 'sub.subareacode', '=', 'route.subareacode')
            ->leftJoin('areamaster as area', 'area.areacode', '=', 'sub.areacode')
            ->leftJoin('depotmaster as depot', 'depot.depotcode', '=', 'area.depotcode')
            ->leftJoin('company as route_company', 'route_company.cmpycode', '=', 'route.cmpycode')
            ->leftJoin('company as depot_company', 'depot_company.cmpycode', '=', 'depot.cmpycode')
            ->leftJoin('regionmaster as region', 'region.regionmstcode', '=', 'route.regionmstcode')
            ->leftJoin('salesman as salesman', 'salesman.salesmancode', '=', 'route.salesmancode')
            ->selectRaw("
                {$route}.routecode,
                COALESCE({$route}.routename, '') as routename,
                COALESCE({$route}.arbroutename, '') as arbroutename,
                COALESCE({$route}.cmpycode, {$depotCompany}.cmpycode, 0) as cmpycode,
                COALESCE({$routeCompany}.name, {$depotCompany}.name, '') as company_name,
                COALESCE({$route}.regionmstcode, 0) as regionmstcode,
                COALESCE({$region}.regionmstname, '') as regionmstname,
                COALESCE({$region}.countrycode, 0) as countrycode,
                COALESCE({$depot}.depotcode, 0) as depotcode,
                COALESCE({$depot}.depotname, '') as depotname,
                COALESCE({$area}.areacode, 0) as areacode,
                COALESCE({$area}.areaname, '') as areaname,
                COALESCE({$sub}.subareacode, 0) as subareacode,
                COALESCE({$sub}.subareaname, '') as subareaname,
                COALESCE({$route}.salesmancode, 0) as salesmancode,
                COALESCE({$salesman}.salesmanname1, '') as salesmanname1
            ")
            ->when(
                Schema::hasColumn('routemaster', 'routetmpl'),
                fn ($builder) => $builder->where('route.routetmpl', 0)
            )
            ->when(
                Schema::hasColumn('routemaster', 'activestatus'),
                fn ($builder) => $builder->where('route.activestatus', 1)
            );

        $accessType = (int) ($user->accesstypeid ?? 0);
        $accessRows = UserAccessCode::where('username', $user->username)->get();

        if ($this->userIsScoped($user) && $accessRows->isNotEmpty()) {
            $ids = (match ($accessType) {
                1 => $accessRows->pluck('cmpycode'),
                2 => $accessRows->pluck('countrycode'),
                3 => $accessRows->pluck('regionmstcode'),
                4 => $accessRows->pluck('depotcode'),
                5 => $accessRows->pluck('areacode'),
                6 => $accessRows->pluck('subareacode'),
                default => collect(),
            })->filter()->map(fn ($value) => (int) $value)->unique()->values();

            $query->when($ids->isNotEmpty(), function ($builder) use ($accessType, $ids, $route, $depotCompany) {
                match ($accessType) {
                    1 => $builder->whereIn(DB::raw("COALESCE({$route}.cmpycode, {$depotCompany}.cmpycode)"), $ids->all()),
                    2 => $builder->whereIn('region.countrycode', $ids->all()),
                    3 => $builder->whereIn('route.regionmstcode', $ids->all()),
                    4 => $builder->whereIn('depot.depotcode', $ids->all()),
                    5 => $builder->whereIn('area.areacode', $ids->all()),
                    6 => $builder->whereIn('sub.subareacode', $ids->all()),
                    default => null,
                };
            });
        }

        return $query->orderBy('route.routecode')->get()->map(function ($row) {
            return [
                'routecode' => (int) ($row->routecode ?? 0),
                'cmpycode' => (int) ($row->cmpycode ?? 0),
                'regionmstcode' => (int) ($row->regionmstcode ?? 0),
                'countrycode' => (int) ($row->countrycode ?? 0),
                'depotcode' => (int) ($row->depotcode ?? 0),
                'areacode' => (int) ($row->areacode ?? 0),
                'subareacode' => (int) ($row->subareacode ?? 0),
                'company_label' => $this->label($row->cmpycode, $row->company_name),
                'region_label' => $this->label($row->regionmstcode, $row->regionmstname),
                'depot_label' => $this->label($row->depotcode, $row->depotname),
                'area_label' => $this->label($row->areacode, $row->areaname),
                'subarea_label' => $this->label($row->subareacode, $row->subareaname),
                'route_label' => trim(($row->routecode ?? '') . ' - ' . ($row->routename ?: $row->arbroutename)),
            ];
        });
    }

    private function invoiceSummary(array $filters, array $routecodes): array
    {
        if (!$this->hasTables(['invoiceheader'])) {
            return ['amount' => 0.0, 'gross_sales' => 0.0, 'documents' => 0, 'customers' => 0, 'routes' => 0, 'returns' => 0.0, 'damaged' => 0.0, 'promo' => 0.0, 'discount' => 0.0];
        }

        $row = $this->invoiceBaseQuery($filters, $routecodes)
            ->selectRaw('
                COUNT(*) as documents,
                COUNT(DISTINCT routecode) as routes,
                COUNT(DISTINCT customercode) as customers,
                COALESCE(SUM(totalinvoiceamount), 0) as amount,
                COALESCE(SUM(totalsalesamount), 0) as gross_sales,
                COALESCE(SUM(totalreturnamount), 0) as returns,
                COALESCE(SUM(totaldamagedamount), 0) as damaged,
                COALESCE(SUM(totalpromoamount), 0) as promo,
                COALESCE(SUM(totaldiscountamount), 0) as discount
            ')
            ->first();

        return [
            'amount' => (float) ($row->amount ?? 0),
            'gross_sales' => (float) ($row->gross_sales ?? 0),
            'documents' => (int) ($row->documents ?? 0),
            'customers' => (int) ($row->customers ?? 0),
            'routes' => (int) ($row->routes ?? 0),
            'returns' => (float) ($row->returns ?? 0),
            'damaged' => (float) ($row->damaged ?? 0),
            'promo' => (float) ($row->promo ?? 0),
            'discount' => (float) ($row->discount ?? 0),
        ];
    }

    private function orderSummary(array $filters, array $routecodes): array
    {
        if (!$this->hasTables(['salesorderheader'])) {
            return ['amount' => 0.0, 'documents' => 0, 'customers' => 0, 'routes' => 0];
        }

        $row = $this->orderBaseQuery($filters, $routecodes)
            ->selectRaw('
                COUNT(*) as documents,
                COUNT(DISTINCT routecode) as routes,
                COUNT(DISTINCT customercode) as customers,
                COALESCE(SUM(totalinvoiceamount), 0) as amount
            ')
            ->first();

        return [
            'amount' => (float) ($row->amount ?? 0),
            'documents' => (int) ($row->documents ?? 0),
            'customers' => (int) ($row->customers ?? 0),
            'routes' => (int) ($row->routes ?? 0),
        ];
    }

    private function collectionSummary(array $filters, array $routecodes): array
    {
        if (!$this->hasTables(['arheader'])) {
            return ['amount' => 0.0, 'invoice_total' => 0.0, 'documents' => 0, 'customers' => 0, 'excess' => 0.0];
        }

        $row = $this->collectionBaseQuery($filters, $routecodes)
            ->selectRaw('
                COUNT(*) as documents,
                COUNT(DISTINCT customercode) as customers,
                COALESCE(SUM(amountpaid), 0) as amount,
                COALESCE(SUM(totalinvoiceamount), 0) as invoice_total,
                COALESCE(SUM(excesspayment), 0) as excess
            ')
            ->first();

        return [
            'amount' => (float) ($row->amount ?? 0),
            'invoice_total' => (float) ($row->invoice_total ?? 0),
            'documents' => (int) ($row->documents ?? 0),
            'customers' => (int) ($row->customers ?? 0),
            'excess' => (float) ($row->excess ?? 0),
        ];
    }

    private function inventorySummary(array $filters, array $routecodes): array
    {
        if (!$this->hasTables(['inventorysummarydetail', 'startendday'])) {
            return ['loaded_qty' => 0.0, 'sold_qty' => 0.0, 'return_qty' => 0.0, 'damage_qty' => 0.0, 'free_qty' => 0.0, 'closing_qty' => 0.0, 'routes' => 0];
        }

        $detail = $this->qualifiedAlias('isd');
        $day = $this->qualifiedAlias('sed');
        $row = $this->inventoryBaseQuery($filters, $routecodes)
            ->selectRaw("
                COUNT(DISTINCT {$day}.routecode) as routes,
                COALESCE(SUM({$detail}.loadqty), 0) as loaded_qty,
                COALESCE(SUM({$detail}.saleqty), 0) as sold_qty,
                COALESCE(SUM({$detail}.returnqty), 0) as return_qty,
                COALESCE(SUM({$detail}.damagedcutqty + {$detail}.damagedaddqty + {$detail}.damagedunloadqty + {$detail}.truckdamagedunloadqty), 0) as damage_qty,
                COALESCE(SUM({$detail}.freesampleqty + {$detail}.promoqty), 0) as free_qty,
                COALESCE(SUM({$detail}.endstockqty + {$detail}.unloadqty), 0) as closing_qty
            ")
            ->first();

        return [
            'loaded_qty' => (float) ($row->loaded_qty ?? 0),
            'sold_qty' => (float) ($row->sold_qty ?? 0),
            'return_qty' => (float) ($row->return_qty ?? 0),
            'damage_qty' => (float) ($row->damage_qty ?? 0),
            'free_qty' => (float) ($row->free_qty ?? 0),
            'closing_qty' => (float) ($row->closing_qty ?? 0),
            'routes' => (int) ($row->routes ?? 0),
        ];
    }

    private function overviewTrend(array $filters, array $routecodes): array
    {
        $labels = $this->dateLabels($filters);
        $invoiceRows = $this->invoiceBaseQuery($filters, $routecodes)
            ->selectRaw('DATE(transactiondate) as period, SUM(totalinvoiceamount) as amount, COUNT(*) as documents, COUNT(DISTINCT routecode) as routes, COUNT(DISTINCT customercode) as customers')
            ->groupBy(DB::raw('DATE(transactiondate)'))
            ->get()
            ->keyBy('period');

        $orderRows = $this->orderBaseQuery($filters, $routecodes)
            ->selectRaw('DATE(transactiondate) as period, SUM(totalinvoiceamount) as amount')
            ->groupBy(DB::raw('DATE(transactiondate)'))
            ->get()
            ->keyBy('period');

        $collectionRows = $this->collectionBaseQuery($filters, $routecodes)
            ->selectRaw('DATE(transactiondate) as period, SUM(amountpaid) as amount')
            ->groupBy(DB::raw('DATE(transactiondate)'))
            ->get()
            ->keyBy('period');

        return [
            'labels' => array_values($labels),
            'invoice_amounts' => $this->seriesFromMap($labels, $invoiceRows, 'amount'),
            'order_amounts' => $this->seriesFromMap($labels, $orderRows, 'amount'),
            'collection_amounts' => $this->seriesFromMap($labels, $collectionRows, 'amount'),
            'active_routes' => $this->seriesFromMap($labels, $invoiceRows, 'routes'),
            'customers' => $this->seriesFromMap($labels, $invoiceRows, 'customers'),
        ];
    }

    private function salesTrend(array $filters, array $routecodes): array
    {
        $labels = $this->dateLabels($filters);
        $invoiceRows = $this->invoiceBaseQuery($filters, $routecodes)
            ->selectRaw('DATE(transactiondate) as period, SUM(totalinvoiceamount) as amount, COUNT(*) as documents')
            ->groupBy(DB::raw('DATE(transactiondate)'))
            ->get()
            ->keyBy('period');

        $orderRows = $this->orderBaseQuery($filters, $routecodes)
            ->selectRaw('DATE(transactiondate) as period, SUM(totalinvoiceamount) as amount, COUNT(*) as documents')
            ->groupBy(DB::raw('DATE(transactiondate)'))
            ->get()
            ->keyBy('period');

        return [
            'labels' => array_values($labels),
            'invoice_amounts' => $this->seriesFromMap($labels, $invoiceRows, 'amount'),
            'order_amounts' => $this->seriesFromMap($labels, $orderRows, 'amount'),
            'invoice_docs' => $this->seriesFromMap($labels, $invoiceRows, 'documents'),
            'order_docs' => $this->seriesFromMap($labels, $orderRows, 'documents'),
        ];
    }

    private function collectionTrend(array $filters, array $routecodes): array
    {
        $labels = $this->dateLabels($filters);
        $rows = $this->collectionBaseQuery($filters, $routecodes)
            ->selectRaw('DATE(transactiondate) as period, SUM(amountpaid) as amount, SUM(totalinvoiceamount) as invoice_total, COUNT(*) as documents')
            ->groupBy(DB::raw('DATE(transactiondate)'))
            ->get()
            ->keyBy('period');

        return [
            'labels' => array_values($labels),
            'amounts' => $this->seriesFromMap($labels, $rows, 'amount'),
            'invoice_totals' => $this->seriesFromMap($labels, $rows, 'invoice_total'),
            'documents' => $this->seriesFromMap($labels, $rows, 'documents'),
        ];
    }

    private function inventoryTrend(array $filters, array $routecodes): array
    {
        $labels = $this->dateLabels($filters);

        if (!$this->hasTables(['inventorysummarydetail', 'startendday'])) {
            return [
                'labels' => array_values($labels),
                'loaded' => array_fill(0, count($labels), 0),
                'sold' => array_fill(0, count($labels), 0),
                'returned' => array_fill(0, count($labels), 0),
                'damaged' => array_fill(0, count($labels), 0),
                'active_routes' => array_fill(0, count($labels), 0),
                'inventory_variance' => array_fill(0, count($labels), 0),
            ];
        }

        $day = $this->qualifiedAlias('sed');
        $detail = $this->qualifiedAlias('isd');
        $movementRows = $this->inventoryBaseQuery($filters, $routecodes)
            ->selectRaw("
                DATE({$day}.routestartdate) as period,
                SUM({$detail}.loadqty) as loaded,
                SUM({$detail}.saleqty) as sold,
                SUM({$detail}.returnqty) as returned,
                SUM({$detail}.damagedcutqty + {$detail}.damagedaddqty + {$detail}.damagedunloadqty + {$detail}.truckdamagedunloadqty) as damaged
            ")
            ->groupBy(DB::raw('DATE(' . $day . '.routestartdate)'))
            ->get()
            ->keyBy('period');

        $routeRows = $this->startEndDayBaseQuery($filters, $routecodes)
            ->selectRaw('DATE(routestartdate) as period, COUNT(DISTINCT routecode) as active_routes, SUM(inventoryvariance) as inventory_variance')
            ->groupBy(DB::raw('DATE(routestartdate)'))
            ->get()
            ->keyBy('period');

        return [
            'labels' => array_values($labels),
            'loaded' => $this->seriesFromMap($labels, $movementRows, 'loaded'),
            'sold' => $this->seriesFromMap($labels, $movementRows, 'sold'),
            'returned' => $this->seriesFromMap($labels, $movementRows, 'returned'),
            'damaged' => $this->seriesFromMap($labels, $movementRows, 'damaged'),
            'active_routes' => $this->seriesFromMap($labels, $routeRows, 'active_routes'),
            'inventory_variance' => $this->seriesFromMap($labels, $routeRows, 'inventory_variance'),
        ];
    }

    private function paymentMix(array $filters, array $routecodes): array
    {
        if (!$this->hasTables(['invoiceheader', 'customermaster'])) {
            return ['cash' => 0.0, 'gc' => 0.0, 'tc' => 0.0];
        }

        $customer = $this->qualifiedAlias('customer');
        $invoice = $this->qualifiedAlias('ih');
        $row = $this->invoiceBaseQuery($filters, $routecodes)
            ->join('customermaster as customer', 'customer.customercode', '=', 'ih.customercode')
            ->selectRaw("
                SUM(CASE WHEN COALESCE({$customer}.invoicepaymentterms, 0) < 2 THEN {$invoice}.totalinvoiceamount ELSE 0 END) as cash,
                SUM(CASE WHEN COALESCE({$customer}.invoicepaymentterms, 0) = 2 THEN {$invoice}.totalinvoiceamount ELSE 0 END) as gc,
                SUM(CASE WHEN COALESCE({$customer}.invoicepaymentterms, 0) > 2 THEN {$invoice}.totalinvoiceamount ELSE 0 END) as tc
            ")
            ->first();

        return [
            'cash' => (float) ($row->cash ?? 0),
            'gc' => (float) ($row->gc ?? 0),
            'tc' => (float) ($row->tc ?? 0),
        ];
    }

    private function collectionMix(array $filters, array $routecodes): array
    {
        if (!$this->hasTables(['cashcheckdetail', 'arheader'])) {
            return ['cash' => 0.0, 'checks' => 0.0];
        }

        $cash = $this->qualifiedAlias('ccd');
        $ar = $this->qualifiedAlias('arh');
        $row = DB::table('cashcheckdetail as ccd')
            ->join('arheader as arh', function ($join) {
                $join->on('arh.routekey', '=', 'ccd.routekey')
                    ->on('arh.visitkey', '=', 'ccd.visitkey');
            })
            ->whereBetween('arh.transactiondate', [$filters['from_date'], $filters['to_date']])
            ->where('arh.voidflag', 0)
            ->when(!empty($routecodes), fn ($query) => $query->whereIn('arh.routecode', $routecodes))
            ->selectRaw("
                SUM(CASE WHEN {$cash}.typecode = 0 THEN {$cash}.amount ELSE 0 END) as cash,
                SUM(CASE WHEN {$cash}.typecode = 1 THEN {$cash}.amount ELSE 0 END) as checks
            ")
            ->first();

        return [
            'cash' => (float) ($row->cash ?? 0),
            'checks' => (float) ($row->checks ?? 0),
        ];
    }

    private function topRoutesTable(array $filters, array $routecodes): array
    {
        $rows = [];

        if ($this->hasTables(['invoiceheader', 'routemaster', 'salesman'])) {
            $invoice = $this->qualifiedAlias('ih');
            $route = $this->qualifiedAlias('route');
            $salesman = $this->qualifiedAlias('salesman');
            $rows = $this->invoiceBaseQuery($filters, $routecodes)
                ->leftJoin('routemaster as route', 'route.routecode', '=', 'ih.routecode')
                ->leftJoin('salesman as salesman', 'salesman.salesmancode', '=', 'ih.salesmancode')
                ->groupBy('ih.routecode', 'route.routename', 'route.arbroutename', 'salesman.salesmanname1')
                ->select([
                    'ih.routecode',
                    'route.routename',
                    'route.arbroutename',
                    'salesman.salesmanname1',
                ])
                ->selectRaw("COUNT(*) as documents, SUM({$invoice}.totalinvoiceamount) as total_amount, COUNT(DISTINCT {$invoice}.customercode) as customers")
                ->orderByDesc('total_amount')
                ->limit(8)
                ->get()
                ->map(fn ($row) => [
                    'route_label' => trim(((int) $row->routecode) . ' - ' . ($row->routename ?: $row->arbroutename) . ($row->salesmanname1 ? ' (' . $row->salesmanname1 . ')' : '')),
                    'documents' => (int) ($row->documents ?? 0),
                    'customers' => (int) ($row->customers ?? 0),
                    'total_amount' => (float) ($row->total_amount ?? 0),
                ])
                ->all();
        }

        return $this->table(
            $this->t('analytics_top_routes'),
            [
                ['key' => 'route_label', 'label' => $this->t('route'), 'kind' => 'text'],
                ['key' => 'documents', 'label' => $this->t('analytics_docs'), 'kind' => 'number'],
                ['key' => 'customers', 'label' => $this->t('analytics_customers'), 'kind' => 'number'],
                ['key' => 'total_amount', 'label' => $this->t('analytics_value'), 'kind' => 'amount'],
            ],
            $rows
        );
    }

    private function topCustomersTable(array $filters, array $routecodes): array
    {
        $rows = [];

        if ($this->hasTables(['invoiceheader', 'customermaster'])) {
            $invoice = $this->qualifiedAlias('ih');
            $rows = $this->invoiceBaseQuery($filters, $routecodes)
                ->leftJoin('customermaster as customer', 'customer.customercode', '=', 'ih.customercode')
                ->groupBy('ih.customercode', 'customer.alternatecode', 'customer.customername', 'customer.arbcustomername')
                ->select([
                    'ih.customercode',
                    'customer.alternatecode',
                    'customer.customername',
                    'customer.arbcustomername',
                ])
                ->selectRaw("COUNT(*) as documents, SUM({$invoice}.totalinvoiceamount) as total_amount")
                ->orderByDesc('total_amount')
                ->limit(10)
                ->get()
                ->map(fn ($row) => [
                    'customer_label' => trim(((int) $row->customercode) . ' - ' . ($row->customername ?: $row->arbcustomername ?: $row->alternatecode)),
                    'documents' => (int) ($row->documents ?? 0),
                    'total_amount' => (float) ($row->total_amount ?? 0),
                ])
                ->all();
        }

        return $this->table(
            $this->t('analytics_top_customers'),
            [
                ['key' => 'customer_label', 'label' => $this->t('customer'), 'kind' => 'text'],
                ['key' => 'documents', 'label' => $this->t('analytics_docs'), 'kind' => 'number'],
                ['key' => 'total_amount', 'label' => $this->t('analytics_value'), 'kind' => 'amount'],
            ],
            $rows
        );
    }

    private function salesmanPerformanceTable(array $filters, array $routecodes): array
    {
        $rows = [];

        if ($this->hasTables(['invoiceheader', 'salesman'])) {
            $invoice = $this->qualifiedAlias('ih');
            $rows = $this->invoiceBaseQuery($filters, $routecodes)
                ->leftJoin('salesman as salesman', 'salesman.salesmancode', '=', 'ih.salesmancode')
                ->groupBy('ih.salesmancode', 'salesman.salesmanname1', 'salesman.arbsalesmanname1')
                ->select([
                    'ih.salesmancode',
                    'salesman.salesmanname1',
                    'salesman.arbsalesmanname1',
                ])
                ->selectRaw("COUNT(*) as documents, COUNT(DISTINCT {$invoice}.customercode) as customers, SUM({$invoice}.totalinvoiceamount) as total_amount")
                ->orderByDesc('total_amount')
                ->limit(10)
                ->get()
                ->map(fn ($row) => [
                    'salesman_label' => trim(((int) ($row->salesmancode ?? 0)) . ' - ' . ($row->salesmanname1 ?: $row->arbsalesmanname1)),
                    'documents' => (int) ($row->documents ?? 0),
                    'customers' => (int) ($row->customers ?? 0),
                    'total_amount' => (float) ($row->total_amount ?? 0),
                ])
                ->all();
        }

        return $this->table(
            $this->t('analytics_salesman_performance'),
            [
                ['key' => 'salesman_label', 'label' => $this->t('salesman'), 'kind' => 'text'],
                ['key' => 'documents', 'label' => $this->t('analytics_docs'), 'kind' => 'number'],
                ['key' => 'customers', 'label' => $this->t('analytics_customers'), 'kind' => 'number'],
                ['key' => 'total_amount', 'label' => $this->t('analytics_value'), 'kind' => 'amount'],
            ],
            $rows
        );
    }

    private function collectionRoutesTable(array $filters, array $routecodes): array
    {
        $rows = [];

        if ($this->hasTables(['arheader', 'routemaster', 'salesman'])) {
            $ar = $this->qualifiedAlias('arh');
            $rows = $this->collectionBaseQuery($filters, $routecodes)
                ->leftJoin('routemaster as route', 'route.routecode', '=', 'arh.routecode')
                ->leftJoin('salesman as salesman', 'salesman.salesmancode', '=', 'arh.salesmancode')
                ->groupBy('arh.routecode', 'route.routename', 'route.arbroutename', 'salesman.salesmanname1')
                ->select([
                    'arh.routecode',
                    'route.routename',
                    'route.arbroutename',
                    'salesman.salesmanname1',
                ])
                ->selectRaw("COUNT(*) as documents, SUM({$ar}.amountpaid) as collected, SUM({$ar}.totalinvoiceamount) as invoice_total")
                ->orderByDesc('collected')
                ->limit(8)
                ->get()
                ->map(fn ($row) => [
                    'route_label' => trim(((int) $row->routecode) . ' - ' . ($row->routename ?: $row->arbroutename) . ($row->salesmanname1 ? ' (' . $row->salesmanname1 . ')' : '')),
                    'documents' => (int) ($row->documents ?? 0),
                    'invoice_total' => (float) ($row->invoice_total ?? 0),
                    'collected' => (float) ($row->collected ?? 0),
                ])
                ->all();
        }

        return $this->table(
            $this->t('analytics_top_collection_routes'),
            [
                ['key' => 'route_label', 'label' => $this->t('route'), 'kind' => 'text'],
                ['key' => 'documents', 'label' => $this->t('analytics_docs'), 'kind' => 'number'],
                ['key' => 'invoice_total', 'label' => $this->t('analytics_ar_value'), 'kind' => 'amount'],
                ['key' => 'collected', 'label' => $this->t('analytics_collected'), 'kind' => 'amount'],
            ],
            $rows
        );
    }

    private function collectionCustomersTable(array $filters, array $routecodes): array
    {
        $rows = [];

        if ($this->hasTables(['arheader', 'customermaster'])) {
            $ar = $this->qualifiedAlias('arh');
            $rows = $this->collectionBaseQuery($filters, $routecodes)
                ->leftJoin('customermaster as customer', 'customer.customercode', '=', 'arh.customercode')
                ->groupBy('arh.customercode', 'customer.alternatecode', 'customer.customername', 'customer.arbcustomername')
                ->select([
                    'arh.customercode',
                    'customer.alternatecode',
                    'customer.customername',
                    'customer.arbcustomername',
                ])
                ->selectRaw("COUNT(*) as documents, SUM({$ar}.amountpaid) as collected, SUM({$ar}.totalinvoiceamount) as invoice_total")
                ->orderByDesc('collected')
                ->limit(10)
                ->get()
                ->map(fn ($row) => [
                    'customer_label' => trim(((int) $row->customercode) . ' - ' . ($row->customername ?: $row->arbcustomername ?: $row->alternatecode)),
                    'documents' => (int) ($row->documents ?? 0),
                    'invoice_total' => (float) ($row->invoice_total ?? 0),
                    'collected' => (float) ($row->collected ?? 0),
                ])
                ->all();
        }

        return $this->table(
            $this->t('analytics_top_collection_customers'),
            [
                ['key' => 'customer_label', 'label' => $this->t('customer'), 'kind' => 'text'],
                ['key' => 'documents', 'label' => $this->t('analytics_docs'), 'kind' => 'number'],
                ['key' => 'invoice_total', 'label' => $this->t('analytics_ar_value'), 'kind' => 'amount'],
                ['key' => 'collected', 'label' => $this->t('analytics_collected'), 'kind' => 'amount'],
            ],
            $rows
        );
    }

    private function inventoryRoutesTable(array $filters, array $routecodes): array
    {
        $rows = [];

        if ($this->hasTables(['inventorysummarydetail', 'startendday', 'routemaster'])) {
            $detail = $this->qualifiedAlias('isd');
            $day = $this->qualifiedAlias('sed');
            $rows = $this->inventoryBaseQuery($filters, $routecodes)
                ->leftJoin('routemaster as route', 'route.routecode', '=', 'sed.routecode')
                ->groupBy('sed.routecode', 'route.routename', 'route.arbroutename')
                ->select([
                    'sed.routecode',
                    'route.routename',
                    'route.arbroutename',
                ])
                ->selectRaw("
                    SUM({$detail}.loadqty) as loaded_qty,
                    SUM({$detail}.saleqty) as sold_qty,
                    SUM({$detail}.returnqty) as return_qty,
                    SUM({$detail}.endstockqty + {$detail}.unloadqty) as closing_qty
                ")
                ->orderByDesc('sold_qty')
                ->limit(8)
                ->get()
                ->map(fn ($row) => [
                    'route_label' => trim(((int) $row->routecode) . ' - ' . ($row->routename ?: $row->arbroutename)),
                    'loaded_qty' => (float) ($row->loaded_qty ?? 0),
                    'sold_qty' => (float) ($row->sold_qty ?? 0),
                    'return_qty' => (float) ($row->return_qty ?? 0),
                    'closing_qty' => (float) ($row->closing_qty ?? 0),
                ])
                ->all();
        }

        return $this->table(
            $this->t('analytics_route_stock_movement'),
            [
                ['key' => 'route_label', 'label' => $this->t('route'), 'kind' => 'text'],
                ['key' => 'loaded_qty', 'label' => $this->t('analytics_loaded'), 'kind' => 'number'],
                ['key' => 'sold_qty', 'label' => $this->t('analytics_sold'), 'kind' => 'number'],
                ['key' => 'return_qty', 'label' => $this->t('analytics_return'), 'kind' => 'number'],
                ['key' => 'closing_qty', 'label' => $this->t('analytics_closing'), 'kind' => 'number'],
            ],
            $rows
        );
    }

    private function inventoryItemsTable(array $filters, array $routecodes): array
    {
        $rows = [];

        if ($this->hasTables(['inventorysummarydetail', 'startendday', 'itemmaster'])) {
            $detail = $this->qualifiedAlias('isd');
            $rows = $this->inventoryBaseQuery($filters, $routecodes)
                ->join('itemmaster as item', 'item.actualitemcode', '=', 'isd.itemcode')
                ->groupBy('isd.itemcode', 'item.alternatecode', 'item.itemshortdescription', 'item.itemdescription')
                ->select([
                    'isd.itemcode',
                    'item.alternatecode',
                    'item.itemshortdescription',
                    'item.itemdescription',
                ])
                ->selectRaw("
                    SUM({$detail}.saleqty) as sold_qty,
                    SUM({$detail}.returnqty) as return_qty,
                    SUM({$detail}.damagedcutqty + {$detail}.damagedaddqty + {$detail}.damagedunloadqty + {$detail}.truckdamagedunloadqty) as damage_qty
                ")
                ->orderByDesc('sold_qty')
                ->limit(10)
                ->get()
                ->map(fn ($row) => [
                    'item_label' => trim(((int) $row->itemcode) . ' - ' . ($row->itemshortdescription ?: $row->itemdescription ?: $row->alternatecode)),
                    'sold_qty' => (float) ($row->sold_qty ?? 0),
                    'return_qty' => (float) ($row->return_qty ?? 0),
                    'damage_qty' => (float) ($row->damage_qty ?? 0),
                ])
                ->all();
        }

        return $this->table(
            $this->t('analytics_fast_moving_items'),
            [
                ['key' => 'item_label', 'label' => $this->t('item'), 'kind' => 'text'],
                ['key' => 'sold_qty', 'label' => $this->t('analytics_sold'), 'kind' => 'number'],
                ['key' => 'return_qty', 'label' => $this->t('analytics_return'), 'kind' => 'number'],
                ['key' => 'damage_qty', 'label' => $this->t('analytics_damage'), 'kind' => 'number'],
            ],
            $rows
        );
    }

    private function invoiceBaseQuery(array $filters, array $routecodes)
    {
        return DB::table('invoiceheader as ih')
            ->whereBetween('ih.transactiondate', [$filters['from_date'], $filters['to_date']])
            ->where('ih.voidflag', 0)
            ->when(!empty($routecodes), fn ($query) => $query->whereIn('ih.routecode', $routecodes));
    }

    private function orderBaseQuery(array $filters, array $routecodes)
    {
        return DB::table('salesorderheader as soh')
            ->whereBetween('soh.transactiondate', [$filters['from_date'], $filters['to_date']])
            ->where('soh.voidflag', 0)
            ->when(!empty($routecodes), fn ($query) => $query->whereIn('soh.routecode', $routecodes));
    }

    private function collectionBaseQuery(array $filters, array $routecodes)
    {
        return DB::table('arheader as arh')
            ->whereBetween('arh.transactiondate', [$filters['from_date'], $filters['to_date']])
            ->where('arh.voidflag', 0)
            ->when(!empty($routecodes), fn ($query) => $query->whereIn('arh.routecode', $routecodes));
    }

    private function startEndDayBaseQuery(array $filters, array $routecodes)
    {
        return DB::table('startendday as sed')
            ->whereBetween('sed.routestartdate', [$filters['from_date'], $filters['to_date']])
            ->when(!empty($routecodes), fn ($query) => $query->whereIn('sed.routecode', $routecodes));
    }

    private function inventoryBaseQuery(array $filters, array $routecodes)
    {
        return DB::table('inventorysummarydetail as isd')
            ->join('startendday as sed', 'sed.routekey', '=', 'isd.routekey')
            ->whereBetween('sed.routestartdate', [$filters['from_date'], $filters['to_date']])
            ->when(!empty($routecodes), fn ($query) => $query->whereIn('sed.routecode', $routecodes));
    }

    private function dateLabels(array $filters): array
    {
        $labels = [];

        foreach (CarbonPeriod::create($filters['from_date'], $filters['to_date']) as $date) {
            $labels[$date->toDateString()] = $date->format('d M');
        }

        return $labels;
    }

    private function seriesFromMap(array $labels, Collection $rows, string $field): array
    {
        return collect(array_keys($labels))
            ->map(function ($key) use ($rows, $field) {
                $row = $rows->get($key);
                return $row ? (float) ($row->{$field} ?? 0) : 0;
            })
            ->values()
            ->all();
    }

    private function boardMeta(string $board): array
    {
        return match ($board) {
            'sales' => [
                'key' => 'sales',
                'title' => $this->t('sales_analytics'),
                'subtitle' => $this->t('analytics_sales_subtitle'),
                'accent' => '#0f766e',
            ],
            'collections' => [
                'key' => 'collections',
                'title' => $this->t('collection_analytics'),
                'subtitle' => $this->t('analytics_collections_subtitle'),
                'accent' => '#ea580c',
            ],
            'inventory' => [
                'key' => 'inventory',
                'title' => $this->t('inventory_analytics'),
                'subtitle' => $this->t('analytics_inventory_subtitle'),
                'accent' => '#7c3aed',
            ],
            default => [
                'key' => 'overview',
                'title' => $this->t('analytics_executive_overview'),
                'subtitle' => $this->t('analytics_overview_subtitle'),
                'accent' => '#2563eb',
            ],
        };
    }

    private function t(string $key, array $replace = []): string
    {
        return __('ui.' . $key, $replace);
    }

    private function accessTypeLabel(int $accessType): string
    {
        $key = self::ACCESS_TYPES[$accessType] ?? null;

        return $key ? $this->t($key) : $this->t('all_routes_label');
    }

    private function metricCard(string $label, float|int $value, string $kind, string $note): array
    {
        return compact('label', 'value', 'kind', 'note');
    }

    private function lineChart(string $title, array $labels, array $datasets): array
    {
        return ['title' => $title, 'type' => 'line', 'labels' => $labels, 'datasets' => $datasets];
    }

    private function barChart(string $title, array $labels, array $datasets): array
    {
        return ['title' => $title, 'type' => 'bar', 'labels' => $labels, 'datasets' => $datasets];
    }

    private function doughnutChart(string $title, array $segments): array
    {
        return [
            'title' => $title,
            'type' => 'doughnut',
            'labels' => collect($segments)->pluck('label')->values()->all(),
            'datasets' => [[
                'data' => collect($segments)->pluck('value')->map(fn ($value) => (float) $value)->values()->all(),
                'backgroundColor' => collect($segments)->pluck('color')->values()->all(),
            ]],
        ];
    }

    private function table(string $title, array $columns, array $rows): array
    {
        return compact('title', 'columns', 'rows');
    }

    private function distinctOptions(Collection $rows, string $idKey, string $labelKey): array
    {
        return $rows
            ->filter(fn (array $row) => !empty($row[$idKey]))
            ->unique($idKey)
            ->sortBy($idKey)
            ->map(fn (array $row) => [
                'id' => (int) $row[$idKey],
                'label' => $row[$labelKey],
            ])
            ->values()
            ->all();
    }

    private function companyOptions(User $user, Collection $routeRows): array
    {
        $options = collect($this->distinctOptions($routeRows, 'cmpycode', 'company_label'));

        if ((int) ($user->accesstypeid ?? 0) !== 1 || ! $this->userIsScoped($user) || ! Schema::hasTable('company')) {
            return $options->all();
        }

        $accessOptions = UserAccessCode::query()
            ->where('username', $user->username)
            ->whereNotNull('cmpycode')
            ->select('cmpycode')
            ->distinct()
            ->orderBy('cmpycode')
            ->get()
            ->map(function ($row) {
                $company = DB::table('company')
                    ->where('cmpycode', (int) $row->cmpycode)
                    ->select('cmpycode', 'name')
                    ->first();

                if (! $company) {
                    return null;
                }

                return [
                    'id' => (int) $company->cmpycode,
                    'label' => $this->label($company->cmpycode, $company->name),
                ];
            })
            ->filter();

        return $options
            ->concat($accessOptions)
            ->unique('id')
            ->sortBy('id')
            ->values()
            ->all();
    }

    private function userIsScoped(User $user): bool
    {
        return (int) ($user->accesstypeid ?? 0) >= 1
            && (int) ($user->accesstypeid ?? 0) <= 6
            && UserAccessCode::where('username', $user->username)->exists();
    }

    private function nullableInt(mixed $value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }

        return (int) $value;
    }

    private function isValidDate(mixed $value): bool
    {
        if (!is_string($value) || trim($value) === '') {
            return false;
        }

        try {
            Carbon::parse($value);
            return true;
        } catch (\Throwable) {
            return false;
        }
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

    private function qualifiedAlias(string $alias): string
    {
        return DB::getTablePrefix() . $alias;
    }

    private function label(mixed $id, mixed $name): string
    {
        return trim(((int) ($id ?? 0)) . ' - ' . ((string) ($name ?? '')));
    }

    private function ratioText(float|int $part, float|int $whole): string
    {
        if ((float) $whole <= 0) {
            return '0.0%';
        }

        return number_format(((float) $part / (float) $whole) * 100, 1) . '%';
    }
}
