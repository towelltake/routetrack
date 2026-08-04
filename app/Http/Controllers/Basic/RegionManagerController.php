<?php

namespace App\Http\Controllers\Basic;

use App\Http\Controllers\Controller;
use App\Models\CompanyMaster;
use App\Models\RegionManager;
use App\Services\AccessScopeService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class RegionManagerController extends Controller
{
    public function index(): Response
    {
        $scope = app(AccessScopeService::class);
        $user = request()->user();
        $search = request('search');
        $perPage = (int) request('per_page', 10);
        $perPage = in_array($perPage, [10, 25, 50, 100], true) ? $perPage : 10;

        $managers = RegionManager::query()
            ->leftJoin('company', 'company.cmpycode', '=', 'regionmanager.parentcompany')
            ->when($search, function ($query, $searchTerm) {
                $query->where(function ($inner) use ($searchTerm) {
                    $inner->where('regionmanager.regionmanagername', 'like', '%' . $searchTerm . '%')
                        ->orWhere('regionmanager.arbregionmanagername', 'like', '%' . $searchTerm . '%')
                        ->orWhere('regionmanager.alternatecode', 'like', '%' . $searchTerm . '%')
                        ->orWhere('company.name', 'like', '%' . $searchTerm . '%');
                });
            })
            ->tap(fn ($query) => $scope->scopeQuery($user, $query, 'company', 'regionmanager.parentcompany'))
            ->orderBy('regionmanager.regionmanagercode')
            ->paginate($perPage, [
                'regionmanager.regionmanagercode',
                'regionmanager.alternatecode',
                'regionmanager.parentcompany',
                'regionmanager.regionmanagername',
                'regionmanager.arbregionmanagername',
                'regionmanager.activestatus',
                'regionmanager.cdat',
                'company.name as parentcompanyname',
            ])
            ->withQueryString();

        return Inertia::render('basic/regionmanager/Index', [
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

    private function validated(Request $request, ?RegionManager $manager = null): array
    {
        $data = $request->validate([
            'alternatecode' => [
                'nullable',
                'string',
                'max:50',
                Rule::unique('regionmanager', 'alternatecode')
                    ->ignore($manager?->regionmanagercode, 'regionmanagercode'),
            ],
            'parentcompany' => ['nullable', 'integer', Rule::exists('company', 'cmpycode')],
            'regionmanagername' => ['required', 'string', 'max:50'],
            'arbregionmanagername' => ['required', 'string', 'max:50'],
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

        RegionManager::create($data);

        return back()->with('success', 'Regional manager created.');
    }

    public function update(Request $request, RegionManager $regionmanager): RedirectResponse
    {
        abort_unless(app(AccessScopeService::class)->allows($request->user(), 'company', $regionmanager->parentcompany), 403);

        $data = $this->validated($request, $regionmanager);
        $data['modified'] = auth()->user()?->name;
        $data['mdat'] = now();

        $regionmanager->update($data);

        return back()->with('success', 'Regional manager updated.');
    }

    public function destroy(RegionManager $regionmanager): RedirectResponse
    {
        abort_unless(app(AccessScopeService::class)->allows(request()->user(), 'company', $regionmanager->parentcompany), 403);

        try {
            $regionmanager->delete();
        } catch (\Throwable $e) {
            return back()->with('error', 'Cannot delete: record is in use.');
        }

        return back()->with('success', 'Regional manager deleted.');
    }
}
