<?php

namespace App\Http\Controllers\Basic;

use App\Http\Controllers\Controller;
use App\Models\CompanyMaster;
use App\Models\CountryMaster;
use App\Services\AccessScopeService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class CompanyController extends Controller
{
    public function index(): Response
    {
        $scope = app(AccessScopeService::class);
        $user = request()->user();
        $search = request('search');
        $perPage = (int) request('per_page', 10);
        $perPage = in_array($perPage, [10, 25, 50, 100], true) ? $perPage : 10;

        $companiesQuery = CompanyMaster::query()
            ->when($search, function ($query, $searchTerm) {
                $query->where(function ($inner) use ($searchTerm) {
                    $inner->where('name', 'like', '%' . $searchTerm . '%')
                        ->orWhere('alternatecmpycode', 'like', '%' . $searchTerm . '%')
                        ->orWhere('countryname', 'like', '%' . $searchTerm . '%')
                        ->orWhere('telephone', 'like', '%' . $searchTerm . '%');
                });
            });

        $scope->scopeQuery($user, $companiesQuery, 'company', 'cmpycode');

        $companies = $companiesQuery
            ->orderBy('cmpycode')
            ->paginate($perPage, [
                'cmpycode',
                'alternatecmpycode',
                'name',
                'arbcompanyname',
                'parentcompany',
                'contactname',
                'address',
                'telephone',
                'fax',
                'zipcode',
                'countrycode',
                'countryname',
                'taxregistrationnumber',
                'distributorcode',
                'activestatus',
            ])
            ->withQueryString();

        return Inertia::render('basic/company/Index', [
            'filters' => [
                'search' => $search,
                'per_page' => $perPage,
            ],
            'companies' => $companies,
            'companyOptions' => $scope->scopeQuery($user, CompanyMaster::query(), 'company', 'cmpycode')
                ->orderBy('name')
                ->get(['cmpycode', 'name']),
            'countries' => $scope->scopeQuery($user, CountryMaster::query(), 'country', 'countrycode')
                ->orderBy('countryname')
                ->get(['countrycode', 'countryname']),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validatedData($request);

        $data['created'] = auth()->user()->name;
        $data['cdat'] = now();
        $data['modified'] = auth()->user()->name;
        $data['mdat'] = now();

        CompanyMaster::create($data);

        return back()->with('success', 'Company created.');
    }

    public function update(Request $request, CompanyMaster $company): RedirectResponse
    {
        abort_unless(app(AccessScopeService::class)->allows($request->user(), 'company', $company->cmpycode), 403);

        $data = $this->validatedData($request, $company);

        $data['modified'] = auth()->user()->name;
        $data['mdat'] = now();

        $company->update($data);

        return back()->with('success', 'Company updated.');
    }

    public function destroy(CompanyMaster $company): RedirectResponse
    {
        abort_unless(app(AccessScopeService::class)->allows(request()->user(), 'company', $company->cmpycode), 403);

        try {
            $company->delete();
        } catch (\Throwable $e) {
            return back()->with('error', 'Cannot delete: record is in use.');
        }

        return back()->with('success', 'Company deleted.');
    }

    private function validatedData(Request $request, ?CompanyMaster $company = null): array
    {
        $data = $request->validate([
            'alternatecmpycode' => [
                'nullable',
                'string',
                'max:50',
                Rule::unique('company', 'alternatecmpycode')
                    ->ignore($company?->cmpycode, 'cmpycode')
                    ->where(fn ($query) => $query->whereNotNull('alternatecmpycode')),
            ],
            'name' => [
                'required',
                'string',
                'max:50',
                Rule::unique('company', 'name')->ignore($company?->cmpycode, 'cmpycode'),
            ],
            'arbcompanyname' => ['nullable', 'string', 'max:100'],
            'parentcompany' => ['nullable', 'integer', Rule::exists('company', 'cmpycode')],
            'contactname' => ['nullable', 'string', 'max:40'],
            'address' => ['required', 'string', 'max:255'],
            'telephone' => ['required', 'string', 'max:50'],
            'fax' => ['nullable', 'string', 'max:50'],
            'zipcode' => ['nullable', 'string', 'max:20'],
            'countrycode' => ['required', 'integer', Rule::exists('country', 'countrycode')],
            'taxregistrationnumber' => ['nullable', 'string', 'max:50'],
            'distributorcode' => ['nullable', 'string', 'max:10'],
            'activestatus' => ['required', 'integer', 'in:0,1'],
        ]);

        $scope = app(AccessScopeService::class);

        if (! $scope->allows($request->user(), 'company', $data['parentcompany'] ?? null)) {
            throw ValidationException::withMessages([
                'parentcompany' => 'Selected parent company is outside your access scope.',
            ]);
        }

        if (! $scope->allows($request->user(), 'country', $data['countrycode'] ?? null)) {
            throw ValidationException::withMessages([
                'countrycode' => 'Selected country is outside your access scope.',
            ]);
        }

        $country = CountryMaster::query()
            ->where('countrycode', $data['countrycode'])
            ->first(['countryname']);

        $data['countryname'] = $country?->countryname;

        return $data;
    }
}
