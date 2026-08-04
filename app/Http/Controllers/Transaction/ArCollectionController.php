<?php

namespace App\Http\Controllers\Transaction;

use App\Http\Controllers\Controller;
use App\Services\AccessScopeService;
use Carbon\Carbon;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Inertia\Inertia;
use Inertia\Response;

class ArCollectionController extends Controller
{
    public function index(Request $request): Response
    {
        $scope = app(AccessScopeService::class);
        $user = $request->user();
        $headerAlias = $this->prefixedAlias('header');
        $routeAlias = $this->prefixedAlias('route');
        $salesmanAlias = $this->prefixedAlias('salesman');
        $customerAlias = $this->prefixedAlias('customer');

        $allowedPerPage = [10, 25, 50, 100];
        $allowedSorts = ['customercode', 'customername', 'invoicenumber', 'routecode', 'routename', 'salesmanname1', 'amountpaid'];
        $perPage = (int) $request->input('per_page', 10);
        $sortBy = (string) $request->input('sort_by', 'invoicenumber');
        $sortDir = $request->input('sort_dir', 'desc') === 'asc' ? 'asc' : 'desc';
        $selectedDate = $this->selectedDate($request)->toDateString();
        $selectedRoute = max(0, (int) $request->input('routecode', 0));
        $search = trim((string) $request->input('search', ''));

        if (!in_array($perPage, $allowedPerPage, true)) {
            $perPage = 10;
        }

        if (!in_array($sortBy, $allowedSorts, true)) {
            $sortBy = 'invoicenumber';
        }

        if (!$this->hasOverviewTables()) {
            return Inertia::render('transaction/ar-collection/Index', [
                'documents' => $this->emptyPaginator($request, $perPage),
                'routeOptions' => $this->routeOptions($user),
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

        $query = DB::table('arheader as header')
            ->leftJoin('routemaster as route', 'route.routecode', '=', 'header.routecode')
            ->leftJoin('salesman as salesman', 'salesman.salesmancode', '=', 'header.salesmancode')
            ->leftJoin('customermaster as customer', 'customer.customercode', '=', 'header.customercode')
            ->selectRaw("
                {$headerAlias}.transactionkey,
                {$headerAlias}.customercode,
                COALESCE({$customerAlias}.alternatecode, '') as alternatecode,
                COALESCE({$customerAlias}.customername, '') as customername,
                COALESCE({$customerAlias}.arbcustomername, '') as arbcustomername,
                COALESCE({$headerAlias}.invoicenumber, '') as invoicenumber,
                {$headerAlias}.routecode,
                COALESCE({$routeAlias}.routename, '') as routename,
                COALESCE({$routeAlias}.arbroutename, '') as arbroutename,
                {$headerAlias}.salesmancode,
                COALESCE({$salesmanAlias}.salesmanname1, '') as salesmanname1,
                COALESCE({$salesmanAlias}.arbsalesmanname1, '') as arbsalesmanname1,
                COALESCE({$headerAlias}.amountpaid, 0) as amountpaid
            ")
            ->whereDate('header.transactiondate', $selectedDate)
            ->where(function ($query) {
                $query->whereNull('header.advancepaymentflag')
                    ->orWhere('header.advancepaymentflag', '!=', 1);
            });

        $scope->scopeQuery($user, $query, 'route', 'header.routecode');

        if ($selectedRoute > 0) {
            $query->where('header.routecode', $selectedRoute);
        }

        if ($search !== '') {
            $query->where(function ($searchQuery) use ($search) {
                $like = '%' . $search . '%';

                $searchQuery
                    ->where('header.customercode', 'like', $like)
                    ->orWhere('customer.alternatecode', 'like', $like)
                    ->orWhere('customer.customername', 'like', $like)
                    ->orWhere('customer.arbcustomername', 'like', $like)
                    ->orWhere('header.invoicenumber', 'like', $like)
                    ->orWhere('header.routecode', 'like', $like)
                    ->orWhere('route.routename', 'like', $like)
                    ->orWhere('route.arbroutename', 'like', $like)
                    ->orWhere('salesman.salesmanname1', 'like', $like)
                    ->orWhere('salesman.arbsalesmanname1', 'like', $like);
            });
        }

        $documents = $query
            ->orderBy($this->sortColumn($sortBy), $sortDir)
            ->orderBy('header.transactionkey', 'desc')
            ->paginate($perPage)
            ->withQueryString()
            ->through(fn ($row) => [
                'transactionkey' => (int) $row->transactionkey,
                'customercode' => (int) ($row->customercode ?? 0),
                'alternatecode' => $row->alternatecode,
                'customername' => $row->customername,
                'arbcustomername' => $row->arbcustomername,
                'invoicenumber' => $this->identifier($row->invoicenumber),
                'routecode' => (int) ($row->routecode ?? 0),
                'routename' => $row->routename,
                'arbroutename' => $row->arbroutename,
                'salesmancode' => (int) ($row->salesmancode ?? 0),
                'salesmanname1' => $row->salesmanname1,
                'arbsalesmanname1' => $row->arbsalesmanname1,
                'amountpaid' => (float) ($row->amountpaid ?? 0),
            ]);

        return Inertia::render('transaction/ar-collection/Index', [
            'documents' => $documents,
            'routeOptions' => $this->routeOptions($user),
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
        abort_unless($this->hasOverviewTables(), 404);

        $headerAlias = $this->prefixedAlias('header');
        $routeAlias = $this->prefixedAlias('route');
        $salesmanAlias = $this->prefixedAlias('salesman');
        $customerAlias = $this->prefixedAlias('customer');
        $sedAlias = $this->prefixedAlias('sed');
        $paymentAlias = $this->prefixedAlias('payment');
        $bankAlias = $this->prefixedAlias('bank');

        $header = DB::table('arheader as header')
            ->leftJoin('routemaster as route', 'route.routecode', '=', 'header.routecode')
            ->leftJoin('salesman as salesman', 'salesman.salesmancode', '=', 'header.salesmancode')
            ->leftJoin('customermaster as customer', 'customer.customercode', '=', 'header.customercode')
            ->leftJoin('startendday as sed', 'sed.routekey', '=', 'header.routekey')
            ->where('header.transactionkey', $transactionkey)
            ->where(function ($query) {
                $query->whereNull('header.advancepaymentflag')
                    ->orWhere('header.advancepaymentflag', '!=', 1);
            })
            ->selectRaw("
                {$headerAlias}.transactionkey,
                {$headerAlias}.routekey,
                {$headerAlias}.visitkey,
                {$headerAlias}.documentnumber,
                {$headerAlias}.invoicenumber,
                {$headerAlias}.transactiondate,
                COALESCE({$headerAlias}.transactiontime, '') as transactiontime,
                {$headerAlias}.routecode,
                COALESCE({$routeAlias}.routename, '') as routename,
                COALESCE({$routeAlias}.arbroutename, '') as arbroutename,
                COALESCE(DATE_FORMAT({$sedAlias}.routestartdate, '%d-%m-%Y'), '') as routestartdate,
                {$headerAlias}.salesmancode,
                COALESCE({$salesmanAlias}.salesmanname1, '') as salesmanname1,
                COALESCE({$salesmanAlias}.arbsalesmanname1, '') as arbsalesmanname1,
                {$headerAlias}.customercode,
                COALESCE({$customerAlias}.alternatecode, '') as alternatecode,
                COALESCE({$customerAlias}.customername, '') as customername,
                COALESCE({$customerAlias}.arbcustomername, '') as arbcustomername,
                {$this->stringColumnExpression('customermaster', 'customeraddress1', "{$customerAlias}.customeraddress1")} as customeraddress1,
                {$this->numericColumnExpression('customermaster', 'invoicepaymentterms', "{$customerAlias}.invoicepaymentterms")} as invoicepaymentterms,
                {$this->numericColumnExpression('arheader', 'amountpaid', "{$headerAlias}.amountpaid")} as amountpaid,
                {$this->numericColumnExpression('arheader', 'excesspayment', "{$headerAlias}.excesspayment")} as excesspayment,
                {$this->numericColumnExpression('arheader', 'totalinvoiceamount', "{$headerAlias}.totalinvoiceamount")} as totalinvoiceamount,
                {$this->numericColumnExpression('arheader', 'invoicebalance', "{$headerAlias}.invoicebalance")} as invoicebalance,
                {$this->numericColumnExpression('arheader', 'voidflag', "{$headerAlias}.voidflag")} as voidflag,
                {$this->stringColumnExpression('arheader', 'comments', "{$headerAlias}.comments")} as comments
            ")
            ->first();

        abort_unless($header, 404);
        abort_unless(app(AccessScopeService::class)->allows($request->user(), 'route', $header->routecode ?? null), 403);

        $details = collect();
        if (Schema::hasTable('ardetail')) {
            $details = DB::table('ardetail')
                ->where('transactionkey', $transactionkey)
                ->selectRaw('
                    COALESCE(invoicenumber, "") as invoicenumber,
                    invoicedate,
                    COALESCE(totalinvoiceamount, 0) as totalinvoiceamount,
                    COALESCE(amountpaid, 0) as amountpaid
                ')
                ->orderBy('invoicedate')
                ->orderBy('invoicenumber')
                ->get()
                ->map(fn ($row) => [
                    'invoicenumber' => $this->identifier($row->invoicenumber),
                    'invoicedate' => $this->formatDate($row->invoicedate),
                    'totalinvoiceamount' => (float) ($row->totalinvoiceamount ?? 0),
                    'amountpaid' => (float) ($row->amountpaid ?? 0),
                ])
                ->values();
        }

        $payments = collect();
        if (Schema::hasTable('cashcheckdetail')) {
            $payments = DB::table('cashcheckdetail as payment')
                ->leftJoin('bankmaster as bank', 'bank.bankcode', '=', 'payment.bankcode')
                ->where('payment.routekey', $header->routekey)
                ->where('payment.visitkey', $header->visitkey)
                ->where('payment.transactiontype', 2)
                ->selectRaw("
                    {$paymentAlias}.typecode,
                    COALESCE({$paymentAlias}.amount, 0) as amount,
                    COALESCE({$paymentAlias}.checknumber, '') as checknumber,
                    {$paymentAlias}.checkdate,
                    COALESCE({$bankAlias}.bankname, {$bankAlias}.arbbankname, '') as bankname
                ")
                ->get()
                ->map(fn ($row) => [
                    'mode' => (int) ($row->typecode ?? 0) === 1 ? 'Cheque' : 'Cash',
                    'amount' => (float) ($row->amount ?? 0),
                    'checknumber' => $this->identifier($row->checknumber),
                    'checkdate' => $this->formatDate($row->checkdate),
                    'bankname' => $row->bankname,
                ])
                ->values();
        }

        return Inertia::render('transaction/ar-collection/Show', [
            'header' => [
                'transactionkey' => (int) $header->transactionkey,
                'documentnumber' => $this->identifier($header->documentnumber),
                'invoicenumber' => $this->identifier($header->invoicenumber),
                'transactiondate' => $this->formatDate($header->transactiondate),
                'transactiontime' => $header->transactiontime,
                'routecode' => (int) ($header->routecode ?? 0),
                'routename' => $header->routename,
                'arbroutename' => $header->arbroutename,
                'routestartdate' => $header->routestartdate,
                'salesmancode' => (int) ($header->salesmancode ?? 0),
                'salesmanname1' => $header->salesmanname1,
                'arbsalesmanname1' => $header->arbsalesmanname1,
                'customercode' => (int) ($header->customercode ?? 0),
                'alternatecode' => $header->alternatecode,
                'customername' => $header->customername,
                'arbcustomername' => $header->arbcustomername,
                'customeraddress1' => $header->customeraddress1,
                'paymentterm' => $this->paymentTermLabel($header->invoicepaymentterms),
                'amountpaid' => (float) ($header->amountpaid ?? 0),
                'excesspayment' => (float) ($header->excesspayment ?? 0),
                'totalinvoiceamount' => (float) ($header->totalinvoiceamount ?? 0),
                'invoicebalance' => (float) ($header->invoicebalance ?? 0),
                'documentvalid' => $this->documentValidityLabel($header->voidflag),
                'comments' => $header->comments,
            ],
            'details' => $details,
            'payments' => $payments,
            'filters' => [
                'date' => (string) $request->input('date', ''),
                'routecode' => max(0, (int) $request->input('routecode', 0)),
                'search' => (string) $request->input('search', ''),
                'page' => max(1, (int) $request->input('page', 1)),
                'per_page' => max(10, (int) $request->input('per_page', 10)),
                'sort_by' => (string) $request->input('sort_by', 'invoicenumber'),
                'sort_dir' => (string) $request->input('sort_dir', 'desc'),
            ],
        ]);
    }

    private function hasOverviewTables(): bool
    {
        return Schema::hasTable('arheader')
            && Schema::hasTable('routemaster')
            && Schema::hasTable('salesman')
            && Schema::hasTable('customermaster');
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

    private function routeOptions($user = null): array
    {
        if (!Schema::hasTable('routemaster')) {
            return [];
        }

        $scope = app(AccessScopeService::class);
        $query = DB::table('routemaster')
            ->select(['routecode', 'routename'])
            ->orderBy('routecode');

        if (Schema::hasColumn('routemaster', 'activestatus')) {
            $query->where('activestatus', 1);
        }

        $scope->scopeQuery($user, $query, 'route', 'routecode');

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
            'customercode' => 'header.customercode',
            'customername' => 'customer.customername',
            'routecode' => 'header.routecode',
            'routename' => 'route.routename',
            'salesmanname1' => 'salesman.salesmanname1',
            'amountpaid' => 'header.amountpaid',
            default => 'header.invoicenumber',
        };
    }

    private function numericColumnExpression(string $table, string $column, string $qualifiedColumn): string
    {
        return Schema::hasColumn($table, $column)
            ? 'COALESCE(' . $qualifiedColumn . ', 0)'
            : '0';
    }

    private function stringColumnExpression(string $table, string $column, string $qualifiedColumn): string
    {
        return Schema::hasColumn($table, $column)
            ? 'COALESCE(' . $qualifiedColumn . ', "")'
            : '""';
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
            0, 1 => 'Cash',
            2 => 'Credit',
            3 => 'TC (Cash or Cheque)',
            4 => 'TC (Cash Only)',
            default => '',
        };
    }

    private function prefixedAlias(string $alias): string
    {
        return DB::getTablePrefix() . $alias;
    }
}
