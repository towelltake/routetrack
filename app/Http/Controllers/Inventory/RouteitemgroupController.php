<?php

namespace App\Http\Controllers\Inventory;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class RouteitemgroupController extends Controller
{
    public function index(Request $request): Response
    {
        $allowedPerPage = [10, 25, 50, 100];
        $allowedSorts = ['routeitemgrpcode', 'description', 'itemgroupname', 'transferstatus'];
        $perPage = (int) $request->input('per_page', 10);
        $sortBy = $request->input('sort_by', 'routeitemgrpcode');
        $sortDir = $request->input('sort_dir', 'asc') === 'desc' ? 'desc' : 'asc';

        if (!in_array($perPage, $allowedPerPage, true)) {
            $perPage = 10;
        }

        if (!in_array($sortBy, $allowedSorts, true)) {
            $sortBy = 'routeitemgrpcode';
        }

        $query = DB::table('routeitemgrp as rig')
            ->leftJoin('itemgroup as ig', 'ig.itemgroupcode', '=', 'rig.itemgroupcode')
            ->select([
                'rig.routeitemgrpcode',
                'rig.description',
                'rig.itemgroupcode',
                'rig.transferstatus',
                'ig.itemgroupname',
                'ig.arbitemgroup',
            ]);

        if ($request->filled('search')) {
            $search = trim((string) $request->input('search'));
            $query->where(function ($builder) use ($search) {
                $builder
                    ->where('rig.routeitemgrpcode', 'like', "%{$search}%")
                    ->orWhere('rig.description', 'like', "%{$search}%")
                    ->orWhere('ig.itemgroupname', 'like', "%{$search}%")
                    ->orWhere('ig.arbitemgroup', 'like', "%{$search}%");
            });
        }

        $routeItemGroups = $query
            ->orderBy($sortBy === 'itemgroupname' ? 'ig.itemgroupname' : "rig.{$sortBy}", $sortDir)
            ->paginate($perPage)
            ->withQueryString()
            ->through(fn ($record) => [
                'routeitemgrpcode' => (int) $record->routeitemgrpcode,
                'description' => $record->description,
                'itemgroupcode' => $record->itemgroupcode !== null ? (int) $record->itemgroupcode : 0,
                'transferstatus' => (int) ($record->transferstatus ?? 0),
                'itemgroupname' => $record->itemgroupname,
                'arbitemgroup' => $record->arbitemgroup,
            ]);

        return Inertia::render('inventory/routeitemgroup/Index', [
            'routeItemGroups' => $routeItemGroups,
            'filters' => [
                'search' => $request->input('search', ''),
                'per_page' => $perPage,
                'sort_by' => $sortBy,
                'sort_dir' => $sortDir,
            ],
        ]);
    }

    public function create(): Response
    {
        $props = $this->formProps();
        $props['routeItemGroupData']['routeitemgrpcode'] = $this->nextCode();

        return Inertia::render('inventory/routeitemgroup/Create', $props);
    }

    public function show(int $routeitemgroup): Response
    {
        return Inertia::render('inventory/routeitemgroup/View', $this->formProps($routeitemgroup));
    }

    public function edit(int $routeitemgroup): Response
    {
        return Inertia::render('inventory/routeitemgroup/Edit', $this->formProps($routeitemgroup));
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validatedData($request);
        $username = auth()->user()?->username ?? auth()->user()?->name ?? 'SYSTEM';

        $routeItemGroupId = DB::table('routeitemgrp')->insertGetId([
            'itemgroupcode' => $this->normalizeItemGroupCode($data['itemgroupcode']),
            'description' => $data['description'],
            'created' => $username,
            'cdat' => now(),
            'modified' => $username,
            'mdat' => now(),
            'transferstatus' => (int) $data['transferstatus'],
        ]);

        $this->syncItems($routeItemGroupId, $data['items'] ?? []);

        return redirect()
            ->route('inventory.routeitemgroup.index')
            ->with('success', __('success.route_item_group_created'));
    }

    public function update(Request $request, int $routeitemgroup): RedirectResponse
    {
        $current = DB::table('routeitemgrp')->where('routeitemgrpcode', $routeitemgroup)->first();
        abort_unless($current, 404);

        $data = $this->validatedData($request);
        $username = auth()->user()?->username ?? auth()->user()?->name ?? 'SYSTEM';

        $hasLinkedRoute = DB::table('routemaster')
            ->where('routeitemgrpcode', $routeitemgroup)
            ->exists();

        if ($hasLinkedRoute && (int) ($current->transferstatus ?? 0) === 1 && (int) $data['transferstatus'] === 0) {
            return back()->with('error', __('error.route_item_group_inactivate_linked'));
        }

        DB::table('routeitemgrp')
            ->where('routeitemgrpcode', $routeitemgroup)
            ->update([
                'itemgroupcode' => $this->normalizeItemGroupCode($data['itemgroupcode']),
                'description' => $data['description'],
                'transferstatus' => (int) $data['transferstatus'],
                'modified' => $username,
                'mdat' => now(),
            ]);

        $this->syncItems($routeitemgroup, $data['items'] ?? []);

        return redirect()
            ->route('inventory.routeitemgroup.index')
            ->with('success', __('success.route_item_group_updated'));
    }

