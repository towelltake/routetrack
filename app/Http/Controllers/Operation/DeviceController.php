<?php

namespace App\Http\Controllers\Operation;

use App\Http\Controllers\Controller;
use App\Models\CompanyMaster;
use App\Models\DeviceMaster;
use App\Services\AccessScopeService;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;

class DeviceController extends Controller
{
    public function index()
    {
        $scope = app(AccessScopeService::class);
        $user = request()->user();

        return Inertia::render('operation/Device', [
            'devices'   => $scope->scopeQuery($user, DeviceMaster::query(), 'company', 'companyid')->orderBy('deviceid')->get(),
            'companies' => $scope->scopeQuery($user, CompanyMaster::query(), 'company', 'cmpycode')
                ->orderBy('name')
                ->get(['cmpycode as id', 'name as companyname']),
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'deviceid'   => 'required|string|max:255|unique:devicemaster,deviceid',
            'remarks'    => 'nullable|string|max:255',
            'companyid'  => 'nullable|integer',
            'statusflag' => 'required|integer',
        ]);

        if (! app(AccessScopeService::class)->allows($request->user(), 'company', $data['companyid'] ?? null)) {
            throw ValidationException::withMessages([
                'companyid' => 'Selected company is outside your access scope.',
            ]);
        }

        DeviceMaster::create($data);
        return back();
    }

    public function update(Request $request, DeviceMaster $device)
    {
        abort_unless(app(AccessScopeService::class)->allows($request->user(), 'company', $device->companyid), 403);

        $data = $request->validate([
            'deviceid'   => "required|string|max:255|unique:devicemaster,deviceid,{$device->id}",
            'remarks'    => 'nullable|string|max:255',
            'companyid'  => 'nullable|integer',
            'statusflag' => 'required|integer',
        ]);

        if (! app(AccessScopeService::class)->allows($request->user(), 'company', $data['companyid'] ?? null)) {
            throw ValidationException::withMessages([
                'companyid' => 'Selected company is outside your access scope.',
            ]);
        }

        $device->update($data);
        return back();
    }

    public function destroy(DeviceMaster $device)
    {
        abort_unless(app(AccessScopeService::class)->allows(request()->user(), 'company', $device->companyid), 403);

        $device->delete();
        return back();
    }
}
