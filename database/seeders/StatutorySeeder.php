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

        // Wage orders are the Philippine minimum-wage instrument.
        if (Installation::statutoryDriver($country) === 'ph') {
            Artisan::call('payroll:wage-orders:import');
        }
    }
}
