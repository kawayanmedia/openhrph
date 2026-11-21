<?php

namespace App\Jurisdictions\Philippines\Calculators;

use App\Enums\Sector;

/**
 * Mandatory employee and employer contributions for one payroll period.
 * Amounts are centavos.
 */
final class StatutoryContributionResult
{
    public function __construct(
        public Sector $sector,
        public int $socialInsuranceEmployee = 0,
        public int $socialInsuranceEmployer = 0,
        public int $philHealthEmployee = 0,
        public int $philHealthEmployer = 0,
        public int $pagIbigEmployee = 0,
        public int $pagIbigEmployer = 0,
    ) {}

    public function agency(): string
    {
        return $this->sector->isGovernment() ? 'GSIS' : 'SSS';
    }

    public function totalEmployee(): int
    {
        return $this->socialInsuranceEmployee
            + $this->philHealthEmployee
            + $this->pagIbigEmployee;
    }

    public function totalEmployer(): int
    {
        return $this->socialInsuranceEmployer
            + $this->philHealthEmployer
            + $this->pagIbigEmployer;
    }
}
