<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            CompanySeeder::class,
            OrganizationSeeder::class,
            GovernmentReferenceSeeder::class,
            PlantillaItemSeeder::class,
            CompensationSeeder::class,
            StatutorySeeder::class,
        ]);
    }
}
