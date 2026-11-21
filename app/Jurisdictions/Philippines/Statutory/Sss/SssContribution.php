<?php

namespace App\Jurisdictions\Philippines\Statutory\Sss;

use App\Support\Money;

/**
 * The monthly SSS split for one employee, in centavos.
 */
final class SssContribution
{
    public function __construct(
        public int $msc = 0,
        public int $employee = 0,
        public int $employer = 0,
        public int $ec = 0,
        public int $wispEmployee = 0,
        public int $wispEmployer = 0,
    ) {}

    /**
     * The employee's monthly contribution including WISP/MPF.
     */
    public function employeeShare(): int
    {
        return Money::add($this->employee, $this->wispEmployee);
    }

    /**
     * The employer's monthly contribution including EC and WISP.
     */
    public function employerShare(): int
    {
        return Money::add(Money::add($this->employer, $this->ec), $this->wispEmployer);
    }

    public function total(): int
    {
        return Money::add($this->employeeShare(), $this->employerShare());
    }
}
