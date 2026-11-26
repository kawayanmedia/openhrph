<?php

namespace App\Services\Payroll;

use App\Jurisdictions\Philippines\Statutory\Gsis\GsisSchedule;
use App\Jurisdictions\Philippines\Statutory\Schemes\SchemeResolver;
use App\Jurisdictions\Philippines\Statutory\Schemes\SocialInsuranceScheme;
use App\Sectors\SectorManager;
use App\Support\Tenancy\TenantContext;

/**
 * Resolves the social-insurance scheme the active company remits to.
 *
 * Private-sector companies contribute to SSS and government agencies to GSIS,
 * so anything that reads or reports the social-insurance number must resolve
 * the column through the same sector policy payroll uses.
 */
final class SocialInsuranceResolver
{
    public static function current(): SocialInsuranceScheme
    {
        $companyId = app(TenantContext::class)->id();
        $agency = $companyId === null
            ? 'sss'
            : app(SectorManager::class)->forCompany($companyId)->socialInsuranceAgency();

        return SchemeResolver::make($agency, [], GsisSchedule::default());
    }
}
