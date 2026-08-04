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

class SubMajorCategoryController extends Controller
{
    public function index(Request $request): Response
    {
        $user = $request->user();
        $allowedPerPage = [10, 25, 50, 100];
        $allowedSorts = ['submajorcategorycode', 'alternatecode', 'description', 'arbdescription', 'activestatus'];
        $perPage = (int) $request->input('per_page', 10);
        $sortBy = $request->input('sort_by', 'submajorcategorycode');
        $sortDir = $request->input('sort_dir', 'asc') === 'desc' ? 'desc' : 'asc';

        if (!in_array($perPage, $allowedPerPage, true)) {
            $perPage = 10;
        }

        if (!in_array($sortBy, $allowedSorts, true)) {
            $sortBy = 'submajorcategorycode';
        }

        $query = DB::table('submajorcategory')
            ->leftJoin('majorcategory', 'majorcategory.majorcategorycode', '=', 'submajorcategory.majorcategorycode')
            ->select([
                'submajorcategory.submajorcategorycode',
                'submajorcategory.alternatecode',
                'submajorcategory.majorcategorycode',
                'submajorcategory.description',
                'submajorcategory.arbdescription',
                'submajorcategory.activestatus',
                'majorcategory.description as major_category_name',
                'majorcategory.arbdescription as major_category_name_ar',
            ]);

        $query->whereIn('submajorcategory.majorcategorycode', $this->allowedMajorCategoryCodes($user));

        if ($request->filled('search')) {
            $search = trim((string) $request->input('search'));
            $query->where(function ($builder) use ($search) {
                $builder
                    ->where('submajorcategory.alternatecode', 'like', "%{$search}%")
                    ->orWhere('submajorcategory.description', 'like', "%{$search}%")
                    ->orWhere('submajorcategory.arbdescription', 'like', "%{$search}%")
                    ->orWhere('majorcategory.description', 'like', "%{$search}%")
                    ->orWhere('majorcategory.arbdescription', 'like', "%{$search}%");
            });
        }

        $subMajorCategories = $query
            ->orderBy("submajorcategory.{$sortBy}", $sortDir)
            ->paginate($perPage)
            ->withQueryString()
            ->through(fn ($record) => [
                'submajorcategorycode' => (int) $record->submajorcategorycode,
                'alternatecode' => $record->alternatecode,
                'majorcategorycode' => $record->majorcategorycode !== null ? (int) $record->majorcategorycode : null,
                'description' => $record->description,
                'arbdescription' => $record->arbdescription,
                'activestatus' => (int) ($record->activestatus ?? 0),
                'major_category_name' => $record->major_category_name,
                'major_category_name_ar' => $record->major_category_name_ar,
            ]);

        $majorCategories = DB::table('majorcategory')
            ->where('activestatus', 1)
            ->whereIn('majorcategorycode', $this->allowedMajorCategoryCodes($user))
            ->orderBy('description')
            ->get(['majorcategorycode', 'description', 'arbdescription'])
            ->map(fn ($category) => [
                'majorcategorycode' => (int) $category->majorcategorycode,
                'description' => $category->description,
                'arbdescription' => $category->arbdescription,
            ])
            ->all();

        return Inertia::render('inventory/submajorcategory/Index', [
            'subMajorCategories' => $subMajorCategories,
            'filters' => [
                'search' => $request->input('search', ''),
                'per_page' => $perPage,
                'sort_by' => $sortBy,
                'sort_dir' => $sortDir,
            ],
            'majorCategories' => $majorCategories,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'alternatecode' => ['nullable', 'string', 'max:50', Rule::unique('submajorcategory', 'alternatecode')],
            'description' => ['required', 'string', 'max:50'],
            'arbdescription' => ['nullable', 'string', 'max:50'],
            'majorcategorycode' => ['required', 'integer', Rule::exists('majorcategory', 'majorcategorycode')],
        ]);
        $this->assertMajorCategoryAccess($request, (int) $data['majorcategorycode']);

        DB::table('submajorcategory')->insert([
            'alternatecode' => $data['alternatecode'] ?: null,
            'majorcategorycode' => (int) $data['majorcategorycode'],
            'description' => $data['description'],
            'arbdescription' => $data['arbdescription'] ?: null,
            'created' => auth()->user()->name ?? 'SYSTEM',
            'cdat' => now(),
            'modified' => auth()->user()->name ?? 'SYSTEM',
            'mdat' => now(),
            'activestatus' => 1,
        ]);

        return back()->with('success', __('success.submajorcategory_created'));
    }

