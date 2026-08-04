<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class CurrencySeeder extends Seeder
{
    public function run(): void
    {
        DB::table('currencymaster')->updateOrInsert(
            ['alternatecode' => 'AED'],
            [
                'currencyname' => 'UAE Dirham',
                'arbcurrencyname' => 'UAE Dirham',
                'currencysymbol' => 'AED',
                'arbcurrencysymbol' => 'AED',
                'decimalplaces' => 2,
                'startdate' => now(),
                'enddate' => null,
                'created' => 'seed',
                'cdat' => now(),
                'modified' => 'seed',
                'mdat' => now(),
                'defaultcurrency' => 1,
            ]
        );
    }
}
