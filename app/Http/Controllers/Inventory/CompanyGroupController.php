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

class CompanyGroupController extends Controller
{
    public function index(Request $request): Response
    {
        $scope = app(AccessScopeService::class);
        $user = $request->user();
        $allowedPerPage = [10, 25, 50, 100];
        $allowedSorts = ['companygroupcode', 'alternatecode', 'description', 'arbdescription', 'activestatus'];
        $perPage = (int) $request->input('per_page', 10);
        $sortBy = $request->input('sort_by', 'companygroupcode');
        $sortDir = $request->input('sort_dir', 'asc') === 'desc' ? 'desc' : 'asc';

        if (!in_array($perPage, $allowedPerPage, true)) {
            $perPage = 10;
        }

        if (!in_array($sortBy, $allowedSorts, true)) {
            $sortBy = 'companygroupcode';
        }

        $query = DB::table('companygroup')
            ->leftJoin('company', 'company.cmpycode', '=', 'companygroup.parentcompany')
            ->select([
                'companygroup.companygroupcode',
                'companygroup.alternatecode',
                'companygroup.parentcompany',
                'companygroup.description',
                'companygroup.arbdescription',
                'companygroup.activestatus',
                'company.name as company_name',
                'company.arbcompanyname as company_name_ar',
            ]);

        $scope->scopeQuery($user, $query, 'company', 'companygroup.parentcompany');

        if ($request->filled('search')) {
            $search = trim((string) $request->input('search'));
            $query->where(function ($builder) use ($search) {
                $builder
                    ->where('companygroup.alternatecode', 'like', "%{$search}%")
                    ->orWhere('companygroup.description', 'like', "%{$search}%")
                    ->orWhere('companygroup.arbdescription', 'like', "%{$search}%")
                    ->orWhere('company.name', 'like', "%{$search}%");
            });
        }

        $companyGroups = $query
            ->orderBy("companygroup.{$sortBy}", $sortDir)
            ->paginate($perPage)
            ->withQueryString()
            ->through(fn ($record) => [
                'companygroupcode' => (int) $record->companygroupcode,
                'alternatecode' => $record->alternatecode,
                'parentcompany' => $record->parentcompany !== null ? (int) $record->parentcompany : null,
                'description' => $record->description,
                'arbdescription' => $record->arbdescription,
                'activestatus' => (int) ($record->activestatus ?? 0),
                'company_name' => $record->company_name,
                'company_name_ar' => $record->company_name_ar,
            ]);

        $companies = DB::table('company')
            ->where('activestatus', 1)
            ->tap(fn ($query) => $scope->scopeQuery($user, $query, 'company', 'cmpycode'))
            ->orderBy('name')
            ->get(['cmpycode', 'name', 'arbcompanyname'])
            ->map(fn ($company) => [
                'cmpycode' => (int) $company->cmpycode,
                'name' => $company->name,
                'arbcompanyname' => $company->arbcompanyname,
            ])
            ->all();

        return Inertia::render('inventory/companygroup/Index', [
            'companyGroups' => $companyGroups,
            'filters' => [
                'search' => $request->input('search', ''),
                'per_page' => $perPage,
                'sort_by' => $sortBy,
                'sort_dir' => $sortDir,
            ],
            'companies' => $companies,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'alternatecode' => ['required', 'string', 'max:50', Rule::unique('companygroup', 'alternatecode')],
            'description' => ['required', 'string', 'max:50'],
            'arbdescription' => ['required', 'string', 'max:50'],
            'parentcompany' => ['required', 'integer', Rule::exists('company', 'cmpycode')],
        ]);
        $this->assertCompanyAccess($request, (int) $data['parentcompany']);

        DB::table('companygroup')->insert([
            'alternatecode' => $data['alternatecode'],
            'parentcompany' => (int) $data['parentcompany'],
            'description' => $data['description'],
            'arbdescription' => $data['arbdescription'],
            'created' => auth()->user()->name ?? 'SYSTEM',
            'cdat' => now(),
            'modified' => auth()->user()->name ?? 'SYSTEM',
            'mdat' => now(),
            'activestatus' => 1,
        ]);

        return back()->with('success', __('success.companygroup_created'));
    }

    public function update(Request $request, int $companygroup): RedirectResponse
    {
        $current = DB::table('companygroup')->where('companygroupcode', $companygroup)->first();
        abort_unless($current, 404);
        $this->assertCompanyAccess($request, (int) $current->parentcompany);

        $data = $request->validate([
            'alternatecode' => ['required', 'string', 'max:50', Rule::unique('companygroup', 'alternatecode')->ignore($companygroup, 'companygroupcode')],
            'description' => ['required', 'string', 'max:50'],
            'arbdescription' => ['required', 'string', 'max:50'],
            'parentcompany' => ['required', 'integer', Rule::exists('company', 'cmpycode')],
            'activestatus' => ['required', 'integer', 'in:0,1'],
        ]);
        $this->assertCompanyAccess($request, (int) $data['parentcompany']);

        $hasLinkedMajorCategory = DB::table('majorcategory')
            ->where('companygroupcode', $companygroup)
            ->exists();

        if ($hasLinkedMajorCategory && (int) ($current->activestatus ?? 0) === 1 && (int) $data['activestatus'] === 0) {
            return back()->with('error', __('error.companygroup_inactivate_linked_majorcategory'));
        }

        DB::table('companygroup')
            ->where('companygroupcode', $companygroup)
            ->update([
                'alternatecode' => $data['alternatecode'],
                'parentcompany' => (int) $data['parentcompany'],
                'description' => $data['description'],
                'arbdescription' => $data['arbdescription'],
                'activestatus' => (int) $data['activestatus'],
                'modified' => auth()->user()->name ?? 'SYSTEM',
                'mdat' => now(),
            ]);

        return back()->with('success', __('success.companygroup_updated'));
    }

    public function destroy(int $companygroup): RedirectResponse
    {
        $record = DB::table('companygroup')
            ->where('companygroupcode', $companygroup)
            ->first(['parentcompany']);
        abort_unless($record, 404);
        $this->assertCompanyAccess(request(), (int) $record->parentcompany);

        $hasLinkedMajorCategory = DB::table('majorcategory')
            ->where('companygroupcode', $companygroup)
            ->exists();

        if ($hasLinkedMajorCategory) {
            return back()->with('error', __('error.companygroup_delete_failed'));
        }

        DB::table('companygroup')->where('companygroupcode', $companygroup)->delete();

        return back()->with('success', __('success.companygroup_deleted'));
    }

    private function assertCompanyAccess(Request $request, int|string|null $companyCode): void
    {
        if (!app(AccessScopeService::class)->allows($request->user(), 'company', $companyCode)) {
            throw ValidationException::withMessages([
                'parentcompany' => 'The selected company is outside your access scope.',
            ]);
        }
    }
}
