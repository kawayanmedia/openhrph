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
        $catalogueSector = self::resolveSector($sector);
        $policy = app(SectorManager::class)->for($catalogueSector);
        $country = Installation::countryCode();

        return [
            'employment_types' => EmploymentTypeCatalogue::seedDefaults($companyId, $policy->sector()),
            'leave_types' => LeaveTypeCatalogue::sync(null, $catalogueSector),
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
        $catalogueSector = self::resolveSector($sector);
        $policy = app(SectorManager::class)->for($catalogueSector);
        $totals = [
            'companies' => 0,
            'employment_types' => 0,
            'leave_types' => LeaveTypeCatalogue::sync(null, $catalogueSector),
            'deductions' => 0,
            'loans' => 0,
            'compensations' => CompensationCatalogue::sync(null, $policy->sector()),
        ];

        DB::table('companies')->orderBy('id')->pluck('id')->each(function ($companyId) use ($catalogueSector, &$totals): void {
            $counts = self::seedForCompany((int) $companyId, $catalogueSector);

            $totals['companies']++;
            $totals['employment_types'] += $counts['employment_types'];
            $totals['deductions'] += $counts['deductions'];
            $totals['loans'] += $counts['loans'];
        });

        return $totals;
    }

    /**
     * Resolve the sector to seed. Before the installer runs there is no sector,
     * so only the catalogue rows both sectors share are seeded.
     */
    private static function resolveSector(Sector|string|null $sector): ?Sector
    {
        return match (true) {
            $sector instanceof Sector => $sector,
            is_string($sector) && trim($sector) !== '' => Sector::fromValue($sector),
            default => Installation::sectorEnum(),
        };
    }
}
