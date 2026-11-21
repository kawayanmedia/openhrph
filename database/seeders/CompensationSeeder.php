<?php

namespace Database\Seeders;

use App\Models\Company;
use App\Sectors\Catalogues\LeaveTypeCatalogue;
use App\Sectors\Catalogues\SectorCatalogue;
use App\Services\Payroll\RateCatalogue;
use App\Support\Installation\Installation;
use App\Support\Payroll\CompensationCatalogue;
use Database\Seeders\Concerns\SeedsDemoTenant;
use Illuminate\Database\Seeder;

class CompensationSeeder extends Seeder
{
    use SeedsDemoTenant;

    public function run(): void
    {
        // Null until `openhr:install` locks the sector. The catalogues then
        // seed only the rows both sectors share, and the sector's own rows.
        $catalogueSector = Installation::sectorEnum();
        $policySector = Installation::sectorPolicy()->sector();

        // The catalogues are system-wide, so they are seeded once outside any
        // company context.
        CompensationCatalogue::sync(null, $policySector);
        LeaveTypeCatalogue::sync(null, $catalogueSector);

        $this->runForDemoTenant(function (Company $company) use ($catalogueSector): void {
            $this->seedRates($company);
            SectorCatalogue::seedForCompany($company->getKey(), $catalogueSector);
        });
    }

    private function seedRates(Company $company): void
    {
        RateCatalogue::sync((int) $company->getKey());
    }
}
