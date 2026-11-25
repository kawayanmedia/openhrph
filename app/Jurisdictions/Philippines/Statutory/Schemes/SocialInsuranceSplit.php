<?php

namespace App\Jurisdictions\Philippines\Statutory\Schemes;

/**
 * The components of one social-insurance assessment.
 */
final readonly class SocialInsuranceSplit
{
    public function __construct(
        public int $employee = 0,
        public int $employer = 0,
        public int $providentEmployee = 0,
        public int $providentEmployer = 0,
        public int $employerEc = 0,
    ) {}

    public function total(): int
    {
        return $this->employee + $this->employer + $this->providentEmployee + $this->providentEmployer + $this->employerEc;
    }
}
