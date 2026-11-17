<?php

namespace Database\Seeders;

use App\Models\Company;
use App\Models\Rate;
use App\Sectors\Catalogues\SectorCatalogue;
use App\Support\Installation\Installation;
use App\Support\Payroll\CompensationCatalogue;
use Database\Seeders\Concerns\SeedsDemoTenant;
use Illuminate\Database\Seeder;

class CompensationSeeder extends Seeder
{
    use SeedsDemoTenant;

    public function run(): void
    {
        $sector = Installation::sectorPolicy()->sector();

        // The compensation catalogue is system-wide, so it is seeded once
        // outside any company context.
        CompensationCatalogue::sync(null, $sector);

        $this->runForDemoTenant(function (Company $company) use ($sector): void {
            $this->seedRates($company);
            SectorCatalogue::seedForCompany($company->getKey(), $sector);
        });
    }

    private function seedRates(Company $company): void
    {
        $rates = [
            ['regular', 'Regular Day', false, false, 1.00, 1.25, 1.10, 1.375],
            ['rd', 'Rest Day', true, false, 1.30, 1.69, 1.43, 1.859],
            ['sh', 'Special Holiday', false, true, 1.30, 1.69, 1.43, 1.859],
            ['rh', 'Regular Holiday', false, true, 2.00, 2.60, 2.20, 2.86],
            ['rd-sh', 'Rest Day - Special Holiday', true, true, 1.50, 1.95, 1.65, 2.145],
            ['rd-rh', 'Rest Day - Regular Holiday', true, true, 2.60, 3.38, 2.86, 3.718],
            ['2rh', 'Double Regular Holiday', false, true, 3.00, 3.90, 3.30, 4.29],
            ['rd-2rh', 'Rest Day - Double Regular Holiday', true, true, 3.90, 5.07, 4.29, 5.577],
            ['rh-sh', 'Regular Holiday, Special Holiday', false, true, 2.60, 3.38, 2.86, 3.718],
            ['rd-rh-sh', 'Rest Day - Regular Holiday, Special Holiday', false, true, 3.00, 3.90, 3.30, 4.29],
            ['pl', 'Paid Leave', false, false, 1.00, 1.00, 1.00, 1.00],
            ['upl', 'Unpaid Leave', false, false, 1.00, 1.00, 1.00, 1.00],
        ];

        foreach ($rates as [$code, $dayType, $restDay, $holiday, $regular, $ot, $nt, $ntOt]) {
            $this->upsertModel(
                Rate::class,
                ['company_id' => $company->getKey(), 'code' => $code],
                [
                    'day_type' => $dayType,
                    'is_rest_day' => $restDay,
                    'is_holiday' => $holiday,
                    'regular' => $regular,
                    'ot' => $ot,
                    'nt' => $nt,
                    'nt_ot' => $ntOt,
                ],
            );
        }
    }
}
