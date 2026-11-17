<?php

namespace App\Services\Payroll\Computation;

/**
 * One effective-dated regional/industry wage order.
 */
final class MinimumWage
{
    public function __construct(
        public string $region = '',
        public string $industry = '',
        public string $wageOrder = '',
        public int $amount = 0,
        public string $effectiveFrom = '',
        public string $effectiveTo = '',
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            region: (string) ($data['region'] ?? ''),
            industry: (string) ($data['industry'] ?? ''),
            wageOrder: (string) ($data['wage_order'] ?? ''),
            amount: (int) ($data['amount'] ?? 0),
            effectiveFrom: (string) ($data['effective_from'] ?? ''),
            effectiveTo: (string) ($data['effective_to'] ?? ''),
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'region' => $this->region,
            'industry' => $this->industry,
            'wage_order' => $this->wageOrder,
            'amount' => $this->amount,
            'effective_from' => $this->effectiveFrom,
            'effective_to' => $this->effectiveTo,
        ];
    }
}
