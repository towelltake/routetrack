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

class MajorCategoryController extends Controller
{
    public function index(Request $request): Response
    {
        $user = $request->user();
        $allowedPerPage = [10, 25, 50, 100];
        $allowedSorts = ['majorcategorycode', 'alternatecode', 'description', 'arbdescription', 'activestatus'];
        $perPage = (int) $request->input('per_page', 10);
        $sortBy = $request->input('sort_by', 'majorcategorycode');
        $sortDir = $request->input('sort_dir', 'asc') === 'desc' ? 'desc' : 'asc';

        if (!in_array($perPage, $allowedPerPage, true)) {
            $perPage = 10;
        }

        if (!in_array($sortBy, $allowedSorts, true)) {
            $sortBy = 'majorcategorycode';
        }

        $query = DB::table('majorcategory')
            ->leftJoin('companygroup', 'companygroup.companygroupcode', '=', 'majorcategory.companygroupcode')
            ->select([
                'majorcategory.majorcategorycode',
                'majorcategory.alternatecode',
                'majorcategory.companygroupcode',
                'majorcategory.description',
                'majorcategory.arbdescription',
                'majorcategory.activestatus',
                'companygroup.description as company_group_name',
                'companygroup.arbdescription as company_group_name_ar',
            ]);

        $query->whereIn('majorcategory.companygroupcode', $this->allowedCompanyGroupCodes($user));

        if ($request->filled('search')) {
            $search = trim((string) $request->input('search'));
            $query->where(function ($builder) use ($search) {
                $builder
                    ->where('majorcategory.alternatecode', 'like', "%{$search}%")
                    ->orWhere('majorcategory.description', 'like', "%{$search}%")
                    ->orWhere('majorcategory.arbdescription', 'like', "%{$search}%")
                    ->orWhere('companygroup.description', 'like', "%{$search}%")
                    ->orWhere('companygroup.arbdescription', 'like', "%{$search}%");
            });
        }

        $majorCategories = $query
            ->orderBy("majorcategory.{$sortBy}", $sortDir)
            ->paginate($perPage)
            ->withQueryString()
            ->through(fn ($record) => [
                'majorcategorycode' => (int) $record->majorcategorycode,
                'alternatecode' => $record->alternatecode,
                'companygroupcode' => $record->companygroupcode !== null ? (int) $record->companygroupcode : null,
                'description' => $record->description,
                'arbdescription' => $record->arbdescription,
                'activestatus' => (int) ($record->activestatus ?? 0),
                'company_group_name' => $record->company_group_name,
                'company_group_name_ar' => $record->company_group_name_ar,
            ]);

        $companyGroups = DB::table('companygroup')
            ->where('activestatus', 1)
            ->whereIn('companygroupcode', $this->allowedCompanyGroupCodes($user))
            ->orderBy('description')
            ->get(['companygroupcode', 'description', 'arbdescription'])
            ->map(fn ($group) => [
                'companygroupcode' => (int) $group->companygroupcode,
                'description' => $group->description,
                'arbdescription' => $group->arbdescription,
            ])
            ->all();

        return Inertia::render('inventory/majorcategory/Index', [
            'majorCategories' => $majorCategories,
            'filters' => [
                'search' => $request->input('search', ''),
                'per_page' => $perPage,
                'sort_by' => $sortBy,
                'sort_dir' => $sortDir,
            ],
            'companyGroups' => $companyGroups,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'alternatecode' => ['nullable', 'string', 'max:50', Rule::unique('majorcategory', 'alternatecode')],
            'description' => ['required', 'string', 'max:50'],
            'arbdescription' => ['nullable', 'string', 'max:50'],
            'companygroupcode' => ['required', 'integer', Rule::exists('companygroup', 'companygroupcode')],
        ]);
        $this->assertCompanyGroupAccess($request, (int) $data['companygroupcode']);

        DB::table('majorcategory')->insert([
            'alternatecode' => $data['alternatecode'] ?: null,
            'companygroupcode' => (int) $data['companygroupcode'],
            'description' => $data['description'],
            'arbdescription' => $data['arbdescription'] ?: null,
            'created' => auth()->user()->name ?? 'SYSTEM',
            'cdat' => now(),
            'modified' => auth()->user()->name ?? 'SYSTEM',
            'mdat' => now(),
            'activestatus' => 1,
        ]);

        return back()->with('success', __('success.majorcategory_created'));
    }

