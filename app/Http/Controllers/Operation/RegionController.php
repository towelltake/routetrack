<?php

namespace App\Http\Controllers\Operation;

use App\Http\Controllers\Controller;
use App\Models\RegionMaster;
use App\Services\AccessScopeService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;

class RegionController extends Controller
{
    public function index()
    {
        $scope = app(AccessScopeService::class);
        $user = request()->user();

        return Inertia::render('operation/Region', [
            'regions'   => $scope->scopeQuery($user, RegionMaster::query(), 'region', 'regionmstcode')->orderBy('regionmstname')->get([
                'regionmstcode', 'alternatecode', 'regionmstname', 'arbregionmstname', 'countrycode',
            ]),
            'countries' => $scope->scopeQuery($user, DB::table('country'), 'country', 'countrycode')->orderBy('countryname')
                ->get(['countrycode', 'countryname']),
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'alternatecode'    => 'nullable|string|max:50',
            'regionmstname'    => 'required|string|max:50',
            'arbregionmstname' => 'nullable|string|max:50',
            'countrycode'      => 'nullable|integer',
        ]);

        if (! app(AccessScopeService::class)->allows($request->user(), 'country', $data['countrycode'] ?? null)) {
            throw ValidationException::withMessages([
                'countrycode' => 'Selected country is outside your access scope.',
            ]);
        }

        $data['created']  = auth()->user()->name;
        $data['cdat']     = now();
        $data['modified'] = auth()->user()->name;
        $data['mdat']     = now();

        RegionMaster::create($data);
        return back();
    }

    public function update(Request $request, RegionMaster $region)
    {
        abort_unless(app(AccessScopeService::class)->allows($request->user(), 'region', $region->regionmstcode), 403);

        $data = $request->validate([
            'alternatecode'    => 'nullable|string|max:50',
            'regionmstname'    => 'required|string|max:50',
            'arbregionmstname' => 'nullable|string|max:50',
            'countrycode'      => 'nullable|integer',
        ]);

        $data['modified'] = auth()->user()->name;
        $data['mdat']     = now();

        if (! app(AccessScopeService::class)->allows($request->user(), 'country', $data['countrycode'] ?? null)) {
            throw ValidationException::withMessages([
                'countrycode' => 'Selected country is outside your access scope.',
            ]);
        }

        $region->update($data);
        return back();
    }

    public function destroy(RegionMaster $region)
    {
        abort_unless(app(AccessScopeService::class)->allows(request()->user(), 'region', $region->regionmstcode), 403);

        try {
            $region->delete();
        } catch (\Exception $e) {
            return back()->withErrors(['delete' => 'Cannot delete: record is in use.']);
        }
        return back();
    }
}
