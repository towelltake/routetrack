<?php

namespace App\Http\Controllers\Operation;

use App\Http\Controllers\Controller;
use App\Models\CompanyMaster;
use App\Models\VehicleMaster;
use App\Services\AccessScopeService;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;

class VehicleController extends Controller
{
    public function index()
    {
        $scope = app(AccessScopeService::class);
        $user = request()->user();

        return Inertia::render('operation/Vehicle', [
            'vehicles'  => $scope->scopeQuery($user, VehicleMaster::query(), 'company', 'companyid')->orderBy('vandescription')->get(),
            'companies' => $scope->scopeQuery($user, CompanyMaster::query(), 'company', 'cmpycode')
                ->orderBy('name')
                ->get(['cmpycode as id', 'name as companyname']),
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'code'                => 'nullable|string|max:50',
            'vandescription'      => 'required|string|max:50',
            'arbvandescription'   => 'nullable|string|max:50',
            'vehicleregistration' => 'nullable|string|max:50',
            'vanmodel'            => 'nullable|string|max:50',
            'vantype'             => 'nullable|string|max:50',
            'companyid'           => 'nullable|integer',
            'statusflag'          => 'required|integer',
        ]);

        if (! app(AccessScopeService::class)->allows($request->user(), 'company', $data['companyid'] ?? null)) {
            throw ValidationException::withMessages([
                'companyid' => 'Selected company is outside your access scope.',
            ]);
        }

        VehicleMaster::create($data);
        return back();
    }

    public function update(Request $request, VehicleMaster $vehicle)
    {
        abort_unless(app(AccessScopeService::class)->allows($request->user(), 'company', $vehicle->companyid), 403);

        $data = $request->validate([
            'code'                => 'nullable|string|max:50',
            'vandescription'      => 'required|string|max:50',
            'arbvandescription'   => 'nullable|string|max:50',
            'vehicleregistration' => 'nullable|string|max:50',
            'vanmodel'            => 'nullable|string|max:50',
            'vantype'             => 'nullable|string|max:50',
            'companyid'           => 'nullable|integer',
            'statusflag'          => 'required|integer',
        ]);

        if (! app(AccessScopeService::class)->allows($request->user(), 'company', $data['companyid'] ?? null)) {
            throw ValidationException::withMessages([
                'companyid' => 'Selected company is outside your access scope.',
            ]);
        }

        $vehicle->update($data);
        return back();
    }

    public function destroy(VehicleMaster $vehicle)
    {
        abort_unless(app(AccessScopeService::class)->allows(request()->user(), 'company', $vehicle->companyid), 403);

        $vehicle->delete();
        return back();
    }
}
