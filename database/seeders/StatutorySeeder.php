<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Artisan;

class StatutorySeeder extends Seeder
{
    public function run(): void
    {
        Artisan::call('payroll:statutory:import');
        Artisan::call('payroll:wage-orders:import');
    }
}
