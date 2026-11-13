<?php

namespace App\Services\Payroll\Computation;

/**
 * The monthly SSS split for one employee.
 */
final class SssResult
{
    public function __construct(
        public int $msc = 0,
        public int $ee = 0,
        public int $er = 0,
        public int $ec = 0,
        public int $wispEE = 0,
        public int $wispER = 0,
    ) {}

    /**
     * The employee's monthly contribution including WISP/MPF.
     */
    public function employeeShare(): int
    {
        return Money::add($this->ee, $this->wispEE);
    }

    /**
     * The employer's monthly contribution including EC and WISP.
     */
    public function employerShare(): int
    {
        return Money::add(Money::add($this->er, $this->ec), $this->wispER);
    }

    public function total(): int
    {
        return Money::add($this->employeeShare(), $this->employerShare());
    }
}
