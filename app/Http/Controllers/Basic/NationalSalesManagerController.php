<?php

namespace App\Http\Controllers\Basic;

use App\Http\Controllers\Controller;
use App\Models\CompanyMaster;
use App\Models\NationalSalesManager;
use App\Services\AccessScopeService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class NationalSalesManagerController extends Controller
{
    public function index(): Response
    {
        $scope = app(AccessScopeService::class);
        $user = request()->user();
        $search = request('search');
        $perPage = (int) request('per_page', 10);
        $perPage = in_array($perPage, [10, 25, 50, 100], true) ? $perPage : 10;

        $managers = NationalSalesManager::query()
            ->leftJoin('company', 'company.cmpycode', '=', 'nationalsalesmanager.parentcompany')
            ->when($search, function ($query, $searchTerm) {
                $query->where(function ($inner) use ($searchTerm) {
                    $inner->where('nationalsalesmanager.nationalsalesmanagername', 'like', '%' . $searchTerm . '%')
                        ->orWhere('nationalsalesmanager.arbnationalsalesmanagername', 'like', '%' . $searchTerm . '%')
                        ->orWhere('nationalsalesmanager.alternatecode', 'like', '%' . $searchTerm . '%')
                        ->orWhere('company.name', 'like', '%' . $searchTerm . '%');
                });
            })
            ->tap(fn ($query) => $scope->scopeQuery($user, $query, 'company', 'nationalsalesmanager.parentcompany'))
            ->orderBy('nationalsalesmanager.nationalsalesmanagercode')
            ->paginate($perPage, [
                'nationalsalesmanager.nationalsalesmanagercode',
                'nationalsalesmanager.alternatecode',
                'nationalsalesmanager.parentcompany',
                'nationalsalesmanager.nationalsalesmanagername',
                'nationalsalesmanager.arbnationalsalesmanagername',
                'nationalsalesmanager.activestatus',
                'nationalsalesmanager.cdat',
                'company.name as parentcompanyname',
            ])
            ->withQueryString();

        return Inertia::render('basic/nationalsalesmanager/Index', [
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

    private function validated(Request $request, ?NationalSalesManager $manager = null): array
    {
        $data = $request->validate([
            'alternatecode' => [
                'nullable',
                'string',
                'max:50',
                Rule::unique('nationalsalesmanager', 'alternatecode')
                    ->ignore($manager?->nationalsalesmanagercode, 'nationalsalesmanagercode'),
            ],
            'parentcompany' => ['nullable', 'integer', Rule::exists('company', 'cmpycode')],
            'nationalsalesmanagername' => ['required', 'string', 'max:50'],
            'arbnationalsalesmanagername' => ['required', 'string', 'max:50'],
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

        NationalSalesManager::create($data);

        return back()->with('success', 'National sales manager created.');
    }

    public function update(Request $request, NationalSalesManager $nationalsalesmanager): RedirectResponse
    {
        abort_unless(app(AccessScopeService::class)->allows($request->user(), 'company', $nationalsalesmanager->parentcompany), 403);

        $data = $this->validated($request, $nationalsalesmanager);
        $data['modified'] = auth()->user()?->name;
        $data['mdat'] = now();

        $nationalsalesmanager->update($data);

        return back()->with('success', 'National sales manager updated.');
    }

    public function destroy(NationalSalesManager $nationalsalesmanager): RedirectResponse
    {
        abort_unless(app(AccessScopeService::class)->allows(request()->user(), 'company', $nationalsalesmanager->parentcompany), 403);

        try {
            $nationalsalesmanager->delete();
        } catch (\Throwable $e) {
            return back()->with('error', 'Cannot delete: record is in use.');
        }

        return back()->with('success', 'National sales manager deleted.');
    }
}
