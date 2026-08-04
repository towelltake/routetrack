<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class CompanySeeder extends Seeder
{
    public function run(): void
    {
        $payload = $this->filterColumns('company', [
            'alternatecmpycode' => 'TRAC',
            'name' => 'TRAC Trading',
            'address' => 'Dubai',
            'telephone' => '0000000000',
            'fax' => null,
            'nationalsalesmanagercode' => null,
            'contactname' => 'System Administrator',
            'city' => 'Dubai',
            'country' => 'United Arab Emirates',
            'arbcompanyname' => 'TRAC Trading',
            'created' => 'seed',
            'cdat' => now(),
            'modified' => 'seed',
            'mdat' => now(),
            'zipcode' => null,
            'countrycode' => null,
            'countryname' => 'United Arab Emirates',
            'arbcountryname' => 'United Arab Emirates',
            'taxregistrationnumber' => null,
            'distributorcode' => 'TRAC',
            'activestatus' => 1,
            'parentcompany' => null,
        ]);

        $lookup = array_key_exists('alternatecmpycode', $payload)
            ? ['alternatecmpycode' => 'TRAC']
            : ['name' => 'TRAC Trading'];

        DB::table('company')->updateOrInsert($lookup, $payload);
    }

    private function filterColumns(string $table, array $payload): array
    {
        return collect($payload)
            ->filter(fn ($value, $column) => Schema::hasColumn($table, $column))
            ->all();
    }
}
