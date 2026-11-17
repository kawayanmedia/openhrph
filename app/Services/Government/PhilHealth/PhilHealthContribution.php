<?php

namespace App\Services\Government\PhilHealth;

/**
 * The monthly PhilHealth premium split, in centavos.
 */
final class PhilHealthContribution
{
    public function __construct(
        public int $base = 0,
        public int $premium = 0,
        public int $employee = 0,
        public int $employer = 0,
    ) {}
}
