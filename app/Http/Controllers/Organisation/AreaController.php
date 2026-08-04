<?php

namespace App\Http\Controllers\Organisation;

use App\Http\Controllers\Controller;
use App\Models\AreaManager;
use App\Models\AreaMaster;
use App\Models\DepotMaster;
use App\Services\AccessScopeService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class AreaController extends Controller
{
    public function index(): Response
    {
        $scope = app(AccessScopeService::class);
        $user = request()->user();
        $search = request('search');
        $perPage = (int) request('per_page', 10);
        $perPage = in_array($perPage, [10, 25, 50, 100], true) ? $perPage : 10;

        $areasQuery = AreaMaster::query()
            ->from('areamaster as area')
            ->leftJoin('areamanager as am', 'am.areamanagercode', '=', 'area.areamanagercode')
            ->leftJoin('depotmaster as depot', 'depot.depotcode', '=', 'area.depotcode')
            ->when($search, function ($query, $searchTerm) {
                $query->where(function ($inner) use ($searchTerm) {
                    $inner->where('area.areaname', 'like', '%' . $searchTerm . '%')
                        ->orWhere('area.alternateareacode', 'like', '%' . $searchTerm . '%')
                        ->orWhere('am.areamanagername', 'like', '%' . $searchTerm . '%')
                        ->orWhere('depot.depotname', 'like', '%' . $searchTerm . '%');
                });
            });

        $scope->scopeQuery($user, $areasQuery, 'area', 'area.areacode');

        $areas = $areasQuery
            ->orderBy('area.areacode')
            ->paginate($perPage, [
                'area.areacode',
                'area.alternateareacode',
                'area.areaname',
                'area.arbareaname',
                'area.depotcode',
                'area.areamanagercode',
                'area.areaprefix',
                'area.activestatus',
                'area.created',
                'area.cdat',
                'area.modified',
                'area.mdat',
                'am.areamanagername',
                'depot.depotname',
            ])
            ->withQueryString();

        return Inertia::render('organisation/area/Index', [
            'filters' => [
                'search' => $search,
                'per_page' => $perPage,
            ],
            'areas' => $areas,
            'depots' => $scope->scopeQuery($user, DepotMaster::query(), 'depot', 'depotcode')
                ->orderBy('depotname')
                ->get(['depotcode', 'depotname']),
            'areaManagers' => AreaManager::query()
                ->orderBy('areamanagername')
                ->get(['areamanagercode', 'areamanagername']),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validatedData($request);

        $data['areaprefix'] = 0;
        $data['created'] = auth()->user()->name;
        $data['cdat'] = now();
        $data['modified'] = auth()->user()->name;
        $data['mdat'] = now();

        AreaMaster::create($data);

        return back()->with('success', 'Area created.');
    }

    public function update(Request $request, AreaMaster $area): RedirectResponse
    {
        abort_unless(app(AccessScopeService::class)->allows($request->user(), 'area', $area->areacode), 403);

        $data = $this->validatedData($request, $area);

        $data['areaprefix'] = 0;
        $data['modified'] = auth()->user()->name;
        $data['mdat'] = now();

        $area->update($data);

        return back()->with('success', 'Area updated.');
    }

    public function destroy(AreaMaster $area): RedirectResponse
    {
        abort_unless(app(AccessScopeService::class)->allows(request()->user(), 'area', $area->areacode), 403);

        try {
            $area->delete();
        } catch (\Throwable $e) {
            return back()->with('error', 'Cannot delete: record is in use.');
        }

        return back()->with('success', 'Area deleted.');
    }

    private function validatedData(Request $request, ?AreaMaster $area = null): array
    {
        $data = $request->validate([
            'alternateareacode' => [
                'nullable',
                'string',
                'max:30',
                Rule::unique('areamaster', 'alternateareacode')
                    ->ignore($area?->areacode, 'areacode')
                    ->where(fn ($query) => $query->whereNotNull('alternateareacode')),
            ],
            'areaname' => [
                'required',
                'string',
                'max:30',
                Rule::unique('areamaster', 'areaname')
                    ->ignore($area?->areacode, 'areacode'),
            ],
            'arbareaname' => ['nullable', 'string', 'max:30'],
            'depotcode' => ['required', 'integer', Rule::exists('depotmaster', 'depotcode')],
            'areamanagercode' => ['required', 'integer', Rule::exists('areamanager', 'areamanagercode')],
            'activestatus' => ['required', 'integer', Rule::in([0, 1])],
        ]);

        if (! app(AccessScopeService::class)->allows($request->user(), 'depot', $data['depotcode'] ?? null)) {
            throw ValidationException::withMessages([
                'depotcode' => 'Selected depot is outside your access scope.',
            ]);
        }

        return $data;
    }
}
