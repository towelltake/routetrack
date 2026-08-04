<?php

namespace App\Http\Controllers\Usermanagement;

use App\Http\Controllers\Controller;
use App\Models\UserType;
use App\Models\UserTypeDetail;
use App\Models\ModuleDetail;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Inertia\Inertia;
use Inertia\Response;

class UserTypeController extends Controller
{
    public function index(): Response
    {
        $search = request('search');
        $perPage = (int) request('per_page', 10);
        $perPage = in_array($perPage, [10, 25, 50, 100], true) ? $perPage : 10;

        $userTypes = UserType::query()
            ->when($search, fn ($query, $searchTerm) => $query->where('usertypename', 'like', '%' . $searchTerm . '%'))
            ->orderBy('usertypename')
            ->paginate($perPage)
            ->withQueryString();

        return Inertia::render('usermanagement/UserType', [
            'filters' => [
                'search' => $search,
                'per_page' => $perPage,
            ],
            'userTypes' => $userTypes,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $request->validate([
            'user_type' => ['required', 'string', 'max:50', 'unique:usertype,usertypename'],
        ]);

        DB::transaction(function () use ($request) {
            $nextId = ((int) UserType::max('usertypeid')) + 1;
            $ut = UserType::create([
                'usertypeid' => $nextId,
                'usertypename' => $request->user_type,
            ]);

            // Seed permission rows for this user type from existing module forms
            $this->seedUserTypePermissions($ut->usertypeid);
        });

        return back()->with('success', 'User type created successfully.');
    }

    public function update(Request $request, UserType $userType): RedirectResponse
    {
        $request->validate([
            'user_type' => ['required', 'string', 'max:50', 'unique:usertype,usertypename,' . $userType->usertypeid . ',usertypeid'],
        ]);

        $userType->update([
            'usertypename' => $request->user_type,
        ]);

        return back()->with('success', 'User type updated successfully.');
    }

    public function destroy(UserType $userType): RedirectResponse
    {
        $inUse = \App\Models\User::where('usertypeid', $userType->usertypeid)->exists();

        if ($inUse) {
            return back()->with('error', 'Cannot delete: user type is assigned to one or more users.');
        }

        DB::transaction(function () use ($userType) {
            UserTypeDetail::where('usertypeid', $userType->usertypeid)->delete();
            $userType->delete();
        });

        return back()->with('success', 'User type deleted successfully.');
    }

    private function seedUserTypePermissions(int $usertypeid): void
    {
        $forms = ModuleDetail::all();
        $hasViewColumn = Schema::hasColumn('usertypedetail', 'viewdata');

        if ($forms->isEmpty()) {
            return;
        }

        $rows = $forms->map(function ($f) use ($usertypeid, $hasViewColumn) {
            $row = [
                'usertypeid'     => $usertypeid,
                'formname'       => $f->formname,
                'formdescription'=> $f->formdescription,
                'readdata'       => 0,
                'updatedata'     => 0,
                'insertdata'     => 0,
                'deletedata'     => 0,
                'allpermissions' => 0,
                'moduleid'       => $f->moduleid,
                'formid'         => $f->formid,
            ];

            if ($hasViewColumn) {
                $row['viewdata'] = 0;
            }

            return $row;
        })->toArray();

        UserTypeDetail::insert($rows);
    }
}
