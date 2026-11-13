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

    public function __construct(
        public string $code = '',
        public int $ceiling = 0,
        public string $basis = self::BASIS_MONTHLY,
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
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return ['code' => $this->code, 'ceiling' => $this->ceiling, 'basis' => $this->basis];
    }
}
