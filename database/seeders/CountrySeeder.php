<?php

namespace Database\Seeders;

use App\Models\CompanyMaster;
use App\Models\CountryMaster;
use App\Models\CurrencyMaster;
use App\Models\NationalSalesManager;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Schema;

class CountrySeeder extends Seeder
{
    public function run(): void
    {
        $company = CompanyMaster::query()->orderBy('cmpycode')->first();
        $currency = CurrencyMaster::query()->orderBy('currencycode')->first();

        if (!$company || !$currency) {
            return;
        }

        $nationalSalesManager = NationalSalesManager::query()
            ->orderBy('nationalsalesmanagercode')
            ->first();

        foreach ([
            ['alternatecode' => 'AE', 'countryname' => 'United Arab Emirates'],
            ['alternatecode' => 'OM', 'countryname' => 'Oman'],
        ] as $country) {
            $payload = collect([
                'countryname' => $country['countryname'],
                'arbcountryname' => $country['countryname'],
                'currencycode' => $currency->currencycode,
                'cmpycode' => $company->cmpycode,
                'pricechangevariance' => 0,
                'nationalsalesmanagercode' => $nationalSalesManager?->nationalsalesmanagercode,
                'created' => 'seed',
                'cdat' => now(),
                'modified' => 'seed',
                'mdat' => now(),
            ])
                ->filter(fn ($value, $column) => Schema::hasColumn('country', $column))
                ->all();

            CountryMaster::query()->updateOrCreate(
                ['alternatecode' => $country['alternatecode']],
                $payload
            );
        }
    }
}
