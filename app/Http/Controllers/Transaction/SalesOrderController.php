<?php

namespace App\Http\Controllers\Transaction;

use App\Http\Controllers\Controller;
use App\Services\AccessScopeService;
use Carbon\Carbon;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\View\View;
use Inertia\Inertia;
use Inertia\Response;

class SalesOrderController extends Controller
{
    public function index(Request $request): Response
    {
        $scope = app(AccessScopeService::class);
        $user = $request->user();
        $headerAlias = $this->qualifiedAlias('header');
        $routeAlias = $this->qualifiedAlias('route');
        $salesmanAlias = $this->qualifiedAlias('salesman');
        $customerAlias = $this->qualifiedAlias('customer');

        $allowedPerPage = [10, 25, 50, 100];
        $allowedSorts = ['routecode', 'routename', 'salesmanname1', 'documentnumber', 'invoicenumber', 'customercode', 'customername', 'transactiontime'];
        $perPage = (int) $request->input('per_page', 10);
        $sortBy = (string) $request->input('sort_by', 'transactiontime');
        $sortDir = $request->input('sort_dir', 'desc') === 'asc' ? 'asc' : 'desc';
        $selectedDate = $this->selectedDate($request)->toDateString();
        $selectedRoute = max(0, (int) $request->input('routecode', 0));
        $search = trim((string) $request->input('search', ''));

        if (!in_array($perPage, $allowedPerPage, true)) {
            $perPage = 10;
        }

        if (!in_array($sortBy, $allowedSorts, true)) {
            $sortBy = 'transactiontime';
        }

        if (!$this->hasHeaderTables()) {
            return Inertia::render('transaction/sales-order/Index', [
                'documents' => $this->emptyPaginator($request, $perPage),
                'routeOptions' => $this->routeOptions(),
                'filters' => [
                    'date' => $selectedDate,
                    'routecode' => $selectedRoute,
                    'search' => $search,
                    'per_page' => $perPage,
                    'sort_by' => $sortBy,
                    'sort_dir' => $sortDir,
                ],
            ]);
        }

        $query = DB::table('salesorderheader as header')
            ->leftJoin('routemaster as route', 'route.routecode', '=', 'header.routecode')
            ->leftJoin('salesman as salesman', 'salesman.salesmancode', '=', 'header.salesmancode')
            ->leftJoin('customermaster as customer', 'customer.customercode', '=', 'header.customercode')
            ->selectRaw('
                ' . $headerAlias . '.transactionkey,
                ' . $headerAlias . '.routecode,
                COALESCE(' . $routeAlias . '.routename, "") as routename,
                COALESCE(' . $routeAlias . '.arbroutename, "") as arbroutename,
                COALESCE(' . $salesmanAlias . '.salesmanname1, "") as salesmanname1,
                COALESCE(' . $salesmanAlias . '.arbsalesmanname1, "") as arbsalesmanname1,
                COALESCE(' . $headerAlias . '.documentnumber, "") as documentnumber,
                COALESCE(' . $headerAlias . '.invoicenumber, "") as invoicenumber,
                ' . $headerAlias . '.customercode,
                COALESCE(' . $customerAlias . '.alternatecode, "") as alternatecode,
                COALESCE(' . $customerAlias . '.customername, "") as customername,
                COALESCE(' . $customerAlias . '.arbcustomername, "") as arbcustomername,
                ' . $this->transactionTimeExpression($headerAlias) . ' as transactiontime
            ')
            ->whereDate('header.transactiondate', $selectedDate);

        $scope->scopeQuery($user, $query, 'route', 'header.routecode');

        if ($selectedRoute > 0) {
            $query->where('header.routecode', $selectedRoute);
        }

        if ($search !== '') {
            $query->where(function ($searchQuery) use ($search) {
                $like = '%' . $search . '%';

                $searchQuery
                    ->where('header.documentnumber', 'like', $like)
                    ->orWhere('header.invoicenumber', 'like', $like)
                    ->orWhere('header.routecode', 'like', $like)
                    ->orWhere('header.customercode', 'like', $like)
                    ->orWhere('header.dsdnumber', 'like', $like)
                    ->orWhere('header.ponumber', 'like', $like)
                    ->orWhere('route.routename', 'like', $like)
                    ->orWhere('route.arbroutename', 'like', $like)
                    ->orWhere('salesman.salesmanname1', 'like', $like)
                    ->orWhere('salesman.arbsalesmanname1', 'like', $like)
                    ->orWhere('customer.alternatecode', 'like', $like)
                    ->orWhere('customer.customername', 'like', $like)
                    ->orWhere('customer.arbcustomername', 'like', $like);
            });
        }

        $documents = $query
            ->orderBy($this->sortColumn($sortBy), $sortDir)
            ->orderBy('header.transactionkey', 'desc')
            ->paginate($perPage)
            ->withQueryString()
            ->through(fn ($row) => [
                'transactionkey' => (int) $row->transactionkey,
                'routecode' => (int) ($row->routecode ?? 0),
                'routename' => $row->routename,
                'arbroutename' => $row->arbroutename,
                'salesmanname1' => $row->salesmanname1,
                'arbsalesmanname1' => $row->arbsalesmanname1,
                'documentnumber' => $this->identifier($row->documentnumber),
                'invoicenumber' => $this->identifier($row->invoicenumber),
                'customercode' => (int) ($row->customercode ?? 0),
                'alternatecode' => $row->alternatecode,
                'customername' => $row->customername,
                'arbcustomername' => $row->arbcustomername,
                'transactiontime' => $row->transactiontime,
            ]);

        return Inertia::render('transaction/sales-order/Index', [
            'documents' => $documents,
            'routeOptions' => $this->routeOptions(),
            'filters' => [
                'date' => $selectedDate,
                'routecode' => $selectedRoute,
                'search' => $search,
                'per_page' => $perPage,
                'sort_by' => $sortBy,
                'sort_dir' => $sortDir,
            ],
        ]);
    }

    public function show(Request $request, int $transactionkey): Response
    {
        abort_unless($this->hasHeaderTables(), 404);
        $header = $this->salesOrderHeaderRow($transactionkey);
        abort_unless($header, 404);
        abort_unless(app(AccessScopeService::class)->allows($request->user(), 'route', $header->routecode ?? null), 403);

        return Inertia::render('transaction/sales-order/Show', [
            'header' => $this->presentSalesOrderHeader($header),
            'lines' => $this->salesOrderLineRows($transactionkey),
            'filters' => [
                'date' => (string) $request->input('date', ''),
                'routecode' => max(0, (int) $request->input('routecode', 0)),
                'search' => (string) $request->input('search', ''),
                'page' => max(1, (int) $request->input('page', 1)),
                'per_page' => max(10, (int) $request->input('per_page', 10)),
                'sort_by' => (string) $request->input('sort_by', 'transactiontime'),
                'sort_dir' => (string) $request->input('sort_dir', 'desc'),
            ],
        ]);
    }

    public function print(Request $request, int $transactionkey): View
    {
        abort_unless($this->hasHeaderTables(), 404);

        $header = $this->salesOrderHeaderRow($transactionkey);
        abort_unless($header, 404);
        abort_unless(app(AccessScopeService::class)->allows($request->user(), 'route', $header->routecode ?? null), 403);

        return view('transaction.sales-order.print', [
            'header' => $this->presentSalesOrderHeader($header),
            'lines' => $this->salesOrderLineRows($transactionkey),
            'company' => $this->companyHeaderForSalesOrder($header),
        ]);
    }

    private function hasHeaderTables(): bool
    {
        return Schema::hasTable('salesorderheader')
            && Schema::hasTable('routemaster')
            && Schema::hasTable('salesman')
            && Schema::hasTable('customermaster');
    }

    private function hasDetailTables(): bool
    {
        return Schema::hasTable('salesorderdetail') && Schema::hasTable('itemmaster');
    }

    private function salesOrderHeaderRow(int $transactionkey): ?object
    {
        $headerAlias = $this->qualifiedAlias('header');
        $routeAlias = $this->qualifiedAlias('route');
        $deliveryRouteAlias = $this->qualifiedAlias('delivery_route');
        $salesmanAlias = $this->qualifiedAlias('salesman');
        $customerAlias = $this->qualifiedAlias('customer');
        $startDayAlias = $this->qualifiedAlias('start_day');

        $headerQuery = DB::table('salesorderheader as header')
            ->leftJoin('routemaster as route', 'route.routecode', '=', 'header.routecode')
            ->leftJoin('routemaster as delivery_route', 'delivery_route.routecode', '=', 'header.orderdeliveryroutecode')
            ->leftJoin('salesman as salesman', 'salesman.salesmancode', '=', 'header.salesmancode')
            ->leftJoin('customermaster as customer', 'customer.customercode', '=', 'header.customercode')
            ->where('header.transactionkey', $transactionkey);

        if (Schema::hasTable('startendday')) {
            $headerQuery->leftJoin('startendday as start_day', 'start_day.routekey', '=', 'header.routekey');
        }

        return $headerQuery->selectRaw('
                ' . $headerAlias . '.transactionkey,
                ' . $headerAlias . '.routekey,
                ' . $headerAlias . '.transactiondate,
                ' . $this->transactionTimeExpression($headerAlias) . ' as transactiontime,
                COALESCE(' . $headerAlias . '.documentnumber, "") as documentnumber,
                COALESCE(' . $headerAlias . '.invoicenumber, "") as invoicenumber,
                COALESCE(' . $headerAlias . '.dsdnumber, "") as dsdnumber,
                COALESCE(' . $headerAlias . '.ponumber, "") as ponumber,
                ' . $headerAlias . '.routecode,
                COALESCE(' . $routeAlias . '.routename, "") as routename,
                COALESCE(' . $routeAlias . '.arbroutename, "") as arbroutename,
                ' . $headerAlias . '.salesmancode,
                COALESCE(' . $salesmanAlias . '.salesmanname1, "") as salesmanname1,
                COALESCE(' . $salesmanAlias . '.arbsalesmanname1, "") as arbsalesmanname1,
                ' . $headerAlias . '.customercode,
                COALESCE(' . $customerAlias . '.alternatecode, "") as alternatecode,
                COALESCE(' . $customerAlias . '.customername, "") as customername,
                COALESCE(' . $customerAlias . '.arbcustomername, "") as arbcustomername,
                ' . $this->stringColumnExpression('customermaster', 'customeraddress1', $customerAlias . '.customeraddress1') . ' as customeraddress1,
                ' . $this->numericColumnExpression('customermaster', 'invoicepaymentterms', $customerAlias . '.invoicepaymentterms') . ' as invoicepaymentterms,
                ' . $this->dateColumnExpression('startendday', 'routestartdate', $startDayAlias . '.routestartdate') . ' as routestartdate,
                COALESCE(' . $headerAlias . '.orderdeliveryroutecode, 0) as orderdeliveryroutecode,
                COALESCE(' . $deliveryRouteAlias . '.routename, "") as deliveryroutename,
                COALESCE(' . $deliveryRouteAlias . '.arbroutename, "") as arbdeliveryroutename,
                ' . $headerAlias . '.orderdeliverydate,
                ' . $this->stringColumnExpression('salesorderheader', 'comments', $headerAlias . '.comments') . ' as comments,
                ' . $this->stringColumnExpression('salesorderheader', 'comments2', $headerAlias . '.comments2') . ' as comments2,
                ' . $this->numericColumnExpression('salesorderheader', 'status', $headerAlias . '.status') . ' as status,
                ' . $this->numericColumnExpression('salesorderheader', 'voidflag', $headerAlias . '.voidflag') . ' as voidflag,
                ' . $this->numericColumnExpression('salesorderheader', 'totalinvoiceamount', $headerAlias . '.totalinvoiceamount') . ' as totalinvoiceamount,
                ' . $this->numericColumnExpression('salesorderheader', 'totalsalesamount', $headerAlias . '.totalsalesamount') . ' as totalsalesamount,
                ' . $this->numericColumnExpression('salesorderheader', 'totalreturnamount', $headerAlias . '.totalreturnamount') . ' as totalreturnamount,
                ' . $this->numericColumnExpression('salesorderheader', 'totaldamagedamount', $headerAlias . '.totaldamagedamount') . ' as totaldamagedamount,
                ' . $this->numericColumnExpression('salesorderheader', 'totalfreesampleamount', $headerAlias . '.totalfreesampleamount') . ' as totalfreesampleamount,
                ' . $this->numericColumnExpression('salesorderheader', 'totalpromoamount', $headerAlias . '.totalpromoamount') . ' as totalpromoamount,
                ' . $this->numericColumnExpression('salesorderheader', 'lineitemdiscount', $headerAlias . '.lineitemdiscount') . ' as lineitemdiscount,
                ' . $this->numericColumnExpression('salesorderheader', 'totallineitemtax', $headerAlias . '.totallineitemtax') . ' as totallineitemtax,
                ' . $this->numericColumnExpression('salesorderheader', 'totalvat', $headerAlias . '.totalvat') . ' as totalvat,
                ' . $this->numericColumnExpression('salesorderheader', 'totalexcisetax', $headerAlias . '.totalexcisetax') . ' as totalexcisetax
            ')
            ->first();
    }

    private function salesOrderLineRows(int $transactionkey): array
    {
        if (!$this->hasDetailTables()) {
            return [];
        }

        $detailAlias = $this->qualifiedAlias('detail');
        $itemAlias = $this->qualifiedAlias('item');
        $useAlternateCode = $this->useAlternateCode();

        return DB::table('salesorderdetail as detail')
            ->join('itemmaster as item', 'item.actualitemcode', '=', 'detail.itemcode')
            ->where('detail.transactionkey', $transactionkey)
            ->selectRaw('
                ' . $detailAlias . '.primary_key,
                ' . $detailAlias . '.itemcode,
                COALESCE(' . $itemAlias . '.alternatecode, "") as alternatecode,
                ' . $this->itemDescriptionExpression($itemAlias) . ' as itemdescription,
                ' . $this->arabicItemDescriptionExpression($itemAlias) . ' as arbitemdescription,
                COALESCE(' . $detailAlias . '.upc, ' . $this->unitsPerCaseExpression($itemAlias) . ') as upc,
                COALESCE(' . $detailAlias . '.salescaseprice, 0) as salescaseprice,
                COALESCE(' . $detailAlias . '.salesprice, 0) as salesprice,
                ' . $this->returnCasePriceExpression($detailAlias) . ' as returncaseprice,
                ' . $this->returnUnitPriceExpression($detailAlias) . ' as returnprice,
                FLOOR(COALESCE(' . $detailAlias . '.salesqty, 0) / GREATEST(' . $this->unitsPerCaseExpression($itemAlias) . ', 1)) as salescases,
                MOD(COALESCE(' . $detailAlias . '.salesqty, 0), GREATEST(' . $this->unitsPerCaseExpression($itemAlias) . ', 1)) as salespcs,
                FLOOR(COALESCE(' . $detailAlias . '.returnqty, 0) / GREATEST(' . $this->unitsPerCaseExpression($itemAlias) . ', 1)) as returncases,
                MOD(COALESCE(' . $detailAlias . '.returnqty, 0), GREATEST(' . $this->unitsPerCaseExpression($itemAlias) . ', 1)) as returnpcs,
                FLOOR(COALESCE(' . $detailAlias . '.damagedqty, 0) / GREATEST(' . $this->unitsPerCaseExpression($itemAlias) . ', 1)) as damagedcases,
                MOD(COALESCE(' . $detailAlias . '.damagedqty, 0), GREATEST(' . $this->unitsPerCaseExpression($itemAlias) . ', 1)) as damagedpcs,
                FLOOR(COALESCE(' . $detailAlias . '.manualfreeqty, 0) / GREATEST(' . $this->unitsPerCaseExpression($itemAlias) . ', 1)) as freegoodcases,
                MOD(COALESCE(' . $detailAlias . '.manualfreeqty, 0), GREATEST(' . $this->unitsPerCaseExpression($itemAlias) . ', 1)) as freegoodpcs,
                FLOOR(COALESCE(' . $detailAlias . '.freesampleqty, 0) / GREATEST(' . $this->unitsPerCaseExpression($itemAlias) . ', 1)) as promotioncases,
                MOD(COALESCE(' . $detailAlias . '.freesampleqty, 0), GREATEST(' . $this->unitsPerCaseExpression($itemAlias) . ', 1)) as promotionpcs,
                COALESCE(' . $detailAlias . '.sales_amount, 0) as sales_amount,
                COALESCE(' . $detailAlias . '.promoamount, 0) as promoamount,
                (
                    COALESCE(' . $detailAlias . '.salesorderexcisetax, 0) +
                    COALESCE(' . $detailAlias . '.salesordervat, 0) +
                    COALESCE(' . $detailAlias . '.fgexcisetax, 0) +
                    COALESCE(' . $detailAlias . '.fgvat, 0) +
                    COALESCE(' . $detailAlias . '.promoexcisetax, 0) +
                    COALESCE(' . $detailAlias . '.promovat, 0)
                ) as taxorder,
                (
                    COALESCE(' . $detailAlias . '.returnexcisetax, 0) +
                    COALESCE(' . $detailAlias . '.returnvat, 0) +
                    COALESCE(' . $detailAlias . '.damagedexcisetax, 0) +
                    COALESCE(' . $detailAlias . '.damagedvat, 0)
                ) as taxreturn,
                (
                    (
                        (FLOOR(COALESCE(' . $detailAlias . '.salesqty, 0) / GREATEST(' . $this->unitsPerCaseExpression($itemAlias) . ', 1)) * COALESCE(' . $detailAlias . '.salescaseprice, 0)) +
                        (MOD(COALESCE(' . $detailAlias . '.salesqty, 0), GREATEST(' . $this->unitsPerCaseExpression($itemAlias) . ', 1)) * COALESCE(' . $detailAlias . '.salesprice, 0)) +
                        COALESCE(' . $detailAlias . '.salesorderexcisetax, 0) +
                        COALESCE(' . $detailAlias . '.salesordervat, 0) +
                        COALESCE(' . $detailAlias . '.fgexcisetax, 0) +
                        COALESCE(' . $detailAlias . '.fgvat, 0) +
                        COALESCE(' . $detailAlias . '.promoexcisetax, 0) +
                        COALESCE(' . $detailAlias . '.promovat, 0)
                    ) -
                    (
                        (FLOOR(COALESCE(' . $detailAlias . '.returnqty, 0) / GREATEST(' . $this->unitsPerCaseExpression($itemAlias) . ', 1)) * ' . $this->returnCasePriceExpression($detailAlias) . ') +
                        (MOD(COALESCE(' . $detailAlias . '.returnqty, 0), GREATEST(' . $this->unitsPerCaseExpression($itemAlias) . ', 1)) * ' . $this->returnUnitPriceExpression($detailAlias) . ') +
                        COALESCE(' . $detailAlias . '.returnexcisetax, 0) +
                        COALESCE(' . $detailAlias . '.returnvat, 0)
                    ) -
                    (
                        (FLOOR(COALESCE(' . $detailAlias . '.damagedqty, 0) / GREATEST(' . $this->unitsPerCaseExpression($itemAlias) . ', 1)) * COALESCE(' . $detailAlias . '.returncaseprice, 0)) +
                        (MOD(COALESCE(' . $detailAlias . '.damagedqty, 0), GREATEST(' . $this->unitsPerCaseExpression($itemAlias) . ', 1)) * COALESCE(' . $detailAlias . '.returnprice, 0)) +
                        COALESCE(' . $detailAlias . '.damagedexcisetax, 0) +
                        COALESCE(' . $detailAlias . '.damagedvat, 0)
                    ) -
                    COALESCE(' . $detailAlias . '.promoamount, 0)
                ) as total_amount
            ')
            ->orderBy('detail.itemcode')
            ->get()
            ->map(fn ($row) => [
                'primary_key' => (int) $row->primary_key,
                'itemcode' => (int) $row->itemcode,
                'display_code' => $useAlternateCode && filled($row->alternatecode)
                    ? (string) $row->alternatecode
                    : (string) $row->itemcode,
                'description' => $row->itemdescription,
                'arbdescription' => $row->arbitemdescription,
                'upc' => max(1, (int) ($row->upc ?? 1)),
                'salescaseprice' => (float) ($row->salescaseprice ?? 0),
                'salesprice' => (float) ($row->salesprice ?? 0),
                'returncaseprice' => (float) ($row->returncaseprice ?? 0),
                'returnprice' => (float) ($row->returnprice ?? 0),
                'salescases' => (int) ($row->salescases ?? 0),
                'salespcs' => (int) ($row->salespcs ?? 0),
                'returncases' => (int) ($row->returncases ?? 0),
                'returnpcs' => (int) ($row->returnpcs ?? 0),
                'damagedcases' => (int) ($row->damagedcases ?? 0),
                'damagedpcs' => (int) ($row->damagedpcs ?? 0),
                'freegoodcases' => (int) ($row->freegoodcases ?? 0),
                'freegoodpcs' => (int) ($row->freegoodpcs ?? 0),
                'promotioncases' => (int) ($row->promotioncases ?? 0),
                'promotionpcs' => (int) ($row->promotionpcs ?? 0),
                'sales_amount' => (float) ($row->sales_amount ?? 0),
                'promoamount' => (float) ($row->promoamount ?? 0),
                'taxorder' => (float) ($row->taxorder ?? 0),
                'taxreturn' => (float) ($row->taxreturn ?? 0),
                'total_amount' => (float) ($row->total_amount ?? 0),
            ])
            ->values()
            ->all();
    }

    private function presentSalesOrderHeader(object $header): array
    {
        return [
            'transactionkey' => (int) $header->transactionkey,
            'transactiondate' => $this->formatDate($header->transactiondate),
            'transactiontime' => $header->transactiontime,
            'documentnumber' => $this->identifier($header->documentnumber),
            'invoicenumber' => $this->identifier($header->invoicenumber),
            'dsdnumber' => $header->dsdnumber,
            'ponumber' => $header->ponumber,
            'routecode' => (int) ($header->routecode ?? 0),
            'routename' => $header->routename,
            'arbroutename' => $header->arbroutename,
            'salesmancode' => (int) ($header->salesmancode ?? 0),
            'salesmanname1' => $header->salesmanname1,
            'arbsalesmanname1' => $header->arbsalesmanname1,
            'customercode' => (int) ($header->customercode ?? 0),
            'alternatecode' => $header->alternatecode,
            'customername' => $header->customername,
            'arbcustomername' => $header->arbcustomername,
            'customeraddress1' => $header->customeraddress1,
            'routestartdate' => $this->formatDate($header->routestartdate),
            'paymentterm' => $this->paymentTermLabel($header->invoicepaymentterms),
            'orderdeliveryroutecode' => (int) ($header->orderdeliveryroutecode ?? 0),
            'deliveryroutename' => $header->deliveryroutename,
            'arbdeliveryroutename' => $header->arbdeliveryroutename,
            'orderdeliverydate' => $this->formatDate($header->orderdeliverydate),
            'comments' => $header->comments,
            'comments2' => $header->comments2,
            'status' => $header->status,
            'documentvalid' => $this->documentValidityLabel($header->voidflag),
            'totalinvoiceamount' => (float) ($header->totalinvoiceamount ?? 0),
            'totalsalesamount' => (float) ($header->totalsalesamount ?? 0),
            'totalreturnamount' => (float) ($header->totalreturnamount ?? 0),
            'totaldamagedamount' => (float) ($header->totaldamagedamount ?? 0),
            'totalfreesampleamount' => (float) ($header->totalfreesampleamount ?? 0),
            'totalpromoamount' => (float) ($header->totalpromoamount ?? 0),
            'lineitemdiscount' => (float) ($header->lineitemdiscount ?? 0),
            'totallineitemtax' => (float) ($header->totallineitemtax ?? 0),
            'totalvat' => (float) ($header->totalvat ?? 0),
            'totalexcisetax' => (float) ($header->totalexcisetax ?? 0),
            'orderdiscount' => max(0, (float) ($header->totalpromoamount ?? 0) - (float) ($header->lineitemdiscount ?? 0)),
            'signaturedata' => $this->signatureDataForOrder(
                (int) ($header->transactionkey ?? 0),
                (int) ($header->routekey ?? 0),
                (int) ($header->customercode ?? 0)
            ),
        ];
    }

    private function companyHeaderForSalesOrder(object $header): array
    {
        $company = null;

        if (
            Schema::hasTable('routemaster')
            && Schema::hasTable('subareamaster')
            && Schema::hasTable('areamaster')
            && Schema::hasTable('depotmaster')
            && Schema::hasTable('company')
        ) {
            $company = DB::table('routemaster as route')
                ->join('subareamaster as subarea', 'subarea.subareacode', '=', 'route.subareacode')
                ->join('areamaster as area', 'area.areacode', '=', 'subarea.areacode')
                ->join('depotmaster as depot', 'depot.depotcode', '=', 'area.depotcode')
                ->join('company as company', 'company.cmpycode', '=', 'depot.cmpycode')
                ->where('route.routecode', $header->routecode ?? 0)
                ->select([
                    'company.cmpycode',
                    'company.name',
                    'company.arbcompanyname',
                    'company.address',
                    'company.telephone',
                    'company.fax',
                ])
                ->first();
        }

        if (!$company && Schema::hasTable('company')) {
            $company = DB::table('company')
                ->select(['cmpycode', 'name', 'arbcompanyname', 'address', 'telephone', 'fax'])
                ->orderBy('cmpycode')
                ->first();
        }

        return [
            'cmpycode' => (int) ($company->cmpycode ?? 0),
            'name' => (string) ($company->name ?? ''),
            'arbcompanyname' => (string) ($company->arbcompanyname ?? ''),
            'address' => (string) ($company->address ?? ''),
            'telephone' => (string) ($company->telephone ?? ''),
            'fax' => (string) ($company->fax ?? ''),
        ];
    }

    private function selectedDate(Request $request): Carbon
    {
        $date = (string) $request->input('date', '');

        try {
            return $date !== '' ? Carbon::parse($date) : now();
        } catch (\Throwable) {
            return now();
        }
    }

    private function routeOptions(): array
    {
        if (!Schema::hasTable('routemaster')) {
            return [];
        }

        $query = DB::table('routemaster')
            ->select(['routecode', 'routename'])
            ->orderBy('routecode');

        if (Schema::hasColumn('routemaster', 'activestatus')) {
            $query->where('activestatus', 1);
        }

        app(AccessScopeService::class)->scopeQuery(request()->user(), $query, 'route', 'routecode');

        return $query->get()->map(fn ($route) => [
            'id' => (int) $route->routecode,
            'label' => trim($route->routecode . ' - ' . ($route->routename ?? '')),
        ])->values()->all();
    }

    private function emptyPaginator(Request $request, int $perPage): LengthAwarePaginator
    {
        return new \Illuminate\Pagination\LengthAwarePaginator(
            [],
            0,
            $perPage,
            max(1, (int) $request->input('page', 1)),
            [
                'path' => $request->url(),
                'query' => $request->query(),
            ]
        );
    }

    private function sortColumn(string $sortBy): string
    {
        return match ($sortBy) {
            'routecode' => 'header.routecode',
            'routename' => 'route.routename',
            'salesmanname1' => 'salesman.salesmanname1',
            'documentnumber' => 'header.documentnumber',
            'invoicenumber' => 'header.invoicenumber',
            'customercode' => 'header.customercode',
            'customername' => 'customer.customername',
            default => Schema::hasColumn('salesorderheader', 'transactiontime')
                ? 'header.transactiontime'
                : 'header.transactiondate',
        };
    }

    private function qualifiedAlias(string $alias): string
    {
        return DB::getTablePrefix() . $alias;
    }

    private function transactionTimeExpression(string $alias): string
    {
        if (Schema::hasColumn('salesorderheader', 'transactiontime')) {
            return 'COALESCE(' . $alias . '.transactiontime, "")';
        }

        return 'DATE_FORMAT(' . $alias . '.transactiondate, "%H:%i:%s")';
    }

    private function itemDescriptionExpression(string $alias): string
    {
        $descriptionColumns = [];

        if (Schema::hasColumn('itemmaster', 'itemshortdescription')) {
            $descriptionColumns[] = $alias . '.itemshortdescription';
        }

        if (Schema::hasColumn('itemmaster', 'itemdescription')) {
            $descriptionColumns[] = $alias . '.itemdescription';
        }

        if (empty($descriptionColumns)) {
            return '""';
        }

        return 'COALESCE(' . implode(', ', $descriptionColumns) . ', "")';
    }

    private function arabicItemDescriptionExpression(string $alias): string
    {
        $descriptionColumns = [];

        if (Schema::hasColumn('itemmaster', 'arbitemshortdescription')) {
            $descriptionColumns[] = $alias . '.arbitemshortdescription';
        }

        if (Schema::hasColumn('itemmaster', 'arbitemdescription')) {
            $descriptionColumns[] = $alias . '.arbitemdescription';
        }

        if (empty($descriptionColumns)) {
            return '""';
        }

        return 'COALESCE(' . implode(', ', $descriptionColumns) . ', "")';
    }

    private function unitsPerCaseExpression(string $alias): string
    {
        return Schema::hasColumn('itemmaster', 'unitspercase')
            ? 'COALESCE(' . $alias . '.unitspercase, 1)'
            : '1';
    }

    private function returnCasePriceExpression(string $detailAlias): string
    {
        if (Schema::hasColumn('salesorderdetail', 'goodreturncaseprice')) {
            return 'COALESCE(' . $detailAlias . '.goodreturncaseprice, COALESCE(' . $detailAlias . '.returncaseprice, 0))';
        }

        return 'COALESCE(' . $detailAlias . '.returncaseprice, 0)';
    }

    private function returnUnitPriceExpression(string $detailAlias): string
    {
        if (Schema::hasColumn('salesorderdetail', 'goodreturnprice')) {
            return 'COALESCE(' . $detailAlias . '.goodreturnprice, COALESCE(' . $detailAlias . '.returnprice, 0))';
        }

        return 'COALESCE(' . $detailAlias . '.returnprice, 0)';
    }


    private function numericColumnExpression(string $table, string $column, string $qualifiedColumn): string
    {
        return Schema::hasColumn($table, $column)
            ? 'COALESCE(' . $qualifiedColumn . ', 0)'
            : '0';
    }

    private function dateColumnExpression(string $table, string $column, string $qualifiedColumn): string
    {
        return Schema::hasColumn($table, $column)
            ? $qualifiedColumn
            : 'NULL';
    }

    private function stringColumnExpression(string $table, string $column, string $qualifiedColumn): string
    {
        return Schema::hasColumn($table, $column)
            ? 'COALESCE(' . $qualifiedColumn . ', "")'
            : '""';
    }

    private function useAlternateCode(): bool
    {
        if (!Schema::hasTable('controlpanel')) {
            return false;
        }

        return (int) DB::table('controlpanel')
            ->where('flagname', 'Use Alternate Code')
            ->value('status') === 1;
    }

    private function formatDate(mixed $value): string
    {
        if (!$value) {
            return '';
        }

        try {
            return Carbon::parse($value)->format('d-m-Y');
        } catch (\Throwable) {
            return (string) $value;
        }
    }

    private function identifier(mixed $value): string
    {
        $string = trim((string) ($value ?? ''));

        if ($string === '') {
            return '';
        }

        if (preg_match('/^\d+\.0+$/', $string) === 1) {
            return strstr($string, '.', true) ?: $string;
        }

        return $string;
    }

    private function documentValidityLabel(mixed $value): string
    {
        if ($value === null || $value === '') {
            return '';
        }

        if (is_numeric($value)) {
            return (int) $value === 1 ? 'Void' : 'Valid';
        }

        return (string) $value;
    }

    private function paymentTermLabel(mixed $value): string
    {
        return match ((int) ($value ?? 0)) {
            1 => 'Cash',
            2 => 'Credit',
            3 => 'TC',
            4 => 'PDC',
            default => '',
        };
    }

    private function signatureDataForOrder(int $transactionKey, int $routeCode, int $customerCode): string
    {
        $value = null;

        if (Schema::hasTable('ordersigcapturedata')) {
            $query = DB::table('ordersigcapturedata')->where('transactionkey', $transactionKey);

            if ($customerCode > 0 && Schema::hasColumn('ordersigcapturedata', 'customercode')) {
                $query->where('customercode', $customerCode);
            }

            $value = $query->value('signaturedata');
        }

        if (($value === null || $value === '') && Schema::hasTable('sigcapturedata')) {
            $query = DB::table('sigcapturedata')->where('transactionkey', $transactionKey);

            if (Schema::hasColumn('sigcapturedata', 'transaction_type')) {
                $query->where('transaction_type', 4);
            }

            if ($customerCode > 0 && Schema::hasColumn('sigcapturedata', 'customercode')) {
                $query->where('customercode', $customerCode);
            }

            if ($routeCode > 0 && Schema::hasColumn('sigcapturedata', 'routekey')) {
                $query->where('routekey', $routeCode);
            }

            $value = $query->value('signaturedata');
        }

        return $this->normalizeSignatureData($value);
    }

    private function normalizeSignatureData(mixed $value): string
    {
        if ($value === null) {
            return '';
        }

        $string = is_resource($value) ? stream_get_contents($value) : (string) $value;
        $string = trim($string);

        if ($string === '') {
            return '';
        }

        if (str_starts_with($string, 'data:image/')) {
            return $string;
        }

        if (preg_match('/^[A-Za-z0-9+\/=]+$/', $string) === 1) {
            return 'data:image/png;base64,' . $string;
        }

        return 'data:image/png;base64,' . base64_encode($string);
    }
}
