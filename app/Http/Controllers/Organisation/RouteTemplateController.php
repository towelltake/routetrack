<?php

namespace App\Http\Controllers\Organisation;

use App\Http\Controllers\Controller;
use App\Models\RouteMaster;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class RouteTemplateController extends Controller
{
    public function index(): Response
    {
        $search = request('search');
        $perPage = (int) request('per_page', 10);
        $perPage = in_array($perPage, [10, 25, 50, 100], true) ? $perPage : 10;

        $templates = RouteMaster::query()
            ->from('routemaster as route')
            ->leftJoin('routecategory as rc', 'rc.routecatcode', '=', 'route.routecatcode')
            ->where('route.routetmpl', 1)
            ->when($search, function ($query, $searchTerm) {
                $query->where(function ($inner) use ($searchTerm) {
                    $inner->where('route.routecode', 'like', '%' . $searchTerm . '%')
                        ->orWhere('route.templatename', 'like', '%' . $searchTerm . '%')
                        ->orWhere('rc.routecatname', 'like', '%' . $searchTerm . '%');
                });
            })
            ->orderBy('route.routecode')
            ->paginate($perPage, [
                'route.routecode',
                'route.templatename',
                'route.routetype',
                'route.routecatcode',
                'route.activestatus',
                'route.created',
                'route.cdat',
                'route.modified',
                'route.mdat',
                'rc.routecatname',
            ])
            ->withQueryString();

        return Inertia::render('organisation/routetemplate/Index', [
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
        $props['templateData']['routecode'] = $this->nextRouteCode();

        return Inertia::render('organisation/routetemplate/Create', $props);
    }

    public function show(RouteMaster $routeTemplate): Response
    {
        abort_unless((int) $routeTemplate->routetmpl === 1, 404);

        return Inertia::render('organisation/routetemplate/View', $this->formProps($routeTemplate));
    }

    public function edit(RouteMaster $routeTemplate): Response
    {
        abort_unless((int) $routeTemplate->routetmpl === 1, 404);

        return Inertia::render('organisation/routetemplate/Edit', $this->formProps($routeTemplate));
    }

    public function store(Request $request): RedirectResponse
    {
        $payload = $this->validatedData($request);
        $payload['routetmpl'] = 1;
        $payload['created'] = auth()->user()->name;
        $payload['cdat'] = now();
        $payload['modified'] = auth()->user()->name;
        $payload['mdat'] = now();

        RouteMaster::create($payload);

        return redirect()
            ->route('organisation.routetemplate.index')
            ->with('success', 'Route template created.');
    }

    public function update(Request $request, RouteMaster $routeTemplate): RedirectResponse
    {
        abort_unless((int) $routeTemplate->routetmpl === 1, 404);

        $payload = $this->validatedData($request, $routeTemplate);
        $payload['routetmpl'] = 1;
        $payload['modified'] = auth()->user()->name;
        $payload['mdat'] = now();

        $routeTemplate->update($payload);

        return redirect()
            ->route('organisation.routetemplate.index')
            ->with('success', 'Route template updated.');
    }

    public function destroy(RouteMaster $routeTemplate): RedirectResponse
    {
        abort_unless((int) $routeTemplate->routetmpl === 1, 404);

        try {
            $routeTemplate->delete();
        } catch (\Throwable $e) {
            return back()->with('error', 'Cannot delete: record is in use.');
        }

        return back()->with('success', 'Route template deleted.');
    }

    private function formProps(?RouteMaster $routeTemplate = null): array
    {
        return [
            'templateData' => $this->templateFormData($routeTemplate),
            'lookupOptions' => [
                'routeCategories' => DB::table('routecategory')
                    ->orderBy('routecatname')
                    ->get(['routecatcode as id', 'routecatname as label']),
                'routeItemGroups' => DB::table('routeitemgrp')
                    ->orderBy('description')
                    ->get(['routeitemgrpcode as id', 'description as label']),
                'itemMustKeys' => $this->itemMustKeyOptions(),
            ],
            'optionSets' => [
                'statusOptions' => [
                    ['id' => 1, 'label' => 'Active'],
                    ['id' => 0, 'label' => 'Inactive'],
                ],
                'routeTypes' => $this->routeTypes(),
                'printOptions' => [
                    ['id' => 0, 'label' => 'Disable Print'],
                    ['id' => 1, 'label' => 'Optional (User Choice)'],
                    ['id' => 2, 'label' => 'Force Print'],
                ],
                'promptOdometerOptions' => [
                    ['id' => 0, 'label' => 'No Prompt'],
                    ['id' => 1, 'label' => 'Prompt at Start/End Day'],
                    ['id' => 2, 'label' => 'Prompt at Start/End Day + Each Visit'],
                ],
                'inventoryCaseInputOptions' => [
                    ['id' => 0, 'label' => 'Inventory By Units Only'],
                    ['id' => 1, 'label' => 'Enter By Units, Print by Cases/Units'],
                    ['id' => 2, 'label' => 'Inventory By Cases/Units'],
                ],
                'loadRequestReportOptions' => [
                    ['id' => 0, 'label' => 'Disable Load Request'],
                    ['id' => 1, 'label' => 'Request Qty Only Report'],
                    ['id' => 2, 'label' => 'Add On Qty Report'],
                ],
                'autoCalculateLoadInOptions' => [
                    ['id' => 0, 'label' => 'Disable'],
                    ['id' => 1, 'label' => 'Calc Unload Qtys; Disable Chgs'],
                    ['id' => 2, 'label' => 'Calc Unload Qtys; Enable Chgs'],
                    ['id' => 3, 'label' => 'Calc End Inv. Qtys; Disable Chgs'],
                    ['id' => 4, 'label' => 'Calc End Inv. Qtys; Enable Chgs'],
                ],
                'requireLoadInOptions' => [
                    ['id' => 0, 'label' => 'Disable'],
                    ['id' => 1, 'label' => 'Prompt Before Settlement'],
                    ['id' => 2, 'label' => 'Require Before Settlement'],
                ],
                'inventoryValuePrintOptions' => [
                    ['id' => 0, 'label' => 'Do not Print Inventory Values'],
                    ['id' => 1, 'label' => 'Include Inventory Values on Report Totals'],
                ],
                'itemCodeDisplayOptions' => [
                    ['id' => 0, 'label' => 'Display Actual Item Code'],
                    ['id' => 1, 'label' => 'Display Alternate Item Code'],
                ],
                'printAlternateOptions' => [
                    ['id' => 0, 'label' => 'Disabled'],
                    ['id' => 1, 'label' => 'Print Alternate Code and Actual Item Code'],
                    ['id' => 2, 'label' => 'Print Alternate Code Only'],
                ],
                'itemDescriptionDisplayOptions' => [
                    ['id' => 0, 'label' => 'Display Item Description'],
                    ['id' => 1, 'label' => 'Display Item Long Description'],
                ],
                'useAlternateCodeOptions' => [
                    ['id' => 0, 'label' => 'Display Actual Customer Code'],
                    ['id' => 1, 'label' => 'Display Alternate Code'],
                ],
                'enableLoadTransferOptions' => [
                    ['id' => 0, 'label' => 'Disable'],
                    ['id' => 1, 'label' => 'Transfer In Only'],
                    ['id' => 2, 'label' => 'Transfer Out Only'],
                    ['id' => 3, 'label' => 'Damage Only'],
                    ['id' => 4, 'label' => 'Transfer In and Transfer Out'],
                    ['id' => 5, 'label' => 'Transfer In and Damage'],
                    ['id' => 6, 'label' => 'Transfer Out and Damage'],
                    ['id' => 7, 'label' => 'Enable All'],
                ],
                'scannerUseOptions' => [
                    ['id' => 0, 'label' => 'Manual'],
                    ['id' => 1, 'label' => 'Manual/Scanning'],
                    ['id' => 2, 'label' => 'Scanning Only'],
                ],
                'enableNoSaleOptions' => [
                    ['id' => 0, 'label' => 'Disable'],
                    ['id' => 1, 'label' => 'Enable No Sale at Point of Sale'],
                    ['id' => 2, 'label' => 'Enable No Sale With Unserviced Customer Print'],
                ],
                'cashBalanceOptions' => [
                    ['id' => 0, 'label' => 'Balance Should be 0.00'],
                    ['id' => 1, 'label' => 'Allow Over/Short Deposit Report'],
                ],
                'inventoryVarianceOptions' => [
                    ['id' => 0, 'label' => 'Disable'],
                    ['id' => 1, 'label' => 'Variance not Due at End Day'],
                    ['id' => 2, 'label' => 'Variance Due at End Day'],
                    ['id' => 3, 'label' => 'Shortages Only Due at End Day'],
                ],
                'inventoryOversellOptions' => [
                    ['id' => 0, 'label' => 'Disable'],
                    ['id' => 1, 'label' => 'Enable Over Sell of Inventory'],
                ],
                'damagedTransactionOptions' => [
                    ['id' => 0, 'label' => 'Disable'],
                    ['id' => 1, 'label' => 'Allow unloading damaged return mid-day'],
                ],
                'displayInventorySummaryOptions' => [
                    ['id' => 0, 'label' => 'Disable'],
                    ['id' => 1, 'label' => 'Display after invoice exit'],
                    ['id' => 2, 'label' => 'Display after each transaction and invoice exit'],
                ],
                'includeLoadRequestOptions' => [
                    ['id' => 0, 'label' => 'Disable'],
                    ['id' => 1, 'label' => 'Enable'],
                ],
                'loadRequestRollupOptions' => [
                    ['id' => 0, 'label' => 'Add Load Request'],
                    ['id' => 1, 'label' => 'Suggested Load Request from Average Sales'],
                    ['id' => 2, 'label' => 'Roll up Orders for Next Load Request'],
                    ['id' => 3, 'label' => 'Request only MSL items'],
                ],
                'loadRequestMethodOptions' => [
                    ['id' => 0, 'label' => 'View only'],
                    ['id' => 1, 'label' => 'Editable'],
                ],
                'printerOptions' => [
                    ['id' => 0, 'label' => 'None'],
                    ['id' => 1, 'label' => 'Zebra RW420'],
                    ['id' => 2, 'label' => 'Intermec PB42'],
                    ['id' => 3, 'label' => 'Intermec 6822'],
                ],
                'depotPrinterOptions' => [
                    ['id' => 0, 'label' => 'Printer 1 - 6820 Cabled'],
                    ['id' => 1, 'label' => 'Printer 2 - 6820 IrDA'],
                    ['id' => 2, 'label' => 'Printer 3 - 6804T Cabled'],
                    ['id' => 3, 'label' => 'Printer 4 - 6804T IrDA'],
                    ['id' => 4, 'label' => 'Printer 5 - 6804DM Cabled'],
                    ['id' => 5, 'label' => 'Printer 6 - 6804DM IrDA'],
                    ['id' => 6, 'label' => 'Printer 7 - 6805A'],
                    ['id' => 7, 'label' => 'Printer 8 - 6806'],
                ],
                'decimalDigitOptions' => [
                    ['id' => 0, 'label' => '0'],
                    ['id' => 1, 'label' => '1'],
                    ['id' => 2, 'label' => '2'],
                    ['id' => 3, 'label' => '3'],
                    ['id' => 4, 'label' => '4'],
                ],
            ],
        ];
    }

    private function templateFormData(?RouteMaster $routeTemplate): array
    {
        $record = $routeTemplate?->toArray() ?? [];

        return array_merge($this->defaultTemplateData(), array_intersect_key(
            $record,
            array_flip(array_keys($this->defaultTemplateData()))
        ));
    }

    private function defaultTemplateData(): array
    {
        return [
            'routecode' => null,
            'templatename' => '',
            'routecatcode' => null,
            'routetype' => 0,
            'activestatus' => 1,
            'routeitemgrpcode' => null,
            'depotrouteflag' => 0,
            'presalesorder' => 0,
            'itemmustkey' => null,
            'password1' => null,
            'password2' => null,
            'password3' => null,
            'password4' => null,
            'password5' => null,
            'passwordarray01' => null,
            'passwordarray02' => null,
            'passwordarray03' => null,
            'passwordarray04' => null,
            'passwordarray05' => null,
            'passwordarray06' => null,
            'passwordarray07' => null,
            'passwordarray08' => null,
            'passwordarray09' => null,
            'passwordarray10' => null,
            'passwordarray11' => null,
            'passwordarray12' => null,
            'passwordarray13' => null,
            'passwordarray14' => null,
            'passwordarray15' => null,
            'passwordarray16' => null,
            'unloadoversellmessage' => 0,
            'inventoryvalueprint' => 0,
            'promptodominput' => 0,
            'inventorycaseinput' => 0,
            'loadreqreportformat' => 0,
            'autocalculateloadin' => 0,
            'requireloadin' => 0,
            'loadsheetreport' => 0,
            'amountdecimaldigits' => 0,
            'itemcodedisplay' => 0,
            'usealternatecodes' => 0,
            'itemdescriptiondisplay' => 0,
            'enableloadtransfer' => 0,
            'enablescanneruse' => 0,
            'enableeodaddchecks' => 0,
            'enabledelayprint' => 0,
            'enableaddcustomer' => 0,
            'enforcecallsequence' => 0,
            'enablefoclimit' => 0,
            'enablescancustomer' => 0,
            'loadoutadjustments' => 0,
            'enableeodexpenses' => 0,
            'enablecashonlydiscount' => 0,
            'enablepostvoid' => 0,
            'enableeodadjchecks' => 0,
            'transactionnoseq' => 0,
            'enablefreereason' => 0,
            'inventoryreportcontrol' => 0,
            'enablestartdayrtewkdayedit' => 0,
            'enablestartdaydatetimeedit' => 0,
            'routeunloadvariance' => null,
            'salesmantargetdays' => 1,
            'voidoverride' => 0,
            'enablenosale' => 0,
            'cashbalance' => 0,
            'inventoryvariance' => 0,
            'invenoversell' => 0,
            'enabledamagedtrxn' => 0,
            'displayinvsummary' => 0,
            'includeloadrequest' => 0,
            'loadreqrolluporders' => 0,
            'depotprinter' => 0,
            'loadreqmethod' => 0,
            'routeprinter' => 0,
            'memo1' => '',
            'memo2' => '',
            'enablemiddaytelecom' => 0,
            'enabledraftcopy' => 0,
            'cdcvaliditydays' => null,
            'newcustomerseqnumber' => null,
            'creditlimit' => null,
            'routebalance' => null,
            'vehicleodometer' => null,
            'defaultdeliverydays' => null,
            'allowedradius' => null,
            'pdcthreshold' => null,
            'defaultrequestdays' => null,
            'forcesettlementdays' => null,
            'routecreditcheck' => 0,
            'routecreditlimitdays' => null,
            'updategps' => 0,
            'enforcegps' => 0,
            'enablegps' => 0,
            'routetmpl' => 1,
        ];
    }

    private function validatedData(Request $request, ?RouteMaster $routeTemplate = null): array
    {
        $data = $request->validate([
            'templatename' => [
                'required',
                'string',
                'max:100',
                Rule::unique('routemaster', 'templatename')->ignore($routeTemplate?->routecode, 'routecode'),
            ],
            'routecatcode' => ['nullable', 'integer', Rule::exists('routecategory', 'routecatcode')],
            'routetype' => ['required', 'integer', Rule::in([0, 1, 2, 3])],
            'activestatus' => ['required', 'integer', Rule::in([0, 1])],
            'routeitemgrpcode' => ['nullable', 'integer', Rule::exists('routeitemgrp', 'routeitemgrpcode')],
            'depotrouteflag' => ['nullable', 'integer', Rule::in([0, 1])],
            'presalesorder' => ['nullable', 'integer', Rule::in([0, 1])],
            'itemmustkey' => ['nullable', 'integer'],
            'password1' => ['nullable', 'integer', 'min:0'],
            'password2' => ['nullable', 'integer', 'min:0'],
            'password3' => ['nullable', 'integer', 'min:0'],
            'password4' => ['nullable', 'integer', 'min:0'],
            'password5' => ['nullable', 'integer', 'min:0'],
            'passwordarray01' => ['nullable', 'integer', 'min:0'],
            'passwordarray02' => ['nullable', 'integer', 'min:0'],
            'passwordarray03' => ['nullable', 'integer', 'min:0'],
            'passwordarray04' => ['nullable', 'integer', 'min:0'],
            'passwordarray05' => ['nullable', 'integer', 'min:0'],
            'passwordarray06' => ['nullable', 'integer', 'min:0'],
            'passwordarray07' => ['nullable', 'integer', 'min:0'],
            'passwordarray08' => ['nullable', 'integer', 'min:0'],
            'passwordarray09' => ['nullable', 'integer', 'min:0'],
            'passwordarray10' => ['nullable', 'integer', 'min:0'],
            'passwordarray11' => ['nullable', 'integer', 'min:0'],
            'passwordarray12' => ['nullable', 'integer', 'min:0'],
            'passwordarray13' => ['nullable', 'integer', 'min:0'],
            'passwordarray14' => ['nullable', 'integer', 'min:0'],
            'passwordarray15' => ['nullable', 'integer', 'min:0'],
            'passwordarray16' => ['nullable', 'integer', 'min:0'],
            'unloadoversellmessage' => ['nullable', 'integer'],
            'inventoryvalueprint' => ['nullable', 'integer'],
            'promptodominput' => ['nullable', 'integer'],
            'inventorycaseinput' => ['nullable', 'integer'],
            'loadreqreportformat' => ['nullable', 'integer'],
            'autocalculateloadin' => ['nullable', 'integer'],
            'requireloadin' => ['nullable', 'integer'],
            'loadsheetreport' => ['nullable', 'integer'],
            'amountdecimaldigits' => ['nullable', 'integer'],
            'itemcodedisplay' => ['nullable', 'integer'],
            'usealternatecodes' => ['nullable', 'integer'],
            'itemdescriptiondisplay' => ['nullable', 'integer'],
            'enableloadtransfer' => ['nullable', 'integer'],
            'enablescanneruse' => ['nullable', 'integer'],
            'enableeodaddchecks' => ['nullable', 'integer', Rule::in([0, 1])],
            'enabledelayprint' => ['nullable', 'integer', Rule::in([0, 1])],
            'enableaddcustomer' => ['nullable', 'integer', Rule::in([0, 1])],
            'enforcecallsequence' => ['nullable', 'integer', Rule::in([0, 1])],
            'enablefoclimit' => ['nullable', 'integer', Rule::in([0, 1])],
            'enablescancustomer' => ['nullable', 'integer', Rule::in([0, 1])],
            'loadoutadjustments' => ['nullable', 'integer', Rule::in([0, 1])],
            'enableeodexpenses' => ['nullable', 'integer', Rule::in([0, 1])],
            'enablecashonlydiscount' => ['nullable', 'integer', Rule::in([0, 1])],
            'enablepostvoid' => ['nullable', 'integer', Rule::in([0, 1])],
            'enableeodadjchecks' => ['nullable', 'integer', Rule::in([0, 1])],
            'transactionnoseq' => ['nullable', 'integer', Rule::in([0, 1])],
            'enablefreereason' => ['nullable', 'integer', Rule::in([0, 1])],
            'inventoryreportcontrol' => ['nullable', 'integer'],
            'enablestartdayrtewkdayedit' => ['nullable', 'integer', Rule::in([0, 1])],
            'enablestartdaydatetimeedit' => ['nullable', 'integer', Rule::in([0, 1])],
            'routeunloadvariance' => ['nullable', 'numeric'],
            'salesmantargetdays' => ['nullable', 'integer', 'min:0'],
            'voidoverride' => ['nullable', 'integer', Rule::in([0, 1])],
            'enablenosale' => ['nullable', 'integer'],
            'cashbalance' => ['nullable', 'integer'],
            'inventoryvariance' => ['nullable', 'integer'],
            'invenoversell' => ['nullable', 'integer'],
            'enabledamagedtrxn' => ['nullable', 'integer'],
            'displayinvsummary' => ['nullable', 'integer'],
            'includeloadrequest' => ['nullable', 'integer'],
            'loadreqrolluporders' => ['nullable', 'integer'],
            'depotprinter' => ['nullable', 'integer'],
            'loadreqmethod' => ['nullable', 'integer'],
            'routeprinter' => ['nullable', 'integer'],
            'memo1' => ['nullable', 'string', 'max:50'],
            'memo2' => ['nullable', 'string', 'max:50'],
            'enablemiddaytelecom' => ['nullable', 'integer', Rule::in([0, 1])],
            'enabledraftcopy' => ['nullable', 'integer', Rule::in([0, 1])],
            'cdcvaliditydays' => ['nullable', 'integer', 'min:0'],
            'newcustomerseqnumber' => ['nullable', 'integer', 'min:0'],
            'creditlimit' => ['nullable', 'numeric'],
            'routebalance' => ['nullable', 'numeric'],
            'vehicleodometer' => ['nullable', 'integer', 'min:0'],
            'defaultdeliverydays' => ['nullable', 'integer', 'min:0'],
            'allowedradius' => ['nullable', 'numeric'],
            'pdcthreshold' => ['nullable', 'numeric'],
            'defaultrequestdays' => ['nullable', 'integer', 'min:0'],
            'forcesettlementdays' => ['nullable', 'integer', 'min:0'],
            'routecreditcheck' => ['nullable', 'integer', Rule::in([0, 1])],
            'routecreditlimitdays' => ['nullable', 'integer', 'min:0'],
            'updategps' => ['nullable', 'integer', Rule::in([0, 1])],
            'enforcegps' => ['nullable', 'integer', Rule::in([0, 1])],
            'enablegps' => ['nullable', 'integer', Rule::in([0, 1])],
        ]);

        foreach (array_keys($this->defaultTemplateData()) as $key) {
            if (array_key_exists($key, $data) && $data[$key] === '') {
                $data[$key] = null;
            }
        }

        return $data;
    }

    private function itemMustKeyOptions()
    {
        if (!Schema::hasTable('itemmustheader')) {
            return [];
        }

        return DB::table('itemmustheader')
            ->orderBy('itemmustdescription')
            ->get(['itemmustcode as id', 'itemmustdescription as label']);
    }

    private function routeTypes(): array
    {
        return [
            ['id' => 0, 'label' => '0 - Enable Order and Sales Process (History & Goal for Order Only)'],
            ['id' => 1, 'label' => '1 - Enable Order and Sales Process (History & Goal for Sales Only)'],
            ['id' => 2, 'label' => '2 - Enable Order Process Only'],
            ['id' => 3, 'label' => '3 - Enable Sales Process Only'],
        ];
    }

    private function nextRouteCode(): int
    {
        return ((int) RouteMaster::max('routecode')) + 1;
    }
}
