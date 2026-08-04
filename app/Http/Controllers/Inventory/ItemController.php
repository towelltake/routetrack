<?php

namespace App\Http\Controllers\Inventory;

use App\Http\Controllers\Controller;
use App\Services\AccessScopeService;
use App\Support\ExcelXmlWorkbook;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\Response as HttpResponse;

class ItemController extends Controller
{
    public function index(Request $request): Response
    {
        $user = $request->user();
        $allowedPerPage = [10, 25, 50, 100];
        $allowedSorts = ['actualitemcode', 'alternatecode', 'itemshortdescription', 'itemgroupname', 'unitspercase', 'activeitem'];
        $perPage = (int) $request->input('per_page', 10);
        $sortBy = $request->input('sort_by', 'actualitemcode');
        $sortDir = $request->input('sort_dir', 'asc') === 'desc' ? 'desc' : 'asc';

        if (!in_array($perPage, $allowedPerPage, true)) {
            $perPage = 10;
        }

        if (!in_array($sortBy, $allowedSorts, true)) {
            $sortBy = 'actualitemcode';
        }

        $query = DB::table('itemmaster as item')
            ->leftJoin('itemgroup as igrp', 'igrp.itemgroupcode', '=', 'item.itemgroupcode')
            ->select([
                'item.actualitemcode',
                'item.alternatecode',
                'item.itemshortdescription',
                'item.arbitemshortdescription',
                'item.unitspercase',
                'item.activeitem',
                'item.itemgroupcode',
                'igrp.itemgroupname',
                'igrp.arbitemgroup',
            ]);

        $query->whereIn('item.itemgroupcode', $this->allowedItemGroupCodes($user));

        if ($request->filled('search')) {
            $search = trim((string) $request->input('search'));
            $query->where(function ($builder) use ($search) {
                $builder
                    ->where('item.actualitemcode', 'like', "%{$search}%")
                    ->orWhere('item.alternatecode', 'like', "%{$search}%")
                    ->orWhere('item.itemshortdescription', 'like', "%{$search}%")
                    ->orWhere('item.arbitemshortdescription', 'like', "%{$search}%")
                    ->orWhere('igrp.itemgroupname', 'like', "%{$search}%")
                    ->orWhere('igrp.arbitemgroup', 'like', "%{$search}%")
                    ->orWhere('item.unitspercase', 'like', "%{$search}%");
            });
        }

        $items = $query
            ->orderBy($sortBy === 'itemgroupname' ? 'igrp.itemgroupname' : "item.{$sortBy}", $sortDir)
            ->paginate($perPage)
            ->withQueryString()
            ->through(fn ($record) => [
                'actualitemcode' => (int) $record->actualitemcode,
                'alternatecode' => $record->alternatecode,
                'itemshortdescription' => $record->itemshortdescription,
                'arbitemshortdescription' => $record->arbitemshortdescription,
                'unitspercase' => $record->unitspercase !== null ? (int) $record->unitspercase : null,
                'activeitem' => (int) ($record->activeitem ?? 0),
                'itemgroupcode' => $record->itemgroupcode !== null ? (int) $record->itemgroupcode : null,
                'itemgroupname' => $record->itemgroupname,
                'arbitemgroup' => $record->arbitemgroup,
            ]);

        return Inertia::render('inventory/item/Index', [
            'items' => $items,
            'filters' => [
                'search' => $request->input('search', ''),
                'per_page' => $perPage,
                'sort_by' => $sortBy,
                'sort_dir' => $sortDir,
            ],
            'useAlternateCode' => $this->useAlternateCode(),
        ]);
    }

    public function create(): Response
    {
        $props = $this->formProps();
        $props['itemData']['actualitemcode'] = $this->nextItemCode();

        return Inertia::render('inventory/item/Create', $props);
    }

    public function show(int $item): Response
    {
        $this->assertItemAccess(request(), $item);

        return Inertia::render('inventory/item/View', $this->formProps($item));
    }

    public function edit(int $item): Response
    {
        $this->assertItemAccess(request(), $item);

        return Inertia::render('inventory/item/Edit', $this->formProps($item));
    }

