<?php

namespace App\Services\Government\PagIbig;

use App\Support\Money;

/**
 * The HDMF (Pag-IBIG) contribution schedule. Amounts are centavos.
 */
final class PagIbigSchedule
{
    public function __construct(
        public float $rate = 0.02,
        public float $lowRate = 0.01,
        public int $lowThreshold = 150000,
        public int $maxFundSalary = 1000000,
    ) {}

    /**
     * The 2% schedule capped at a ₱10,000 fund salary (₱200 maximum a side),
     * with 1% for members earning at or below ₱1,500.
     */
    public static function default(): self
    {
        return new self(
            rate: 0.02,
            lowRate: 0.01,
            lowThreshold: Money::fromPesos(1500),
            maxFundSalary: Money::fromPesos(10000),
        );
    }

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
