<?php

namespace App\Http\Controllers\Scheme;

use App\Http\Controllers\Controller;
use App\Models\ItemMaster;
use App\Models\ProductGroupDetail;
use App\Models\ProductGroupHeader;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class LoyaltyGroupController extends Controller
{
    public function index(): Response
    {
        $search = request('search');
        $perPage = (int) request('per_page', 10);
        $perPage = in_array($perPage, [10, 25, 50, 100], true) ? $perPage : 10;
        $productGroupDetailAlias = DB::getTablePrefix() . 'productgroupdetail';

        $groups = ProductGroupHeader::query()
            ->where('grouptype', 1)
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

        return Inertia::render('scheme/loyalty-group/Index', [
            'filters' => [
                'search' => $search,
                'per_page' => $perPage,
            ],
            'groupMeta' => $this->meta(),
            'groups' => $groups,
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('scheme/loyalty-group/FormPage', [
            'mode' => 'create',
            'groupMeta' => $this->meta(),
            'groupData' => [
                'groupnumber' => $this->nextGroupNumber(),
                'groupdescription' => '',
                'arbgroupdescription' => '',
                'createddate' => null,
            ],
            'itemOptions' => $this->itemOptions(),
            'assignedItems' => [],
            'workflowMeta' => $this->workflowMeta(),
        ]);
    }

    public function show(ProductGroupHeader $loyaltyGroup): Response
    {
        $this->abortUnlessLoyaltyGroup($loyaltyGroup);

        return Inertia::render('scheme/loyalty-group/FormPage', [
            'mode' => 'view',
            'groupMeta' => $this->meta(),
            'groupData' => $this->groupData($loyaltyGroup),
            'itemOptions' => $this->itemOptions(),
            'assignedItems' => $this->assignedItems((int) $loyaltyGroup->groupnumber),
            'workflowMeta' => $this->workflowMeta(),
        ]);
    }

    public function edit(ProductGroupHeader $loyaltyGroup): Response
    {
        $this->abortUnlessLoyaltyGroup($loyaltyGroup);

        return Inertia::render('scheme/loyalty-group/FormPage', [
            'mode' => 'edit',
            'groupMeta' => $this->meta(),
            'groupData' => $this->groupData($loyaltyGroup),
            'itemOptions' => $this->itemOptions(),
            'assignedItems' => $this->assignedItems((int) $loyaltyGroup->groupnumber),
            'workflowMeta' => $this->workflowMeta(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validatedData($request);
        $username = auth()->user()?->username ?? auth()->user()?->name ?? 'system';

        DB::transaction(function () use ($data, $username) {
            $group = ProductGroupHeader::query()->create([
                'groupdescription' => $data['groupdescription'],
                'arbgroupdescription' => $data['arbgroupdescription'],
                'grouptype' => 1,
                'created' => $username,
                'cdat' => now(),
                'modified' => $username,
                'mdat' => now(),
            ]);

            $this->syncItems((int) $group->groupnumber, $data['items']);
        });

        return redirect($this->meta()['indexUrl'])->with('success', 'Loyalty group created.');
    }

    public function update(Request $request, ProductGroupHeader $loyaltyGroup): RedirectResponse
    {
        $this->abortUnlessLoyaltyGroup($loyaltyGroup);

        $data = $this->validatedData($request);
        $username = auth()->user()?->username ?? auth()->user()?->name ?? 'system';

        DB::transaction(function () use ($loyaltyGroup, $data, $username) {
            $loyaltyGroup->update([
                'groupdescription' => $data['groupdescription'],
                'arbgroupdescription' => $data['arbgroupdescription'],
                'modified' => $username,
                'mdat' => now(),
            ]);

            $this->syncItems((int) $loyaltyGroup->groupnumber, $data['items']);
        });

        return redirect($this->meta()['indexUrl'])->with('success', 'Loyalty group updated.');
    }

    public function destroy(ProductGroupHeader $loyaltyGroup): RedirectResponse
    {
        $this->abortUnlessLoyaltyGroup($loyaltyGroup);

        if ($this->groupInUse((int) $loyaltyGroup->groupnumber)) {
            return back()->with('error', 'Cannot delete: record is in use.');
        }

        ProductGroupDetail::query()->where('groupnumber', $loyaltyGroup->groupnumber)->delete();
        $loyaltyGroup->delete();

        return back()->with('success', 'Loyalty group deleted.');
    }

    public function itemGroupItems(Request $request): JsonResponse
    {
        $data = $request->validate([
            'item_group' => ['required', 'integer'],
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

    private function validatedData(Request $request): array
    {
        $showItemQuantity = $this->workflowMeta()['showItemQuantity'];

        $data = $request->validate([
            'groupdescription' => ['required', 'string', 'max:50'],
            'arbgroupdescription' => ['nullable', 'string', 'max:50'],
            'items' => ['nullable', 'array'],
            'items.*.itemcode' => ['required', 'integer', Rule::exists('itemmaster', 'actualitemcode')],
            'items.*.itemqty' => $showItemQuantity
                ? ['nullable', 'numeric', 'min:0']
                : ['nullable'],
        ]);

        $data['arbgroupdescription'] = $data['arbgroupdescription'] === '' ? null : $data['arbgroupdescription'];
        $data['items'] = collect($data['items'] ?? [])
            ->map(function (array $item) use ($showItemQuantity) {
                return [
                    'itemcode' => (int) $item['itemcode'],
                    'itemqty' => $showItemQuantity ? (float) ($item['itemqty'] === '' ? 0 : $item['itemqty']) : 0,
                ];
            })
            ->unique('itemcode')
            ->values()
            ->all();

        return $data;
    }

    private function syncItems(int $groupNumber, array $items): void
    {
        ProductGroupDetail::query()->where('groupnumber', $groupNumber)->delete();

        if ($items === []) {
            return;
        }

        $rows = array_map(fn (array $item) => [
            'groupnumber' => $groupNumber,
            'itemcode' => $item['itemcode'],
            'itemqty' => $item['itemqty'],
            'promopcprice' => 0,
            'promocaseprice' => 0,
        ], $items);

        ProductGroupDetail::query()->insert($rows);
    }

    private function assignedItems(int $groupNumber): array
    {
        $useAlternateCode = $this->workflowMeta()['useAlternateCode'];
        $itemAlias = 'im';
        $itemRawAlias = DB::getTablePrefix() . 'im';
        $productGroupDetailTable = (new ProductGroupDetail())->getTable();

        return ProductGroupDetail::query()
            ->where("{$productGroupDetailTable}.groupnumber", $groupNumber)
            ->join('itemmaster as im', 'im.actualitemcode', '=', "{$productGroupDetailTable}.itemcode")
            ->orderByRaw("{$itemRawAlias}.itemshortdescription asc")
            ->get([
                "{$productGroupDetailTable}.itemcode",
                "{$productGroupDetailTable}.itemqty",
                "{$itemAlias}.actualitemcode",
                "{$itemAlias}.alternatecode",
                "{$itemAlias}.itemshortdescription",
            ])
            ->map(function ($item) use ($useAlternateCode) {
                $displayCode = $useAlternateCode && filled($item->alternatecode)
                    ? $item->alternatecode
                    : $item->actualitemcode;

                return [
                    'itemcode' => (int) $item->itemcode,
                    'displaycode' => (string) $displayCode,
                    'actualitemcode' => (int) $item->actualitemcode,
                    'itemshortdescription' => (string) $item->itemshortdescription,
                    'itemqty' => (float) $item->itemqty,
                ];
            })
            ->all();
    }

    private function itemOptions(): array
    {
        $useAlternateCode = $this->workflowMeta()['useAlternateCode'];

        return ItemMaster::query()
            ->where('activeitem', 1)
            ->orderBy('itemshortdescription')
            ->get([
                'actualitemcode',
                'alternatecode',
                'itemshortdescription',
            ])
            ->map(function ($item) use ($useAlternateCode) {
                $code = $useAlternateCode && filled($item->alternatecode)
                    ? $item->alternatecode
                    : $item->actualitemcode;

                return [
                    'id' => (int) $item->actualitemcode,
                    'code' => (string) $code,
                    'description' => (string) $item->itemshortdescription,
                    'label' => trim($code . ' - ' . $item->itemshortdescription),
                ];
            })
            ->all();
    }

    private function groupData(ProductGroupHeader $group): array
    {
        return [
            'groupnumber' => (int) $group->groupnumber,
            'groupdescription' => $group->groupdescription ?? '',
            'arbgroupdescription' => $group->arbgroupdescription ?? '',
            'createddate' => $group->cdat ? date('d-m-Y', strtotime((string) $group->cdat)) : null,
        ];
    }

    private function workflowMeta(): array
    {
        $flags = [
            'useAlternateCode' => false,
            'showItemQuantity' => false,
        ];

        if (! Schema::hasTable('controlpanel')) {
            return $flags;
        }

        $rows = DB::table('controlpanel')
            ->whereIn('flagname', [
                'Use Alternate Code',
                'Fixed Qualification/Fixed Assignment',
                'Ranged Qualification on Fixed Assignment',
            ])
            ->pluck('status', 'flagname');

        $flags['useAlternateCode'] = (int) ($rows['Use Alternate Code'] ?? 0) === 1;
        $flags['showItemQuantity'] = (int) ($rows['Fixed Qualification/Fixed Assignment'] ?? 0) === 1
            || (int) ($rows['Ranged Qualification on Fixed Assignment'] ?? 0) === 1;

        return $flags;
    }

    private function groupInUse(int $groupNumber): bool
    {
        if (Schema::hasTable('loyaltyplandetail') && DB::table('loyaltyplandetail')->where('qualificationgroup', $groupNumber)->exists()) {
            return true;
        }

        return false;
    }

    private function nextGroupNumber(): int
    {
        return ((int) ProductGroupHeader::query()->where('grouptype', 1)->max('groupnumber')) + 1;
    }

    private function abortUnlessLoyaltyGroup(ProductGroupHeader $group): void
    {
        abort_unless((int) $group->grouptype === 1, 404);
    }

    private function meta(): array
    {
        return [
            'permission' => 'loyalty group',
            'label' => 'Loyalty Group',
            'singular' => 'Loyalty group',
            'subtitle' => 'Maintain loyalty qualification groups and item eligibility rules',
            'overviewTitle' => 'Loyalty Group Overview',
            'detailsTitle' => 'Loyalty Group Details',
            'indexUrl' => '/scheme/loyalty/loyalty-group',
            'createUrl' => '/scheme/loyalty/loyalty-group/create',
            'baseUrl' => '/scheme/loyalty/loyalty-group',
            'usedByLabel' => 'Qualified Items',
        ];
    }
}
