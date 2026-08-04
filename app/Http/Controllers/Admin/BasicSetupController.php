<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Support\AmountPrecision;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class BasicSetupController extends Controller
{
    public function index(): Response
    {
        $setup = DB::table('setup')->orderBy('setupid')->first();
        $salesCalendarCount = (int) DB::table('salescalender')->count();

        return Inertia::render('settings/BasicSetup', [
            'form' => [
                'setupid' => $setup->setupid ?? 1,
                'routesequenceplanflag' => $setup->routesequenceplanflag ?? 1,
                'previousdayuploadflag' => (int) ($setup->previousdayuploadflag ?? 0),
                'journeyplanflag' => $setup->journeyplanflag ?? 1,
                'allowpreparefilesafterupload' => (int) ($setup->allowpreparefilesafterupload ?? 0),
                'transferinventoryflag' => $setup->transferinventoryflag ?? 0,
                'restrictpreparefile' => (int) ($setup->restrictpreparefile ?? 0),
                'tabletsyncmode' => $setup->tabletsyncmode ?? null,
                'allowmorethanonesalesman' => (int) ($setup->allowmorethanonesalesman ?? 0),
                'importfilepath' => $setup->importfilepath ?? '',
                'synctimeinterval' => $setup->synctimeinterval ?? null,
                'decimalplaces' => AmountPrecision::normalize($setup->decimalplaces ?? 3),
            ],
            'routeSequenceOptions' => [
                ['id' => 1, 'label' => 'Generic Week'],
                ['id' => 2, 'label' => 'Sales Week'],
            ],
            'journeyPlanOptions' => [
                ['id' => 1, 'label' => 'Only One Day'],
                ['id' => 2, 'label' => 'All Days'],
            ],
            'transferInventoryOptions' => [
                ['id' => 0, 'label' => 'Disable'],
                ['id' => 1, 'label' => 'Only On Routes'],
                ['id' => 2, 'label' => 'Only On Depots'],
                ['id' => 3, 'label' => 'On Routes/Depot'],
            ],
            'tabletSyncModeOptions' => [
                ['id' => 1, 'label' => 'Online'],
                ['id' => 2, 'label' => 'Batch'],
                ['id' => 3, 'label' => 'Manual'],
            ],
            'salesCalendarCount' => $salesCalendarCount,
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'setupid' => ['required', 'integer'],
            'routesequenceplanflag' => ['required', 'integer', 'in:1,2'],
            'previousdayuploadflag' => ['nullable', 'boolean'],
            'journeyplanflag' => ['required', 'integer', 'in:1,2'],
            'allowpreparefilesafterupload' => ['nullable', 'boolean'],
            'transferinventoryflag' => ['required', 'integer', 'in:0,1,2,3'],
            'restrictpreparefile' => ['nullable', 'boolean'],
            'tabletsyncmode' => ['nullable', 'integer', 'in:1,2,3'],
            'allowmorethanonesalesman' => ['nullable', 'boolean'],
            'importfilepath' => ['nullable', 'string', 'max:1000'],
            'synctimeinterval' => ['nullable', 'integer', 'min:1', 'max:99999'],
            'decimalplaces' => ['required', 'integer', 'min:0', 'max:6'],
        ]);

        if (($validated['tabletsyncmode'] ?? null) !== 2) {
            $validated['synctimeinterval'] = null;
        }

        $payload = [
            'routesequenceplanflag' => $validated['routesequenceplanflag'],
            'previousdayuploadflag' => (int) ($validated['previousdayuploadflag'] ?? false),
            'journeyplanflag' => $validated['journeyplanflag'],
            'allowpreparefilesafterupload' => (int) ($validated['allowpreparefilesafterupload'] ?? false),
            'transferinventoryflag' => $validated['transferinventoryflag'],
            'restrictpreparefile' => (int) ($validated['restrictpreparefile'] ?? false),
            'tabletsyncmode' => $validated['tabletsyncmode'] ?? null,
            'allowmorethanonesalesman' => (int) ($validated['allowmorethanonesalesman'] ?? false),
            'importfilepath' => $validated['importfilepath'] ?? null,
            'synctimeinterval' => $validated['synctimeinterval'] ?? null,
            'decimalplaces' => $validated['decimalplaces'],
            'modifieddate' => now(),
        ];

        $existing = DB::table('setup')->where('setupid', $validated['setupid'])->first();

        if ($existing) {
            DB::table('setup')->where('setupid', $validated['setupid'])->update($payload);
        } else {
            DB::table('setup')->insert(array_merge([
                'setupid' => $validated['setupid'],
            ], $payload));
        }

        return back()->with('success', 'Basic setup updated.');
    }
}
