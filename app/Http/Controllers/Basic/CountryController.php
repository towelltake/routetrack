<?php

namespace App\Http\Controllers\Basic;

use App\Http\Controllers\Controller;
use App\Models\CountryMaster;
use App\Models\CurrencyMaster;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class CountryController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('basic/Country', [
            'countries'  => CountryMaster::orderBy('countrycode')->get([
                'countrycode', 'alternatecode', 'countryname', 'arbcountryname', 'currencycode',
            ]),
            'currencies' => CurrencyMaster::orderBy('currencyname')->get(['currencycode', 'currencyname']),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $request->validate([
            'countryname'  => ['required', 'string', 'max:50'],
            'alternatecode'=> ['nullable', 'string', 'max:20'],
            'currencycode' => ['nullable', 'integer'],
        ]);

        CountryMaster::create($request->only(
            'alternatecode', 'countryname', 'arbcountryname', 'currencycode'
        ));

        return back()->with('success', 'Country created.');
    }

    public function update(Request $request, CountryMaster $country): RedirectResponse
    {
        $request->validate([
            'countryname'  => ['required', 'string', 'max:50'],
            'alternatecode'=> ['nullable', 'string', 'max:20'],
            'currencycode' => ['nullable', 'integer'],
        ]);

        $country->update($request->only(
            'alternatecode', 'countryname', 'arbcountryname', 'currencycode'
        ));

        return back()->with('success', 'Country updated.');
    }

    public function destroy(CountryMaster $country): RedirectResponse
    {
        $country->delete();
        return back()->with('success', 'Country deleted.');
    }
}
