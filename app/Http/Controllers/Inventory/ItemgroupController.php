<?php

namespace App\Http\Controllers\Inventory;

use App\Http\Controllers\Controller;
use App\Services\AccessScopeService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class ItemgroupController extends Controller
{
    public function index(Request $request): Response
    {
        $user = $request->user();
        $allowedPerPage = [10, 25, 50, 100];
        $allowedSorts = ['itemgroupcode', 'alternateitemgroupcode', 'itemgroupname', 'arbitemgroup', 'activestatus'];
        $perPage = (int) $request->input('per_page', 10);
        $sortBy = $request->input('sort_by', 'itemgroupcode');
        $sortDir = $request->input('sort_dir', 'asc') === 'desc' ? 'desc' : 'asc';

        if (!in_array($perPage, $allowedPerPage, true)) {
            $perPage = 10;
        }

        if (!in_array($sortBy, $allowedSorts, true)) {
            $sortBy = 'itemgroupcode';
        }

        $query = DB::table('itemgroup')
            ->leftJoin('submajorcategory', 'submajorcategory.submajorcategorycode', '=', 'itemgroup.submajorcategorycode')
            ->select([
                'itemgroup.itemgroupcode',
                'itemgroup.alternateitemgroupcode',
                'itemgroup.submajorcategorycode',
                'itemgroup.itemgroupname',
                'itemgroup.arbitemgroup',
                'itemgroup.activestatus',
                'submajorcategory.description as sub_major_category_name',
                'submajorcategory.arbdescription as sub_major_category_name_ar',
            ]);

        $query->whereIn('itemgroup.submajorcategorycode', $this->allowedSubMajorCategoryCodes($user));

        if ($request->filled('search')) {
            $search = trim((string) $request->input('search'));
            $query->where(function ($builder) use ($search) {
                $builder
                    ->where('itemgroup.itemgroupcode', 'like', "%{$search}%")
                    ->orWhere('itemgroup.alternateitemgroupcode', 'like', "%{$search}%")
                    ->orWhere('itemgroup.itemgroupname', 'like', "%{$search}%")
                    ->orWhere('itemgroup.arbitemgroup', 'like', "%{$search}%")
                    ->orWhere('submajorcategory.description', 'like', "%{$search}%")
                    ->orWhere('submajorcategory.arbdescription', 'like', "%{$search}%");
            });
        }

        $itemGroups = $query
            ->orderBy("itemgroup.{$sortBy}", $sortDir)
            ->paginate($perPage)
            ->withQueryString()
            ->through(fn ($record) => [
                'itemgroupcode' => (int) $record->itemgroupcode,
                'alternateitemgroupcode' => $record->alternateitemgroupcode,
                'submajorcategorycode' => $record->submajorcategorycode !== null ? (int) $record->submajorcategorycode : null,
                'itemgroupname' => $record->itemgroupname,
                'arbitemgroup' => $record->arbitemgroup,
                'activestatus' => (int) ($record->activestatus ?? 0),
                'sub_major_category_name' => $record->sub_major_category_name,
                'sub_major_category_name_ar' => $record->sub_major_category_name_ar,
            ]);

        $subMajorCategories = DB::table('submajorcategory')
            ->where('activestatus', 1)
            ->whereIn('submajorcategorycode', $this->allowedSubMajorCategoryCodes($user))
            ->orderBy('description')
            ->get(['submajorcategorycode', 'description', 'arbdescription'])
            ->map(fn ($category) => [
                'submajorcategorycode' => (int) $category->submajorcategorycode,
                'description' => $category->description,
                'arbdescription' => $category->arbdescription,
            ])
            ->all();

        $nextItemGroupCode = ((int) DB::table('itemgroup')->max('itemgroupcode')) + 1;

        return Inertia::render('inventory/itemgroup/Index', [
            'itemGroups' => $itemGroups,
            'filters' => [
                'search' => $request->input('search', ''),
                'per_page' => $perPage,
                'sort_by' => $sortBy,
                'sort_dir' => $sortDir,
            ],
            'subMajorCategories' => $subMajorCategories,
            'nextItemGroupCode' => $nextItemGroupCode,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'alternateitemgroupcode' => ['nullable', 'string', 'max:50', Rule::unique('itemgroup', 'alternateitemgroupcode')],
            'itemgroupname' => ['required', 'string', 'max:50'],
            'arbitemgroup' => ['nullable', 'string', 'max:50'],
            'submajorcategorycode' => ['required', 'integer', Rule::exists('submajorcategory', 'submajorcategorycode')],
        ]);
        $this->assertSubMajorCategoryAccess($request, (int) $data['submajorcategorycode']);

        DB::table('itemgroup')->insert([
            'alternateitemgroupcode' => $data['alternateitemgroupcode'] ?: null,
            'submajorcategorycode' => (int) $data['submajorcategorycode'],
            'itemgroupname' => $data['itemgroupname'],
            'arbitemgroup' => $data['arbitemgroup'] ?: '',
            'created' => auth()->user()->name ?? 'SYSTEM',
            'cdat' => now(),
            'modified' => auth()->user()->name ?? 'SYSTEM',
            'mdat' => now(),
            'activestatus' => 1,
        ]);

        return back()->with('success', __('success.itemgroup_created'));
    }

    public function update(Request $request, int $itemgroup): RedirectResponse
    {
        $current = DB::table('itemgroup')->where('itemgroupcode', $itemgroup)->first();
        abort_unless($current, 404);
        $this->assertItemGroupAccess($request, $itemgroup);

        $data = $request->validate([
            'alternateitemgroupcode' => ['nullable', 'string', 'max:50', Rule::unique('itemgroup', 'alternateitemgroupcode')->ignore($itemgroup, 'itemgroupcode')],
            'itemgroupname' => ['required', 'string', 'max:50'],
            'arbitemgroup' => ['nullable', 'string', 'max:50'],
            'submajorcategorycode' => ['required', 'integer', Rule::exists('submajorcategory', 'submajorcategorycode')],
            'activestatus' => ['required', 'integer', 'in:0,1'],
        ]);
        $this->assertSubMajorCategoryAccess($request, (int) $data['submajorcategorycode']);

        $hasLinkedItemMaster = DB::table('itemmaster')
            ->where('itemgroupcode', $itemgroup)
            ->exists();

        if ($hasLinkedItemMaster && (int) ($current->activestatus ?? 0) === 1 && (int) $data['activestatus'] === 0) {
            return back()->with('error', __('error.itemgroup_inactivate_linked_itemmaster'));
        }

        DB::table('itemgroup')
            ->where('itemgroupcode', $itemgroup)
            ->update([
                'alternateitemgroupcode' => $data['alternateitemgroupcode'] ?: null,
                'submajorcategorycode' => (int) $data['submajorcategorycode'],
                'itemgroupname' => $data['itemgroupname'],
                'arbitemgroup' => $data['arbitemgroup'] ?: '',
                'activestatus' => (int) $data['activestatus'],
                'modified' => auth()->user()->name ?? 'SYSTEM',
                'mdat' => now(),
            ]);

        return back()->with('success', __('success.itemgroup_updated'));
    }

    public function destroy(int $itemgroup): RedirectResponse
    {
        $this->assertItemGroupAccess(request(), $itemgroup);

        $hasLinkedItemMaster = DB::table('itemmaster')
            ->where('itemgroupcode', $itemgroup)
            ->exists();

        if ($hasLinkedItemMaster) {
            return back()->with('error', __('error.itemgroup_delete_failed'));
        }

        DB::table('itemgroup')->where('itemgroupcode', $itemgroup)->delete();

        return back()->with('success', __('success.itemgroup_deleted'));
    }

    private function allowedSubMajorCategoryCodes($user): array
    {
        $query = DB::table('submajorcategory as sub')
            ->join('majorcategory as major', 'major.majorcategorycode', '=', 'sub.majorcategorycode')
            ->join('companygroup as companygroup', 'companygroup.companygroupcode', '=', 'major.companygroupcode')
            ->select('sub.submajorcategorycode');

        app(AccessScopeService::class)->scopeQuery($user, $query, 'company', 'companygroup.parentcompany');

        return $query->pluck('sub.submajorcategorycode')->map(fn ($code) => (int) $code)->all();
    }

    private function assertSubMajorCategoryAccess(Request $request, int|string|null $subMajorCategoryCode): void
    {
        $allowed = DB::table('submajorcategory')
            ->where('submajorcategorycode', $subMajorCategoryCode)
            ->whereIn('submajorcategorycode', $this->allowedSubMajorCategoryCodes($request->user()))
            ->exists();

        if (!$allowed) {
            throw ValidationException::withMessages([
                'submajorcategorycode' => 'The selected sub major category is outside your access scope.',
            ]);
        }
    }

    private function assertItemGroupAccess(Request $request, int $itemGroupCode): void
    {
        $allowed = DB::table('itemgroup')
            ->where('itemgroupcode', $itemGroupCode)
            ->whereIn('submajorcategorycode', $this->allowedSubMajorCategoryCodes($request->user()))
            ->exists();

        abort_unless($allowed, 403);
    }
}
