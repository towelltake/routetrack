<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Models\UserAccessCode;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class AuthenticatedSessionController extends Controller
{
    /**
     * Display the login view.
     */
    public function create(): Response
    {
        return Inertia::render('auth/Login', [
            'canResetPassword' => false,
            'status' => session('status'),
        ]);
    }

    /**
     * Handle an incoming authentication request.
     */
    public function store(LoginRequest $request): RedirectResponse
    {
        $request->authenticate();

        $request->session()->regenerate();

        $rows = UserAccessCode::query()
            ->where('username', $request->user()->username)
            ->get(['cmpycode', 'countrycode', 'regionmstcode', 'depotcode', 'areacode', 'subareacode']);

        $routes = DB::connection('sfa_mysql')->table('routemaster as route')
            ->leftJoin('subareamaster as subarea', 'subarea.subareacode', '=', 'route.subareacode')
            ->leftJoin('areamaster as area', 'area.areacode', '=', 'subarea.areacode')
            ->leftJoin('depotmaster as depot', 'depot.depotcode', '=', 'area.depotcode')
            ->leftJoin('regionmaster as region', 'region.regionmstcode', '=', 'depot.regionmstcode')
            ->leftJoin('country', 'country.countrycode', '=', 'region.countrycode')
            ->where(function ($query) use ($rows) {
                if ($rows->isEmpty()) {
                    $query->whereRaw('1 = 0');
                }

                foreach ($rows as $row) {
                    $permission = collect([
                        'subarea.subareacode' => $row->subareacode,
                        'area.areacode' => $row->areacode,
                        'depot.depotcode' => $row->depotcode,
                        'region.regionmstcode' => $row->regionmstcode,
                        'country.countrycode' => $row->countrycode,
                        'route.cmpycode' => $row->cmpycode,
                    ]);
                    $column = $permission->search(fn ($value) => filled($value));

                    if ($column !== false) {
                        $query->orWhere($column, $permission[$column]);
                    }
                }
            })
            ->get([
                'route.routecode',
                'route.cmpycode',
                'subarea.subareacode',
                'area.areacode',
                'depot.depotcode',
                'region.regionmstcode',
                'country.countrycode',
            ]);

        $accessType = (int) $request->user()->accesstypeid;

        $request->session()->put('user_access', [
            'access_type' => $accessType,
            'route_codes' => $routes->pluck('routecode')->filter()->unique()->values()->all(),
            'company_codes' => $routes->pluck('cmpycode')->filter()->unique()->values()->all(),
            'country_codes' => $routes->pluck('countrycode')->filter()->unique()->values()->all(),
            'region_codes' => $routes->pluck('regionmstcode')->filter()->unique()->values()->all(),
            'depot_codes' => $routes->pluck('depotcode')->filter()->unique()->values()->all(),
            'area_codes' => $routes->pluck('areacode')->filter()->unique()->values()->all(),
            'subarea_codes' => $routes->pluck('subareacode')->filter()->unique()->values()->all(),
        ]);

        return redirect()->intended(route('customer-location.index', absolute: false));
    }

    /**
     * Destroy an authenticated session.
     */
    public function destroy(Request $request): RedirectResponse
    {
        Auth::guard('web')->logout();

        $request->session()->invalidate();

        $request->session()->regenerateToken();

        return redirect()->route('login');
    }
}
