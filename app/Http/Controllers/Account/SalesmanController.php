<?php

namespace App\Http\Controllers\Account;

use App\Http\Controllers\Controller;
use App\Models\AccountSalesman;
use App\Models\CompanyMaster;
use App\Models\SalesmanMessage;
use App\Services\AccessScopeService;
use App\Support\ExcelXmlWorkbook;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\Response as HttpResponse;

class SalesmanController extends Controller
{
    private const TYPE_OPTIONS = [
        ['id' => 1, 'label' => 'Salesman'],
        ['id' => 2, 'label' => 'Merchandizer'],
        ['id' => 3, 'label' => 'Supervisor'],
        ['id' => 4, 'label' => 'Helper'],
    ];

    public function index(): Response
    {
        $scope = app(AccessScopeService::class);
        $user = request()->user();
        $search = request('search');
        $perPage = (int) request('per_page', 10);
        $perPage = in_array($perPage, [10, 25, 50, 100], true) ? $perPage : 10;

        $salesmen = AccountSalesman::query()
            ->leftJoin('company', 'company.cmpycode', '=', 'salesman.parentcompany')
            ->leftJoin('salesmanmessages', 'salesmanmessages.messagekey', '=', 'salesman.messagekey')
            ->when($search, function ($query, $searchTerm) {
                $query->where(function ($inner) use ($searchTerm) {
                    $inner->where('salesman.salesmancode', 'like', '%' . $searchTerm . '%')
                        ->orWhere('salesman.alternatesalesmancode', 'like', '%' . $searchTerm . '%')
                        ->orWhere('salesman.salesmanname1', 'like', '%' . $searchTerm . '%')
                        ->orWhere('salesman.arbsalesmanname1', 'like', '%' . $searchTerm . '%')
                        ->orWhere('salesman.salesmanname2', 'like', '%' . $searchTerm . '%')
                        ->orWhere('salesman.username', 'like', '%' . $searchTerm . '%')
                        ->orWhere('company.name', 'like', '%' . $searchTerm . '%')
                        ->orWhere('salesmanmessages.messagedescription', 'like', '%' . $searchTerm . '%');
                });
            })
            ->tap(fn ($query) => $scope->scopeQuery($user, $query, 'company', 'salesman.parentcompany'))
            ->orderBy('salesman.salesmancode')
            ->select([
                'salesman.salesmancode',
                'salesman.alternatesalesmancode',
                'salesman.salesmanname1',
                'salesman.salesmanname2',
                'salesman.type',
                'salesman.activestatus',
                'salesman.username',
                'company.name as parentcompanyname',
                'salesmanmessages.messagedescription as messagedescription',
            ])
            ->paginate($perPage)
            ->through(function ($record) {
                $record->type_label = $this->typeLabel((int) $record->type);

                return $record;
            })
            ->withQueryString();

        return Inertia::render('account/salesman/Index', [
            'filters' => [
                'search' => $search,
                'per_page' => $perPage,
            ],
            'salesmen' => $salesmen,
        ]);
    }

    public function create(): Response
    {
        $props = $this->formProps();
        $props['salesmanData']['salesmancode'] = $this->nextSalesmanCode();

        return Inertia::render('account/salesman/Create', $props);
    }

    public function show(AccountSalesman $salesman): Response
    {
        abort_unless(app(AccessScopeService::class)->allows(request()->user(), 'company', $salesman->parentcompany), 403);

        return Inertia::render('account/salesman/View', $this->formProps($salesman));
    }

    public function edit(AccountSalesman $salesman): Response
    {
        abort_unless(app(AccessScopeService::class)->allows(request()->user(), 'company', $salesman->parentcompany), 403);

        return Inertia::render('account/salesman/Edit', $this->formProps($salesman));
    }

    public function store(Request $request): RedirectResponse
    {
        $payload = $this->validatedData($request);
        $username = auth()->user()?->username ?? auth()->user()?->name;
        $payload['created'] = $username;
        $payload['cdat'] = now();
        $payload['modified'] = $username;
        $payload['mdat'] = now();

        AccountSalesman::create($payload);

        return redirect()
            ->route('account.salesman.index')
            ->with('success', 'Salesman created.');
    }