    public function update(Request $request, int $majorcategory): RedirectResponse
    {
        $current = DB::table('majorcategory')->where('majorcategorycode', $majorcategory)->first();
        abort_unless($current, 404);
        $this->assertCompanyGroupAccess($request, (int) $current->companygroupcode);

        $data = $request->validate([
            'alternatecode' => ['nullable', 'string', 'max:50', Rule::unique('majorcategory', 'alternatecode')->ignore($majorcategory, 'majorcategorycode')],
            'description' => ['required', 'string', 'max:50'],
            'arbdescription' => ['nullable', 'string', 'max:50'],
            'companygroupcode' => ['required', 'integer', Rule::exists('companygroup', 'companygroupcode')],
            'activestatus' => ['required', 'integer', 'in:0,1'],
        ]);
        $this->assertCompanyGroupAccess($request, (int) $data['companygroupcode']);

        $hasLinkedSubMajorCategory = DB::table('submajorcategory')
            ->where('majorcategorycode', $majorcategory)
            ->exists();

        if ($hasLinkedSubMajorCategory && (int) ($current->activestatus ?? 0) === 1 && (int) $data['activestatus'] === 0) {
            return back()->with('error', __('error.majorcategory_inactivate_linked_submajorcategory'));
        }

        DB::table('majorcategory')
            ->where('majorcategorycode', $majorcategory)
            ->update([
                'alternatecode' => $data['alternatecode'] ?: null,
                'companygroupcode' => (int) $data['companygroupcode'],
                'description' => $data['description'],
                'arbdescription' => $data['arbdescription'] ?: null,
                'activestatus' => (int) $data['activestatus'],
                'modified' => auth()->user()->name ?? 'SYSTEM',
                'mdat' => now(),
            ]);

        return back()->with('success', __('success.majorcategory_updated'));
    }

    public function destroy(int $majorcategory): RedirectResponse
    {
        $record = DB::table('majorcategory')
            ->where('majorcategorycode', $majorcategory)
            ->first(['companygroupcode']);
        abort_unless($record, 404);
        $this->assertCompanyGroupAccess(request(), (int) $record->companygroupcode);

        $hasLinkedSubMajorCategory = DB::table('submajorcategory')
            ->where('majorcategorycode', $majorcategory)
            ->exists();

        if ($hasLinkedSubMajorCategory) {
            return back()->with('error', __('error.majorcategory_delete_failed'));
        }

        DB::table('majorcategory')->where('majorcategorycode', $majorcategory)->delete();

        return back()->with('success', __('success.majorcategory_deleted'));
    }

    private function allowedCompanyGroupCodes($user): array
    {
        $query = DB::table('companygroup')->select('companygroupcode');
        app(AccessScopeService::class)->scopeQuery($user, $query, 'company', 'parentcompany');

        return $query->pluck('companygroupcode')->map(fn ($code) => (int) $code)->all();
    }

    private function assertCompanyGroupAccess(Request $request, int|string|null $companyGroupCode): void
    {
        $allowed = DB::table('companygroup')
            ->where('companygroupcode', $companyGroupCode)
            ->whereIn('companygroupcode', $this->allowedCompanyGroupCodes($request->user()))
            ->exists();

        if (!$allowed) {
            throw ValidationException::withMessages([
                'companygroupcode' => 'The selected company group is outside your access scope.',
            ]);
        }
    }
}
