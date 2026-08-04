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

class AdvancePaymentController extends Controller
{
    public function index(Request $request): Response
    {
        $scope = app(AccessScopeService::class);
        $user = $request->user();
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
            return Inertia::render('transaction/advance-payment/Index', [
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

        $query = DB::table('arheader as header')
            ->leftJoin('routemaster as route', 'route.routecode', '=', 'header.routecode')
            ->leftJoin('salesman as salesman', 'salesman.salesmancode', '=', 'header.salesmancode')
            ->leftJoin('customermaster as customer', 'customer.customercode', '=', 'header.customercode')
            ->selectRaw('
                header.transactionkey,
                header.customercode,
                COALESCE(customer.alternatecode, "") as alternatecode,
                COALESCE(customer.customername, "") as customername,
                COALESCE(customer.arbcustomername, "") as arbcustomername,
                COALESCE(header.invoicenumber, "") as invoicenumber,
                header.routecode,
                COALESCE(route.routename, "") as routename,
                COALESCE(route.arbroutename, "") as arbroutename,
                header.salesmancode,
                COALESCE(salesman.salesmanname1, "") as salesmanname1,
                COALESCE(salesman.arbsalesmanname1, "") as arbsalesmanname1,
                COALESCE(header.amountpaid, 0) as amountpaid
            ')
            ->whereDate('header.transactiondate', $selectedDate)
            ->where('header.advancepaymentflag', 1);

        $scope->scopeQuery($user, $query, 'route', 'header.routecode');

        if (Schema::hasColumn('arheader', 'record_flag')) {
            $query->where('header.record_flag', '1');
        }

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

        return Inertia::render('transaction/advance-payment/Index', [
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
        abort_unless($this->hasOverviewTables(), 404);

        $header = DB::table('arheader as header')
            ->leftJoin('routemaster as route', 'route.routecode', '=', 'header.routecode')
            ->leftJoin('salesman as salesman', 'salesman.salesmancode', '=', 'header.salesmancode')
            ->leftJoin('customermaster as customer', 'customer.customercode', '=', 'header.customercode')
            ->leftJoin('startendday as sed', 'sed.routekey', '=', 'header.routekey')
            ->where('header.transactionkey', $transactionkey)
            ->where('header.advancepaymentflag', 1)
            ->selectRaw('
                header.transactionkey,
                header.visitkey,
                header.documentnumber,
                header.invoicenumber,
                header.transactiondate,
                COALESCE(header.transactiontime, "") as transactiontime,
                header.routekey,
                header.routecode,
                COALESCE(route.routename, "") as routename,
                COALESCE(route.arbroutename, "") as arbroutename,
                COALESCE(DATE_FORMAT(sed.routestartdate, "%d-%m-%Y"), "") as routestartdate,
                header.salesmancode,
                COALESCE(salesman.salesmanname1, "") as salesmanname1,
                COALESCE(salesman.arbsalesmanname1, "") as arbsalesmanname1,
                header.customercode,
                COALESCE(customer.alternatecode, "") as alternatecode,
                COALESCE(customer.customername, "") as customername,
                COALESCE(customer.arbcustomername, "") as arbcustomername,
                ' . $this->stringColumnExpression('customermaster', 'customeraddress1', 'customer.customeraddress1') . ' as customeraddress1,
                ' . $this->numericColumnExpression('customermaster', 'invoicepaymentterms', 'customer.invoicepaymentterms') . ' as invoicepaymentterms,
                ' . $this->numericColumnExpression('arheader', 'totalinvoiceamount', 'header.totalinvoiceamount') . ' as totalinvoiceamount,
                ' . $this->numericColumnExpression('arheader', 'amountpaid', 'header.amountpaid') . ' as amountpaid,
                ' . $this->numericColumnExpression('arheader', 'invoicebalance', 'header.invoicebalance') . ' as invoicebalance,
                ' . $this->numericColumnExpression('arheader', 'voidflag', 'header.voidflag') . ' as voidflag,
                ' . $this->stringColumnExpression('arheader', 'comments', 'header.comments') . ' as comments
            ')
            ->first();

        abort_unless($header, 404);
        abort_unless(app(AccessScopeService::class)->allows($request->user(), 'route', $header->routecode ?? null), 403);

        $paymentRows = collect();
        if (Schema::hasTable('cashcheckdetail')) {
            $paymentRows = DB::table('cashcheckdetail as payment')
                ->leftJoin('bankmaster as bank', 'bank.bankcode', '=', 'payment.bankcode')
                ->where('payment.routekey', $header->routekey)
                ->where('payment.visitkey', $header->visitkey)
                ->where('payment.transactiontype', 2)
                ->selectRaw('
                    payment.typecode,
                    COALESCE(payment.amount, 0) as amount,
                    COALESCE(payment.checknumber, "") as checknumber,
                    payment.checkdate,
                    COALESCE(bank.bankname, bank.arbbankname, "") as bankname
                ')
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

        return Inertia::render('transaction/advance-payment/Show', [
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
                'totalinvoiceamount' => (float) ($header->totalinvoiceamount ?? 0),
                'amountpaid' => (float) ($header->amountpaid ?? 0),
                'invoicebalance' => (float) ($header->invoicebalance ?? 0),
                'documentvalid' => $this->documentValidityLabel($header->voidflag),
                'comments' => $header->comments,
            ],
            'payments' => $paymentRows,
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
}
