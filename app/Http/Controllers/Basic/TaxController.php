<?php

namespace App\Http\Controllers\Basic;

use App\Http\Controllers\Controller;
use App\Models\TaxMaster;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class TaxController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('basic/Tax', [
            'taxes' => TaxMaster::orderBy('taxcode')->get([
                'taxcode', 'taxdescription', 'arbtaxdescription', 'pricecomponent',
            ]),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $request->validate([
            'taxcode'       => ['required', 'string', 'max:20', 'unique:taxmaster,taxcode'],
            'taxdescription'=> ['required', 'string', 'max:100'],
        ]);

        TaxMaster::create($request->only(
            'taxcode', 'taxdescription', 'arbtaxdescription', 'pricecomponent'
        ));

        return back()->with('success', 'Tax created.');
    }

    public function update(Request $request, TaxMaster $tax): RedirectResponse
    {
        $request->validate([
            'taxcode'       => ['required', 'string', 'max:20', 'unique:taxmaster,taxcode,' . $tax->taxcode . ',taxcode'],
            'taxdescription'=> ['required', 'string', 'max:100'],
        ]);

        $tax->update($request->only(
            'taxcode', 'taxdescription', 'arbtaxdescription', 'pricecomponent'
        ));

        return back()->with('success', 'Tax updated.');
    }

    public function destroy(TaxMaster $tax): RedirectResponse
    {
        $tax->delete();
        return back()->with('success', 'Tax deleted.');
    }
}
