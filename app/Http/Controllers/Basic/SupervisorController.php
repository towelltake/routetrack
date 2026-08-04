<?php

namespace App\Http\Controllers\Basic;

use App\Http\Controllers\Controller;
use App\Models\CompanyMaster;
use App\Models\Supervisor;
use App\Services\AccessScopeService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class SupervisorController extends Controller
{
    public function index(): Response
    {
        $scope = app(AccessScopeService::class);
        $user = request()->user();
        $search = request('search');
        $perPage = (int) request('per_page', 10);
        $perPage = in_array($perPage, [10, 25, 50, 100], true) ? $perPage : 10;

        $supervisorsQuery = Supervisor::query()
            ->leftJoin('company', 'company.cmpycode', '=', 'supervisor.parentcompany')
            ->when($search, function ($query, $searchTerm) {
                $query->where(function ($inner) use ($searchTerm) {
                    $inner->where('supervisor.supervisorname', 'like', '%' . $searchTerm . '%')
                        ->orWhere('supervisor.arbsupervisorname', 'like', '%' . $searchTerm . '%')
                        ->orWhere('supervisor.alternatesupervisorcode', 'like', '%' . $searchTerm . '%')
                        ->orWhere('company.name', 'like', '%' . $searchTerm . '%');
                });
            });

        $scope->scopeQuery($user, $supervisorsQuery, 'company', 'supervisor.parentcompany');

        $supervisors = $supervisorsQuery
            ->orderBy('supervisor.supervisorcode')
            ->paginate($perPage, [
                'supervisor.supervisorcode',
                'supervisor.parentcompany',
                'supervisor.supervisorname',
                'supervisor.arbsupervisorname',
                'supervisor.alternatesupervisorcode',
                'supervisor.type',
                'supervisor.activestatus',
                'supervisor.cdat',
                'company.name as parentcompanyname',
            ])
            ->withQueryString();

        return Inertia::render('basic/supervisor/Index', [
            'filters' => [
                'search' => $search,
                'per_page' => $perPage,
            ],
            'supervisors' => $supervisors,
            'companies' => $scope->scopeQuery($user, CompanyMaster::query(), 'company', 'cmpycode')->orderBy('name')->get([
                'cmpycode',
                'name',
            ]),
        ]);
    }

    private function validated(Request $request, ?Supervisor $supervisor = null): array
    {
        $data = $request->validate([
            'alternatesupervisorcode' => [
                'nullable',
                'string',
                'max:50',
                Rule::unique('supervisor', 'alternatesupervisorcode')
                    ->ignore($supervisor?->supervisorcode, 'supervisorcode'),
            ],
            'parentcompany' => ['nullable', 'integer', Rule::exists('company', 'cmpycode')],
            'supervisorname' => ['required', 'string', 'max:50'],
            'arbsupervisorname' => ['required', 'string', 'max:50'],
            'type' => ['nullable', 'integer'],
            'activestatus' => ['required', 'integer', 'in:0,1'],
        ]);

        if (! app(AccessScopeService::class)->allows($request->user(), 'company', $data['parentcompany'] ?? null)) {
            throw ValidationException::withMessages([
                'parentcompany' => 'Selected company is outside your access scope.',
            ]);
        }

        return $data;
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request);
        $data['type'] = $data['type'] ?? 0;
        $data['created'] = auth()->user()?->name;
        $data['cdat'] = now();
        $data['modified'] = auth()->user()?->name;
        $data['mdat'] = now();

        Supervisor::create($data);

        return back()->with('success', 'Supervisor created.');
    }

    public function update(Request $request, Supervisor $supervisor): RedirectResponse
    {
        abort_unless(app(AccessScopeService::class)->allows($request->user(), 'company', $supervisor->parentcompany), 403);

        $data = $this->validated($request, $supervisor);
        $data['type'] = $data['type'] ?? ($supervisor->type ?? 0);
        $data['modified'] = auth()->user()?->name;
        $data['mdat'] = now();

        $supervisor->update($data);

        return back()->with('success', 'Supervisor updated.');
    }

    public function destroy(Supervisor $supervisor): RedirectResponse
    {
        abort_unless(app(AccessScopeService::class)->allows(request()->user(), 'company', $supervisor->parentcompany), 403);

        try {
            $supervisor->delete();
        } catch (\Throwable $e) {
            return back()->with('error', 'Cannot delete: record is in use.');
        }

        return back()->with('success', 'Supervisor deleted.');
    }
}
