<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            CurrencySeeder::class,
            CompanySeeder::class,
            NationalSalesManagerSeeder::class,
            CountrySeeder::class,
            ModuleCatalogSeeder::class,
            AdminBootstrapSeeder::class,
        ]);
    }
}
