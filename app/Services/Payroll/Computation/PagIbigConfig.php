<?php

namespace App\Services\Payroll\Computation;

/**
 * The HDMF (Pag-IBIG) contribution schedule.
 */
final class PagIbigConfig
{
    public function __construct(
        public float $rate = 0.02,
        public float $lowRate = 0.01,
        public int $lowThreshold = 150000,
        public int $maxFundSalary = 1000000,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            rate: (float) ($data['rate'] ?? 0.02),
            lowRate: (float) ($data['low_rate'] ?? 0.01),
            lowThreshold: (int) ($data['low_threshold'] ?? 150000),
            maxFundSalary: (int) ($data['max_fund_salary'] ?? 1000000),
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'rate' => $this->rate,
            'low_rate' => $this->lowRate,
            'low_threshold' => $this->lowThreshold,
            'max_fund_salary' => $this->maxFundSalary,
        ];
    }
}
