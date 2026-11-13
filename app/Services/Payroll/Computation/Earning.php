<?php

namespace App\Services\Payroll\Computation;

/**
 * One additional earning or deduction line.
 */
final class Earning
{
    public const KIND_ALLOWANCE = 'allowance';

    public const KIND_COMPENSATION = 'compensation';

    public const KIND_BONUS = 'bonus';

    public const KIND_COMMISSION = 'commission';

    public const KIND_DEDUCTION = 'deduction';

    public const KIND_LOAN = 'loan';

    public function __construct(
        public string $code = '',
        public string $name = '',
        public string $kind = '',
        public int $amount = 0,
        public bool $taxable = true,
        public int $nonTaxableCeiling = 0,
        public bool $prorated = false,
        public string $proratedBy = '',
    ) {}

    public static function allowance(string $code, string $name, int $amount, bool $taxable = true): self
    {
        return new self(code: $code, name: $name, kind: self::KIND_ALLOWANCE, amount: $amount, taxable: $taxable);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            code: (string) ($data['code'] ?? ''),
            name: (string) ($data['name'] ?? ''),
            kind: (string) ($data['kind'] ?? ''),
            amount: (int) ($data['amount'] ?? 0),
            taxable: (bool) ($data['taxable'] ?? true),
            nonTaxableCeiling: (int) ($data['non_taxable_ceiling'] ?? 0),
            prorated: (bool) ($data['prorated'] ?? false),
            proratedBy: (string) ($data['prorated_by'] ?? ''),
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'code' => $this->code,
            'name' => $this->name,
            'kind' => $this->kind,
            'amount' => $this->amount,
            'taxable' => $this->taxable,
            'non_taxable_ceiling' => $this->nonTaxableCeiling,
            'prorated' => $this->prorated,
            'prorated_by' => $this->proratedBy,
        ];
    }
}
