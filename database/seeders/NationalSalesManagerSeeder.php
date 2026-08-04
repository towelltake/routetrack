<?php

namespace Database\Seeders;

use App\Models\CompanyMaster;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class NationalSalesManagerSeeder extends Seeder
{
    public function run(): void
    {
        $company = CompanyMaster::query()
            ->where('alternatecmpycode', 'TRAC')
            ->orWhere('name', 'TRAC Trading')
            ->orderBy('cmpycode')
            ->first();

        if (!$company) {
            return;
        }

        DB::table('nationalsalesmanager')->updateOrInsert(
            ['alternatecode' => 'NSM001'],
            [
                'parentcompany' => $company->cmpycode,
                'nationalsalesmanagername' => 'Default National Sales Manager',
                'arbnationalsalesmanagername' => 'Default National Sales Manager',
                'created' => 'seed',
                'cdat' => now(),
                'modified' => 'seed',
                'mdat' => now(),
                'activestatus' => 1,
            ]
        );

        $managerId = DB::table('nationalsalesmanager')
            ->where('alternatecode', 'NSM001')
            ->value('nationalsalesmanagercode');

        $companyUpdate = collect([
            'nationalsalesmanagercode' => $managerId,
            'modified' => 'seed',
            'mdat' => now(),
        ])
            ->filter(fn ($value, $column) => Schema::hasColumn('company', $column))
            ->all();

        if ($companyUpdate !== []) {
            DB::table('company')
                ->where('cmpycode', $company->cmpycode)
                ->update($companyUpdate);
        }
    }
}
