<?php

namespace App\Http\Controllers\Scheme;

use App\Http\Controllers\Controller;
use App\Models\ItemGroup;
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

class PromotionGroupController extends Controller
{
    public function qualificationIndex(): Response
    {
        return $this->indexForType(1);
    }

    public function qualificationCreate(): Response
    {
        return $this->createForType(1);
    }

    public function qualificationShow(ProductGroupHeader $qualificationGroup): Response
    {
        return $this->showForType($qualificationGroup, 1);
    }

    public function qualificationEdit(ProductGroupHeader $qualificationGroup): Response
    {
        return $this->editForType($qualificationGroup, 1);
    }

    public function qualificationStore(Request $request): RedirectResponse
    {
        return $this->storeForType($request, 1);
    }

    public function qualificationUpdate(Request $request, ProductGroupHeader $qualificationGroup): RedirectResponse
    {
        return $this->updateForType($request, $qualificationGroup, 1);
    }

    public function qualificationDestroy(ProductGroupHeader $qualificationGroup): RedirectResponse
    {
        return $this->destroyForType($qualificationGroup, 1);
    }

    public function assignmentIndex(): Response
    {
        return $this->indexForType(2);
    }

    public function assignmentCreate(): Response
    {
        return $this->createForType(2);
    }

    public function assignmentShow(ProductGroupHeader $assignmentGroup): Response
    {
        return $this->showForType($assignmentGroup, 2);
    }

    public function assignmentEdit(ProductGroupHeader $assignmentGroup): Response
    {
        return $this->editForType($assignmentGroup, 2);
    }

    public function assignmentStore(Request $request): RedirectResponse
    {
        return $this->storeForType($request, 2);
    }

    public function assignmentUpdate(Request $request, ProductGroupHeader $assignmentGroup): RedirectResponse
    {
        return $this->updateForType($request, $assignmentGroup, 2);
    }