    public function update(Request $request, int $submajorcategory): RedirectResponse
    {
        $current = DB::table('submajorcategory')->where('submajorcategorycode', $submajorcategory)->first();
        abort_unless($current, 404);
        $this->assertSubMajorCategoryAccess($request, $submajorcategory);

        $data = $request->validate([
            'alternatecode' => ['nullable', 'string', 'max:50', Rule::unique('submajorcategory', 'alternatecode')->ignore($submajorcategory, 'submajorcategorycode')],
            'description' => ['required', 'string', 'max:50'],
            'arbdescription' => ['nullable', 'string', 'max:50'],
            'majorcategorycode' => ['required', 'integer', Rule::exists('majorcategory', 'majorcategorycode')],
            'activestatus' => ['required', 'integer', 'in:0,1'],
        ]);
        $this->assertMajorCategoryAccess($request, (int) $data['majorcategorycode']);

        $hasLinkedItemGroup = DB::table('itemgroup')
            ->where('submajorcategorycode', $submajorcategory)
            ->exists();

        if ($hasLinkedItemGroup && (int) ($current->activestatus ?? 0) === 1 && (int) $data['activestatus'] === 0) {
            return back()->with('error', __('error.submajorcategory_inactivate_linked_itemgroup'));
        }

        DB::table('submajorcategory')
            ->where('submajorcategorycode', $submajorcategory)
            ->update([
                'alternatecode' => $data['alternatecode'] ?: null,
                'majorcategorycode' => (int) $data['majorcategorycode'],
                'description' => $data['description'],
                'arbdescription' => $data['arbdescription'] ?: null,
                'activestatus' => (int) $data['activestatus'],
                'modified' => auth()->user()->name ?? 'SYSTEM',
                'mdat' => now(),
            ]);

        return back()->with('success', __('success.submajorcategory_updated'));
    }

    public function destroy(int $submajorcategory): RedirectResponse
    {
        $this->assertSubMajorCategoryAccess(request(), $submajorcategory);

        $hasLinkedItemGroup = DB::table('itemgroup')
            ->where('submajorcategorycode', $submajorcategory)
            ->exists();

        if ($hasLinkedItemGroup) {
            return back()->with('error', __('error.submajorcategory_delete_failed'));
        }

        DB::table('submajorcategory')->where('submajorcategorycode', $submajorcategory)->delete();

        return back()->with('success', __('success.submajorcategory_deleted'));
    }

    private function allowedMajorCategoryCodes($user): array
    {
        $query = DB::table('majorcategory')
            ->join('companygroup', 'companygroup.companygroupcode', '=', 'majorcategory.companygroupcode')
            ->select('majorcategory.majorcategorycode');

        app(AccessScopeService::class)->scopeQuery($user, $query, 'company', 'companygroup.parentcompany');

        return $query->pluck('majorcategory.majorcategorycode')->map(fn ($code) => (int) $code)->all();
    }

    private function assertMajorCategoryAccess(Request $request, int|string|null $majorCategoryCode): void
    {
        $allowed = DB::table('majorcategory')
            ->where('majorcategorycode', $majorCategoryCode)
            ->whereIn('majorcategorycode', $this->allowedMajorCategoryCodes($request->user()))
            ->exists();

        if (!$allowed) {
            throw ValidationException::withMessages([
                'majorcategorycode' => 'The selected major category is outside your access scope.',
            ]);
        }
    }

    private function assertSubMajorCategoryAccess(Request $request, int $subMajorCategoryCode): void
    {
        $allowed = DB::table('submajorcategory')
            ->where('submajorcategorycode', $subMajorCategoryCode)
            ->whereIn('majorcategorycode', $this->allowedMajorCategoryCodes($request->user()))
            ->exists();

        abort_unless($allowed, 403);
    }
}
