<?php

namespace App\Services\Payroll\Computation;

/**
 * One withholding-tax band for a pay frequency.
 */
final class TaxBracket
{
    public function __construct(
        public int $from = 0,
        public int $to = 0,
        public int $baseTax = 0,
        public float $rate = 0.0,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            from: (int) ($data['from'] ?? 0),
            to: (int) ($data['to'] ?? 0),
            baseTax: (int) ($data['base_tax'] ?? 0),
            rate: (float) ($data['rate'] ?? 0),
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'from' => $this->from,
            'to' => $this->to,
            'base_tax' => $this->baseTax,
            'rate' => $this->rate,
        ];
    }
}
