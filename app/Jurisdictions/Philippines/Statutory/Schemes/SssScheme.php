<?php

namespace App\Jurisdictions\Philippines\Statutory\Schemes;

use App\Jurisdictions\Philippines\Statutory\Sss\SssBracket;
use App\Jurisdictions\Philippines\Statutory\Sss\SssContributionService;

/**
 * SSS / RA 11199 (private sector).
 */
final class SssScheme implements SocialInsuranceScheme
{
    /**
     * @param  list<SssBracket>  $brackets
     */
    public function __construct(private readonly array $brackets) {}

    public function code(): string
    {
        return 'sss';
    }

    public function label(): string
    {
        return 'SSS';
    }

    public function identifierField(): string
    {
        return 'sss_number';
    }

    public function identifierLabel(): string
    {
        return 'SSS';
    }

    public function compute(int $monthlyBase): SocialInsuranceSplit
    {
        $sss = (new SssContributionService($this->brackets))->compute($monthlyBase);

        return new SocialInsuranceSplit(
            employee: $sss->employee,
            employer: $sss->employer,
            providentEmployee: $sss->wispEmployee,
            providentEmployer: $sss->wispEmployer,
            employerEc: $sss->ec,
        );
    }
}