    public function store(Request $request): RedirectResponse
    {
        $payload = $this->validatedData($request);
        $username = auth()->user()?->username ?? auth()->user()?->name ?? 'SYSTEM';

        $insertId = DB::table('itemmaster')->insertGetId([
            'itemgroupcode' => (int) $payload['itemgroupcode'],
            'itemtype' => (int) $payload['itemtype'],
            'itemshortdescription' => $payload['itemshortdescription'],
            'itemdescription' => $payload['itemdescription'],
            'unitspercase' => (int) $payload['unitspercase'],
            'defaultsalesprice' => $payload['defaultsalesprice'],
            'defaultreturnprice' => $payload['defaultreturnprice'],
            'arbitemshortdescription' => $payload['arbitemshortdescription'] ?: null,
            'arbitemdescription' => $payload['arbitemdescription'] ?: null,
            'activeitem' => (int) $payload['activeitem'],
            'caseprice' => $payload['caseprice'],
            'returncaseprice' => $payload['returncaseprice'],
            'alternatecode' => $payload['alternatecode'] ?: null,
            'created' => $username,
            'cdat' => now(),
            'modified' => $username,
            'mdat' => now(),
            'liter' => $payload['liter'],
            'dataentry' => $payload['dataentry'],
            'memo1' => $payload['memo1'] ?: null,
            'memo2' => $payload['memo2'] ?: null,
            'tcallowed' => (int) $payload['tcallowed'],
            'printsequenceroute' => $payload['printsequenceroute'],
            'printsequencecust' => $payload['printsequencecust'],
            'captureshelfstock' => (int) $payload['captureshelfstock'],
            'literperunit' => $payload['literperunit'],
            'fastmovingitemflag' => (int) $payload['fastmovingitemflag'],
            'codedateformat' => (int) $payload['codedateformat'],
            'itemtaxkey1' => $payload['itemtaxkey1'],
            'itemtaxkey2' => $payload['itemtaxkey2'],
            'itemtaxkey3' => $payload['itemtaxkey3'],
            'packagecode' => $payload['packagecode'],
            'anitemcode' => $payload['anitemcode'] ?: null,
            'caseuom' => $payload['caseuom'],
            'warehousestock' => $payload['warehousestock'],
            'itemgrpcode' => $payload['itemgrpcode'] ?: null,
            'defaultgoodreturnprice' => $payload['defaultgoodreturnprice'],
            'defaultgoodreturncaseprice' => $payload['defaultgoodreturncaseprice'],
            'allowbatchentry' => (int) $payload['allowbatchentry'],
            'costcaseprice' => $payload['costcaseprice'],
            'defaultcostprice' => $payload['defaultcostprice'],
            'allowinvoicepricechange' => (int) $payload['allowinvoicepricechange'],
            'offtakeparameter' => $payload['offtakeparameter'],
            'barcode1' => $payload['barcode1'] ?: null,
            'barcode2' => $payload['barcode2'] ?: null,
            'barcode3' => $payload['barcode3'] ?: null,
            'barcode4' => $payload['barcode4'] ?: null,
            'barcode5' => $payload['barcode5'] ?: null,
            'barcode6' => $payload['barcode6'] ?: null,
            'barcode7' => $payload['barcode7'] ?: null,
            'barcode8' => $payload['barcode8'] ?: null,
            'barcode9' => $payload['barcode9'] ?: null,
            'barcode10' => $payload['barcode10'] ?: null,
        ]);

        return redirect()
            ->route('inventory.item.index')
            ->with('success', __('success.item_created_successfully'));
    }

