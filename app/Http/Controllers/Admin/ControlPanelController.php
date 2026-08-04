<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class ControlPanelController extends Controller
{
    private const DEFAULT_TIME = '18:00:00';

    public function index(): Response
    {
        $definitions = $this->definitions();
        $this->ensureDefinitionsExist($definitions);

        $records = $this->recordsByName();

        return Inertia::render('settings/ControlPanel', [
            'tabs' => $this->buildTabs($definitions, $records),
            'form' => $this->buildForm($definitions, $records),
            'meta' => [
                'hasSavedRows' => $records->isNotEmpty(),
                'legacyPath' => 'Administration > Control Panel',
            ],
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $definitions = $this->definitions();

        $request->merge([
            'flags' => array_merge([
                'general' => [],
                'route' => [],
                'customer' => [],
                'item' => [],
            ], (array) $request->input('flags', [])),
        ]);

        $validated = $request->validate([
            'flags' => ['required', 'array'],
            'flags.general' => ['present', 'array'],
            'flags.route' => ['present', 'array'],
            'flags.customer' => ['present', 'array'],
            'flags.item' => ['present', 'array'],
            'startingLoadMethod' => ['required', 'integer', 'in:0,1,2,3,4,5'],
            'customerCodeGeneration' => ['required', 'string', 'in:Customer Code With Route,Customer Code With Depot,Customer Code With Route and Depot,Normal Sequence Number'],
            'pdcClearance' => ['required', 'string', 'in:PDC Clearance With CashierReceipt,PDC Clearance WithOut CashierReceipt'],
            'depotInventory' => ['required', 'string', 'in:Standard Depot Inventory,Advanced Depot Inventory'],
            'costPricePercent' => ['nullable', 'numeric', 'min:0', 'max:999.99'],
            'monthClosingTime' => ['nullable', 'date_format:H:i'],
        ]);

        $definitionsByName = collect($definitions)->keyBy('name');
        $selectedNames = collect([
            ...($validated['flags']['general'] ?? []),
            ...($validated['flags']['route'] ?? []),
            ...($validated['flags']['customer'] ?? []),
            ...($validated['flags']['item'] ?? []),
            $validated['pdcClearance'],
            $validated['depotInventory'],
            $validated['customerCodeGeneration'],
        ])->filter()->unique()->values();

        $selectedIds = $selectedNames
            ->map(fn (string $name) => $definitionsByName->get($name)['id'] ?? null)
            ->filter()
            ->values();

        DB::transaction(function () use ($definitions, $validated, $selectedIds): void {
            $this->ensureDefinitionsExist($definitions);

            DB::table('controlpanel')->update([
                'status' => 0,
                'modifieddate' => now(),
            ]);

            if ($selectedIds->isNotEmpty()) {
                DB::table('controlpanel')
                    ->whereIn('flagid', $selectedIds->all())
                    ->update([
                        'status' => 1,
                        'modifieddate' => now(),
                    ]);
            }

            DB::table('controlpanel')
                ->where('flagid', 60)
                ->update([
                    'status' => (int) $validated['startingLoadMethod'],
                    'modifieddate' => now(),
                ]);

            DB::table('controlpanel')
                ->where('flagid', 78)
                ->update([
                    'flagvalue' => (string) ($validated['costPricePercent'] ?? 0),
                    'modifieddate' => now(),
                ]);

            DB::table('controlpanel')
                ->where('flagid', 83)
                ->update([
                    'flagvalue' => ($validated['monthClosingTime'] ?? '') !== ''
                        ? $validated['monthClosingTime']
                        : self::DEFAULT_TIME,
                    'modifieddate' => now(),
                ]);
        });

        return back()->with('success', 'Control panel updated.');
    }

    private function recordsByName()
    {
        return DB::table('controlpanel')
            ->select('flagid', 'formid', 'flagname', 'status', 'flagvalue')
            ->orderBy('flagid')
            ->get()
            ->keyBy('flagname');
    }

    private function buildTabs(array $definitions, $records): array
    {
        $labels = [
            'general' => 'General',
            'route' => 'Route',
            'customer' => 'Customer',
            'item' => 'Items',
        ];

        $tabs = [];

        foreach ($labels as $key => $label) {
            $tabs[] = [
                'key' => $key,
                'label' => $label,
                'items' => collect($definitions)
                    ->where('tab', $key)
                    ->map(function (array $definition) use ($records) {
                        $record = $records->get($definition['name']);

                        return [
                            'name' => $definition['name'],
                            'label' => $definition['label'],
                            'type' => $definition['type'],
                            'group' => $definition['group'],
                            'enabled' => $this->statusValue($record, $definition),
                            'value' => $this->flagValue($record, $definition),
                            'options' => $definition['options'] ?? [],
                        ];
                    })
                    ->values()
                    ->all(),
            ];
        }

        return $tabs;
    }

    private function buildForm(array $definitions, $records): array
    {
        return [
            'flags' => [
                'general' => $this->checkboxNamesForTab($definitions, $records, 'general'),
                'route' => $this->checkboxNamesForTab($definitions, $records, 'route'),
                'customer' => $this->checkboxNamesForTab($definitions, $records, 'customer'),
                'item' => $this->checkboxNamesForTab($definitions, $records, 'item'),
            ],
            'startingLoadMethod' => (int) optional($records->get('Load From ERP'))->status,
            'customerCodeGeneration' => $this->selectedRadio($definitions, $records, 'customer_code_generation', 'Customer Code With Route'),
            'pdcClearance' => $this->selectedRadio($definitions, $records, 'pdc_clearance', 'PDC Clearance With CashierReceipt'),
            'depotInventory' => $this->selectedRadio($definitions, $records, 'depot_inventory', 'Standard Depot Inventory'),
            'costPricePercent' => $this->flagValue($records->get('Cost Price Percent'), [
                'type' => 'value',
                'default_value' => '0',
            ]),
            'monthClosingTime' => $this->flagValue($records->get('Month Closing Time'), [
                'type' => 'value',
                'default_value' => self::DEFAULT_TIME,
            ]),
        ];
    }

    private function checkboxNamesForTab(array $definitions, $records, string $tab): array
    {
        return collect($definitions)
            ->where('tab', $tab)
            ->where('type', 'checkbox')
            ->filter(fn (array $definition) => $this->statusValue($records->get($definition['name']), $definition) === 1)
            ->pluck('name')
            ->values()
            ->all();
    }

    private function selectedRadio(array $definitions, $records, string $group, string $fallback): string
    {
        $selected = collect($definitions)
            ->where('type', 'radio')
            ->where('group', $group)
            ->first(fn (array $definition) => $this->statusValue($records->get($definition['name']), $definition) === 1);

        return $selected['name'] ?? $fallback;
    }

    private function statusValue($record, array $definition): int
    {
        if (! $record) {
            return (int) ($definition['default_status'] ?? 0);
        }

        return (int) ($record->status ?? 0);
    }

    private function flagValue($record, array $definition): string
    {
        if (! $record || $record->flagvalue === null || $record->flagvalue === '') {
            return (string) ($definition['default_value'] ?? '');
        }

        return (string) $record->flagvalue;
    }

    private function definitions(): array
    {
        return [
            ['id' => 1, 'formid' => 0, 'tab' => 'general', 'type' => 'checkbox', 'group' => null, 'name' => 'Password Generator', 'label' => 'Use Password Generator', 'default_status' => 0, 'default_value' => ''],
            ['id' => 84, 'formid' => 0, 'tab' => 'general', 'type' => 'checkbox', 'group' => null, 'name' => 'Customer Free Contract', 'label' => 'Enable Customer Free Contracts', 'default_status' => 0, 'default_value' => ''],
            ['id' => 2, 'formid' => 0, 'tab' => 'general', 'type' => 'checkbox', 'group' => null, 'name' => 'Suggested Sales', 'label' => 'Use Suggested Sales', 'default_status' => 0, 'default_value' => ''],
            ['id' => 3, 'formid' => 0, 'tab' => 'general', 'type' => 'checkbox', 'group' => null, 'name' => 'Discount Key', 'label' => 'Enable Discount Key (Item Level Ranged Discounts)', 'default_status' => 0, 'default_value' => ''],
            ['id' => 24, 'formid' => 0, 'tab' => 'general', 'type' => 'checkbox', 'group' => null, 'name' => 'Enable Authorized Item Group', 'label' => 'Enable Authorized Item Group', 'default_status' => 0, 'default_value' => ''],
            ['id' => 85, 'formid' => 0, 'tab' => 'general', 'type' => 'checkbox', 'group' => null, 'name' => 'Distribution Key', 'label' => 'Enable Distribution Key (Item Level Case Price Discounts)', 'default_status' => 0, 'default_value' => ''],
            ['id' => 44, 'formid' => 0, 'tab' => 'general', 'type' => 'checkbox', 'group' => null, 'name' => 'Enable Outlet Product Code', 'label' => 'Enable Outlet Product Code', 'default_status' => 0, 'default_value' => ''],
            ['id' => 86, 'formid' => 0, 'tab' => 'general', 'type' => 'checkbox', 'group' => null, 'name' => 'Depot Damage Expiry', 'label' => 'Depot Damage Expiry', 'default_status' => 0, 'default_value' => ''],
            ['id' => 41, 'formid' => 0, 'tab' => 'general', 'type' => 'checkbox', 'group' => null, 'name' => 'Use Alternate Code', 'label' => 'Use Alternate Code In Backoffice (Customer & Item)', 'default_status' => 0, 'default_value' => ''],
            ['id' => 87, 'formid' => 0, 'tab' => 'general', 'type' => 'checkbox', 'group' => null, 'name' => 'Damage/Expiry Management', 'label' => 'Damage/Expiry Management', 'default_status' => 0, 'default_value' => ''],
            ['id' => 88, 'formid' => 0, 'tab' => 'general', 'type' => 'checkbox', 'group' => null, 'name' => 'User Alternate Code For Pending Invoices', 'label' => 'Use Alternate Code For Pending Invoices', 'default_status' => 0, 'default_value' => ''],
            ['id' => 89, 'formid' => 0, 'tab' => 'general', 'type' => 'checkbox', 'group' => null, 'name' => 'Fixed Qualification/Fixed Assignment', 'label' => 'Use Fixed Qualification And Fixed Assignment', 'default_status' => 0, 'default_value' => ''],
            ['id' => 40, 'formid' => 0, 'tab' => 'general', 'type' => 'checkbox', 'group' => null, 'name' => 'Download Other Route Pending Invs', 'label' => 'Download Other Route Pending Invoices', 'default_status' => 0, 'default_value' => ''],
            ['id' => 90, 'formid' => 0, 'tab' => 'general', 'type' => 'checkbox', 'group' => null, 'name' => 'Ranged Qualification on Fixed Assignment', 'label' => 'Use Ranged Qualification On Fixed Assignment', 'default_status' => 0, 'default_value' => ''],
            ['id' => 91, 'formid' => 0, 'tab' => 'general', 'type' => 'checkbox', 'group' => null, 'name' => 'Disable Balance Update', 'label' => 'Post PDC Collected As Cash', 'default_status' => 0, 'default_value' => ''],
            ['id' => 57, 'formid' => 0, 'tab' => 'general', 'type' => 'checkbox', 'group' => null, 'name' => 'Pdc Balance Amount', 'label' => 'Get PDC Amount From CI', 'default_status' => 0, 'default_value' => ''],
            ['id' => 62, 'formid' => 0, 'tab' => 'general', 'type' => 'checkbox', 'group' => null, 'name' => 'Insert AR with Old Salesman', 'label' => 'Post Collection With Respect To Invoiced Salesman', 'default_status' => 0, 'default_value' => ''],
            ['id' => 92, 'formid' => 0, 'tab' => 'general', 'type' => 'checkbox', 'group' => null, 'name' => 'Include PDC Amount in ARD', 'label' => 'Get PDC Amount From ARD', 'default_status' => 0, 'default_value' => ''],
            ['id' => 77, 'formid' => 0, 'tab' => 'general', 'type' => 'checkbox', 'group' => null, 'name' => 'Allow Cloud Transaction Entry', 'label' => 'Enable Transaction Posting In Cloud', 'default_status' => 0, 'default_value' => ''],
            ['id' => 93, 'formid' => 0, 'tab' => 'general', 'type' => 'checkbox', 'group' => null, 'name' => 'Enable Tax', 'label' => 'Enable Tax', 'default_status' => 0, 'default_value' => ''],
            ['id' => 6, 'formid' => 0, 'tab' => 'general', 'type' => 'checkbox', 'group' => null, 'name' => 'Month Close', 'label' => 'Enable Month Closing', 'default_status' => 0, 'default_value' => ''],
            ['id' => 83, 'formid' => 0, 'tab' => 'general', 'type' => 'value', 'group' => null, 'name' => 'Month Closing Time', 'label' => 'Closing Time(HH:MM)', 'default_status' => 0, 'default_value' => self::DEFAULT_TIME],
            ['id' => 30, 'formid' => 0, 'tab' => 'general', 'type' => 'radio', 'group' => 'pdc_clearance', 'name' => 'PDC Clearance With CashierReceipt', 'label' => 'PDC Clearance With CashierReceipt', 'default_status' => 1, 'default_value' => ''],
            ['id' => 31, 'formid' => 0, 'tab' => 'general', 'type' => 'radio', 'group' => 'pdc_clearance', 'name' => 'PDC Clearance WithOut CashierReceipt', 'label' => 'PDC Clearance WithOut CashierReceipt', 'default_status' => 0, 'default_value' => ''],
            ['id' => 14, 'formid' => 0, 'tab' => 'general', 'type' => 'radio', 'group' => 'depot_inventory', 'name' => 'Standard Depot Inventory', 'label' => 'Standard', 'default_status' => 1, 'default_value' => ''],
            ['id' => 64, 'formid' => 0, 'tab' => 'general', 'type' => 'radio', 'group' => 'depot_inventory', 'name' => 'Advanced Depot Inventory', 'label' => 'Advance', 'default_status' => 0, 'default_value' => ''],
            ['id' => 94, 'formid' => 1, 'tab' => 'route', 'type' => 'checkbox', 'group' => null, 'name' => 'Enable Company and Region', 'label' => 'Enable Company And Region', 'default_status' => 0, 'default_value' => ''],
            ['id' => 95, 'formid' => 1, 'tab' => 'route', 'type' => 'checkbox', 'group' => null, 'name' => 'Allowed Radius', 'label' => 'Allowed Radius (For GPS Limits)', 'default_status' => 0, 'default_value' => ''],
            ['id' => 96, 'formid' => 1, 'tab' => 'route', 'type' => 'checkbox', 'group' => null, 'name' => 'Enable Item Must List', 'label' => 'Enable Item Must List', 'default_status' => 0, 'default_value' => ''],
            ['id' => 34, 'formid' => 1, 'tab' => 'route', 'type' => 'checkbox', 'group' => null, 'name' => 'Import Sync With Salesman Load', 'label' => 'Import Sync With Salesman Load', 'default_status' => 0, 'default_value' => ''],
            ['id' => 60, 'formid' => 1, 'tab' => 'route', 'type' => 'select', 'group' => 'starting_load_method', 'name' => 'Load From ERP', 'label' => 'Daily Salesman Load Generation Method', 'default_status' => 0, 'default_value' => '', 'options' => [
                ['value' => 0, 'label' => 'Create New Load'],
                ['value' => 1, 'label' => 'Load Imported From ERP'],
                ['value' => 2, 'label' => 'Convert Load Request to Load'],
                ['value' => 3, 'label' => 'Sales order to load'],
                ['value' => 4, 'label' => 'Populate Previous day load'],
                ['value' => 5, 'label' => 'Use Suggested Load'],
            ]],
            ['id' => 74, 'formid' => 2, 'tab' => 'customer', 'type' => 'checkbox', 'group' => null, 'name' => 'Use Grace Period', 'label' => 'Use Grace Period (For Credit Limit Days)', 'default_status' => 0, 'default_value' => ''],
            ['id' => 97, 'formid' => 2, 'tab' => 'customer', 'type' => 'checkbox', 'group' => null, 'name' => 'Customer Sequence', 'label' => 'Create Route Sequence For New Customers', 'default_status' => 0, 'default_value' => ''],
            ['id' => 98, 'formid' => 2, 'tab' => 'customer', 'type' => 'checkbox', 'group' => null, 'name' => 'Journey Plan Credit Limit', 'label' => 'Use Journey Plan Credit Limit (Route Wise Limit For Customer)', 'default_status' => 0, 'default_value' => ''],
            ['id' => 99, 'formid' => 2, 'tab' => 'customer', 'type' => 'checkbox', 'group' => null, 'name' => 'Show Additional Customer Details', 'label' => 'Show Additional Customer Details During Creation', 'default_status' => 0, 'default_value' => ''],
            ['id' => 79, 'formid' => 2, 'tab' => 'customer', 'type' => 'checkbox', 'group' => null, 'name' => 'Use Credit Check Exception', 'label' => 'Use Credit Check Exception', 'default_status' => 0, 'default_value' => ''],
            ['id' => 82, 'formid' => 2, 'tab' => 'customer', 'type' => 'checkbox', 'group' => null, 'name' => 'Enabled Channel Master', 'label' => 'Enabled Channel Master', 'default_status' => 0, 'default_value' => ''],
            ['id' => 80, 'formid' => 2, 'tab' => 'customer', 'type' => 'radio', 'group' => 'customer_code_generation', 'name' => 'Customer Code With Route', 'label' => 'With Route Code', 'default_status' => 1, 'default_value' => ''],
            ['id' => 81, 'formid' => 2, 'tab' => 'customer', 'type' => 'radio', 'group' => 'customer_code_generation', 'name' => 'Customer Code With Depot', 'label' => 'With Depot Code', 'default_status' => 0, 'default_value' => ''],
            ['id' => 76, 'formid' => 2, 'tab' => 'customer', 'type' => 'radio', 'group' => 'customer_code_generation', 'name' => 'Customer Code With Route and Depot', 'label' => 'With Route And Depot Code', 'default_status' => 0, 'default_value' => ''],
            ['id' => 100, 'formid' => 2, 'tab' => 'customer', 'type' => 'radio', 'group' => 'customer_code_generation', 'name' => 'Normal Sequence Number', 'label' => 'With Depot And Route Code', 'default_status' => 0, 'default_value' => ''],
            ['id' => 101, 'formid' => 3, 'tab' => 'item', 'type' => 'checkbox', 'group' => null, 'name' => 'Enabled Batch And Expiry', 'label' => 'Enabled Batch And Expiry', 'default_status' => 0, 'default_value' => ''],
            ['id' => 102, 'formid' => 3, 'tab' => 'item', 'type' => 'checkbox', 'group' => null, 'name' => 'Add New Items To Route Item Grouping', 'label' => 'Add New Items To Route Item Grouping', 'default_status' => 0, 'default_value' => ''],
            ['id' => 103, 'formid' => 3, 'tab' => 'item', 'type' => 'checkbox', 'group' => null, 'name' => 'Enable Cost Price', 'label' => 'Enable Cost Price', 'default_status' => 0, 'default_value' => ''],
            ['id' => 78, 'formid' => 3, 'tab' => 'item', 'type' => 'value', 'group' => null, 'name' => 'Cost Price Percent', 'label' => 'Cost Price Calculation %', 'default_status' => 0, 'default_value' => '0'],
        ];
    }

    private function ensureDefinitionsExist(array $definitions): void
    {
        $existingIds = DB::table('controlpanel')->pluck('flagid')->all();
        $missing = collect($definitions)
            ->reject(fn (array $definition) => in_array($definition['id'], $existingIds, true))
            ->map(fn (array $definition) => [
                'flagid' => $definition['id'],
                'formid' => $definition['formid'],
                'flagname' => $definition['name'],
                'status' => $definition['type'] === 'select'
                    ? (int) $definition['default_status']
                    : (($definition['type'] === 'radio' || $definition['type'] === 'checkbox')
                        ? (int) $definition['default_status']
                        : 0),
                'modifieddate' => now(),
                'flagvalue' => (string) ($definition['default_value'] ?? ''),
            ])
            ->values()
            ->all();

        if ($missing !== []) {
            DB::table('controlpanel')->insert($missing);
        }
    }
}
