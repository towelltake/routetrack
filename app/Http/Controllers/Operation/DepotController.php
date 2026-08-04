<?php

namespace App\Http\Controllers\Operation;

use App\Http\Controllers\Controller;
use App\Models\CompanyMaster;
use App\Models\DepotMaster;
use App\Models\RegionMaster;
use App\Services\AccessScopeService;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;

class DepotController extends Controller
{
    public function index()
    {
        $scope = app(AccessScopeService::class);
        $user = request()->user();

        return Inertia::render('operation/Depot', [
            'depots'    => $scope->scopeQuery($user, DepotMaster::query(), 'depot', 'depotcode')->orderBy('depotname')->get([
                'depotcode', 'alternatedepotcode', 'depotname', 'arbdepotname',
                'cmpycode', 'regionmstcode', 'centralwh', 'activestatus',
                'phonenumber', 'faxnumber',
            ]),
            'companies' => $scope->scopeQuery($user, CompanyMaster::query(), 'company', 'cmpycode')->orderBy('name')->get(['cmpycode', 'name']),
            'regions'   => $scope->scopeQuery($user, RegionMaster::query(), 'region', 'regionmstcode')->orderBy('regionmstname')->get(['regionmstcode', 'regionmstname']),
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'alternatedepotcode' => 'nullable|string|max:50',
            'depotname'          => 'required|string|max:50',
            'arbdepotname'       => 'nullable|string|max:50',
            'cmpycode'           => 'nullable|integer',
            'regionmstcode'      => 'nullable|integer',
            'centralwh'          => 'nullable|integer',
            'activestatus'       => 'required|integer',
            'phonenumber'        => 'nullable|string|max:15',
            'faxnumber'          => 'nullable|string|max:15',
        ]);

        $data['centralwh'] = $data['centralwh'] ?? 0;
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

        $data['created']   = auth()->user()->name;
        $data['cdat']      = now();
        $data['modified']  = auth()->user()->name;
        $data['mdat']      = now();

        DepotMaster::create($data);
        return back();
    }

    public function update(Request $request, DepotMaster $depot)
    {
        abort_unless(app(AccessScopeService::class)->allows($request->user(), 'depot', $depot->depotcode), 403);

        $data = $request->validate([
            'alternatedepotcode' => 'nullable|string|max:50',
            'depotname'          => 'required|string|max:50',
            'arbdepotname'       => 'nullable|string|max:50',
            'cmpycode'           => 'nullable|integer',
            'regionmstcode'      => 'nullable|integer',
            'centralwh'          => 'nullable|integer',
            'activestatus'       => 'required|integer',
            'phonenumber'        => 'nullable|string|max:15',
            'faxnumber'          => 'nullable|string|max:15',
        ]);

        $data['centralwh'] = $data['centralwh'] ?? 0;
        $data['modified']  = auth()->user()->name;
        $data['mdat']      = now();

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

        $depot->update($data);
        return back();
    }

    public function destroy(DepotMaster $depot)
    {
        abort_unless(app(AccessScopeService::class)->allows(request()->user(), 'depot', $depot->depotcode), 403);

        try {
            $depot->delete();
        } catch (\Exception $e) {
            return back()->withErrors(['delete' => 'Cannot delete: record is in use.']);
        }
        return back();
    }
}
