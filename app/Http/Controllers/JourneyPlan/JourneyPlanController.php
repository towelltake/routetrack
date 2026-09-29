<?php

namespace App\Http\Controllers\JourneyPlan;

use App\Http\Controllers\Controller;
use App\Models\RouteMaster;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class JourneyPlanController extends Controller
{
    private function accessibleRoutes()
    {
        return RouteMaster::on('sfa_mysql')
            ->whereIn('routecode', session('user_access.route_codes', []))
            ->whereIn('cmpycode', session('user_access.company_codes', []))
            ->whereIn('subareacode', session('user_access.subarea_codes', []));
    }

    public function index()
    {
        return Inertia::render('journeyplan/Index');
    }

    public function routes()
    {
        return response()->json($this->accessibleRoutes()->orderBy('routename')
            ->get(['routecode', 'routename', 'cmpycode', 'salesmancode']));
    }

    public function plan(Request $request)
    {
        $data = $request->validate(['routecode' => ['required', 'integer']]);
        $route = $this->accessibleRoutes()->where('routecode', $data['routecode'])->firstOrFail();
        $rows = DB::connection('sfa_mysql')->table('routesequence as rs')
            ->leftJoin('customermaster as cm', 'cm.customercode', '=', 'rs.customercode')
            ->where('rs.routecode', $route->routecode)
            ->orderBy('rs.rp32weeknumber')->orderBy('rs.customercode')
            ->get(['rs.*', 'cm.customername', 'cm.alternatecode', 'cm.fixedlatitude',
                'cm.fixedlongitude', 'cm.callfrequency']);

        return response()->json(['route' => $route->only(['routecode', 'routename', 'salesmancode']), 'rows' => $rows]);
    }
}
