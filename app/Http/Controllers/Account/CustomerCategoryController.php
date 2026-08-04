<?php

namespace App\Http\Controllers\Account;

use App\Http\Controllers\Controller;
use App\Models\CategoryMaster;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class CustomerCategoryController extends Controller
{
    public function index(): Response
    {
        $search = request('search');
        $perPage = (int) request('per_page', 10);
        $perPage = in_array($perPage, [10, 25, 50, 100], true) ? $perPage : 10;

        $categories = CategoryMaster::query()
            ->when($search, function ($query, $searchTerm) {
                $query->where(function ($inner) use ($searchTerm) {
                    $inner->where('categoryid', 'like', '%' . $searchTerm . '%')
                        ->orWhere('alternatecode', 'like', '%' . $searchTerm . '%')
                        ->orWhere('categoryname', 'like', '%' . $searchTerm . '%')
                        ->orWhere('arbcategoryname', 'like', '%' . $searchTerm . '%');
                });
            })
            ->orderBy('categoryid')
            ->paginate($perPage)
            ->withQueryString();

        return Inertia::render('account/customercategory/Index', [
            'filters' => [
                'search' => $search,
                'per_page' => $perPage,
            ],
            'categories' => $categories,
            'nextCode' => $this->nextCategoryCode(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $payload = $this->validatedData($request);
        $username = auth()->user()?->username ?? auth()->user()?->name;
        $payload['created'] = $username;
        $payload['cdat'] = now();
        $payload['modified'] = $username;
        $payload['mdat'] = now();

        CategoryMaster::create($payload);

        return redirect()
            ->route('account.customer-category.index')
            ->with('success', 'Customer category created.');
    }

    public function update(Request $request, CategoryMaster $customerCategory): RedirectResponse
    {
        $payload = $this->validatedData($request, $customerCategory);
        $payload['modified'] = auth()->user()?->username ?? auth()->user()?->name;
        $payload['mdat'] = now();

        $customerCategory->update($payload);

        return redirect()
            ->route('account.customer-category.index')
            ->with('success', 'Customer category updated.');
    }

    public function destroy(CategoryMaster $customerCategory): RedirectResponse
    {
        try {
            $customerCategory->delete();
        } catch (\Throwable $e) {
            return back()->with('error', 'Cannot delete: record is in use.');
        }

        return back()->with('success', 'Customer category deleted.');
    }

    private function validatedData(Request $request, ?CategoryMaster $category = null): array
    {
        $data = $request->validate([
            'alternatecode' => ['nullable', 'string', 'max:50'],
            'categoryname' => ['required', 'string', 'max:50'],
            'arbcategoryname' => ['nullable', 'string', 'max:50'],
            'activestatus' => ['required', 'integer', Rule::in([0, 1])],
        ]);

        foreach (['alternatecode', 'arbcategoryname'] as $key) {
            if (array_key_exists($key, $data) && $data[$key] === '') {
                $data[$key] = null;
            }
        }

        return $data;
    }

    private function nextCategoryCode(): int
    {
        return ((int) CategoryMaster::max('categoryid')) + 1;
    }
}