    public function update(Request $request, int $item): RedirectResponse
    {
        $current = DB::table('itemmaster')->where('actualitemcode', $item)->first();
        abort_unless($current, 404);
        $this->assertItemAccess($request, $item);

        $payload = $this->validatedData($request, $item);
        $username = auth()->user()?->username ?? auth()->user()?->name ?? 'SYSTEM';

        DB::table('itemmaster')
            ->where('actualitemcode', $item)
            ->update([
                'itemgroupcode' => (int) $payload['itemgroupcode'],
                'itemtype' => (int) $payload['itemtype'],
                'itemshortdescription' => $payload['itemshortdescription'],
                'itemdescription' => $payload['itemdescription'],
                'unitspercase' => (int) $payload['unitspercase'],
                'defaultsalesprice' => $payload['defaultsalesprice'],
                'defaultreturnprice' => $payload['defaultreturnprice'],
                'arbitemshortdescription' => $payload['arbitemshortdescription'] ?: null,
                'arbitemdescription' => $payload['arbitemdescription'] ?: null,
                'activeitem' => (int) $payload['activeitem'],
                'caseprice' => $payload['caseprice'],
                'returncaseprice' => $payload['returncaseprice'],
                'alternatecode' => $payload['alternatecode'] ?: null,
                'modified' => $username,
                'mdat' => now(),
                'liter' => $payload['liter'],
                'dataentry' => $payload['dataentry'],
                'memo1' => $payload['memo1'] ?: null,
                'memo2' => $payload['memo2'] ?: null,
                'tcallowed' => (int) $payload['tcallowed'],
                'printsequenceroute' => $payload['printsequenceroute'],
                'printsequencecust' => $payload['printsequencecust'],
                'captureshelfstock' => (int) $payload['captureshelfstock'],
                'literperunit' => $payload['literperunit'],
                'fastmovingitemflag' => (int) $payload['fastmovingitemflag'],
                'codedateformat' => (int) $payload['codedateformat'],
                'itemtaxkey1' => $payload['itemtaxkey1'],
                'itemtaxkey2' => $payload['itemtaxkey2'],
                'itemtaxkey3' => $payload['itemtaxkey3'],
                'packagecode' => $payload['packagecode'],
                'anitemcode' => $payload['anitemcode'] ?: null,
                'caseuom' => $payload['caseuom'],
                'warehousestock' => $payload['warehousestock'],
                'itemgrpcode' => $payload['itemgrpcode'] ?: null,
                'defaultgoodreturnprice' => $payload['defaultgoodreturnprice'],
                'defaultgoodreturncaseprice' => $payload['defaultgoodreturncaseprice'],
                'allowbatchentry' => (int) $payload['allowbatchentry'],
                'costcaseprice' => $payload['costcaseprice'],
                'defaultcostprice' => $payload['defaultcostprice'],
                'allowinvoicepricechange' => (int) $payload['allowinvoicepricechange'],
                'offtakeparameter' => $payload['offtakeparameter'],
                'barcode1' => $payload['barcode1'] ?: null,
                'barcode2' => $payload['barcode2'] ?: null,
                'barcode3' => $payload['barcode3'] ?: null,
                'barcode4' => $payload['barcode4'] ?: null,
                'barcode5' => $payload['barcode5'] ?: null,
                'barcode6' => $payload['barcode6'] ?: null,
                'barcode7' => $payload['barcode7'] ?: null,
                'barcode8' => $payload['barcode8'] ?: null,
                'barcode9' => $payload['barcode9'] ?: null,
                'barcode10' => $payload['barcode10'] ?: null,
            ]);

        return redirect()
            ->route('inventory.item.index')
            ->with('success', __('success.item_updated_successfully'));
    }

    public function downloadBulkImportTemplate(): HttpResponse
    {
        return ExcelXmlWorkbook::download(
            'inventory-item-bulk-import-template.xls',
            $this->bulkImportTemplateHeaders(),
            [],
            'ItemMaster'
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
            return back()->withErrors(['file' => 'The uploaded file does not contain any item rows.']);
        }

        $imported = 0;
        $username = auth()->user()?->username ?? auth()->user()?->name ?? 'SYSTEM';

        DB::transaction(function () use ($rows, &$imported, $username) {
            foreach ($rows as $index => $row) {
                $payload = $this->mapBulkImportRow($row);

                try {
                    $validated = $this->validatePayload($payload);
                } catch (\Illuminate\Validation\ValidationException $exception) {
                    $messages = collect($exception->errors())->flatten()->implode(' ');

                    throw \Illuminate\Validation\ValidationException::withMessages([
                        'file' => 'Row ' . ($index + 2) . ': ' . $messages,
                    ]);
                }

                DB::table('itemmaster')->insert($this->itemWritePayload($validated, $username, true));
                $imported++;
            }
        });

        return redirect()
            ->route('inventory.item.index')
            ->with('success', $imported . ' item record(s) imported successfully.');
    }

    private function formProps(?int $itemId = null): array
    {
        return [
            'itemData' => $this->itemFormData($itemId),
            'lookupOptions' => [
                'itemGroups' => DB::table('itemgroup')
                    ->when(Schema::hasColumn('itemgroup', 'activestatus'), fn ($query) => $query->where('activestatus', 1))
                    ->whereIn('itemgroupcode', $this->allowedItemGroupCodes(request()->user()))
                    ->orderBy('itemgroupname')
                    ->get(['itemgroupcode as id', 'itemgroupname as label'])
                    ->map(fn ($record) => [
                        'id' => (int) $record->id,
                        'label' => trim($record->id . ' -- ' . ($record->label ?? '')),
                    ])
                    ->all(),
                'itemPackages' => $this->itemPackageOptions(),
                'itemTaxes' => $this->itemTaxOptions(),
            ],
            'optionSets' => [
                'statusOptions' => [
                    ['id' => 1, 'label' => 'Active'],
                    ['id' => 0, 'label' => 'Inactive'],
                ],
                'itemTypeOptions' => [
                    ['id' => 1, 'label' => 'Product Item'],
                    ['id' => 2, 'label' => 'Containers (Crates)'],
                    ['id' => 3, 'label' => 'Other Item (Not Used)'],
                    ['id' => 4, 'label' => 'Competitor Item'],
                    ['id' => 5, 'label' => 'Parent Item'],
                ],
                'yesNoOptions' => [
                    ['id' => 0, 'label' => 'No'],
                    ['id' => 1, 'label' => 'Yes'],
                ],
                'codeDateFormatOptions' => [
                    ['id' => 0, 'label' => 'None'],
                    ['id' => 1, 'label' => 'DDMMYY'],
                    ['id' => 2, 'label' => 'MMDDYY'],
                    ['id' => 3, 'label' => 'YYMMDD'],
                ],
            ],
        ];
    }

