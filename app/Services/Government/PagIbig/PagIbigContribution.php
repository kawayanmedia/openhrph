<?php

namespace App\Services\Government\PagIbig;

/**
 * The monthly HDMF split, in centavos.
 */
final class PagIbigContribution
{
    public function __construct(
        public int $base = 0,
        public int $employee = 0,
        public int $employer = 0,
    ) {}
}