    public function update(Request $request, AccountSalesman $salesman): RedirectResponse
    {
        abort_unless(app(AccessScopeService::class)->allows($request->user(), 'company', $salesman->parentcompany), 403);

        $payload = $this->validatedData($request, $salesman);
        $payload['modified'] = auth()->user()?->username ?? auth()->user()?->name;
        $payload['mdat'] = now();

        $salesman->update($payload);

        return redirect()
            ->route('account.salesman.index')
            ->with('success', 'Salesman updated.');
    }

    public function destroy(AccountSalesman $salesman): RedirectResponse
    {
        abort_unless(app(AccessScopeService::class)->allows(request()->user(), 'company', $salesman->parentcompany), 403);

        try {
            $salesman->delete();
        } catch (\Throwable $e) {
            return back()->with('error', 'Cannot delete: record is in use.');
        }

        return back()->with('success', 'Salesman deleted.');
    }

    public function downloadBulkImportTemplate(): HttpResponse
    {
        return ExcelXmlWorkbook::download(
            'account-salesman-bulk-import-template.xls',
            $this->bulkImportTemplateHeaders(),
            [],
            'AccountSalesman'
        );
    }

    public function bulkImport(Request $request): RedirectResponse
    {
        $request->validate([
            'file' => ['required', 'file', 'max:5120'],
        ]);

        try {
            $rows = ExcelXmlWorkbook::parseFile($request->file('file')->getRealPath());
        } catch (\RuntimeException $exception) {
            return back()->withErrors(['file' => $exception->getMessage()]);
        }

        if ($rows === []) {
            return back()->withErrors(['file' => 'The uploaded file does not contain any salesman rows.']);
        }

        $imported = 0;
        $username = auth()->user()?->username ?? auth()->user()?->name;

        DB::transaction(function () use ($rows, &$imported, $username) {
            foreach ($rows as $index => $row) {
                $payload = $this->mapBulkImportRow($row);

                try {
                    $validated = $this->validatePayload($payload, null, false);
                } catch (\Illuminate\Validation\ValidationException $exception) {
                    $messages = collect($exception->errors())->flatten()->implode(' ');

                    throw \Illuminate\Validation\ValidationException::withMessages([
                        'file' => 'Row ' . ($index + 2) . ': ' . $messages,
                    ]);
                }

                $validated['created'] = $username;
                $validated['cdat'] = now();
                $validated['modified'] = $username;
                $validated['mdat'] = now();

                AccountSalesman::create($validated);
                $imported++;
            }
        });

        return redirect()
            ->route('account.salesman.index')
            ->with('success', $imported . ' salesman record(s) imported successfully.');
    }

    private function formProps(?AccountSalesman $salesman = null): array
    {
        return [
            'salesmanData' => $this->salesmanFormData($salesman),
            'optionSets' => [
                'statusOptions' => [
                    ['id' => 1, 'label' => 'Active'],
                    ['id' => 0, 'label' => 'Inactive'],
                ],
                'typeOptions' => self::TYPE_OPTIONS,
                'parentCompanies' => app(AccessScopeService::class)->scopeQuery(request()->user(), CompanyMaster::query(), 'company', 'cmpycode')
                    ->where('activestatus', 1)
                    ->orderBy('name')
                    ->get(['cmpycode as id', 'name as label']),
                'messageOptions' => SalesmanMessage::query()
                    ->orderBy('messagedescription')
                    ->get(['messagekey as id', 'messagedescription as label']),
            ],
        ];
    }

    private function salesmanFormData(?AccountSalesman $salesman): array
    {
        $record = $salesman?->toArray() ?? [];

        return array_merge($this->defaultSalesmanData(), array_intersect_key(
            $record,
            array_flip(array_keys($this->defaultSalesmanData()))
        ));
    }

    private function defaultSalesmanData(): array
    {
        return [
            'salesmancode' => null,
            'alternatesalesmancode' => '',
            'salesmanname1' => '',
            'salesmanname2' => '',
            'arbsalesmanname1' => '',
            'messagekey' => null,
            'type' => 1,
            'parentcompany' => null,
            'username' => '',
            'userpassword' => '',
            'activestatus' => 1,
            'created' => null,
            'cdat' => null,
            'modified' => null,
            'mdat' => null,
        ];
    }

    private function validatedData(Request $request, ?AccountSalesman $salesman = null): array
    {
        return $this->validatePayload($request->all(), $salesman, true);
    }

