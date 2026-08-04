<?php

namespace App\Http\Controllers\Operation;

use App\Http\Controllers\Controller;
use App\Models\AreaMaster;
use App\Models\DepotMaster;
use App\Services\AccessScopeService;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;

class AreaController extends Controller
{
    public function index()
    {
        $scope = app(AccessScopeService::class);
        $user = request()->user();

        return Inertia::render('operation/Area', [
            'areas'  => $scope->scopeQuery($user, AreaMaster::query(), 'area', 'areacode')->orderBy('areaname')->get([
                'areacode', 'alternateareacode', 'areaname', 'arbareaname',
                'depotcode', 'activestatus',
            ]),
            'depots' => $scope->scopeQuery($user, DepotMaster::query(), 'depot', 'depotcode')->orderBy('depotname')->get(['depotcode', 'depotname']),
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'alternateareacode' => 'nullable|string|max:30',
            'areaname'          => 'required|string|max:30',
            'arbareaname'       => 'nullable|string|max:30',
            'depotcode'         => 'nullable|integer',
            'activestatus'      => 'required|integer',
        ]);

        if (! app(AccessScopeService::class)->allows($request->user(), 'depot', $data['depotcode'] ?? null)) {
            throw ValidationException::withMessages([
                'depotcode' => 'Selected depot is outside your access scope.',
            ]);
        }

        $data['created']  = auth()->user()->name;
        $data['cdat']     = now();
        $data['modified'] = auth()->user()->name;
        $data['mdat']     = now();

        AreaMaster::create($data);
        return back();
    }

    public function update(Request $request, AreaMaster $area)
    {
        abort_unless(app(AccessScopeService::class)->allows($request->user(), 'area', $area->areacode), 403);

        $data = $request->validate([
            'alternateareacode' => 'nullable|string|max:30',
            'areaname'          => 'required|string|max:30',
            'arbareaname'       => 'nullable|string|max:30',
            'depotcode'         => 'nullable|integer',
            'activestatus'      => 'required|integer',
        ]);

        $data['modified'] = auth()->user()->name;
        $data['mdat']     = now();

        if (! app(AccessScopeService::class)->allows($request->user(), 'depot', $data['depotcode'] ?? null)) {
            throw ValidationException::withMessages([
                'depotcode' => 'Selected depot is outside your access scope.',
            ]);
        }

        $area->update($data);
        return back();
    }

    public function destroy(AreaMaster $area)
    {
        abort_unless(app(AccessScopeService::class)->allows(request()->user(), 'area', $area->areacode), 403);

        try {
            $area->delete();
        } catch (\Exception $e) {
            return back()->withErrors(['delete' => 'Cannot delete: record is in use.']);
        }
        return back();
    }
}
