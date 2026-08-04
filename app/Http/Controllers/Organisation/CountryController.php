<?php

namespace App\Http\Controllers\Organisation;

use App\Http\Controllers\Controller;
use App\Models\CompanyMaster;
use App\Models\CountryMaster;
use App\Models\CurrencyMaster;
use App\Models\NationalSalesManager;
use App\Services\AccessScopeService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class CountryController extends Controller
{
    public function index(): Response
    {
        $scope = app(AccessScopeService::class);
        $user = request()->user();
        $search = request('search');
        $perPage = (int) request('per_page', 10);
        $perPage = in_array($perPage, [10, 25, 50, 100], true) ? $perPage : 10;

        $countriesQuery = CountryMaster::query()
            ->leftJoin('currencymaster', 'currencymaster.currencycode', '=', 'country.currencycode')
            ->leftJoin('company', 'company.cmpycode', '=', 'country.cmpycode')
            ->leftJoin(
                'nationalsalesmanager',
                'nationalsalesmanager.nationalsalesmanagercode',
                '=',
                'country.nationalsalesmanagercode'
            )
            ->when($search, function ($query, $searchTerm) {
                $query->where(function ($inner) use ($searchTerm) {
                    $inner->where('country.countryname', 'like', '%' . $searchTerm . '%')
                        ->orWhere('country.alternatecode', 'like', '%' . $searchTerm . '%')
                        ->orWhere('currencymaster.currencyname', 'like', '%' . $searchTerm . '%')
                        ->orWhere('company.name', 'like', '%' . $searchTerm . '%');
                });
            });

        $scope->scopeQuery($user, $countriesQuery, 'country', 'country.countrycode');

        $countries = $countriesQuery
            ->orderBy('country.countrycode')
            ->paginate($perPage, [
                'country.countrycode',
                'country.alternatecode',
                'country.countryname',
                'country.arbcountryname',
                'country.currencycode',
                'country.cmpycode',
                'country.pricechangevariance',
                'country.nationalsalesmanagercode',
                'country.created',
                'country.cdat',
                'country.modified',
                'country.mdat',
                'currencymaster.currencyname',
                'company.name as companyname',
                'nationalsalesmanager.nationalsalesmanagername',
            ])
            ->withQueryString();

        return Inertia::render('organisation/country/Index', [
            'filters' => [
                'search' => $search,
                'per_page' => $perPage,
            ],
            'countries' => $countries,
            'currencies' => CurrencyMaster::query()
                ->orderBy('currencyname')
                ->get(['currencycode', 'currencyname']),
            'companies' => $scope->scopeQuery($user, CompanyMaster::query(), 'company', 'cmpycode')
                ->orderBy('name')
                ->get(['cmpycode', 'name']),
            'nationalSalesManagers' => NationalSalesManager::query()
                ->orderBy('nationalsalesmanagername')
                ->get(['nationalsalesmanagercode', 'nationalsalesmanagername']),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validatedData($request);

        $data['created'] = auth()->user()->name;
        $data['cdat'] = now();
        $data['modified'] = auth()->user()->name;
        $data['mdat'] = now();

        CountryMaster::create($data);

        return back()->with('success', 'Country created.');
    }

    public function update(Request $request, CountryMaster $country): RedirectResponse
    {
        abort_unless(app(AccessScopeService::class)->allows($request->user(), 'country', $country->countrycode), 403);

        $data = $this->validatedData($request, $country);

        $data['modified'] = auth()->user()->name;
        $data['mdat'] = now();

        $country->update($data);

        return back()->with('success', 'Country updated.');
    }

    public function destroy(CountryMaster $country): RedirectResponse
    {
        abort_unless(app(AccessScopeService::class)->allows(request()->user(), 'country', $country->countrycode), 403);

        try {
            $country->delete();
        } catch (\Throwable $e) {
            return back()->with('error', 'Cannot delete: record is in use.');
        }

        return back()->with('success', 'Country deleted.');
    }

    private function validatedData(Request $request, ?CountryMaster $country = null): array
    {
        $data = $request->validate([
            'alternatecode' => [
                'nullable',
                'string',
                'max:50',
                Rule::unique('country', 'alternatecode')
                    ->ignore($country?->countrycode, 'countrycode')
                    ->where(fn ($query) => $query->whereNotNull('alternatecode')),
            ],
            'countryname' => [
                'required',
                'string',
                'max:50',
                Rule::unique('country', 'countryname')->ignore($country?->countrycode, 'countrycode'),
            ],
            'arbcountryname' => ['nullable', 'string', 'max:50'],
            'currencycode' => ['required', 'integer', Rule::exists('currencymaster', 'currencycode')],
            'cmpycode' => ['required', 'integer', Rule::exists('company', 'cmpycode')],
            'nationalsalesmanagercode' => ['nullable', 'integer', Rule::exists('nationalsalesmanager', 'nationalsalesmanagercode')],
            'pricechangevariance' => ['nullable', 'numeric', 'min:0'],
        ]);

        $scope = app(AccessScopeService::class);

        if (! $scope->allows($request->user(), 'company', $data['cmpycode'] ?? null)) {
            throw ValidationException::withMessages([
                'cmpycode' => 'Selected company is outside your access scope.',
            ]);
        }

        return $data;
    }
}
