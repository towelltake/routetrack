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

class SettlementController extends Controller
{
    public function cashReceipt(Request $request): Response
    {
        $scope = app(AccessScopeService::class);
        $user = $request->user();
        $date = $this->selectedDate($request);
        $cashReceiptAlias = DB::getTablePrefix() . 'cr';
        $salesmanAlias = DB::getTablePrefix() . 'sm';
        $routeAlias = DB::getTablePrefix() . 'rm';
        $bankAlias = DB::getTablePrefix() . 'bm';

        $rows = DB::table('cashierreceiptheader as cr')
            ->leftJoin('salesman as sm', 'sm.salesmancode', '=', 'cr.salesmancode')
            ->leftJoin('routemaster as rm', 'rm.routecode', '=', 'cr.routecode')
            ->leftJoin('bankmaster as bm', 'bm.bankcode', '=', 'cr.tobankcode')
            ->selectRaw("
                {$cashReceiptAlias}.documentnumber,
                {$cashReceiptAlias}.routecode,
                COALESCE({$routeAlias}.routename, {$routeAlias}.arbroutename, '') as routename,
                {$cashReceiptAlias}.salesmancode,
                COALESCE({$salesmanAlias}.salesmanname1, {$salesmanAlias}.arbsalesmanname1, '') as salesmanname,
                {$cashReceiptAlias}.tobankcode,
                COALESCE({$bankAlias}.bankname, {$bankAlias}.arbbankname, '') as bankname,
                {$cashReceiptAlias}.slipno,
                {$cashReceiptAlias}.total,
                {$cashReceiptAlias}.date
            ")
            ->whereDate('cr.date', $date->toDateString())
            ->tap(fn ($query) => $scope->scopeQuery($user, $query, 'route', 'cr.routecode'))
            ->orderByDesc('cr.documentnumber')
            ->get()
            ->map(fn ($row) => [
                'documentnumber' => (int) $row->documentnumber,
                'routecode' => (int) $row->routecode,
                'routename' => $row->routename,
                'salesmancode' => (int) $row->salesmancode,
                'salesmanname' => $row->salesmanname,
                'bankname' => $row->bankname,
                'slipno' => $row->slipno,
                'total' => (float) $row->total,
                'date' => $row->date,
            ])
            ->values();

        return Inertia::render('account/settlement/cash-receipt/Index', [
            'filters' => ['date' => $date->format('Y-m-d')],
            'rows' => $rows,
        ]);
    }

    public function createCashReceipt(Request $request): Response
    {
        $date = $this->selectedDate($request);
        [$routeOptions, $bankOptions] = $this->cashReceiptBaseOptions();

        return Inertia::render('account/settlement/cash-receipt/Form', [
            'mode' => 'create',
            'filters' => ['date' => $date->format('Y-m-d')],
            'routeOptions' => $routeOptions,
            'bankOptions' => $bankOptions,
            'cashReceiptData' => [
                'documentnumber' => null,
                'routecode' => '',
                'routeLabel' => '',
                'salesmancode' => '',
                'salesmanname' => '',
                'bankcode' => '',
                'slipno' => '',
                'cashamount' => 0,
                'receiptamount' => 0,
                'chequeamount' => 0,
                'total' => 0,
            ],
            'initialMeta' => [
                'salesmancode' => '',
                'salesmanname' => '',
            ],
            'detailRows' => [],
        ]);
    }

    public function showCashReceipt(Request $request, int $documentnumber): Response
    {
        [$routeOptions, $bankOptions] = $this->cashReceiptBaseOptions();
        $header = DB::table('cashierreceiptheader')
            ->where('documentnumber', $documentnumber)
            ->first();

        abort_unless($header, 404);
        abort_unless(app(AccessScopeService::class)->allows($request->user(), 'route', $header->routecode ?? null), 403);
        $header = (array) $header;
        $cashReceiptDetailAlias = DB::getTablePrefix() . 'crd';
        $bankAlias = DB::getTablePrefix() . 'bm';

        $detailRows = DB::table('cashierreceiptdetails as crd')
            ->leftJoin('bankmaster as bm', 'bm.bankcode', '=', 'crd.bankcode')
            ->selectRaw("
                {$cashReceiptDetailAlias}.transactionno,
                {$cashReceiptDetailAlias}.transactiondate,
                {$cashReceiptDetailAlias}.type,
                {$cashReceiptDetailAlias}.checkno,
                {$cashReceiptDetailAlias}.checkdate,
                COALESCE({$bankAlias}.bankname, {$bankAlias}.arbbankname, '') as bankname,
                {$cashReceiptDetailAlias}.invoiceamount,
                {$cashReceiptDetailAlias}.paid,
                {$cashReceiptDetailAlias}.balance
            ")
            ->where('crd.documentnumber', $documentnumber)
            ->orderBy('crd.transactiondate')
            ->orderBy('crd.transactionno')
            ->get()
            ->map(fn ($row) => [
                'transactionno' => $row->transactionno,
                'transactiondate' => $row->transactiondate,
                'type' => (int) $row->type === 1 ? 'Cheque' : 'Receipt',
                'checkno' => $row->checkno,
                'checkdate' => $row->checkdate,
                'bankname' => $row->bankname,
                'invoiceamount' => (float) $row->invoiceamount,
                'paid' => (float) $row->paid,
                'balance' => (float) $row->balance,
            ])
            ->values()
            ->all();

        $date = $request->input('date')
            ? Carbon::parse($request->input('date'))->format('Y-m-d')
            : Carbon::parse($header['date'])->format('Y-m-d');

        return Inertia::render('account/settlement/cash-receipt/Form', [
            'mode' => 'view',
            'filters' => ['date' => $date],
            'routeOptions' => $routeOptions,
            'bankOptions' => $bankOptions,
            'cashReceiptData' => [
                'documentnumber' => $documentnumber,
                'routecode' => (int) ($header['routecode'] ?? 0),
                'routeLabel' => trim(($header['routecode'] ?? '') . ' - ' . ($this->routeName((int) ($header['routecode'] ?? 0)) ?? '')),
                'salesmancode' => (int) ($header['salesmancode'] ?? 0),
                'salesmanname' => $this->salesmanName((int) ($header['salesmancode'] ?? 0)),
                'bankcode' => (int) ($header['tobankcode'] ?? 0),
                'slipno' => $header['slipno'] ?? '',
                'cashamount' => (float) ($header['cashamount'] ?? 0),
                'receiptamount' => (float) ($header['receiptamount'] ?? 0),
                'chequeamount' => (float) ($header['checkamount'] ?? 0),
                'total' => (float) ($header['total'] ?? 0),
            ],
            'initialMeta' => [
                'salesmancode' => (int) ($header['salesmancode'] ?? 0),
                'salesmanname' => $this->salesmanName((int) ($header['salesmancode'] ?? 0)) ?? '',
            ],
            'detailRows' => $detailRows,
        ]);
    }

    public function storeCashReceipt(Request $request): RedirectResponse
    {
        $payload = $request->validate([
            'date' => ['required', 'date'],
            'routecode' => ['required', 'integer'],
            'salesmancode' => ['required', 'integer'],
            'cashamount' => ['required', 'numeric'],
            'receiptamount' => ['required', 'numeric'],
            'chequeamount' => ['required', 'numeric'],
            'total' => ['required', 'numeric'],
            'bankcode' => ['required', 'integer'],
            'slipno' => ['required', 'string', 'max:30'],
        ]);

        abort_unless(app(AccessScopeService::class)->allows($request->user(), 'route', $payload['routecode']), 403);

        if ((float) $payload['chequeamount'] > 0) {
            return back()->withErrors(['chequeamount' => 'No data to generate cashier receipt when cheque entries exist.'])->withInput();
        }

        DB::transaction(function () use ($payload) {
            $transactionDate = Carbon::parse($payload['date'])->toDateString();

            $headerId = DB::table('cashierreceiptheader')->insertGetId([
                'tobankcode' => (int) $payload['bankcode'],
                'slipno' => $payload['slipno'],
                'paymentdate' => $transactionDate,
                'routecode' => (int) $payload['routecode'],
                'salesmancode' => (int) $payload['salesmancode'],
                'cashamount' => (float) $payload['cashamount'],
                'receiptamount' => (float) $payload['receiptamount'],
                'checkamount' => (float) $payload['chequeamount'],
                'total' => (float) $payload['total'],
                'date' => $transactionDate,
            ]);

            $documentnumber = (int) ((string) $payload['routecode'] . str_pad((string) $headerId, 5, '0', STR_PAD_LEFT));

            DB::table('cashierreceiptheader')
                ->where('primary_key', $headerId)
                ->update(['documentnumber' => $documentnumber]);

            $invoiceRows = $this->cashReceiptInvoiceSourceRows(
                (int) $payload['routecode'],
                (int) $payload['salesmancode'],
                Carbon::parse($payload['date']),
                (int) $payload['bankcode'],
                $payload['slipno'],
                $documentnumber
            );

            $receiptRows = $this->cashReceiptArSourceRows(
                (int) $payload['routecode'],
                (int) $payload['salesmancode'],
                Carbon::parse($payload['date']),
                (int) $payload['bankcode'],
                $payload['slipno'],
                $documentnumber
            );

            if ($invoiceRows->isNotEmpty()) {
                DB::table('cashierreceiptdetails')->insert($invoiceRows->all());
            }

            if ($receiptRows->isNotEmpty()) {
                DB::table('cashierreceiptdetails')->insert($receiptRows->all());
            }
        });

        return redirect()
            ->route('account.settlement.cash-receipt', ['date' => $payload['date']])
            ->with('success', 'Cashier receipt saved.');
    }

    public function destroyCashReceipt(Request $request, int $documentnumber): RedirectResponse
    {
        $deleted = false;

        DB::transaction(function () use ($documentnumber, &$deleted) {
            $header = DB::table('cashierreceiptheader')
                ->select('documentnumber', 'routecode')
                ->where('documentnumber', $documentnumber)
                ->lockForUpdate()
                ->first();

            abort_unless($header, 404);
            abort_unless(app(AccessScopeService::class)->allows(request()->user(), 'route', $header->routecode ?? null), 403);

            $detailRows = DB::table('cashierreceiptdetails')
                ->select('documentnumber', 'checkstatus')
                ->where('documentnumber', $documentnumber)
                ->lockForUpdate()
                ->get();

            if ($detailRows->isEmpty() || $detailRows->contains(fn ($row) => (int) $row->checkstatus === 1)) {
                return;
            }

            DB::table('cashierreceiptdetails')
                ->where('documentnumber', $documentnumber)
                ->delete();

            DB::table('cashierreceiptheader')
                ->where('documentnumber', $documentnumber)
                ->delete();

            $deleted = true;
        });

        $date = $request->input('date') ?: now()->format('Y-m-d');

        return redirect()
            ->route('account.settlement.cash-receipt', ['date' => $date])
            ->with(
                $deleted ? 'success' : 'error',
                $deleted ? 'Cashier receipt deleted.' : 'Record already in use.'
            );
    }

    public function cashReceiptRouteMeta(Request $request): JsonResponse
    {
        $payload = $request->validate([
            'routecode' => ['required', 'integer'],
        ]);

        abort_unless(app(AccessScopeService::class)->allows($request->user(), 'route', $payload['routecode']), 403);

        return response()->json($this->routeMetaPayload((int) $payload['routecode']));
    }

    public function cashReceiptPopulate(Request $request): JsonResponse
    {
        $payload = $request->validate([
            'routecode' => ['required', 'integer'],
            'salesmancode' => ['required', 'integer'],
            'date' => ['required', 'date'],
        ]);

        abort_unless(app(AccessScopeService::class)->allows($request->user(), 'route', $payload['routecode']), 403);

        $date = Carbon::parse($payload['date']);

        if ($this->routeIsLive((int) $payload['routecode'], $date)) {
            return response()->json([
                'message' => "Route is live, can't generate cashier receipt.",
            ], 422);
        }

        $rows = $this->cashReceiptPopulateRows((int) $payload['routecode'], (int) $payload['salesmancode'], $date);
        $receiptAmount = collect($rows)->where('type', 'Receipt')->sum('paid');
        $cashAmount = collect($rows)->where('type', 'Cash')->sum('paid');
        $chequeAmount = collect($rows)->where('type', 'Cheque')->sum('paid');

        return response()->json([
            'rows' => $rows,
            'totals' => [
                'receiptamount' => (float) $receiptAmount,
                'cashamount' => (float) $cashAmount,
                'chequeamount' => (float) $chequeAmount,
                'total' => (float) ($receiptAmount + $cashAmount + $chequeAmount),
            ],
        ]);
    }

    public function pdcClearance(Request $request): Response
    {
        $scope = app(AccessScopeService::class);
        $user = $request->user();
        $date = $this->selectedDate($request);
        $pdcAlias = DB::getTablePrefix() . 'pdc';
        $routeAlias = DB::getTablePrefix() . 'rm';
        $customerAlias = DB::getTablePrefix() . 'cm';
        $salesmanAlias = DB::getTablePrefix() . 'sm';
        $bankAlias = DB::getTablePrefix() . 'bm';

        $rows = DB::table('pdcclearenceheader as pdc')
            ->leftJoin('routemaster as rm', 'rm.routecode', '=', 'pdc.routecode')
            ->leftJoin('customermaster as cm', 'cm.customercode', '=', 'pdc.customercode')
            ->leftJoin('salesman as sm', 'sm.salesmancode', '=', 'pdc.salesmancode')
            ->leftJoin('bankmaster as bm', 'bm.bankcode', '=', 'pdc.chequeclearedbank')
            ->selectRaw("
                {$pdcAlias}.transactionkey,
                {$pdcAlias}.routecode,
                COALESCE({$routeAlias}.routename, {$routeAlias}.arbroutename, '') as routename,
                {$pdcAlias}.customercode,
                COALESCE({$customerAlias}.customername, {$customerAlias}.arbcustomername, '') as customername,
                {$pdcAlias}.salesmancode,
                COALESCE({$salesmanAlias}.salesmanname1, {$salesmanAlias}.arbsalesmanname1, '') as salesmanname,
                COALESCE({$bankAlias}.bankname, {$bankAlias}.arbbankname, '') as bankname,
                {$pdcAlias}.chequeamount,
                {$pdcAlias}.transactiondate
            ")
            ->whereDate('pdc.transactiondate', $date->toDateString())
            ->tap(fn ($query) => $scope->scopeQuery($user, $query, 'route', 'pdc.routecode'))
            ->orderByDesc('pdc.transactionkey')
            ->get()
            ->map(fn ($row) => [
                'transactionkey' => (int) $row->transactionkey,
                'routecode' => (int) $row->routecode,
                'routename' => $row->routename,
                'customercode' => (int) $row->customercode,
                'customername' => $row->customername,
                'salesmancode' => (int) $row->salesmancode,
                'salesmanname' => $row->salesmanname,
                'bankname' => $row->bankname,
                'chequeamount' => (float) $row->chequeamount,
                'transactiondate' => $row->transactiondate,
            ])
            ->values();

        return Inertia::render('account/settlement/pdc-clearance/Index', [
            'filters' => ['date' => $date->format('Y-m-d')],
            'rows' => $rows,
        ]);
    }

    public function createPdcClearance(Request $request): Response
    {
        $date = $this->selectedDate($request);
        [$routeOptions, $bankOptions] = $this->cashReceiptBaseOptions();

        return Inertia::render('account/settlement/pdc-clearance/Form', [
            'filters' => ['date' => $date->format('Y-m-d')],
            'routeOptions' => $routeOptions,
            'bankOptions' => $bankOptions,
            'initialMeta' => [
                'salesmancode' => '',
                'salesmanname' => '',
                'customerOptions' => [],
            ],
            'rows' => [],
        ]);
    }

    public function pdcClearanceRouteMeta(Request $request): JsonResponse
    {
        $payload = $request->validate([
            'routecode' => ['required', 'integer'],
        ]);

        abort_unless(app(AccessScopeService::class)->allows($request->user(), 'route', $payload['routecode']), 403);

        return response()->json($this->routeMetaPayload((int) $payload['routecode'], true));
    }

    public function pdcClearancePopulate(Request $request): JsonResponse
    {
        $payload = $request->validate([
            'routecode' => ['required', 'integer'],
            'salesmancode' => ['required', 'integer'],
            'date' => ['required', 'date'],
            'customercode' => ['nullable', 'integer'],
        ]);

        abort_unless(app(AccessScopeService::class)->allows($request->user(), 'route', $payload['routecode']), 403);

        $date = Carbon::parse($payload['date']);

        if ($this->routeIsLive((int) $payload['routecode'], $date)) {
            return response()->json([
                'message' => "Route is live, can't process PDC clearance.",
            ], 422);
        }

        return response()->json([
            'rows' => $this->pdcClearanceRows(
                (int) $payload['routecode'],
                (int) $payload['salesmancode'],
                $date,
                (int) ($payload['customercode'] ?? 0)
            ),
        ]);
    }

    public function storePdcClearance(Request $request): RedirectResponse
    {
        $payload = $request->validate([
            'date' => ['required', 'date'],
            'routecode' => ['required', 'integer'],
            'salesmancode' => ['required', 'integer'],
            'customercode' => ['nullable', 'integer'],
            'bankcode' => ['nullable', 'integer'],
            'bank_date' => ['nullable', 'date'],
            'erpreferencenumber' => ['nullable', 'string', 'max:50'],
            'bankreferenceno' => ['nullable', 'string', 'max:50'],
            'remark' => ['nullable', 'string', 'max:50'],
            'selected_refs' => ['required', 'array', 'min:1'],
            'selected_refs.*' => ['string'],
        ]);

        abort_unless(app(AccessScopeService::class)->allows($request->user(), 'route', $payload['routecode']), 403);

        DB::statement('CALL sp_add_account_settlement_addpdcgrid_1(?,?,?,?,?,?,?,?,?,?,?)', [
            (int) $payload['routecode'],
            (int) $payload['salesmancode'],
            Carbon::parse($payload['date'])->format('d-m-Y'),
            (int) ($payload['bankcode'] ?? 0),
            $payload['erpreferencenumber'] ?? '',
            $payload['remark'] ?? '',
            filled($payload['bank_date'] ?? null) ? Carbon::parse($payload['bank_date'])->format('d-m-Y') : '',
            implode('$', $payload['selected_refs']),
            count($payload['selected_refs']),
            $request->user()->username,
            $payload['bankreferenceno'] ?? '',
        ]);

        return redirect()
            ->route('account.settlement.pdc-clearance', ['date' => $payload['date']])
            ->with('success', 'PDC clearance updated.');
    }

    public function bouncePdcClearance(Request $request): RedirectResponse
    {
        $payload = $request->validate([
            'date' => ['required', 'date'],
            'selected_refs' => ['required', 'array', 'min:1'],
            'selected_refs.*' => ['string'],
        ]);

        $this->callProcedure('CALL sp_add_account_settlement_bounce_pdccheque(?,?)', [
            implode('$', $payload['selected_refs']),
            count($payload['selected_refs']),
        ]);

        return redirect()
            ->route('account.settlement.pdc-clearance', ['date' => $payload['date']])
            ->with('success', 'Cheque bounce processed.');
    }

    private function selectedDate(Request $request): Carbon
    {
        return $request->filled('date')
            ? Carbon::parse($request->input('date'))->startOfDay()
            : now()->startOfDay();
    }

    private function cashReceiptBaseOptions(): array
    {
        $routeOptions = app(AccessScopeService::class)->scopeQuery(request()->user(), DB::table('routemaster'), 'route', 'routecode')
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

        $bankOptions = DB::table('bankmaster')
            ->selectRaw('bankcode as id, COALESCE(bankname, arbbankname, "") as bankname')
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

        return [$routeOptions, $bankOptions];
    }

    private function routeMetaPayload(int $routecode, bool $withCustomers = false): array
    {
        $salesman = DB::table('routemaster as rm')
            ->leftJoin('salesman as sm', 'sm.salesmancode', '=', 'rm.salesmancode')
            ->selectRaw('sm.salesmancode, COALESCE(sm.salesmanname1, sm.arbsalesmanname1, "") as salesmanname')
            ->where('rm.routecode', $routecode)
            ->first();
        $customers = [];

        if ($withCustomers) {
            $customers = DB::table('customermaster')
                ->selectRaw('customercode as id, COALESCE(customername, arbcustomername, "") as customername')
                ->where('routecode', $routecode)
                ->orderBy('customercode')
                ->get()
                ->map(fn ($row) => [
                    'id' => (int) $row->id,
                    'label' => trim($row->id . ' - ' . $row->customername),
                ])
                ->values()
                ->all();
        }

        return [
            'salesmancode' => isset($salesman?->salesmancode) ? (int) $salesman->salesmancode : null,
            'salesmanname' => $salesman->salesmanname ?? '',
            'customerOptions' => $customers,
        ];
    }

    private function cashReceiptInvoiceSourceRows(
        int $routecode,
        int $salesmancode,
        Carbon $date,
        int $bankcode,
        string $slipno,
        int $documentnumber
    ) {
        return DB::table('invoiceheader as ih')
            ->leftJoin('cashcheckdetail as ccd', function ($join) {
                $join->on('ccd.routekey', '=', 'ih.routekey')
                    ->on('ccd.visitkey', '=', 'ih.visitkey');
            })
            ->selectRaw('
                ih.invoicenumber as transactionno,
                ih.transactiondate,
                ccd.typecode as type,
                ccd.checknumber as checkno,
                ccd.checkdate,
                ih.totalinvoiceamount as invoiceamount,
                ih.amountpaid as paid,
                ih.invoicebalance as balance,
                ccd.amount as checkamount,
                ccd.chequestatusindicator as checkstatus,
                ccd.paymenttype,
                ih.routekey,
                ih.visitkey,
                ih.transactionkey,
                ih.customercode
            ')
            ->where('ih.paymenttype', '!=', 1)
            ->whereIn('ih.paymenttype', [0, 4])
            ->whereIn('ccd.typecode', [0, 1])
            ->where('ccd.transactiontype', 1)
            ->where('ih.routecode', $routecode)
            ->where('ih.salesmancode', $salesmancode)
            ->whereDate('ih.transactiondate', $date->toDateString())
            ->get()
            ->map(fn ($row) => [
                'transactionno' => $row->transactionno,
                'transactiondate' => $row->transactiondate,
                'type' => $row->type,
                'slipno' => $slipno,
                'checkno' => $row->checkno,
                'checkdate' => $row->checkdate,
                'bankcode' => $bankcode,
                'invoiceamount' => $row->invoiceamount,
                'paid' => $row->paid,
                'balance' => $row->balance,
                'routecode' => $routecode,
                'salesmancode' => $salesmancode,
                'date' => $date->toDateString(),
                'pdcbalance' => 0,
                'checkamount' => $row->checkamount,
                'checkstatus' => $row->checkstatus,
                'paymenttype' => $row->paymenttype,
                'routekey' => $row->routekey,
                'visitkey' => $row->visitkey,
                'transactionkey' => $row->transactionkey,
                'documentnumber' => $documentnumber,
                'customercode' => $row->customercode,
            ]);
    }

    private function cashReceiptArSourceRows(
        int $routecode,
        int $salesmancode,
        Carbon $date,
        int $bankcode,
        string $slipno,
        int $documentnumber
    ) {
        return DB::table('arheader as ah')
            ->leftJoin('cashcheckdetail as ccd', function ($join) {
                $join->on('ccd.routekey', '=', 'ah.routekey')
                    ->on('ccd.visitkey', '=', 'ah.visitkey');
            })
            ->selectRaw('
                ah.invoicenumber as transactionno,
                ah.transactiondate,
                ccd.typecode as type,
                ccd.checknumber as checkno,
                ccd.checkdate,
                ah.totalinvoiceamount as invoiceamount,
                ccd.amount as paid,
                ah.invoicebalance as balance,
                ccd.amount as checkamount,
                ccd.chequestatusindicator as checkstatus,
                ccd.paymenttype,
                ah.routekey,
                ah.visitkey,
                ah.transactionkey,
                ah.customercode
            ')
            ->whereIn('ccd.typecode', [0, 1])
            ->where('ccd.transactiontype', 2)
            ->where('ah.routecode', $routecode)
            ->where('ah.salesmancode', $salesmancode)
            ->whereDate('ah.transactiondate', $date->toDateString())
            ->get()
            ->map(fn ($row) => [
                'transactionno' => $row->transactionno,
                'transactiondate' => $row->transactiondate,
                'type' => $row->type,
                'slipno' => $slipno,
                'checkno' => $row->checkno,
                'checkdate' => $row->checkdate,
                'bankcode' => $bankcode,
                'invoiceamount' => $row->invoiceamount,
                'paid' => $row->paid,
                'balance' => $row->balance,
                'routecode' => $routecode,
                'salesmancode' => $salesmancode,
                'date' => $date->toDateString(),
                'pdcbalance' => 0,
                'checkamount' => $row->checkamount,
                'checkstatus' => $row->checkstatus,
                'paymenttype' => $row->paymenttype,
                'routekey' => $row->routekey,
                'visitkey' => $row->visitkey,
                'transactionkey' => $row->transactionkey,
                'documentnumber' => $documentnumber,
                'customercode' => $row->customercode,
            ]);
    }

    private function routeIsLive(int $routecode, Carbon $date): bool
    {
        $resultSets = $this->callProcedure('CALL sp_get_route_status_startendday(?,?)', [
            $routecode,
            $date->format('d-m-Y'),
        ]);

        return (int) ($resultSets[0][0]['cnt'] ?? 0) > 0;
    }

    private function cashReceiptPopulateRows(int $routecode, int $salesmancode, Carbon $date): array
    {
        $receiptHeaderAlias = DB::getTablePrefix() . 'ah';
        $receiptDetailAlias = DB::getTablePrefix() . 'ad';
        $cashCheckAlias = DB::getTablePrefix() . 'ccd';
        $bankAlias = DB::getTablePrefix() . 'bm';

        $receiptRows = DB::table('arheader as ah')
            ->join('ardetail as ad', 'ad.transactionkey', '=', 'ah.transactionkey')
            ->leftJoin('cashcheckdetail as ccd', function ($join) {
                $join->on('ccd.routekey', '=', 'ah.routekey')
                    ->on('ccd.visitkey', '=', 'ah.visitkey');
            })
            ->leftJoin('bankmaster as bm', 'bm.bankcode', '=', 'ccd.bankcode')
            ->selectRaw("
                {$receiptHeaderAlias}.transactionkey as id,
                {$receiptHeaderAlias}.invoicenumber as transactionno,
                {$receiptHeaderAlias}.transactiondate,
                'Receipt' as type,
                {$cashCheckAlias}.checknumber,
                {$cashCheckAlias}.checkdate,
                COALESCE({$bankAlias}.bankname, {$bankAlias}.arbbankname, '') as bankname,
                COALESCE({$receiptDetailAlias}.totalinvoiceamount, 0) as totalinvoiceamount,
                COALESCE({$receiptDetailAlias}.amountpaid, 0) as paid,
                COALESCE({$receiptDetailAlias}.invoicebalance, 0) as balance
            ")
            ->where('ah.routecode', $routecode)
            ->where('ah.salesmancode', $salesmancode)
            ->whereDate('ah.transactiondate', $date->toDateString())
            ->whereIn('ccd.typecode', [0, 1])
            ->where('ccd.transactiontype', 2)
            ->get()
            ->map(fn ($row) => [
                'id' => 'ah_' . $row->id,
                'transactionno' => $row->transactionno,
                'transactiondate' => $row->transactiondate,
                'type' => $row->type,
                'checknumber' => $row->checknumber,
                'checkdate' => $row->checkdate,
                'bankname' => $row->bankname,
                'totalinvoiceamount' => (float) $row->totalinvoiceamount,
                'paid' => (float) $row->paid,
                'balance' => (float) $row->balance,
            ]);

        $invoiceHeaderAlias = DB::getTablePrefix() . 'ih';
        $customerInvoiceAlias = DB::getTablePrefix() . 'ci';

        $invoiceRows = DB::table('invoiceheader as ih')
            ->join('customerinvoice as ci', 'ci.invoicenumber', '=', 'ih.invoicenumber')
            ->leftJoin('cashcheckdetail as ccd', function ($join) {
                $join->on('ccd.routekey', '=', 'ih.routekey')
                    ->on('ccd.visitkey', '=', 'ih.visitkey');
            })
            ->leftJoin('bankmaster as bm', 'bm.bankcode', '=', 'ccd.bankcode')
            ->selectRaw("
                {$invoiceHeaderAlias}.transactionkey as id,
                {$invoiceHeaderAlias}.invoicenumber as transactionno,
                {$invoiceHeaderAlias}.transactiondate,
                {$cashCheckAlias}.typecode,
                {$cashCheckAlias}.checknumber,
                {$cashCheckAlias}.checkdate,
                COALESCE({$bankAlias}.bankname, {$bankAlias}.arbbankname, '') as bankname,
                COALESCE({$customerInvoiceAlias}.totalinvoiceamount, 0) as totalinvoiceamount,
                COALESCE({$customerInvoiceAlias}.amountpaid, 0) as paid,
                COALESCE({$customerInvoiceAlias}.invoicebalance, 0) as balance
            ")
            ->where('ih.routecode', $routecode)
            ->where('ih.salesmancode', $salesmancode)
            ->whereDate('ih.transactiondate', $date->toDateString())
            ->whereIn('ih.paymenttype', [0, 4])
            ->whereIn('ccd.typecode', [0, 1])
            ->where('ccd.transactiontype', 1)
            ->get()
            ->map(fn ($row) => [
                'id' => 'ih_' . $row->id,
                'transactionno' => $row->transactionno,
                'transactiondate' => $row->transactiondate,
                'type' => (int) $row->typecode === 1 ? 'Cheque' : 'Cash',
                'checknumber' => $row->checknumber,
                'checkdate' => $row->checkdate,
                'bankname' => $row->bankname,
                'totalinvoiceamount' => (float) $row->totalinvoiceamount,
                'paid' => (float) $row->paid,
                'balance' => (float) $row->balance,
            ]);

        return $receiptRows
            ->concat($invoiceRows)
            ->sortBy(['transactiondate', 'transactionno'])
            ->values()
            ->all();
    }

    private function pdcClearanceRows(int $routecode, int $salesmancode, Carbon $date, int $customercode = 0): array
    {
        $rows = collect();
        $arHeaderAlias = DB::getTablePrefix() . 'ah';
        $cashCheckAlias = DB::getTablePrefix() . 'ccd';
        $bankAlias = DB::getTablePrefix() . 'bm';

        $arRows = DB::table('arheader as ah')
            ->leftJoin('cashcheckdetail as ccd', function ($join) {
                $join->on('ccd.routekey', '=', 'ah.routekey')
                    ->on('ccd.visitkey', '=', 'ah.visitkey');
            })
            ->leftJoin('bankmaster as bm', 'bm.bankcode', '=', 'ccd.bankcode')
            ->selectRaw("
                CONCAT({$arHeaderAlias}.transactionkey, '_ah_', {$arHeaderAlias}.invoicenumber) as transaction_ref,
                {$arHeaderAlias}.invoicenumber as transactionno,
                {$arHeaderAlias}.customercode,
                {$cashCheckAlias}.checknumber,
                {$cashCheckAlias}.checkdate,
                COALESCE({$cashCheckAlias}.amount, 0) as checkamount,
                COALESCE({$bankAlias}.bankname, {$bankAlias}.arbbankname, '') as bankname
            ")
            ->where('ah.routecode', $routecode)
            ->where('ah.salesmancode', $salesmancode)
            ->whereDate('ah.transactiondate', $date->toDateString())
            ->where('ccd.transactiontype', 2)
            ->where('ccd.typecode', 1)
            ->whereNotIn('ccd.checkstatus', [1, 2])
            ->when($customercode > 0, fn ($query) => $query->where('ah.customercode', $customercode))
            ->get();

        $invoiceHeaderAlias = DB::getTablePrefix() . 'ih';

        $invoiceRows = DB::table('invoiceheader as ih')
            ->leftJoin('cashcheckdetail as ccd', function ($join) {
                $join->on('ccd.routekey', '=', 'ih.routekey')
                    ->on('ccd.visitkey', '=', 'ih.visitkey');
            })
            ->leftJoin('bankmaster as bm', 'bm.bankcode', '=', 'ccd.bankcode')
            ->selectRaw("
                CONCAT({$invoiceHeaderAlias}.transactionkey, '_ih_', {$invoiceHeaderAlias}.invoicenumber) as transaction_ref,
                {$invoiceHeaderAlias}.invoicenumber as transactionno,
                {$invoiceHeaderAlias}.customercode,
                {$cashCheckAlias}.checknumber,
                {$cashCheckAlias}.checkdate,
                COALESCE({$cashCheckAlias}.amount, 0) as checkamount,
                COALESCE({$bankAlias}.bankname, {$bankAlias}.arbbankname, '') as bankname
            ")
            ->where('ih.routecode', $routecode)
            ->where('ih.salesmancode', $salesmancode)
            ->whereDate('ih.transactiondate', $date->toDateString())
            ->where('ih.paymenttype', 4)
            ->where('ccd.transactiontype', 1)
            ->where('ccd.typecode', 1)
            ->whereNotIn('ccd.checkstatus', [1, 2])
            ->when($customercode > 0, fn ($query) => $query->where('ih.customercode', $customercode))
            ->get();

        $bearHeaderAlias = DB::getTablePrefix() . 'bh';
        $bearCashCheckAlias = DB::getTablePrefix() . 'bcd';

        $bearRows = DB::table('bearheader as bh')
            ->join('beardetail as bd', 'bd.transactionkey', '=', 'bh.transactionkey')
            ->join('bearcashcheckdetail as bcd', 'bcd.transactionkey', '=', 'bh.transactionkey')
            ->leftJoin('bankmaster as bm', 'bm.bankcode', '=', 'bcd.bankcode')
            ->selectRaw("
                CONCAT({$bearHeaderAlias}.transactionkey, '_be_', {$bearHeaderAlias}.documentnumber) as transaction_ref,
                {$bearHeaderAlias}.documentnumber as transactionno,
                {$bearHeaderAlias}.customercode,
                {$bearCashCheckAlias}.checknumber,
                {$bearCashCheckAlias}.checkdate,
                COALESCE({$bearCashCheckAlias}.amount, 0) as checkamount,
                COALESCE({$bankAlias}.bankname, {$bankAlias}.arbbankname, '') as bankname
            ")
            ->where('bh.routecode', $routecode)
            ->where('bh.salesmancode', $salesmancode)
            ->whereDate('bh.transactiondate', $date->toDateString())
            ->where('bd.paymentmode', 1)
            ->whereNotIn('bcd.checkstatus', [1, 2])
            ->when($customercode > 0, fn ($query) => $query->where('bh.customercode', $customercode))
            ->get();

        $dcarHeaderAlias = DB::getTablePrefix() . 'dh';
        $dcarCashCheckAlias = DB::getTablePrefix() . 'dcd';

        $dcarRows = DB::table('dcarheader as dh')
            ->join('dcardetail as dd', 'dd.transactionkey', '=', 'dh.transactionkey')
            ->join('dcarcashcheckdetail as dcd', 'dcd.transactionkey', '=', 'dh.transactionkey')
            ->leftJoin('bankmaster as bm', 'bm.bankcode', '=', 'dcd.bankcode')
            ->selectRaw("
                CONCAT({$dcarHeaderAlias}.transactionkey, '_dc_', {$dcarHeaderAlias}.documentnumber) as transaction_ref,
                {$dcarHeaderAlias}.documentnumber as transactionno,
                {$dcarHeaderAlias}.customercode,
                {$dcarCashCheckAlias}.checknumber,
                {$dcarCashCheckAlias}.checkdate,
                COALESCE({$dcarCashCheckAlias}.amount, 0) as checkamount,
                COALESCE({$bankAlias}.bankname, {$bankAlias}.arbbankname, '') as bankname
            ")
            ->where('dh.routecode', $routecode)
            ->where('dh.salesmancode', $salesmancode)
            ->whereDate('dh.transactiondate', $date->toDateString())
            ->where('dd.paymentmode', 1)
            ->whereNotIn('dcd.checkstatus', [1, 2])
            ->when($customercode > 0, fn ($query) => $query->where('dh.customercode', $customercode))
            ->get();

        return $rows
            ->concat($arRows)
            ->concat($invoiceRows)
            ->concat($bearRows)
            ->concat($dcarRows)
            ->map(fn ($row) => [
                'transaction_ref' => $row->transaction_ref,
                'transactionno' => $row->transactionno,
                'customercode' => (int) $row->customercode,
                'customername' => $this->customerName((int) $row->customercode),
                'checknumber' => $row->checknumber,
                'checkdate' => $row->checkdate,
                'checkamount' => (float) $row->checkamount,
                'bankname' => $row->bankname,
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

    private function salesmanName(int $salesmancode): ?string
    {
        return DB::table('salesman')
            ->selectRaw('COALESCE(salesmanname1, arbsalesmanname1) as name')
            ->where('salesmancode', $salesmancode)
            ->value('name');
    }

    private function customerName(int $customercode): ?string
    {
        return DB::table('customermaster')
            ->selectRaw('COALESCE(customername, arbcustomername) as name')
            ->where('customercode', $customercode)
            ->value('name');
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
}