    private function itemFormData(?int $itemId): array
    {
        if (!$itemId) {
            return $this->defaultItemData();
        }

        $record = DB::table('itemmaster')
            ->where('actualitemcode', $itemId)
            ->first();

        abort_unless($record, 404);
        $this->assertItemAccess(request(), $itemId);

        return [
            'actualitemcode' => (int) $record->actualitemcode,
            'itemgroupcode' => $record->itemgroupcode !== null ? (int) $record->itemgroupcode : '',
            'itemtype' => (int) ($record->itemtype ?? 1),
            'anitemcode' => $record->anitemcode ?? '',
            'alternatecode' => $record->alternatecode ?? '',
            'itemshortdescription' => $record->itemshortdescription ?? '',
            'arbitemshortdescription' => $record->arbitemshortdescription ?? '',
            'itemdescription' => $record->itemdescription ?? '',
            'arbitemdescription' => $record->arbitemdescription ?? '',
            'itemgrpcode' => $record->itemgrpcode ?? '',
            'unitspercase' => (int) ($record->unitspercase ?? 1),
            'caseuom' => (int) ($record->caseuom ?? 1),
            'warehousestock' => $this->numericOrNull($record->warehousestock),
            'dataentry' => $record->dataentry !== null ? (int) $record->dataentry : null,
            'offtakeparameter' => $record->offtakeparameter !== null ? (int) $record->offtakeparameter : null,
            'liter' => $this->numericOrNull($record->liter),
            'literperunit' => $this->numericOrNull($record->literperunit),
            'caseprice' => $this->numericOrDefault($record->caseprice),
            'defaultsalesprice' => $this->numericOrDefault($record->defaultsalesprice),
            'defaultgoodreturncaseprice' => $this->numericOrDefault($record->defaultgoodreturncaseprice),
            'defaultgoodreturnprice' => $this->numericOrDefault($record->defaultgoodreturnprice),
            'returncaseprice' => $this->numericOrDefault($record->returncaseprice),
            'defaultreturnprice' => $this->numericOrDefault($record->defaultreturnprice),
            'costcaseprice' => $this->numericOrDefault($record->costcaseprice),
            'defaultcostprice' => $this->numericOrDefault($record->defaultcostprice),
            'activeitem' => (int) ($record->activeitem ?? 1),
            'captureshelfstock' => (int) ($record->captureshelfstock ?? 0),
            'tcallowed' => (int) ($record->tcallowed ?? 0),
            'allowinvoicepricechange' => (int) ($record->allowinvoicepricechange ?? 0),
            'allowbatchentry' => (int) ($record->allowbatchentry ?? 0),
            'fastmovingitemflag' => (int) ($record->fastmovingitemflag ?? 0),
            'codedateformat' => (int) ($record->codedateformat ?? 0),
            'printsequenceroute' => $record->printsequenceroute !== null ? (int) $record->printsequenceroute : null,
            'printsequencecust' => $record->printsequencecust !== null ? (int) $record->printsequencecust : null,
            'itemtaxkey1' => $record->itemtaxkey1 !== null ? (int) $record->itemtaxkey1 : null,
            'itemtaxkey2' => $record->itemtaxkey2 !== null ? (int) $record->itemtaxkey2 : null,
            'itemtaxkey3' => $record->itemtaxkey3 !== null ? (int) $record->itemtaxkey3 : null,
            'packagecode' => $record->packagecode !== null ? (int) $record->packagecode : null,
            'memo1' => $record->memo1 ?? '',
            'memo2' => $record->memo2 ?? '',
            'barcode1' => $record->barcode1 ?? '',
            'barcode2' => $record->barcode2 ?? '',
            'barcode3' => $record->barcode3 ?? '',
            'barcode4' => $record->barcode4 ?? '',
            'barcode5' => $record->barcode5 ?? '',
            'barcode6' => $record->barcode6 ?? '',
            'barcode7' => $record->barcode7 ?? '',
            'barcode8' => $record->barcode8 ?? '',
            'barcode9' => $record->barcode9 ?? '',
            'barcode10' => $record->barcode10 ?? '',
            'cdat' => $record->cdat,
        ];
    }

