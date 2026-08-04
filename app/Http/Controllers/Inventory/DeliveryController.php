<?php

namespace App\Http\Controllers\Inventory;

use App\Http\Controllers\Controller;
use App\Services\AccessScopeService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class DeliveryController extends Controller
{
    public function index(Request $request): Response
    {
        $scope = app(AccessScopeService::class);
        $user = $request->user();
        $allowedPerPage = [10, 25, 50, 100];
        $allowedSorts = ['deliverydate', 'deliveryno', 'deliveryroute', 'routename', 'drivercode', 'salesmanname1', 'customercode', 'customername', 'orderno', 'delivered'];
        $perPage = (int) $request->input('per_page', 10);
        $sortBy = $request->input('sort_by', 'deliverydate');
        $sortDir = $request->input('sort_dir', 'desc') === 'asc' ? 'asc' : 'desc';
        $headerAlias = DB::getTablePrefix() . 'header';
        $routeAlias = DB::getTablePrefix() . 'route';
        $salesmanAlias = DB::getTablePrefix() . 'salesman';
        $customerAlias = DB::getTablePrefix() . 'customer';
        $detailAlias = DB::getTablePrefix() . 'detail';

        if (!in_array($perPage, $allowedPerPage, true)) {
            $perPage = 10;
        }

        if (!in_array($sortBy, $allowedSorts, true)) {
            $sortBy = 'deliverydate';
        }

        $query = DB::table('deliveryheader as header')
            ->leftJoin('routemaster as route', 'route.routecode', '=', 'header.deliveryroute')
            ->leftJoin('salesman as salesman', 'salesman.salesmancode', '=', 'header.drivercode')
            ->leftJoin('customermaster as customer', 'customer.customercode', '=', 'header.customercode')
            ->leftJoin('deliverydetail as detail', 'detail.deliveryno', '=', 'header.deliveryno')
            ->select([
                'header.deliveryno',
                'header.deliverydate',
                'header.deliveryroute',
                'header.drivercode',
                'header.customercode',
                'header.orderno',
                'header.referenceno',
                'header.delivered',
                'route.routename',
                'route.arbroutename',
                'salesman.salesmanname1',
                'salesman.arbsalesmanname1',
                'customer.customername',
                'customer.arbcustomername',
                DB::raw("COUNT({$detailAlias}.deliveryindex) as itemcount"),
            ])
            ->groupBy([
                'header.deliveryno',
                'header.deliverydate',
                'header.deliveryroute',
                'header.drivercode',
                'header.customercode',
                'header.orderno',
                'header.referenceno',
                'header.delivered',
                'route.routename',
                'route.arbroutename',
                'salesman.salesmanname1',
                'salesman.arbsalesmanname1',
                'customer.customername',
                'customer.arbcustomername',
            ]);

        $scope->scopeQuery($user, $query, 'route', 'header.deliveryroute');

        if ($request->filled('search')) {
            $search = trim((string) $request->input('search'));
            $query->havingRaw(
                "(CAST({$headerAlias}.deliveryno AS CHAR) like ? or CAST({$headerAlias}.deliveryroute AS CHAR) like ? or CAST({$headerAlias}.drivercode AS CHAR) like ? or CAST({$headerAlias}.customercode AS CHAR) like ? or CAST({$headerAlias}.orderno AS CHAR) like ? or {$routeAlias}.routename like ? or {$salesmanAlias}.salesmanname1 like ? or {$customerAlias}.customername like ?)",
                ["%{$search}%", "%{$search}%", "%{$search}%", "%{$search}%", "%{$search}%", "%{$search}%", "%{$search}%", "%{$search}%"]
            );
        }

        if ($request->filled('delivery_date')) {
            $query->whereDate('header.deliverydate', $request->input('delivery_date'));
        }

        $documents = $query
            ->orderBy($sortBy === 'routename' ? 'route.routename' : ($sortBy === 'salesmanname1' ? 'salesman.salesmanname1' : ($sortBy === 'customername' ? 'customer.customername' : 'header.' . $sortBy)), $sortDir)
            ->paginate($perPage)
            ->withQueryString()
            ->through(fn ($record) => [
                'deliveryno' => (int) $record->deliveryno,
                'deliverydate' => $record->deliverydate,
                'deliveryroute' => $record->deliveryroute !== null ? (int) $record->deliveryroute : null,
                'drivercode' => $record->drivercode !== null ? (int) $record->drivercode : null,
                'customercode' => $record->customercode !== null ? (int) $record->customercode : null,
                'orderno' => $record->orderno,
                'referenceno' => $record->referenceno,
                'delivered' => (int) ($record->delivered ?? 0),
                'statuslabel' => $this->deliveryStatusLabel((int) ($record->delivered ?? 0)),
                'routename' => $record->routename,
                'arbroutename' => $record->arbroutename,
                'salesmanname1' => $record->salesmanname1,
                'arbsalesmanname1' => $record->arbsalesmanname1,
                'customername' => $record->customername,
                'arbcustomername' => $record->arbcustomername,
                'itemcount' => (int) ($record->itemcount ?? 0),
            ]);

        return Inertia::render('inventory/delivery/Index', [
            'documents' => $documents,
            'filters' => [
                'search' => $request->input('search', ''),
                'delivery_date' => $request->input('delivery_date', ''),
                'per_page' => $perPage,
                'sort_by' => $sortBy,
                'sort_dir' => $sortDir,
            ],
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('inventory/delivery/Create', $this->formProps());
    }

    public function show(int $delivery): Response
    {
        $this->assertDeliveryAccess($delivery);

        return Inertia::render('inventory/delivery/View', $this->formProps($delivery));
    }

    public function edit(int $delivery): Response
    {
        $this->assertDeliveryAccess($delivery);

        return Inertia::render('inventory/delivery/Edit', $this->formProps($delivery));
    }

    public function destroy(int $delivery): RedirectResponse
    {
        $this->assertDeliveryAccess($delivery);

        DB::transaction(function () use ($delivery) {
            DB::table('deliverydetail')->where('deliveryno', $delivery)->delete();
            DB::table('deliveryheader')->where('deliveryno', $delivery)->delete();
        });

        return back()->with('success', 'Delivery deleted successfully.');
    }

    public function routeMeta(Request $request): JsonResponse
    {
        $data = $request->validate([
            'routecode' => ['required', 'integer', Rule::exists('routemaster', 'routecode')],
        ]);
        abort_unless(app(AccessScopeService::class)->allows($request->user(), 'route', $data['routecode']), 403);

        $route = $this->routeLookupRecord((int) $data['routecode']);

        return response()->json([
            'route' => $route,
            'itemOptions' => $this->routeItemOptions((int) $data['routecode']),
            'customerOptions' => $this->routeCustomerOptions((int) $data['routecode']),
        ]);
    }

    public function itemMeta(Request $request): JsonResponse
    {
        $data = $request->validate([
            'routecode' => ['required', 'integer', Rule::exists('routemaster', 'routecode')],
            'itemcode' => ['required', 'integer', Rule::exists('itemmaster', 'actualitemcode')],
        ]);
        abort_unless(app(AccessScopeService::class)->allows($request->user(), 'route', $data['routecode']), 403);

        $item = DB::table('itemmaster')
            ->where('actualitemcode', (int) $data['itemcode'])
            ->first([
                'actualitemcode',
                'alternatecode',
                'itemshortdescription',
                'unitspercase',
                'caseprice',
                'defaultsalesprice',
            ]);

        abort_unless($item, 404);

        return response()->json([
            'itemcode' => (int) $item->actualitemcode,
            'display_code' => $this->useAlternateCode() && filled($item->alternatecode) ? (string) $item->alternatecode : (string) $item->actualitemcode,
            'description' => $item->itemshortdescription ?? '',
            'upc' => max(1, (int) ($item->unitspercase ?? 1)),
            'caseprice' => (float) ($item->caseprice ?? 0),
            'salesprice' => (float) ($item->defaultsalesprice ?? 0),
        ]);
    }

    public function deliveryNumberStatus(Request $request): JsonResponse
    {
        $data = $request->validate([
            'deliveryno' => ['required', 'integer', 'min:1'],
            'ignore_deliveryno' => ['nullable', 'integer', 'min:1'],
        ]);

        $exists = DB::table('deliveryheader')
            ->where('deliveryno', (int) $data['deliveryno'])
            ->when(!empty($data['ignore_deliveryno']), fn ($query) => $query->where('deliveryno', '!=', (int) $data['ignore_deliveryno']))
            ->exists();

        return response()->json([
            'duplicate' => $exists,
        ]);
    }

    public function storeLine(Request $request): JsonResponse
    {
        $data = $this->validatedLineData($request);
        abort_unless(app(AccessScopeService::class)->allows($request->user(), 'route', $data['routecode']), 403);

        $deliveryNo = (int) $data['deliveryno'];
        $delivery = DB::table('deliveryheader')->where('deliveryno', $deliveryNo)->first();

        if ($delivery) {
            abort_unless(app(AccessScopeService::class)->allows($request->user(), 'route', $delivery->deliveryroute), 403);
        }

        DB::transaction(function () use (&$delivery, $data, $deliveryNo) {
            if (!$delivery) {
                $duplicateDeliveryNo = DB::table('deliveryheader')->where('deliveryno', $deliveryNo)->exists();
                abort_if($duplicateDeliveryNo, 422, 'Delivery number is already assigned.');

                DB::table('deliveryheader')->insert([
                    'deliveryno' => $deliveryNo,
                    'deliveryroute' => (int) $data['routecode'],
                    'drivercode' => (int) $data['salesmancode'],
                    'helpercode' => (int) $data['salesmancode'],
                    'customercode' => (int) $data['customercode'],
                    'orderno' => $data['orderno'] !== '' ? (int) $data['orderno'] : null,
                    'referenceno' => $data['referenceno'] ?: '',
                    'presalesroute' => 0,
                    'dispatched' => 0,
                    'canceled' => 0,
                    'redispatch' => 0,
                    'loadsheetnumber' => '0',
                    'delivered' => 0,
                    'sequence' => 0,
                    'dispatchdate' => Carbon::parse($data['deliverydate'])->toDateString(),
                    'deliverydate' => Carbon::parse($data['deliverydate'])->toDateString(),
                    'invoiced' => 0,
                    'ponumber' => '',
                    'upddate' => now(),
                ]);
            }

            $exists = DB::table('deliverydetail')
                ->where('deliveryno', $deliveryNo)
                ->where('itemcode', (int) $data['itemcode'])
                ->exists();

            abort_if($exists, 422, 'Item already added to the delivery.');

            $upc = max(1, (int) $data['upc']);
            DB::table('deliverydetail')->insert([
                'deliveryno' => $deliveryNo,
                'itemcode' => (int) $data['itemcode'],
                'unitspercase' => $upc,
                'caseprice' => (float) $data['caseprice'],
                'salesprice' => (float) $data['salesprice'],
                'salesqty' => ((int) $data['delivery_cases'] * $upc) + (int) $data['delivery_units'],
                'focqty' => ((int) $data['free_cases'] * $upc) + (int) $data['free_units'],
                'returnqty' => 0,
                'promotionamount' => 0,
                'deliveredqty' => 0,
                'deliveredfoc' => 0,
                'receivedreturn' => 0,
                'reasoncode' => 0,
                'ManualFreeQty' => 0,
                'processedflag' => 0,
            ]);
        });

        return response()->json($this->deliveryPayload($deliveryNo));
    }

    public function updateLine(Request $request, int $line): JsonResponse
    {
        $data = $request->validate([
            'caseprice' => ['nullable', 'numeric', 'min:0'],
            'salesprice' => ['nullable', 'numeric', 'min:0'],
            'delivery_cases' => ['nullable', 'integer', 'min:0'],
            'delivery_units' => ['nullable', 'integer', 'min:0'],
            'free_cases' => ['nullable', 'integer', 'min:0'],
            'free_units' => ['nullable', 'integer', 'min:0'],
        ]);

        $record = DB::table('deliverydetail')->where('deliveryindex', $line)->first();
        abort_unless($record, 404);
        $this->assertDeliveryAccess((int) $record->deliveryno);

        $upc = max(1, (int) ($record->unitspercase ?? 1));
        DB::table('deliverydetail')
            ->where('deliveryindex', $line)
            ->update([
                'caseprice' => (float) ($data['caseprice'] ?? 0),
                'salesprice' => (float) ($data['salesprice'] ?? 0),
                'salesqty' => ((int) ($data['delivery_cases'] ?? 0) * $upc) + (int) ($data['delivery_units'] ?? 0),
                'focqty' => ((int) ($data['free_cases'] ?? 0) * $upc) + (int) ($data['free_units'] ?? 0),
            ]);

        return response()->json($this->deliveryPayload((int) $record->deliveryno));
    }

    public function destroyLine(int $line): JsonResponse
    {
        $record = DB::table('deliverydetail')->where('deliveryindex', $line)->first();
        abort_unless($record, 404);
        $this->assertDeliveryAccess((int) $record->deliveryno);

        DB::table('deliverydetail')->where('deliveryindex', $line)->delete();

        return response()->json($this->deliveryPayload((int) $record->deliveryno));
    }

    private function formProps(?int $deliveryNo = null): array
    {
        $header = $deliveryNo ? $this->deliveryHeader($deliveryNo) : [
            'deliveryno' => '',
            'deliverydate' => now()->toDateString(),
            'deliveryroute' => '',
            'drivercode' => '',
            'salesmanname' => '',
            'customercode' => '',
            'orderno' => '',
            'referenceno' => '',
            'delivered' => 0,
            'statuslabel' => $this->deliveryStatusLabel(0),
        ];

        return [
            'deliveryData' => [
                'header' => $header,
                'lines' => $deliveryNo ? $this->deliveryLines($deliveryNo) : [],
            ],
            'lookupOptions' => [
                'routes' => $this->routeOptions(),
                'items' => $deliveryNo && $header['deliveryroute'] ? $this->routeItemOptions((int) $header['deliveryroute']) : [],
                'customers' => $deliveryNo && $header['deliveryroute'] ? $this->routeCustomerOptions((int) $header['deliveryroute']) : [],
            ],
            'formMeta' => [
                'routeMetaUrl' => route('inventory.delivery.route-meta'),
                'itemMetaUrl' => route('inventory.delivery.item-meta'),
                'deliveryNoStatusUrl' => route('inventory.delivery.number-status'),
                'lineStoreUrl' => route('inventory.delivery.lines.store'),
                'lineUpdateBaseUrl' => '/inventory/delivery/lines',
                'lineDestroyBaseUrl' => '/inventory/delivery/lines',
            ],
            'useAlternateCode' => $this->useAlternateCode(),
        ];
    }

    private function validatedLineData(Request $request): array
    {
        return $request->validate([
            'deliverydate' => ['required', 'date'],
            'routecode' => ['required', 'integer', Rule::exists('routemaster', 'routecode')],
            'salesmancode' => ['required', 'integer', Rule::exists('salesman', 'salesmancode')],
            'deliveryno' => ['required', 'integer', 'min:1'],
            'customercode' => ['required', 'integer', Rule::exists('customermaster', 'customercode')],
            'orderno' => ['nullable', 'string', 'max:30'],
            'referenceno' => ['nullable', 'string', 'max:50'],
            'itemcode' => ['required', 'integer', Rule::exists('itemmaster', 'actualitemcode')],
            'upc' => ['required', 'integer', 'min:1'],
            'caseprice' => ['nullable', 'numeric', 'min:0'],
            'salesprice' => ['nullable', 'numeric', 'min:0'],
            'delivery_cases' => ['nullable', 'integer', 'min:0'],
            'delivery_units' => ['nullable', 'integer', 'min:0'],
            'free_cases' => ['nullable', 'integer', 'min:0'],
            'free_units' => ['nullable', 'integer', 'min:0'],
        ]);
    }

    private function deliveryPayload(int $deliveryNo): array
    {
        return [
            'header' => $this->deliveryHeader($deliveryNo),
            'lines' => $this->deliveryLines($deliveryNo),
        ];
    }

    private function deliveryHeader(int $deliveryNo): array
    {
        $record = DB::table('deliveryheader as header')
            ->leftJoin('routemaster as route', 'route.routecode', '=', 'header.deliveryroute')
            ->leftJoin('salesman as salesman', 'salesman.salesmancode', '=', 'header.drivercode')
            ->leftJoin('customermaster as customer', 'customer.customercode', '=', 'header.customercode')
            ->where('header.deliveryno', $deliveryNo)
            ->first([
                'header.deliveryno',
                'header.deliverydate',
                'header.deliveryroute',
                'header.drivercode',
                'header.customercode',
                'header.orderno',
                'header.referenceno',
                'header.delivered',
                'salesman.salesmanname1',
                'route.routename',
                'customer.customername',
            ]);

        abort_unless($record, 404);

        return [
            'deliveryno' => (int) $record->deliveryno,
            'deliverydate' => Carbon::parse($record->deliverydate)->toDateString(),
            'deliveryroute' => $record->deliveryroute !== null ? (int) $record->deliveryroute : null,
            'drivercode' => $record->drivercode !== null ? (int) $record->drivercode : null,
            'salesmanname' => $record->salesmanname1 ?? '',
            'customercode' => $record->customercode !== null ? (int) $record->customercode : null,
            'customername' => $record->customername ?? '',
            'orderno' => $record->orderno ?? '',
            'referenceno' => $record->referenceno ?? '',
            'delivered' => (int) ($record->delivered ?? 0),
            'statuslabel' => $this->deliveryStatusLabel((int) ($record->delivered ?? 0)),
            'routename' => $record->routename ?? '',
        ];
    }

    private function deliveryLines(int $deliveryNo): array
    {
        $useAlternateCode = $this->useAlternateCode();

        return DB::table('deliverydetail as detail')
            ->join('itemmaster as item', 'item.actualitemcode', '=', 'detail.itemcode')
            ->where('detail.deliveryno', $deliveryNo)
            ->orderBy('detail.deliveryindex')
            ->get([
                'detail.deliveryindex',
                'detail.itemcode',
                'detail.unitspercase',
                'detail.caseprice',
                'detail.salesprice',
                'detail.salesqty',
                'detail.focqty',
                'item.alternatecode',
                'item.itemshortdescription',
            ])
            ->map(function ($line) use ($useAlternateCode) {
                $upc = max(1, (int) ($line->unitspercase ?? 1));
                $displayCode = $useAlternateCode && filled($line->alternatecode) ? $line->alternatecode : $line->itemcode;

                return [
                    'deliveryindex' => (int) $line->deliveryindex,
                    'itemcode' => (int) $line->itemcode,
                    'display_code' => (string) $displayCode,
                    'description' => $line->itemshortdescription ?? '',
                    'upc' => $upc,
                    'caseprice' => (float) ($line->caseprice ?? 0),
                    'salesprice' => (float) ($line->salesprice ?? 0),
                    'delivery_cases' => intdiv((int) ($line->salesqty ?? 0), $upc),
                    'delivery_units' => (int) ($line->salesqty ?? 0) % $upc,
                    'free_cases' => intdiv((int) ($line->focqty ?? 0), $upc),
                    'free_units' => (int) ($line->focqty ?? 0) % $upc,
                ];
            })
            ->values()
            ->all();
    }

    private function routeOptions(): array
    {
        $query = DB::table('routemaster')
            ->select(['routecode', 'routename', 'salesmancode'])
            ->orderBy('routename');

        if (Schema::hasColumn('routemaster', 'activestatus')) {
            $query->where('activestatus', 1);
        }

        if (Schema::hasColumn('routemaster', 'deliveryroute')) {
            $query->where('deliveryroute', 1);
        }

        app(AccessScopeService::class)->scopeQuery(request()->user(), $query, 'route', 'routecode');

        return $query->get()->map(fn ($route) => [
            'id' => (int) $route->routecode,
            'label' => trim($route->routecode . ' -- ' . ($route->routename ?? '')),
            'salesmancode' => $route->salesmancode !== null ? (int) $route->salesmancode : null,
        ])->all();
    }

    private function routeLookupRecord(int $routecode): array
    {
        $this->assertRouteAccess($routecode);

        $route = DB::table('routemaster as route')
            ->leftJoin('salesman as salesman', 'salesman.salesmancode', '=', 'route.salesmancode')
            ->where('route.routecode', $routecode)
            ->first([
                'route.routecode',
                'route.routename',
                'route.salesmancode',
                'salesman.salesmanname1',
            ]);

        abort_unless($route, 404);

        return [
            'routecode' => (int) $route->routecode,
            'routename' => $route->routename,
            'salesmancode' => $route->salesmancode !== null ? (int) $route->salesmancode : null,
            'salesmanname' => $route->salesmanname1,
        ];
    }

    private function routeCustomerOptions(int $routecode): array
    {
        $this->assertRouteAccess($routecode);

        $query = DB::table('customermaster')
            ->where('routecode', $routecode)
            ->where('templateindicator', 0)
            ->orderBy('customername');

        if (Schema::hasColumn('customermaster', 'activecustomer')) {
            $query->where('activecustomer', 1);
        }

        $useAlternateCode = $this->useAlternateCode();

        return $query->get(['customercode', 'alternatecode', 'customername'])->map(function ($customer) use ($useAlternateCode) {
            $displayCode = $useAlternateCode && filled($customer->alternatecode)
                ? $customer->alternatecode
                : $customer->customercode;

            return [
                'id' => (int) $customer->customercode,
                'label' => trim($displayCode . ' -- ' . ($customer->customername ?? '')),
            ];
        })->all();
    }

    private function routeItemOptions(int $routecode): array
    {
        $this->assertRouteAccess($routecode);

        $routeItemGroupCode = (int) DB::table('routemaster')
            ->where('routecode', $routecode)
            ->value('routeitemgrpcode');

        $query = DB::table('itemmaster as item')
            ->select([
                'item.actualitemcode',
                'item.alternatecode',
                'item.itemshortdescription',
            ])
            ->orderBy('item.actualitemcode');

        if (Schema::hasColumn('itemmaster', 'activeitem')) {
            $query->where('item.activeitem', 1);
        }

        if ($routeItemGroupCode > 0 && Schema::hasTable('routeitemmapping')) {
            $query->join('routeitemmapping as mapping', 'mapping.itemcode', '=', 'item.actualitemcode')
                ->where('mapping.routeitemgrpcode', $routeItemGroupCode);
        }

        $useAlternateCode = $this->useAlternateCode();

        return $query->get()->map(function ($item) use ($useAlternateCode) {
            $displayCode = $useAlternateCode && filled($item->alternatecode)
                ? $item->alternatecode
                : $item->actualitemcode;

            return [
                'id' => (int) $item->actualitemcode,
                'label' => trim($displayCode . ' -- ' . ($item->itemshortdescription ?? '')),
            ];
        })->values()->all();
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

    private function deliveryStatusLabel(int $status): string
    {
        return match ($status) {
            1 => 'Delivered',
            2 => 'Partial Delivered',
            default => 'Not Delivered',
        };
    }

    private function assertRouteAccess(int|string|null $routecode): void
    {
        abort_unless(app(AccessScopeService::class)->allows(request()->user(), 'route', $routecode), 403);
    }

    private function assertDeliveryAccess(int $deliveryNo): void
    {
        $routeCode = DB::table('deliveryheader')->where('deliveryno', $deliveryNo)->value('deliveryroute');
        abort_unless($routeCode !== null, 404);

        $this->assertRouteAccess((int) $routeCode);
    }
}
