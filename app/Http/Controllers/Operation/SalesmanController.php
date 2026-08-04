<?php

namespace App\Http\Controllers\Operation;

use App\Http\Controllers\Controller;
use App\Models\CompanyMaster;
use App\Models\SalesmanMaster;
use App\Services\AccessScopeService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;

class SalesmanController extends Controller
{
    public function index()
    {
        $scope = app(AccessScopeService::class);
        $user = request()->user();

        return Inertia::render('operation/Salesman', [
            'salesmans' => $scope->scopeQuery($user, SalesmanMaster::query(), 'company', 'companyid')
                ->orderBy('salesmanname')
                ->get(),
            'companies' => $scope->scopeQuery($user, CompanyMaster::query(), 'company', 'cmpycode')
                ->orderBy('name')
                ->get(['cmpycode as id', 'name as companyname']),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validatedData($request);

        SalesmanMaster::create($data);
        return back();
    }

    public function update(Request $request, SalesmanMaster $salesman): RedirectResponse
    {
        abort_unless(app(AccessScopeService::class)->allows($request->user(), 'company', $salesman->companyid), 403);

        $data = $this->validatedData($request);

        $salesman->update($data);
        return back();
    }

    public function destroy(SalesmanMaster $salesman): RedirectResponse
    {
        abort_unless(app(AccessScopeService::class)->allows(request()->user(), 'company', $salesman->companyid), 403);

        $salesman->delete();
        return back();
    }

    private function validatedData(Request $request): array
    {
        $data = $request->validate([
            'code' => ['nullable', 'string', 'max:50'],
            'salesmanname' => ['required', 'string', 'max:50'],
            'arbsalesmanname' => ['nullable', 'string', 'max:50'],
            'contactnumber' => ['nullable', 'string', 'max:50'],
            'companyid' => ['nullable', 'integer', 'exists:company,cmpycode'],
            'username' => ['nullable', 'string', 'max:255'],
            'userpassword' => ['nullable', 'string', 'max:255'],
            'statusflag' => ['required', 'integer', 'in:0,1'],
        ]);

        if (! app(AccessScopeService::class)->allows($request->user(), 'company', $data['companyid'] ?? null)) {
            throw ValidationException::withMessages([
                'companyid' => 'Selected company is outside your access scope.',
            ]);
        }

        return $data;
    }
}
