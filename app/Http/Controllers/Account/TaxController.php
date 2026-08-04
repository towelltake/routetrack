<?php

namespace App\Http\Controllers\Account;

use App\Http\Controllers\Controller;
use App\Models\AccountTax;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class TaxController extends Controller
{
    public function index(): Response
    {
        $search = request('search');
        $perPage = (int) request('per_page', 10);
        $perPage = in_array($perPage, [10, 25, 50, 100], true) ? $perPage : 10;

        $taxes = AccountTax::query()
            ->when($search, function ($query, $searchTerm) {
                $query->where(function ($inner) use ($searchTerm) {
                    $inner->where('taxcode', 'like', '%' . $searchTerm . '%')
                        ->orWhere('taxdescription', 'like', '%' . $searchTerm . '%')
                        ->orWhere('arbtaxdescription', 'like', '%' . $searchTerm . '%');
                });
            })
            ->orderBy('taxcode')
            ->paginate($perPage)
            ->withQueryString();

        return Inertia::render('account/tax/Index', [
            'filters' => [
                'search' => $search,
                'per_page' => $perPage,
            ],
            'taxes' => $taxes,
            'nextCode' => $this->nextTaxCode(),
            'optionSets' => [
                'taxTypeOptions' => [
                    ['id' => 1, 'label' => 'Customer'],
                    ['id' => 2, 'label' => 'Item'],
                ],
                'taxBaseOptions' => [
                    ['id' => 1, 'label' => 'Price'],
                    ['id' => 2, 'label' => 'Quantity'],
                ],
            ],
        ]);
    }

    public function create(): Response
    {
        $props = $this->formProps();
        $props['taxData']['taxcode'] = $this->nextTaxCode();

        return Inertia::render('account/tax/Create', $props);
    }

    public function show(AccountTax $tax): Response
    {
        return Inertia::render('account/tax/View', $this->formProps($tax));
    }

    public function edit(AccountTax $tax): Response
    {
        return Inertia::render('account/tax/Edit', $this->formProps($tax));
    }

    public function store(Request $request): RedirectResponse
    {
        $payload = $this->validatedData($request);
        $username = auth()->user()?->username ?? auth()->user()?->name ?? 'system';
        $payload['taxcode'] = $this->nextTaxCode();
        $payload['created'] = $username;
        $payload['cdat'] = now();
        $payload['modified'] = $username;
        $payload['mdat'] = now();

        AccountTax::create($payload);

        return redirect()
            ->route('account.tax.index')
            ->with('success', 'Tax created.');
    }

    public function update(Request $request, AccountTax $tax): RedirectResponse
    {
        $payload = $this->validatedData($request);
        $payload['modified'] = auth()->user()?->username ?? auth()->user()?->name ?? 'system';
        $payload['mdat'] = now();

        $tax->update($payload);

        return redirect()
            ->route('account.tax.index')
            ->with('success', 'Tax updated.');
    }

    public function destroy(AccountTax $tax): RedirectResponse
    {
        $taxCode = $tax->taxcode;

        $isInUse = DB::table('customermaster')
            ->where('custtaxkey1', $taxCode)
            ->orWhere('custtaxkey2', $taxCode)
            ->orWhere('custtaxkey3', $taxCode)
            ->exists()
            || DB::table('itemmaster')
                ->where('itemtaxkey1', $taxCode)
                ->orWhere('itemtaxkey2', $taxCode)
                ->orWhere('itemtaxkey3', $taxCode)
                ->exists();

        if ($isInUse) {
            return back()->with('error', 'Cannot delete: record is in use.');
        }

        try {
            $tax->delete();
        } catch (\Throwable $e) {
            return back()->with('error', 'Cannot delete: record is in use.');
        }

        return back()->with('success', 'Tax deleted.');
    }

    private function formProps(?AccountTax $tax = null): array
    {
        return [
            'taxData' => $this->taxFormData($tax),
            'optionSets' => [
                'taxTypeOptions' => [
                    ['id' => 1, 'label' => 'Customer'],
                    ['id' => 2, 'label' => 'Item'],
                ],
                'taxBaseOptions' => [
                    ['id' => 1, 'label' => 'Price'],
                    ['id' => 2, 'label' => 'Quantity'],
                ],
            ],
        ];
    }

    private function taxFormData(?AccountTax $tax): array
    {
        $record = $tax?->toArray() ?? [];

        return array_merge([
            'taxcode' => null,
            'taxdescription' => '',
            'arbtaxdescription' => '',
            'taxtype' => 1,
            'taxpercentage' => 0,
            'taxbase' => 1,
            'cdat' => null,
        ], array_intersect_key($record, array_flip([
            'taxcode',
            'taxdescription',
            'arbtaxdescription',
            'taxtype',
            'taxpercentage',
            'taxbase',
            'cdat',
        ])));
    }

    private function validatedData(Request $request): array
    {
        return $request->validate([
            'taxdescription' => ['required', 'string', 'max:50'],
            'arbtaxdescription' => ['nullable', 'string', 'max:50'],
            'taxtype' => ['required', 'integer', Rule::in([1, 2])],
            'taxpercentage' => ['required', 'numeric', 'min:0', 'max:9999.9999'],
            'taxbase' => ['required', 'integer', Rule::in([1, 2])],
        ]);
    }

    private function nextTaxCode(): int
    {
        return ((int) AccountTax::query()->max('taxcode')) + 1;
    }
}
