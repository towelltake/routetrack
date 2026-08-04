<?php

namespace App\Http\Controllers\Basic;

use App\Http\Controllers\Controller;
use App\Models\BranchManager;
use App\Models\CompanyMaster;
use App\Services\AccessScopeService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class BranchManagerController extends Controller
{
    public function index(): Response
    {
        $scope = app(AccessScopeService::class);
        $user = request()->user();
        $search = request('search');
        $perPage = (int) request('per_page', 10);
        $perPage = in_array($perPage, [10, 25, 50, 100], true) ? $perPage : 10;

        $managersQuery = BranchManager::query()
            ->leftJoin('company', 'company.cmpycode', '=', 'branchmanager.parentcompany')
            ->when($search, function ($query, $searchTerm) {
                $query->where(function ($inner) use ($searchTerm) {
                    $inner->where('branchmanager.branchmanagername', 'like', '%' . $searchTerm . '%')
                        ->orWhere('branchmanager.arbbranchmanagername', 'like', '%' . $searchTerm . '%')
                        ->orWhere('branchmanager.alternatebranchmanagercode', 'like', '%' . $searchTerm . '%')
                        ->orWhere('company.name', 'like', '%' . $searchTerm . '%');
                });
            });

        $scope->scopeQuery($user, $managersQuery, 'company', 'branchmanager.parentcompany');

        $managers = $managersQuery
            ->orderBy('branchmanager.branchmanagercode')
            ->paginate($perPage, [
                'branchmanager.branchmanagercode',
                'branchmanager.parentcompany',
                'branchmanager.branchmanagername',
                'branchmanager.arbbranchmanagername',
                'branchmanager.alternatebranchmanagercode',
                'branchmanager.activestatus',
                'branchmanager.cdat',
                'company.name as parentcompanyname',
            ])
            ->withQueryString();

        return Inertia::render('basic/branchmanager/Index', [
            'filters' => [
                'search' => $search,
                'per_page' => $perPage,
            ],
            'managers' => $managers,
            'companies' => $scope->scopeQuery($user, CompanyMaster::query(), 'company', 'cmpycode')->orderBy('name')->get([
                'cmpycode',
                'name',
            ]),
        ]);
    }

    private function validated(Request $request, ?BranchManager $manager = null): array
    {
        $data = $request->validate([
            'alternatebranchmanagercode' => [
                'nullable',
                'string',
                'max:50',
                Rule::unique('branchmanager', 'alternatebranchmanagercode')
                    ->ignore($manager?->branchmanagercode, 'branchmanagercode'),
            ],
            'parentcompany' => ['nullable', 'integer', Rule::exists('company', 'cmpycode')],
            'branchmanagername' => ['required', 'string', 'max:50'],
            'arbbranchmanagername' => ['required', 'string', 'max:50'],
            'activestatus' => ['required', 'integer', 'in:0,1'],
        ]);

        if (! app(AccessScopeService::class)->allows($request->user(), 'company', $data['parentcompany'] ?? null)) {
            throw ValidationException::withMessages([
                'parentcompany' => 'Selected company is outside your access scope.',
            ]);
        }

        return $data;
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request);
        $data['created'] = auth()->user()?->name;
        $data['cdat'] = now();
        $data['modified'] = auth()->user()?->name;
        $data['mdat'] = now();

        BranchManager::create($data);

        return back()->with('success', 'Branch/depot manager created.');
    }

    public function update(Request $request, BranchManager $branchmanager): RedirectResponse
    {
        abort_unless(app(AccessScopeService::class)->allows($request->user(), 'company', $branchmanager->parentcompany), 403);

        $data = $this->validated($request, $branchmanager);
        $data['modified'] = auth()->user()?->name;
        $data['mdat'] = now();

        $branchmanager->update($data);

        return back()->with('success', 'Branch/depot manager updated.');
    }

    public function destroy(BranchManager $branchmanager): RedirectResponse
    {
        abort_unless(app(AccessScopeService::class)->allows(request()->user(), 'company', $branchmanager->parentcompany), 403);

        try {
            $branchmanager->delete();
        } catch (\Throwable $e) {
            return back()->with('error', 'Cannot delete: record is in use.');
        }

        return back()->with('success', 'Branch/depot manager deleted.');
    }
}
