<?php

namespace App\Http\Controllers\Organisation;

use App\Http\Controllers\Controller;
use App\Models\VanMaster;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class VanController extends Controller
{
    public function index(): Response
    {
        $search = request('search');
        $perPage = (int) request('per_page', 10);
        $perPage = in_array($perPage, [10, 25, 50, 100], true) ? $perPage : 10;

        $vans = VanMaster::query()
            ->when($search, function ($query, $searchTerm) {
                $query->where(function ($inner) use ($searchTerm) {
                    $inner->where('vandescription', 'like', '%' . $searchTerm . '%')
                        ->orWhere('arbvandescription', 'like', '%' . $searchTerm . '%')
                        ->orWhere('alternatecode', 'like', '%' . $searchTerm . '%')
                        ->orWhere('vanregno', 'like', '%' . $searchTerm . '%')
                        ->orWhere('vanmodel', 'like', '%' . $searchTerm . '%')
                        ->orWhere('vantype', 'like', '%' . $searchTerm . '%');
                });
            })
            ->orderBy('vancode')
            ->paginate($perPage, [
                'vancode',
                'alternatecode',
                'vanregno',
                'vanmodel',
                'vantype',
                'vandescription',
                'arbvandescription',
                'activestatus',
                'created',
                'cdat',
                'modified',
                'mdat',
            ])
            ->withQueryString();

        return Inertia::render('organisation/van/Index', [
            'filters' => [
                'search' => $search,
                'per_page' => $perPage,
            ],
            'vans' => $vans,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validatedData($request);

        $data['created'] = auth()->user()->name;
        $data['cdat'] = now();
        $data['modified'] = auth()->user()->name;
        $data['mdat'] = now();

        VanMaster::create($data);

        return back()->with('success', 'Van created.');
    }

    public function update(Request $request, VanMaster $van): RedirectResponse
    {
        $data = $this->validatedData($request, $van);

        $data['modified'] = auth()->user()->name;
        $data['mdat'] = now();

        $van->update($data);

        return back()->with('success', 'Van updated.');
    }

    public function destroy(VanMaster $van): RedirectResponse
    {
        try {
            $van->delete();
        } catch (\Throwable $e) {
            return back()->with('error', 'Cannot delete: record is in use.');
        }

        return back()->with('success', 'Van deleted.');
    }

    private function validatedData(Request $request, ?VanMaster $van = null): array
    {
        return $request->validate([
            'alternatecode' => [
                'nullable',
                'string',
                'max:50',
                Rule::unique('vanmaster', 'alternatecode')
                    ->ignore($van?->vancode, 'vancode')
                    ->where(fn ($query) => $query->whereNotNull('alternatecode')),
            ],
            'vanregno' => ['required', 'string', 'max:50'],
            'vanmodel' => ['nullable', 'string', 'max:50'],
            'vantype' => ['nullable', 'string', 'max:50'],
            'vandescription' => [
                'required',
                'string',
                'max:50',
                Rule::unique('vanmaster', 'vandescription')
                    ->ignore($van?->vancode, 'vancode'),
            ],
            'arbvandescription' => ['nullable', 'string', 'max:50'],
            'activestatus' => ['required', 'integer', Rule::in([0, 1])],
        ]);
    }
}
