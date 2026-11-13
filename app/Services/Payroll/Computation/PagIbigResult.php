<?php

namespace App\Services\Payroll\Computation;

/**
 * The monthly HDMF split.
 */
final class PagIbigResult
{
    public function __construct(
        public int $base = 0,
        public int $employee = 0,
        public int $employer = 0,
    ) {}
}
