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

        $subareaCodes = $rows->pluck('subareacode')->filter()->unique()->values();
        $hierarchy = DB::connection('sfa_mysql')->table('subareamaster as subarea')
            ->leftJoin('areamaster as area', 'area.areacode', '=', 'subarea.areacode')
            ->leftJoin('depotmaster as depot', 'depot.depotcode', '=', 'area.depotcode')
            ->leftJoin('regionmaster as region', 'region.regionmstcode', '=', 'depot.regionmstcode')
            ->leftJoin('country', 'country.countrycode', '=', 'region.countrycode')
            ->whereIn('subarea.subareacode', $subareaCodes)
            ->get([
                'subarea.subareacode',
                'area.areacode',
                'depot.depotcode',
                'region.regionmstcode',
                'country.countrycode',
                'country.cmpycode',
            ]);

        $accessType = (int) $request->user()->accesstypeid;
        $companyCodes = ($accessType === 1 ? $rows : $hierarchy)
            ->pluck('cmpycode')
            ->filter()
            ->unique()
            ->values()
            ->all();

        $request->session()->put('user_access', [
            'access_type' => $accessType,
            'company_codes' => $companyCodes,
            'country_codes' => $hierarchy->pluck('countrycode')->filter()->unique()->values()->all(),
            'region_codes' => $hierarchy->pluck('regionmstcode')->filter()->unique()->values()->all(),
            'depot_codes' => $hierarchy->pluck('depotcode')->filter()->unique()->values()->all(),
            'area_codes' => $hierarchy->pluck('areacode')->filter()->unique()->values()->all(),
            'subarea_codes' => $subareaCodes->all(),
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
