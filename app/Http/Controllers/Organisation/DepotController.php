<?php

namespace App\Http\Controllers\Organisation;

use App\Http\Controllers\Controller;
use App\Models\BranchManager;
use App\Models\CompanyMaster;
use App\Models\DepotMaster;
use App\Models\RegionMaster;
use App\Services\AccessScopeService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class DepotController extends Controller
{
    public function index(): Response
    {
        $scope = app(AccessScopeService::class);
        $user = request()->user();
        $search = request('search');
        $perPage = (int) request('per_page', 10);
        $perPage = in_array($perPage, [10, 25, 50, 100], true) ? $perPage : 10;

        $depotsQuery = DepotMaster::query()
            ->from('depotmaster as depot')
            ->leftJoin('branchmanager as bm', 'bm.branchmanagercode', '=', 'depot.branchmanagercode')
            ->leftJoin('company as cmp', 'cmp.cmpycode', '=', 'depot.cmpycode')
            ->leftJoin('regionmaster as reg', 'reg.regionmstcode', '=', 'depot.regionmstcode')
            ->leftJoin('customerpricing as pk', 'pk.pricingkey', '=', 'depot.pricingkey')
            ->when($search, function ($query, $searchTerm) {
                $query->where(function ($inner) use ($searchTerm) {
                    $inner->where('depot.depotname', 'like', '%' . $searchTerm . '%')
                        ->orWhere('depot.alternatedepotcode', 'like', '%' . $searchTerm . '%')
                        ->orWhere('bm.branchmanagername', 'like', '%' . $searchTerm . '%')
                        ->orWhere('cmp.name', 'like', '%' . $searchTerm . '%')
                        ->orWhere('reg.regionmstname', 'like', '%' . $searchTerm . '%');
                });
            });

        $scope->scopeQuery($user, $depotsQuery, 'depot', 'depot.depotcode');

        $depots = $depotsQuery
            ->orderBy('depot.depotcode')
            ->paginate($perPage, [
                'depot.depotcode',
                'depot.alternatedepotcode',
                'depot.depotname',
                'depot.arbdepotname',
                'depot.cmpycode',
                'depot.branchmanagercode',
                'depot.regionmstcode',
                'depot.pricingkey',
                'depot.depotprefix',
                'depot.centralwh',
                'depot.activestatus',
                'depot.created',
                'depot.cdat',
                'depot.modified',
                'depot.mdat',
                'bm.branchmanagername',
                'cmp.name as companyname',
                'reg.regionmstname',
                'pk.description as pricingkeyname',
            ])
            ->withQueryString();

        return Inertia::render('organisation/depot/Index', [
            'filters' => [
                'search' => $search,
                'per_page' => $perPage,
            ],
            'depots' => $depots,
            'branchManagers' => BranchManager::query()
                ->orderBy('branchmanagername')
                ->get(['branchmanagercode', 'branchmanagername']),
            'companies' => $scope->scopeQuery($user, CompanyMaster::query(), 'company', 'cmpycode')
                ->orderBy('name')
                ->get(['cmpycode', 'name']),
            'regions' => $scope->scopeQuery($user, RegionMaster::query(), 'region', 'regionmstcode')
                ->orderBy('regionmstname')
                ->get(['regionmstcode', 'regionmstname']),
            'pricingKeys' => DB::table('customerpricing')
                ->orderBy('description')
                ->get(['pricingkey', 'description']),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validatedData($request);

        $data['created'] = auth()->user()->name;
        $data['cdat'] = now();
        $data['modified'] = auth()->user()->name;
        $data['mdat'] = now();

        DepotMaster::create($data);

        return back()->with('success', 'Branch/Depot created.');
    }

    public function update(Request $request, DepotMaster $depot): RedirectResponse
    {
        abort_unless(app(AccessScopeService::class)->allows($request->user(), 'depot', $depot->depotcode), 403);

        $data = $this->validatedData($request, $depot);

        $data['modified'] = auth()->user()->name;
        $data['mdat'] = now();

        $depot->update($data);

        return back()->with('success', 'Branch/Depot updated.');
    }

    public function destroy(DepotMaster $depot): RedirectResponse
    {
        abort_unless(app(AccessScopeService::class)->allows(request()->user(), 'depot', $depot->depotcode), 403);

        try {
            $depot->delete();
        } catch (\Throwable $e) {
            return back()->with('error', 'Cannot delete: record is in use.');
        }

        return back()->with('success', 'Branch/Depot deleted.');
    }

    private function validatedData(Request $request, ?DepotMaster $depot = null): array
    {
        $data = $request->validate([
            'alternatedepotcode' => [
                'nullable',
                'string',
                'max:50',
                Rule::unique('depotmaster', 'alternatedepotcode')
                    ->ignore($depot?->depotcode, 'depotcode')
                    ->where(fn ($query) => $query->whereNotNull('alternatedepotcode')),
            ],
            'depotname' => [
                'required',
                'string',
                'max:50',
                Rule::unique('depotmaster', 'depotname')->ignore($depot?->depotcode, 'depotcode'),
            ],
            'arbdepotname' => ['nullable', 'string', 'max:50'],
            'cmpycode' => ['required', 'integer', Rule::exists('company', 'cmpycode')],
            'branchmanagercode' => ['required', 'integer', Rule::exists('branchmanager', 'branchmanagercode')],
            'regionmstcode' => ['required', 'integer', Rule::exists('regionmaster', 'regionmstcode')],
            'pricingkey' => ['nullable', 'integer', Rule::exists('customerpricing', 'pricingkey')],
            'depotprefix' => ['nullable', 'integer', 'min:0'],
            'centralwh' => ['required', 'integer', 'in:0,1'],
            'activestatus' => ['required', 'integer', 'in:0,1'],
        ]);

        $scope = app(AccessScopeService::class);

        if (! $scope->allows($request->user(), 'company', $data['cmpycode'] ?? null)) {
            throw ValidationException::withMessages([
                'cmpycode' => 'Selected company is outside your access scope.',
            ]);
        }

        if (! $scope->allows($request->user(), 'region', $data['regionmstcode'] ?? null)) {
            throw ValidationException::withMessages([
                'regionmstcode' => 'Selected region is outside your access scope.',
            ]);
        }

        return $data;
    }
}
