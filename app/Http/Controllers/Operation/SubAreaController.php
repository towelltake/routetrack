<?php

namespace App\Http\Controllers\Operation;

use App\Http\Controllers\Controller;
use App\Models\AreaMaster;
use App\Models\SubAreaMaster;
use App\Services\AccessScopeService;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;

class SubAreaController extends Controller
{
    public function index()
    {
        $scope = app(AccessScopeService::class);
        $user = request()->user();

        return Inertia::render('operation/SubArea', [
            'subareas' => $scope->scopeQuery($user, SubAreaMaster::query(), 'subarea', 'subareacode')->orderBy('subareaname')->get([
                'subareacode', 'alternatesubareacode', 'subareaname', 'arbsubareaname',
                'areacode', 'activestatus',
            ]),
            'areas' => $scope->scopeQuery($user, AreaMaster::query(), 'area', 'areacode')->orderBy('areaname')->get(['areacode', 'areaname']),
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'alternatesubareacode' => 'nullable|string|max:30',
            'subareaname'          => 'required|string|max:50',
            'arbsubareaname'       => 'nullable|string|max:50',
            'areacode'             => 'nullable|integer',
            'activestatus'         => 'required|integer',
        ]);

        if (! app(AccessScopeService::class)->allows($request->user(), 'area', $data['areacode'] ?? null)) {
            throw ValidationException::withMessages([
                'areacode' => 'Selected area is outside your access scope.',
            ]);
        }

        $data['created']  = auth()->user()->name;
        $data['cdat']     = now();
        $data['modified'] = auth()->user()->name;
        $data['mdat']     = now();

        SubAreaMaster::create($data);
        return back();
    }

    public function update(Request $request, SubAreaMaster $subarea)
    {
        abort_unless(app(AccessScopeService::class)->allows($request->user(), 'subarea', $subarea->subareacode), 403);

        $data = $request->validate([
            'alternatesubareacode' => 'nullable|string|max:30',
            'subareaname'          => 'required|string|max:50',
            'arbsubareaname'       => 'nullable|string|max:50',
            'areacode'             => 'nullable|integer',
            'activestatus'         => 'required|integer',
        ]);

        if (! app(AccessScopeService::class)->allows($request->user(), 'area', $data['areacode'] ?? null)) {
            throw ValidationException::withMessages([
                'areacode' => 'Selected area is outside your access scope.',
            ]);
        }

        $data['modified'] = auth()->user()->name;
        $data['mdat']     = now();

        $subarea->update($data);
        return back();
    }

    public function destroy(SubAreaMaster $subarea)
    {
        abort_unless(app(AccessScopeService::class)->allows(request()->user(), 'subarea', $subarea->subareacode), 403);

        try {
            $subarea->delete();
        } catch (\Exception $e) {
            return back()->withErrors(['delete' => 'Cannot delete: record is in use.']);
        }
        return back();
    }
}
