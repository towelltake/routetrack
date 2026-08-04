<?php

namespace App\Http\Controllers\Account;

use App\Http\Controllers\Controller;
use App\Support\SalesmanOtpGenerator;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class SalesmanOtpController extends Controller
{
    public function __construct(private readonly SalesmanOtpGenerator $generator)
    {
    }

    public function index(): Response
    {
        return $this->renderPage();
    }

    public function generate(Request $request): Response
    {
        if ($request->isMethod('get')) {
            return $this->renderPage();
        }

        $typeOptions = collect($this->typeOptions());

        $payload = $request->validate([
            'type' => ['required', Rule::in($typeOptions->pluck('id')->all())],
            'customer_code' => [
                Rule::requiredIf(fn () => in_array((string) $request->input('type'), ['2', '5', '6'], true)),
                'nullable',
                'string',
                'max:50',
                'regex:/^[0-9]+$/',
            ],
            'route_code' => [
                Rule::requiredIf(fn () => in_array((string) $request->input('type'), ['2', '5', '6'], true)),
                'nullable',
                'string',
                'max:50',
                'regex:/^[0-9]+$/',
            ],
            'access_key' => ['required', 'string', 'max:50', 'regex:/^[0-9]+$/'],
        ], [
            'customer_code.required' => __('ui.customer_code_required_for_otp'),
            'customer_code.regex' => __('ui.customer_code_digits_only'),
            'route_code.required' => __('ui.route_code_required'),
            'route_code.regex' => __('ui.route_code_digits_only'),
            'access_key.regex' => __('ui.access_key_digits_only'),
        ]);

        $customerCode = trim((string) ($payload['customer_code'] ?? ''));
        $routeCode = trim((string) ($payload['route_code'] ?? ''));
        $customer = $customerCode !== '' ? $this->customerRecord($customerCode) : null;

        $otp = $this->generator->generate($payload['type'], $payload['access_key'], $customerCode);
        $typeLabel = (string) $typeOptions->firstWhere('id', $payload['type'])['label'];

        if ($customerCode !== '' || $routeCode !== '') {
            $this->logGeneration($typeLabel, $customerCode, $routeCode, $customer);
        }

        return $this->renderPage([
            'requested_type' => $payload['type'],
            'requested_customer_code' => $customerCode,
            'requested_route_code' => $routeCode,
            'requested_access_key' => $payload['access_key'],
            'otp' => $otp,
            'type_label' => $typeLabel,
            'customer' => $customer ? [
                'customercode' => (int) $customer->customercode,
                'alternatecode' => (string) ($customer->alternatecode ?? ''),
                'customername' => (string) ($customer->customername ?? ''),
                'creditlimit' => $customer->creditlimit !== null ? (float) $customer->creditlimit : null,
                'creditlimitdays' => $customer->creditlimitdays !== null ? (int) $customer->creditlimitdays : null,
                'graceperiod' => $customer->graceperiod !== null ? (int) $customer->graceperiod : null,
                'balance' => $customer->balance !== null ? (float) $customer->balance : null,
                'routecode' => $customer->routecode !== null ? (int) $customer->routecode : null,
            ] : null,
            'customer_lookup_warning' => $customerCode !== '' && ! $customer
                ? __('ui.otp_generated_customer_not_found')
                : null,
            'invoices' => $customer ? $this->invoiceRows((int) $customer->customercode) : [],
        ]);
    }

    private function renderPage(?array $result = null): Response
    {
        return Inertia::render('account/salesman-otp/Index', [
            'typeOptions' => $this->typeOptions(),
            'result' => $result,
            'notes' => [
                __('ui.otp_note_popup_passkey'),
                __('ui.otp_note_standard_flow'),
                __('ui.otp_note_finance_flow'),
                __('ui.otp_note_customer_route_required'),
            ],
        ]);
    }

    private function typeOptions(): array
    {
        $options = [
            ['id' => '1', 'label' => __('ui.otp_type_journey_plan')],
            ['id' => '2', 'label' => __('ui.otp_type_gps')],
            ['id' => '3', 'label' => __('ui.otp_type_post_void')],
            ['id' => '4', 'label' => __('ui.otp_type_customer_returns')],
        ];

        if ((int) (auth()->user()?->usertypeid ?? 0) > 0 && (int) auth()->user()->usertypeid < 6) {
            $options[] = ['id' => '5', 'label' => __('ui.otp_type_credit_limit_amount')];
            $options[] = ['id' => '6', 'label' => __('ui.otp_type_credit_days')];
        }

        $options[] = ['id' => '7', 'label' => __('ui.otp_type_multiple_request')];

        return $options;
    }

    private function customerRecord(string $customerCode): ?object
    {
        return DB::table('customermaster')
            ->select([
                'customercode',
                'alternatecode',
                'customername',
                'creditlimit',
                'creditlimitdays',
                'graceperiod',
                'balance',
                'routecode',
            ])
            ->where(function ($query) use ($customerCode) {
                $query->where('customercode', $customerCode);

                if (Schema::hasColumn('customermaster', 'alternatecode')) {
                    $query->orWhere('alternatecode', $customerCode);
                }
            })
            ->first();
    }

    private function invoiceRows(int $customerCode): array
    {
        if (! Schema::hasTable('customerinvoice') || ! Schema::hasTable('salesman')) {
            return [];
        }

        return DB::table('customerinvoice as ci')
            ->leftJoin('salesman as sm', 'sm.salesmancode', '=', 'ci.salesmancode')
            ->where('ci.customercode', $customerCode)
            ->orderBy('ci.duedate')
            ->get([
                'ci.transactiondate',
                'ci.invoicenumber',
                'ci.erpreferencenumber',
                'sm.alternatesalesmancode',
                'ci.totalinvoiceamount',
                'ci.invoicebalance',
                'ci.duedate',
            ])
            ->map(fn ($row) => [
                'transactiondate' => $row->transactiondate,
                'invoicenumber' => $row->invoicenumber,
                'erpreferencenumber' => $row->erpreferencenumber,
                'salesmancode' => $row->alternatesalesmancode,
                'totalinvoiceamount' => $row->totalinvoiceamount !== null ? (float) $row->totalinvoiceamount : null,
                'invoicebalance' => $row->invoicebalance !== null ? (float) $row->invoicebalance : null,
                'duedate' => $row->duedate,
            ])
            ->all();
    }

    private function logGeneration(string $typeLabel, string $customerCode, string $routeCode, ?object $customer = null): void
    {
        if (! Schema::hasTable('otplogdetail')) {
            return;
        }

        DB::table('otplogdetail')->insert([
            'routecode' => $routeCode !== '' ? (int) $routeCode : ($customer?->routecode !== null ? (int) $customer->routecode : null),
            'customercode' => $customerCode !== '' ? (int) $customerCode : ($customer?->customercode !== null ? (int) $customer->customercode : null),
            'username' => (string) (auth()->user()?->username ?? auth()->user()?->name ?? 'system'),
            'otptype' => $typeLabel,
            'otpdate' => now()->toDateString(),
            'otptime' => now()->format('H:i:s'),
            'cdate' => now(),
        ]);
    }
}
