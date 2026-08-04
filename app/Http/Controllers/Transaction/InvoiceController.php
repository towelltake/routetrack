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

class InvoiceController extends Controller
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
            return Inertia::render('transaction/invoice/Index', [
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

        $query = DB::table('invoiceheader as header')
            ->leftJoin('routemaster as route', 'route.routecode', '=', 'header.routecode')
            ->leftJoin('salesman as salesman', 'salesman.salesmancode', '=', 'header.salesmancode')
            ->leftJoin('customermaster as customer', 'customer.customercode', '=', 'header.customercode')
            ->selectRaw("
                {$headerAlias}.transactionkey,
                {$headerAlias}.routecode,
                COALESCE({$routeAlias}.routename, '') as routename,
                COALESCE({$routeAlias}.arbroutename, '') as arbroutename,
                COALESCE({$salesmanAlias}.salesmanname1, '') as salesmanname1,
                COALESCE({$salesmanAlias}.arbsalesmanname1, '') as arbsalesmanname1,
                COALESCE({$headerAlias}.documentnumber, '') as documentnumber,
                COALESCE({$headerAlias}.invoicenumber, '') as invoicenumber,
                {$headerAlias}.customercode,
                COALESCE({$customerAlias}.alternatecode, '') as alternatecode,
                COALESCE({$customerAlias}.customername, '') as customername,
                COALESCE({$customerAlias}.arbcustomername, '') as arbcustomername,
                {$this->transactionTimeExpression('header')} as transactiontime
            ")
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

        return Inertia::render('transaction/invoice/Index', [
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

        $header = $this->invoiceHeaderRow($transactionkey);
        abort_unless($header, 404);
        abort_unless(app(AccessScopeService::class)->allows($request->user(), 'route', $header->routecode ?? null), 403);
        $lines = $this->invoiceLineRows($header);

        return Inertia::render('transaction/invoice/Show', [
            'header' => $this->presentInvoiceHeader($header),
            'lines' => $lines,
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

        $header = $this->invoiceHeaderRow($transactionkey);
        abort_unless($header, 404);
        abort_unless(app(AccessScopeService::class)->allows($request->user(), 'route', $header->routecode ?? null), 403);

        return view('transaction.invoice.print', [
            'header' => $this->presentInvoiceHeader($header),
            'lines' => $this->invoiceLineRows($header),
            'company' => $this->companyHeaderForInvoice($header),
        ]);
    }

    private function hasHeaderTables(): bool
    {
        return Schema::hasTable('invoiceheader')
            && Schema::hasTable('routemaster')
            && Schema::hasTable('salesman')
            && Schema::hasTable('customermaster');
    }

    private function hasDetailTables(): bool
    {
        return Schema::hasTable('invoicedetail') && Schema::hasTable('itemmaster');
    }

    private function invoiceHeaderRow(int $transactionkey): ?object
    {
        $headerAlias = $this->qualifiedAlias('header');
        $routeAlias = $this->qualifiedAlias('route');
        $salesmanAlias = $this->qualifiedAlias('salesman');
        $customerAlias = $this->qualifiedAlias('customer');

        return DB::table('invoiceheader as header')
            ->leftJoin('routemaster as route', 'route.routecode', '=', 'header.routecode')
            ->leftJoin('salesman as salesman', 'salesman.salesmancode', '=', 'header.salesmancode')
            ->leftJoin('customermaster as customer', 'customer.customercode', '=', 'header.customercode')
            ->where('header.transactionkey', $transactionkey)
            ->selectRaw("
                {$headerAlias}.transactionkey,
                {$headerAlias}.routekey,
                {$headerAlias}.visitkey,
                {$headerAlias}.transactiondate,
                {$this->transactionTimeExpression('header')} as transactiontime,
                COALESCE({$headerAlias}.documentnumber, '') as documentnumber,
                COALESCE({$headerAlias}.invoicenumber, '') as invoicenumber,
                COALESCE({$headerAlias}.dsdnumber, '') as dsdnumber,
                COALESCE({$headerAlias}.ponumber, '') as ponumber,
                {$headerAlias}.routecode,
                COALESCE({$routeAlias}.routename, '') as routename,
                COALESCE({$routeAlias}.arbroutename, '') as arbroutename,
                {$headerAlias}.salesmancode,
                COALESCE({$salesmanAlias}.salesmanname1, '') as salesmanname1,
                COALESCE({$salesmanAlias}.arbsalesmanname1, '') as arbsalesmanname1,
                {$headerAlias}.customercode,
                COALESCE({$customerAlias}.alternatecode, '') as alternatecode,
                COALESCE({$customerAlias}.customername, '') as customername,
                COALESCE({$customerAlias}.arbcustomername, '') as arbcustomername,
                {$this->stringColumnExpression('customermaster', 'customeraddress1', 'customer.customeraddress1')} as customeraddress1,
                {$this->stringColumnExpression('customermaster', 'customeraddress2', 'customer.customeraddress2')} as customeraddress2,
                {$this->stringColumnExpression('customermaster', 'customeraddress3', 'customer.customeraddress3')} as customeraddress3,
                {$this->stringColumnExpression('customermaster', 'customerphone', 'customer.customerphone')} as customerphone,
                {$this->numericColumnExpression('invoiceheader', 'paymenttype', 'header.paymenttype')} as paymenttypecode,
                {$this->stringColumnExpression('invoiceheader', 'comments', 'header.comments')} as comments,
                {$this->numericColumnExpression('invoiceheader', 'status', 'header.status')} as status,
                {$this->numericColumnExpression('invoiceheader', 'voidflag', 'header.voidflag')} as voidflag,
                {$this->numericColumnExpression('invoiceheader', 'totalinvoiceamount', 'header.totalinvoiceamount')} as totalinvoiceamount,
                {$this->numericColumnExpression('invoiceheader', 'totalsalesamount', 'header.totalsalesamount')} as totalsalesamount,
                {$this->numericColumnExpression('invoiceheader', 'totalpromoamount', 'header.totalpromoamount')} as totalpromoamount,
                {$this->numericColumnExpression('invoiceheader', 'totaldiscountamount', 'header.totaldiscountamount')} as totaldiscountamount,
                {$this->numericColumnExpression('invoiceheader', 'totalvat', 'header.totalvat')} as totalvat,
                {$this->numericColumnExpression('invoiceheader', 'totalexcisetax', 'header.totalexcisetax')} as totalexcisetax
            ")
            ->first();
    }

    private function invoiceLineRows(object $header): array
    {
        if (!$this->hasDetailTables()) {
            return [];
        }

        $detailAlias = $this->qualifiedAlias('detail');
        $itemAlias = $this->qualifiedAlias('item');
        $useAlternateCode = $this->useAlternateCode();
        $transactionKey = (int) ($header->transactionkey ?? 0);
        $routeKey = $header->routekey ?? null;
        $visitKey = $header->visitkey ?? null;

        $query = DB::table('invoicedetail as detail')
            ->join('itemmaster as item', 'item.actualitemcode', '=', 'detail.itemcode')
            ->where('detail.transactionkey', $transactionKey)
            ->selectRaw("
                {$detailAlias}.primary_key,
                {$detailAlias}.itemcode,
                COALESCE({$itemAlias}.alternatecode, '') as alternatecode,
                {$this->itemDescriptionExpression('item')} as itemdescription,
                {$this->arabicItemDescriptionExpression('item')} as arbitemdescription,
                COALESCE({$this->qualifiedColumn('detail.upc')}, {$this->unitsPerCaseExpression('item')}) as upc,
                {$this->numericColumnExpression('invoicedetail', 'salescaseprice', 'detail.salescaseprice')} as salescaseprice,
                {$this->numericColumnExpression('invoicedetail', 'salesprice', 'detail.salesprice')} as salesprice,
                {$this->numericColumnExpression('invoicedetail', 'returncaseprice', 'detail.returncaseprice')} as returncaseprice,
                {$this->numericColumnExpression('invoicedetail', 'returnprice', 'detail.returnprice')} as returnprice,
                {$this->caseQuantityExpression('detail', 'item', 'salescases', 'salesqty')} as salescases,
                {$this->pieceQuantityExpression('detail', 'item', 'salespcs', 'salesqty')} as salespcs,
                {$this->caseQuantityExpression('detail', 'item', 'returncases', 'returnqty')} as returncases,
                {$this->pieceQuantityExpression('detail', 'item', 'returnpcs', 'returnqty')} as returnpcs,
                FLOOR(COALESCE({$this->qualifiedColumn('detail.damagedqty')}, 0) / GREATEST({$this->unitsPerCaseExpression('item')}, 1)) as damagedcases,
                MOD(COALESCE({$this->qualifiedColumn('detail.damagedqty')}, 0), GREATEST({$this->unitsPerCaseExpression('item')}, 1)) as damagedpcs,
                FLOOR(COALESCE({$this->qualifiedColumn('detail.manualfreeqty')}, 0) / GREATEST({$this->unitsPerCaseExpression('item')}, 1)) as freegoodcases,
                MOD(COALESCE({$this->qualifiedColumn('detail.manualfreeqty')}, 0), GREATEST({$this->unitsPerCaseExpression('item')}, 1)) as freegoodpcs,
                FLOOR(COALESCE({$this->qualifiedColumn('detail.freesampleqty')}, 0) / GREATEST({$this->unitsPerCaseExpression('item')}, 1)) as promotioncases,
                MOD(COALESCE({$this->qualifiedColumn('detail.freesampleqty')}, 0), GREATEST({$this->unitsPerCaseExpression('item')}, 1)) as promotionpcs,
                {$this->numericColumnExpression('invoicedetail', 'sales_amount', 'detail.sales_amount')} as sales_amount,
                {$this->numericColumnExpression('invoicedetail', 'promoamount', 'detail.promoamount')} as promoamount,
                {$this->taxSalesExpression('detail')} as taxsales,
                {$this->taxReturnExpression('detail')} as taxreturn,
                {$this->totalAmountExpression('detail', 'item')} as total_amount
            ")
            ->orderBy('detail.itemcode');

        if ($routeKey !== null && $routeKey !== '') {
            $query->where('detail.routekey', $routeKey);
        }

        if ($visitKey !== null && $visitKey !== '') {
            $query->where('detail.visitkey', $visitKey);
        }

        return $query
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
                'taxsales' => (float) ($row->taxsales ?? 0),
                'taxreturn' => (float) ($row->taxreturn ?? 0),
                'total_amount' => (float) ($row->total_amount ?? 0),
            ])
            ->values()
            ->all();
    }

    private function presentInvoiceHeader(object $header): array
    {
        return [
            'transactionkey' => (int) $header->transactionkey,
            'transactiondate' => $this->formatDate($header->transactiondate),
            'transactiontime' => $header->transactiontime,
            'documentnumber' => $this->identifier($header->documentnumber),
            'invoicenumber' => $this->identifier($header->invoicenumber),
            'dsdnumber' => $header->dsdnumber,
            'ponumber' => $header->ponumber,
            'documentvalid' => $this->documentValidityLabel($header->voidflag),
            'status' => $this->identifier($header->status),
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
            'customeraddress2' => $header->customeraddress2,
            'customeraddress3' => $header->customeraddress3,
            'customerphone' => $header->customerphone,
            'paymenttype' => $this->paymentTypeLabel($header->paymenttypecode),
            'comments' => $header->comments,
            'totalinvoiceamount' => (float) ($header->totalinvoiceamount ?? 0),
            'totalsalesamount' => (float) ($header->totalsalesamount ?? 0),
            'totalpromoamount' => (float) ($header->totalpromoamount ?? 0),
            'totaldiscountamount' => (float) ($header->totaldiscountamount ?? 0),
            'totalvat' => (float) ($header->totalvat ?? 0),
            'totalexcisetax' => (float) ($header->totalexcisetax ?? 0),
        ];
    }

    private function companyHeaderForInvoice(object $header): array
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
            default => Schema::hasColumn('invoiceheader', 'transactiontime')
                ? 'header.transactiontime'
                : 'header.transactiondate',
        };
    }

    private function qualifiedAlias(string $alias): string
    {
        return DB::getTablePrefix() . $alias;
    }

    private function qualifiedColumn(string $qualifiedColumn): string
    {
        if (!str_contains($qualifiedColumn, '.')) {
            return $qualifiedColumn;
        }

        [$alias, $column] = explode('.', $qualifiedColumn, 2);

        return $this->qualifiedAlias($alias) . '.' . $column;
    }

    private function transactionTimeExpression(string $alias): string
    {
        $qualifiedAlias = $this->qualifiedAlias($alias);

        if (Schema::hasColumn('invoiceheader', 'transactiontime')) {
            return 'COALESCE(' . $qualifiedAlias . '.transactiontime, "")';
        }

        return 'DATE_FORMAT(' . $qualifiedAlias . '.transactiondate, "%H:%i:%s")';
    }

    private function itemDescriptionExpression(string $alias): string
    {
        $qualifiedAlias = $this->qualifiedAlias($alias);
        $descriptionColumns = [];

        if (Schema::hasColumn('itemmaster', 'itemshortdescription')) {
            $descriptionColumns[] = $qualifiedAlias . '.itemshortdescription';
        }

        if (Schema::hasColumn('itemmaster', 'itemdescription')) {
            $descriptionColumns[] = $qualifiedAlias . '.itemdescription';
        }

        if (empty($descriptionColumns)) {
            return '""';
        }

        return 'COALESCE(' . implode(', ', $descriptionColumns) . ', "")';
    }

    private function arabicItemDescriptionExpression(string $alias): string
    {
        $qualifiedAlias = $this->qualifiedAlias($alias);
        $descriptionColumns = [];

        if (Schema::hasColumn('itemmaster', 'arbitemshortdescription')) {
            $descriptionColumns[] = $qualifiedAlias . '.arbitemshortdescription';
        }

        if (Schema::hasColumn('itemmaster', 'arbitemdescription')) {
            $descriptionColumns[] = $qualifiedAlias . '.arbitemdescription';
        }

        if (empty($descriptionColumns)) {
            return '""';
        }

        return 'COALESCE(' . implode(', ', $descriptionColumns) . ', "")';
    }

    private function unitsPerCaseExpression(string $alias): string
    {
        $qualifiedAlias = $this->qualifiedAlias($alias);

        return Schema::hasColumn('itemmaster', 'unitspercase')
            ? 'COALESCE(' . $qualifiedAlias . '.unitspercase, 1)'
            : '1';
    }

    private function caseQuantityExpression(string $detailAlias, string $itemAlias, string $preferredColumn, string $quantityColumn): string
    {
        $qualifiedDetailAlias = $this->qualifiedAlias($detailAlias);

        if (Schema::hasColumn('invoicedetail', $preferredColumn)) {
            return 'COALESCE(' . $qualifiedDetailAlias . '.' . $preferredColumn . ', FLOOR(COALESCE(' . $qualifiedDetailAlias . '.' . $quantityColumn . ', 0) / GREATEST(' . $this->unitsPerCaseExpression($itemAlias) . ', 1)))';
        }

        return 'FLOOR(COALESCE(' . $qualifiedDetailAlias . '.' . $quantityColumn . ', 0) / GREATEST(' . $this->unitsPerCaseExpression($itemAlias) . ', 1))';
    }

    private function pieceQuantityExpression(string $detailAlias, string $itemAlias, string $preferredColumn, string $quantityColumn): string
    {
        $qualifiedDetailAlias = $this->qualifiedAlias($detailAlias);

        if (Schema::hasColumn('invoicedetail', $preferredColumn)) {
            return 'COALESCE(' . $qualifiedDetailAlias . '.' . $preferredColumn . ', MOD(COALESCE(' . $qualifiedDetailAlias . '.' . $quantityColumn . ', 0), GREATEST(' . $this->unitsPerCaseExpression($itemAlias) . ', 1)))';
        }

        return 'MOD(COALESCE(' . $qualifiedDetailAlias . '.' . $quantityColumn . ', 0), GREATEST(' . $this->unitsPerCaseExpression($itemAlias) . ', 1))';
    }

    private function numericColumnExpression(string $table, string $column, string $qualifiedColumn): string
    {
        return Schema::hasColumn($table, $column)
            ? 'COALESCE(' . $this->qualifiedColumn($qualifiedColumn) . ', 0)'
            : '0';
    }

    private function stringColumnExpression(string $table, string $column, string $qualifiedColumn): string
    {
        return Schema::hasColumn($table, $column)
            ? 'COALESCE(' . $this->qualifiedColumn($qualifiedColumn) . ', "")'
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

    private function taxSalesExpression(string $detailAlias): string
    {
        $parts = [];

        foreach (['salesitemexcisetax', 'salesitemgsttax', 'fgitemexcisetax', 'fgitemgsttax', 'promoitemexcisetax', 'promoitemgsttax'] as $column) {
            $parts[] = $this->numericColumnExpression('invoicedetail', $column, $detailAlias . '.' . $column);
        }

        return '(' . implode(' + ', $parts) . ')';
    }

    private function taxReturnExpression(string $detailAlias): string
    {
        $parts = [];

        foreach (['returnitemexcisetax', 'returnitemgsttax', 'damageditemexcisetax', 'damageditemgsttax', 'buybackexcisetax', 'buybackgsttax'] as $column) {
            $parts[] = $this->numericColumnExpression('invoicedetail', $column, $detailAlias . '.' . $column);
        }

        return '(' . implode(' + ', $parts) . ')';
    }

    private function totalAmountExpression(string $detailAlias, string $itemAlias): string
    {
        $qualifiedDetailAlias = $this->qualifiedAlias($detailAlias);
        $units = 'GREATEST(' . $this->unitsPerCaseExpression($itemAlias) . ', 1)';
        $salesQty = 'COALESCE(' . $qualifiedDetailAlias . '.salesqty, 0)';
        $returnQty = 'COALESCE(' . $qualifiedDetailAlias . '.returnqty, 0)';
        $damagedQty = 'COALESCE(' . $qualifiedDetailAlias . '.damagedqty, 0)';
        $salesAmount = '(FLOOR(' . $salesQty . ' / ' . $units . ') * ' . $this->numericColumnExpression('invoicedetail', 'salescaseprice', $detailAlias . '.salescaseprice') . ') + (MOD(' . $salesQty . ', ' . $units . ') * ' . $this->numericColumnExpression('invoicedetail', 'salesprice', $detailAlias . '.salesprice') . ')';
        $returnAmount = '(FLOOR(' . $returnQty . ' / ' . $units . ') * ' . $this->numericColumnExpression('invoicedetail', 'returncaseprice', $detailAlias . '.returncaseprice') . ') + (MOD(' . $returnQty . ', ' . $units . ') * ' . $this->numericColumnExpression('invoicedetail', 'returnprice', $detailAlias . '.returnprice') . ')';
        $damagedAmount = '(FLOOR(' . $damagedQty . ' / ' . $units . ') * ' . $this->numericColumnExpression('invoicedetail', 'returncaseprice', $detailAlias . '.returncaseprice') . ') + (MOD(' . $damagedQty . ', ' . $units . ') * ' . $this->numericColumnExpression('invoicedetail', 'returnprice', $detailAlias . '.returnprice') . ')';

        return '((' . $salesAmount . ' + ' . $this->taxSalesExpression($detailAlias) . ') - (' . $returnAmount . ' + ' . $this->taxReturnExpression($detailAlias) . ') - (' . $damagedAmount . ') - ' . $this->numericColumnExpression('invoicedetail', 'promoamount', $detailAlias . '.promoamount') . ')';
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

    private function paymentTypeLabel(mixed $value): string
    {
        return match ((int) ($value ?? 0)) {
            1 => 'Cash',
            2 => 'TC',
            3 => 'GC',
            default => '',
        };
    }
}
