<?php

namespace App\Http\Controllers\Organisation;

use App\Http\Controllers\Controller;
use App\Models\CountryMaster;
use App\Models\RegionManager;
use App\Models\RegionMaster;
use App\Services\AccessScopeService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class RegionController extends Controller
{
    public function index(): Response
    {
        $scope = app(AccessScopeService::class);
        $user = request()->user();
        $search = request('search');
        $perPage = (int) request('per_page', 10);
        $perPage = in_array($perPage, [10, 25, 50, 100], true) ? $perPage : 10;

        $regionsQuery = RegionMaster::query()
            ->leftJoin('country', 'country.countrycode', '=', 'regionmaster.countrycode')
            ->leftJoin('regionmanager', 'regionmanager.regionmanagercode', '=', 'regionmaster.regionmanagercode')
            ->when($search, function ($query, $searchTerm) {
                $query->where(function ($inner) use ($searchTerm) {
                    $inner->where('regionmaster.regionmstname', 'like', '%' . $searchTerm . '%')
                        ->orWhere('regionmaster.alternatecode', 'like', '%' . $searchTerm . '%')
                        ->orWhere('country.countryname', 'like', '%' . $searchTerm . '%')
                        ->orWhere('regionmanager.regionmanagername', 'like', '%' . $searchTerm . '%');
                });
            });

        $scope->scopeQuery($user, $regionsQuery, 'region', 'regionmaster.regionmstcode');

        $regions = $regionsQuery
            ->orderBy('regionmaster.regionmstcode')
            ->paginate($perPage, [
                'regionmaster.regionmstcode',
                'regionmaster.alternatecode',
                'regionmaster.regionmstname',
                'regionmaster.arbregionmstname',
                'regionmaster.countrycode',
                'regionmaster.regionmanagercode',
                'regionmaster.created',
                'regionmaster.cdat',
                'regionmaster.modified',
                'regionmaster.mdat',
                'country.countryname',
                'regionmanager.regionmanagername',
            ])
            ->withQueryString();

        return Inertia::render('organisation/region/Index', [
            'filters' => [
                'search' => $search,
                'per_page' => $perPage,
            ],
            'regions' => $regions,
            'countries' => $scope->scopeQuery($user, CountryMaster::query(), 'country', 'countrycode')
                ->orderBy('countryname')
                ->get(['countrycode', 'countryname']),
            'regionManagers' => RegionManager::query()
                ->orderBy('regionmanagername')
                ->get(['regionmanagercode', 'regionmanagername']),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validatedData($request);

        $data['created'] = auth()->user()->name;
        $data['cdat'] = now();
        $data['modified'] = auth()->user()->name;
        $data['mdat'] = now();

        RegionMaster::create($data);

        return back()->with('success', 'Region created.');
    }

    public function update(Request $request, RegionMaster $region): RedirectResponse
    {
        abort_unless(app(AccessScopeService::class)->allows($request->user(), 'region', $region->regionmstcode), 403);

        $data = $this->validatedData($request, $region);

        $data['modified'] = auth()->user()->name;
        $data['mdat'] = now();

        $region->update($data);

        return back()->with('success', 'Region updated.');
    }

    public function destroy(RegionMaster $region): RedirectResponse
    {
        abort_unless(app(AccessScopeService::class)->allows(request()->user(), 'region', $region->regionmstcode), 403);

        try {
            $region->delete();
        } catch (\Throwable $e) {
            return back()->with('error', 'Cannot delete: record is in use.');
        }

        return back()->with('success', 'Region deleted.');
    }

    private function validatedData(Request $request, ?RegionMaster $region = null): array
    {
        $data = $request->validate([
            'alternatecode' => [
                'nullable',
                'string',
                'max:50',
                Rule::unique('regionmaster', 'alternatecode')
                    ->ignore($region?->regionmstcode, 'regionmstcode')
                    ->where(fn ($query) => $query->whereNotNull('alternatecode')),
            ],
            'regionmstname' => [
                'required',
                'string',
                'max:50',
                Rule::unique('regionmaster', 'regionmstname')
                    ->ignore($region?->regionmstcode, 'regionmstcode'),
            ],
            'arbregionmstname' => ['nullable', 'string', 'max:50'],
            'countrycode' => ['required', 'integer', Rule::exists('country', 'countrycode')],
            'regionmanagercode' => ['nullable', 'integer', Rule::exists('regionmanager', 'regionmanagercode')],
        ]);

        if (! app(AccessScopeService::class)->allows($request->user(), 'country', $data['countrycode'] ?? null)) {
            throw ValidationException::withMessages([
                'countrycode' => 'Selected country is outside your access scope.',
            ]);
        }

        return $data;
    }
}