    private function defaultItemData(): array
    {
        return [
            'actualitemcode' => null,
            'itemgroupcode' => '',
            'itemtype' => 1,
            'anitemcode' => '',
            'alternatecode' => '',
            'itemshortdescription' => '',
            'arbitemshortdescription' => '',
            'itemdescription' => '',
            'arbitemdescription' => '',
            'itemgrpcode' => '',
            'unitspercase' => 1,
            'caseuom' => 1,
            'warehousestock' => null,
            'dataentry' => null,
            'offtakeparameter' => null,
            'liter' => null,
            'literperunit' => null,
            'caseprice' => 0,
            'defaultsalesprice' => 0,
            'defaultgoodreturncaseprice' => 0,
            'defaultgoodreturnprice' => 0,
            'returncaseprice' => 0,
            'defaultreturnprice' => 0,
            'costcaseprice' => 0,
            'defaultcostprice' => 0,
            'activeitem' => 1,
            'captureshelfstock' => 0,
            'tcallowed' => 0,
            'allowinvoicepricechange' => 0,
            'allowbatchentry' => 0,
            'fastmovingitemflag' => 0,
            'codedateformat' => 0,
            'printsequenceroute' => null,
            'printsequencecust' => null,
            'itemtaxkey1' => null,
            'itemtaxkey2' => null,
            'itemtaxkey3' => null,
            'packagecode' => null,
            'memo1' => '',
            'memo2' => '',
            'barcode1' => '',
            'barcode2' => '',
            'barcode3' => '',
            'barcode4' => '',
            'barcode5' => '',
            'barcode6' => '',
            'barcode7' => '',
            'barcode8' => '',
            'barcode9' => '',
            'barcode10' => '',
            'cdat' => null,
        ];
    }

    private function validatedData(Request $request, ?int $itemId = null): array
    {
        return $this->validatePayload($request->all(), $itemId);
    }

    private function validatePayload(array $data, ?int $itemId = null): array
    {
        $validated = Validator::make($data, [
            'itemgroupcode' => ['required', 'integer', Rule::exists('itemgroup', 'itemgroupcode')],
            'itemtype' => ['required', 'integer', Rule::in([1, 2, 3, 4, 5])],
            'anitemcode' => ['nullable', 'string', 'max:50'],
            'alternatecode' => ['nullable', 'string', 'max:50', Rule::unique('itemmaster', 'alternatecode')->ignore($itemId, 'actualitemcode')],
            'itemshortdescription' => ['required', 'string', 'max:50'],
            'arbitemshortdescription' => ['nullable', 'string', 'max:200'],
            'itemdescription' => ['required', 'string', 'max:50'],
            'arbitemdescription' => ['nullable', 'string', 'max:200'],
            'itemgrpcode' => ['nullable', 'string', 'max:10'],
            'unitspercase' => ['required', 'integer', 'min:1'],
            'caseuom' => ['nullable', 'integer', 'min:0'],
            'warehousestock' => ['nullable', 'numeric', 'min:0'],
            'dataentry' => ['nullable', 'integer', 'min:0'],
            'offtakeparameter' => ['nullable', 'integer', 'min:0'],
            'liter' => ['nullable', 'numeric', 'min:0'],
            'literperunit' => ['nullable', 'numeric', 'min:0'],
            'caseprice' => ['nullable', 'numeric', 'min:0'],
            'defaultsalesprice' => ['nullable', 'numeric', 'min:0'],
            'defaultgoodreturncaseprice' => ['nullable', 'numeric', 'min:0'],
            'defaultgoodreturnprice' => ['nullable', 'numeric', 'min:0'],
            'returncaseprice' => ['nullable', 'numeric', 'min:0'],
            'defaultreturnprice' => ['nullable', 'numeric', 'min:0'],
            'costcaseprice' => ['nullable', 'numeric', 'min:0'],
            'defaultcostprice' => ['nullable', 'numeric', 'min:0'],
            'activeitem' => ['required', 'integer', Rule::in([0, 1])],
            'captureshelfstock' => ['nullable', 'integer', Rule::in([0, 1])],
            'tcallowed' => ['nullable', 'integer', Rule::in([0, 1])],
            'allowinvoicepricechange' => ['nullable', 'integer', Rule::in([0, 1])],
            'allowbatchentry' => ['nullable', 'integer', Rule::in([0, 1])],
            'fastmovingitemflag' => ['nullable', 'integer', Rule::in([0, 1])],
            'codedateformat' => ['required', 'integer', Rule::in([0, 1, 2, 3])],
            'printsequenceroute' => ['nullable', 'integer', 'min:0'],
            'printsequencecust' => ['nullable', 'integer', 'min:0'],
            'itemtaxkey1' => ['nullable', 'integer'],
            'itemtaxkey2' => ['nullable', 'integer'],
            'itemtaxkey3' => ['nullable', 'integer'],
            'packagecode' => ['nullable', 'integer'],
            'memo1' => ['nullable', 'string', 'max:50'],
            'memo2' => ['nullable', 'string', 'max:50'],
            'barcode1' => ['nullable', 'string', 'max:20'],
            'barcode2' => ['nullable', 'string', 'max:20'],
            'barcode3' => ['nullable', 'string', 'max:20'],
            'barcode4' => ['nullable', 'string', 'max:20'],
            'barcode5' => ['nullable', 'string', 'max:20'],
            'barcode6' => ['nullable', 'string', 'max:20'],
            'barcode7' => ['nullable', 'string', 'max:20'],
            'barcode8' => ['nullable', 'string', 'max:20'],
            'barcode9' => ['nullable', 'string', 'max:20'],
            'barcode10' => ['nullable', 'string', 'max:20'],
        ])->validate();

        $request = request();
        $itemGroupCode = isset($validated['itemgroupcode']) ? (int) $validated['itemgroupcode'] : null;
        if ($itemGroupCode !== null) {
            $allowed = DB::table('itemgroup')
                ->where('itemgroupcode', $itemGroupCode)
                ->whereIn('itemgroupcode', $this->allowedItemGroupCodes($request->user()))
                ->exists();

            if (!$allowed) {
                throw ValidationException::withMessages([
                    'itemgroupcode' => 'The selected item group is outside your access scope.',
                ]);
            }
        }

        foreach ([
            'anitemcode',
            'alternatecode',
            'arbitemshortdescription',
            'arbitemdescription',
            'itemgrpcode',
            'memo1',
            'memo2',
            'barcode1',
            'barcode2',
            'barcode3',
            'barcode4',
            'barcode5',
            'barcode6',
            'barcode7',
            'barcode8',
            'barcode9',
            'barcode10',
        ] as $key) {
            if (array_key_exists($key, $validated) && $validated[$key] === '') {
                $validated[$key] = null;
            }
        }

        return $validated;
    }