    private function validatePayload(array $data, ?AccountSalesman $salesman = null, bool $requirePasswordConfirmation = true): array
    {
        $passwordRules = ['required', 'string', 'max:50'];
        if ($requirePasswordConfirmation) {
            $passwordRules[] = 'confirmed';
        }

        $data = Validator::make($data, [
            'alternatesalesmancode' => [
                'required',
                'string',
                'max:50',
                Rule::unique('salesman', 'alternatesalesmancode')
                    ->ignore($salesman?->salesmancode, 'salesmancode'),
            ],
            'salesmanname1' => ['required', 'string', 'max:50'],
            'salesmanname2' => ['nullable', 'string', 'max:50'],
            'arbsalesmanname1' => ['nullable', 'string', 'max:50'],
            'messagekey' => ['nullable', 'integer', Rule::exists('salesmanmessages', 'messagekey')],
            'type' => ['required', 'integer', Rule::in([1, 2, 3, 4])],
            'parentcompany' => ['required', 'integer', Rule::exists('company', 'cmpycode')],
            'username' => [
                'required',
                'string',
                'max:50',
                Rule::unique('salesman', 'username')
                    ->ignore($salesman?->salesmancode, 'salesmancode'),
            ],
            'userpassword' => $passwordRules,
            'activestatus' => ['required', 'integer', 'in:0,1'],
        ])->validate();

        foreach (['salesmanname2', 'arbsalesmanname1', 'messagekey'] as $key) {
            if (array_key_exists($key, $data) && $data[$key] === '') {
                $data[$key] = null;
            }
        }

        if (! app(AccessScopeService::class)->allows(request()->user(), 'company', $data['parentcompany'] ?? null)) {
            throw ValidationException::withMessages([
                'parentcompany' => 'Selected company is outside your access scope.',
            ]);
        }

        return $data;
    }

    private function mapBulkImportRow(array $row): array
    {
        $row = collect($row)
            ->mapWithKeys(fn ($value, $key) => [$this->normalizeBulkImportHeader($key) => $value])
            ->all();

        return [
            'alternatesalesmancode' => $this->nullIfBlank($row['alternate_code'] ?? null),
            'salesmanname1' => $this->nullIfBlank($row['salesman_name'] ?? null),
            'salesmanname2' => $this->nullIfBlank($row['salesman_name_2'] ?? null),
            'arbsalesmanname1' => $this->nullIfBlank($row['arabic_name'] ?? null),
            'messagekey' => $this->integerOrNull($row['message_key'] ?? null),
            'type' => $this->integerOrNull($row['type'] ?? null),
            'parentcompany' => $this->integerOrNull($row['parent_company_code'] ?? null),
            'username' => $this->nullIfBlank($row['username'] ?? null),
            'userpassword' => $this->nullIfBlank($row['userpassword'] ?? null),
            'activestatus' => $this->normalizeFlag($row['status'] ?? null),
        ];
    }

    private function bulkImportTemplateHeaders(): array
    {
        return [
            'alternate_code',
            'salesman_name',
            'salesman_name_2',
            'arabic_name',
            'message_key',
            'type',
            'parent_company_code',
            'username',
            'userpassword',
            'status',
        ];
    }

    private function normalizeBulkImportHeader(string $header): string
    {
        $header = strtolower(trim($header));
        $header = preg_replace('/[^a-z0-9]+/', '_', $header) ?? $header;

        return trim($header, '_');
    }

    private function nullIfBlank(mixed $value): ?string
    {
        $value = trim((string) ($value ?? ''));

        return $value === '' ? null : $value;
    }

    private function integerOrNull(mixed $value): ?int
    {
        $value = $this->nullIfBlank($value);

        return $value === null ? null : (int) $value;
    }

    private function normalizeFlag(mixed $value): int
    {
        $value = strtolower(trim((string) ($value ?? '')));

        return match ($value) {
            '1', 'true', 'yes', 'y', 'active' => 1,
            default => 0,
        };
    }

    private function nextSalesmanCode(): int
    {
        return ((int) DB::table('salesman')->max('salesmancode')) + 1;
    }

    private function typeLabel(int $type): string
    {
        foreach (self::TYPE_OPTIONS as $option) {
            if ($option['id'] === $type) {
                return $option['label'];
            }
        }

        return '-';
    }
}
