<?php

namespace App\Http\Controllers\Operation;

use App\Http\Controllers\Controller;
use App\Models\CompanyMaster;
use App\Services\AccessScopeService;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;

class CompanyController extends Controller
{
    public function index()
    {
        $scope = app(AccessScopeService::class);
        $user = request()->user();

        return Inertia::render('operation/Company', [
            'companies' => $scope->scopeQuery($user, CompanyMaster::query(), 'company', 'cmpycode')->orderBy('name')->get([
                'cmpycode', 'alternatecmpycode', 'name', 'arbcompanyname',
                'telephone', 'address', 'activestatus', 'parentcompany',
            ]),
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'alternatecmpycode'    => 'nullable|string|max:50',
            'name'                 => 'required|string|max:50',
            'arbcompanyname'       => 'nullable|string|max:100',
            'parentcompany'        => 'nullable|integer',
            'contactname'          => 'nullable|string|max:40',
            'address'              => 'nullable|string|max:255',
            'telephone'            => 'nullable|string|max:50',
            'fax'                  => 'nullable|string|max:50',
            'zipcode'              => 'nullable|string|max:20',
            'taxregistrationnumber'=> 'nullable|string|max:50',
            'distributorcode'      => 'nullable|string|max:10',
            'activestatus'         => 'required|integer',
        ]);

        if (! app(AccessScopeService::class)->allows($request->user(), 'company', $data['parentcompany'] ?? null)) {
            throw ValidationException::withMessages([
                'parentcompany' => 'Selected parent company is outside your access scope.',
            ]);
        }

        $data['created']  = auth()->user()->name;
        $data['cdat']     = now();
        $data['modified'] = auth()->user()->name;
        $data['mdat']     = now();

        CompanyMaster::create($data);
        return back();
    }

    public function update(Request $request, CompanyMaster $company)
    {
        abort_unless(app(AccessScopeService::class)->allows($request->user(), 'company', $company->cmpycode), 403);

        $data = $request->validate([
            'alternatecmpycode'    => 'nullable|string|max:50',
            'name'                 => 'required|string|max:50',
            'arbcompanyname'       => 'nullable|string|max:100',
            'parentcompany'        => 'nullable|integer',
            'contactname'          => 'nullable|string|max:40',
            'address'              => 'nullable|string|max:255',
            'telephone'            => 'nullable|string|max:50',
            'fax'                  => 'nullable|string|max:50',
            'zipcode'              => 'nullable|string|max:20',
            'taxregistrationnumber'=> 'nullable|string|max:50',
            'distributorcode'      => 'nullable|string|max:10',
            'activestatus'         => 'required|integer',
        ]);

        $data['modified'] = auth()->user()->name;
        $data['mdat']     = now();

        if (! app(AccessScopeService::class)->allows($request->user(), 'company', $data['parentcompany'] ?? null)) {
            throw ValidationException::withMessages([
                'parentcompany' => 'Selected parent company is outside your access scope.',
            ]);
        }

        $company->update($data);
        return back();
    }

    public function destroy(CompanyMaster $company)
    {
        abort_unless(app(AccessScopeService::class)->allows(request()->user(), 'company', $company->cmpycode), 403);

        try {
            $company->delete();
        } catch (\Exception $e) {
            return back()->withErrors(['delete' => 'Cannot delete: record is in use.']);
        }
        return back();
    }
}