    public function assignmentDestroy(ProductGroupHeader $assignmentGroup): RedirectResponse
    {
        return $this->destroyForType($assignmentGroup, 2);
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

    private function indexForType(int $groupType): Response
    {
        $meta = $this->meta($groupType);
        $search = request('search');
        $perPage = (int) request('per_page', 10);
        $perPage = in_array($perPage, [10, 25, 50, 100], true) ? $perPage : 10;
        $productGroupDetailAlias = DB::getTablePrefix() . 'productgroupdetail';

        $groups = ProductGroupHeader::query()
            ->where('grouptype', $groupType)
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

        return Inertia::render('scheme/promotion-group/Index', [
            'filters' => [
                'search' => $search,
                'per_page' => $perPage,
            ],
            'groupMeta' => $meta,
            'groups' => $groups,
        ]);
    }

    private function createForType(int $groupType): Response
    {
        $props = $this->formProps($groupType);
        $props['groupData']['groupnumber'] = $this->nextGroupNumber($groupType);

        return Inertia::render('scheme/promotion-group/FormPage', $props + ['mode' => 'create']);
    }

    private function showForType(ProductGroupHeader $group, int $groupType): Response
    {
        $this->abortUnlessGroupType($group, $groupType);

        return Inertia::render('scheme/promotion-group/FormPage', $this->formProps($groupType, $group) + ['mode' => 'view']);
    }

    private function editForType(ProductGroupHeader $group, int $groupType): Response
    {
        $this->abortUnlessGroupType($group, $groupType);

        return Inertia::render('scheme/promotion-group/FormPage', $this->formProps($groupType, $group) + ['mode' => 'edit']);
    }

    private function storeForType(Request $request, int $groupType): RedirectResponse
    {
        $data = $this->validatedData($request);
        $username = auth()->user()?->username ?? auth()->user()?->name ?? 'system';
        $meta = $this->meta($groupType);

        DB::transaction(function () use ($data, $groupType, $username) {
            $group = ProductGroupHeader::create([
                'groupdescription' => $data['groupdescription'],
                'arbgroupdescription' => $data['arbgroupdescription'],
                'grouptype' => $groupType,
                'created' => $username,
                'cdat' => now(),
                'modified' => $username,
                'mdat' => now(),
            ]);

            $this->syncItems($group->groupnumber, $data['itemcodes'] ?? []);
        });

        return redirect($meta['indexUrl'])
            ->with('success', $meta['singular'] . ' created.');
    }

    private function updateForType(Request $request, ProductGroupHeader $group, int $groupType): RedirectResponse
    {
        $this->abortUnlessGroupType($group, $groupType);

        $data = $this->validatedData($request);
        $username = auth()->user()?->username ?? auth()->user()?->name ?? 'system';
        $meta = $this->meta($groupType);

        DB::transaction(function () use ($group, $data, $username) {
            $group->update([
                'groupdescription' => $data['groupdescription'],
                'arbgroupdescription' => $data['arbgroupdescription'],
                'modified' => $username,
                'mdat' => now(),
            ]);

            $this->syncItems($group->groupnumber, $data['itemcodes'] ?? []);
        });

        return redirect($meta['indexUrl'])
            ->with('success', $meta['singular'] . ' updated.');
    }

    private function destroyForType(ProductGroupHeader $group, int $groupType): RedirectResponse
    {
        $this->abortUnlessGroupType($group, $groupType);

        if ($this->groupInUse($group->groupnumber, $groupType)) {
            return back()->with('error', 'Cannot delete: record is in use.');
        }

        try {
            ProductGroupDetail::query()
                ->where('groupnumber', $group->groupnumber)
                ->delete();

            $group->delete();
        } catch (\Throwable $e) {
            return back()->with('error', 'Cannot delete: record is in use.');
        }

        return back()->with('success', $this->meta($groupType)['singular'] . ' deleted.');
    }

    private function formProps(int $groupType, ?ProductGroupHeader $group = null): array
    {
        return [
            'groupMeta' => $this->meta($groupType),
            'groupData' => $this->groupFormData($group),
            'itemGroupOptions' => ItemGroup::query()
                ->where('activestatus', 1)
                ->orderBy('itemgroupname')
                ->get([
                    'itemgroupcode as id',
                    DB::raw("CONCAT(itemgroupcode, ' - ', itemgroupname) as label"),
                ]),
            'assignedItems' => $this->assignedItems($group?->groupnumber),
            'itemGroupItemsUrl' => route('scheme.promotion.promo-group.item-group-items'),
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

        $itemAlias = DB::getTablePrefix() . 'im';

        return ProductGroupDetail::query()
            ->where('productgroupdetail.groupnumber', $groupNumber)
            ->join('itemmaster as im', 'im.actualitemcode', '=', 'productgroupdetail.itemcode')
            ->orderByRaw("{$itemAlias}.itemshortdescription asc")
            ->get([
                'productgroupdetail.itemcode as id',
                DB::raw("CONCAT(COALESCE(NULLIF({$itemAlias}.alternatecode, ''), {$itemAlias}.actualitemcode), ' - ', {$itemAlias}.itemshortdescription) as label"),
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

    private function groupInUse(int $groupNumber, int $groupType): bool
    {
        if ($groupType === 1 && Schema::hasTable('promotioncontrol')) {
            if (DB::table('promotioncontrol')->where('qualificationgroup', $groupNumber)->exists()) {
                return true;
            }
        }

        if ($groupType === 2 && Schema::hasTable('promotioncontrol')) {
            if (DB::table('promotioncontrol')->where('assignmentgroup', $groupNumber)->exists()) {
                return true;
            }
        }

        if (Schema::hasTable('promoplandetail')) {
            $column = $groupType === 1 ? 'qualificationgroup' : 'assignmentgroup';
            if (DB::table('promoplandetail')->where($column, $groupNumber)->exists()) {
                return true;
            }
        }

        if (Schema::hasTable('promokeydetail')) {
            $column = $groupType === 1 ? 'qualificationgroup' : 'assignmentgroup';
            if (DB::table('promokeydetail')->where($column, $groupNumber)->exists()) {
                return true;
            }
        }

        return false;
    }

    private function abortUnlessGroupType(ProductGroupHeader $group, int $groupType): void
    {
        abort_unless((int) $group->grouptype === $groupType, 404);
    }

    private function nextGroupNumber(int $groupType): int
    {
        return ((int) ProductGroupHeader::query()
            ->where('grouptype', $groupType)
            ->max('groupnumber')) + 1;
    }

    private function meta(int $groupType): array
    {
        return match ($groupType) {
            1 => [
                'type' => 1,
                'permission' => 'qualification group',
                'label' => 'Qualification Group',
                'singular' => 'Qualification group',
                'subtitle' => 'Manage promotion qualification groups and eligible item ranges',
                'overviewTitle' => 'Qualification Group Overview',
                'detailsTitle' => 'Qualification Group Details',
                'indexUrl' => '/scheme/promotion/promo-group/qualification-group',
                'createUrl' => '/scheme/promotion/promo-group/qualification-group/create',
                'baseUrl' => '/scheme/promotion/promo-group/qualification-group',
                'usedByLabel' => 'Qualified Items',
            ],
            2 => [
                'type' => 2,
                'permission' => 'assignment group',
                'label' => 'Assignment Group',
                'singular' => 'Assignment group',
                'subtitle' => 'Manage promotion assignment groups and item application targets',
                'overviewTitle' => 'Assignment Group Overview',
                'detailsTitle' => 'Assignment Group Details',
                'indexUrl' => '/scheme/promotion/promo-group/assignment-group',
                'createUrl' => '/scheme/promotion/promo-group/assignment-group/create',
                'baseUrl' => '/scheme/promotion/promo-group/assignment-group',
                'usedByLabel' => 'Assigned Items',
            ],
        };
    }
}
