<?php

namespace App\Http\Controllers\Links;

use App\Http\Controllers\Controller;
use App\Services\AccessScopeService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class ItemsGroupController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('links/items-group/Index', [
            'available' => $this->hasRequiredTables(),
            'formMeta' => [
                'title' => 'Items Group',
                'subtitle' => 'Move items from one item group to another using the legacy workflow',
                'indexUrl' => '/links/items-group',
                'loadUrl' => '/links/items-group/load',
                'saveUrl' => '/links/items-group/save',
                'permission' => 'items group',
            ],
            'optionSets' => [
                'itemGroupOptions' => $this->itemGroupOptions(),
            ],
        ]);
    }

    public function load(Request $request): JsonResponse
    {
        abort_unless($this->hasRequiredTables(), 404);

        $data = $request->validate([
            'item_group' => ['required', 'integer', Rule::exists('itemgroup', 'itemgroupcode')],
        ]);
        $this->assertItemGroupAccess($request, (int) $data['item_group']);

        $useAlternateCode = $this->useAlternateCode();
        $items = $this->itemQuery((int) $data['item_group'])->get();

        return response()->json([
            'items' => $this->transformItems($items, $useAlternateCode),
            'selectedItems' => [],
            'selectedItemIds' => [],
        ]);
    }

    public function save(Request $request): RedirectResponse
    {
        abort_unless($this->hasRequiredTables(), 404);

        $data = $request->validate([
            'item_group' => ['required', 'integer', Rule::exists('itemgroup', 'itemgroupcode')],
            'target_item_group' => ['required', 'integer', Rule::exists('itemgroup', 'itemgroupcode')],
            'items' => ['array'],
            'items.*' => ['integer', Rule::exists('itemmaster', 'actualitemcode')],
        ]);

        $sourceGroup = (int) $data['item_group'];
        $targetGroup = (int) $data['target_item_group'];
        $this->assertItemGroupAccess($request, $sourceGroup);
        $this->assertItemGroupAccess($request, $targetGroup);
        $itemIds = collect($data['items'] ?? [])
            ->map(fn ($value) => (int) $value)
            ->unique()
            ->values();

        if ($itemIds->isNotEmpty() && $sourceGroup !== $targetGroup) {
            DB::table('itemmaster')
                ->where('itemgroupcode', $sourceGroup)
                ->whereIn('actualitemcode', $itemIds->all())
                ->update(['itemgroupcode' => $targetGroup]);
        }

        return redirect('/links/items-group')->with('success', 'Update Record');
    }

    private function itemGroupOptions(): array
    {
        $query = DB::table('itemgroup');

        if (Schema::hasColumn('itemgroup', 'activestatus')) {
            $query->where('activestatus', 1);
        }

        $query->whereIn('itemgroupcode', $this->allowedItemGroupCodes(request()->user()));

        return $query
            ->orderBy('itemgroupcode')
            ->get(['itemgroupcode', 'itemgroupname'])
            ->map(fn ($record) => [
                'id' => (int) $record->itemgroupcode,
                'label' => trim($record->itemgroupcode . ' -- ' . ($record->itemgroupname ?? '')),
            ])
            ->all();
    }

    private function itemQuery(int $itemGroup)
    {
        return DB::table('itemmaster')
            ->whereIn('itemgroupcode', $this->allowedItemGroupCodes(request()->user()))
            ->where('itemgroupcode', $itemGroup)
            ->orderBy('actualitemcode')
            ->select([
                'actualitemcode as id',
                'alternatecode',
                'itemshortdescription',
            ]);
    }

    private function transformItems(Collection $items, bool $useAlternateCode): array
    {
        return $items
            ->map(function ($item) use ($useAlternateCode) {
                $displayCode = $useAlternateCode && filled($item->alternatecode)
                    ? $item->alternatecode
                    : $item->id;

                return [
                    'id' => (int) $item->id,
                    'label' => trim($displayCode . ' -- ' . ($item->itemshortdescription ?? '')),
                ];
            })
            ->values()
            ->all();
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

    private function hasRequiredTables(): bool
    {
        return Schema::hasTable('itemmaster')
            && Schema::hasTable('itemgroup')
            && Schema::hasColumn('itemmaster', 'itemgroupcode');
    }

    private function allowedItemGroupCodes($user): array
    {
        $query = DB::table('itemgroup as itemgroup')
            ->join('submajorcategory as sub', 'sub.submajorcategorycode', '=', 'itemgroup.submajorcategorycode')
            ->join('majorcategory as major', 'major.majorcategorycode', '=', 'sub.majorcategorycode')
            ->join('companygroup as companygroup', 'companygroup.companygroupcode', '=', 'major.companygroupcode')
            ->select('itemgroup.itemgroupcode');

        app(AccessScopeService::class)->scopeQuery($user, $query, 'company', 'companygroup.parentcompany');

        return $query->pluck('itemgroup.itemgroupcode')->map(fn ($code) => (int) $code)->all();
    }

    private function assertItemGroupAccess(Request $request, int $itemGroup): void
    {
        abort_unless(in_array($itemGroup, $this->allowedItemGroupCodes($request->user()), true), 403);
    }
}
