<?php

namespace App\Http\Controllers\Basic;

use App\Http\Controllers\Controller;
use App\Models\BankMaster;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class BankController extends Controller
{
    public function index(): Response
    {
        $search = request('search');
        $perPage = (int) request('per_page', 10);
        $perPage = in_array($perPage, [10, 25, 50, 100], true) ? $perPage : 10;

        $banks = BankMaster::query()
            ->when($search, function ($query, $searchTerm) {
                $query->where(function ($inner) use ($searchTerm) {
                    $inner->where('bankname', 'like', '%' . $searchTerm . '%')
                        ->orWhere('arbbankname', 'like', '%' . $searchTerm . '%')
                        ->orWhere('alternatecode', 'like', '%' . $searchTerm . '%')
                        ->orWhere('acnumber', 'like', '%' . $searchTerm . '%');
                });
            })
            ->orderBy('bankcode')
            ->paginate($perPage, [
                'bankcode', 'bankname', 'arbbankname', 'alternatecode',
                'type', 'acnumber', 'activestatus',
            ])
            ->withQueryString();

        return Inertia::render('basic/bank/Index', [
            'filters' => [
                'search' => $search,
                'per_page' => $perPage,
            ],
            'banks' => $banks,
        ]);
    }

    private function validated(Request $request, ?BankMaster $bank = null): array
    {
        $request->validate([
            'bankname' => [
                'required',
                'string',
                'max:50',
                Rule::unique('bankmaster', 'bankname')->ignore($bank?->bankcode, 'bankcode'),
            ],
            'arbbankname' => ['nullable', 'string', 'max:50'],
            'alternatecode' => [
                'required',
                'string',
                'max:50',
                Rule::unique('bankmaster', 'alternatecode')->ignore($bank?->bankcode, 'bankcode'),
            ],
            'type' => ['nullable', 'integer', 'in:1,2'],
            'acnumber' => ['nullable', 'digits_between:1,18'],
            'activestatus' => ['required', 'integer', 'in:0,1'],
        ]);

        return [
            'bankname'     => $request->bankname,
            'arbbankname'  => $request->arbbankname,
            'alternatecode'=> $request->alternatecode,
            'type'         => $request->filled('type') ? (int) $request->type : null,
            'acnumber'     => $request->filled('acnumber') ? $request->acnumber : 0,
            'activestatus' => (int) $request->activestatus,
        ];
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request);
        $data['created'] = auth()->user()->name;
        $data['cdat'] = now();
        $data['modified'] = auth()->user()->name;
        $data['mdat'] = now();

        BankMaster::create($data);
        return back()->with('success', 'Bank created.');
    }

    public function update(Request $request, BankMaster $bank): RedirectResponse
    {
        $data = $this->validated($request, $bank);
        $data['modified'] = auth()->user()->name;
        $data['mdat'] = now();

        $bank->update($data);
        return back()->with('success', 'Bank updated.');
    }

    public function destroy(BankMaster $bank): RedirectResponse
    {
        try {
            $bank->delete();
        } catch (\Throwable $e) {
            return back()->with('error', 'Cannot delete: record is in use.');
        }

        return back()->with('success', 'Bank deleted.');
    }
}
