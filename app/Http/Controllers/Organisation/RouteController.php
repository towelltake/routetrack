<?php

namespace App\Http\Controllers\Organisation;

use App\Http\Controllers\Controller;
use App\Models\RouteMaster;
use App\Services\AccessScopeService;
use App\Support\ExcelXmlWorkbook;
use Illuminate\Database\Query\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\Response as HttpResponse;

class RouteController extends Controller
{
    private ?string $deviceTable = null;
    private ?string $deviceTableMode = null;

    public function index(): Response
    {
        $scope = app(AccessScopeService::class);
        $user = request()->user();
        $search = request('search');
        $perPage = (int) request('per_page', 10);
        $perPage = in_array($perPage, [10, 25, 50, 100], true) ? $perPage : 10;

        $routesQuery = RouteMaster::query()
            ->from('routemaster as route')
            ->leftJoin('salesman as sm', 'sm.salesmancode', '=', 'route.salesmancode')
            ->leftJoin('subareamaster as sa', 'sa.subareacode', '=', 'route.subareacode')
            ->where(function ($query) {
                $query->whereNull('route.routetmpl')
                    ->orWhere('route.routetmpl', 0);
            })
            ->when($search, function ($query, $searchTerm) {
                $query->where(function ($inner) use ($searchTerm) {
                    $inner->where('route.routecode', 'like', '%' . $searchTerm . '%')
                        ->orWhere('route.routename', 'like', '%' . $searchTerm . '%')
                        ->orWhere('route.arbroutename', 'like', '%' . $searchTerm . '%')
                        ->orWhere('route.alternateroutecode', 'like', '%' . $searchTerm . '%')
                        ->orWhere('route.device_assigned_id', 'like', '%' . $searchTerm . '%')
                        ->orWhere('sm.salesmanname1', 'like', '%' . $searchTerm . '%')
                        ->orWhere('sa.subareaname', 'like', '%' . $searchTerm . '%');
                });
            });

        $scope->scopeQuery($user, $routesQuery, 'route', 'route.routecode');

        $routes = $routesQuery
            ->orderBy('route.routecode')
            ->paginate($perPage, [
                'route.routecode',
                'route.routename',
                'route.arbroutename',
                'route.alternateroutecode',
                'route.salesmancode',
                'route.subareacode',
                'route.device_assigned_id',
                'route.activestatus',
                'sm.salesmanname1',
                'sa.subareaname',
            ])
            ->withQueryString();

        return Inertia::render('organisation/route/Index', [
            'filters' => [
                'search' => $search,
                'per_page' => $perPage,
            ],
            'routes' => $routes,
        ]);
    }

    public function create(Request $request): Response
    {
        $templateId = $request->integer('template_id');
        $templateRoute = $templateId ? RouteMaster::find($templateId) : null;
        $props = $this->formProps($templateRoute);
        $props['routeData']['routecode'] = $this->nextRouteCode();

        return Inertia::render('organisation/route/Create', [
            ...$props,
            'selectedTemplateId' => $templateRoute?->routecode,
        ]);
    }

    public function show(RouteMaster $routeMaster): Response
    {
        abort_if((int) $routeMaster->routetmpl === 1, 404);
        abort_unless(app(AccessScopeService::class)->allows(request()->user(), 'route', $routeMaster->routecode), 403);

        return Inertia::render('organisation/route/View', $this->formProps($routeMaster));
    }

    public function edit(RouteMaster $routeMaster): Response
    {
        abort_if((int) $routeMaster->routetmpl === 1, 404);
        abort_unless(app(AccessScopeService::class)->allows(request()->user(), 'route', $routeMaster->routecode), 403);

        return Inertia::render('organisation/route/Edit', $this->formProps($routeMaster));
    }

    public function store(Request $request): RedirectResponse
    {
        $payload = $this->validatedData($request);
        $payload['created'] = auth()->user()->name;
        $payload['cdat'] = now();
        $payload['modified'] = auth()->user()->name;
        $payload['mdat'] = now();

        RouteMaster::create($payload);

        return redirect()
            ->route('organisation.route.index')
            ->with('success', 'Route created.');
    }

