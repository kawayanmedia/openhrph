<?php

namespace App\Services\Payroll\Computation;

/**
 * One non-taxable benefit ceiling.
 */
final class DeMinimisBenefit
{
    public const BASIS_MONTHLY = 'monthly';

    public const BASIS_ANNUAL = 'annual';

    public const BASIS_SEMESTER = 'semester';

    public const BASIS_HOURLY_OT = 'hourly_ot';

    /**
     * Percentage of the regional minimum wage for the hourly_ot basis. RR 29-2025
     * sets the overtime/night meal allowance at 30% of the minimum wage.
     */
    public const DEFAULT_HOURLY_OT_PERCENTAGE = 30;

    public function __construct(
        public string $code = '',
        public int $ceiling = 0,
        public string $basis = self::BASIS_MONTHLY,
        public int $percentage = self::DEFAULT_HOURLY_OT_PERCENTAGE,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            code: (string) ($data['code'] ?? ''),
            ceiling: (int) ($data['ceiling'] ?? 0),
            basis: (string) ($data['basis'] ?? self::BASIS_MONTHLY),
            percentage: (int) ($data['percentage'] ?? self::DEFAULT_HOURLY_OT_PERCENTAGE),
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'code' => $this->code,
            'ceiling' => $this->ceiling,
            'basis' => $this->basis,
            'percentage' => $this->percentage,
        ];
    }
}
