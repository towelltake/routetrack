<?php

namespace App\Http\Controllers\Account;

use App\Http\Controllers\Controller;
use App\Services\AccessScopeService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class TransactionController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('account/transaction/Index');
    }

    public function monthClose(Request $request): Response
    {
        $scope = app(AccessScopeService::class);
        $user = $request->user();
        $year = $request->integer('year') ?: now()->year;
        $month = $request->integer('month');

        $rows = DB::table('monthlysummarizationperroute as mspr')
            ->leftJoin('routemaster as rm', 'rm.routecode', '=', 'mspr.routecode')
            ->selectRaw('mspr.routecode, COALESCE(rm.routename, rm.arbroutename, "") as routename, mspr.byear as byear, mspr.bmonth as bmonth')
            ->when($year, fn ($query) => $query->where('mspr.byear', $year))
            ->when($month, fn ($query) => $query->where('mspr.bmonth', $month))
            ->tap(fn ($query) => $scope->scopeQuery($user, $query, 'route', 'mspr.routecode'))
            ->groupBy('mspr.routecode', 'rm.routename', 'rm.arbroutename', 'mspr.byear', 'mspr.bmonth')
            ->orderBy('mspr.byear')
            ->orderBy('mspr.bmonth')
            ->orderBy('mspr.routecode')
            ->get()
            ->map(fn ($row) => [
                'routecode' => (int) $row->routecode,
                'routename' => $row->routename,
                'byear' => (int) $row->byear,
                'bmonth' => (int) $row->bmonth,
                'month_label' => $this->monthName((int) $row->bmonth),
            ])
            ->values();

        return Inertia::render('account/transaction/MonthClose', [
            'filters' => [
                'year' => $year,
                'month' => $month,
            ],
            'yearOptions' => $this->yearOptions(),
            'monthOptions' => $this->monthOptions(),
            'rows' => $rows,
        ]);
    }

    public function storeMonthClose(Request $request): RedirectResponse
    {
        $payload = $request->validate([
            'year' => ['required', 'integer', 'between:2000,2100'],
            'month' => ['required', 'integer', 'between:1,12'],
        ]);

        $result = DB::select('CALL sp_add_account_transaction_monthclose(?, ?)', [
            (int) $payload['month'],
            (int) $payload['year'],
        ]);

        $message = $result[0]->msg ?? null;

        return redirect()
            ->route('account.transaction.month-close', [
                'year' => (int) $payload['year'],
                'month' => (int) $payload['month'],
            ])
            ->with(
                $message === 'Failed' ? 'error' : 'success',
                $message === 'Failed'
                    ? 'This month transaction was already closed.'
                    : 'Month close completed.'
            );
    }

    public function monthCloseView(int $routecode, int $year, int $month): Response
    {
        $this->assertRouteAccess($routecode);
        $itemAlias = DB::getTablePrefix() . 'im';

        $route = DB::table('routemaster')
            ->select('routecode', 'routename', 'arbroutename')
            ->where('routecode', $routecode)
            ->first();

        abort_unless($route, 404);

        $rows = DB::table('monthlysummarizationperroute as mspr')
            ->leftJoin('itemmaster as im', 'im.actualitemcode', '=', 'mspr.itemcode')
            ->selectRaw('
                mspr.itemcode,
                COALESCE(' . $itemAlias . '.itemshortdescription, "") as itemshortdescription,
                mspr.upc,
                COALESCE(mspr.quantitybegininventory, 0) as quantitybegininventory,
                COALESCE(mspr.quantityload, 0) as quantityload,
                COALESCE(mspr.quantityloadadjust, 0) as quantityloadadjust,
                COALESCE(mspr.quantitytransfer, 0) as quantitytransfer,
                COALESCE(mspr.quantitysales, 0) as quantitysales,
                COALESCE(mspr.quantitybuybacks, 0) as quantitybuybacks,
                COALESCE(mspr.quantityreturnscredited, 0) as quantityreturnscredited,
                COALESCE(mspr.quantitytruckspoilage, 0) as quantitytruckspoilage,
                COALESCE(mspr.quantityfreegood, 0) as quantityfreegood,
                COALESCE(mspr.quantitybuybackfree, 0) as quantitybuybackfree,
                COALESCE(mspr.quantitygiveaway, 0) as quantitygiveaway,
                COALESCE(mspr.quantityendinginventory, 0) as quantityendinginventory,
                COALESCE(mspr.quantitydamage, 0) as quantitydamage,
                COALESCE(mspr.valueendinginventory, 0) as valueendinginventory
            ')
            ->where('mspr.routecode', $routecode)
            ->where('mspr.byear', $year)
            ->where('mspr.bmonth', $month)
            ->orderBy('mspr.itemcode')
            ->get();

        return Inertia::render('account/transaction/MonthCloseView', [
            'header' => [
                'routecode' => $routecode,
                'routename' => $route->routename ?: $route->arbroutename,
                'year' => $year,
                'month' => $month,
                'monthLabel' => $this->monthName($month),
            ],
            'rows' => $rows,
        ]);
    }

    public function gcCollection(Request $request): Response
    {
        $scope = app(AccessScopeService::class);
        $user = $request->user();
        $date = $this->selectedDate($request);
        $bearAlias = DB::getTablePrefix() . 'bear';
        $routeAlias = DB::getTablePrefix() . 'rm';
        $customerAlias = DB::getTablePrefix() . 'cm';

        $rows = DB::table('bearheader as bear')
            ->leftJoin('routemaster as rm', 'rm.routecode', '=', 'bear.routecode')
            ->leftJoin('customermaster as cm', 'cm.customercode', '=', 'bear.customercode')
            ->selectRaw("
                {$bearAlias}.transactionkey,
                {$bearAlias}.documentnumber,
                {$bearAlias}.transactiondate,
                {$bearAlias}.routecode,
                COALESCE({$routeAlias}.routename, {$routeAlias}.arbroutename, '') as routename,
                {$bearAlias}.customercode,
                COALESCE({$customerAlias}.customername, {$customerAlias}.arbcustomername, '') as customername,
                {$bearAlias}.totalinvoiceamount,
                {$bearAlias}.amountpaid
            ")
            ->where('bear.trantype', 2)
            ->where('bear.customercode', '!=', 0)
            ->whereDate('bear.transactiondate', $date->toDateString())
            ->tap(fn ($query) => $scope->scopeQuery($user, $query, 'route', 'bear.routecode'))
            ->orderByDesc('bear.transactionkey')
            ->get()
            ->map(fn ($row) => [
                'transactionkey' => (int) $row->transactionkey,
                'documentnumber' => $row->documentnumber,
                'transactiondate' => $row->transactiondate,
                'routecode' => (int) $row->routecode,
                'routename' => $row->routename,
                'customercode' => (int) $row->customercode,
                'customername' => $row->customername,
                'totalinvoiceamount' => (float) $row->totalinvoiceamount,
                'amountpaid' => (float) $row->amountpaid,
            ])
            ->values();

        return Inertia::render('account/transaction/gc-collection/Index', [
            'filters' => ['date' => $date->format('Y-m-d')],
            'rows' => $rows,
        ]);
    }

    public function createGcCollection(Request $request): Response
    {
        $date = $this->selectedDate($request);
        [$routeOptions, $bankOptions, $nextDocumentNumber] = $this->gcCollectionBaseOptions();

        return Inertia::render('account/transaction/gc-collection/Form', [
            'mode' => 'create',
            'filters' => ['date' => $date->format('Y-m-d')],
            'routeOptions' => $routeOptions,
            'bankOptions' => $bankOptions,
            'gcCollectionData' => [
                'transactionkey' => null,
                'routecode' => '',
                'routeLabel' => '',
                'salesmancode' => '',
                'salesmanname' => '',
                'customercode' => '',
                'customerLabel' => '',
                'documentnumber' => $nextDocumentNumber,
                'erpreferencenumber' => '',
                'paymentmode' => 0,
                'checknumber' => '',
                'checkdate' => '',
                'bankcode' => '',
                'amount' => '',
                'invoiceamount' => '',
                'balanceamount' => '',
                'firstoutstanding' => false,
            ],
            'initialMeta' => [
                'salesmancode' => '',
                'salesmanname' => '',
                'documentnumber' => $nextDocumentNumber,
                'customerOptions' => [],
            ],
            'invoiceRows' => [],
            'paymentDetails' => null,
        ]);
    }

    public function storeGcCollection(Request $request): RedirectResponse
    {
        $payload = $request->validate([
            'date' => ['required', 'date'],
            'routecode' => ['required', 'integer'],
            'salesmancode' => ['required', 'integer'],
            'customercode' => ['required', 'integer'],
            'paymentmode' => ['required', 'integer', 'in:0,1'],
            'amount' => ['required', 'numeric', 'gt:0'],
            'invoice_total' => ['required', 'numeric', 'gte:0'],
            'balance_total' => ['required', 'numeric', 'gte:0'],
            'invoice_ids' => ['required', 'array', 'min:1'],
            'invoice_ids.*' => ['required', 'integer'],
            'invoice_amounts' => ['required', 'array', 'min:1'],
            'invoice_amounts.*' => ['required', 'numeric'],
            'erpreferencenumber' => ['nullable', 'string', 'max:30'],
            'checknumber' => ['nullable', 'string', 'max:15'],
            'checkdate' => ['nullable', 'date'],
            'bankcode' => ['nullable', 'integer'],
            'firstoutstanding' => ['nullable', 'boolean'],
        ]);

        $this->assertRouteAccess((int) $payload['routecode']);

        DB::transaction(function () use ($payload, $request) {
            $route = DB::table('routemaster')
                ->select('routecode', 'acbodocseq', 'amountdecimaldigits')
                ->where('routecode', (int) $payload['routecode'])
                ->lockForUpdate()
                ->first();

            abort_unless($route, 404);

            $customer = DB::table('customermaster')
                ->select('customercode', 'enableadvancepayment')
                ->where('customercode', (int) $payload['customercode'])
                ->lockForUpdate()
                ->first();

            abort_unless($customer, 404);

            $invoiceRows = DB::table('customerinvoice')
                ->select('transactionkey', 'invoicenumber', 'totalinvoiceamount', 'amountpaid', 'invoicebalance')
                ->whereIn('transactionkey', array_map('intval', $payload['invoice_ids']))
                ->lockForUpdate()
                ->get()
                ->keyBy(fn ($row) => (int) $row->transactionkey);

            $documentNumber = $this->nextOpeningBalanceDocumentNumber((int) $payload['routecode']);
            $amount = abs((float) $payload['amount']);
            $transactionDate = Carbon::parse($payload['date'])->toDateString();
            $checkDate = !empty($payload['checkdate']) ? Carbon::parse($payload['checkdate'])->toDateString() : null;
            $currencyCode = (int) ($route->amountdecimaldigits ?? 0);
            $pdcAsCash = $this->pdcAsCashEnabled();
            $aggregateTotalInvoice = (float) $invoiceRows->sum(fn ($row) => (float) $row->totalinvoiceamount);
            $aggregateAmountPaid = (float) $invoiceRows->sum(fn ($row) => (float) $row->amountpaid);

            $headerId = DB::table('bearheader')->insertGetId([
                'routecode' => (int) $payload['routecode'],
                'documentnumber' => $documentNumber,
                'salesmancode' => (int) $payload['salesmancode'],
                'customercode' => (int) $payload['customercode'],
                'trantype' => 2,
                'amountpaid' => $amount,
                'totalinvoiceamount' => $aggregateTotalInvoice,
                'invoicebalance' => $aggregateTotalInvoice - ($aggregateAmountPaid + $amount),
                'transactiondate' => $transactionDate,
                'transactiontime' => now()->format('H:i:s'),
                'erpreferencenumber' => $payload['erpreferencenumber'] ?: '',
                'created' => $request->user()->username,
                'modified' => $request->user()->username,
                'cdat' => now()->toDateString(),
                'mdat' => now(),
                'currencycode' => $currencyCode,
                'pdcstatus' => (int) $payload['paymentmode'],
                'voidflag' => 0,
            ]);

            DB::table('routemaster')
                ->where('routecode', (int) $payload['routecode'])
                ->update([
                    'acbodocseq' => DB::raw('COALESCE(acbodocseq, 0) + 1'),
                ]);

            DB::table('bearcashcheckdetail')->insert([
                'transactionkey' => $headerId,
                'typecode' => (int) $payload['paymentmode'],
                'checknumber' => $payload['paymentmode'] === 1 ? ($payload['checknumber'] ?: null) : null,
                'checkdate' => $payload['paymentmode'] === 1 ? $checkDate : null,
                'bankcode' => $payload['paymentmode'] === 1 ? ($payload['bankcode'] ?: null) : null,
                'amount' => $amount,
                'currencycode' => $currencyCode,
            ]);

            $remainingAmount = $amount;

            foreach (array_values(array_map('intval', $payload['invoice_ids'])) as $index => $invoiceTransactionKey) {
                $invoice = $invoiceRows->get($invoiceTransactionKey);

                if (! $invoice) {
                    continue;
                }

                $requestedAmount = (float) ($payload['invoice_amounts'][$index] ?? 0);
                $invoiceBalance = (float) $invoice->invoicebalance;
                $applicableAmount = min(abs($requestedAmount), abs($invoiceBalance));
                $appliedAmount = $invoiceBalance < 0 ? -$applicableAmount : $applicableAmount;
                $detailBalance = $invoiceBalance - $appliedAmount;

                DB::table('beardetail')->insert([
                    'transactionkey' => $headerId,
                    'customercode' => (int) $payload['customercode'],
                    'invoicenumber' => $invoice->invoicenumber,
                    'totalinvoiceamount' => (float) $invoice->totalinvoiceamount,
                    'amountpaid' => $appliedAmount,
                    'invoicebalance' => $detailBalance,
                    'paymentmode' => (int) $payload['paymentmode'],
                    'invoicedate' => $transactionDate,
                    'currencycode' => $currencyCode,
                ]);

                if ((int) $payload['paymentmode'] === 0 || $pdcAsCash) {
                    DB::table('customerinvoice')
                        ->where('transactionkey', $invoiceTransactionKey)
                        ->update([
                            'amountpaid' => DB::raw('COALESCE(amountpaid, 0) + ' . $appliedAmount),
                            'invoicebalance' => DB::raw('COALESCE(invoicebalance, 0) - ' . $appliedAmount),
                        ]);

                    DB::table('customermaster')
                        ->where('customercode', (int) $payload['customercode'])
                        ->update([
                            'balance' => DB::raw('COALESCE(balance, 0) - ' . $appliedAmount),
                        ]);
                } else {
                    $pdcDateSql = $checkDate ? "'" . $checkDate . "'" : 'NULL';

                    DB::table('customerinvoice')
                        ->where('transactionkey', $invoiceTransactionKey)
                        ->update([
                            'pdcindicator' => 1,
                            'pdcbalance' => DB::raw('COALESCE(pdcbalance, 0) + ' . $appliedAmount),
                            'pdcdate' => DB::raw($pdcDateSql),
                        ]);
                }

                $remainingAmount -= $appliedAmount;
            }

            if ($remainingAmount > 0 && (int) ($customer->enableadvancepayment ?? 0) === 1) {
                DB::table('customermaster')
                    ->where('customercode', (int) $payload['customercode'])
                    ->update([
                        'balance' => DB::raw('COALESCE(balance, 0) + ' . $remainingAmount),
                    ]);
            }
        });

        return redirect()
            ->route('account.transaction.gc-collection', ['date' => $payload['date']])
            ->with('success', 'GC collection saved.');
    }

    public function showGcCollection(Request $request, int $transactionkey): Response
    {
        [$routeOptions, $bankOptions] = $this->gcCollectionBaseOptions();

        $header = DB::table('bearheader')
            ->where('transactionkey', $transactionkey)
            ->where('trantype', 2)
            ->first();

        abort_unless($header, 404);
        $this->assertRouteAccess((int) $header->routecode);

        $payment = DB::table('bearcashcheckdetail as bcd')
            ->leftJoin('bankmaster as bm', 'bm.bankcode', '=', 'bcd.bankcode')
            ->selectRaw('bcd.typecode, bcd.checknumber, bcd.checkdate, SUM(bcd.amount) as amount, bm.bankname')
            ->where('bcd.transactionkey', $transactionkey)
            ->groupBy('bcd.transactionkey', 'bcd.typecode', 'bcd.checknumber', 'bcd.checkdate', 'bm.bankname')
            ->first();
        $meta = $this->gcCollectionRouteMetaPayload((int) $header->routecode);
        $invoiceRows = $this->gcCollectionInvoiceRows((int) $header->routecode, (int) $header->customercode, $transactionkey);
        $totals = $this->gcInvoiceTotals(collect($invoiceRows)->pluck('transactionkey')->all());

        return Inertia::render('account/transaction/gc-collection/Form', [
            'mode' => 'view',
            'filters' => ['date' => Carbon::parse($header->transactiondate)->format('Y-m-d')],
            'routeOptions' => $routeOptions,
            'bankOptions' => $bankOptions,
            'gcCollectionData' => [
                'transactionkey' => (int) $header->transactionkey,
                'routecode' => (int) $header->routecode,
                'routeLabel' => trim($header->routecode . ' - ' . ($this->routeName((int) $header->routecode) ?? '')),
                'salesmancode' => (int) $header->salesmancode,
                'salesmanname' => $meta['salesmanname'],
                'customercode' => (int) $header->customercode,
                'customerLabel' => trim($header->customercode . ' - ' . ($this->customerName((int) $header->customercode) ?? '')),
                'documentnumber' => $header->documentnumber,
                'erpreferencenumber' => $header->erpreferencenumber,
                'paymentmode' => isset($payment?->typecode) ? (int) $payment->typecode : 0,
                'checknumber' => $payment->checknumber ?? '',
                'checkdate' => !empty($payment?->checkdate) ? Carbon::parse($payment->checkdate)->format('Y-m-d') : '',
                'bankcode' => $this->bankCodeFromName($payment->bankname ?? null, $bankOptions),
                'amount' => isset($payment?->amount) ? (float) $payment->amount : (float) $header->amountpaid,
                'invoiceamount' => $totals['invoice_total'],
                'balanceamount' => $totals['balance_total'],
                'firstoutstanding' => false,
            ],
            'initialMeta' => $meta,
            'invoiceRows' => $invoiceRows,
            'paymentDetails' => $payment ? [
                'amount' => (float) ($payment->amount ?? 0),
                'typecode' => (int) ($payment->typecode ?? 0),
                'checknumber' => $payment->checknumber ?? '',
                'checkdate' => !empty($payment->checkdate) ? Carbon::parse($payment->checkdate)->format('d-m-Y') : '',
                'bankname' => $payment->bankname ?? '',
            ] : null,
        ]);
    }

    public function destroyGcCollection(Request $request, int $transactionkey): RedirectResponse
    {
        $deleted = false;

        DB::transaction(function () use ($transactionkey, &$deleted) {
            $header = DB::table('bearheader')
                ->select('transactionkey', 'routecode', 'pdcstatus')
                ->where('transactionkey', $transactionkey)
                ->lockForUpdate()
                ->first();

            if (! $header || (int) $header->pdcstatus === 2) {
                return;
            }

            $this->assertRouteAccess((int) $header->routecode);

            $details = DB::table('beardetail')
                ->select('customercode', 'invoicenumber', 'amountpaid', 'paymentmode')
                ->where('transactionkey', $transactionkey)
                ->lockForUpdate()
                ->get();

            $pdcAsCash = $this->pdcAsCashEnabled();

            foreach ($details as $detail) {
                $amountPaid = (float) $detail->amountpaid;

                if ((int) $detail->paymentmode === 0 || $pdcAsCash) {
                    DB::table('customerinvoice')
                        ->where('invoicenumber', $detail->invoicenumber)
                        ->update([
                            'amountpaid' => DB::raw('COALESCE(amountpaid, 0) - ' . $amountPaid),
                            'invoicebalance' => DB::raw('COALESCE(invoicebalance, 0) + ' . $amountPaid),
                        ]);

                    DB::table('customermaster')
                        ->where('customercode', (int) $detail->customercode)
                        ->update([
                            'balance' => DB::raw('COALESCE(balance, 0) + ' . $amountPaid),
                        ]);
                } else {
                    DB::table('customerinvoice')
                        ->where('invoicenumber', $detail->invoicenumber)
                        ->update([
                            'pdcbalance' => DB::raw('COALESCE(pdcbalance, 0) - ' . $amountPaid),
                            'pdcindicator' => DB::raw('CASE WHEN COALESCE(pdcbalance, 0) - ' . $amountPaid . ' = 0 THEN 0 ELSE pdcindicator END'),
                            'pdcdate' => DB::raw('CASE WHEN COALESCE(pdcbalance, 0) - ' . $amountPaid . ' = 0 THEN NULL ELSE pdcdate END'),
                        ]);
                }
            }

            DB::table('bearcashcheckdetail')->where('transactionkey', $transactionkey)->delete();
            DB::table('beardetail')->where('transactionkey', $transactionkey)->delete();
            DB::table('bearheader')->where('transactionkey', $transactionkey)->delete();

            $deleted = true;
        });

        return redirect()
            ->route('account.transaction.gc-collection', ['date' => $request->input('date', now()->format('Y-m-d'))])
            ->with(
                $deleted ? 'success' : 'error',
                $deleted ? 'GC collection deleted.' : 'Record already in use.'
            );
    }

    public function gcCollectionRouteMeta(Request $request): JsonResponse
    {
        $payload = $request->validate([
            'routecode' => ['required', 'integer'],
        ]);

        $this->assertRouteAccess((int) $payload['routecode']);

        return response()->json($this->gcCollectionRouteMetaPayload((int) $payload['routecode']));
    }

    public function gcCollectionInvoices(Request $request): JsonResponse
    {
        $payload = $request->validate([
            'routecode' => ['required', 'integer'],
            'customercode' => ['required', 'integer'],
            'transactionkey' => ['nullable', 'integer'],
        ]);

        $rows = $this->gcCollectionInvoiceRows(
            (int) $payload['routecode'],
            (int) $payload['customercode'],
            $payload['transactionkey'] ? (int) $payload['transactionkey'] : null
        );

        $totals = $this->gcInvoiceTotals(collect($rows)->pluck('transactionkey')->all());

        return response()->json([
            'rows' => $rows,
            'totals' => $totals,
        ]);
    }

    public function hoCollection(Request $request): Response
    {
        $scope = app(AccessScopeService::class);
        $user = $request->user();
        $date = $this->selectedDate($request);
        $bearAlias = DB::getTablePrefix() . 'bear';
        $routeAlias = DB::getTablePrefix() . 'rm';
        $customerAlias = DB::getTablePrefix() . 'cm';

        $rows = DB::table('bearheader as bear')
            ->leftJoin('routemaster as rm', 'rm.routecode', '=', 'bear.routecode')
            ->leftJoin('customermaster as cm', 'cm.customercode', '=', 'bear.customercode')
            ->selectRaw("
                {$bearAlias}.transactionkey,
                {$bearAlias}.documentnumber,
                {$bearAlias}.transactiondate,
                {$bearAlias}.routecode,
                COALESCE({$routeAlias}.routename, {$routeAlias}.arbroutename, '') as routename,
                {$bearAlias}.customercode,
                COALESCE({$customerAlias}.customername, {$customerAlias}.arbcustomername, '') as customername,
                {$bearAlias}.totalinvoiceamount,
                {$bearAlias}.amountpaid
            ")
            ->where('bear.trantype', 1)
            ->where('bear.customercode', '!=', 0)
            ->whereDate('bear.transactiondate', $date->toDateString())
            ->tap(fn ($query) => $scope->scopeQuery($user, $query, 'route', 'bear.routecode'))
            ->orderByDesc('bear.transactionkey')
            ->get()
            ->map(fn ($row) => [
                'transactionkey' => (int) $row->transactionkey,
                'documentnumber' => $row->documentnumber,
                'transactiondate' => $row->transactiondate,
                'routecode' => (int) $row->routecode,
                'routename' => $row->routename,
                'customercode' => (int) $row->customercode,
                'customername' => $row->customername,
                'totalinvoiceamount' => (float) $row->totalinvoiceamount,
                'amountpaid' => (float) $row->amountpaid,
            ])
            ->values();

        return Inertia::render('account/transaction/ho-collection/Index', [
            'filters' => ['date' => $date->format('Y-m-d')],
            'rows' => $rows,
        ]);
    }

    public function createHoCollection(Request $request): Response
    {
        $date = $this->selectedDate($request);
        [$routeOptions, $bankOptions, $nextDocumentNumber] = $this->hoCollectionBaseOptions();

        return Inertia::render('account/transaction/ho-collection/Form', [
            'mode' => 'create',
            'filters' => ['date' => $date->format('Y-m-d')],
            'routeOptions' => $routeOptions,
            'bankOptions' => $bankOptions,
            'hoCollectionData' => [
                'transactionkey' => null,
                'routecode' => '',
                'routeLabel' => '',
                'salesmancode' => '',
                'salesmanname' => '',
                'customercode' => '',
                'customerLabel' => '',
                'documentnumber' => $nextDocumentNumber,
                'erpreferencenumber' => '',
                'paymentmode' => 0,
                'checknumber' => '',
                'checkdate' => '',
                'bankcode' => '',
                'amount' => '',
                'invoiceamount' => '',
                'balanceamount' => '',
                'firstoutstanding' => false,
            ],
            'initialMeta' => [
                'salesmancode' => '',
                'salesmanname' => '',
                'documentnumber' => $nextDocumentNumber,
                'customerOptions' => [],
            ],
            'invoiceRows' => [],
            'paymentDetails' => null,
        ]);
    }

    public function storeHoCollection(Request $request): RedirectResponse
    {
        $payload = $request->validate([
            'date' => ['required', 'date'],
            'routecode' => ['required', 'integer'],
            'salesmancode' => ['required', 'integer'],
            'customercode' => ['required', 'integer'],
            'paymentmode' => ['required', 'integer', 'in:0,1'],
            'amount' => ['required', 'numeric', 'gt:0'],
            'invoice_total' => ['required', 'numeric', 'gte:0'],
            'balance_total' => ['required', 'numeric', 'gte:0'],
            'invoice_ids' => ['required', 'array', 'min:1'],
            'invoice_ids.*' => ['required', 'integer'],
            'invoice_amounts' => ['required', 'array', 'min:1'],
            'invoice_amounts.*' => ['required', 'numeric'],
            'erpreferencenumber' => ['nullable', 'string', 'max:30'],
            'checknumber' => ['nullable', 'string', 'max:15'],
            'checkdate' => ['nullable', 'date'],
            'bankcode' => ['nullable', 'integer'],
            'firstoutstanding' => ['nullable', 'boolean'],
        ]);

        $this->assertRouteAccess((int) $payload['routecode']);

        DB::transaction(function () use ($payload, $request) {
            $route = DB::table('routemaster')
                ->select('routecode', 'acbodocseq', 'amountdecimaldigits')
                ->where('routecode', (int) $payload['routecode'])
                ->lockForUpdate()
                ->first();

            abort_unless($route, 404);

            $headerCustomer = DB::table('customermaster')
                ->select('customercode', 'enableadvancepayment')
                ->where('customercode', (int) $payload['customercode'])
                ->lockForUpdate()
                ->first();

            abort_unless($headerCustomer, 404);

            $invoiceRows = DB::table('customerinvoice')
                ->select('transactionkey', 'invoicenumber', 'totalinvoiceamount', 'amountpaid', 'invoicebalance', 'customercode')
                ->whereIn('transactionkey', array_map('intval', $payload['invoice_ids']))
                ->lockForUpdate()
                ->get()
                ->keyBy(fn ($row) => (int) $row->transactionkey);

            $documentNumber = $this->nextOpeningBalanceDocumentNumber((int) $payload['routecode']);
            $amount = abs((float) $payload['amount']);
            $transactionDate = Carbon::parse($payload['date'])->toDateString();
            $checkDate = !empty($payload['checkdate']) ? Carbon::parse($payload['checkdate'])->toDateString() : null;
            $currencyCode = (int) ($route->amountdecimaldigits ?? 0);
            $pdcAsCash = $this->pdcAsCashEnabled();
            $aggregateTotalInvoice = (float) $invoiceRows->sum(fn ($row) => (float) $row->totalinvoiceamount);
            $aggregateAmountPaid = (float) $invoiceRows->sum(fn ($row) => (float) $row->amountpaid);

            $headerId = DB::table('bearheader')->insertGetId([
                'routecode' => (int) $payload['routecode'],
                'documentnumber' => $documentNumber,
                'salesmancode' => (int) $payload['salesmancode'],
                'customercode' => (int) $payload['customercode'],
                'trantype' => 1,
                'amountpaid' => $amount,
                'totalinvoiceamount' => $aggregateTotalInvoice,
                'invoicebalance' => $aggregateTotalInvoice - ($aggregateAmountPaid + $amount),
                'transactiondate' => $transactionDate,
                'transactiontime' => now()->format('H:i:s'),
                'erpreferencenumber' => $payload['erpreferencenumber'] ?: '',
                'created' => $request->user()->username,
                'modified' => $request->user()->username,
                'cdat' => now()->toDateString(),
                'mdat' => now(),
                'currencycode' => $currencyCode,
                'pdcstatus' => (int) $payload['paymentmode'],
                'voidflag' => 0,
            ]);

            DB::table('routemaster')
                ->where('routecode', (int) $payload['routecode'])
                ->update([
                    'acbodocseq' => DB::raw('COALESCE(acbodocseq, 0) + 1'),
                ]);

            DB::table('bearcashcheckdetail')->insert([
                'transactionkey' => $headerId,
                'typecode' => (int) $payload['paymentmode'],
                'checknumber' => $payload['paymentmode'] === 1 ? ($payload['checknumber'] ?: null) : null,
                'checkdate' => $payload['paymentmode'] === 1 ? $checkDate : null,
                'bankcode' => $payload['paymentmode'] === 1 ? ($payload['bankcode'] ?: null) : null,
                'amount' => $amount,
                'currencycode' => $currencyCode,
            ]);

            $remainingAmount = $amount;

            foreach (array_values(array_map('intval', $payload['invoice_ids'])) as $index => $invoiceTransactionKey) {
                $invoice = $invoiceRows->get($invoiceTransactionKey);

                if (! $invoice) {
                    continue;
                }

                $requestedAmount = (float) ($payload['invoice_amounts'][$index] ?? 0);
                $invoiceBalance = (float) $invoice->invoicebalance;
                $applicableAmount = min(abs($requestedAmount), abs($invoiceBalance));
                $appliedAmount = $invoiceBalance < 0 ? -$applicableAmount : $applicableAmount;
                $detailBalance = $invoiceBalance - $appliedAmount;
                $detailCustomerCode = (int) $invoice->customercode;

                DB::table('beardetail')->insert([
                    'transactionkey' => $headerId,
                    'customercode' => $detailCustomerCode,
                    'invoicenumber' => $invoice->invoicenumber,
                    'totalinvoiceamount' => (float) $invoice->totalinvoiceamount,
                    'amountpaid' => $appliedAmount,
                    'invoicebalance' => $detailBalance,
                    'paymentmode' => (int) $payload['paymentmode'],
                    'invoicedate' => $transactionDate,
                    'currencycode' => $currencyCode,
                ]);

                if ((int) $payload['paymentmode'] === 0 || $pdcAsCash) {
                    DB::table('customermaster')
                        ->whereIn('customercode', array_unique([(int) $payload['customercode'], $detailCustomerCode]))
                        ->update([
                            'balance' => DB::raw('COALESCE(balance, 0) - ' . $appliedAmount),
                        ]);

                    DB::table('customerinvoice')
                        ->where('transactionkey', $invoiceTransactionKey)
                        ->update([
                            'amountpaid' => DB::raw('COALESCE(amountpaid, 0) + ' . $appliedAmount),
                            'invoicebalance' => DB::raw('COALESCE(invoicebalance, 0) - ' . $appliedAmount),
                        ]);
                } else {
                    $pdcDateSql = $checkDate ? "'" . $checkDate . "'" : 'NULL';

                    DB::table('customerinvoice')
                        ->where('transactionkey', $invoiceTransactionKey)
                        ->update([
                            'pdcindicator' => 1,
                            'pdcbalance' => DB::raw('COALESCE(pdcbalance, 0) + ' . $appliedAmount),
                            'pdcdate' => DB::raw($pdcDateSql),
                        ]);
                }

                $remainingAmount -= $appliedAmount;
            }

            if ($remainingAmount > 0 && (int) ($headerCustomer->enableadvancepayment ?? 0) === 1) {
                DB::table('customermaster')
                    ->where('customercode', (int) $payload['customercode'])
                    ->update([
                        'balance' => DB::raw('COALESCE(balance, 0) + ' . $remainingAmount),
                    ]);
            }
        });

        return redirect()
            ->route('account.transaction.ho-collection', ['date' => $payload['date']])
            ->with('success', 'HO collection saved.');
    }

    public function showHoCollection(Request $request, int $transactionkey): Response
    {
        [$routeOptions, $bankOptions] = $this->hoCollectionBaseOptions();

        $header = DB::table('bearheader')
            ->where('transactionkey', $transactionkey)
            ->where('trantype', 1)
            ->first();

        abort_unless($header, 404);
        $this->assertRouteAccess((int) $header->routecode);

        $payment = DB::table('bearcashcheckdetail as bcd')
            ->leftJoin('bankmaster as bm', 'bm.bankcode', '=', 'bcd.bankcode')
            ->selectRaw('bcd.typecode, bcd.checknumber, bcd.checkdate, SUM(bcd.amount) as amount, bm.bankname')
            ->where('bcd.transactionkey', $transactionkey)
            ->groupBy('bcd.transactionkey', 'bcd.typecode', 'bcd.checknumber', 'bcd.checkdate', 'bm.bankname')
            ->first();
        $meta = $this->hoCollectionRouteMetaPayload((int) $header->routecode);
        $invoiceRows = $this->hoCollectionInvoiceRows((int) $header->routecode, (int) $header->customercode, $transactionkey);
        $totals = $this->gcInvoiceTotals(collect($invoiceRows)->pluck('transactionkey')->all());

        return Inertia::render('account/transaction/ho-collection/Form', [
            'mode' => 'view',
            'filters' => ['date' => Carbon::parse($header->transactiondate)->format('Y-m-d')],
            'routeOptions' => $routeOptions,
            'bankOptions' => $bankOptions,
            'hoCollectionData' => [
                'transactionkey' => (int) $header->transactionkey,
                'routecode' => (int) $header->routecode,
                'routeLabel' => trim($header->routecode . ' - ' . ($this->routeName((int) $header->routecode) ?? '')),
                'salesmancode' => (int) $header->salesmancode,
                'salesmanname' => $meta['salesmanname'],
                'customercode' => (int) $header->customercode,
                'customerLabel' => trim($header->customercode . ' - ' . ($this->customerName((int) $header->customercode) ?? '')),
                'documentnumber' => $header->documentnumber,
                'erpreferencenumber' => $header->erpreferencenumber,
                'paymentmode' => isset($payment?->typecode) ? (int) $payment->typecode : 0,
                'checknumber' => $payment->checknumber ?? '',
                'checkdate' => !empty($payment?->checkdate) ? Carbon::parse($payment->checkdate)->format('Y-m-d') : '',
                'bankcode' => $this->bankCodeFromName($payment->bankname ?? null, $bankOptions),
                'amount' => isset($payment?->amount) ? (float) $payment->amount : (float) $header->amountpaid,
                'invoiceamount' => $totals['invoice_total'],
                'balanceamount' => $totals['balance_total'],
                'firstoutstanding' => false,
            ],
            'initialMeta' => $meta,
            'invoiceRows' => $invoiceRows,
            'paymentDetails' => $payment ? [
                'amount' => (float) ($payment->amount ?? 0),
                'typecode' => (int) ($payment->typecode ?? 0),
                'checknumber' => $payment->checknumber ?? '',
                'checkdate' => !empty($payment->checkdate) ? Carbon::parse($payment->checkdate)->format('d-m-Y') : '',
                'bankname' => $payment->bankname ?? '',
            ] : null,
        ]);
    }

    public function destroyHoCollection(Request $request, int $transactionkey): RedirectResponse
    {
        $deleted = false;

        DB::transaction(function () use ($transactionkey, &$deleted) {
            $header = DB::table('bearheader')
                ->select('transactionkey', 'pdcstatus', 'customercode')
                ->where('transactionkey', $transactionkey)
                ->lockForUpdate()
                ->first();

            if (! $header || (int) $header->pdcstatus === 2) {
                return;
            }

            $details = DB::table('beardetail')
                ->select('primary_key', 'customercode', 'invoicenumber', 'amountpaid', 'paymentmode')
                ->where('transactionkey', $transactionkey)
                ->lockForUpdate()
                ->get();

            $pdcAsCash = $this->pdcAsCashEnabled();
            $headerCashTotal = (float) $details
                ->filter(fn ($detail) => (int) $detail->customercode === (int) $header->customercode && (int) $detail->paymentmode === 0)
                ->sum(fn ($detail) => (float) $detail->amountpaid);

            if ($headerCashTotal !== 0.0) {
                DB::table('customermaster')
                    ->where('customercode', (int) $header->customercode)
                    ->update([
                        'balance' => DB::raw('COALESCE(balance, 0) + ' . $headerCashTotal),
                    ]);
            }

            foreach ($details as $detail) {
                $amountPaid = (float) $detail->amountpaid;

                if ((int) $detail->paymentmode === 0) {
                    DB::table('customermaster')
                        ->where('customercode', (int) $detail->customercode)
                        ->update([
                            'balance' => DB::raw('COALESCE(balance, 0) + ' . $amountPaid),
                        ]);
                }

                if ((int) $detail->paymentmode === 0 || $pdcAsCash) {
                    DB::table('customerinvoice')
                        ->where('invoicenumber', $detail->invoicenumber)
                        ->update([
                            'amountpaid' => DB::raw('COALESCE(amountpaid, 0) - ' . $amountPaid),
                            'invoicebalance' => DB::raw('COALESCE(invoicebalance, 0) + ' . $amountPaid),
                        ]);
                } else {
                    DB::table('customerinvoice')
                        ->where('invoicenumber', $detail->invoicenumber)
                        ->update([
                            'pdcbalance' => DB::raw('COALESCE(pdcbalance, 0) - ' . $amountPaid),
                            'pdcindicator' => DB::raw('CASE WHEN COALESCE(pdcbalance, 0) - ' . $amountPaid . ' = 0 THEN 0 ELSE pdcindicator END'),
                            'pdcdate' => DB::raw('CASE WHEN COALESCE(pdcbalance, 0) - ' . $amountPaid . ' = 0 THEN NULL ELSE pdcdate END'),
                        ]);
                }
            }

            DB::table('bearcashcheckdetail')->where('transactionkey', $transactionkey)->delete();
            DB::table('beardetail')->where('transactionkey', $transactionkey)->delete();
            DB::table('bearheader')->where('transactionkey', $transactionkey)->delete();

            $deleted = true;
        });

        return redirect()
            ->route('account.transaction.ho-collection', ['date' => $request->input('date', now()->format('Y-m-d'))])
            ->with(
                $deleted ? 'success' : 'error',
                $deleted ? 'HO collection deleted.' : 'Record already in use.'
            );
    }

    public function hoCollectionRouteMeta(Request $request): JsonResponse
    {
        $payload = $request->validate([
            'routecode' => ['required', 'integer'],
        ]);

        $this->assertRouteAccess((int) $payload['routecode']);

        return response()->json($this->hoCollectionRouteMetaPayload((int) $payload['routecode']));
    }

    public function hoCollectionInvoices(Request $request): JsonResponse
    {
        $payload = $request->validate([
            'routecode' => ['required', 'integer'],
            'customercode' => ['required', 'integer'],
            'transactionkey' => ['nullable', 'integer'],
        ]);

        $rows = $this->hoCollectionInvoiceRows(
            (int) $payload['routecode'],
            (int) $payload['customercode'],
            $payload['transactionkey'] ? (int) $payload['transactionkey'] : null
        );

        $totals = $this->gcInvoiceTotals(collect($rows)->pluck('transactionkey')->all());

        return response()->json([
            'rows' => $rows,
            'totals' => $totals,
        ]);
    }

    public function openingBalance(Request $request): Response
    {
        $scope = app(AccessScopeService::class);
        $user = $request->user();
        $date = $this->selectedDate($request);
        $customerInvoiceAlias = DB::getTablePrefix() . 'ci';
        $routeAlias = DB::getTablePrefix() . 'rm';
        $salesmanAlias = DB::getTablePrefix() . 'sm';
        $customerAlias = DB::getTablePrefix() . 'cm';

        $rows = DB::table('customerinvoice as ci')
            ->leftJoin('routemaster as rm', 'rm.routecode', '=', 'ci.routecode')
            ->leftJoin('salesman as sm', 'sm.salesmancode', '=', 'ci.salesmancode')
            ->leftJoin('customermaster as cm', 'cm.customercode', '=', 'ci.customercode')
            ->selectRaw("
                {$customerInvoiceAlias}.transactionkey,
                {$customerInvoiceAlias}.documentnumber,
                {$customerInvoiceAlias}.invoicenumber,
                {$customerInvoiceAlias}.transactiondate,
                {$customerInvoiceAlias}.routecode,
                COALESCE({$routeAlias}.routename, {$routeAlias}.arbroutename, '') as routename,
                {$customerInvoiceAlias}.salesmancode,
                COALESCE({$salesmanAlias}.salesmanname1, {$salesmanAlias}.arbsalesmanname1, '') as salesmanname,
                {$customerInvoiceAlias}.customercode,
                COALESCE({$customerAlias}.customername, {$customerAlias}.arbcustomername, '') as customername,
                {$customerInvoiceAlias}.totalinvoiceamount,
                {$customerInvoiceAlias}.invoicebalance,
                {$customerInvoiceAlias}.erpreferencenumber
            ")
            ->where('ci.transactiontype', 1)
            ->whereDate('ci.transactiondate', $date->toDateString())
            ->tap(fn ($query) => $scope->scopeQuery($user, $query, 'route', 'ci.routecode'))
            ->orderByDesc('ci.transactionkey')
            ->get()
            ->map(fn ($row) => [
                'transactionkey' => (int) $row->transactionkey,
                'documentnumber' => $row->documentnumber,
                'invoicenumber' => $row->invoicenumber,
                'transactiondate' => $row->transactiondate,
                'routecode' => (int) $row->routecode,
                'routename' => $row->routename,
                'salesmancode' => (int) $row->salesmancode,
                'salesmanname' => $row->salesmanname,
                'customercode' => (int) $row->customercode,
                'customername' => $row->customername,
                'totalinvoiceamount' => (float) $row->totalinvoiceamount,
                'invoicebalance' => (float) $row->invoicebalance,
                'erpreferencenumber' => $row->erpreferencenumber,
            ])
            ->values();

        return Inertia::render('account/transaction/opening-balance/Index', [
            'filters' => ['date' => $date->format('Y-m-d')],
            'rows' => $rows,
        ]);
    }

    public function debitNoteCustomer(Request $request): Response
    {
        $scope = app(AccessScopeService::class);
        $user = $request->user();
        $date = $this->selectedDate($request);
        $dcarAlias = DB::getTablePrefix() . 'dcar';
        $routeAlias = DB::getTablePrefix() . 'rm';
        $salesmanAlias = DB::getTablePrefix() . 'sm';
        $customerAlias = DB::getTablePrefix() . 'cm';

        $rows = DB::table('dcarheader as dcar')
            ->leftJoin('routemaster as rm', 'rm.routecode', '=', 'dcar.routecode')
            ->leftJoin('salesman as sm', 'sm.salesmancode', '=', 'dcar.salesmancode')
            ->leftJoin('customermaster as cm', 'cm.customercode', '=', 'dcar.customercode')
            ->selectRaw("
                {$dcarAlias}.transactionkey,
                {$dcarAlias}.documentnumber,
                {$dcarAlias}.invoicenumber,
                {$dcarAlias}.transactiondate,
                {$dcarAlias}.routecode,
                COALESCE({$routeAlias}.routename, {$routeAlias}.arbroutename, '') as routename,
                {$dcarAlias}.salesmancode,
                COALESCE({$salesmanAlias}.salesmanname1, {$salesmanAlias}.arbsalesmanname1, '') as salesmanname,
                {$dcarAlias}.customercode,
                COALESCE({$customerAlias}.customername, {$customerAlias}.arbcustomername, '') as customername,
                {$dcarAlias}.amountpaid,
                {$dcarAlias}.erpreferencenumber
            ")
            ->where('dcar.transactiontype', 3)
            ->where('dcar.customercode', '!=', 0)
            ->whereDate('dcar.transactiondate', $date->toDateString())
            ->tap(fn ($query) => $scope->scopeQuery($user, $query, 'route', 'dcar.routecode'))
            ->orderByDesc('dcar.transactionkey')
            ->get()
            ->map(fn ($row) => [
                'transactionkey' => (int) $row->transactionkey,
                'documentnumber' => $row->documentnumber,
                'invoicenumber' => $row->invoicenumber,
                'transactiondate' => $row->transactiondate,
                'routecode' => (int) $row->routecode,
                'routename' => $row->routename,
                'salesmancode' => (int) $row->salesmancode,
                'salesmanname' => $row->salesmanname,
                'customercode' => (int) $row->customercode,
                'customername' => $row->customername,
                'amountpaid' => (float) $row->amountpaid,
                'erpreferencenumber' => $row->erpreferencenumber,
            ])
            ->values();

        return Inertia::render('account/transaction/debit-note/customer/Index', [
            'filters' => ['date' => $date->format('Y-m-d')],
            'rows' => $rows,
        ]);
    }

    public function createDebitNoteCustomer(Request $request): Response
    {
        $date = $this->selectedDate($request);

        return Inertia::render('account/transaction/debit-note/customer/Form', [
            'mode' => 'create',
            'filters' => ['date' => $date->format('Y-m-d')],
            'routeOptions' => $this->openingBalanceRouteOptions(),
            'debitNoteCustomerData' => [
                'transactionkey' => null,
                'documentnumber' => '',
                'invoicenumber' => '',
                'routecode' => '',
                'routeLabel' => '',
                'salesmancode' => '',
                'salesmanname' => '',
                'customercode' => '',
                'customerLabel' => '',
                'sourceinvoice' => '',
                'sourceInvoiceLabel' => '',
                'invoiceamount' => '',
                'invoicebalance' => '',
                'amount' => '',
                'remarks1' => '',
                'remarks2' => '',
                'erpreferencenumber' => '',
            ],
            'initialMeta' => [
                'salesmancode' => '',
                'salesmanname' => '',
                'documentnumber' => '',
                'invoicenumber' => '',
                'customerOptions' => [],
                'invoiceOptions' => [],
            ],
        ]);
    }

    public function storeDebitNoteCustomer(Request $request): RedirectResponse
    {
        $payload = $request->validate([
            'date' => ['required', 'date'],
            'routecode' => ['required', 'integer'],
            'salesmancode' => ['required', 'integer'],
            'customercode' => ['required', 'integer'],
            'sourceinvoice' => ['required', 'integer'],
            'amount' => ['required', 'numeric', 'not_in:0'],
            'remarks1' => ['nullable', 'string', 'max:255'],
            'remarks2' => ['nullable', 'string', 'max:255'],
            'erpreferencenumber' => ['nullable', 'string', 'max:30'],
        ]);

        $this->assertRouteAccess((int) $payload['routecode']);

        DB::statement('CALL sp_add_account_notes_adddebitnotecustomer(?,?,?,?,?,?,?,?,?,?,?)', [
            (int) $payload['routecode'],
            (int) $payload['salesmancode'],
            (int) $payload['customercode'],
            2,
            (float) $payload['amount'],
            $payload['remarks1'] ?? '',
            $payload['remarks2'] ?? '',
            (int) $payload['sourceinvoice'],
            Carbon::parse($payload['date'])->format('d-m-Y'),
            $request->user()->username,
            $payload['erpreferencenumber'] ?? '',
        ]);

        return redirect()
            ->route('account.transaction.debit-note.customer', ['date' => $payload['date']])
            ->with('success', 'Debit note customer saved.');
    }

    public function showDebitNoteCustomer(Request $request, int $transactionkey): Response
    {
        $dcarAlias = DB::getTablePrefix() . 'dcar';
        $detailAlias = DB::getTablePrefix() . 'detail';
        $routeAlias = DB::getTablePrefix() . 'rm';
        $salesmanAlias = DB::getTablePrefix() . 'sm';
        $customerAlias = DB::getTablePrefix() . 'cm';

        $record = DB::table('dcarheader as dcar')
            ->leftJoin('dcardetail as detail', 'detail.transactionkey', '=', 'dcar.transactionkey')
            ->leftJoin('routemaster as rm', 'rm.routecode', '=', 'dcar.routecode')
            ->leftJoin('salesman as sm', 'sm.salesmancode', '=', 'dcar.salesmancode')
            ->leftJoin('customermaster as cm', 'cm.customercode', '=', 'dcar.customercode')
            ->selectRaw("
                {$dcarAlias}.transactionkey,
                {$dcarAlias}.documentnumber,
                {$dcarAlias}.invoicenumber,
                {$dcarAlias}.transactiondate,
                {$dcarAlias}.routecode,
                COALESCE({$routeAlias}.routename, {$routeAlias}.arbroutename, '') as routename,
                {$dcarAlias}.salesmancode,
                COALESCE({$salesmanAlias}.salesmanname1, {$salesmanAlias}.arbsalesmanname1, '') as salesmanname,
                {$dcarAlias}.customercode,
                COALESCE({$customerAlias}.customername, {$customerAlias}.arbcustomername, '') as customername,
                {$dcarAlias}.amountpaid,
                {$dcarAlias}.remarks1,
                {$dcarAlias}.remarks2,
                {$dcarAlias}.erpreferencenumber,
                {$detailAlias}.invoicenumber as sourceinvoice
            ")
            ->where('dcar.transactiontype', 3)
            ->where('dcar.customercode', '!=', 0)
            ->where('dcar.transactionkey', $transactionkey)
            ->first();

        abort_unless($record, 404);
        $this->assertRouteAccess((int) $record->routecode);

        $date = $request->input('date')
            ? Carbon::parse($request->input('date'))->format('Y-m-d')
            : Carbon::parse($record->transactiondate)->format('Y-m-d');

        $meta = $this->debitNoteCustomerRouteMetaPayload((int) $record->routecode);
        $invoiceOptions = $this->debitNoteCustomerInvoiceOptions((int) $record->customercode, $date);
        $invoiceDetail = $record->sourceinvoice ? $this->debitNoteInvoiceDetailPayload((int) $record->sourceinvoice) : [
            'totalinvoiceamount' => 0,
            'invoicebalance' => 0,
        ];

        if (! collect($meta['customerOptions'])->contains(fn ($option) => (int) $option['id'] === (int) $record->customercode)) {
            $meta['customerOptions'][] = [
                'id' => (int) $record->customercode,
                'label' => trim($record->customercode . ' - ' . ($record->customername ?: '')),
            ];
        }

        if (! collect($invoiceOptions)->contains(fn ($option) => (int) $option['id'] === (int) $record->sourceinvoice)) {
            $invoiceOptions[] = [
                'id' => (int) $record->sourceinvoice,
                'label' => (string) $record->sourceinvoice,
            ];
        }

        $meta['invoiceOptions'] = $invoiceOptions;

        return Inertia::render('account/transaction/debit-note/customer/Form', [
            'mode' => 'view',
            'filters' => ['date' => $date],
            'routeOptions' => $this->openingBalanceRouteOptions(),
            'debitNoteCustomerData' => [
                'transactionkey' => (int) $record->transactionkey,
                'documentnumber' => $record->documentnumber,
                'invoicenumber' => $record->invoicenumber,
                'routecode' => (int) $record->routecode,
                'routeLabel' => trim($record->routecode . ' - ' . ($record->routename ?: '')),
                'salesmancode' => (int) $record->salesmancode,
                'salesmanname' => $record->salesmanname,
                'customercode' => (int) $record->customercode,
                'customerLabel' => trim($record->customercode . ' - ' . ($record->customername ?: '')),
                'sourceinvoice' => (int) $record->sourceinvoice,
                'sourceInvoiceLabel' => (string) $record->sourceinvoice,
                'invoiceamount' => (float) $invoiceDetail['totalinvoiceamount'],
                'invoicebalance' => (float) $invoiceDetail['invoicebalance'],
                'amount' => (float) $record->amountpaid,
                'remarks1' => $record->remarks1,
                'remarks2' => $record->remarks2,
                'erpreferencenumber' => $record->erpreferencenumber,
            ],
            'initialMeta' => $meta,
        ]);
    }

    public function destroyDebitNoteCustomer(Request $request, int $transactionkey): RedirectResponse
    {
        $resultSets = $this->callProcedure('CALL sp_delete_account_note_debitnotecustomer(?,?)', [
            $transactionkey,
            $request->user()->username,
        ]);

        $result = $resultSets[0][0]['result'] ?? null;
        $date = $request->input('date') ?: now()->format('Y-m-d');

        return redirect()
            ->route('account.transaction.debit-note.customer', ['date' => $date])
            ->with(
                $result === 'Not Found' ? 'error' : 'success',
                $result === 'Not Found' ? 'Record already in use.' : 'Debit note customer deleted.'
            );
    }

    public function debitNoteCustomerRouteMeta(Request $request): JsonResponse
    {
        $payload = $request->validate([
            'routecode' => ['required', 'integer'],
        ]);

        $this->assertRouteAccess((int) $payload['routecode']);

        return response()->json($this->debitNoteCustomerRouteMetaPayload((int) $payload['routecode']));
    }

    public function debitNoteCustomerInvoices(Request $request): JsonResponse
    {
        $payload = $request->validate([
            'customercode' => ['required', 'integer'],
            'date' => ['required', 'date'],
        ]);

        return response()->json([
            'invoiceOptions' => $this->debitNoteCustomerInvoiceOptions(
                (int) $payload['customercode'],
                Carbon::parse($payload['date'])->format('Y-m-d')
            ),
        ]);
    }

    public function debitNoteCustomerInvoiceDetail(Request $request): JsonResponse
    {
        $payload = $request->validate([
            'invoiceid' => ['required', 'integer'],
        ]);

        return response()->json($this->debitNoteInvoiceDetailPayload((int) $payload['invoiceid']));
    }

    public function debitNoteRoute(Request $request): Response
    {
        $scope = app(AccessScopeService::class);
        $user = $request->user();
        $date = $this->selectedDate($request);
        $dcarAlias = DB::getTablePrefix() . 'dcar';
        $routeAlias = DB::getTablePrefix() . 'rm';
        $salesmanAlias = DB::getTablePrefix() . 'sm';

        $rows = DB::table('dcarheader as dcar')
            ->leftJoin('routemaster as rm', 'rm.routecode', '=', 'dcar.routecode')
            ->leftJoin('salesman as sm', 'sm.salesmancode', '=', 'dcar.salesmancode')
            ->selectRaw("
                {$dcarAlias}.transactionkey,
                {$dcarAlias}.documentnumber,
                {$dcarAlias}.invoicenumber,
                {$dcarAlias}.transactiondate,
                {$dcarAlias}.routecode,
                COALESCE({$routeAlias}.routename, {$routeAlias}.arbroutename, '') as routename,
                {$dcarAlias}.salesmancode,
                COALESCE({$salesmanAlias}.salesmanname1, {$salesmanAlias}.arbsalesmanname1, '') as salesmanname,
                {$dcarAlias}.amountpaid,
                {$dcarAlias}.erpreferencenumber
            ")
            ->where('dcar.transactiontype', 3)
            ->where('dcar.customercode', 0)
            ->whereDate('dcar.transactiondate', $date->toDateString())
            ->tap(fn ($query) => $scope->scopeQuery($user, $query, 'route', 'dcar.routecode'))
            ->orderByDesc('dcar.transactionkey')
            ->get()
            ->map(fn ($row) => [
                'transactionkey' => (int) $row->transactionkey,
                'documentnumber' => $row->documentnumber,
                'invoicenumber' => $row->invoicenumber,
                'transactiondate' => $row->transactiondate,
                'routecode' => (int) $row->routecode,
                'routename' => $row->routename,
                'salesmancode' => (int) $row->salesmancode,
                'salesmanname' => $row->salesmanname,
                'amountpaid' => (float) $row->amountpaid,
                'erpreferencenumber' => $row->erpreferencenumber,
            ])
            ->values();

        return Inertia::render('account/transaction/debit-note/route/Index', [
            'filters' => ['date' => $date->format('Y-m-d')],
            'rows' => $rows,
        ]);
    }

    public function createDebitNoteRoute(Request $request): Response
    {
        $date = $this->selectedDate($request);

        return Inertia::render('account/transaction/debit-note/route/Form', [
            'mode' => 'create',
            'filters' => ['date' => $date->format('Y-m-d')],
            'routeOptions' => $this->openingBalanceRouteOptions(),
            'debitNoteRouteData' => [
                'transactionkey' => null,
                'documentnumber' => '',
                'invoicenumber' => '',
                'routecode' => '',
                'routeLabel' => '',
                'salesmancode' => '',
                'salesmanname' => '',
                'amount' => '',
                'remarks1' => '',
                'remarks2' => '',
                'erpreferencenumber' => '',
            ],
            'initialMeta' => [
                'salesmancode' => '',
                'salesmanname' => '',
                'documentnumber' => '',
                'invoicenumber' => '',
            ],
        ]);
    }

    public function storeDebitNoteRoute(Request $request): RedirectResponse
    {
        $payload = $request->validate([
            'date' => ['required', 'date'],
            'routecode' => ['required', 'integer'],
            'salesmancode' => ['required', 'integer'],
            'amount' => ['required', 'numeric', 'not_in:0'],
            'remarks1' => ['nullable', 'string', 'max:255'],
            'remarks2' => ['nullable', 'string', 'max:255'],
            'erpreferencenumber' => ['nullable', 'string', 'max:30'],
        ]);

        $this->assertRouteAccess((int) $payload['routecode']);

        DB::statement('CALL sp_add_account_notes_adddebitnoteroute(?,?,?,?,?,?,?,?,?)', [
            (int) $payload['routecode'],
            (int) $payload['salesmancode'],
            2,
            (float) $payload['amount'],
            $payload['remarks1'] ?? '',
            $payload['remarks2'] ?? '',
            Carbon::parse($payload['date'])->format('d-m-Y'),
            $request->user()->username,
            $payload['erpreferencenumber'] ?? '',
        ]);

        return redirect()
            ->route('account.transaction.debit-note.route', ['date' => $payload['date']])
            ->with('success', 'Debit note route saved.');
    }

    public function showDebitNoteRoute(Request $request, int $transactionkey): Response
    {
        $dcarAlias = DB::getTablePrefix() . 'dcar';
        $routeAlias = DB::getTablePrefix() . 'rm';
        $salesmanAlias = DB::getTablePrefix() . 'sm';

        $record = DB::table('dcarheader as dcar')
            ->leftJoin('routemaster as rm', 'rm.routecode', '=', 'dcar.routecode')
            ->leftJoin('salesman as sm', 'sm.salesmancode', '=', 'dcar.salesmancode')
            ->selectRaw("
                {$dcarAlias}.transactionkey,
                {$dcarAlias}.documentnumber,
                {$dcarAlias}.invoicenumber,
                {$dcarAlias}.transactiondate,
                {$dcarAlias}.routecode,
                COALESCE({$routeAlias}.routename, {$routeAlias}.arbroutename, '') as routename,
                {$dcarAlias}.salesmancode,
                COALESCE({$salesmanAlias}.salesmanname1, {$salesmanAlias}.arbsalesmanname1, '') as salesmanname,
                {$dcarAlias}.amountpaid,
                {$dcarAlias}.remarks1,
                {$dcarAlias}.remarks2,
                {$dcarAlias}.erpreferencenumber
            ")
            ->where('dcar.transactiontype', 3)
            ->where('dcar.customercode', 0)
            ->where('dcar.transactionkey', $transactionkey)
            ->first();

        abort_unless($record, 404);
        $this->assertRouteAccess((int) $record->routecode);

        $date = $request->input('date')
            ? Carbon::parse($request->input('date'))->format('Y-m-d')
            : Carbon::parse($record->transactiondate)->format('Y-m-d');

        $meta = $this->debitNoteRouteMetaPayload((int) $record->routecode);

        return Inertia::render('account/transaction/debit-note/route/Form', [
            'mode' => 'view',
            'filters' => ['date' => $date],
            'routeOptions' => $this->openingBalanceRouteOptions(),
            'debitNoteRouteData' => [
                'transactionkey' => (int) $record->transactionkey,
                'documentnumber' => $record->documentnumber,
                'invoicenumber' => $record->invoicenumber,
                'routecode' => (int) $record->routecode,
                'routeLabel' => trim($record->routecode . ' - ' . ($record->routename ?: '')),
                'salesmancode' => (int) $record->salesmancode,
                'salesmanname' => $record->salesmanname,
                'amount' => (float) $record->amountpaid,
                'remarks1' => $record->remarks1,
                'remarks2' => $record->remarks2,
                'erpreferencenumber' => $record->erpreferencenumber,
            ],
            'initialMeta' => $meta,
        ]);
    }

    public function destroyDebitNoteRoute(Request $request, int $transactionkey): RedirectResponse
    {
        $resultSets = $this->callProcedure('CALL sp_delete_account_note_debitnoteroute(?,?)', [
            $transactionkey,
            $request->user()->username,
        ]);

        $result = $resultSets[0][0]['result'] ?? null;
        $date = $request->input('date') ?: now()->format('Y-m-d');

        return redirect()
            ->route('account.transaction.debit-note.route', ['date' => $date])
            ->with(
                $result === 'Not Found' ? 'error' : 'success',
                $result === 'Not Found' ? 'Record already in use.' : 'Debit note route deleted.'
            );
    }

    public function debitNoteRouteMeta(Request $request): JsonResponse
    {
        $payload = $request->validate([
            'routecode' => ['required', 'integer'],
        ]);

        $this->assertRouteAccess((int) $payload['routecode']);

        return response()->json($this->debitNoteRouteMetaPayload((int) $payload['routecode']));
    }

    public function creditNoteCustomer(Request $request): Response
    {
        $scope = app(AccessScopeService::class);
        $user = $request->user();
        $date = $this->selectedDate($request);
        $dcarAlias = DB::getTablePrefix() . 'dcar';
        $routeAlias = DB::getTablePrefix() . 'rm';
        $customerAlias = DB::getTablePrefix() . 'cm';

        $rows = DB::table('dcarheader as dcar')
            ->leftJoin('routemaster as rm', 'rm.routecode', '=', 'dcar.routecode')
            ->leftJoin('customermaster as cm', 'cm.customercode', '=', 'dcar.customercode')
            ->selectRaw("
                {$dcarAlias}.transactionkey,
                {$dcarAlias}.documentnumber,
                {$dcarAlias}.transactiondate,
                {$dcarAlias}.routecode,
                COALESCE({$routeAlias}.routename, {$routeAlias}.arbroutename, '') as routename,
                {$dcarAlias}.customercode,
                COALESCE({$customerAlias}.customername, {$customerAlias}.arbcustomername, '') as customername,
                {$dcarAlias}.totalinvoiceamount,
                {$dcarAlias}.amountpaid,
                {$dcarAlias}.erpreferencenumber
            ")
            ->where('dcar.transactiontype', 4)
            ->where('dcar.customercode', '!=', 0)
            ->whereDate('dcar.transactiondate', $date->toDateString())
            ->tap(fn ($query) => $scope->scopeQuery($user, $query, 'route', 'dcar.routecode'))
            ->orderByDesc('dcar.transactionkey')
            ->get()
            ->map(fn ($row) => [
                'transactionkey' => (int) $row->transactionkey,
                'documentnumber' => $row->documentnumber,
                'transactiondate' => $row->transactiondate,
                'routecode' => (int) $row->routecode,
                'routename' => $row->routename,
                'customercode' => (int) $row->customercode,
                'customername' => $row->customername,
                'totalinvoiceamount' => (float) $row->totalinvoiceamount,
                'amountpaid' => (float) $row->amountpaid,
                'erpreferencenumber' => $row->erpreferencenumber,
            ])
            ->values();

        return Inertia::render('account/transaction/credit-note/customer/Index', [
            'filters' => ['date' => $date->format('Y-m-d')],
            'rows' => $rows,
        ]);
    }

    public function createCreditNoteCustomer(Request $request): Response
    {
        $date = $this->selectedDate($request);
        [$routeOptions, $bankOptions] = $this->creditNoteCustomerBaseOptions();

        return Inertia::render('account/transaction/credit-note/customer/Form', [
            'mode' => 'create',
            'filters' => ['date' => $date->format('Y-m-d')],
            'routeOptions' => $routeOptions,
            'bankOptions' => $bankOptions,
            'creditNoteCustomerData' => [
                'transactionkey' => null,
                'documentnumber' => '',
                'routecode' => '',
                'routeLabel' => '',
                'salesmancode' => '',
                'salesmanname' => '',
                'customercode' => '',
                'customerLabel' => '',
                'paymentmode' => 0,
                'amount' => '',
                'invoiceamount' => 0,
                'balanceamount' => 0,
                'remarks1' => '',
                'remarks2' => '',
                'checknumber' => '',
                'checkdate' => '',
                'bankcode' => '',
                'erpreferencenumber' => '',
                'firstoutstanding' => false,
            ],
            'initialMeta' => [
                'salesmancode' => '',
                'salesmanname' => '',
                'documentnumber' => '',
                'invoicenumber' => '',
                'customerOptions' => [],
            ],
            'invoiceRows' => [],
            'paymentDetails' => null,
        ]);
    }

    public function storeCreditNoteCustomer(Request $request): RedirectResponse
    {
        $payload = $request->validate([
            'date' => ['required', 'date'],
            'routecode' => ['required', 'integer'],
            'salesmancode' => ['required', 'integer'],
            'customercode' => ['required', 'integer'],
            'paymentmode' => ['required', 'integer', 'in:0,1'],
            'amount' => ['required', 'numeric', 'not_in:0'],
            'invoice_total' => ['nullable', 'numeric'],
            'balance_total' => ['nullable', 'numeric'],
            'remarks1' => ['nullable', 'string', 'max:255'],
            'remarks2' => ['nullable', 'string', 'max:255'],
            'checknumber' => ['nullable', 'string', 'max:30'],
            'checkdate' => ['nullable', 'date'],
            'bankcode' => ['nullable', 'integer'],
            'erpreferencenumber' => ['nullable', 'string', 'max:30'],
            'firstoutstanding' => ['nullable', 'boolean'],
            'invoice_ids' => ['required', 'array', 'min:1'],
            'invoice_ids.*' => ['integer'],
            'invoice_amounts' => ['required', 'array', 'min:1'],
            'invoice_amounts.*' => ['numeric'],
        ]);

        DB::transaction(function () use ($payload, $request) {
            $route = DB::table('routemaster')
                ->select('routecode', 'acbodocseq', 'amountdecimaldigits')
                ->where('routecode', (int) $payload['routecode'])
                ->lockForUpdate()
                ->first();

            abort_unless($route, 404);

            $customer = DB::table('customermaster')
                ->select('customercode', 'headofficecode', 'type')
                ->where('customercode', (int) $payload['customercode'])
                ->lockForUpdate()
                ->first();

            abort_unless($customer, 404);

            $invoiceRows = DB::table('customerinvoice')
                ->select('transactionkey', 'invoicenumber', 'totalinvoiceamount', 'amountpaid', 'invoicebalance')
                ->whereIn('transactionkey', array_map('intval', $payload['invoice_ids']))
                ->lockForUpdate()
                ->get()
                ->keyBy(fn ($row) => (int) $row->transactionkey);

            $documentNumber = $this->nextOpeningBalanceDocumentNumber((int) $payload['routecode']);
            $amount = abs((float) $payload['amount']);
            $transactionDate = Carbon::parse($payload['date'])->toDateString();
            $checkDate = !empty($payload['checkdate']) ? Carbon::parse($payload['checkdate'])->toDateString() : null;
            $currencyCode = (int) ($route->amountdecimaldigits ?? 0);
            $pdcAsCash = $this->pdcAsCashEnabled();
            $aggregateTotalInvoice = (float) $invoiceRows->sum(fn ($row) => (float) $row->totalinvoiceamount);

            $headerId = DB::table('dcarheader')->insertGetId([
                'documentnumber' => $documentNumber,
                'invoicenumber' => $documentNumber,
                'routecode' => (int) $payload['routecode'],
                'salesmancode' => (int) $payload['salesmancode'],
                'customercode' => (int) $payload['customercode'],
                'transactiontype' => 4,
                'paymenttype' => 4,
                'amountpaid' => $amount,
                'totalinvoiceamount' => $aggregateTotalInvoice,
                'invoicebalance' => $aggregateTotalInvoice - $amount,
                'transactiondate' => $transactionDate,
                'transactiontime' => now()->format('H:i:s'),
                'remarks1' => $payload['remarks1'] ?? '',
                'remarks2' => $payload['remarks2'] ?? '',
                'erpreferencenumber' => $payload['erpreferencenumber'] ?? '',
                'created' => $request->user()->username,
                'modified' => $request->user()->username,
                'cdat' => now()->toDateString(),
                'mdat' => now(),
                'currencycode' => $currencyCode,
                'pdcstatus' => (int) $payload['paymentmode'],
                'voidflag' => 0,
            ]);

            DB::table('routemaster')
                ->where('routecode', (int) $payload['routecode'])
                ->update([
                    'acbodocseq' => DB::raw('COALESCE(acbodocseq, 0) + 1'),
                ]);

            DB::table('dcarcashcheckdetail')->insert([
                'transactionkey' => $headerId,
                'typecode' => (int) $payload['paymentmode'],
                'checknumber' => (int) $payload['paymentmode'] === 1 ? ($payload['checknumber'] ?: null) : null,
                'checkdate' => (int) $payload['paymentmode'] === 1 ? $checkDate : null,
                'bankcode' => (int) $payload['paymentmode'] === 1 ? ($payload['bankcode'] ?: null) : null,
                'amount' => $amount,
                'currencycode' => $currencyCode,
            ]);

            foreach (array_values(array_map('intval', $payload['invoice_ids'])) as $index => $invoiceTransactionKey) {
                $invoice = $invoiceRows->get($invoiceTransactionKey);

                if (! $invoice) {
                    continue;
                }

                $invoiceBalance = (float) $invoice->invoicebalance;
                if (! empty($payload['firstoutstanding']) && $invoiceBalance < 0) {
                    $requestedAmount = abs($invoiceBalance);
                } elseif (! empty($payload['firstoutstanding']) && $index > 0) {
                    $requestedAmount = $amount;
                } else {
                    $requestedAmount = (float) ($payload['invoice_amounts'][$index] ?? 0);
                }

                $applicableAmount = min(abs($requestedAmount), abs($invoiceBalance));
                $appliedAmount = $invoiceBalance < 0 ? -$applicableAmount : $applicableAmount;
                $detailBalance = $invoiceBalance - $appliedAmount;

                DB::table('dcardetail')->insert([
                    'transactionkey' => $headerId,
                    'customercode' => (int) $payload['customercode'],
                    'invoicenumber' => $invoice->invoicenumber,
                    'totalinvoiceamount' => (float) $invoice->totalinvoiceamount,
                    'amountpaid' => $appliedAmount,
                    'invoicebalance' => $detailBalance,
                    'paymentmode' => (int) $payload['paymentmode'],
                    'invoicedate' => $transactionDate,
                    'currencycode' => $currencyCode,
                ]);

                if ((int) $payload['paymentmode'] === 0 || $pdcAsCash) {
                    DB::table('customerinvoice')
                        ->where('transactionkey', $invoiceTransactionKey)
                        ->update([
                            'cnamountpaid' => DB::raw('COALESCE(cnamountpaid, 0) + ' . $appliedAmount),
                            'amountpaid' => DB::raw('COALESCE(amountpaid, 0) + ' . $appliedAmount),
                            'invoicebalance' => DB::raw('COALESCE(invoicebalance, 0) - ' . $appliedAmount),
                        ]);

                    $this->updateCustomerAndHeadOfficeBalance((int) $payload['customercode'], (float) $appliedAmount * -1, $customer);
                } else {
                    $pdcDateSql = $checkDate ? "'" . $checkDate . "'" : 'NULL';

                    DB::table('customerinvoice')
                        ->where('transactionkey', $invoiceTransactionKey)
                        ->update([
                            'pdcindicator' => 1,
                            'pdcbalance' => DB::raw('COALESCE(pdcbalance, 0) + ' . $appliedAmount),
                            'pdcdate' => DB::raw($pdcDateSql),
                        ]);
                }
            }
        });

        return redirect()
            ->route('account.transaction.credit-note.customer', ['date' => $payload['date']])
            ->with('success', 'Credit note customer saved.');
    }

    public function showCreditNoteCustomer(Request $request, int $transactionkey): Response
    {
        [$routeOptions, $bankOptions] = $this->creditNoteCustomerBaseOptions();
        $header = DB::table('dcarheader')
            ->where('transactiontype', 4)
            ->where('customercode', '!=', 0)
            ->where('transactionkey', $transactionkey)
            ->first();

        abort_unless($header, 404);
        $this->assertRouteAccess((int) ($header->routecode ?? 0));
        $header = (array) $header;

        $date = $request->input('date')
            ? Carbon::parse($request->input('date'))->format('Y-m-d')
            : Carbon::parse($header['transactiondate'])->format('Y-m-d');

        $invoiceRows = $this->creditNoteCustomerInvoiceRows((int) $header['routecode'], (int) $header['customercode'], $transactionkey);
        $invoiceTotals = $this->gcInvoiceTotals(collect($invoiceRows)->pluck('transactionkey')->all());
        $allocatedTotal = collect($invoiceRows)->sum(fn (array $row) => (float) ($row['allocatedamount'] ?? 0));
        $payment = DB::table('dcarcashcheckdetail as dcd')
            ->leftJoin('bankmaster as bm', 'bm.bankcode', '=', 'dcd.bankcode')
            ->selectRaw('
                dcd.typecode,
                dcd.amount,
                dcd.checknumber,
                dcd.checkdate,
                dcd.bankcode,
                COALESCE(bm.bankname, "") as bankname
            ')
            ->where('dcd.transactionkey', $transactionkey)
            ->orderBy('dcd.primary_key')
            ->first();
        $payment = $payment ? (array) $payment : [];

        return Inertia::render('account/transaction/credit-note/customer/Form', [
            'mode' => 'view',
            'filters' => ['date' => $date],
            'routeOptions' => $routeOptions,
            'bankOptions' => $bankOptions,
            'creditNoteCustomerData' => [
                'transactionkey' => (int) $header['transactionkey'],
                'documentnumber' => $header['documentnumber'] ?? '',
                'routecode' => (int) ($header['routecode'] ?? 0),
                'routeLabel' => trim(($header['routecode'] ?? '') . ' - ' . ($this->routeName((int) ($header['routecode'] ?? 0)) ?? '')),
                'salesmancode' => (int) ($header['salesmancode'] ?? 0),
                'salesmanname' => $this->salesmanName((int) ($header['salesmancode'] ?? 0)),
                'customercode' => (int) ($header['customercode'] ?? 0),
                'customerLabel' => trim(($header['customercode'] ?? '') . ' - ' . ($this->customerName((int) ($header['customercode'] ?? 0)) ?? '')),
                'paymentmode' => (int) ($payment['typecode'] ?? 0),
                'amount' => (float) ($header['amountpaid'] ?? 0),
                'invoiceamount' => (float) $invoiceTotals['invoice_total'],
                'balanceamount' => (float) $allocatedTotal,
                'remarks1' => $header['remarks1'] ?? '',
                'remarks2' => $header['remarks2'] ?? '',
                'checknumber' => $payment['checknumber'] ?? '',
                'checkdate' => filled($payment['checkdate'] ?? null) ? Carbon::parse($payment['checkdate'])->format('Y-m-d') : '',
                'bankcode' => isset($payment['bankcode']) ? (int) $payment['bankcode'] : $this->bankCodeFromName($payment['bankname'] ?? null, $bankOptions),
                'erpreferencenumber' => $header['erpreferencenumber'] ?? '',
                'firstoutstanding' => false,
            ],
            'initialMeta' => [
                'salesmancode' => (int) ($header['salesmancode'] ?? 0),
                'salesmanname' => $this->salesmanName((int) ($header['salesmancode'] ?? 0)) ?? '',
                'documentnumber' => $header['documentnumber'] ?? '',
                'invoicenumber' => $header['invoicenumber'] ?? '',
                'customerOptions' => [[
                    'id' => (int) ($header['customercode'] ?? 0),
                    'label' => trim(($header['customercode'] ?? '') . ' - ' . ($this->customerName((int) ($header['customercode'] ?? 0)) ?? '')),
                ]],
            ],
            'invoiceRows' => $invoiceRows,
            'paymentDetails' => [
                'typecode' => (int) ($payment['typecode'] ?? 0),
                'amount' => (float) ($payment['amount'] ?? 0),
                'checknumber' => $payment['checknumber'] ?? '',
                'checkdate' => $payment['checkdate'] ?? '',
                'bankname' => $payment['bankname'] ?? '',
            ],
        ]);
    }

    public function destroyCreditNoteCustomer(Request $request, int $transactionkey): RedirectResponse
    {
        $deleted = false;

        DB::transaction(function () use ($transactionkey, &$deleted) {
            $header = DB::table('dcarheader')
                ->select('transactionkey', 'pdcstatus')
                ->where('transactionkey', $transactionkey)
                ->lockForUpdate()
                ->first();

            if (! $header || (int) $header->pdcstatus === 2) {
                return;
            }

            $this->assertRouteAccess((int) ($header->routecode ?? 0));

            $details = DB::table('dcardetail')
                ->select('customercode', 'invoicenumber', 'amountpaid', 'paymentmode')
                ->where('transactionkey', $transactionkey)
                ->lockForUpdate()
                ->get();

            $customers = DB::table('customermaster')
                ->select('customercode', 'headofficecode', 'type')
                ->whereIn('customercode', $details->pluck('customercode')->filter()->map(fn ($value) => (int) $value)->unique()->all())
                ->lockForUpdate()
                ->get()
                ->keyBy(fn ($row) => (int) $row->customercode);

            $pdcAsCash = $this->pdcAsCashEnabled();

            foreach ($details as $detail) {
                $amountPaid = (float) $detail->amountpaid;

                if ((int) $detail->paymentmode === 0 || $pdcAsCash) {
                    DB::table('customerinvoice')
                        ->where('invoicenumber', $detail->invoicenumber)
                        ->update([
                            'amountpaid' => DB::raw('COALESCE(amountpaid, 0) - ' . $amountPaid),
                            'cnamountpaid' => DB::raw('COALESCE(cnamountpaid, 0) - ' . $amountPaid),
                            'invoicebalance' => DB::raw('COALESCE(invoicebalance, 0) + ' . $amountPaid),
                        ]);

                    $customer = $customers->get((int) $detail->customercode);
                    $this->updateCustomerAndHeadOfficeBalance((int) $detail->customercode, $amountPaid, $customer);
                } else {
                    DB::table('customerinvoice')
                        ->where('invoicenumber', $detail->invoicenumber)
                        ->update([
                            'pdcbalance' => DB::raw('COALESCE(pdcbalance, 0) - ' . $amountPaid),
                            'pdcindicator' => DB::raw('CASE WHEN COALESCE(pdcbalance, 0) - ' . $amountPaid . ' = 0 THEN 0 ELSE pdcindicator END'),
                            'pdcdate' => DB::raw('CASE WHEN COALESCE(pdcbalance, 0) - ' . $amountPaid . ' = 0 THEN NULL ELSE pdcdate END'),
                        ]);
                }
            }

            DB::table('dcarcashcheckdetail')->where('transactionkey', $transactionkey)->delete();
            DB::table('dcardetail')->where('transactionkey', $transactionkey)->delete();
            DB::table('dcarheader')->where('transactionkey', $transactionkey)->delete();

            $deleted = true;
        });

        $date = $request->input('date') ?: now()->format('Y-m-d');

        return redirect()
            ->route('account.transaction.credit-note.customer', ['date' => $date])
            ->with(
                $deleted ? 'success' : 'error',
                $deleted ? 'Credit note customer deleted.' : 'Record already in use.'
            );
    }

    public function creditNoteCustomerRouteMeta(Request $request): JsonResponse
    {
        $payload = $request->validate([
            'routecode' => ['required', 'integer'],
        ]);

        return response()->json($this->creditNoteCustomerRouteMetaPayload((int) $payload['routecode']));
    }

    public function creditNoteCustomerInvoices(Request $request): JsonResponse
    {
        $payload = $request->validate([
            'routecode' => ['required', 'integer'],
            'customercode' => ['required', 'integer'],
        ]);

        $rows = $this->creditNoteCustomerInvoiceRows((int) $payload['routecode'], (int) $payload['customercode']);

        return response()->json([
            'rows' => $rows,
            'totals' => $this->gcInvoiceTotals(collect($rows)->pluck('transactionkey')->all()),
        ]);
    }

    public function creditNoteRoute(Request $request): Response
    {
        $scope = app(AccessScopeService::class);
        $user = $request->user();
        $date = $this->selectedDate($request);
        $dcarAlias = DB::getTablePrefix() . 'dcar';
        $routeAlias = DB::getTablePrefix() . 'rm';
        $salesmanAlias = DB::getTablePrefix() . 'sm';

        $rows = DB::table('dcarheader as dcar')
            ->leftJoin('routemaster as rm', 'rm.routecode', '=', 'dcar.routecode')
            ->leftJoin('salesman as sm', 'sm.salesmancode', '=', 'dcar.salesmancode')
            ->selectRaw("
                {$dcarAlias}.transactionkey,
                {$dcarAlias}.documentnumber,
                {$dcarAlias}.invoicenumber,
                {$dcarAlias}.transactiondate,
                {$dcarAlias}.routecode,
                COALESCE({$routeAlias}.routename, {$routeAlias}.arbroutename, '') as routename,
                {$dcarAlias}.salesmancode,
                COALESCE({$salesmanAlias}.salesmanname1, {$salesmanAlias}.arbsalesmanname1, '') as salesmanname,
                {$dcarAlias}.amountpaid,
                {$dcarAlias}.erpreferencenumber
            ")
            ->where('dcar.transactiontype', 4)
            ->where('dcar.customercode', 0)
            ->whereDate('dcar.transactiondate', $date->toDateString())
            ->tap(fn ($query) => $scope->scopeQuery($user, $query, 'route', 'dcar.routecode'))
            ->orderByDesc('dcar.transactionkey')
            ->get()
            ->map(fn ($row) => [
                'transactionkey' => (int) $row->transactionkey,
                'documentnumber' => $row->documentnumber,
                'invoicenumber' => $row->invoicenumber,
                'transactiondate' => $row->transactiondate,
                'routecode' => (int) $row->routecode,
                'routename' => $row->routename,
                'salesmancode' => (int) $row->salesmancode,
                'salesmanname' => $row->salesmanname,
                'amountpaid' => (float) $row->amountpaid,
                'erpreferencenumber' => $row->erpreferencenumber,
            ])
            ->values();

        return Inertia::render('account/transaction/credit-note/route/Index', [
            'filters' => ['date' => $date->format('Y-m-d')],
            'rows' => $rows,
        ]);
    }

    public function createCreditNoteRoute(Request $request): Response
    {
        $date = $this->selectedDate($request);
        [$routeOptions] = $this->creditNoteRouteBaseOptions();

        return Inertia::render('account/transaction/credit-note/route/Form', [
            'mode' => 'create',
            'filters' => ['date' => $date->format('Y-m-d')],
            'routeOptions' => $routeOptions,
            'creditNoteRouteData' => [
                'transactionkey' => null,
                'documentnumber' => '',
                'invoicenumber' => '',
                'routecode' => '',
                'routeLabel' => '',
                'salesmancode' => '',
                'salesmanname' => '',
                'amount' => '',
                'remarks1' => '',
                'remarks2' => '',
                'erpreferencenumber' => '',
            ],
            'initialMeta' => [
                'salesmancode' => '',
                'salesmanname' => '',
                'documentnumber' => '',
                'invoicenumber' => '',
            ],
        ]);
    }

    public function storeCreditNoteRoute(Request $request): RedirectResponse
    {
        $payload = $request->validate([
            'date' => ['required', 'date'],
            'routecode' => ['required', 'integer'],
            'salesmancode' => ['required', 'integer'],
            'amount' => ['required', 'numeric', 'not_in:0'],
            'remarks1' => ['nullable', 'string', 'max:255'],
            'remarks2' => ['nullable', 'string', 'max:255'],
            'erpreferencenumber' => ['nullable', 'string', 'max:30'],
        ]);

        $this->assertRouteAccess((int) $payload['routecode']);

        DB::transaction(function () use ($payload, $request) {
            $route = DB::table('routemaster')
                ->select('routecode', 'acbodocseq', 'amountdecimaldigits')
                ->where('routecode', (int) $payload['routecode'])
                ->lockForUpdate()
                ->first();

            abort_unless($route, 404);

            $documentNumber = $this->nextOpeningBalanceDocumentNumber((int) $payload['routecode']);
            $amount = (float) $payload['amount'];

            DB::table('dcarheader')->insert([
                'documentnumber' => $documentNumber,
                'invoicenumber' => $documentNumber,
                'currencycode' => (int) ($route->amountdecimaldigits ?? 0),
                'routecode' => (int) $payload['routecode'],
                'salesmancode' => (int) $payload['salesmancode'],
                'customercode' => 0,
                'transactiontype' => 4,
                'paymenttype' => 4,
                'totalinvoiceamount' => $amount,
                'amountpaid' => $amount,
                'invoicebalance' => 0,
                'transactiondate' => Carbon::parse($payload['date'])->toDateString(),
                'remarks1' => $payload['remarks1'] ?? '',
                'remarks2' => $payload['remarks2'] ?? '',
                'erpreferencenumber' => $payload['erpreferencenumber'] ?? '',
                'transactiontime' => now()->format('H:i:s'),
                'mdat' => now(),
                'cdat' => now()->toDateString(),
                'created' => $request->user()->username,
                'modified' => $request->user()->username,
                'voidflag' => 0,
            ]);

            DB::table('routemaster')
                ->where('routecode', (int) $payload['routecode'])
                ->update([
                    'routebalance' => DB::raw('COALESCE(routebalance, 0) - ' . $amount),
                    'acbodocseq' => DB::raw('COALESCE(acbodocseq, 0) + 1'),
                ]);
        });

        return redirect()
            ->route('account.transaction.credit-note.route', ['date' => $payload['date']])
            ->with('success', 'Credit note route saved.');
    }

    public function showCreditNoteRoute(Request $request, int $transactionkey): Response
    {
        $dcarAlias = DB::getTablePrefix() . 'dcar';
        $routeAlias = DB::getTablePrefix() . 'rm';
        $salesmanAlias = DB::getTablePrefix() . 'sm';

        $record = DB::table('dcarheader as dcar')
            ->leftJoin('routemaster as rm', 'rm.routecode', '=', 'dcar.routecode')
            ->leftJoin('salesman as sm', 'sm.salesmancode', '=', 'dcar.salesmancode')
            ->selectRaw("
                {$dcarAlias}.transactionkey,
                {$dcarAlias}.documentnumber,
                {$dcarAlias}.invoicenumber,
                {$dcarAlias}.transactiondate,
                {$dcarAlias}.routecode,
                COALESCE({$routeAlias}.routename, {$routeAlias}.arbroutename, '') as routename,
                {$dcarAlias}.salesmancode,
                COALESCE({$salesmanAlias}.salesmanname1, {$salesmanAlias}.arbsalesmanname1, '') as salesmanname,
                {$dcarAlias}.amountpaid,
                {$dcarAlias}.remarks1,
                {$dcarAlias}.remarks2,
                {$dcarAlias}.erpreferencenumber
            ")
            ->where('dcar.transactiontype', 4)
            ->where('dcar.customercode', 0)
            ->where('dcar.transactionkey', $transactionkey)
            ->first();

        abort_unless($record, 404);
        $this->assertRouteAccess((int) $record->routecode);

        $date = $request->input('date')
            ? Carbon::parse($request->input('date'))->format('Y-m-d')
            : Carbon::parse($record->transactiondate)->format('Y-m-d');

        $meta = $this->creditNoteRouteMetaPayload((int) $record->routecode);

        return Inertia::render('account/transaction/credit-note/route/Form', [
            'mode' => 'view',
            'filters' => ['date' => $date],
            'routeOptions' => $this->creditNoteRouteBaseOptions()[0],
            'creditNoteRouteData' => [
                'transactionkey' => (int) $record->transactionkey,
                'documentnumber' => $record->documentnumber,
                'invoicenumber' => $record->invoicenumber,
                'routecode' => (int) $record->routecode,
                'routeLabel' => trim($record->routecode . ' - ' . ($record->routename ?: '')),
                'salesmancode' => (int) $record->salesmancode,
                'salesmanname' => $record->salesmanname,
                'amount' => (float) $record->amountpaid,
                'remarks1' => $record->remarks1,
                'remarks2' => $record->remarks2,
                'erpreferencenumber' => $record->erpreferencenumber,
            ],
            'initialMeta' => $meta,
        ]);
    }

    public function destroyCreditNoteRoute(Request $request, int $transactionkey): RedirectResponse
    {
        $deleted = false;

        DB::transaction(function () use ($transactionkey, &$deleted) {
            $header = DB::table('dcarheader')
                ->select('transactionkey', 'routecode', 'amountpaid')
                ->where('transactionkey', $transactionkey)
                ->lockForUpdate()
                ->first();

            if (! $header) {
                return;
            }

            $this->assertRouteAccess((int) ($header->routecode ?? 0));

            DB::table('routemaster')
                ->where('routecode', (int) $header->routecode)
                ->update([
                    'routebalance' => DB::raw('COALESCE(routebalance, 0) + ' . (float) $header->amountpaid),
                ]);

            DB::table('dcarheader')->where('transactionkey', $transactionkey)->delete();

            $deleted = true;
        });

        $date = $request->input('date') ?: now()->format('Y-m-d');

        return redirect()
            ->route('account.transaction.credit-note.route', ['date' => $date])
            ->with(
                $deleted ? 'success' : 'error',
                $deleted ? 'Credit note route deleted.' : 'Record already in use.'
            );
    }

    public function creditNoteRouteMeta(Request $request): JsonResponse
    {
        $payload = $request->validate([
            'routecode' => ['required', 'integer'],
        ]);

        $this->assertRouteAccess((int) $payload['routecode']);

        return response()->json($this->creditNoteRouteMetaPayload((int) $payload['routecode']));
    }

    public function createOpeningBalance(Request $request): Response
    {
        $date = $this->selectedDate($request);

        return Inertia::render('account/transaction/opening-balance/Form', [
            'mode' => 'create',
            'filters' => ['date' => $date->format('Y-m-d')],
            'routeOptions' => $this->openingBalanceRouteOptions(),
            'openingBalanceData' => [
                'transactionkey' => null,
                'documentnumber' => '',
                'invoicenumber' => '',
                'routecode' => '',
                'routeLabel' => '',
                'salesmancode' => '',
                'salesmanname' => '',
                'customercode' => '',
                'customerLabel' => '',
                'amount' => '',
                'remarks1' => '',
                'remarks2' => '',
                'erpreferencenumber' => '',
            ],
            'initialMeta' => [
                'salesmancode' => '',
                'salesmanname' => '',
                'documentnumber' => '',
                'invoicenumber' => '',
                'customerOptions' => [],
            ],
        ]);
    }

    public function storeOpeningBalance(Request $request): RedirectResponse
    {
        $payload = $request->validate([
            'date' => ['required', 'date'],
            'routecode' => ['required', 'integer'],
            'salesmancode' => ['required', 'integer'],
            'customercode' => ['required', 'integer'],
            'amount' => ['required', 'numeric', 'not_in:0'],
            'remarks1' => ['nullable', 'string', 'max:255'],
            'remarks2' => ['nullable', 'string', 'max:255'],
            'erpreferencenumber' => ['nullable', 'string', 'max:30'],
        ]);

        $this->assertRouteAccess((int) $payload['routecode']);

        DB::transaction(function () use ($payload) {
            $route = DB::table('routemaster')
                ->select('routecode', 'acbodocseq', 'amountdecimaldigits')
                ->where('routecode', (int) $payload['routecode'])
                ->lockForUpdate()
                ->first();

            abort_unless($route, 404);

            $documentNumber = (string) $route->routecode . str_pad((string) (((int) $route->acbodocseq) + 1), 5, '0', STR_PAD_LEFT);
            $amount = (float) $payload['amount'];
            $transactionDate = Carbon::parse($payload['date'])->startOfDay();
            $customer = DB::table('customermaster')
                ->select('invoicepaymentterms', 'headofficecode', 'type')
                ->where('customercode', (int) $payload['customercode'])
                ->lockForUpdate()
                ->first();

            abort_unless($customer, 404);

            DB::table('customerinvoice')->insert([
                'documentnumber' => $documentNumber,
                'invoicenumber' => $documentNumber,
                'routecode' => (int) $payload['routecode'],
                'salesmancode' => (int) $payload['salesmancode'],
                'customercode' => (int) $payload['customercode'],
                'transactiontype' => 1,
                'paymenttype' => $customer->invoicepaymentterms,
                'transactiontime' => '00:00:00',
                'totalinvoiceamount' => $amount,
                'invoicebalance' => $amount,
                'amountpaid' => 0,
                'transactiondate' => $transactionDate->toDateString(),
                'remarks1' => $payload['remarks1'] ?? '',
                'remarks2' => $payload['remarks2'] ?? '',
                'erpreferencenumber' => $payload['erpreferencenumber'] ?? '',
                'currencycode' => (int) ($route->amountdecimaldigits ?? 0),
                'mdat' => now(),
            ]);

            DB::table('routemaster')
                ->where('routecode', (int) $payload['routecode'])
                ->update([
                    'acbodocseq' => DB::raw('COALESCE(acbodocseq, 0) + 1'),
                ]);

            DB::table('customermaster')
                ->where('customercode', (int) $payload['customercode'])
                ->update([
                    'balance' => DB::raw('COALESCE(balance, 0) + ' . $amount),
                ]);

            $headOfficeCode = (int) ($customer->type == 2 ? ($customer->headofficecode ?? 0) : 0);

            if ($headOfficeCode > 0 && $headOfficeCode !== (int) $payload['customercode']) {
                DB::table('customermaster')
                    ->where('customercode', $headOfficeCode)
                    ->update([
                        'balance' => DB::raw('COALESCE(balance, 0) + ' . $amount),
                    ]);
            }
        });

        return redirect()
            ->route('account.transaction.opening-balance', ['date' => $payload['date']])
            ->with('success', 'Opening balance saved.');
    }

    public function showOpeningBalance(Request $request, int $transactionkey): Response
    {
        $customerInvoiceAlias = DB::getTablePrefix() . 'ci';
        $routeAlias = DB::getTablePrefix() . 'rm';
        $salesmanAlias = DB::getTablePrefix() . 'sm';
        $customerAlias = DB::getTablePrefix() . 'cm';

        $record = DB::table('customerinvoice as ci')
            ->leftJoin('routemaster as rm', 'rm.routecode', '=', 'ci.routecode')
            ->leftJoin('salesman as sm', 'sm.salesmancode', '=', 'ci.salesmancode')
            ->leftJoin('customermaster as cm', 'cm.customercode', '=', 'ci.customercode')
            ->selectRaw("
                {$customerInvoiceAlias}.transactionkey,
                {$customerInvoiceAlias}.documentnumber,
                {$customerInvoiceAlias}.invoicenumber,
                {$customerInvoiceAlias}.transactiondate,
                {$customerInvoiceAlias}.routecode,
                COALESCE({$routeAlias}.routename, {$routeAlias}.arbroutename, '') as routename,
                {$customerInvoiceAlias}.salesmancode,
                COALESCE({$salesmanAlias}.salesmanname1, {$salesmanAlias}.arbsalesmanname1, '') as salesmanname,
                {$customerInvoiceAlias}.customercode,
                COALESCE({$customerAlias}.customername, {$customerAlias}.arbcustomername, '') as customername,
                {$customerInvoiceAlias}.totalinvoiceamount,
                {$customerInvoiceAlias}.remarks1,
                {$customerInvoiceAlias}.remarks2,
                {$customerInvoiceAlias}.erpreferencenumber
            ")
            ->where('ci.transactiontype', 1)
            ->where('ci.transactionkey', $transactionkey)
            ->first();

        abort_unless($record, 404);
        $this->assertRouteAccess((int) $record->routecode);

        $date = $request->input('date')
            ? Carbon::parse($request->input('date'))->format('Y-m-d')
            : Carbon::parse($record->transactiondate)->format('Y-m-d');

        $meta = $this->openingBalanceRouteMetaPayload((int) $record->routecode);

        if (! collect($meta['customerOptions'])->contains(fn ($option) => (int) $option['id'] === (int) $record->customercode)) {
            $meta['customerOptions'][] = [
                'id' => (int) $record->customercode,
                'label' => trim($record->customercode . ' - ' . ($record->customername ?: '')),
            ];
        }

        return Inertia::render('account/transaction/opening-balance/Form', [
            'mode' => 'view',
            'filters' => ['date' => $date],
            'routeOptions' => $this->openingBalanceRouteOptions(),
            'openingBalanceData' => [
                'transactionkey' => (int) $record->transactionkey,
                'documentnumber' => $record->documentnumber,
                'invoicenumber' => $record->invoicenumber,
                'routecode' => (int) $record->routecode,
                'routeLabel' => trim($record->routecode . ' - ' . ($record->routename ?: '')),
                'salesmancode' => (int) $record->salesmancode,
                'salesmanname' => $record->salesmanname,
                'customercode' => (int) $record->customercode,
                'customerLabel' => trim($record->customercode . ' - ' . ($record->customername ?: '')),
                'amount' => (float) $record->totalinvoiceamount,
                'remarks1' => $record->remarks1,
                'remarks2' => $record->remarks2,
                'erpreferencenumber' => $record->erpreferencenumber,
            ],
            'initialMeta' => $meta,
        ]);
    }

    public function destroyOpeningBalance(Request $request, int $transactionkey): RedirectResponse
    {
        $deleted = false;

        DB::transaction(function () use ($transactionkey, &$deleted) {
            $record = DB::table('customerinvoice')
                ->select('transactionkey', 'customercode', 'totalinvoiceamount', 'amountpaid', 'dnamountpaid', 'cnamountpaid')
                ->where('transactionkey', $transactionkey)
                ->lockForUpdate()
                ->first();

            if (! $record) {
                return;
            }

            if ((float) $record->amountpaid >= 1 || (float) $record->dnamountpaid !== 0.0 || (float) $record->cnamountpaid !== 0.0) {
                return;
            }

            $customer = DB::table('customermaster')
                ->select('headofficecode', 'type')
                ->where('customercode', (int) $record->customercode)
                ->lockForUpdate()
                ->first();

            $amount = (float) $record->totalinvoiceamount;

            DB::table('customerinvoice')
                ->where('transactionkey', $transactionkey)
                ->delete();

            DB::table('customermaster')
                ->where('customercode', (int) $record->customercode)
                ->update([
                    'balance' => DB::raw('COALESCE(balance, 0) - ' . $amount),
                ]);

            $headOfficeCode = $customer && (int) $customer->type === 2 ? (int) ($customer->headofficecode ?? 0) : 0;

            if ($headOfficeCode > 0 && $headOfficeCode !== (int) $record->customercode) {
                DB::table('customermaster')
                    ->where('customercode', $headOfficeCode)
                    ->update([
                        'balance' => DB::raw('COALESCE(balance, 0) - ' . $amount),
                    ]);
            }

            $deleted = true;
        });

        $date = $request->input('date') ?: now()->format('Y-m-d');

        return redirect()
            ->route('account.transaction.opening-balance', ['date' => $date])
            ->with(
                $deleted ? 'success' : 'error',
                $deleted ? 'Opening balance deleted.' : 'Record already in use.'
            );
    }

    public function openingBalanceRouteMeta(Request $request): JsonResponse
    {
        $payload = $request->validate([
            'routecode' => ['required', 'integer'],
        ]);

        $this->assertRouteAccess((int) $payload['routecode']);

        return response()->json($this->openingBalanceRouteMetaPayload((int) $payload['routecode']));
    }

    private function selectedDate(Request $request): Carbon
    {
        $value = $request->input('date');

        try {
            return $value ? Carbon::parse($value) : now();
        } catch (\Throwable) {
            return now();
        }
    }

    private function yearOptions(): array
    {
        return collect(range(2000, 2050))
            ->map(fn ($value) => ['id' => $value, 'label' => (string) $value])
            ->all();
    }

    private function monthOptions(): array
    {
        return collect(range(1, 12))
            ->map(fn ($value) => ['id' => $value, 'label' => $this->monthName($value)])
            ->all();
    }

    private function monthName(int $month): string
    {
        return Carbon::create()->month($month)->format('F');
    }

    private function openingBalanceRouteOptions(): array
    {
        return app(AccessScopeService::class)->scopeQuery(request()->user(), DB::table('routemaster'), 'route', 'routecode')
            ->selectRaw('routecode as id, COALESCE(routename, arbroutename, "") as routename')
            ->where('activestatus', 1)
            ->where('routetmpl', 0)
            ->orderBy('routecode')
            ->get()
            ->map(fn ($row) => [
                'id' => (int) $row->id,
                'label' => trim($row->id . ' - ' . $row->routename),
            ])
            ->filter(fn (array $row) => $row['id'] > 0 && $row['label'] !== '')
            ->values()
            ->all();
    }

    private function openingBalanceRouteMetaPayload(int $routecode): array
    {
        $salesman = DB::table('routemaster as rm')
            ->leftJoin('salesman as sm', 'sm.salesmancode', '=', 'rm.salesmancode')
            ->selectRaw('sm.salesmancode, COALESCE(sm.salesmanname1, sm.arbsalesmanname1, "") as salesmanname')
            ->where('rm.routecode', $routecode)
            ->first();

        $customers = $this->openingBalanceCustomerOptions($routecode);
        $documentNumber = $this->nextOpeningBalanceDocumentNumber($routecode);

        return [
            'salesmancode' => isset($salesman?->salesmancode) ? (int) $salesman->salesmancode : null,
            'salesmanname' => $salesman->salesmanname ?? '',
            'documentnumber' => $documentNumber,
            'invoicenumber' => $documentNumber,
            'customerOptions' => $customers,
        ];
    }

    private function debitNoteCustomerRouteMetaPayload(int $routecode): array
    {
        $resultSets = $this->callProcedure('CALL sp_get_salesman_dccustomer_from_routecode(?)', [$routecode]);

        $salesman = $resultSets[0][0] ?? [];
        $customers = collect($resultSets[1] ?? [])
            ->map(fn (array $row) => [
                'id' => (int) ($row['id'] ?? 0),
                'label' => (string) ($row['val'] ?? ''),
            ])
            ->filter(fn (array $row) => $row['id'] > 0 && $row['label'] !== '')
            ->values()
            ->all();
        $lastId = $resultSets[2][0]['lastid'] ?? '';
        $documentNumber = $routecode && $lastId !== '' ? $routecode . $lastId : '';

        return [
            'salesmancode' => isset($salesman['salesmancode']) ? (int) $salesman['salesmancode'] : null,
            'salesmanname' => $salesman['salesmanname1'] ?? '',
            'documentnumber' => $documentNumber,
            'invoicenumber' => $documentNumber,
            'customerOptions' => $customers,
            'invoiceOptions' => [],
        ];
    }

    private function debitNoteCustomerInvoiceOptions(int $customercode, string $date): array
    {
        $resultSets = $this->callProcedure('CALL sp_combo_invoice_customerocde(?,?)', [
            $customercode,
            Carbon::parse($date)->format('d-m-Y'),
        ]);

        $rows = collect($resultSets[0] ?? [])
            ->map(fn (array $row) => [
                'id' => (int) ($row['id'] ?? 0),
                'label' => (string) ($row['val'] ?? ''),
            ])
            ->filter(fn (array $row) => $row['id'] > 0 && $row['label'] !== '')
            ->values();

        if ($rows->isNotEmpty()) {
            return $rows->all();
        }

        return DB::table('customerinvoice')
            ->selectRaw('invoicenumber as id, CAST(invoicenumber as CHAR) as label')
            ->where('customercode', $customercode)
            ->whereDate('transactiondate', '<=', $date)
            ->orderByDesc('transactiondate')
            ->orderByDesc('invoicenumber')
            ->get()
            ->map(fn ($row) => [
                'id' => (int) $row->id,
                'label' => (string) $row->label,
            ])
            ->values()
            ->all();
    }

    private function debitNoteInvoiceDetailPayload(int $invoiceid): array
    {
        $resultSets = $this->callProcedure('CALL sp_get_invocedetail(?)', [$invoiceid]);
        $row = $resultSets[0][0] ?? null;

        if ($row) {
            return [
                'totalinvoiceamount' => (float) ($row['totalinvoiceamount'] ?? 0),
                'invoicebalance' => (float) ($row['invoicebalance'] ?? 0),
            ];
        }

        $record = DB::table('customerinvoice')
            ->selectRaw('COALESCE(totalinvoiceamount, 0) as totalinvoiceamount, COALESCE(invoicebalance, 0) as invoicebalance')
            ->where('invoicenumber', $invoiceid)
            ->first();

        return [
            'totalinvoiceamount' => (float) ($record->totalinvoiceamount ?? 0),
            'invoicebalance' => (float) ($record->invoicebalance ?? 0),
        ];
    }

    private function debitNoteRouteMetaPayload(int $routecode): array
    {
        $resultSets = $this->callProcedure('CALL sp_get_salesman_from_routecode(?)', [$routecode]);

        $salesman = $resultSets[0][0] ?? [];
        $lastId = $this->callProcedure('CALL sp_get_salesman_dccustomer_from_routecode(?)', [$routecode])[2][0]['lastid'] ?? '';
        $documentNumber = $routecode && $lastId !== '' ? $routecode . $lastId : '';

        return [
            'salesmancode' => isset($salesman['salesmancode']) ? (int) $salesman['salesmancode'] : null,
            'salesmanname' => $salesman['salesmanname1'] ?? '',
            'documentnumber' => $documentNumber,
            'invoicenumber' => $documentNumber,
        ];
    }

    private function creditNoteCustomerBaseOptions(): array
    {
        [$routeOptions, $bankOptions] = $this->gcCollectionBaseOptions();

        return [$routeOptions, $bankOptions];
    }

    private function creditNoteRouteBaseOptions(): array
    {
        return [$this->openingBalanceRouteOptions(), 1];
    }

    private function creditNoteCustomerRouteMetaPayload(int $routecode): array
    {
        $meta = $this->gcCollectionRouteMetaPayload($routecode);

        return [
            'salesmancode' => $meta['salesmancode'] ?? null,
            'salesmanname' => $meta['salesmanname'] ?? '',
            'documentnumber' => $meta['documentnumber'] ?? '',
            'invoicenumber' => $meta['documentnumber'] ?? '',
            'customerOptions' => $meta['customerOptions'] ?? [],
        ];
    }

    private function creditNoteRouteMetaPayload(int $routecode): array
    {
        $salesman = DB::table('routemaster as rm')
            ->leftJoin('salesman as sm', 'sm.salesmancode', '=', 'rm.salesmancode')
            ->selectRaw('sm.salesmancode, COALESCE(sm.salesmanname1, sm.arbsalesmanname1, "") as salesmanname')
            ->where('rm.routecode', $routecode)
            ->first();
        $documentNumber = $this->nextOpeningBalanceDocumentNumber($routecode);

        return [
            'salesmancode' => isset($salesman?->salesmancode) ? (int) $salesman->salesmancode : null,
            'salesmanname' => $salesman->salesmanname ?? '',
            'documentnumber' => $documentNumber,
            'invoicenumber' => $documentNumber,
        ];
    }

    private function updateCustomerAndHeadOfficeBalance(int $customercode, float $delta, ?object $customer = null): void
    {
        DB::table('customermaster')
            ->where('customercode', $customercode)
            ->update([
                'balance' => DB::raw('COALESCE(balance, 0) + ' . $delta),
            ]);

        if (! $customer) {
            $customer = DB::table('customermaster')
                ->select('customercode', 'headofficecode', 'type')
                ->where('customercode', $customercode)
                ->first();
        }

        $headOfficeCode = $customer && (int) $customer->type === 2 ? (int) ($customer->headofficecode ?? 0) : 0;

        if ($headOfficeCode > 0 && $headOfficeCode !== $customercode) {
            DB::table('customermaster')
                ->where('customercode', $headOfficeCode)
                ->update([
                    'balance' => DB::raw('COALESCE(balance, 0) + ' . $delta),
                ]);
        }
    }

    private function gcCollectionBaseOptions(): array
    {
        $routeOptions = app(AccessScopeService::class)->scopeQuery(request()->user(), DB::table('routemaster'), 'route', 'routecode')
            ->selectRaw('routecode as id, COALESCE(routename, arbroutename, "") as routename')
            ->where('activestatus', 1)
            ->where('routetmpl', 0)
            ->whereNotIn('routecode', function ($query) {
                $query->from('startendday')
                    ->select('routecode')
                    ->where('routeclosed', 0)
                    ->where('triptype', 0)
                    ->groupBy('routecode');
            })
            ->orderBy('routecode')
            ->get()
            ->map(fn ($row) => [
                'id' => (int) $row->id,
                'label' => trim($row->id . ' - ' . $row->routename),
            ])
            ->filter(fn (array $row) => $row['id'] > 0 && $row['label'] !== '')
            ->values()
            ->all();
        $bankOptions = DB::table('bankmaster')
            ->selectRaw('bankcode as id, COALESCE(bankname, "") as bankname')
            ->where('activestatus', 1)
            ->orderBy('bankcode')
            ->get()
            ->map(fn ($row) => [
                'id' => (int) $row->id,
                'label' => trim($row->id . ' - ' . $row->bankname),
            ])
            ->filter(fn (array $row) => $row['id'] > 0 && $row['label'] !== '')
            ->values()
            ->all();

        return [$routeOptions, $bankOptions, 1];
    }

    private function hoCollectionBaseOptions(): array
    {
        [$routeOptions, $bankOptions] = $this->gcCollectionBaseOptions();

        return [$routeOptions, $bankOptions, 1];
    }

    private function gcCollectionRouteMetaPayload(int $routecode): array
    {
        $salesman = DB::table('routemaster as rm')
            ->leftJoin('salesman as sm', 'sm.salesmancode', '=', 'rm.salesmancode')
            ->selectRaw('sm.salesmancode, COALESCE(sm.salesmanname1, sm.arbsalesmanname1, "") as salesmanname')
            ->where('rm.routecode', $routecode)
            ->first();
        $customers = collect($this->gcCollectionCustomerOptions($routecode));
        $documentNumber = $this->nextOpeningBalanceDocumentNumber($routecode);

        return [
            'salesmancode' => isset($salesman?->salesmancode) ? (int) $salesman->salesmancode : null,
            'salesmanname' => $salesman->salesmanname ?? '',
            'documentnumber' => $documentNumber,
            'customerOptions' => $customers->values()->all(),
        ];
    }

    private function gcCollectionInvoiceRows(int $routecode, int $customercode, ?int $transactionkey = null): array
    {
        if ($transactionkey) {
            return DB::table('beardetail as bd')
                ->join('customerinvoice as ci', 'ci.invoicenumber', '=', 'bd.invoicenumber')
                ->selectRaw('
                    ci.transactionkey,
                    ci.invoicenumber,
                    ci.transactiondate,
                    COALESCE(ci.totalinvoiceamount, 0) as totalinvoiceamount,
                    COALESCE(bd.amountpaid, 0) as amountpaid,
                    COALESCE(ci.invoicebalance, 0) as invoicebalance,
                    COALESCE(ci.pdcbalance, 0) as pdcbalance
                ')
                ->where('bd.transactionkey', $transactionkey)
                ->orderBy('ci.transactiondate')
                ->orderBy('ci.invoicenumber')
                ->get()
                ->map(fn ($row) => [
                    'transactionkey' => (int) $row->transactionkey,
                    'invoicenumber' => $row->invoicenumber,
                    'transactiondate' => $row->transactiondate,
                    'totalinvoiceamount' => (float) $row->totalinvoiceamount,
                    'amountpaid' => (float) $row->amountpaid,
                    'invoicebalance' => (float) $row->invoicebalance,
                    'pdcbalance' => (float) $row->pdcbalance,
                    'allocatedamount' => (float) $row->amountpaid,
                    'selected' => true,
                ])
                ->values()
                ->all();
        }

        return DB::table('customerinvoice')
            ->selectRaw('
                transactionkey,
                invoicenumber,
                transactiondate,
                COALESCE(totalinvoiceamount, 0) as totalinvoiceamount,
                COALESCE(amountpaid, 0) as amountpaid,
                COALESCE(invoicebalance, 0) as invoicebalance,
                COALESCE(pdcbalance, 0) as pdcbalance
            ')
            ->where('routecode', $routecode)
            ->where('customercode', $customercode)
            ->where('invoicebalance', '!=', 0)
            ->orderBy('transactiondate')
            ->orderBy('invoicenumber')
            ->get()
            ->map(fn ($row) => [
                'transactionkey' => (int) $row->transactionkey,
                'invoicenumber' => $row->invoicenumber,
                'transactiondate' => $row->transactiondate,
                'totalinvoiceamount' => (float) $row->totalinvoiceamount,
                'amountpaid' => (float) $row->amountpaid,
                'invoicebalance' => (float) $row->invoicebalance,
                'pdcbalance' => (float) $row->pdcbalance,
                'allocatedamount' => null,
                'selected' => false,
            ])
            ->values()
            ->all();
    }

    private function hoCollectionRouteMetaPayload(int $routecode): array
    {
        $salesman = DB::table('routemaster as rm')
            ->leftJoin('salesman as sm', 'sm.salesmancode', '=', 'rm.salesmancode')
            ->selectRaw('sm.salesmancode, COALESCE(sm.salesmanname1, sm.arbsalesmanname1, "") as salesmanname')
            ->where('rm.routecode', $routecode)
            ->first();
        $customers = collect($this->hoCollectionCustomerOptions($routecode));
        $documentNumber = $this->nextOpeningBalanceDocumentNumber($routecode);

        return [
            'salesmancode' => isset($salesman?->salesmancode) ? (int) $salesman->salesmancode : null,
            'salesmanname' => $salesman->salesmanname ?? '',
            'documentnumber' => $documentNumber,
            'customerOptions' => $customers->values()->all(),
        ];
    }

    private function hoCollectionInvoiceRows(int $routecode, int $customercode, ?int $transactionkey = null): array
    {
        if ($transactionkey) {
            return DB::table('beardetail as bd')
                ->join('customerinvoice as ci', 'ci.invoicenumber', '=', 'bd.invoicenumber')
                ->join('customermaster as cm', 'cm.customercode', '=', 'ci.customercode')
                ->selectRaw('
                    ci.transactionkey,
                    ci.invoicenumber,
                    ci.transactiondate,
                    ci.customercode,
                    COALESCE(ci.totalinvoiceamount, 0) as totalinvoiceamount,
                    COALESCE(bd.amountpaid, 0) as amountpaid,
                    COALESCE(ci.invoicebalance, 0) as invoicebalance,
                    COALESCE(ci.pdcbalance, 0) as pdcbalance
                ')
                ->where('bd.transactionkey', $transactionkey)
                ->where('cm.headofficecode', $customercode)
                ->orderBy('ci.transactiondate')
                ->orderBy('ci.invoicenumber')
                ->get()
                ->map(fn ($row) => [
                    'transactionkey' => (int) $row->transactionkey,
                    'invoicenumber' => $row->invoicenumber,
                    'transactiondate' => $row->transactiondate,
                    'customercode' => (int) $row->customercode,
                    'totalinvoiceamount' => (float) $row->totalinvoiceamount,
                    'amountpaid' => (float) $row->amountpaid,
                    'invoicebalance' => (float) $row->invoicebalance,
                    'pdcbalance' => (float) $row->pdcbalance,
                    'allocatedamount' => (float) $row->amountpaid,
                    'selected' => true,
                ])
                ->values()
                ->all();
        }

        return DB::table('customerinvoice as ci')
            ->join('customermaster as cm', 'cm.customercode', '=', 'ci.customercode')
            ->selectRaw('
                ci.transactionkey,
                ci.invoicenumber,
                ci.transactiondate,
                ci.customercode,
                COALESCE(ci.totalinvoiceamount, 0) as totalinvoiceamount,
                COALESCE(ci.amountpaid, 0) as amountpaid,
                COALESCE(ci.invoicebalance, 0) as invoicebalance,
                COALESCE(ci.pdcbalance, 0) as pdcbalance
            ')
            ->where('ci.routecode', $routecode)
            ->where('cm.headofficecode', $customercode)
            ->where('ci.invoicebalance', '!=', 0)
            ->orderBy('ci.transactiondate')
            ->orderBy('ci.invoicenumber')
            ->get()
            ->map(fn ($row) => [
                'transactionkey' => (int) $row->transactionkey,
                'invoicenumber' => $row->invoicenumber,
                'transactiondate' => $row->transactiondate,
                'customercode' => (int) $row->customercode,
                'totalinvoiceamount' => (float) $row->totalinvoiceamount,
                'amountpaid' => (float) $row->amountpaid,
                'invoicebalance' => (float) $row->invoicebalance,
                'pdcbalance' => (float) $row->pdcbalance,
                'allocatedamount' => null,
                'selected' => false,
            ])
            ->values()
            ->all();
    }

    private function gcInvoiceTotals(array $invoiceIds): array
    {
        $invoiceIds = array_values(array_filter(array_map('intval', $invoiceIds)));

        if ($invoiceIds === []) {
            return ['invoice_total' => 0, 'amount_paid_total' => 0, 'balance_total' => 0];
        }

        $resultSets = $this->callProcedure('CALL sp_get_account_transaction_invoicetotal(?)', [implode(',', $invoiceIds)]);
        $row = $resultSets[0][0] ?? [];

        return [
            'invoice_total' => (float) ($row['invoiceamt'] ?? 0),
            'amount_paid_total' => (float) ($row['amountpaid'] ?? 0),
            'balance_total' => (float) ($row['balanceamt'] ?? 0),
        ];
    }

    private function creditNoteCustomerInvoiceRows(int $routecode, int $customercode, ?int $transactionkey = null): array
    {
        if ($transactionkey) {
            return DB::table('dcardetail as dd')
                ->join('customerinvoice as ci', 'ci.invoicenumber', '=', 'dd.invoicenumber')
                ->selectRaw('
                    ci.transactionkey,
                    ci.invoicenumber,
                    ci.transactiondate,
                    COALESCE(ci.totalinvoiceamount, 0) as totalinvoiceamount,
                    COALESCE(ci.amountpaid, 0) as amountpaid,
                    COALESCE(ci.invoicebalance, 0) as invoicebalance,
                    COALESCE(ci.pdcbalance, 0) as pdcbalance,
                    COALESCE(dd.amountpaid, 0) as allocatedamount
                ')
                ->where('dd.transactionkey', $transactionkey)
                ->orderBy('ci.transactiondate')
                ->orderBy('ci.invoicenumber')
                ->get()
                ->map(fn ($row) => [
                    'transactionkey' => (int) $row->transactionkey,
                    'invoicenumber' => $row->invoicenumber,
                    'transactiondate' => $row->transactiondate,
                    'totalinvoiceamount' => (float) $row->totalinvoiceamount,
                    'amountpaid' => (float) $row->amountpaid,
                    'invoicebalance' => (float) $row->invoicebalance,
                    'pdcbalance' => (float) $row->pdcbalance,
                    'allocatedamount' => (float) $row->allocatedamount,
                    'selected' => true,
                ])
                ->values()
                ->all();
        }

        return DB::table('customerinvoice')
            ->selectRaw('
                transactionkey,
                invoicenumber,
                transactiondate,
                COALESCE(totalinvoiceamount, 0) as totalinvoiceamount,
                COALESCE(amountpaid, 0) as amountpaid,
                COALESCE(invoicebalance, 0) as invoicebalance,
                COALESCE(pdcbalance, 0) as pdcbalance
            ')
            ->where('routecode', $routecode)
            ->where('customercode', $customercode)
            ->where('invoicebalance', '!=', 0)
            ->orderBy('transactiondate')
            ->orderBy('invoicenumber')
            ->get()
            ->map(fn ($row) => [
                'transactionkey' => (int) $row->transactionkey,
                'invoicenumber' => $row->invoicenumber,
                'transactiondate' => $row->transactiondate,
                'totalinvoiceamount' => (float) $row->totalinvoiceamount,
                'amountpaid' => (float) $row->amountpaid,
                'invoicebalance' => (float) $row->invoicebalance,
                'pdcbalance' => (float) $row->pdcbalance,
                'allocatedamount' => null,
                'selected' => false,
            ])
            ->values()
            ->all();
    }

    private function routeName(int $routecode): ?string
    {
        return DB::table('routemaster')
            ->selectRaw('COALESCE(routename, arbroutename) as name')
            ->where('routecode', $routecode)
            ->value('name');
    }

    private function assertRouteAccess(int $routecode): void
    {
        abort_unless(app(AccessScopeService::class)->allows(request()->user(), 'route', $routecode), 403);
    }

    private function openingBalanceCustomerOptions(int $routecode): array
    {
        $useAlternateCode = $this->useAlternateCode();

        return DB::table('customermaster')
            ->select('customercode', 'alternatecode')
            ->selectRaw('COALESCE(customername, arbcustomername, "") as customername')
            ->where('activecustomer', 1)
            ->where('templateindicator', 0)
            ->where('routecode', $routecode)
            ->whereIn('invoicepaymentterms', [2, 3, 4])
            ->whereIn('type', [1, 2])
            ->orderBy('customercode')
            ->get()
            ->map(fn ($row) => [
                'id' => (int) $row->customercode,
                'label' => trim(($useAlternateCode && filled($row->alternatecode) ? $row->alternatecode : $row->customercode) . ' - ' . $row->customername),
            ])
            ->filter(fn (array $row) => $row['id'] > 0 && $row['label'] !== '')
            ->values()
            ->all();
    }

    private function customerName(int $customercode): ?string
    {
        return DB::table('customermaster')
            ->selectRaw('COALESCE(customername, arbcustomername) as name')
            ->where('customercode', $customercode)
            ->value('name');
    }

    private function salesmanName(int $salesmancode): ?string
    {
        return DB::table('salesman')
            ->selectRaw('COALESCE(salesmanname1, arbsalesmanname1) as name')
            ->where('salesmancode', $salesmancode)
            ->value('name');
    }

    private function bankCodeFromName(?string $bankName, array $bankOptions): ?int
    {
        if (!$bankName) {
            return null;
        }

        foreach ($bankOptions as $option) {
            if (str_contains($option['label'], $bankName)) {
                return (int) $option['id'];
            }
        }

        return null;
    }

    private function callProcedure(string $statement, array $parameters = []): array
    {
        $pdo = DB::connection()->getPdo();
        $query = $pdo->prepare($statement);
        $query->execute($parameters);

        $resultSets = [];

        do {
            $resultSets[] = $query->fetchAll(\PDO::FETCH_ASSOC);
        } while ($query->nextRowset());

        $query->closeCursor();

        return $resultSets;
    }

    private function nextOpeningBalanceDocumentNumber(int $routecode): string
    {
        $route = DB::table('routemaster')
            ->select('routecode', 'acbodocseq')
            ->where('routecode', $routecode)
            ->first();

        if (! $route) {
            return '';
        }

        return (string) $route->routecode . str_pad((string) (((int) $route->acbodocseq) + 1), 5, '0', STR_PAD_LEFT);
    }

    private function gcCollectionCustomerOptions(int $routecode): array
    {
        $useAlternateCode = $this->useAlternateCode();

        return DB::table('customermaster')
            ->select('customercode', 'alternatecode')
            ->selectRaw('COALESCE(customername, arbcustomername, "") as customername')
            ->where('activecustomer', 1)
            ->where('templateindicator', 0)
            ->where('routecode', $routecode)
            ->whereIn('invoicepaymentterms', [2, 3, 4])
            ->whereIn('type', [1])
            ->orderBy('customercode')
            ->get()
            ->map(fn ($row) => [
                'id' => (int) $row->customercode,
                'label' => trim(($useAlternateCode && filled($row->alternatecode) ? $row->alternatecode : $row->customercode) . ' - ' . $row->customername),
            ])
            ->values()
            ->all();
    }

    private function hoCollectionCustomerOptions(int $routecode): array
    {
        $useAlternateCode = $this->useAlternateCode();

        return DB::table('customermaster')
            ->select('customercode', 'alternatecode')
            ->selectRaw('COALESCE(customername, arbcustomername, "") as customername')
            ->where('activecustomer', 1)
            ->where('templateindicator', 0)
            ->where('routecode', $routecode)
            ->whereIn('invoicepaymentterms', [2, 3, 4])
            ->where('type', 3)
            ->orderBy('customercode')
            ->get()
            ->map(fn ($row) => [
                'id' => (int) $row->customercode,
                'label' => trim(($useAlternateCode && filled($row->alternatecode) ? $row->alternatecode : $row->customercode) . ' - ' . $row->customername),
            ])
            ->values()
            ->all();
    }

    private function pdcAsCashEnabled(): bool
    {
        return (int) DB::table('controlpanel')
            ->where('flagid', 36)
            ->value('status') === 1;
    }

    private function useAlternateCode(): bool
    {
        return (int) DB::table('controlpanel')
            ->where('flagname', 'Use Alternate Code')
            ->value('status') === 1;
    }
}
