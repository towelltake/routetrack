<?php

namespace App\Http\Controllers\Merchandizing;

use App\Http\Controllers\Controller;
use App\Models\PosInstruction;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Schema;
use Inertia\Inertia;
use Inertia\Response;

class PosInstructionController extends Controller
{
    public function index(): Response
    {
        $search = request('search');
        $perPage = (int) request('per_page', 10);
        $perPage = in_array($perPage, [10, 25, 50, 100], true) ? $perPage : 10;

        if ($this->hasTable()) {
            $records = PosInstruction::query()
                ->when($search, function ($query, $searchTerm) {
                    $query->where(function ($inner) use ($searchTerm) {
                        $inner->where('posinstructioncode', 'like', '%' . $searchTerm . '%')
                            ->orWhere('alternatecode', 'like', '%' . $searchTerm . '%')
                            ->orWhere('posinstructionname', 'like', '%' . $searchTerm . '%')
                            ->orWhere('arbposinstructionname', 'like', '%' . $searchTerm . '%');
                    });
                })
                ->orderBy('posinstructioncode')
                ->paginate($perPage)
                ->withQueryString()
                ->through(fn (PosInstruction $record) => $this->transformRow($record));
        } else {
            $records = new LengthAwarePaginator([], 0, $perPage, 1, [
                'path' => request()->url(),
                'query' => request()->query(),
            ]);
        }

        return Inertia::render('merchandizing/pos-instruction/Index', [
            'available' => $this->hasTable(),
            'filters' => [
                'search' => $search,
                'per_page' => $perPage,
            ],
            'records' => $records,
            'formMeta' => $this->formMeta(),
            'initialInstructionData' => $this->emptyRecordData(),
        ]);
    }

    public function create(): Response
    {
        abort_unless($this->hasTable(), 404);

        return Inertia::render('merchandizing/pos-instruction/FormPage', [
            'mode' => 'create',
            'formMeta' => $this->formMeta(),
            'instructionData' => [
                'posinstructioncode' => $this->nextCode(),
                'alternatecode' => '',
                'posinstructionname' => '',
                'arbposinstructionname' => '',
                'createddate' => '',
            ],
        ]);
    }

    public function show(PosInstruction $posInstruction): Response
    {
        abort_unless($this->hasTable(), 404);

        return Inertia::render('merchandizing/pos-instruction/FormPage', [
            'mode' => 'view',
            'formMeta' => $this->formMeta(),
            'instructionData' => $this->recordData($posInstruction),
        ]);
    }

    public function edit(PosInstruction $posInstruction): Response
    {
        abort_unless($this->hasTable(), 404);

        return Inertia::render('merchandizing/pos-instruction/FormPage', [
            'mode' => 'edit',
            'formMeta' => $this->formMeta(),
            'instructionData' => $this->recordData($posInstruction),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        abort_unless($this->hasTable(), 404);

        $data = $this->validatedData($request);
        $username = auth()->user()?->username ?? auth()->user()?->name ?? 'system';

        $record = PosInstruction::query()->create([
            ...$data,
            'created' => $username,
            'cdat' => now(),
            'modified' => $username,
            'mdat' => now(),
        ]);

        return redirect("/merchandizing/pos-instruction/{$record->posinstructioncode}/edit")->with('success', 'POS instruction created.');
    }

    public function update(Request $request, PosInstruction $posInstruction): RedirectResponse
    {
        abort_unless($this->hasTable(), 404);

        $data = $this->validatedData($request);
        $username = auth()->user()?->username ?? auth()->user()?->name ?? 'system';

        $posInstruction->update([
            ...$data,
            'modified' => $username,
            'mdat' => now(),
        ]);

        return redirect("/merchandizing/pos-instruction/{$posInstruction->posinstructioncode}/edit")->with('success', 'POS instruction updated.');
    }

    public function destroy(PosInstruction $posInstruction): RedirectResponse
    {
        abort_unless($this->hasTable(), 404);

        $posInstruction->delete();

        return back()->with('success', 'POS instruction deleted.');
    }

    private function validatedData(Request $request): array
    {
        $data = $request->validate([
            'alternatecode' => ['nullable', 'string', 'max:50'],
            'posinstructionname' => ['required', 'string', 'max:50'],
            'arbposinstructionname' => ['nullable', 'string', 'max:50'],
        ]);

        $data['alternatecode'] = $data['alternatecode'] === '' ? null : $data['alternatecode'];
        $data['arbposinstructionname'] = $data['arbposinstructionname'] === '' ? null : $data['arbposinstructionname'];

        return $data;
    }

    private function recordData(PosInstruction $record): array
    {
        return [
            'posinstructioncode' => (int) $record->posinstructioncode,
            'alternatecode' => $record->alternatecode ?? '',
            'posinstructionname' => $record->posinstructionname ?? '',
            'arbposinstructionname' => $record->arbposinstructionname ?? '',
            'createddate' => $record->cdat ? $record->cdat->format('d-m-Y') : '',
        ];
    }

    private function transformRow(PosInstruction $record): array
    {
        return [
            'posinstructioncode' => (int) $record->posinstructioncode,
            'alternatecode' => $record->alternatecode ?? '',
            'posinstructionname' => $record->posinstructionname ?? '',
            'arbposinstructionname' => $record->arbposinstructionname ?? '',
            'createddate' => $record->cdat ? $record->cdat->format('d-m-Y') : '',
        ];
    }

    private function emptyRecordData(): array
    {
        return [
            'posinstructioncode' => $this->nextCode(),
            'alternatecode' => '',
            'posinstructionname' => '',
            'arbposinstructionname' => '',
            'createddate' => '',
        ];
    }

    private function nextCode(): int
    {
        return ((int) PosInstruction::query()->max('posinstructioncode')) + 1;
    }

    private function hasTable(): bool
    {
        return Schema::hasTable('posinstructions');
    }

    private function formMeta(): array
    {
        return [
            'indexUrl' => '/merchandizing/pos-instruction',
            'baseUrl' => '/merchandizing/pos-instruction',
            'subtitle' => 'Manage POS instruction labels and display names',
        ];
    }
}
