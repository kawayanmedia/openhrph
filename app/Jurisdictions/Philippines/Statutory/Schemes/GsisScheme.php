<?php

namespace App\Jurisdictions\Philippines\Statutory\Schemes;

use App\Jurisdictions\Philippines\Statutory\Gsis\GsisContributionService;
use App\Jurisdictions\Philippines\Statutory\Gsis\GsisSchedule;

/**
 * GSIS / RA 8291 (public sector).
 */
final class GsisScheme implements SocialInsuranceScheme
{
    public function __construct(private readonly GsisSchedule $schedule) {}

    public function code(): string
    {
        return 'gsis';
    }

    public function label(): string
    {
        return 'GSIS';
    }

    public function identifierField(): string
    {
        return 'gsis_number';
    }

    public function identifierLabel(): string
    {
        return 'GSIS BP';
    }

    public function compute(int $monthlyBase): SocialInsuranceSplit
    {
        $gsis = (new GsisContributionService($this->schedule))->compute($monthlyBase);

        return new SocialInsuranceSplit(
            employee: $gsis->employee,
            employer: $gsis->employer,
        );
    }
}
