<?php

namespace App\Http\Controllers\Basic;

use App\Http\Controllers\Controller;
use App\Models\AreaManager;
use App\Models\CompanyMaster;
use App\Services\AccessScopeService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class AreaManagerController extends Controller
{
    public function index(): Response
    {
        $scope = app(AccessScopeService::class);
        $user = request()->user();
        $search = request('search');
        $perPage = (int) request('per_page', 10);
        $perPage = in_array($perPage, [10, 25, 50, 100], true) ? $perPage : 10;

        $managersQuery = AreaManager::query()
            ->leftJoin('company', 'company.cmpycode', '=', 'areamanager.parentcompany')
            ->when($search, function ($query, $searchTerm) {
                $query->where(function ($inner) use ($searchTerm) {
                    $inner->where('areamanager.areamanagername', 'like', '%' . $searchTerm . '%')
                        ->orWhere('areamanager.arbareamanagername', 'like', '%' . $searchTerm . '%')
                        ->orWhere('areamanager.alternateareamanagercode', 'like', '%' . $searchTerm . '%')
                        ->orWhere('company.name', 'like', '%' . $searchTerm . '%');
                });
            });

        $scope->scopeQuery($user, $managersQuery, 'company', 'areamanager.parentcompany');

        $managers = $managersQuery
            ->orderBy('areamanager.areamanagercode')
            ->paginate($perPage, [
                'areamanager.areamanagercode',
                'areamanager.parentcompany',
                'areamanager.areamanagername',
                'areamanager.arbareamanagername',
                'areamanager.alternateareamanagercode',
                'areamanager.activestatus',
                'areamanager.cdat',
                'company.name as parentcompanyname',
            ])
            ->withQueryString();

        return Inertia::render('basic/areamanager/Index', [
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

    private function validated(Request $request, ?AreaManager $manager = null): array
    {
        $data = $request->validate([
            'alternateareamanagercode' => [
                'nullable',
                'string',
                'max:50',
                Rule::unique('areamanager', 'alternateareamanagercode')
                    ->ignore($manager?->areamanagercode, 'areamanagercode'),
            ],
            'parentcompany' => ['nullable', 'integer', Rule::exists('company', 'cmpycode')],
            'areamanagername' => ['required', 'string', 'max:50'],
            'arbareamanagername' => ['required', 'string', 'max:50'],
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

        AreaManager::create($data);

        return back()->with('success', 'Area manager created.');
    }

    public function update(Request $request, AreaManager $areamanager): RedirectResponse
    {
        abort_unless(app(AccessScopeService::class)->allows($request->user(), 'company', $areamanager->parentcompany), 403);

        $data = $this->validated($request, $areamanager);
        $data['modified'] = auth()->user()?->name;
        $data['mdat'] = now();

        $areamanager->update($data);

        return back()->with('success', 'Area manager updated.');
    }

    public function destroy(AreaManager $areamanager): RedirectResponse
    {
        abort_unless(app(AccessScopeService::class)->allows(request()->user(), 'company', $areamanager->parentcompany), 403);

        try {
            $areamanager->delete();
        } catch (\Throwable $e) {
            return back()->with('error', 'Cannot delete: record is in use.');
        }

        return back()->with('success', 'Area manager deleted.');
    }
}