    public function destroy(int $routeitemgroup): RedirectResponse
    {
        $hasLinkedRoute = DB::table('routemaster')
            ->where('routeitemgrpcode', $routeitemgroup)
            ->exists();

        if ($hasLinkedRoute) {
            return back()->with('error', __('error.route_item_group_delete_failed'));
        }

        DB::transaction(function () use ($routeitemgroup) {
            if (Schema::hasTable('routeitemmapping')) {
                DB::table('routeitemmapping')
                    ->where('routeitemgrpcode', $routeitemgroup)
                    ->delete();
            }

            DB::table('routeitemgrp')
                ->where('routeitemgrpcode', $routeitemgroup)
                ->delete();
        });

        return back()->with('success', __('success.route_item_group_deleted'));
    }

    public function itemOptions(Request $request): JsonResponse
    {
        $data = $request->validate([
            'item_group' => ['nullable', 'integer', 'min:0'],
        ]);

        return response()->json([
            'items' => $this->availableItems((int) ($data['item_group'] ?? 0)),
        ]);
    }

    private function formProps(?int $routeItemGroupId = null): array
    {
        return [
            'routeItemGroupData' => $this->routeItemGroupData($routeItemGroupId),
            'lookupOptions' => [
                'itemGroups' => $this->itemGroupOptions(),
                'selectedItems' => $this->selectedItems($routeItemGroupId),
            ],
            'formMeta' => [
                'itemOptionsUrl' => route('inventory.routeitemgroup.item-options'),
            ],
            'optionSets' => [
                'statusOptions' => [
                    ['id' => 1, 'label' => 'Active'],
                    ['id' => 0, 'label' => 'Inactive'],
                ],
            ],
        ];
    }

    private function routeItemGroupData(?int $routeItemGroupId): array
    {
        if (!$routeItemGroupId) {
            return [
                'routeitemgrpcode' => null,
                'description' => '',
                'itemgroupcode' => 0,
                'transferstatus' => 1,
            ];
        }

        $record = DB::table('routeitemgrp')
            ->where('routeitemgrpcode', $routeItemGroupId)
            ->first();

        abort_unless($record, 404);

        return [
            'routeitemgrpcode' => (int) $record->routeitemgrpcode,
            'description' => $record->description ?? '',
            'itemgroupcode' => $record->itemgroupcode !== null ? (int) $record->itemgroupcode : 0,
            'transferstatus' => (int) ($record->transferstatus ?? 0),
        ];
    }

    private function validatedData(Request $request): array
    {
        return $request->validate([
            'description' => ['required', 'string', 'max:30'],
            'itemgroupcode' => ['nullable', 'integer', 'min:0'],
            'transferstatus' => ['required', 'integer', Rule::in([0, 1])],
            'items' => ['array'],
            'items.*' => ['integer', Rule::exists('itemmaster', 'actualitemcode')],
        ]);
    }

    private function syncItems(int $routeItemGroupId, array $itemIds): void
    {
        if (!Schema::hasTable('routeitemmapping')) {
            return;
        }

        $uniqueItems = collect($itemIds)
            ->map(fn ($value) => (int) $value)
            ->unique()
            ->values();

        DB::transaction(function () use ($routeItemGroupId, $uniqueItems) {
            DB::table('routeitemmapping')
                ->where('routeitemgrpcode', $routeItemGroupId)
                ->delete();

            if ($uniqueItems->isEmpty()) {
                return;
            }

            $rows = $uniqueItems
                ->map(fn ($itemId) => [
                    'routeitemgrpcode' => $routeItemGroupId,
                    'itemcode' => $itemId,
                    'transferstatus' => 1,
                ])
                ->all();

            DB::table('routeitemmapping')->insert($rows);
        });
    }

    private function itemGroupOptions(): array
    {
        $query = DB::table('itemgroup');

        if (Schema::hasColumn('itemgroup', 'activestatus')) {
            $query->where('activestatus', 1);
        }

        $options = $query
            ->orderBy('itemgroupname')
            ->get(['itemgroupcode', 'itemgroupname'])
            ->map(fn ($record) => [
                'id' => (int) $record->itemgroupcode,
                'label' => trim($record->itemgroupcode . ' -- ' . ($record->itemgroupname ?? '')),
            ])
            ->all();

        array_unshift($options, ['id' => 0, 'label' => '--- ALL Items ---']);

        return $options;
    }

    private function selectedItems(?int $routeItemGroupId): array
    {
        if (!$routeItemGroupId || !Schema::hasTable('routeitemmapping')) {
            return [];
        }

        $selected = DB::table('routeitemmapping as rim')
            ->join('itemmaster as item', 'item.actualitemcode', '=', 'rim.itemcode')
            ->where('rim.routeitemgrpcode', $routeItemGroupId)
            ->orderBy('item.actualitemcode')
            ->select([
                'item.actualitemcode as id',
                'item.alternatecode',
                'item.itemshortdescription',
            ])
            ->get();

        return $this->transformItems($selected, $this->useAlternateCode());
    }

    private function availableItems(int $itemGroupCode): array
    {
        $query = DB::table('itemmaster as item')
            ->orderBy('item.actualitemcode')
            ->select([
                'item.actualitemcode as id',
                'item.alternatecode',
                'item.itemshortdescription',
            ]);

        if (Schema::hasColumn('itemmaster', 'activeitem')) {
            $query->where('item.activeitem', 1);
        }

        if ($itemGroupCode > 0) {
            $query->where('item.itemgroupcode', $itemGroupCode);
        }

        return $this->transformItems($query->get(), $this->useAlternateCode());
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

    private function nextCode(): int
    {
        return ((int) DB::table('routeitemgrp')->max('routeitemgrpcode')) + 1;
    }

    private function normalizeItemGroupCode(mixed $value): ?int
    {
        $code = (int) ($value ?? 0);

        return $code > 0 ? $code : 0;
    }
}
