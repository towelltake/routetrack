<?php

namespace App\Http\Controllers\Account;

use App\Http\Controllers\Controller;
use App\Models\ItemGroup;
use App\Models\ItemMaster;
use App\Models\ProductGroupDetail;
use App\Models\ProductGroupHeader;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class CustomerAuthorizeGroupController extends Controller
{
    public function index(): Response
    {
        $search = request('search');
        $perPage = (int) request('per_page', 10);
        $perPage = in_array($perPage, [10, 25, 50, 100], true) ? $perPage : 10;
        $productGroupDetailAlias = DB::getTablePrefix() . 'productgroupdetail';

        $groups = ProductGroupHeader::query()
            ->where('grouptype', 3)
            ->leftJoin('productgroupdetail', 'productgroupdetail.groupnumber', '=', 'productgroupheader.groupnumber')
            ->when($search, function ($query, $searchTerm) {
                $query->where(function ($inner) use ($searchTerm) {
                    $inner->where('productgroupheader.groupnumber', 'like', '%' . $searchTerm . '%')
                        ->orWhere('productgroupheader.groupdescription', 'like', '%' . $searchTerm . '%')
                        ->orWhere('productgroupheader.arbgroupdescription', 'like', '%' . $searchTerm . '%');
                });
            })
            ->groupBy(
                'productgroupheader.groupnumber',
                'productgroupheader.groupdescription',
                'productgroupheader.arbgroupdescription',
                'productgroupheader.cdat'
            )
            ->orderBy('productgroupheader.groupnumber')
            ->select([
                'productgroupheader.groupnumber',
                'productgroupheader.groupdescription',
                'productgroupheader.arbgroupdescription',
                'productgroupheader.cdat',
                DB::raw("COUNT({$productGroupDetailAlias}.primary_key) as item_count"),
            ])
            ->paginate($perPage)
            ->withQueryString();

        return Inertia::render('account/customerauthorizegroup/Index', [
            'filters' => [
                'search' => $search,
                'per_page' => $perPage,
            ],
            'groups' => $groups,
        ]);
    }

    public function create(): Response
    {
        $props = $this->formProps();
        $props['groupData']['groupnumber'] = $this->nextGroupNumber();

        return Inertia::render('account/customerauthorizegroup/Create', $props);
    }

    public function show(ProductGroupHeader $customerAuthorizeGroup): Response
    {
        $this->abortUnlessCustomerAuthorizeGroup($customerAuthorizeGroup);

        return Inertia::render('account/customerauthorizegroup/View', $this->formProps($customerAuthorizeGroup));
    }

    public function edit(ProductGroupHeader $customerAuthorizeGroup): Response
    {
        $this->abortUnlessCustomerAuthorizeGroup($customerAuthorizeGroup);

        return Inertia::render('account/customerauthorizegroup/Edit', $this->formProps($customerAuthorizeGroup));
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validatedData($request);
        $username = auth()->user()?->username ?? auth()->user()?->name ?? 'system';

        DB::transaction(function () use ($data, $username) {
            $group = ProductGroupHeader::create([
                'groupdescription' => $data['groupdescription'],
                'arbgroupdescription' => $data['arbgroupdescription'],
                'grouptype' => 3,
                'created' => $username,
                'cdat' => now(),
                'modified' => $username,
                'mdat' => now(),
            ]);

            $this->syncItems($group->groupnumber, $data['itemcodes'] ?? []);
        });

        return redirect()
            ->route('account.customer-authorize-group.index')
            ->with('success', 'Customer authorize group created.');
    }

    public function update(Request $request, ProductGroupHeader $customerAuthorizeGroup): RedirectResponse
    {
        $this->abortUnlessCustomerAuthorizeGroup($customerAuthorizeGroup);

        $data = $this->validatedData($request);
        $username = auth()->user()?->username ?? auth()->user()?->name ?? 'system';

        DB::transaction(function () use ($customerAuthorizeGroup, $data, $username) {
            $customerAuthorizeGroup->update([
                'groupdescription' => $data['groupdescription'],
                'arbgroupdescription' => $data['arbgroupdescription'],
                'modified' => $username,
                'mdat' => now(),
            ]);

            $this->syncItems($customerAuthorizeGroup->groupnumber, $data['itemcodes'] ?? []);
        });

        return redirect()
            ->route('account.customer-authorize-group.index')
            ->with('success', 'Customer authorize group updated.');
    }

    public function destroy(ProductGroupHeader $customerAuthorizeGroup): RedirectResponse
    {
        $this->abortUnlessCustomerAuthorizeGroup($customerAuthorizeGroup);

        $isInUse = DB::table('customermaster')
            ->where('authorizeditemgrpkey', $customerAuthorizeGroup->groupnumber)
            ->exists();

        if ($isInUse) {
            return back()->with('error', 'Cannot delete: record is in use.');
        }

        try {
            ProductGroupDetail::query()
                ->where('groupnumber', $customerAuthorizeGroup->groupnumber)
                ->delete();

            $customerAuthorizeGroup->delete();
        } catch (\Throwable $e) {
            return back()->with('error', 'Cannot delete: record is in use.');
        }

        return back()->with('success', 'Customer authorize group deleted.');
    }

    public function itemGroupItems(Request $request): JsonResponse
    {
        $data = $request->validate([
            'item_group' => ['required', 'integer', Rule::exists('itemgroup', 'itemgroupcode')],
        ]);

        $items = ItemMaster::query()
            ->where('itemgroupcode', $data['item_group'])
            ->where('activeitem', 1)
            ->orderBy('itemshortdescription')
            ->get([
                'actualitemcode as id',
                DB::raw("CONCAT(COALESCE(NULLIF(alternatecode, ''), actualitemcode), ' - ', itemshortdescription) as label"),
            ]);

        return response()->json($items);
    }

    private function formProps(?ProductGroupHeader $group = null): array
    {
        return [
            'groupData' => $this->groupFormData($group),
            'itemGroupOptions' => ItemGroup::query()
                ->where('activestatus', 1)
                ->orderBy('itemgroupname')
                ->get([
                    'itemgroupcode as id',
                    DB::raw("CONCAT(itemgroupcode, ' - ', itemgroupname) as label"),
                ]),
            'assignedItems' => $this->assignedItems($group?->groupnumber),
            'itemGroupItemsUrl' => route('account.customer-authorize-group.item-group-items'),
        ];
    }

    private function groupFormData(?ProductGroupHeader $group): array
    {
        $record = $group?->toArray() ?? [];

        return array_merge([
            'groupnumber' => null,
            'groupdescription' => '',
            'arbgroupdescription' => '',
            'itemcodes' => [],
        ], array_intersect_key($record, array_flip([
            'groupnumber',
            'groupdescription',
            'arbgroupdescription',
        ])));
    }

    private function assignedItems(?int $groupNumber): array
    {
        if (! $groupNumber) {
            return [];
        }

        $itemAlias = 'im';
        $itemRawAlias = DB::getTablePrefix() . 'im';
        $productGroupDetailTable = (new ProductGroupDetail())->getTable();

        return ProductGroupDetail::query()
            ->where("{$productGroupDetailTable}.groupnumber", $groupNumber)
            ->join('itemmaster as im', 'im.actualitemcode', '=', "{$productGroupDetailTable}.itemcode")
            ->orderByRaw("{$itemRawAlias}.itemshortdescription asc")
            ->get([
                "{$productGroupDetailTable}.itemcode as id",
                DB::raw("CONCAT(COALESCE(NULLIF({$itemRawAlias}.alternatecode, ''), {$itemRawAlias}.actualitemcode), ' - ', {$itemRawAlias}.itemshortdescription) as label"),
            ])
            ->toArray();
    }

    private function validatedData(Request $request): array
    {
        $data = $request->validate([
            'groupdescription' => ['required', 'string', 'max:50'],
            'arbgroupdescription' => ['nullable', 'string', 'max:50'],
            'itemcodes' => ['nullable', 'array'],
            'itemcodes.*' => ['integer', Rule::exists('itemmaster', 'actualitemcode')],
        ]);

        $data['arbgroupdescription'] = $data['arbgroupdescription'] === '' ? null : $data['arbgroupdescription'];
        $data['itemcodes'] = collect($data['itemcodes'] ?? [])
            ->map(fn ($item) => (int) $item)
            ->unique()
            ->values()
            ->all();

        return $data;
    }

    private function syncItems(int $groupNumber, array $itemCodes): void
    {
        $existing = ProductGroupDetail::query()
            ->where('groupnumber', $groupNumber)
            ->pluck('itemcode')
            ->map(fn ($item) => (int) $item)
            ->all();

        $toDelete = array_diff($existing, $itemCodes);
        if ($toDelete) {
            ProductGroupDetail::query()
                ->where('groupnumber', $groupNumber)
                ->whereIn('itemcode', $toDelete)
                ->delete();
        }

        $toInsert = array_diff($itemCodes, $existing);
        if ($toInsert) {
            $rows = array_map(fn ($itemCode) => [
                'groupnumber' => $groupNumber,
                'itemcode' => $itemCode,
                'itemqty' => 0,
                'promopcprice' => 0,
                'promocaseprice' => 0,
            ], $toInsert);

            ProductGroupDetail::query()->insert($rows);
        }
    }

    private function abortUnlessCustomerAuthorizeGroup(ProductGroupHeader $group): void
    {
        abort_unless((int) $group->grouptype === 3, 404);
    }

    private function nextGroupNumber(): int
    {
        return ((int) ProductGroupHeader::query()
            ->where('grouptype', 3)
            ->max('groupnumber')) + 1;
    }
}
