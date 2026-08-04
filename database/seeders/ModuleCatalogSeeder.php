<?php

namespace Database\Seeders;

use App\Models\ModuleDetail;
use App\Models\ModuleHeader;
use Illuminate\Database\Seeder;

class ModuleCatalogSeeder extends Seeder
{
    private const MODULES = [
        [
            'moduleid' => 1,
            'modulename' => 'User Management',
            'forms' => [
                'User Type',
                'Users',
                'User Permission',
            ],
        ],
        [
            'moduleid' => 2,
            'modulename' => 'Basic',
            'forms' => [
                'Company',
                'Currency',
                'Bank',
                'Cash Description',
                'Inventory Location',
                'National Sales Mgr',
                'Region Manager',
                'Branch/Depot Manager',
                'Area Manager',
                'Supervisor',
                'Reason',
            ],
        ],
        [
            'moduleid' => 3,
            'modulename' => 'Organisation',
            'forms' => [
                'Country',
                'Region',
                'Depot',
                'Area',
                'Sub Area',
                'Van',
                'Route Category',
                'Route Bulk Import',
                'Route',
                'Route Template',
            ],
        ],
        [
            'moduleid' => 4,
            'modulename' => 'Account',
            'forms' => [
                'Customer Message',
                'Salesman Message',
                'Account Salesman',
                'Account Customer Channel',
                'Account Customer Category',
                'Account Customer',
                'Account Customer Bulk Import',
                'Account Customer Template',
                'Account Customer Authorize Group',
                'Account Customer Sequence',
                'Arrange Customer Bulk Import',
                'Account Salesman Bulk Import',
                'Account Tax',
                'Account Transaction',
            ],
        ],
        [
            'moduleid' => 5,
            'modulename' => 'Inventory',
            'forms' => [
                'Company Group',
                'Major Category',
                'Sub Major Category',
                'Item Group',
                'Items',
                'Route Item Group',
                'Daily Salesman Load',
                'Delivery',
                'Target Group',
                'Target & Commission',
            ],
        ],
        [
            'moduleid' => 6,
            'modulename' => 'Scheme',
            'forms' => [
                'Qualification Group',
                'Assignment Group',
                'Promo Plan',
                'Promo Key',
                'Pricing Plan',
                'Pricing Key',
                'Loyalty Group',
                'Loyalty Plan',
                'Loyalty Key',
                'Supervisor Free Contract',
            ],
        ],
        [
            'moduleid' => 7,
            'modulename' => 'Merchandizing',
            'forms' => [
                'Survey',
                'Survey Plan',
                'Survey Key',
                'Pos Master',
                'Customer Pos Limit',
                'Pos Instruction',
                'Planogram',
                'Images Captured',
            ],
        ],
        [
            'moduleid' => 8,
            'modulename' => 'Links',
            'forms' => [
                'Category Key',
                'Promotion Link',
                'Special Price Link',
                'Survey Link',
                'Outlet Product Code',
                'Active/Inactive Items',
                'Planogram Key',
                'Items Group',
            ],
        ],
        [
            'moduleid' => 9,
            'modulename' => 'Transaction',
            'forms' => [
                'Begin / Opening Stock',
                'Load',
                'Load Request',
                'Load Transfer',
                'Customer Inventory',
                'Invoice',
                'Sales Order',
                'Advance Payment',
                'AR Collection',
                'Unload Inventory',
                'Unload Variance',
                'Damage Return',
                'Inventory Summary',
            ],
        ],
        [
            'moduleid' => 10,
            'modulename' => 'Reports',
            'forms' => [
                'Route Summary',
                'Route Activity',
                'Route Inventory',
                'Route Trip Analysis',
                'Route Deposit Summary',
                'Discount Summary',
                'Pricing Summary',
                'Sales Summary',
                'Deposit Summary',
                'Order Summary',
                'Collection Summary',
                'Payment Summary',
                'Final Deposit',
                'Item History',
                'Route Visit Summary',
                'Bad Return Summary',
                'POS Tracking',
                'Survey Tracking',
                'Waste Stock',
                'Assets Availability',
                'Merchandized Stock',
                'Route Ageing',
                'Customer Ageing',
                'Route Pending Balance',
                'Customer Pending Balance',
                'Route Monthly Revenue',
                'Sales Free Summary',
                'Item Sales Summary',
                'Item Group Wise Sales',
            ],
        ],
        [
            'moduleid' => 11,
            'modulename' => 'Settings',
            'forms' => [
                'Basic Setup',
                'Control Panel',
                'Email Configuration',
                'Email Templates',
            ],
        ],
        [
            'moduleid' => 12,
            'modulename' => 'Operation',
            'forms' => [
                'Salesman',
                'Device',
                'Vehicle',
            ],
        ],
    ];

    public function run(): void
    {
        $nextFormId = ((int) ModuleDetail::max('formid')) + 1;

        foreach (self::MODULES as $module) {
            ModuleHeader::query()->updateOrCreate(
                ['moduleid' => $module['moduleid']],
                ['modulename' => $module['modulename']]
            );

            foreach ($module['forms'] as $index => $formName) {
                $existing = ModuleDetail::query()
                    ->whereRaw('LOWER(TRIM(formname)) = ?', [strtolower(trim($formName))])
                    ->first();

                $payload = [
                    'formname' => $formName,
                    'formdescription' => $formName,
                    'moduleid' => $module['moduleid'],
                    'order' => $index + 1,
                ];

                if ($existing) {
                    $existing->fill($payload)->save();
                    continue;
                }

                ModuleDetail::query()->insert([
                    'formid' => $nextFormId++,
                    ...$payload,
                ]);
            }
        }
    }
}
