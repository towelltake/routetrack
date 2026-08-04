<?php

namespace App\Http\Controllers\Scheme;

use App\Http\Controllers\Controller;
use App\Models\DepotMaster;
use App\Models\ItemMaster;
use App\Models\Supervisor;
use App\Models\SupervisorFoc;
use App\Models\SupervisorFocBalance;
use App\Models\SupervisorFocDetail;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class SupervisorFreeContractController extends Controller
{
    public function index(): Response
    {
        $search  = request('search');
        $perPage = (int) request('per_page', 10);
        $perPage = in_array($perPage, [10, 25, 50, 100], true) ? $perPage : 10;
        $contractAlias = DB::getTablePrefix() . 'cf';

        if ($this->hasTables()) {
            $contracts = SupervisorFoc::query()
                ->from('supervisor_foc as cf')
                ->leftJoin('supervisor as sv', 'sv.supervisorcode', '=', 'cf.supervisorcode')
                ->when($search, function ($q, $s) {
                    $q->where(function ($inner) use ($s) {
                        $inner->where('cf.contractid', 'like', "%{$s}%")
                              ->orWhere('cf.supervisorcode', 'like', "%{$s}%")
                              ->orWhere('sv.supervisorname', 'like', "%{$s}%")
                              ->orWhere('cf.remarks', 'like', "%{$s}%");
                    });
                })
                ->orderBy('cf.contractid')
                ->paginate($perPage, [
                    'cf.contractid',
                    'cf.supervisorcode',
                    'sv.supervisorname',
                    'cf.startdate',
                    'cf.enddate',
                    'cf.active',
                    'cf.remarks',
                    DB::raw("CASE
                        WHEN CURDATE() < DATE({$contractAlias}.startdate) THEN 'Pending'
                        WHEN DATE({$contractAlias}.startdate) <= CURDATE() AND DATE({$contractAlias}.enddate) >= CURDATE() THEN 'Running'
                        WHEN CURDATE() > DATE({$contractAlias}.enddate) THEN 'Ended'
                        ELSE '-'
                    END AS contract_status"),
                ])
                ->withQueryString();
        } else {
            $contracts = new LengthAwarePaginator([], 0, $perPage, 1, [
                'path'  => request()->url(),
                'query' => request()->query(),
            ]);
        }

        return Inertia::render('scheme/supervisor-free-contract/Index', [
            'available' => $this->hasTables(),
            'filters'   => ['search' => $search, 'per_page' => $perPage],
            'contracts' => $contracts,
        ]);
    }

    public function create(): Response
    {
        abort_unless($this->hasTables(), 404);

        return Inertia::render('scheme/supervisor-free-contract/FormPage', [
            'mode'         => 'create',
            'formMeta'     => $this->formMeta(),
            'contractData' => [
                'contractid'     => null,
                'supervisorcode' => '',
                'depotcode'      => '',
                'startdate'      => '',
                'enddate'        => '',
                'active'         => 1,
                'remarks'        => '',
                'items'          => [],
            ],
        ]);
    }

    public function show(int $supervisorFreeContract): Response
    {
        abort_unless($this->hasTables(), 404);

        return Inertia::render('scheme/supervisor-free-contract/FormPage', [
            'mode'         => 'view',
            'formMeta'     => $this->formMeta(),
            'contractData' => $this->contractData($supervisorFreeContract),
        ]);
    }

    public function edit(int $supervisorFreeContract): Response
    {
        abort_unless($this->hasTables(), 404);

        return Inertia::render('scheme/supervisor-free-contract/FormPage', [
            'mode'         => 'edit',
            'formMeta'     => $this->formMeta(),
            'contractData' => $this->contractData($supervisorFreeContract),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        abort_unless($this->hasTables(), 404);

        $data = $request->validate([
            'supervisorcode' => ['required', 'integer', Rule::exists('supervisor', 'supervisorcode')],
            'depotcode'      => ['nullable', 'integer', Rule::exists('depotmaster', 'depotcode')],
            'startdate'      => ['required', 'date'],
            'enddate'        => ['required', 'date', 'after_or_equal:startdate'],
            'active'         => ['required', 'integer', Rule::in([0, 1])],
            'remarks'        => ['nullable', 'string', 'max:200'],
        ]);

        $duplicate = SupervisorFoc::query()
            ->where('supervisorcode', $data['supervisorcode'])
            ->where(function ($q) use ($data) {
                $q->whereBetween('startdate', [$data['startdate'], $data['enddate']])
                  ->orWhereBetween('enddate', [$data['startdate'], $data['enddate']]);
            })
            ->exists();

        if ($duplicate) {
            return back()->withErrors(['supervisorcode' => 'This supervisor already has a free contract in the given date range.'])->withInput();
        }

        $contract = SupervisorFoc::query()->create([
            'supervisorcode' => $data['supervisorcode'],
            'depotcode'      => $data['depotcode'] ?: null,
            'creationdate'   => $data['startdate'],
            'startdate'      => $data['startdate'],
            'enddate'        => $data['enddate'],
            'active'         => $data['active'],
            'remarks'        => $data['remarks'] ?: null,
        ]);

        return redirect("/scheme/supervisor-free-contract/{$contract->contractid}/edit")
            ->with('success', 'Contract created. You can now add items.');
    }

    public function update(Request $request, int $supervisorFreeContract): RedirectResponse
    {
        abort_unless($this->hasTables(), 404);

        $contract = SupervisorFoc::query()->findOrFail($supervisorFreeContract);

        $data = $request->validate([
            'startdate' => ['required', 'date'],
            'enddate'   => ['required', 'date', 'after_or_equal:startdate'],
            'active'    => ['required', 'integer', Rule::in([0, 1])],
            'remarks'   => ['nullable', 'string', 'max:200'],
        ]);

        $contract->update([
            'creationdate' => $data['startdate'],
            'startdate'    => $data['startdate'],
            'enddate'      => $data['enddate'],
            'active'       => $data['active'],
            'remarks'      => $data['remarks'] ?: null,
        ]);

        return back()->with('success', 'Contract updated.');
    }

    public function destroy(int $supervisorFreeContract): RedirectResponse
    {
        abort_unless($this->hasTables(), 404);

        $inUse = SupervisorFocBalance::query()
            ->where('contractid', $supervisorFreeContract)
            ->whereColumn('originalqty', '!=', 'balanceqty')
            ->exists();

        if ($inUse) {
            return back()->with('error', 'Cannot delete: this contract has already been used.');
        }

        DB::transaction(function () use ($supervisorFreeContract) {
            SupervisorFocBalance::query()->where('contractid', $supervisorFreeContract)->delete();
            SupervisorFocDetail::query()->where('contractid', $supervisorFreeContract)->delete();
            SupervisorFoc::query()->where('contractid', $supervisorFreeContract)->delete();
        });

        return redirect('/scheme/supervisor-free-contract')->with('success', 'Contract deleted.');
    }

    public function addItem(Request $request, int $supervisorFreeContract): RedirectResponse
    {
        abort_unless($this->hasTables(), 404);

        $contract = SupervisorFoc::query()->findOrFail($supervisorFreeContract);

        $data = $request->validate([
            'itemcode'     => ['required', 'integer', Rule::exists('itemmaster', 'actualitemcode')],
            'freequantity' => ['required', 'integer', 'min:1'],
        ]);

        $exists = SupervisorFocDetail::query()
            ->where('contractid', $supervisorFreeContract)
            ->where('itemcode', $data['itemcode'])
            ->exists();

        if ($exists) {
            return back()->with('error', 'This item is already added to the contract.');
        }

        DB::transaction(function () use ($contract, $data) {
            SupervisorFocDetail::query()->create([
                'contractid'    => $contract->contractid,
                'supervisorcode'=> $contract->supervisorcode,
                'itemcode'      => $data['itemcode'],
                'freequantity'  => $data['freequantity'],
                'remarks'       => '',
                'editdate'      => now()->toDateString(),
            ]);

            SupervisorFocBalance::query()->create([
                'contractid'    => $contract->contractid,
                'supervisorcode'=> $contract->supervisorcode,
                'itemcode'      => $data['itemcode'],
                'originalqty'   => $data['freequantity'],
                'balanceqty'    => $data['freequantity'],
                'startdate'     => now()->toDateString(),
            ]);
        });

        return back()->with('success', 'Item added.');
    }

    public function updateItem(Request $request, int $supervisorFreeContract, int $itemcode): RedirectResponse
    {
        abort_unless($this->hasTables(), 404);

        $data = $request->validate([
            'freequantity' => ['required', 'integer', 'min:1'],
        ]);

        $balance = SupervisorFocBalance::query()
            ->where('contractid', $supervisorFreeContract)
            ->where('itemcode', $itemcode)
            ->first();

        if (!$balance) {
            return back()->with('error', 'Item not found.');
        }

        $usedQty = $balance->originalqty - $balance->balanceqty;

        if ($data['freequantity'] < $usedQty) {
            return back()->with('error', "Free quantity cannot be less than the already given quantity ({$usedQty}).");
        }

        DB::transaction(function () use ($supervisorFreeContract, $itemcode, $data, $usedQty) {
            SupervisorFocDetail::query()
                ->where('contractid', $supervisorFreeContract)
                ->where('itemcode', $itemcode)
                ->update([
                    'freequantity' => $data['freequantity'],
                    'editdate'     => now()->toDateString(),
                ]);

            SupervisorFocBalance::query()
                ->where('contractid', $supervisorFreeContract)
                ->where('itemcode', $itemcode)
                ->update([
                    'originalqty' => $data['freequantity'],
                    'balanceqty'  => $data['freequantity'] - $usedQty,
                ]);
        });

        return back()->with('success', 'Item quantity updated.');
    }

    public function removeItem(int $supervisorFreeContract, int $itemcode): RedirectResponse
    {
        abort_unless($this->hasTables(), 404);

        $balance = SupervisorFocBalance::query()
            ->where('contractid', $supervisorFreeContract)
            ->where('itemcode', $itemcode)
            ->first();

        if (!$balance) {
            return back()->with('error', 'Item not found.');
        }

        if ($balance->originalqty !== $balance->balanceqty) {
            return back()->with('error', 'Cannot delete: this item has already been issued.');
        }

        DB::transaction(function () use ($supervisorFreeContract, $itemcode) {
            SupervisorFocDetail::query()
                ->where('contractid', $supervisorFreeContract)
                ->where('itemcode', $itemcode)
                ->delete();

            SupervisorFocBalance::query()
                ->where('contractid', $supervisorFreeContract)
                ->where('itemcode', $itemcode)
                ->delete();
        });

        return back()->with('success', 'Item removed.');
    }

    private function contractData(int $contractId): array
    {
        $contract = SupervisorFoc::query()->findOrFail($contractId);
        $itemAlias = DB::getTablePrefix() . 'im';
        $balanceAlias = DB::getTablePrefix() . 'fb';

        $items = SupervisorFocDetail::query()
            ->from('supervisor_foc_detail as fd')
            ->leftJoin('itemmaster as im', 'im.actualitemcode', '=', 'fd.itemcode')
            ->leftJoin('supervisor_foc_balance as fb', function ($join) use ($contractId) {
                $join->on('fb.contractid', '=', 'fd.contractid')
                     ->on('fb.itemcode', '=', 'fd.itemcode');
            })
            ->where('fd.contractid', $contractId)
            ->orderBy('fd.itemcode')
            ->get([
                'fd.itemcode',
                'fd.freequantity',
                'im.itemshortdescription',
                'im.unitspercase',
                DB::raw("COALESCE(NULLIF({$itemAlias}.alternatecode, ''), {$itemAlias}.actualitemcode) as displaycode"),
                'fb.originalqty',
                'fb.balanceqty',
                DB::raw("({$balanceAlias}.originalqty - {$balanceAlias}.balanceqty) as usedqty"),
            ])
            ->map(fn ($row) => [
                'itemcode'          => (int) $row->itemcode,
                'displaycode'       => $row->displaycode,
                'itemshortdescription' => $row->itemshortdescription,
                'unitspercase'      => $row->unitspercase,
                'freequantity'      => (int) $row->freequantity,
                'originalqty'       => (int) $row->originalqty,
                'balanceqty'        => (int) $row->balanceqty,
                'usedqty'           => (int) $row->usedqty,
                'can_delete'        => (int) $row->originalqty === (int) $row->balanceqty,
            ])
            ->all();

        return [
            'contractid'     => (int) $contract->contractid,
            'supervisorcode' => (int) $contract->supervisorcode,
            'depotcode'      => $contract->depotcode ? (int) $contract->depotcode : null,
            'startdate'      => $contract->startdate,
            'enddate'        => $contract->enddate,
            'active'         => (int) $contract->active,
            'remarks'        => $contract->remarks ?? '',
            'items'          => $items,
        ];
    }

    private function hasTables(): bool
    {
        return Schema::hasTable('supervisor_foc')
            && Schema::hasTable('supervisor_foc_detail')
            && Schema::hasTable('supervisor_foc_balance');
    }

    private function formMeta(): array
    {
        $supervisors = Schema::hasTable('supervisor')
            ? Supervisor::query()->orderBy('supervisorname')
                ->get(['supervisorcode as id', DB::raw("CONCAT(supervisorcode, ' - ', supervisorname) as label")])
            : collect();

        $depots = Schema::hasTable('depotmaster')
            ? DepotMaster::query()->orderBy('depotname')
                ->get(['depotcode as id', DB::raw("CONCAT(depotcode, ' - ', depotname) as label")])
            : collect();

        $items = Schema::hasTable('itemmaster')
            ? ItemMaster::query()->where('activeitem', 1)->orderBy('itemshortdescription')
                ->get(['actualitemcode', 'alternatecode', 'itemshortdescription', 'unitspercase'])
                ->map(fn ($i) => [
                    'id'          => (int) $i->actualitemcode,
                    'label'       => trim(($i->alternatecode ?: $i->actualitemcode) . ' - ' . $i->itemshortdescription),
                    'unitspercase'=> $i->unitspercase,
                ])
            : collect();

        return [
            'indexUrl'    => '/scheme/supervisor-free-contract',
            'baseUrl'     => '/scheme/supervisor-free-contract',
            'subtitle'    => 'Manage supervisor free goods contracts and item allocations',
            'supervisors' => $supervisors,
            'depots'      => $depots,
            'itemOptions' => $items,
        ];
    }
}
