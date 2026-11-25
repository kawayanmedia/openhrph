<?php

namespace App\Jurisdictions\Philippines\Statutory\Schemes;

/**
 * The mandatory social-insurance scheme an employer remits.
 *
 * Government agencies remit GSIS; private establishments remit SSS. The two
 * are mutually exclusive. Consumer code asks the scheme instead of testing the
 * sector, and the scheme receives its effective-dated schedule from the caller
 * so a historical payroll stays reproducible.
 */
interface SocialInsuranceScheme
{
    public function code(): string;

    public function label(): string;

    /** Employee column that carries the membership number, e.g. sss_number. */
    public function identifierField(): string;

    public function identifierLabel(): string;

    public function compute(int $monthlyBase): SocialInsuranceSplit;
}
