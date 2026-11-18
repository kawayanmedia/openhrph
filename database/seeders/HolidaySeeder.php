<?php

namespace Database\Seeders;

use App\Support\Installation\Installation;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Artisan;

/**
 * Imports the bundled holiday calendar for every company.
 *
 * The command is company-scoped, so rerunning it after creating a company is
 * safe.
 */
class HolidaySeeder extends Seeder
{
    public function run(): void
    {
        $country = Installation::countryCode();

        if (Installation::statutoryDriver($country) !== 'ph') {
            return;
        }

        Artisan::call('payroll:holidays:import', ['--year' => 2026]);
    }
}
