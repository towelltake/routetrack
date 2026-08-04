<?php

namespace App\Http\Controllers\CustomerLocation;

use App\Http\Controllers\Controller;
use App\Models\AreaMaster;
use App\Models\CompanyMaster;
use App\Models\CustomerMaster;
use App\Models\RouteMaster;
use App\Models\RouteSequence;
use App\Models\SubAreaMaster;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class CustomerLocationController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('customerlocation/Index');
    }

    public function companies(): JsonResponse
    {
        $routedCmpyCodes = RouteMaster::query()
            ->whereIn('routecode', RouteSequence::query()->distinct()->pluck('routecode'))
            ->distinct()
            ->pluck('cmpycode');

        $companies = CompanyMaster::query()
            ->whereIn('cmpycode', $routedCmpyCodes)
            ->orderBy('name')
            ->get(['cmpycode', 'name']);

        return response()->json($companies);
    }

    public function areas(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'companycode' => ['nullable', 'integer'],
        ]);

        $routedSubareaCodes = RouteMaster::query()
            ->whereIn('routecode', RouteSequence::query()->distinct()->pluck('routecode'))
            ->when($validated['companycode'] ?? null, fn ($query, $companycode) => $query->where('cmpycode', $companycode))
            ->pluck('subareacode');

        $areaCodes = SubAreaMaster::query()
            ->whereIn('subareacode', $routedSubareaCodes)
            ->distinct()
            ->pluck('areacode');

        $areas = AreaMaster::query()
            ->whereIn('areacode', $areaCodes)
            ->orderBy('areaname')
            ->get(['areacode', 'areaname']);

        return response()->json($areas);
    }

    public function subareas(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'areacode' => ['required', 'integer'],
            'companycode' => ['nullable', 'integer'],
        ]);

        $routedSubareaCodes = RouteMaster::query()
            ->whereIn('routecode', RouteSequence::query()->distinct()->pluck('routecode'))
            ->when($validated['companycode'] ?? null, fn ($query, $companycode) => $query->where('cmpycode', $companycode))
            ->pluck('subareacode');

        $subareas = SubAreaMaster::query()
            ->where('areacode', $validated['areacode'])
            ->whereIn('subareacode', $routedSubareaCodes)
            ->orderBy('subareaname')
            ->get(['subareacode', 'subareaname']);

        return response()->json($subareas);
    }

    public function locations(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'companycode' => ['nullable', 'integer'],
            'subareacode' => ['nullable', 'integer'],
        ]);

        $customerCodes = null;

        if (($validated['companycode'] ?? null) || ($validated['subareacode'] ?? null)) {
            $routeCodes = RouteMaster::query()
                ->whereIn('routecode', RouteSequence::query()->distinct()->pluck('routecode'))
                ->when($validated['companycode'] ?? null, fn ($query, $companycode) => $query->where('cmpycode', $companycode))
                ->when($validated['subareacode'] ?? null, fn ($query, $subareacode) => $query->where('subareacode', $subareacode))
                ->pluck('routecode');

            $customerCodes = RouteSequence::query()
                ->whereIn('routecode', $routeCodes)
                ->distinct()
                ->pluck('customercode');
        }

        $customers = CustomerMaster::query()
            ->when($customerCodes !== null, fn ($query) => $query->whereIn('customercode', $customerCodes))
            ->whereNotNull('fixedlatitude')
            ->whereNotNull('fixedlongitude')
            ->where('fixedlatitude', '!=', 0)
            ->where('fixedlongitude', '!=', 0)
            ->get(['customercode', 'customername', 'customeraddress1', 'customeraddress2', 'fixedlatitude', 'fixedlongitude'])
            ->map(fn (CustomerMaster $customer) => [
                'customercode' => $customer->customercode,
                'customername' => $customer->customername,
                'address' => trim($customer->customeraddress1.' '.$customer->customeraddress2),
                'lat' => (float) $customer->fixedlatitude,
                'lng' => (float) $customer->fixedlongitude,
            ]);

        return response()->json($customers);
    }
}