    private function nextItemCode(): int
    {
        return ((int) DB::table('itemmaster')->max('actualitemcode')) + 1;
    }

    private function useAlternateCode(): bool
    {
        if (!Schema::hasTable('controlpanel')) {
            return false;
        }

        return (int) DB::table('controlpanel')
            ->where('flagname', 'Use Alternate Code')
            ->value('status') === 1;
    }

    private function itemPackageOptions(): array
    {
        if (!Schema::hasTable('itempackagemaster')) {
            return [];
        }

        $query = DB::table('itempackagemaster')->orderBy('packagedescription');
        if (Schema::hasColumn('itempackagemaster', 'activestatus')) {
            $query->where('activestatus', 1);
        }

        return $query
            ->get(['packagecode as id', 'packagedescription as label'])
            ->map(fn ($record) => [
                'id' => (int) $record->id,
                'label' => trim($record->id . ' -- ' . ($record->label ?? '')),
            ])
            ->all();
    }

    private function itemTaxOptions(): array
    {
        if (!Schema::hasTable('tbltaxmaster')) {
            return [];
        }

        return DB::table('tbltaxmaster')
            ->when(Schema::hasColumn('tbltaxmaster', 'taxtype'), fn ($query) => $query->where('taxtype', 2))
            ->orderBy('taxdescription')
            ->get(['taxcode as id', 'taxdescription as label'])
            ->map(fn ($record) => [
                'id' => (int) $record->id,
                'label' => trim($record->id . ' -- ' . ($record->label ?? '')),
            ])
            ->all();
    }

    private function numericOrNull(mixed $value): mixed
    {
        return $value !== null ? (float) $value : null;
    }

    private function numericOrDefault(mixed $value): float
    {
        return $value !== null ? (float) $value : 0.0;
    }

