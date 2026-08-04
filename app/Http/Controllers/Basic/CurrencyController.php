<?php

namespace App\Http\Controllers\Basic;

use App\Http\Controllers\Controller;
use App\Models\CurrencyMaster;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class CurrencyController extends Controller
{
    public function index(): Response
    {
        $search = request('search');
        $perPage = (int) request('per_page', 10);
        $perPage = in_array($perPage, [10, 25, 50, 100], true) ? $perPage : 10;

        $currencies = CurrencyMaster::query()
            ->when($search, function ($query, $searchTerm) {
                $query->where(function ($inner) use ($searchTerm) {
                    $inner->where('currencyname', 'like', '%' . $searchTerm . '%')
                        ->orWhere('alternatecode', 'like', '%' . $searchTerm . '%')
                        ->orWhere('currencysymbol', 'like', '%' . $searchTerm . '%');
                });
            })
            ->orderBy('currencycode')
            ->paginate($perPage, [
                'currencycode', 'alternatecode', 'currencyname', 'arbcurrencyname',
                'currencysymbol', 'decimalplaces', 'defaultcurrency',
            ])
            ->withQueryString();

        return Inertia::render('basic/currency/Index', [
            'filters' => [
                'search' => $search,
                'per_page' => $perPage,
            ],
            'currencies' => $currencies,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $request->validate([
            'currencyname'  => ['required', 'string', 'max:50', 'unique:currencymaster,currencyname'],
            'currencysymbol'=> ['required', 'string', 'max:10'],
            'alternatecode' => [
                'nullable',
                'string',
                'max:20',
                Rule::unique('currencymaster', 'alternatecode')->where(fn ($query) => $query->whereNotNull('alternatecode')),
            ],
            'decimalplaces' => ['nullable', 'integer', 'min:0', 'max:6'],
        ]);

        CurrencyMaster::create($request->only(
            'alternatecode', 'currencyname', 'arbcurrencyname',
            'currencysymbol', 'arbcurrencysymbol', 'decimalplaces', 'defaultcurrency'
        ));

        return back()->with('success', 'Currency created.');
    }

    public function update(Request $request, CurrencyMaster $currency): RedirectResponse
    {
        $request->validate([
            'currencyname'  => ['required', 'string', 'max:50', 'unique:currencymaster,currencyname,' . $currency->currencycode . ',currencycode'],
            'currencysymbol'=> ['required', 'string', 'max:10'],
            'alternatecode' => [
                'nullable',
                'string',
                'max:20',
                Rule::unique('currencymaster', 'alternatecode')
                    ->ignore($currency->currencycode, 'currencycode')
                    ->where(fn ($query) => $query->whereNotNull('alternatecode')),
            ],
            'decimalplaces' => ['nullable', 'integer', 'min:0', 'max:6'],
        ]);

        $currency->update($request->only(
            'alternatecode', 'currencyname', 'arbcurrencyname',
            'currencysymbol', 'arbcurrencysymbol', 'decimalplaces', 'defaultcurrency'
        ));

        return back()->with('success', 'Currency updated.');
    }

    public function destroy(CurrencyMaster $currency): RedirectResponse
    {
        $currency->delete();
        return back()->with('success', 'Currency deleted.');
    }
}
