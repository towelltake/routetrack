<?php

namespace App\Http\Controllers\Basic;

use App\Http\Controllers\Controller;
use App\Models\Reason\BadReturnReason;
use App\Models\Reason\ExpenseReason;
use App\Models\Reason\FocReason;
use App\Models\Reason\GoodReturnReason;
use App\Models\Reason\NonServiceReason;
use App\Models\Reason\VoidReason;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class ReasonController extends Controller
{
    public function index(): Response
    {
        $type = request('type', 'goodreturn');
        $search = request('search');
        $perPage = (int) request('per_page', 10);
        $perPage = in_array($perPage, [10, 25, 50, 100], true) ? $perPage : 10;

        $config = $this->reasonConfig($type);
        $modelClass = $config['model'];
        $primaryKey = $config['primary_key'];
        $altField = $config['alternate'];
        $descField = $config['description'];
        $arbField = $config['arabic'];

        $reasons = $modelClass::query()
            ->when($search, function ($query, $searchTerm) use ($altField, $descField, $arbField) {
                $query->where(function ($inner) use ($searchTerm, $altField, $descField, $arbField) {
                    $inner->where($descField, 'like', '%' . $searchTerm . '%')
                        ->orWhere($arbField, 'like', '%' . $searchTerm . '%')
                        ->orWhere($altField, 'like', '%' . $searchTerm . '%');
                });
            })
            ->orderBy($primaryKey)
            ->paginate($perPage)
            ->through(function ($record) use ($type, $primaryKey, $altField, $descField, $arbField) {
                return [
                    'id' => $record->{$primaryKey},
                    'code' => $record->{$primaryKey},
                    'alternatecode' => $record->{$altField},
                    'description' => $record->{$descField},
                    'arbdescription' => $record->{$arbField},
                    'type' => $type,
                    'createddate' => $record->cdat,
                    'activestatus' => $record->activestatus ?? 1,
                ];
            })
            ->withQueryString();

        return Inertia::render('basic/reason/Index', [
            'reasons' => $reasons,
            'filters' => [
                'type' => $type,
                'search' => $search,
                'per_page' => $perPage,
            ],
            'types' => $this->reasonTypes(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request);
        $config = $this->reasonConfig($data['type']);
        $payload = $this->mapPayload($data, $config);
        $payload['created'] = auth()->user()?->name;
        $payload['cdat'] = now();
        $payload['modified'] = auth()->user()?->name;
        $payload['mdat'] = now();

        $modelClass = $config['model'];
        $modelClass::create($payload);

        return back()->with('success', 'Reason created.');
    }

    public function update(Request $request, string $reason): RedirectResponse
    {
        $data = $this->validated($request, $reason);
        $config = $this->reasonConfig($data['type']);
        $modelClass = $config['model'];
        $record = $modelClass::findOrFail($reason);

        $payload = $this->mapPayload($data, $config);
        $payload['modified'] = auth()->user()?->name;
        $payload['mdat'] = now();

        $record->update($payload);

        return back()->with('success', 'Reason updated.');
    }

    public function destroy(Request $request, string $reason): RedirectResponse
    {
        $type = (string) $request->query('type', 'goodreturn');
        $config = $this->reasonConfig($type);
        $modelClass = $config['model'];
        $record = $modelClass::findOrFail($reason);

        try {
            $record->delete();
        } catch (\Throwable $e) {
            return back()->with('error', 'Cannot delete: record is in use.');
        }

        return back()->with('success', 'Reason deleted.');
    }

    private function reasonTypes(): array
    {
        return [
            'badreturn' => 'Bad Return',
            'expense' => 'Expenses',
            'foc' => 'Free',
            'goodreturn' => 'Good Return',
            'nonservice' => 'Non Service',
            'void' => 'Void/Cancel',
        ];
    }

    private function reasonConfig(string $type): array
    {
        $map = [
            'badreturn' => [
                'model' => BadReturnReason::class,
                'primary_key' => 'code',
                'alternate' => 'alternatecode',
                'description' => 'description',
                'arabic' => 'arbdescription',
                'requires_arabic' => false,
            ],
            'expense' => [
                'model' => ExpenseReason::class,
                'primary_key' => 'code',
                'alternate' => 'alternatecode',
                'description' => 'description',
                'arabic' => 'arbdescription',
                'requires_arabic' => true,
            ],
            'foc' => [
                'model' => FocReason::class,
                'primary_key' => 'reason_code',
                'alternate' => 'alternatereasoncode',
                'description' => 'reason_desc',
                'arabic' => 'reason_arb_desc',
                'requires_arabic' => false,
            ],
            'goodreturn' => [
                'model' => GoodReturnReason::class,
                'primary_key' => 'code',
                'alternate' => 'alternatecode',
                'description' => 'description',
                'arabic' => 'arbdescription',
                'requires_arabic' => false,
            ],
            'nonservice' => [
                'model' => NonServiceReason::class,
                'primary_key' => 'code',
                'alternate' => 'alternatecode',
                'description' => 'description',
                'arabic' => 'arbdescription',
                'requires_arabic' => false,
            ],
            'void' => [
                'model' => VoidReason::class,
                'primary_key' => 'code',
                'alternate' => 'alternatecode',
                'description' => 'description',
                'arabic' => 'arbdescription',
                'requires_arabic' => false,
            ],
        ];

        return $map[$type] ?? $map['goodreturn'];
    }

    private function validated(Request $request, ?string $id = null): array
    {
        $type = (string) $request->input('type', 'goodreturn');
        $config = $this->reasonConfig($type);
        $model = new $config['model']();

        $arabicRule = $config['requires_arabic'] ? ['required', 'string', 'max:100'] : ['nullable', 'string', 'max:100'];

        return $request->validate([
            'type' => ['required', Rule::in(array_keys($this->reasonTypes()))],
            'alternatecode' => [
                'nullable',
                'string',
                'max:50',
                Rule::unique($model->getTable(), $config['alternate'])
                    ->ignore($id, $config['primary_key']),
            ],
            'description' => ['required', 'string', 'max:50'],
            'arbdescription' => $arabicRule,
        ]);
    }

    private function mapPayload(array $data, array $config): array
    {
        $payload = [
            $config['alternate'] => $data['alternatecode'] ?? null,
            $config['description'] => $data['description'],
            $config['arabic'] => $data['arbdescription'] ?? null,
        ];

        if ($config['model'] === ExpenseReason::class) {
            $payload['allowliterentry'] = 0;
        }

        if ($config['model'] === GoodReturnReason::class) {
            $payload['activestatus'] = 1;
        }

        return $payload;
    }
}