    private function itemWritePayload(array $payload, string $username, bool $includeCreated = false): array
    {
        $data = [
            'itemgroupcode' => (int) $payload['itemgroupcode'],
            'itemtype' => (int) $payload['itemtype'],
            'itemshortdescription' => $payload['itemshortdescription'],
            'itemdescription' => $payload['itemdescription'],
            'unitspercase' => (int) $payload['unitspercase'],
            'defaultsalesprice' => $payload['defaultsalesprice'],
            'defaultreturnprice' => $payload['defaultreturnprice'],
            'arbitemshortdescription' => $payload['arbitemshortdescription'] ?: null,
            'arbitemdescription' => $payload['arbitemdescription'] ?: null,
            'activeitem' => (int) $payload['activeitem'],
            'caseprice' => $payload['caseprice'],
            'returncaseprice' => $payload['returncaseprice'],
            'alternatecode' => $payload['alternatecode'] ?: null,
            'modified' => $username,
            'mdat' => now(),
            'liter' => $payload['liter'],
            'dataentry' => $payload['dataentry'],
            'memo1' => $payload['memo1'] ?: null,
            'memo2' => $payload['memo2'] ?: null,
            'tcallowed' => (int) $payload['tcallowed'],
            'printsequenceroute' => $payload['printsequenceroute'],
            'printsequencecust' => $payload['printsequencecust'],
            'captureshelfstock' => (int) $payload['captureshelfstock'],
            'literperunit' => $payload['literperunit'],
            'fastmovingitemflag' => (int) $payload['fastmovingitemflag'],
            'codedateformat' => (int) $payload['codedateformat'],
            'itemtaxkey1' => $payload['itemtaxkey1'],
            'itemtaxkey2' => $payload['itemtaxkey2'],
            'itemtaxkey3' => $payload['itemtaxkey3'],
            'packagecode' => $payload['packagecode'],
            'anitemcode' => $payload['anitemcode'] ?: null,
            'caseuom' => $payload['caseuom'],
            'warehousestock' => $payload['warehousestock'],
            'itemgrpcode' => $payload['itemgrpcode'] ?: null,
            'defaultgoodreturnprice' => $payload['defaultgoodreturnprice'],
            'defaultgoodreturncaseprice' => $payload['defaultgoodreturncaseprice'],
            'allowbatchentry' => (int) $payload['allowbatchentry'],
            'costcaseprice' => $payload['costcaseprice'],
            'defaultcostprice' => $payload['defaultcostprice'],
            'allowinvoicepricechange' => (int) $payload['allowinvoicepricechange'],
            'offtakeparameter' => $payload['offtakeparameter'],
            'barcode1' => $payload['barcode1'] ?: null,
            'barcode2' => $payload['barcode2'] ?: null,
            'barcode3' => $payload['barcode3'] ?: null,
            'barcode4' => $payload['barcode4'] ?: null,
            'barcode5' => $payload['barcode5'] ?: null,
            'barcode6' => $payload['barcode6'] ?: null,
            'barcode7' => $payload['barcode7'] ?: null,
            'barcode8' => $payload['barcode8'] ?: null,
            'barcode9' => $payload['barcode9'] ?: null,
            'barcode10' => $payload['barcode10'] ?: null,
        ];

        if ($includeCreated) {
            $data['created'] = $username;
            $data['cdat'] = now();
        }

        return $data;
    }

