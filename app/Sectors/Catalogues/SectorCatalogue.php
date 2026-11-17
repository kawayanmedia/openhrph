<?php

namespace App\Sectors\Catalogues;

use App\Enums\Sector;
use App\Sectors\SectorManager;
use App\Services\Payroll\Statutory\DeductionCatalogue;
use App\Support\Installation\Installation;
use App\Support\Payroll\CompensationCatalogue;
use App\Support\Payroll\LoanCatalogue;
use Illuminate\Support\Facades\DB;

/**
 * One entry point for every sector-dependent catalogue.
 *
 * Call this from the installer and from DatabaseSeeder so a company always
 * starts with the rows its sector actually uses.
 */
final class SectorCatalogue
{
    /**
     * @return array{employment_types: int, leave_types: int, deductions: int, loans: int, compensations: int}
     */
    public static function seedForCompany(int $companyId, Sector|string|null $sector = null): array
    {
        $policy = app(SectorManager::class)->for($sector);
        $country = Installation::countryCode();

        return [
            'employment_types' => EmploymentTypeCatalogue::seedDefaults($companyId, $policy->sector()),
            'leave_types' => LeaveTypeCatalogue::seedDefaults($companyId, $policy->sector()),
            'deductions' => DeductionCatalogue::seedDefaults($companyId, $country, $policy->sector()),
            'loans' => LoanCatalogue::seedDefaults($companyId, $country, $policy->sector()),
            'compensations' => CompensationCatalogue::sync(null, $policy->sector()),
        ];
    }

    /**
     * @return array{companies: int, employment_types: int, leave_types: int, deductions: int, loans: int, compensations: int}
     */
    public static function seedAll(Sector|string|null $sector = null): array
    {
        $policy = app(SectorManager::class)->for($sector);
        $totals = [
            'companies' => 0,
            'employment_types' => 0,
            'leave_types' => 0,
            'deductions' => 0,
            'loans' => 0,
            'compensations' => CompensationCatalogue::sync(null, $policy->sector()),
        ];

        DB::table('companies')->orderBy('id')->pluck('id')->each(function ($companyId) use ($policy, &$totals): void {
            $counts = self::seedForCompany((int) $companyId, $policy->sector());

            $totals['companies']++;
            $totals['employment_types'] += $counts['employment_types'];
            $totals['leave_types'] += $counts['leave_types'];
            $totals['deductions'] += $counts['deductions'];
            $totals['loans'] += $counts['loans'];
        });

        return $totals;
    }
}
