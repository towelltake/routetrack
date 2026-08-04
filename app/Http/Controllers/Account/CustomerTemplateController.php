<?php

namespace App\Http\Controllers\Account;

use App\Http\Controllers\Controller;
use App\Models\CategoryMaster;
use App\Models\ChannelMaster;
use App\Models\CurrencyMaster;
use App\Models\CustomerMaster;
use App\Models\CustomerMessage;
use App\Models\RouteMaster;
use App\Services\AccessScopeService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class CustomerTemplateController extends Controller
{
    public function index(): Response
    {
        $scope = app(AccessScopeService::class);
        $user = request()->user();
        $search = request('search');
        $perPage = (int) request('per_page', 10);
        $perPage = in_array($perPage, [10, 25, 50, 100], true) ? $perPage : 10;

        $templates = CustomerMaster::query()
            ->where('customermaster.templateindicator', 1)
            ->leftJoin('routemaster as route', 'route.routecode', '=', 'customermaster.routecode')
            ->when($search, function ($query, $searchTerm) {
                $query->where(function ($inner) use ($searchTerm) {
                    $inner->where('customermaster.customercode', 'like', '%' . $searchTerm . '%')
                        ->orWhere('customermaster.templatename', 'like', '%' . $searchTerm . '%')
                        ->orWhere('customermaster.customername', 'like', '%' . $searchTerm . '%')
                        ->orWhere('customermaster.alternatecode', 'like', '%' . $searchTerm . '%')
                        ->orWhere('route.routename', 'like', '%' . $searchTerm . '%');
                });
            })
            ->tap(fn ($query) => $scope->scopeQuery($user, $query, 'route', 'customermaster.routecode'))
            ->orderBy('customermaster.customercode')
            ->select([
                'customermaster.customercode',
                'customermaster.templatename',
                'customermaster.customername',
                'customermaster.alternatecode',
                'customermaster.invoicepaymentterms',
                'customermaster.activecustomer',
                'route.routename as routename',
            ])
            ->paginate($perPage)
            ->withQueryString();

        return Inertia::render('account/customertemplate/Index', [
            'filters' => [
                'search' => $search,
                'per_page' => $perPage,
            ],
            'templates' => $templates,
        ]);
    }

    public function create(): Response
    {
        $props = $this->formProps();
        $props['customerData']['customercode'] = $this->nextCustomerCode();

        return Inertia::render('account/customertemplate/Create', $props);
    }

    public function show(CustomerMaster $customerTemplate): Response
    {
        abort_unless((int) $customerTemplate->templateindicator === 1, 404);
        abort_unless(app(AccessScopeService::class)->allows(request()->user(), 'route', $customerTemplate->routecode), 403);

        return Inertia::render('account/customertemplate/View', $this->formProps($customerTemplate));
    }

    public function edit(CustomerMaster $customerTemplate): Response
    {
        abort_unless((int) $customerTemplate->templateindicator === 1, 404);
        abort_unless(app(AccessScopeService::class)->allows(request()->user(), 'route', $customerTemplate->routecode), 403);

        return Inertia::render('account/customertemplate/Edit', $this->formProps($customerTemplate));
    }

    public function store(Request $request): RedirectResponse
    {
        $payload = $this->validatedData($request);
        $username = auth()->user()?->username ?? auth()->user()?->name;
        $payload['templateindicator'] = 1;
        $payload['created'] = $username;
        $payload['cdat'] = now();
        $payload['modified'] = $username;
        $payload['mdat'] = now();

        CustomerMaster::create($payload);

        return redirect()
            ->route('account.customer-template.index')
            ->with('success', 'Customer template created.');
    }

    public function update(Request $request, CustomerMaster $customerTemplate): RedirectResponse
    {
        abort_unless((int) $customerTemplate->templateindicator === 1, 404);
        abort_unless(app(AccessScopeService::class)->allows($request->user(), 'route', $customerTemplate->routecode), 403);

        $payload = $this->validatedData($request, $customerTemplate);
        $payload['templateindicator'] = 1;
        $payload['modified'] = auth()->user()?->username ?? auth()->user()?->name;
        $payload['mdat'] = now();

        $customerTemplate->update($payload);

        return redirect()
            ->route('account.customer-template.index')
            ->with('success', 'Customer template updated.');
    }

    public function destroy(CustomerMaster $customerTemplate): RedirectResponse
    {
        abort_unless((int) $customerTemplate->templateindicator === 1, 404);
        abort_unless(app(AccessScopeService::class)->allows(request()->user(), 'route', $customerTemplate->routecode), 403);

        try {
            $customerTemplate->delete();
        } catch (\Throwable $e) {
            return back()->with('error', 'Cannot delete: record is in use.');
        }

        return back()->with('success', 'Customer template deleted.');
    }

    private function formProps(?CustomerMaster $customer = null): array
    {
        return [
            'customerData' => $this->customerFormData($customer),
            'optionSets' => [
                'statusOptions' => $this->statusOptions(),
                'customerTypeOptions' => $this->customerTypeOptions(),
                'invoicePaymentTermOptions' => $this->invoicePaymentTermOptions(),
                'arCustomerTypeOptions' => $this->arCustomerTypeOptions(),
                'routeOptions' => RouteMaster::query()
                    ->tap(fn ($query) => app(AccessScopeService::class)->scopeQuery(request()->user(), $query, 'route', 'routecode'))
                    ->where('routetmpl', 0)
                    ->orderBy('routename')
                    ->get(['routecode as id', 'routename as label']),
                'headOfficeOptions' => CustomerMaster::query()
                    ->tap(fn ($query) => app(AccessScopeService::class)->scopeQuery(request()->user(), $query, 'route', 'routecode'))
                    ->where('templateindicator', 0)
                    ->where('customertype', '3')
                    ->orderBy('customername')
                    ->get(['customercode as id', 'customername as label']),
                'channelOptions' => ChannelMaster::query()
                    ->orderBy('channelname')
                    ->get(['channelcode as id', 'channelname as label']),
                'categoryOptions' => CategoryMaster::query()
                    ->orderBy('categoryname')
                    ->get(['categoryid as id', 'categoryname as label']),
                'currencyOptions' => CurrencyMaster::query()
                    ->orderBy('currencyname')
                    ->get(['currencycode as id', 'currencyname as label']),
                'messageOptions' => CustomerMessage::query()
                    ->orderBy('messagedescription')
                    ->get(['messagekey as id', 'messagedescription as label']),
                'planogramOptions' => $this->planogramOptions(),
                'printSequenceOptions' => $this->printSequenceOptions(),
                'stockCaptureOptions' => $this->stockCaptureOptions(),
                'invoicePricePrintOptions' => $this->invoicePricePrintOptions(),
                'editPriceOptions' => $this->editPriceOptions(),
                'sellPreviousOptions' => $this->sellPreviousOptions(),
                'suggestSalesOptions' => $this->suggestSalesOptions(),
                'autoFillOptions' => $this->autoFillOptions(),
                'salesTransactionOptions' => $this->salesTransactionOptions(),
                'signatureCaptureOptions' => $this->signatureCaptureOptions(),
                'invoiceFormatOptions' => $this->invoiceFormatOptions(),
                'printLanguageOptions' => $this->printLanguageOptions(),
                'promoEditOptions' => $this->promoEditOptions(),
                'roundingOptions' => $this->roundingOptions(),
                'taxPrintOptions' => $this->taxPrintOptions(),
                'applyTaxOptions' => $this->applyTaxOptions(),
                'yesNoOptions' => $this->yesNoOptions(),
            ],
            'formConfig' => [
                'useGracePeriod' => $this->useGracePeriod(),
            ],
        ];
    }

    private function customerFormData(?CustomerMaster $customer): array
    {
        $record = $customer?->toArray() ?? [];

        return array_merge($this->defaultCustomerData(), array_intersect_key(
            $record,
            array_flip(array_keys($this->defaultCustomerData()))
        ));
    }

    private function defaultCustomerData(): array
    {
        return [
            'customercode' => null,
            'templateindicator' => 1,
            'templatename' => '',
            'routecode' => null,
            'headofficecode' => null,
            'customername' => '',
            'arbcustomername' => '',
            'alternatecode' => '',
            'barcode' => '',
            'customerphone' => '',
            'customeraddress1' => '',
            'customeraddress2' => '',
            'customeraddress3' => '',
            'customercity' => '',
            'customerzip' => '',
            'pobox' => '',
            'customertype' => '1',
            'channelcode' => null,
            'customercategory' => null,
            'currencycode' => null,
            'invoicepaymentterms' => 0,
            'creditlimit' => null,
            'creditlimitdays' => null,
            'balance' => 0,
            'activecustomer' => 1,
            'arcustomertype' => 0,
            'tclimit' => null,
            'messagekey1' => null,
            'messagekey2' => null,
            'messagekey3' => null,
            'messagekey4' => null,
            'messagekey5' => null,
            'messagekey6' => null,
            'invoiceformatoption' => 1,
            'printsequence' => 1,
            'forcestockcapture' => 0,
            'invoicepriceprint' => 0,
            'enablepriceeditinvs' => 1,
            'enablesellprevious' => 1,
            'enablesuggestsales' => 0,
            'enableautofilldamaged' => 1,
            'enablepromotrxn' => 1,
            'enableautofillreturns' => 1,
            'enablesalestrxn' => 1,
            'enablesigcapture' => 1,
            'enabledamagedreturns' => 1,
            'enablereturnstrxn' => 1,
            'enableexchangetrxn' => 0,
            'enablearcollection' => 0,
            'enablesurveyaudit' => 0,
            'enabledelivinstruct' => 0,
            'enableinvoicecomment' => 0,
            'invoicedetailentry' => 0,
            'orderdetailentry' => 0,
            'authorizeditemlistctl' => 0,
            'orderformat' => 0,
            'autosettlecollection' => 0,
            'enableupcprint' => 0,
            'enabledelayprint' => 0,
            'enableinvoicecopy' => 0,
            'enablerental' => 0,
            'enableposequipment' => 0,
            'enableadvancepayment' => 0,
            'enablebuybackfree' => 0,
            'enableautofillsales' => 0,
            'enablebatchselection' => 0,
            'enablereturnpassword' => 0,
            'invoiceformat' => 1,
            'printlanguageflag' => 1,
            'fixedlatitude' => null,
            'fixedlongitude' => null,
            'visualcode' => null,
            'enablepromoeditinvs' => 0,
            'roundnetamount' => 0,
            'roundingoffvalue' => 0,
            'enablepromoeditords' => 0,
            'histmaxdeliveries' => null,
            'forwardcoverfactor' => null,
            'enforcepromotion' => 0,
            'invoicelimiter' => 0,
            'enabledraftcopy' => 0,
            'graceperiod' => 0,
            'printoutletitemcode' => 0,
            'allowcashoncreditexceed' => 0,
            'memo1' => '',
            'memo2' => '',
            'traname' => '',
            'tranamearabic' => '',
            'taxregistrationnumber' => '',
            'customertaxidoptions' => 0,
            'applytax' => 0,
            'shoptelephonenumber' => '',
            'shopfaxnumber' => '',
            'ownername' => '',
            'ownerlandlinenumber' => '',
            'ownermobilenumber' => '',
            'contactname' => '',
            'contactpersonlandlinenumber' => '',
            'contactpersonmobilenumber' => '',
            'contactpersonemail' => '',
            'purchasemanagername' => '',
            'purchasemanagerlandlinenumber' => '',
            'purchasemanagermobilenumber' => '',
            'purchasemanageremail' => '',
            'warehousemanagername' => '',
            'warehousemanagerlandlinenumber' => '',
            'warehousemanagermobilenumber' => '',
            'warehousemanageremail' => '',
            'created' => null,
            'cdat' => null,
            'modified' => null,
            'mdat' => null,
        ];
    }

    private function validatedData(Request $request, ?CustomerMaster $customer = null): array
    {
        $data = $this->normalizeLegacyOptionDefaults($request->all());

        $data = validator($data, [
            'templatename' => ['required', 'string', 'max:100'],
            'routecode' => ['nullable', 'integer', Rule::exists('routemaster', 'routecode')],
            'headofficecode' => ['nullable', 'integer', Rule::exists('customermaster', 'customercode')],
            'customername' => ['nullable', 'string', 'max:255'],
            'arbcustomername' => ['nullable', 'string', 'max:250'],
            'alternatecode' => [
                'nullable',
                'string',
                'max:50',
                Rule::unique('customermaster', 'alternatecode')->ignore($customer?->customercode, 'customercode'),
            ],
            'barcode' => ['nullable', 'string', 'max:20'],
            'customerphone' => ['nullable', 'string', 'max:30'],
            'customeraddress1' => ['nullable', 'string', 'max:255'],
            'customeraddress2' => ['nullable', 'string', 'max:255'],
            'customeraddress3' => ['nullable', 'string', 'max:255'],
            'customercity' => ['nullable', 'string', 'max:100'],
            'customerzip' => ['nullable', 'string', 'max:10'],
            'pobox' => ['nullable', 'string', 'max:30'],
            'customertype' => ['nullable', Rule::in(['1', '2', '3'])],
            'channelcode' => ['nullable', 'integer', Rule::exists('channelmaster', 'channelcode')],
            'customercategory' => ['nullable', 'integer', Rule::exists('categorymaster', 'categoryid')],
            'currencycode' => ['nullable', 'integer', Rule::exists('currencymaster', 'currencycode')],
            'invoicepaymentterms' => ['required', 'integer', Rule::in([0, 1, 2, 3, 4])],
            'creditlimit' => ['nullable', 'numeric'],
            'creditlimitdays' => ['nullable', 'integer', 'min:0'],
            'activecustomer' => ['required', 'integer', Rule::in([0, 1])],
            'arcustomertype' => ['required', 'integer', Rule::in([0, 1])],
            'tclimit' => ['nullable', 'integer', 'min:0'],
            'messagekey1' => ['nullable', 'integer', Rule::exists('customermessages', 'messagekey')],
            'messagekey2' => ['nullable', 'integer', Rule::exists('customermessages', 'messagekey')],
            'messagekey3' => ['nullable', 'integer', Rule::exists('customermessages', 'messagekey')],
            'messagekey4' => ['nullable', 'integer', Rule::exists('customermessages', 'messagekey')],
            'messagekey5' => ['nullable', 'integer', Rule::exists('customermessages', 'messagekey')],
            'messagekey6' => ['nullable', 'integer', Rule::exists('customermessages', 'messagekey')],
            'invoiceformatoption' => ['required', 'integer', Rule::in([1, 2])],
            'printsequence' => ['required', 'integer', Rule::in([1, 2, 3, 4])],
            'forcestockcapture' => ['required', 'integer', Rule::in([0, 1, 2])],
            'invoicepriceprint' => ['required', 'integer', Rule::in([0, 1])],
            'enablepriceeditinvs' => ['required', 'integer', Rule::in([1, 2, 3, 4, 5, 6, 7, 8])],
            'enablesellprevious' => ['required', 'integer', Rule::in([1, 2])],
            'enablesuggestsales' => ['required', 'integer', Rule::in([0, 1])],
            'enableautofilldamaged' => ['required', 'integer', Rule::in([1, 2, 3, 4])],
            'enablepromotrxn' => ['required', 'integer', Rule::in([1, 2, 3, 4])],
            'enableautofillreturns' => ['required', 'integer', Rule::in([1, 2, 3, 4])],
            'enablesalestrxn' => ['required', 'integer', Rule::in([1, 2, 3, 4])],
            'enablesigcapture' => ['required', 'integer', Rule::in([1, 2, 3])],
            'enabledamagedreturns' => ['required', 'integer', Rule::in([1, 2, 3, 4])],
            'enablereturnstrxn' => ['required', 'integer', Rule::in([1, 2, 3, 4])],
            'enableexchangetrxn' => ['required', 'integer', Rule::in([0, 1])],
            'enablearcollection' => ['required', 'integer', Rule::in([0, 1])],
            'enablesurveyaudit' => ['required', 'integer', Rule::in([0, 1])],
            'enabledelivinstruct' => ['required', 'integer', Rule::in([0, 1])],
            'enableinvoicecomment' => ['required', 'integer', Rule::in([0, 1])],
            'invoicedetailentry' => ['required', 'integer', Rule::in([0, 1])],
            'orderdetailentry' => ['required', 'integer', Rule::in([0, 1])],
            'authorizeditemlistctl' => ['required', 'integer', Rule::in([0, 1])],
            'orderformat' => ['required', 'integer', Rule::in([0, 1])],
            'autosettlecollection' => ['required', 'integer', Rule::in([0, 1])],
            'enableupcprint' => ['required', 'integer', Rule::in([0, 1])],
            'enabledelayprint' => ['required', 'integer', Rule::in([0, 1])],
            'enableinvoicecopy' => ['required', 'integer', Rule::in([0, 1])],
            'enablerental' => ['required', 'integer', Rule::in([0, 1])],
            'enableposequipment' => ['required', 'integer', Rule::in([0, 1])],
            'enableadvancepayment' => ['required', 'integer', Rule::in([0, 1])],
            'enablebuybackfree' => ['required', 'integer', Rule::in([0, 1])],
            'enableautofillsales' => ['required', 'integer', Rule::in([0, 1])],
            'enablebatchselection' => ['required', 'integer', Rule::in([0, 1])],
            'enablereturnpassword' => ['required', 'integer', Rule::in([0, 1])],
            'invoiceformat' => ['required', 'integer', Rule::in([1, 2])],
            'printlanguageflag' => ['required', 'integer', Rule::in([1, 2])],
            'fixedlatitude' => ['nullable', 'numeric'],
            'fixedlongitude' => ['nullable', 'numeric'],
            'visualcode' => $this->visualCodeRules(),
            'enablepromoeditinvs' => ['required', 'integer', Rule::in([0, 1, 2, 3])],
            'roundnetamount' => ['required', 'integer', Rule::in([0, 1, 2])],
            'roundingoffvalue' => ['required', 'integer', Rule::in([0, 1, 2])],
            'enablepromoeditords' => ['required', 'integer', Rule::in([0, 1])],
            'histmaxdeliveries' => ['nullable', 'integer', 'min:0'],
            'forwardcoverfactor' => ['nullable', 'numeric'],
            'enforcepromotion' => ['required', 'integer', Rule::in([0, 1])],
            'invoicelimiter' => ['required', 'integer', Rule::in([0, 1])],
            'enabledraftcopy' => ['required', 'integer', Rule::in([0, 1])],
            'graceperiod' => ['nullable', 'integer', 'min:0'],
            'printoutletitemcode' => ['required', 'integer', Rule::in([0, 1])],
            'allowcashoncreditexceed' => ['required', 'integer', Rule::in([0, 1])],
            'memo1' => ['nullable', 'string', 'max:50'],
            'memo2' => ['nullable', 'string', 'max:50'],
            'traname' => ['nullable', 'string', 'max:100'],
            'tranamearabic' => ['nullable', 'string', 'max:100'],
            'taxregistrationnumber' => ['nullable', 'string', 'max:50'],
            'customertaxidoptions' => ['required', 'integer', Rule::in([0, 1, 2])],
            'applytax' => ['required', 'integer', Rule::in([0, 1, 2])],
            'shoptelephonenumber' => ['nullable', 'string', 'max:30'],
            'shopfaxnumber' => ['nullable', 'string', 'max:30'],
            'ownername' => ['nullable', 'string', 'max:30'],
            'ownerlandlinenumber' => ['nullable', 'string', 'max:30'],
            'ownermobilenumber' => ['nullable', 'string', 'max:30'],
            'contactname' => ['nullable', 'string', 'max:255'],
            'contactpersonlandlinenumber' => ['nullable', 'string', 'max:30'],
            'contactpersonmobilenumber' => ['nullable', 'string', 'max:30'],
            'contactpersonemail' => ['nullable', 'email', 'max:30'],
            'purchasemanagername' => ['nullable', 'string', 'max:30'],
            'purchasemanagerlandlinenumber' => ['nullable', 'string', 'max:30'],
            'purchasemanagermobilenumber' => ['nullable', 'string', 'max:30'],
            'purchasemanageremail' => ['nullable', 'email', 'max:30'],
            'warehousemanagername' => ['nullable', 'string', 'max:30'],
            'warehousemanagerlandlinenumber' => ['nullable', 'string', 'max:30'],
            'warehousemanagermobilenumber' => ['nullable', 'string', 'max:30'],
            'warehousemanageremail' => ['nullable', 'email', 'max:30'],
        ])->validate();

        foreach ([
            'headofficecode', 'arbcustomername', 'barcode', 'customerphone', 'customeraddress1', 'customeraddress2',
            'customeraddress3', 'customercity', 'customerzip', 'pobox', 'channelcode', 'customercategory', 'currencycode',
            'creditlimit', 'creditlimitdays', 'tclimit', 'messagekey1', 'messagekey2', 'messagekey3', 'messagekey4',
            'messagekey5', 'messagekey6', 'visualcode', 'fixedlatitude', 'fixedlongitude', 'histmaxdeliveries',
            'forwardcoverfactor', 'memo1', 'memo2', 'traname', 'tranamearabic', 'taxregistrationnumber',
            'shoptelephonenumber', 'shopfaxnumber', 'ownername', 'ownerlandlinenumber', 'ownermobilenumber', 'contactname',
            'contactpersonlandlinenumber', 'contactpersonmobilenumber', 'contactpersonemail', 'purchasemanagername',
            'purchasemanagerlandlinenumber', 'purchasemanagermobilenumber', 'purchasemanageremail', 'warehousemanagername',
            'warehousemanagerlandlinenumber', 'warehousemanagermobilenumber', 'warehousemanageremail',
        ] as $key) {
            if (array_key_exists($key, $data) && $data[$key] === '') {
                $data[$key] = null;
            }
        }

        if ($data['customertype'] !== '2') {
            $data['headofficecode'] = null;
        }

        $scope = app(AccessScopeService::class);

        if (! $scope->allows(request()->user(), 'route', $data['routecode'] ?? null)) {
            throw ValidationException::withMessages([
                'routecode' => 'Selected route is outside your access scope.',
            ]);
        }

        if (! empty($data['headofficecode'])) {
            $allowedHeadOffice = CustomerMaster::query()
                ->where('customercode', (int) $data['headofficecode'])
                ->whereIn('routecode', $scope->ids(request()->user(), 'route')?->all() ?? [])
                ->exists();

            if (! $allowedHeadOffice) {
                throw ValidationException::withMessages([
                    'headofficecode' => 'Selected head office customer is outside your access scope.',
                ]);
            }
        }

        $data['customertype'] = $data['customertype'] ?: '1';
        $data['customername'] = $data['customername'] ?: $data['templatename'];

        if (! filled($data['alternatecode'])) {
            $data['alternatecode'] = 'TMPL-' . ($customer?->customercode ?? $request->input('customercode'));
        }

        if ((int) $data['arcustomertype'] !== 1) {
            $data['tclimit'] = null;
        }

        if (in_array((int) $data['invoicepaymentterms'], [0, 1], true)) {
            $data['creditlimit'] = 0;
            $data['creditlimitdays'] = 0;
            $data['tclimit'] = 0;
            $data['arcustomertype'] = 0;
        }

        $data['balance'] = $customer?->balance ?? 0;

        return $data;
    }

    private function nextCustomerCode(): int
    {
        return ((int) DB::table('customermaster')->max('customercode')) + 1;
    }

    private function statusOptions(): array
    {
        return [
            ['id' => 1, 'label' => 'Active'],
            ['id' => 0, 'label' => 'Inactive'],
        ];
    }

    private function customerTypeOptions(): array
    {
        return [
            ['id' => '1', 'label' => 'Normal'],
            ['id' => '2', 'label' => 'Branch'],
            ['id' => '3', 'label' => 'Head Office'],
        ];
    }

    private function invoicePaymentTermOptions(): array
    {
        return [
            ['id' => 0, 'label' => 'CASH Only'],
            ['id' => 1, 'label' => 'CASH or CHEQUE'],
            ['id' => 2, 'label' => 'CHARGE Only (GC)'],
            ['id' => 3, 'label' => 'TC (CASH or CHEQUE)'],
            ['id' => 4, 'label' => 'TC (CASH Only)'],
        ];
    }

    private function arCustomerTypeOptions(): array
    {
        return [
            ['id' => 0, 'label' => 'Normal'],
            ['id' => 1, 'label' => 'Bill to Bill'],
        ];
    }

    private function printSequenceOptions(): array
    {
        return [
            ['id' => 1, 'label' => 'Itemmaster.ActualItemCode'],
            ['id' => 2, 'label' => 'Itemmaster.AlternateCode'],
            ['id' => 3, 'label' => 'Itemmaster.PrintSequenceCustomer'],
            ['id' => 4, 'label' => 'OutLetCode'],
        ];
    }

    private function stockCaptureOptions(): array
    {
        return [
            ['id' => 0, 'label' => 'Disable'],
            ['id' => 1, 'label' => 'Enable'],
            ['id' => 2, 'label' => 'Force Capture'],
        ];
    }

    private function invoicePricePrintOptions(): array
    {
        return $this->yesNoOptions();
    }

    private function editPriceOptions(): array
    {
        return [
            ['id' => 1, 'label' => 'Disable'],
            ['id' => 2, 'label' => 'Allow Chgs to sale and Rtn Prices'],
            ['id' => 3, 'label' => 'Allow Chgs to Return Prices Only'],
            ['id' => 4, 'label' => 'Allow Chgs to Sales and Good Rtrn Only'],
            ['id' => 5, 'label' => 'Allow Chgs to Sales and Bad Rtrn Only'],
            ['id' => 6, 'label' => 'Allow Chgs to Sales Only'],
            ['id' => 7, 'label' => 'Allow Chgs to Good Rtrn Only'],
            ['id' => 8, 'label' => 'Allow Chgs to Bad Rtrn Only'],
        ];
    }

    private function sellPreviousOptions(): array
    {
        return [
            ['id' => 1, 'label' => 'Disable'],
            ['id' => 2, 'label' => 'Allow Resale of Previous Trxns'],
        ];
    }

    private function suggestSalesOptions(): array
    {
        return [
            ['id' => 0, 'label' => 'Disable'],
            ['id' => 1, 'label' => 'Enabled'],
        ];
    }

    private function autoFillOptions(): array
    {
        return [
            ['id' => 1, 'label' => 'Disable'],
            ['id' => 2, 'label' => 'Fill All Items'],
            ['id' => 3, 'label' => 'Fill Items From Sales With Qty.'],
            ['id' => 4, 'label' => 'Fill Items From Sales Without Qty.'],
        ];
    }

    private function salesTransactionOptions(): array
    {
        return [
            ['id' => 1, 'label' => 'Disable'],
            ['id' => 2, 'label' => 'Allow on Order Transaction'],
            ['id' => 3, 'label' => 'Allow on Invoice Transaction'],
            ['id' => 4, 'label' => 'Both'],
        ];
    }

    private function signatureCaptureOptions(): array
    {
        return [
            ['id' => 1, 'label' => 'Disable'],
            ['id' => 2, 'label' => 'Enable Signature Capture'],
            ['id' => 3, 'label' => 'Enable and Print on Order/Invoice'],
        ];
    }

    private function invoiceFormatOptions(): array
    {
        return [
            ['id' => 1, 'label' => 'Net Sales and Return'],
            ['id' => 2, 'label' => 'Split Sales and Return'],
        ];
    }

    private function printLanguageOptions(): array
    {
        return [
            ['id' => 1, 'label' => 'English/Arabic'],
            ['id' => 2, 'label' => 'Arabic'],
        ];
    }

    private function promoEditOptions(): array
    {
        return [
            ['id' => 0, 'label' => 'Disable'],
            ['id' => 1, 'label' => 'Sales Only'],
            ['id' => 2, 'label' => 'Return Only'],
            ['id' => 3, 'label' => 'Both'],
        ];
    }

    private function roundingOptions(): array
    {
        return [
            ['id' => 0, 'label' => 'Disable'],
            ['id' => 1, 'label' => 'Allow Line Level Rounding'],
            ['id' => 2, 'label' => 'Allow Invoice Level Rounding'],
        ];
    }

    private function taxPrintOptions(): array
    {
        return [
            ['id' => 0, 'label' => 'Do not Print'],
            ['id' => 1, 'label' => 'Print Tax In Amount'],
            ['id' => 2, 'label' => 'Print Tax In Percent (%)'],
        ];
    }

    private function applyTaxOptions(): array
    {
        return [
            ['id' => 0, 'label' => 'Disabled'],
            ['id' => 1, 'label' => 'Apply Tax for Sales & Returns Only'],
            ['id' => 2, 'label' => 'Apply Tax for All'],
        ];
    }

    private function yesNoOptions(): array
    {
        return [
            ['id' => 0, 'label' => 'Disable'],
            ['id' => 1, 'label' => 'Enable'],
        ];
    }

    private function planogramOptions()
    {
        if (!Schema::hasTable('visualheader')) {
            return collect();
        }

        return DB::table('visualheader')
            ->orderBy('visualcode')
            ->get(['visualcode as id', DB::raw("CONCAT(visualcode, ' -- ', visualdescription) as label")]);
    }

    private function useGracePeriod(): bool
    {
        if (!Schema::hasTable('controlpanel')) {
            return false;
        }

        return (int) DB::table('controlpanel')
            ->where('flagname', 'Use Grace Period')
            ->value('status') === 1;
    }

    private function visualCodeRules(): array
    {
        $rules = ['nullable', 'integer'];

        if (Schema::hasTable('visualheader')) {
            $rules[] = Rule::exists('visualheader', 'visualcode');
        }

        return $rules;
    }

    private function normalizeLegacyOptionDefaults(array $data): array
    {
        $fallbacks = [
            'invoiceformatoption' => 1,
            'printsequence' => 1,
            'forcestockcapture' => 0,
            'invoicepriceprint' => 0,
            'enablepriceeditinvs' => 1,
            'enablesellprevious' => 1,
            'enablesuggestsales' => 0,
            'enableautofilldamaged' => 1,
            'enablepromotrxn' => 1,
            'enableautofillreturns' => 1,
            'enablesalestrxn' => 1,
            'enablesigcapture' => 1,
            'enabledamagedreturns' => 1,
            'enablereturnstrxn' => 1,
            'enableexchangetrxn' => 0,
            'enablearcollection' => 0,
            'enablesurveyaudit' => 0,
            'enabledelivinstruct' => 0,
            'enableinvoicecomment' => 0,
            'invoicedetailentry' => 0,
            'orderdetailentry' => 0,
            'authorizeditemlistctl' => 0,
            'orderformat' => 0,
            'autosettlecollection' => 0,
            'enableupcprint' => 0,
            'enabledelayprint' => 0,
            'enableinvoicecopy' => 0,
            'enablerental' => 0,
            'enableposequipment' => 0,
            'enableadvancepayment' => 0,
            'enablebuybackfree' => 0,
            'enableautofillsales' => 0,
            'enablebatchselection' => 0,
            'enablereturnpassword' => 0,
            'invoiceformat' => 1,
            'printlanguageflag' => 1,
            'enablepromoeditinvs' => 0,
            'roundnetamount' => 0,
            'roundingoffvalue' => 0,
            'enablepromoeditords' => 0,
            'enforcepromotion' => 0,
            'invoicelimiter' => 0,
            'enabledraftcopy' => 0,
            'printoutletitemcode' => 0,
            'allowcashoncreditexceed' => 0,
            'customertaxidoptions' => 0,
            'applytax' => 0,
        ];

        foreach ($fallbacks as $field => $default) {
            if (!array_key_exists($field, $data)) {
                continue;
            }

            if ($data[$field] === null || $data[$field] === '' || (string) $data[$field] === '0') {
                $data[$field] = $default;
            }
        }

        return $data;
    }
}
