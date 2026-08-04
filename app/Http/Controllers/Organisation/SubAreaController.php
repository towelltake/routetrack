<?php

namespace App\Http\Controllers\Organisation;

use App\Http\Controllers\Controller;
use App\Models\AreaMaster;
use App\Models\SubAreaMaster;
use App\Models\Supervisor;
use App\Services\AccessScopeService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class SubAreaController extends Controller
{
    public function index(): Response
    {
        $scope = app(AccessScopeService::class);
        $user = request()->user();
        $search = request('search');
        $perPage = (int) request('per_page', 10);
        $perPage = in_array($perPage, [10, 25, 50, 100], true) ? $perPage : 10;

        $subAreasQuery = SubAreaMaster::query()
            ->from('subareamaster as sub')
            ->leftJoin('areamaster as area', 'area.areacode', '=', 'sub.areacode')
            ->leftJoin('supervisor as sup', 'sup.supervisorcode', '=', 'sub.supervisorcode')
            ->when($search, function ($query, $searchTerm) {
                $query->where(function ($inner) use ($searchTerm) {
                    $inner->where('sub.subareaname', 'like', '%' . $searchTerm . '%')
                        ->orWhere('sub.alternatesubareacode', 'like', '%' . $searchTerm . '%')
                        ->orWhere('area.areaname', 'like', '%' . $searchTerm . '%')
                        ->orWhere('sup.supervisorname', 'like', '%' . $searchTerm . '%');
                });
            });

        $scope->scopeQuery($user, $subAreasQuery, 'subarea', 'sub.subareacode');

        $subAreas = $subAreasQuery
            ->orderBy('sub.subareacode')
            ->paginate($perPage, [
                'sub.subareacode',
                'sub.alternatesubareacode',
                'sub.subareaname',
                'sub.arbsubareaname',
                'sub.areacode',
                'sub.supervisorcode',
                'sub.activestatus',
                'sub.created',
                'sub.cdat',
                'sub.modified',
                'sub.mdat',
                'area.areaname',
                'sup.supervisorname',
            ])
            ->withQueryString();

        return Inertia::render('organisation/subarea/Index', [
            'filters' => [
                'search' => $search,
                'per_page' => $perPage,
            ],
            'subAreas' => $subAreas,
            'areas' => $scope->scopeQuery($user, AreaMaster::query(), 'area', 'areacode')
                ->orderBy('areaname')
                ->get(['areacode', 'areaname']),
            'supervisors' => Supervisor::query()
                ->orderBy('supervisorname')
                ->get(['supervisorcode', 'supervisorname']),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validatedData($request);

        $data['created'] = auth()->user()->name;
        $data['cdat'] = now();
        $data['modified'] = auth()->user()->name;
        $data['mdat'] = now();

        SubAreaMaster::create($data);

        return back()->with('success', 'Sub Area created.');
    }

    public function update(Request $request, SubAreaMaster $subarea): RedirectResponse
    {
        abort_unless(app(AccessScopeService::class)->allows($request->user(), 'subarea', $subarea->subareacode), 403);

        $data = $this->validatedData($request, $subarea);

        $data['modified'] = auth()->user()->name;
        $data['mdat'] = now();

        $subarea->update($data);

        return back()->with('success', 'Sub Area updated.');
    }

    public function destroy(SubAreaMaster $subarea): RedirectResponse
    {
        abort_unless(app(AccessScopeService::class)->allows(request()->user(), 'subarea', $subarea->subareacode), 403);

        try {
            $subarea->delete();
        } catch (\Throwable $e) {
            return back()->with('error', 'Cannot delete: record is in use.');
        }

        return back()->with('success', 'Sub Area deleted.');
    }

    private function validatedData(Request $request, ?SubAreaMaster $subarea = null): array
    {
        $data = $request->validate([
            'alternatesubareacode' => [
                'nullable',
                'string',
                'max:30',
                Rule::unique('subareamaster', 'alternatesubareacode')
                    ->ignore($subarea?->subareacode, 'subareacode')
                    ->where(fn ($query) => $query->whereNotNull('alternatesubareacode')),
            ],
            'subareaname' => [
                'required',
                'string',
                'max:50',
                Rule::unique('subareamaster', 'subareaname')
                    ->ignore($subarea?->subareacode, 'subareacode'),
            ],
            'arbsubareaname' => ['nullable', 'string', 'max:50'],
            'areacode' => ['required', 'integer', Rule::exists('areamaster', 'areacode')],
            'supervisorcode' => ['required', 'integer', Rule::exists('supervisor', 'supervisorcode')],
            'activestatus' => ['required', 'integer', Rule::in([0, 1])],
        ]);

        if (! app(AccessScopeService::class)->allows($request->user(), 'area', $data['areacode'] ?? null)) {
            throw ValidationException::withMessages([
                'areacode' => 'Selected area is outside your access scope.',
            ]);
        }

        return $data;
    }
}
