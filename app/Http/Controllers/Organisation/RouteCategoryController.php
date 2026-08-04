<?php

namespace App\Http\Controllers\Organisation;

use App\Http\Controllers\Controller;
use App\Models\RouteCategory;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class RouteCategoryController extends Controller
{
    public function index(): Response
    {
        $search = request('search');
        $perPage = (int) request('per_page', 10);
        $perPage = in_array($perPage, [10, 25, 50, 100], true) ? $perPage : 10;

        $routeCategories = RouteCategory::query()
            ->when($search, function ($query, $searchTerm) {
                $query->where(function ($inner) use ($searchTerm) {
                    $inner->where('routecatname', 'like', '%' . $searchTerm . '%')
                        ->orWhere('arbroutecatname', 'like', '%' . $searchTerm . '%')
                        ->orWhere('routecatcode', 'like', '%' . $searchTerm . '%');
                });
            })
            ->orderBy('routecatcode')
            ->paginate($perPage, [
                'routecatcode',
                'routecatname',
                'arbroutecatname',
                'created',
                'cdat',
                'modified',
                'mdat',
            ])
            ->withQueryString();

        return Inertia::render('organisation/routecategory/Index', [
            'filters' => [
                'search' => $search,
                'per_page' => $perPage,
            ],
            'routeCategories' => $routeCategories,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validatedData($request);

        $data['created'] = auth()->user()->name;
        $data['cdat'] = now();
        $data['modified'] = auth()->user()->name;
        $data['mdat'] = now();

        RouteCategory::create($data);

        return back()->with('success', 'Route Category created.');
    }

    public function update(Request $request, RouteCategory $routecategory): RedirectResponse
    {
        $data = $this->validatedData($request, $routecategory);

        $data['modified'] = auth()->user()->name;
        $data['mdat'] = now();

        $routecategory->update($data);

        return back()->with('success', 'Route Category updated.');
    }

    public function destroy(RouteCategory $routecategory): RedirectResponse
    {
        try {
            $routecategory->delete();
        } catch (\Throwable $e) {
            return back()->with('error', 'Cannot delete: record is in use.');
        }

        return back()->with('success', 'Route Category deleted.');
    }

    private function validatedData(Request $request, ?RouteCategory $routeCategory = null): array
    {
        return $request->validate([
            'routecatname' => [
                'required',
                'string',
                'max:50',
                Rule::unique('routecategory', 'routecatname')
                    ->ignore($routeCategory?->routecatcode, 'routecatcode'),
            ],
            'arbroutecatname' => ['nullable', 'string', 'max:50'],
        ]);
    }
}
