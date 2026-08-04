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

class ActiveInactiveItemsController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('links/active-inactive-items/Index', [
            'available' => $this->hasRequiredTables(),
            'formMeta' => [
                'title' => 'Active / In Active Items',
                'subtitle' => 'Move items to the inactive list for a selected item group using the legacy workflow',
                'indexUrl' => '/links/active-inactive-items',
                'loadUrl' => '/links/active-inactive-items/load',
                'saveUrl' => '/links/active-inactive-items/save',
                'permission' => 'active/inactive items',
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
            'item_group' => ['required', 'integer', 'min:0'],
        ]);

        $itemGroup = (int) $data['item_group'];
        $this->assertItemGroupAccess($request, $itemGroup);

        if ($itemGroup > 0) {
            validator(
                ['item_group' => $itemGroup],
                ['item_group' => [Rule::exists('itemgroup', 'itemgroupcode')]]
            )->validate();
        }

        $useAlternateCode = $this->useAlternateCode();
        $allItems = $this->itemQuery($itemGroup)->get();
        $inactiveItems = $this->itemQuery($itemGroup)
            ->where('activeitem', 0)
            ->get();

        return response()->json([
            'items' => $this->transformItems($allItems, $useAlternateCode),
            'inactiveItems' => $this->transformItems($inactiveItems, $useAlternateCode),
            'inactiveItemIds' => $inactiveItems->pluck('id')->map(fn ($value) => (int) $value)->all(),
        ]);
    }

    public function save(Request $request): RedirectResponse
    {
        abort_unless($this->hasRequiredTables(), 404);

        $data = $request->validate([
            'item_group' => ['required', 'integer', 'min:0'],
            'items' => ['array'],
            'items.*' => ['integer', Rule::exists('itemmaster', 'actualitemcode')],
        ]);

        $itemGroup = (int) $data['item_group'];
        $inactiveItemIds = collect($data['items'] ?? [])
            ->map(fn ($value) => (int) $value)
            ->unique()
            ->values();
        $this->assertItemGroupAccess($request, $itemGroup);

        if ($itemGroup > 0) {
            validator(
                ['item_group' => $itemGroup],
                ['item_group' => [Rule::exists('itemgroup', 'itemgroupcode')]]
            )->validate();
        }

        DB::transaction(function () use ($itemGroup, $inactiveItemIds) {
            if ($inactiveItemIds->isNotEmpty()) {
                // Preserve the legacy workflow: everything in scope becomes active, then selected items become inactive.
                DB::table('itemmaster')
                    ->when($itemGroup > 0, fn ($query) => $query->where('itemgroupcode', $itemGroup))
                    ->whereNotIn('actualitemcode', $inactiveItemIds->all())
                    ->update(['activeitem' => 1]);
                DB::table('itemmaster')
                    ->when($itemGroup > 0, fn ($query) => $query->where('itemgroupcode', $itemGroup))
                    ->whereIn('actualitemcode', $inactiveItemIds->all())
                    ->update(['activeitem' => 0]);

                return;
            }

            // Legacy behavior for empty selection: reactivate all items globally.
            if ($itemGroup === 0) {
                DB::table('itemmaster')->update(['activeitem' => 1]);
                return;
            }

            DB::table('itemmaster')
                ->where('itemgroupcode', $itemGroup)
                ->update(['activeitem' => 1]);
        });

        return redirect('/links/active-inactive-items')->with('success', 'Update Record');
    }

    private function itemGroupOptions(): array
    {
        $query = DB::table('itemgroup');

        if (Schema::hasColumn('itemgroup', 'activestatus')) {
            $query->where('activestatus', 1);
        }

        $query->whereIn('itemgroupcode', $this->allowedItemGroupCodes(request()->user()));

        $options = $query
            ->orderBy('itemgroupcode')
            ->get(['itemgroupcode', 'itemgroupname'])
            ->map(fn ($record) => [
                'id' => (int) $record->itemgroupcode,
                'label' => trim($record->itemgroupcode . ' -- ' . ($record->itemgroupname ?? '')),
            ])
            ->all();

        array_unshift($options, ['id' => 0, 'label' => '--- ALL ---']);

        return $options;
    }

    private function itemQuery(int $itemGroup)
    {
        return DB::table('itemmaster')
            ->whereIn('itemgroupcode', $this->allowedItemGroupCodes(request()->user()))
            ->when($itemGroup > 0, fn ($query) => $query->where('itemgroupcode', $itemGroup))
            ->orderBy('actualitemcode')
            ->select([
                'actualitemcode as id',
                'alternatecode',
                'itemshortdescription',
                'activeitem',
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
            && Schema::hasColumn('itemmaster', 'activeitem')
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
        if ($itemGroup === 0) {
            return;
        }

        abort_unless(in_array($itemGroup, $this->allowedItemGroupCodes($request->user()), true), 403);
    }
}
