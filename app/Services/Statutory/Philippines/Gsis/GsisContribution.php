<?php

namespace App\Services\Statutory\Philippines\Gsis;

use App\Support\Money;

/**
 * The monthly GSIS premium split for one employee, in centavos.
 */
final class GsisContribution
{
    public function __construct(
        public int $base = 0,
        public int $employee = 0,
        public int $employer = 0,
    ) {}

    public function total(): int
    {
        return Money::add($this->employee, $this->employer);
    }
}
