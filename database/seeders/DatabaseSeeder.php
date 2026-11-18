<?php

namespace Database\Seeders;

use App\Enums\Sector;
use App\Support\Installation\Installation;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            CompanySeeder::class,
            OrganizationSeeder::class,
            CompensationSeeder::class,
            StatutorySeeder::class,
            HolidaySeeder::class,
        ]);

        // Government-only reference data. Before `openhr:install` runs there is
        // no sector, so a fresh development database gets the full default set.
        $sector = Installation::sectorEnum();

        if ($sector === null || $sector === Sector::Government) {
            $this->call([
                GovernmentReferenceSeeder::class,
                PlantillaItemSeeder::class,
            ]);
        }
    }
}
