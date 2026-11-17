<?php

namespace App\Services\Payroll\Computation;

/**
 * One country-neutral deduction line produced by a payroll run.
 */
final class DeductionLine
{
    public const SOURCE_STATUTORY = 'statutory';

    public const SOURCE_DEDUCTION = 'deduction';

    public const SOURCE_LOAN = 'loan';

    public function __construct(
        public string $code = '',
        public string $name = '',
        public string $category = 'contribution',
        public int $base = 0,
        public int $employee = 0,
        public int $employer = 0,
        public bool $reducesTaxableIncome = false,
        public string $source = self::SOURCE_STATUTORY,
        public int $sortOrder = 0,
    ) {}
}
