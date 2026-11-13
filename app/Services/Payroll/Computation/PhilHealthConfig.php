<?php

namespace App\Services\Payroll\Computation;

/**
 * The PhilHealth premium schedule in force.
 */
final class PhilHealthConfig
{
    public function __construct(
        public float $rate = 0.05,
        public int $floor = 1000000,
        public int $ceiling = 10000000,
    ) {}

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
