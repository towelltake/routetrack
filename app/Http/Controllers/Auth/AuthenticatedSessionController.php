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

        $companyCodes = UserAccessCode::query()
            ->where('username', $request->user()->username)
            ->pluck('cmpycode')
            ->filter(fn ($code) => filled($code))
            ->unique()
            ->values()
            ->all();

        $routes = DB::connection('sfa_mysql')->table('routemaster as route')
            ->leftJoin('company as company', 'company.cmpycode', '=', 'route.cmpycode')
            ->leftJoin('subareamaster as subarea', 'subarea.subareacode', '=', 'route.subareacode')
            ->leftJoin('areamaster as area', 'area.areacode', '=', 'subarea.areacode')
            ->leftJoin('depotmaster as depot', 'depot.depotcode', '=', 'area.depotcode')
            // Only explicit company grants authorize routes. An empty list denies
            // access; country and other geographic permission fields are ignored.
            ->whereIn('route.cmpycode', $companyCodes)
            ->get([
                'route.routecode',
                'route.cmpycode',
                'route.subareacode',
                'area.areacode',
                'depot.depotcode',
                'route.regionmstcode',
                'company.countrycode',
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

        return redirect()->intended(route('dashboard.index', absolute: false));
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
