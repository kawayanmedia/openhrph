<?php

namespace App\Services\Payroll\Computation;

/**
 * The monthly PhilHealth premium split.
 */
final class PhilHealthResult
{
    public function __construct(
        public int $base = 0,
        public int $premium = 0,
        public int $employee = 0,
        public int $employer = 0,
    ) {}
}