    public function update(Request $request, RouteMaster $routeMaster): RedirectResponse
    {
        abort_if((int) $routeMaster->routetmpl === 1, 404);
        abort_unless(app(AccessScopeService::class)->allows($request->user(), 'route', $routeMaster->routecode), 403);

        $payload = $this->validatedData($request, $routeMaster);
        $payload['modified'] = auth()->user()->name;
        $payload['mdat'] = now();

        $routeMaster->update($payload);

        return redirect()
            ->route('organisation.route.index')
            ->with('success', 'Route updated.');
    }

    public function destroy(RouteMaster $routeMaster): RedirectResponse
    {
        abort_if((int) $routeMaster->routetmpl === 1, 404);
        abort_unless(app(AccessScopeService::class)->allows(request()->user(), 'route', $routeMaster->routecode), 403);

        try {
            $routeMaster->delete();
        } catch (\Throwable $e) {
            return back()->with('error', 'Cannot delete: record is in use.');
        }

        return back()->with('success', 'Route deleted.');
    }

    public function downloadBulkImportTemplate(): HttpResponse
    {
        return ExcelXmlWorkbook::download(
            'route-bulk-import-template.xls',
            $this->bulkImportTemplateHeaders(),
            [],
            'Routes'
        );
    }

    public function bulkImport(Request $request): RedirectResponse
    {
        $request->validate([
            'file' => ['required', 'file', 'max:5120'],
        ]);

        try {
            $rows = ExcelXmlWorkbook::parseFile($request->file('file')->getRealPath());
        } catch (\RuntimeException $exception) {
            return back()->withErrors(['file' => $exception->getMessage()]);
        }

        if ($rows === []) {
            return back()->withErrors(['file' => 'The uploaded file does not contain any route rows.']);
        }

        $imported = 0;

        DB::transaction(function () use ($rows, &$imported) {
            foreach ($rows as $index => $row) {
                $payload = $this->mapBulkImportRow($row);

                try {
                    $validated = $this->validateRoutePayload($payload);
                } catch (ValidationException $exception) {
                    $messages = collect($exception->errors())
                        ->flatten()
                        ->implode(' ');

                    throw ValidationException::withMessages([
                        'file' => 'Row ' . ($index + 2) . ': ' . $messages,
                    ]);
                }

                $validated['created'] = auth()->user()->name;
                $validated['cdat'] = now();
                $validated['modified'] = auth()->user()->name;
                $validated['mdat'] = now();

                RouteMaster::create($validated);
                $imported++;
            }
        });

        return redirect()
            ->route('organisation.route.index')
            ->with('success', $imported . ' route(s) imported successfully.');
    }

