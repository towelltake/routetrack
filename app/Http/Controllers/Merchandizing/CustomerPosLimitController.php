<?php

namespace App\Http\Controllers\Merchandizing;

use App\Http\Controllers\Controller;
use App\Models\CustomerMaster;
use App\Models\CustomerPosInventory;
use App\Models\CustomerPosLimit;
use App\Models\PosMaster;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class CustomerPosLimitController extends Controller
{
    public function index(): Response
    {
        $search = request('search');
        $perPage = (int) request('per_page', 10);
        $perPage = in_array($perPage, [10, 25, 50, 100], true) ? $perPage : 10;

        if ($this->hasTables()) {
            $records = CustomerPosLimit::query()
                ->join('customermaster as customer', 'customer.customercode', '=', 'customerposlimit.customercode')
                ->when($search, function ($query, $searchTerm) {
                    $query->where(function ($inner) use ($searchTerm) {
                        $inner->where('customer.customername', 'like', '%' . $searchTerm . '%')
                            ->orWhere('customer.arbcustomername', 'like', '%' . $searchTerm . '%')
                            ->orWhere('customer.alternatecode', 'like', '%' . $searchTerm . '%')
                            ->orWhere('customerposlimit.customercode', 'like', '%' . $searchTerm . '%');
                    });
                })
                ->orderBy('customerposlimit.primary_key')
                ->paginate($perPage, [
                    'customerposlimit.*',
                    'customer.customername',
                    'customer.arbcustomername',
                    'customer.alternatecode',
                ])
                ->withQueryString()
                ->through(fn ($record) => $this->transformRow($record));
        } else {
            $records = new LengthAwarePaginator([], 0, $perPage, 1, [
                'path' => request()->url(),
                'query' => request()->query(),
            ]);
        }

        return Inertia::render('merchandizing/customer-pos-limit/Index', [
            'available' => $this->hasTables(),
            'filters' => [
                'search' => $search,
                'per_page' => $perPage,
            ],
            'records' => $records,
            'formMeta' => $this->formMeta(),
        ]);
    }

    public function create(): Response
    {
        abort_unless($this->hasTables(), 404);

        return Inertia::render('merchandizing/customer-pos-limit/FormPage', [
            'mode' => 'create',
            'formMeta' => $this->formMeta(),
            'limitData' => [
                'primary_key' => null,
                'customercode' => '',
                'customerlabel' => '',
                'poslimit' => '',
                'posbalance' => '',
                'items' => [],
            ],
        ]);
    }

    public function show(CustomerPosLimit $customerPosLimit): Response
    {
        abort_unless($this->hasTables(), 404);

        return Inertia::render('merchandizing/customer-pos-limit/FormPage', [
            'mode' => 'view',
            'formMeta' => $this->formMeta((int) $customerPosLimit->customercode),
            'limitData' => $this->recordData($customerPosLimit),
        ]);
    }

    public function edit(CustomerPosLimit $customerPosLimit): Response
    {
        abort_unless($this->hasTables(), 404);

        return Inertia::render('merchandizing/customer-pos-limit/FormPage', [
            'mode' => 'edit',
            'formMeta' => $this->formMeta((int) $customerPosLimit->customercode),
            'limitData' => $this->recordData($customerPosLimit),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        abort_unless($this->hasTables(), 404);

        $data = $request->validate([
            'customercode' => ['required', 'integer', Rule::exists('customermaster', 'customercode')],
            'poslimit' => ['required', 'integer', 'min:1'],
        ]);

        $request->validate([
            'customercode' => [Rule::unique('customerposlimit', 'customercode')],
        ]);

        $username = auth()->user()?->username ?? auth()->user()?->name ?? 'system';

        $record = CustomerPosLimit::query()->create([
            'customercode' => (int) $data['customercode'],
            'poslimit' => (int) $data['poslimit'],
            'posbalance' => (int) $data['poslimit'],
            'created' => $username,
            'cdat' => now(),
            'modified' => $username,
            'mdat' => now(),
        ]);

        return redirect("/merchandizing/customer-pos-limit/{$record->primary_key}/edit")->with('success', 'Customer POS limit created.');
    }

    public function update(Request $request, CustomerPosLimit $customerPosLimit): RedirectResponse
    {
        abort_unless($this->hasTables(), 404);

        $data = $request->validate([
            'poslimit' => ['required', 'integer', 'min:1'],
        ]);

        $username = auth()->user()?->username ?? auth()->user()?->name ?? 'system';

        $assignedCount = CustomerPosInventory::query()
            ->where('customercode', $customerPosLimit->customercode)
            ->count();

        $customerPosLimit->update([
            'poslimit' => (int) $data['poslimit'],
            'posbalance' => (int) $data['poslimit'] - $assignedCount,
            'modified' => $username,
            'mdat' => now(),
        ]);

        return redirect("/merchandizing/customer-pos-limit/{$customerPosLimit->primary_key}/edit")->with('success', 'Customer POS limit updated.');
    }

    public function destroy(CustomerPosLimit $customerPosLimit): RedirectResponse
    {
        abort_unless($this->hasTables(), 404);

        $hasDetails = CustomerPosInventory::query()
            ->where('customercode', $customerPosLimit->customercode)
            ->exists();

        if ($hasDetails) {
            return back()->with('error', 'Remove assigned POS items before deleting this customer POS limit.');
        }

        $customerPosLimit->delete();

        return back()->with('success', 'Customer POS limit deleted.');
    }

    public function detailStore(Request $request, CustomerPosLimit $customerPosLimit): RedirectResponse
    {
        abort_unless($this->hasTables(), 404);

        $payload = $this->validatedDetailPayload($request);
        $username = auth()->user()?->username ?? auth()->user()?->name ?? 'system';
        $item = PosMaster::query()->findOrFail($payload['itemcode']);

        if ((int) ($customerPosLimit->posbalance ?? 0) <= 0) {
            return back()->withErrors([
                'detail.itemcode' => 'POS balance is zero.',
            ]);
        }

        if ((int) ($item->inventorytype ?? 0) === 1) {
            $duplicate = CustomerPosInventory::query()
                ->where('customercode', $customerPosLimit->customercode)
                ->where('itemcode', $item->itemcode)
                ->where('serialnumber', $payload['serialnumber'])
                ->exists();

            if ($duplicate) {
                return back()->withErrors([
                    'detail.serialnumber' => 'Duplicate serial number for this POS item.',
                ]);
            }
        }

        DB::transaction(function () use ($customerPosLimit, $payload, $username, $item) {
            CustomerPosInventory::query()->create([
                'customercode' => (int) $customerPosLimit->customercode,
                'itemcode' => (int) $payload['itemcode'],
                'quantity' => (int) ($item->inventorytype === 0 ? $payload['quantity'] : 1),
                'serialnumber' => $item->inventorytype === 1 ? $payload['serialnumber'] : null,
                'created' => $username,
                'cdat' => now(),
                'modified' => $username,
                'mdat' => now(),
            ]);

            $customerPosLimit->update([
                'posbalance' => ((int) $customerPosLimit->posbalance) - 1,
                'modified' => $username,
                'mdat' => now(),
            ]);
        });

        return back()->with('success', 'POS item added.');
    }

    public function detailUpdate(Request $request, CustomerPosLimit $customerPosLimit, CustomerPosInventory $detail): RedirectResponse
    {
        abort_unless($this->hasTables(), 404);
        abort_unless((int) $detail->customercode === (int) $customerPosLimit->customercode, 404);

        $payload = $this->validatedDetailPayload($request);
        $username = auth()->user()?->username ?? auth()->user()?->name ?? 'system';
        $item = PosMaster::query()->findOrFail($detail->itemcode);

        if ((int) ($item->inventorytype ?? 0) === 1) {
            $duplicate = CustomerPosInventory::query()
                ->where('customercode', $customerPosLimit->customercode)
                ->where('itemcode', $detail->itemcode)
                ->where('serialnumber', $payload['serialnumber'])
                ->where('table_pk', '!=', $detail->table_pk)
                ->exists();

            if ($duplicate) {
                return back()->withErrors([
                    'detail_edit.' . $detail->table_pk . '.serialnumber' => 'Duplicate serial number for this POS item.',
                ]);
            }
        }

        $detail->update([
            'quantity' => (int) ($item->inventorytype === 0 ? $payload['quantity'] : ($detail->quantity ?? 1)),
            'serialnumber' => $item->inventorytype === 1 ? $payload['serialnumber'] : null,
            'modified' => $username,
            'mdat' => now(),
        ]);

        return back()->with('success', 'POS item updated.');
    }

    public function detailDestroy(CustomerPosLimit $customerPosLimit, CustomerPosInventory $detail): RedirectResponse
    {
        abort_unless($this->hasTables(), 404);
        abort_unless((int) $detail->customercode === (int) $customerPosLimit->customercode, 404);

        $username = auth()->user()?->username ?? auth()->user()?->name ?? 'system';

        DB::transaction(function () use ($customerPosLimit, $detail, $username) {
            $detail->delete();

            $customerPosLimit->update([
                'posbalance' => ((int) $customerPosLimit->posbalance) + 1,
                'modified' => $username,
                'mdat' => now(),
            ]);
        });

        return back()->with('success', 'POS item removed.');
    }

    private function validatedDetailPayload(Request $request): array
    {
        $data = $request->validate([
            'itemcode' => ['required', 'integer', Rule::exists('posmaster', 'itemcode')],
            'quantity' => ['nullable', 'integer', 'min:1'],
            'serialnumber' => ['nullable', 'string', 'max:20'],
        ]);

        $item = PosMaster::query()->findOrFail($data['itemcode']);

        if ((int) ($item->inventorytype ?? 0) === 0) {
            $request->validate([
                'quantity' => ['required', 'integer', 'min:1'],
            ]);
        } else {
            $request->validate([
                'serialnumber' => ['required', 'string', 'max:20'],
            ]);
        }

        $data['serialnumber'] = $data['serialnumber'] === '' ? null : $data['serialnumber'];

        return $data;
    }

    private function recordData(CustomerPosLimit $record): array
    {
        $customer = CustomerMaster::query()->find($record->customercode);

        $items = CustomerPosInventory::query()
            ->join('posmaster as pos', 'pos.itemcode', '=', 'customerposinventory.itemcode')
            ->where('customerposinventory.customercode', $record->customercode)
            ->orderBy('customerposinventory.table_pk')
            ->get([
                'customerposinventory.*',
                'pos.itemdescription',
                'pos.arbitemdescription',
                'pos.inventorytype',
            ])
            ->map(fn ($item) => [
                'table_pk' => (int) $item->table_pk,
                'itemcode' => (int) $item->itemcode,
                'itemdescription' => $item->itemdescription ?? '',
                'arbitemdescription' => $item->arbitemdescription ?? '',
                'inventorytype' => (int) ($item->inventorytype ?? 0),
                'quantity' => $item->quantity !== null ? (int) $item->quantity : null,
                'serialnumber' => $item->serialnumber ?? '',
            ])
            ->all();

        return [
            'primary_key' => (int) $record->primary_key,
            'customercode' => (int) $record->customercode,
            'customerlabel' => $customer ? trim(collect([$customer->customercode, $customer->customername])->implode(' - ')) : '',
            'poslimit' => $record->poslimit !== null ? (int) $record->poslimit : '',
            'posbalance' => $record->posbalance !== null ? (int) $record->posbalance : '',
            'items' => $items,
        ];
    }

    private function transformRow($record): array
    {
        return [
            'primary_key' => (int) $record->primary_key,
            'customercode' => (int) $record->customercode,
            'customername' => $record->customername ?? '',
            'alternatecode' => $record->alternatecode ?? '',
            'poslimit' => $record->poslimit !== null ? (int) $record->poslimit : 0,
            'posbalance' => $record->posbalance !== null ? (int) $record->posbalance : 0,
        ];
    }

    private function hasTables(): bool
    {
        return Schema::hasTable('customerposlimit')
            && Schema::hasTable('customerposinventory')
            && Schema::hasTable('customermaster')
            && Schema::hasTable('posmaster');
    }

    private function formMeta(?int $selectedCustomerCode = null): array
    {
        return [
            'indexUrl' => '/merchandizing/customer-pos-limit',
            'baseUrl' => '/merchandizing/customer-pos-limit',
            'subtitle' => 'Maintain customer POS limits and assigned POS inventory',
            'customerOptions' => $this->customerOptions($selectedCustomerCode),
            'itemOptions' => Schema::hasTable('posmaster')
                ? PosMaster::query()
                    ->orderBy('itemcode')
                    ->get([
                        'itemcode',
                        'itemdescription',
                        'inventorytype',
                    ])
                    ->map(fn (PosMaster $item) => [
                        'id' => (int) $item->itemcode,
                        'label' => trim(collect([$item->itemcode, $item->itemdescription])->implode(' - ')),
                        'inventorytype' => (int) ($item->inventorytype ?? 0),
                    ])
                    ->all()
                : [],
        ];
    }

    private function customerOptions(?int $selectedCustomerCode = null): array
    {
        if (!Schema::hasTable('customermaster')) {
            return [];
        }

        $assignedCodes = Schema::hasTable('customerposlimit')
            ? CustomerPosLimit::query()
                ->when($selectedCustomerCode, fn ($query) => $query->where('customercode', '!=', $selectedCustomerCode))
                ->pluck('customercode')
                ->all()
            : [];

        $options = CustomerMaster::query()
            ->when(Schema::hasColumn('customermaster', 'activecustomer'), fn ($query) => $query->where('activecustomer', 1))
            ->when(Schema::hasColumn('customermaster', 'templateindicator'), fn ($query) => $query->where('templateindicator', 0))
            ->when($assignedCodes, fn ($query) => $query->whereNotIn('customercode', $assignedCodes))
            ->orderBy('customercode')
            ->get([
                'customercode',
                'customername',
            ])
            ->map(fn (CustomerMaster $customer) => [
                'id' => (int) $customer->customercode,
                'label' => trim(collect([$customer->customercode, $customer->customername])->implode(' - ')),
            ])
            ->all();

        if ($selectedCustomerCode && !collect($options)->contains(fn ($option) => $option['id'] === $selectedCustomerCode)) {
            $customer = CustomerMaster::query()->find($selectedCustomerCode);

            if ($customer) {
                array_unshift($options, [
                    'id' => (int) $customer->customercode,
                    'label' => trim(collect([$customer->customercode, $customer->customername])->implode(' - ')),
                ]);
            }
        }

        return $options;
    }
}
