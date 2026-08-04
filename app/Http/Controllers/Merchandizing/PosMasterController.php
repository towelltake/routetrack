<?php

namespace App\Http\Controllers\Merchandizing;

use App\Http\Controllers\Controller;
use App\Models\PosMaster;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class PosMasterController extends Controller
{
    public function index(): Response
    {
        $search = request('search');
        $perPage = (int) request('per_page', 10);
        $perPage = in_array($perPage, [10, 25, 50, 100], true) ? $perPage : 10;

        if ($this->hasTable()) {
            $records = PosMaster::query()
                ->when($search, function ($query, $searchTerm) {
                    $query->where(function ($inner) use ($searchTerm) {
                        $inner->where('itemcode', 'like', '%' . $searchTerm . '%')
                            ->orWhere('alternatecode', 'like', '%' . $searchTerm . '%')
                            ->orWhere('itemdescription', 'like', '%' . $searchTerm . '%')
                            ->orWhere('arbitemdescription', 'like', '%' . $searchTerm . '%');
                    });
                })
                ->orderBy('itemcode')
                ->paginate($perPage)
                ->withQueryString()
                ->through(fn (PosMaster $record) => $this->transformRow($record));
        } else {
            $records = new LengthAwarePaginator([], 0, $perPage, 1, [
                'path' => request()->url(),
                'query' => request()->query(),
            ]);
        }

        return Inertia::render('merchandizing/pos-master/Index', [
            'available' => $this->hasTable(),
            'filters' => [
                'search' => $search,
                'per_page' => $perPage,
            ],
            'records' => $records,
            'formMeta' => $this->formMeta(),
            'initialPosData' => $this->emptyRecordData(),
        ]);
    }

    public function create(): Response
    {
        abort_unless($this->hasTable(), 404);

        return Inertia::render('merchandizing/pos-master/FormPage', [
            'mode' => 'create',
            'formMeta' => $this->formMeta(),
            'posData' => [
                'itemcode' => $this->nextItemCode(),
                'alternatecode' => '',
                'itemdescription' => '',
                'arbitemdescription' => '',
                'itemvalue' => '0.0000',
                'inventorytype' => 0,
                'activestatus' => 1,
            ],
        ]);
    }

    public function show(PosMaster $posMaster): Response
    {
        abort_unless($this->hasTable(), 404);

        return Inertia::render('merchandizing/pos-master/FormPage', [
            'mode' => 'view',
            'formMeta' => $this->formMeta(),
            'posData' => $this->recordData($posMaster),
        ]);
    }

    public function edit(PosMaster $posMaster): Response
    {
        abort_unless($this->hasTable(), 404);

        return Inertia::render('merchandizing/pos-master/FormPage', [
            'mode' => 'edit',
            'formMeta' => $this->formMeta(),
            'posData' => $this->recordData($posMaster),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        abort_unless($this->hasTable(), 404);

        $data = $this->validatedData($request);
        $username = auth()->user()?->username ?? auth()->user()?->name ?? 'system';

        $record = PosMaster::query()->create([
            ...$data,
            'created' => $username,
            'cdat' => now(),
            'modified' => $username,
            'mdat' => now(),
        ]);

        return redirect("/merchandizing/pos-master/{$record->itemcode}/edit")->with('success', 'POS master created.');
    }

    public function update(Request $request, PosMaster $posMaster): RedirectResponse
    {
        abort_unless($this->hasTable(), 404);

        $data = $this->validatedData($request);
        $username = auth()->user()?->username ?? auth()->user()?->name ?? 'system';

        $posMaster->update([
            ...$data,
            'modified' => $username,
            'mdat' => now(),
        ]);

        return redirect("/merchandizing/pos-master/{$posMaster->itemcode}/edit")->with('success', 'POS master updated.');
    }

    public function destroy(PosMaster $posMaster): RedirectResponse
    {
        abort_unless($this->hasTable(), 404);

        $posMaster->delete();

        return back()->with('success', 'POS master deleted.');
    }

    private function validatedData(Request $request): array
    {
        $data = $request->validate([
            'alternatecode' => ['nullable', 'string', 'max:50'],
            'itemdescription' => ['required', 'string', 'max:50'],
            'arbitemdescription' => ['nullable', 'string', 'max:50'],
            'itemvalue' => ['nullable', 'numeric', 'min:0'],
            'inventorytype' => ['required', 'integer', Rule::in([0, 1])],
            'activestatus' => ['required', 'integer', Rule::in([0, 1])],
        ]);

        $data['alternatecode'] = $data['alternatecode'] === '' ? null : $data['alternatecode'];
        $data['arbitemdescription'] = $data['arbitemdescription'] === '' ? null : $data['arbitemdescription'];
        $data['itemvalue'] = number_format((float) ($data['itemvalue'] ?? 0), 4, '.', '');

        return $data;
    }

    private function recordData(PosMaster $record): array
    {
        return [
            'itemcode' => (int) $record->itemcode,
            'alternatecode' => $record->alternatecode ?? '',
            'itemdescription' => $record->itemdescription ?? '',
            'arbitemdescription' => $record->arbitemdescription ?? '',
            'itemvalue' => number_format((float) ($record->itemvalue ?? 0), 4, '.', ''),
            'inventorytype' => (int) ($record->inventorytype ?? 0),
            'activestatus' => (int) ($record->activestatus ?? 1),
        ];
    }

    private function transformRow(PosMaster $record): array
    {
        return [
            'itemcode' => (int) $record->itemcode,
            'alternatecode' => $record->alternatecode ?? '',
            'itemdescription' => $record->itemdescription ?? '',
            'arbitemdescription' => $record->arbitemdescription ?? '',
            'itemvalue' => number_format((float) ($record->itemvalue ?? 0), 4, '.', ''),
            'inventorytype' => (int) ($record->inventorytype ?? 0),
            'activestatus' => (int) ($record->activestatus ?? 1),
        ];
    }

    private function emptyRecordData(): array
    {
        return [
            'itemcode' => $this->nextItemCode(),
            'alternatecode' => '',
            'itemdescription' => '',
            'arbitemdescription' => '',
            'itemvalue' => '0.0000',
            'inventorytype' => 0,
            'activestatus' => 1,
        ];
    }

    private function nextItemCode(): int
    {
        return ((int) PosMaster::query()->max('itemcode')) + 1;
    }

    private function hasTable(): bool
    {
        return Schema::hasTable('posmaster');
    }

    private function formMeta(): array
    {
        return [
            'indexUrl' => '/merchandizing/pos-master',
            'baseUrl' => '/merchandizing/pos-master',
            'subtitle' => 'Manage POS definitions, values, and inventory behavior',
            'inventoryTypeOptions' => [
                ['id' => 0, 'label' => 'Value'],
                ['id' => 1, 'label' => 'Inventory'],
            ],
        ];
    }
}
