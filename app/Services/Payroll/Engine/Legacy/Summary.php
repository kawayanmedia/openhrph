<?php

namespace App\Services\Payroll\Engine\Legacy;

use App\Services\Payroll\Computation\Money;

/**
 * The aggregate result of writing a payroll run.
 */
final class Summary
{
    /**
     * @param  list<string>  $warnings
     */
    public function __construct(
        public int $payrollID = 0,
        public int $employeeCount = 0,
        public int $gross = 0,
        public int $deductions = 0,
        public int $net = 0,
        public int $revision = 0,
        public array $warnings = [],
    ) {}

    public function grossPesos(): string
    {
        return Money::format($this->gross);
    }

    public function netPesos(): string
    {
        return Money::format($this->net);
    }

    public function deductionsPesos(): string
    {
        return Money::format($this->deductions);
    }
}
