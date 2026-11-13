<?php

namespace App\Services\Payroll\Engine\Legacy;

/**
 * The payroll run being processed.
 *
 * Mirrors db.Run in payroll-engine/internal/db.
 */
final class Run
{
    /**
     * @param  array<string, string>  $meta
     */
    public function __construct(
        public int $payrollID = 0,
        public int $companyID = 0,
        public int $groupID = 0,
        public string $status = '',
        public string $type = 'regular',
        public string $cutoff = '',
        public string $dateFrom = '',
        public string $dateTo = '',
        public string $payrollDate = '',
        public array $meta = [],
    ) {}

    public function year(): int
    {
        return (int) substr($this->payrollDate, 0, 4);
    }
}
