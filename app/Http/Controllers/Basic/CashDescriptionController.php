<?php

namespace App\Http\Controllers\Basic;

use App\Http\Controllers\Controller;
use App\Models\CashDescription;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class CashDescriptionController extends Controller
{
    public function index(): Response
    {
        $search = request('search');
        $perPage = (int) request('per_page', 10);
        $perPage = in_array($perPage, [10, 25, 50, 100], true) ? $perPage : 10;

        $items = CashDescription::query()
            ->when($search, function ($query, $searchTerm) {
                $query->where(function ($inner) use ($searchTerm) {
                    $inner->where('description', 'like', '%' . $searchTerm . '%')
                        ->orWhere('arbdescription', 'like', '%' . $searchTerm . '%')
                        ->orWhere('alternatecode', 'like', '%' . $searchTerm . '%');
                });
            })
            ->orderBy('code')
            ->paginate($perPage, [
                'code', 'alternatecode', 'description', 'arbdescription', 'hhcdescription',
            ])
            ->withQueryString();

        return Inertia::render('basic/cashdescription/Index', [
            'filters' => [
                'search' => $search,
                'per_page' => $perPage,
            ],
            'items' => $items,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request);
        $data['created'] = auth()->user()->name;
        $data['cdat'] = now();
        $data['modified'] = auth()->user()->name;
        $data['mdat'] = now();
        $data['hhcdescription'] = '';

        CashDescription::create($data);

        return back()->with('success', 'Cash description created.');
    }

    public function update(Request $request, CashDescription $cashdescription): RedirectResponse
    {
        $data = $this->validated($request, $cashdescription);
        $data['modified'] = auth()->user()->name;
        $data['mdat'] = now();
        $data['hhcdescription'] = $cashdescription->hhcdescription ?? '';

        $cashdescription->update($data);

        return back()->with('success', 'Cash description updated.');
    }

    public function destroy(CashDescription $cashdescription): RedirectResponse
    {
        try {
            $cashdescription->delete();
        } catch (\Throwable $e) {
            return back()->with('error', 'Cannot delete: record is in use.');
        }

        return back()->with('success', 'Cash description deleted.');
    }

    private function validated(Request $request, ?CashDescription $cashDescription = null): array
    {
        return $request->validate([
            'alternatecode' => [
                'nullable',
                'string',
                'max:50',
                Rule::unique('cashdesc', 'alternatecode')->ignore($cashDescription?->code, 'code'),
            ],
            'description' => [
                'required',
                'string',
                'max:50',
                Rule::unique('cashdesc', 'description')->ignore($cashDescription?->code, 'code'),
            ],
            'arbdescription' => ['required', 'string', 'max:50'],
        ]);
    }
}
