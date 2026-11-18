<?php

namespace Database\Seeders;

use App\Support\Installation\Installation;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Artisan;

class StatutorySeeder extends Seeder
{
    public function run(): void
    {
        $country = Installation::countryCode();

        Artisan::call('payroll:statutory:import', ['--country' => $country]);

        // Wage orders are the private-sector minimum-wage instrument; Government
        // compensation follows the DBM salary schedule instead.
        if (Installation::statutoryDriver($country) === 'ph' && ! Installation::isGovernment()) {
            Artisan::call('payroll:wage-orders:import');
        }
    }
}
