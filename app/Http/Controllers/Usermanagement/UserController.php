<?php

namespace App\Http\Controllers\Usermanagement;

use App\Http\Controllers\Controller;
use App\Models\AreaMaster;
use App\Models\CompanyMaster;
use App\Models\CountryMaster;
use App\Models\DepotMaster;
use App\Models\RegionMaster;
use App\Models\SubAreaMaster;
use App\Models\User;
use App\Models\UserAccessCode;
use App\Models\UserType;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Inertia\Inertia;
use Inertia\Response;

class UserController extends Controller
{
    private const ACCESS_TYPES = [
        1 => 'Company',
        2 => 'Country',
        3 => 'Region',
        4 => 'Depot',
        5 => 'Area',
        6 => 'SubArea',
    ];

    public function index(): Response
    {
        $search = request('search');
        $perPage = (int) request('per_page', 10);
        $perPage = in_array($perPage, [10, 25, 50, 100], true) ? $perPage : 10;

        $users = User::with('userType')
            ->when($search, function ($query, $searchTerm) {
                $matchingAccessTypeIds = $this->matchingAccessTypeIds($searchTerm);

                $query->where('username', 'like', '%' . $searchTerm . '%')
                    ->orWhereHas('userType', fn ($userType) => $userType->where('usertypename', 'like', '%' . $searchTerm . '%'))
                    ->when(
                        !empty($matchingAccessTypeIds),
                        fn ($userQuery) => $userQuery->orWhereIn('accesstypeid', $matchingAccessTypeIds)
                    );
            })
            ->orderBy('userid')
            ->paginate($perPage)
            ->withQueryString();

        $users->getCollection()->transform(fn ($user) => [
            'userid' => $user->userid,
            'username' => $user->username,
            'email' => $user->email,
            'usertypeid' => $user->usertypeid,
            'usertypename' => $user->userType?->user_type ?? '-',
            'accesstypeid' => $user->accesstypeid,
            'access_type' => self::ACCESS_TYPES[(int) $user->accesstypeid] ?? '-',
            'access_ids' => $this->selectedAccessIds($user->username, (int) $user->accesstypeid),
        ]);

        return Inertia::render('usermanagement/UserMaster', [
            'filters' => [
                'search' => $search,
                'per_page' => $perPage,
            ],
            'users' => $users,
            'nextUserId' => ((int) User::max('userid')) + 1,
            'userTypes' => UserType::orderBy('usertypename')->get(['usertypeid', 'usertypename']),
            'accessTypes' => collect(self::ACCESS_TYPES)
                ->map(fn ($label, $id) => ['id' => $id, 'label' => $label])
                ->values(),
            'accessOptions' => [
                1 => CompanyMaster::orderBy('name')->get(['cmpycode as id', 'name as label']),
                2 => CountryMaster::orderBy('countryname')->get(['countrycode as id', 'countryname as label']),
                3 => RegionMaster::orderBy('regionmstname')->get(['regionmstcode as id', 'regionmstname as label']),
                4 => DepotMaster::orderBy('depotname')->get(['depotcode as id', 'depotname as label']),
                5 => AreaMaster::orderBy('areaname')->get(['areacode as id', 'areaname as label']),
                6 => SubAreaMaster::orderBy('subareaname')->get(['subareacode as id', 'subareaname as label']),
            ],
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $rules = [
            'username' => ['required', 'string', 'max:10', 'unique:usermaster,username'],
            'password' => ['required', 'string', 'min:4', 'confirmed'],
            'usertypeid' => ['required', 'integer', 'exists:usertype,usertypeid'],
            'accesstypeid' => ['nullable', 'integer', 'in:1,2,3,4,5,6'],
            'access_ids' => ['nullable', 'array'],
            'access_ids.*' => ['integer'],
        ];

        if ($this->hasEmailColumn()) {
            $rules['email'] = ['nullable', 'email', 'max:30'];
        }

        $data = $request->validate($rules);

        DB::transaction(function () use ($data) {
            $payload = [
                'username' => $data['username'],
                'password' => Hash::make($data['password']),
                'usertypeid' => $data['usertypeid'],
                'accesstypeid' => $data['accesstypeid'] ?? null,
            ];

            if ($this->hasEmailColumn()) {
                $payload['email'] = $data['email'] ?? null;
            }

            $user = User::create($payload);

            $this->syncAccessCodes($user->username, $data['accesstypeid'] ?? null, $data['access_ids'] ?? []);
        });

        return back()->with('success', 'User master record created successfully.');
    }

    public function update(Request $request, User $user): RedirectResponse
    {
        $rules = [
            'username' => ['required', 'string', 'max:10', 'unique:usermaster,username,' . $user->userid . ',userid'],
            'password' => ['nullable', 'string', 'min:4', 'confirmed'],
            'usertypeid' => ['required', 'integer', 'exists:usertype,usertypeid'],
            'accesstypeid' => ['nullable', 'integer', 'in:1,2,3,4,5,6'],
            'access_ids' => ['nullable', 'array'],
            'access_ids.*' => ['integer'],
        ];

        if ($this->hasEmailColumn()) {
            $rules['email'] = ['nullable', 'email', 'max:30'];
        }

        $data = $request->validate($rules);

        DB::transaction(function () use ($user, $data) {
            $oldUsername = $user->username;

            $payload = [
                'username' => $data['username'],
                'usertypeid' => $data['usertypeid'],
                'accesstypeid' => $data['accesstypeid'] ?? null,
            ];

            if ($this->hasEmailColumn()) {
                $payload['email'] = $data['email'] ?? null;
            }

            if (!empty($data['password'])) {
                $payload['password'] = Hash::make($data['password']);
            }

            $user->update($payload);

            if ($oldUsername !== $user->username) {
                UserAccessCode::where('username', $oldUsername)->delete();
            }

            $this->syncAccessCodes($user->username, $data['accesstypeid'] ?? null, $data['access_ids'] ?? []);
        });

        return back()->with('success', 'User master record updated successfully.');
    }

    public function destroy(User $user): RedirectResponse
    {
        if ((int) Auth::id() === (int) $user->userid) {
            return back()->with('error', "Can't delete the currently logged in user.");
        }

        UserAccessCode::where('username', $user->username)->delete();
        $user->delete();

        return back()->with('success', 'User master record deleted successfully.');
    }

    private function selectedAccessIds(string $username, int $accessType): array
    {
        if ($accessType < 1 || $accessType > 6) {
            return [];
        }

        $column = match ($accessType) {
            1 => 'cmpycode',
            2 => 'countrycode',
            3 => 'regionmstcode',
            4 => 'depotcode',
            5 => 'areacode',
            6 => 'subareacode',
        };

        return UserAccessCode::where('username', $username)
            ->whereNotNull($column)
            ->pluck($column)
            ->map(fn ($value) => (int) $value)
            ->filter()
            ->values()
            ->all();
    }

    private function syncAccessCodes(string $username, ?int $accessType, array $accessIds): void
    {
        UserAccessCode::where('username', $username)->delete();

        if (empty($accessType) || empty($accessIds)) {
            return;
        }

        $column = match ($accessType) {
            1 => 'cmpycode',
            2 => 'countrycode',
            3 => 'regionmstcode',
            4 => 'depotcode',
            5 => 'areacode',
            6 => 'subareacode',
            default => null,
        };

        if ($column === null) {
            return;
        }

        $rows = collect($accessIds)
            ->map(fn ($id) => (int) $id)
            ->filter()
            ->unique()
            ->map(function ($id) use ($username, $column) {
                $row = [
                    'username' => $username,
                    'cmpycode' => null,
                    'countrycode' => null,
                    'regionmstcode' => null,
                    'depotcode' => null,
                    'areacode' => null,
                    'subareacode' => null,
                ];

                $row[$column] = $id;

                return $row;
            })
            ->values()
            ->all();

        if (!empty($rows)) {
            UserAccessCode::insert($rows);
        }
    }

    private function matchingAccessTypeIds(string $search): array
    {
        $search = strtolower(trim($search));

        return collect(self::ACCESS_TYPES)
            ->filter(fn ($label) => str_contains(strtolower($label), $search))
            ->keys()
            ->map(fn ($id) => (int) $id)
            ->all();
    }

    private function hasEmailColumn(): bool
    {
        static $hasEmailColumn = null;

        if ($hasEmailColumn === null) {
            $hasEmailColumn = Schema::hasColumn('usermaster', 'email');
        }

        return $hasEmailColumn;
    }
}