    private function formProps(?RouteMaster $routeMaster = null): array
    {
        $scope = app(AccessScopeService::class);
        $user = request()->user();

        return [
            'routeData' => $this->routeFormData($routeMaster),
            'lookupOptions' => [
                'companies' => $scope->scopeQuery($user, DB::table('company'), 'company', 'cmpycode')
                    ->orderBy('name')
                    ->get(['cmpycode as id', 'name as label']),
                'regions' => $scope->scopeQuery($user, DB::table('regionmaster'), 'region', 'regionmstcode')
                    ->orderBy('regionmstname')
                    ->get(['regionmstcode as id', 'regionmstname as label']),
                'subAreas' => $scope->scopeQuery($user, DB::table('subareamaster'), 'subarea', 'subareacode')
                    ->orderBy('subareaname')
                    ->get(['subareacode as id', 'subareaname as label']),
                'salesmen' => DB::table('salesman')
                    ->orderBy('salesmanname1')
                    ->get(['salesmancode as id', 'salesmanname1 as label']),
                'vans' => DB::table('vanmaster')
                    ->orderBy('vandescription')
                    ->get(['vancode as id', 'vandescription as label']),
                'routeCategories' => DB::table('routecategory')
                    ->orderBy('routecatname')
                    ->get(['routecatcode as id', 'routecatname as label']),
                'routeItemGroups' => DB::table('routeitemgrp')
                    ->orderBy('description')
                    ->get(['routeitemgrpcode as id', 'description as label']),
                'itemMustKeys' => $this->itemMustKeyOptions(),
                'deviceOptions' => $this->deviceOptions($routeMaster),
                'varianceCustomers' => DB::table('customermaster')
                    ->orderBy('customername')
                    ->limit(500)
                    ->get(['customercode as id', 'customername as label']),
                'routeTemplates' => $this->routeTemplateOptions(),
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
                'enableDisableOptions' => [
                    ['id' => 0, 'label' => 'Disable'],
                    ['id' => 1, 'label' => 'Enable'],
                ],
                'promptOdometerOptions' => [
                    ['id' => 0, 'label' => 'No Prompt'],
                    ['id' => 1, 'label' => 'Prompt at Start/End Day'],
                    ['id' => 2, 'label' => 'Prompt at Start/End Day + Each Visit'],
                ],
                'inventoryCaseInputOptions' => [
                    ['id' => 0, 'label' => 'Inventory By Units Only'],
                    ['id' => 1, 'label' => 'Inventory By Cases/Units'],
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
                    ['id' => 0, 'label' => 'Inventory By Units Only'],
                    ['id' => 1, 'label' => 'Inventory By Cases/Units'],
                ],
                'itemCodeDisplayOptions' => [
                    ['id' => 0, 'label' => 'Display ActualItemCode'],
                    ['id' => 1, 'label' => 'Display AlternateCode'],
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
                    ['id' => 0, 'label' => 'Display ActualCustomerCode'],
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
                    ['id' => 2, 'label' => 'Scanning'],
                ],
                'enableNoSaleOptions' => [
                    ['id' => 0, 'label' => 'Disable'],
                    ['id' => 1, 'label' => 'Enable No Sale at Point of Sale'],
                    ['id' => 2, 'label' => 'Enable No Sale at Point of Sale with Printing of Unserviced Customers'],
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
                    ['id' => 1, 'label' => 'Allow unloading damaged return in the middle of the day.'],
                ],
                'includeLoadRequestOptions' => [
                    ['id' => 0, 'label' => 'Disable'],
                    ['id' => 1, 'label' => 'Enable'],
                ],
                'loadRequestRollupOptions' => [
                    ['id' => 0, 'label' => 'Add Load Request'],
                    ['id' => 1, 'label' => 'Prefill with average sales'],
                    ['id' => 2, 'label' => 'Prefill with order quantities'],
                    ['id' => 3, 'label' => 'Request only MSL Items'],
                ],
                'loadRequestMethodOptions' => [
                    ['id' => 0, 'label' => 'View only'],
                    ['id' => 1, 'label' => 'Editable'],
                ],
                'printerOptions' => [
                    ['id' => 1, 'label' => 'Zebra RW420'],
                    ['id' => 2, 'label' => 'Intermec PB42'],
                    ['id' => 3, 'label' => 'Intermec 6822'],
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

    private function routeFormData(?RouteMaster $routeMaster): array
    {
        $record = $routeMaster?->toArray() ?? [];

        $data = array_merge($this->defaultRouteData(), array_intersect_key(
            $record,
            array_flip(array_keys($this->defaultRouteData()))
        ));

        foreach (['autojp_work_start_time', 'autojp_work_end_time'] as $field) {
            $data[$field] = $this->normalizeTimeValue($data[$field] ?? null);
        }

        return $data;
    }

    private function defaultRouteData(): array
    {
        return [
            'routecode' => null,
            'routename' => '',
            'arbroutename' => '',
            'alternateroutecode' => '',
            'cmpycode' => null,
            'regionmstcode' => null,
            'subareacode' => null,
            'salesmancode' => null,
            'vehiclenumber' => null,
            'routecatcode' => null,
            'routetype' => 0,
            'routeitemgrpcode' => null,
            'itemmustkey' => null,
            'device_assigned_id' => '',
            'activestatus' => 1,
            'presalesorder' => 0,
            'depotrouteflag' => 0,
            'deliveryroute' => 0,
            'allowroutestartdayflag' => 0,
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
            'inventorycaseinput' => 1,
            'loadreqreportformat' => 0,
            'autocalculateloadin' => 3,
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
            'displayinvsummary' => null,
            'includeloadrequest' => 0,
            'loadreqrolluporders' => 0,
            'depotprinter' => null,
            'loadreqmethod' => 0,
            'routeprinter' => null,
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
            'enableautopostingaccount' => 0,
            'variancecustomercode' => null,
            'forcesettlementdays' => null,
            'routecreditcheck' => 0,
            'routecreditlimitdays' => null,
            'updategps' => 0,
            'enforcegps' => 0,
            'enablegps' => 0,
            'autojp_enabled' => 0,
            'autojp_work_start_time' => '08:00',
            'autojp_work_end_time' => '17:00',
            'autojp_working_days' => '1,2,3,4,5',
            'reqeoddepositreport' => 0,
            'reqeodsalesreport' => 0,
            'reqeodrteactivreport' => 0,
            'reqeodrtestlmtreport' => 0,
            'reqeodroutereviewrpt' => 0,
            'reqeodrtnexchreport' => 0,
            'reqeodplacementsrpt' => 0,
            'reqeodprcchgreport' => 0,
            'reqeodpromosreport' => 0,
            'reqeodnosalereport' => 0,
            'reqeodnondelreport' => 0,
            'reqeodexceptionrpt' => 0,
            'reqeodunauthbalance' => 0,
            'reqeodroasummary' => 0,
            'reqeodnonscannedreport' => 0,
            'reqeododomlogreport' => 0,
        ];
    }

    private function validatedData(Request $request, ?RouteMaster $routeMaster = null): array
    {
        return $this->validateRoutePayload($request->all(), $routeMaster);
    }

    private function validateRoutePayload(array $data, ?RouteMaster $routeMaster = null): array
    {
        $data = Validator::make($data, [
            'routename' => [
                'required',
                'string',
                'max:50',
                Rule::unique('routemaster', 'routename')->ignore($routeMaster?->routecode, 'routecode'),
            ],
            'arbroutename' => ['nullable', 'string', 'max:50'],
            'alternateroutecode' => [
                'nullable',
                'string',
                'max:50',
                Rule::unique('routemaster', 'alternateroutecode')->ignore($routeMaster?->routecode, 'routecode'),
            ],
            'cmpycode' => ['required', 'integer'],
            'regionmstcode' => ['required', 'integer'],
            'subareacode' => ['required', 'integer'],
            'salesmancode' => ['required', 'integer'],
            'vehiclenumber' => ['nullable', 'integer'],
            'routecatcode' => ['nullable', 'integer'],
            'routetype' => ['required', 'integer', Rule::in([0, 1, 2, 3])],
            'routeitemgrpcode' => ['nullable', 'integer'],
            'itemmustkey' => ['nullable', 'integer'],
            'device_assigned_id' => ['nullable', 'string', 'max:50'],
            'activestatus' => ['required', 'integer', Rule::in([0, 1])],
            'presalesorder' => ['required', 'integer', Rule::in([0, 1])],
            'depotrouteflag' => ['required', 'integer', Rule::in([0, 1])],
            'deliveryroute' => ['nullable', 'integer', Rule::in([0, 1])],
            'allowroutestartdayflag' => ['nullable', 'integer', Rule::in([0, 1])],
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
            'enableautopostingaccount' => ['nullable', 'integer', Rule::in([0, 1])],
            'variancecustomercode' => ['nullable', 'integer'],
            'forcesettlementdays' => ['nullable', 'integer', 'min:0'],
            'routecreditcheck' => ['nullable', 'integer', Rule::in([0, 1])],
            'routecreditlimitdays' => ['nullable', 'integer', 'min:0'],
            'updategps' => ['nullable', 'integer', Rule::in([0, 1])],
            'enforcegps' => ['nullable', 'integer', Rule::in([0, 1])],
            'enablegps' => ['nullable', 'integer', Rule::in([0, 1])],
            'autojp_enabled' => ['nullable', 'integer', Rule::in([0, 1])],
            'autojp_work_start_time' => ['nullable', 'date_format:H:i'],
            'autojp_work_end_time' => ['nullable', 'date_format:H:i', 'after:autojp_work_start_time'],
            'autojp_working_days' => ['nullable', 'string', 'max:32', 'regex:/^[1-7](,[1-7])*$/'],
            'reqeoddepositreport' => ['nullable', 'integer'],
            'reqeodsalesreport' => ['nullable', 'integer'],
            'reqeodrteactivreport' => ['nullable', 'integer'],
            'reqeodrtestlmtreport' => ['nullable', 'integer'],
            'reqeodroutereviewrpt' => ['nullable', 'integer'],
            'reqeodrtnexchreport' => ['nullable', 'integer'],
            'reqeodplacementsrpt' => ['nullable', 'integer'],
            'reqeodprcchgreport' => ['nullable', 'integer'],
            'reqeodpromosreport' => ['nullable', 'integer'],
            'reqeodnosalereport' => ['nullable', 'integer'],
            'reqeodnondelreport' => ['nullable', 'integer'],
            'reqeodexceptionrpt' => ['nullable', 'integer'],
            'reqeodunauthbalance' => ['nullable', 'integer'],
            'reqeodroasummary' => ['nullable', 'integer'],
            'reqeodnonscannedreport' => ['nullable', 'integer'],
            'reqeododomlogreport' => ['nullable', 'integer'],
        ])->validate();

        foreach (array_keys($this->defaultRouteData()) as $key) {
            if (!array_key_exists($key, $data)) {
                continue;
            }

            if (is_string($data[$key])) {
                $data[$key] = trim($data[$key]);
            }

            if ($data[$key] === '') {
                $data[$key] = null;
            }
        }

        $deviceId = $data['device_assigned_id'] ?? null;

        if ($deviceId !== null && ! $this->deviceExists($deviceId)) {
            throw ValidationException::withMessages([
                'device_assigned_id' => 'The selected device is invalid.',
            ]);
        }

        $this->ensureRelatedRecordExistsOrIsCurrent($data, 'cmpycode', 'company', 'cmpycode', $routeMaster);
        $this->ensureRelatedRecordExistsOrIsCurrent($data, 'regionmstcode', 'regionmaster', 'regionmstcode', $routeMaster);
        $this->ensureRelatedRecordExistsOrIsCurrent($data, 'subareacode', 'subareamaster', 'subareacode', $routeMaster);
        $this->ensureRelatedRecordExistsOrIsCurrent($data, 'salesmancode', 'salesman', 'salesmancode', $routeMaster);
        $this->ensureRelatedRecordExistsOrIsCurrent($data, 'vehiclenumber', 'vanmaster', 'vancode', $routeMaster);
        $this->ensureRelatedRecordExistsOrIsCurrent($data, 'routecatcode', 'routecategory', 'routecatcode', $routeMaster);
        $this->ensureRelatedRecordExistsOrIsCurrent($data, 'routeitemgrpcode', 'routeitemgrp', 'routeitemgrpcode', $routeMaster);
        $this->ensureRelatedRecordExistsOrIsCurrent($data, 'variancecustomercode', 'customermaster', 'customercode', $routeMaster);

        $scope = app(AccessScopeService::class);
        $user = request()->user();

        if (! $scope->allows($user, 'company', $data['cmpycode'] ?? null)) {
            throw ValidationException::withMessages([
                'cmpycode' => 'Selected company is outside your access scope.',
            ]);
        }

        if (! $scope->allows($user, 'region', $data['regionmstcode'] ?? null)) {
            throw ValidationException::withMessages([
                'regionmstcode' => 'Selected region is outside your access scope.',
            ]);
        }

        if (! $scope->allows($user, 'subarea', $data['subareacode'] ?? null)) {
            throw ValidationException::withMessages([
                'subareacode' => 'Selected sub area is outside your access scope.',
            ]);
        }

        if ($deviceId !== null && $this->deviceAssignedToAnotherRoute($deviceId, $routeMaster?->routecode)) {
            throw ValidationException::withMessages([
                'device_assigned_id' => 'The selected device is already assigned to another route.',
            ]);
        }

        $data['deliveryroute'] = $data['depotrouteflag'] ?? 0;
        $data['variancecustomercode'] = ($data['enableautopostingaccount'] ?? 0) ? ($data['variancecustomercode'] ?? null) : null;
        $data['autojp_working_days'] = $data['autojp_enabled'] ? ($data['autojp_working_days'] ?? '1,2,3,4,5') : null;
        $data['autojp_work_start_time'] = $data['autojp_enabled'] ? ($data['autojp_work_start_time'] ?? null) : null;
        $data['autojp_work_end_time'] = $data['autojp_enabled'] ? ($data['autojp_work_end_time'] ?? null) : null;

        return $data;
    }

    private function normalizeTimeValue(mixed $value): ?string
    {
        $value = trim((string) ($value ?? ''));

        return $value === '' ? null : substr($value, 0, 5);
    }

    private function ensureRelatedRecordExistsOrIsCurrent(
        array $data,
        string $field,
        string $table,
        string $column,
        ?RouteMaster $routeMaster = null
    ): void {
        $value = $data[$field] ?? null;

        if ($value === null) {
            return;
        }

        $exists = DB::table($table)
            ->where($column, $value)
            ->exists();

        if ($exists) {
            return;
        }

        $currentValue = $routeMaster?->{$field};

        if ($routeMaster !== null && $currentValue !== null && (string) $currentValue === (string) $value) {
            return;
        }

        throw ValidationException::withMessages([
            $field => 'The selected ' . str_replace('_', ' ', $field) . ' is invalid.',
        ]);
    }

    private function mapBulkImportRow(array $row): array
    {
        $row = collect($row)
            ->mapWithKeys(fn ($value, $key) => [$this->normalizeBulkImportHeader($key) => $value])
            ->all();

        return array_replace($this->defaultRouteData(), [
            'routename' => $this->nullIfBlank($row['route_name'] ?? null),
            'arbroutename' => $this->nullIfBlank($row['arabic_name'] ?? null),
            'alternateroutecode' => $this->nullIfBlank($row['alternate_code'] ?? null),
            'cmpycode' => $this->integerOrNull($row['company_code'] ?? null),
            'regionmstcode' => $this->integerOrNull($row['region_code'] ?? null),
            'subareacode' => $this->integerOrNull($row['sub_area_code'] ?? null),
            'salesmancode' => $this->integerOrNull($row['salesman_code'] ?? null),
            'vehiclenumber' => $this->integerOrNull($row['van_code'] ?? null),
            'routecatcode' => $this->integerOrNull($row['route_category_code'] ?? null),
            'routetype' => $this->integerOrNull($row['route_type'] ?? null),
            'routeitemgrpcode' => $this->integerOrNull($row['route_item_group_code'] ?? null),
            'itemmustkey' => $this->integerOrNull($row['item_must_key'] ?? null),
            'device_assigned_id' => $this->nullIfBlank($row['device_assigned_id'] ?? null),
            'activestatus' => $this->normalizeFlag($row['status'] ?? null),
            'presalesorder' => $this->normalizeFlag($row['presales_order'] ?? 0),
            'depotrouteflag' => $this->normalizeFlag($row['depot_route_flag'] ?? 0),
            'autojp_enabled' => $this->normalizeFlag($row['autojp_enabled'] ?? 0),
            'autojp_work_start_time' => $this->normalizeImportedTime($row['autojp_work_start_time'] ?? null),
            'autojp_work_end_time' => $this->normalizeImportedTime($row['autojp_work_end_time'] ?? null),
            'autojp_working_days' => $this->nullIfBlank($row['autojp_working_days'] ?? null),
        ]);
    }

    private function bulkImportTemplateHeaders(): array
    {
        return [
            'route_name',
            'arabic_name',
            'alternate_code',
            'company_code',
            'region_code',
            'sub_area_code',
            'salesman_code',
            'van_code',
            'route_category_code',
            'route_type',
            'route_item_group_code',
            'item_must_key',
            'device_assigned_id',
            'status',
            'presales_order',
            'depot_route_flag',
            'autojp_enabled',
            'autojp_work_start_time',
            'autojp_work_end_time',
            'autojp_working_days',
        ];
    }

    private function normalizeBulkImportHeader(string $header): string
    {
        $header = strtolower(trim($header));
        $header = preg_replace('/[^a-z0-9]+/', '_', $header) ?? $header;

        return trim($header, '_');
    }

    private function nullIfBlank(mixed $value): ?string
    {
        $value = trim((string) ($value ?? ''));

        return $value === '' ? null : $value;
    }

    private function integerOrNull(mixed $value): ?int
    {
        $value = $this->nullIfBlank($value);

        return $value === null ? null : (int) $value;
    }

    private function normalizeImportedTime(mixed $value): ?string
    {
        $value = $this->nullIfBlank($value);

        if ($value === null) {
            return null;
        }

        return substr($value, 0, 5);
    }

    private function normalizeFlag(mixed $value): int
    {
        $value = strtolower(trim((string) ($value ?? '')));

        return match ($value) {
            '1', 'true', 'yes', 'y', 'active' => 1,
            default => 0,
        };
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

    private function routeTemplateOptions()
    {
        if (!Schema::hasColumn('routemaster', 'routetmpl')) {
            return [];
        }

        return DB::table('routemaster')
            ->where('routetmpl', 1)
            ->orderBy('routename')
            ->get(['routecode as id', 'routename as label']);
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

    private function deviceOptions(?RouteMaster $routeMaster = null)
    {
        $currentDeviceId = trim((string) ($routeMaster?->device_assigned_id ?? ''));
        $assignedDeviceIds = DB::table('routemaster')
            ->when(
                $routeMaster?->routecode,
                fn ($query, $routeCode) => $query->where('routecode', '<>', $routeCode)
            )
            ->whereNotNull('device_assigned_id')
            ->where('device_assigned_id', '<>', '')
            ->pluck('device_assigned_id')
            ->map(fn ($deviceId) => trim((string) $deviceId))
            ->filter()
            ->unique()
            ->values();

        $options = $this->deviceTableQuery()
            ->when(
                $assignedDeviceIds->isNotEmpty(),
                fn (Builder $query) => $query->whereNotIn($this->deviceIdColumn(), $assignedDeviceIds->all())
            )
            ->orderBy($this->deviceIdColumn())
            ->get($this->deviceSelectColumns());

        if ($currentDeviceId !== '' && ! collect($options)->contains(fn ($option) => (string) $option->id === $currentDeviceId)) {
            $currentDevice = $this->deviceTableQuery()
                ->where($this->deviceIdColumn(), $currentDeviceId)
                ->first($this->deviceSelectColumns());

            if ($currentDevice !== null) {
                $options->prepend($currentDevice);
            } else {
                $options->prepend((object) [
                    'id' => $currentDeviceId,
                    'label' => $currentDeviceId,
                ]);
            }
        }

        return $options->values();
    }

    private function deviceExists(string $deviceId): bool
    {
        return $this->deviceTableQuery()
            ->where($this->deviceIdColumn(), $deviceId)
            ->exists();
    }

    private function deviceAssignedToAnotherRoute(string $deviceId, int|string|null $routeCode = null): bool
    {
        return DB::table('routemaster')
            ->when($routeCode, fn ($query) => $query->where('routecode', '<>', $routeCode))
            ->where('device_assigned_id', $deviceId)
            ->exists();
    }

    private function deviceTableQuery(): Builder
    {
        return DB::query()->from(DB::raw($this->deviceTable()));
    }

    private function deviceTable(): string
    {
        if ($this->deviceTable !== null) {
            return $this->deviceTable;
        }

        foreach ($this->deviceTableCandidates() as [$mode, $table]) {
            if ($this->tableExists($table)) {
                $this->deviceTableMode = $mode;

                return $this->deviceTable = $table;
            }
        }

        $this->deviceTableMode = 'legacy';

        return $this->deviceTable = 'tbl_device';
    }

    private function tableExists(string $table): bool
    {
        if ($table === '') {
            return false;
        }

        $database = DB::getDatabaseName();
        $result = DB::selectOne(
            'SELECT EXISTS(
                SELECT 1
                FROM information_schema.tables
                WHERE table_schema = ?
                  AND table_name = ?
            ) AS table_exists',
            [$database, $table]
        );

        return (bool) ($result->table_exists ?? false);
    }

    private function deviceTableCandidates(): array
    {
        $prefix = (string) config('database.connections.mysql.prefix', '');

        return [
            ['legacy', $prefix . 'tbl_device'],
            ['legacy', 'tbl_device'],
            ['modern', $prefix . 'devicemaster'],
            ['modern', 'devicemaster'],
        ];
    }

    private function usesLegacyDeviceTable(): bool
    {
        $this->deviceTable();

        return $this->deviceTableMode === 'legacy';
    }

    private function deviceIdColumn(): string
    {
        return $this->usesLegacyDeviceTable() ? 'device_id' : 'deviceid';
    }

    private function deviceSelectColumns(): array
    {
        if ($this->usesLegacyDeviceTable()) {
            return [
                DB::raw('device_id as id'),
                DB::raw('device_id as label'),
            ];
        }

        return [
            DB::raw('deviceid as id'),
            DB::raw('deviceid as label'),
        ];
    }

    private function nextRouteCode(): int
    {
        return ((int) RouteMaster::max('routecode')) + 1;
    }
}
