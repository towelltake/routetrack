<?php

namespace App\Http\Controllers\Basic;

use App\Http\Controllers\Controller;
use App\Models\InventoryLocation;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class InventoryLocationController extends Controller
{
    public function index(): Response
    {
        $search = request('search');
        $perPage = (int) request('per_page', 10);
        $perPage = in_array($perPage, [10, 25, 50, 100], true) ? $perPage : 10;

        $locations = InventoryLocation::query()
            ->when($search, function ($query, $searchTerm) {
                $query->where(function ($inner) use ($searchTerm) {
                    $inner->where('description', 'like', '%' . $searchTerm . '%')
                        ->orWhere('arbdescription', 'like', '%' . $searchTerm . '%')
                        ->orWhere('alternatecode', 'like', '%' . $searchTerm . '%');
                });
            })
            ->orderBy('code')
            ->paginate($perPage, [
                'code',
                'alternatecode',
                'description',
                'arbdescription',
                'hhcdescription',
                'cdat',
            ])
            ->withQueryString();

        return Inertia::render('basic/inventorylocation/Index', [
            'filters' => [
                'search' => $search,
                'per_page' => $perPage,
            ],
            'locations' => $locations,
        ]);
    }

    private function validated(Request $request, ?InventoryLocation $inventoryLocation = null): array
    {
        return $request->validate([
            'alternatecode' => [
                'nullable',
                'string',
                'max:50',
                Rule::unique('inventorylocation', 'alternatecode')->ignore($inventoryLocation?->code, 'code'),
            ],
            'description' => [
                'required',
                'string',
                'max:50',
                Rule::unique('inventorylocation', 'description')->ignore($inventoryLocation?->code, 'code'),
            ],
            'arbdescription' => ['required', 'string', 'max:50'],
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request);
        $data['created'] = auth()->user()?->name;
        $data['cdat'] = now();
        $data['modified'] = auth()->user()?->name;
        $data['mdat'] = now();
        $data['hhcdescription'] = '';

        InventoryLocation::create($data);

        return back()->with('success', 'Inventory location created.');
    }

    public function update(Request $request, InventoryLocation $inventorylocation): RedirectResponse
    {
        $data = $this->validated($request, $inventorylocation);
        $data['modified'] = auth()->user()?->name;
        $data['mdat'] = now();
        $data['hhcdescription'] = $inventorylocation->hhcdescription ?? '';

        $inventorylocation->update($data);

        return back()->with('success', 'Inventory location updated.');
    }

    public function destroy(InventoryLocation $inventorylocation): RedirectResponse
    {
        try {
            $inventorylocation->delete();
        } catch (\Throwable $e) {
            return back()->with('error', 'Cannot delete: record is in use.');
        }

        return back()->with('success', 'Inventory location deleted.');
    }
}
