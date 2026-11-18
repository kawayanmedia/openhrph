<?php

namespace Database\Seeders;

use App\Sectors\Catalogues\LeaveTypeCatalogue;
use App\Support\Installation\Installation;
use App\Support\Payroll\CompensationCatalogue;
use Illuminate\Database\Seeder;

/**
 * Seeds the system-wide catalogues a fresh deployment needs.
 *
 * Unlike DatabaseSeeder this does not create a demo tenant, so it is safe to
 * run on every boot and after an upgrade. The rows are idempotent, and only the
 * defaults plus the configured sector's rows are created.
 */
class ReferenceDataSeeder extends Seeder
{
    public function run(): void
    {
        $sector = Installation::sectorEnum();

        LeaveTypeCatalogue::sync(null, $sector);
        CompensationCatalogue::sync(null, $sector);
    }
}
