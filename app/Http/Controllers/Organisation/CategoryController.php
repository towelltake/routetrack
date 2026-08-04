<?php

namespace App\Http\Controllers\Organisation;

use App\Http\Controllers\Controller;
use App\Models\CategoryMaster;
use Illuminate\Http\Request;
use Inertia\Inertia;

class CategoryController extends Controller
{
    public function index()
    {
        return Inertia::render('operation/Category', [
            'categories' => CategoryMaster::orderBy('categoryid')->get([
                'categoryid', 'alternatecode', 'categoryname', 'arbcategoryname', 'activestatus',
            ]),
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'alternatecode'   => 'nullable|string|max:50',
            'categoryname'    => 'required|string|max:50',
            'arbcategoryname' => 'nullable|string|max:50',
            'activestatus'    => 'required|integer',
        ]);

        $data['created']  = auth()->user()->name;
        $data['cdat']     = now();
        $data['modified'] = auth()->user()->name;
        $data['mdat']     = now();

        CategoryMaster::create($data);
        return back();
    }

    public function update(Request $request, CategoryMaster $category)
    {
        $data = $request->validate([
            'alternatecode'   => 'nullable|string|max:50',
            'categoryname'    => 'required|string|max:50',
            'arbcategoryname' => 'nullable|string|max:50',
            'activestatus'    => 'required|integer',
        ]);

        $data['modified'] = auth()->user()->name;
        $data['mdat']     = now();

        $category->update($data);
        return back();
    }

    public function destroy(CategoryMaster $category)
    {
        try {
            $category->delete();
        } catch (\Exception $e) {
            return back()->withErrors(['delete' => 'Cannot delete: record is in use.']);
        }
        return back();
    }
}