    private function mapBulkImportRow(array $row): array
    {
        $row = collect($row)
            ->mapWithKeys(fn ($value, $key) => [$this->normalizeBulkImportHeader($key) => $value])
            ->all();

        $shortDescription = $this->nullIfBlank($row['short_description'] ?? null);

        return array_merge($this->defaultItemData(), [
            'itemgroupcode' => $this->integerOrNull($row['item_group_code'] ?? null),
            'itemtype' => $this->integerOrDefault($row['item_type'] ?? null, 1),
            'anitemcode' => $this->nullIfBlank($row['an_item_code'] ?? null),
            'alternatecode' => $this->nullIfBlank($row['alternate_code'] ?? null),
            'itemshortdescription' => $shortDescription,
            'arbitemshortdescription' => $this->nullIfBlank($row['arabic_short_description'] ?? null),
            'itemdescription' => $this->nullIfBlank($row['description'] ?? null) ?? $shortDescription,
            'arbitemdescription' => $this->nullIfBlank($row['arabic_description'] ?? null),
            'itemgrpcode' => $this->nullIfBlank($row['item_group_text_code'] ?? null),
            'unitspercase' => $this->integerOrDefault($row['units_per_case'] ?? null, 1),
            'caseuom' => $this->integerOrDefault($row['case_uom'] ?? null, 1),
            'warehousestock' => $this->decimalOrNull($row['warehouse_stock'] ?? null),
            'dataentry' => $this->integerOrNull($row['data_entry'] ?? null),
            'offtakeparameter' => $this->integerOrNull($row['offtake_parameter'] ?? null),
            'liter' => $this->decimalOrNull($row['liter'] ?? null),
            'literperunit' => $this->decimalOrNull($row['liter_per_unit'] ?? null),
            'caseprice' => $this->decimalOrDefault($row['case_price'] ?? null, 0),
            'defaultsalesprice' => $this->decimalOrDefault($row['sales_price'] ?? null, 0),
            'defaultgoodreturncaseprice' => $this->decimalOrDefault($row['good_return_case_price'] ?? null, 0),
            'defaultgoodreturnprice' => $this->decimalOrDefault($row['good_return_price'] ?? null, 0),
            'returncaseprice' => $this->decimalOrDefault($row['return_case_price'] ?? null, 0),
            'defaultreturnprice' => $this->decimalOrDefault($row['return_price'] ?? null, 0),
            'costcaseprice' => $this->decimalOrDefault($row['cost_case_price'] ?? null, 0),
            'defaultcostprice' => $this->decimalOrDefault($row['cost_price'] ?? null, 0),
            'activeitem' => $this->normalizeFlag($row['status'] ?? 1),
            'captureshelfstock' => $this->normalizeFlag($row['capture_shelf_stock'] ?? 0),
            'tcallowed' => $this->normalizeFlag($row['tc_allowed'] ?? 0),
            'allowinvoicepricechange' => $this->normalizeFlag($row['allow_invoice_price_change'] ?? 0),
            'allowbatchentry' => $this->normalizeFlag($row['allow_batch_entry'] ?? 0),
            'fastmovingitemflag' => $this->normalizeFlag($row['fast_moving_item'] ?? 0),
            'codedateformat' => $this->integerOrDefault($row['code_date_format'] ?? null, 0),
            'printsequenceroute' => $this->integerOrNull($row['print_sequence_route'] ?? null),
            'printsequencecust' => $this->integerOrNull($row['print_sequence_customer'] ?? null),
            'itemtaxkey1' => $this->integerOrNull($row['item_tax_1'] ?? null),
            'itemtaxkey2' => $this->integerOrNull($row['item_tax_2'] ?? null),
            'itemtaxkey3' => $this->integerOrNull($row['item_tax_3'] ?? null),
            'packagecode' => $this->integerOrNull($row['package_code'] ?? null),
            'memo1' => $this->nullIfBlank($row['memo_1'] ?? null),
            'memo2' => $this->nullIfBlank($row['memo_2'] ?? null),
            'barcode1' => $this->nullIfBlank($row['barcode_1'] ?? null),
            'barcode2' => $this->nullIfBlank($row['barcode_2'] ?? null),
            'barcode3' => $this->nullIfBlank($row['barcode_3'] ?? null),
            'barcode4' => $this->nullIfBlank($row['barcode_4'] ?? null),
            'barcode5' => $this->nullIfBlank($row['barcode_5'] ?? null),
            'barcode6' => $this->nullIfBlank($row['barcode_6'] ?? null),
            'barcode7' => $this->nullIfBlank($row['barcode_7'] ?? null),
            'barcode8' => $this->nullIfBlank($row['barcode_8'] ?? null),
            'barcode9' => $this->nullIfBlank($row['barcode_9'] ?? null),
            'barcode10' => $this->nullIfBlank($row['barcode_10'] ?? null),
        ]);
    }

    private function bulkImportTemplateHeaders(): array
    {
        return [
            'item_group_code',
            'item_type',
            'alternate_code',
            'short_description',
            'arabic_short_description',
            'description',
            'arabic_description',
            'units_per_case',
            'case_price',
            'sales_price',
            'return_price',
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

    private function integerOrDefault(mixed $value, int $default): int
    {
        $value = $this->integerOrNull($value);

        return $value ?? $default;
    }

    private function decimalOrNull(mixed $value): ?float
    {
        $value = $this->nullIfBlank($value);

        return $value === null ? null : (float) $value;
    }

    private function decimalOrDefault(mixed $value, float $default): float
    {
        $value = $this->decimalOrNull($value);

        return $value ?? $default;
    }

    private function normalizeFlag(mixed $value): int
    {
        $value = strtolower(trim((string) ($value ?? '')));

        return match ($value) {
            '1', 'true', 'yes', 'y', 'active' => 1,
            default => 0,
        };
    }

    private function allowedItemGroupCodes($user): array
    {
        $query = DB::table('itemgroup as itemgroup')
            ->join('submajorcategory as sub', 'sub.submajorcategorycode', '=', 'itemgroup.submajorcategorycode')
            ->join('majorcategory as major', 'major.majorcategorycode', '=', 'sub.majorcategorycode')
            ->join('companygroup as companygroup', 'companygroup.companygroupcode', '=', 'major.companygroupcode')
            ->select('itemgroup.itemgroupcode');

        app(AccessScopeService::class)->scopeQuery($user, $query, 'company', 'companygroup.parentcompany');

        return $query->pluck('itemgroup.itemgroupcode')->map(fn ($code) => (int) $code)->all();
    }

    private function assertItemAccess(Request $request, int $itemCode): void
    {
        $allowed = DB::table('itemmaster')
            ->where('actualitemcode', $itemCode)
            ->whereIn('itemgroupcode', $this->allowedItemGroupCodes($request->user()))
            ->exists();

        abort_unless($allowed, 403);
    }
}
