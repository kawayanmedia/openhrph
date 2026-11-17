<?php

namespace App\Services\Statutory\Philippines\PhilHealth;

use App\Support\Money;

/**
 * The PhilHealth premium schedule in force. Amounts are centavos.
 */
final class PhilHealthSchedule
{
    public function __construct(
        public float $rate = 0.05,
        public int $floor = 1000000,
        public int $ceiling = 10000000,
    ) {}

    /**
     * The 5% schedule with a ₱10,000 floor and ₱100,000 ceiling.
     */
    public static function default(): self
    {
        return new self(
            rate: 0.05,
            floor: Money::fromPesos(10000),
            ceiling: Money::fromPesos(100000),
        );
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            rate: (float) ($data['rate'] ?? 0.05),
            floor: (int) ($data['floor'] ?? 1000000),
            ceiling: (int) ($data['ceiling'] ?? 10000000),
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return ['rate' => $this->rate, 'floor' => $this->floor, 'ceiling' => $this->ceiling];
    }
}
