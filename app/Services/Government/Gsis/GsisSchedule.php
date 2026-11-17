<?php

namespace App\Services\Government\Gsis;

use App\Support\Money;

/**
 * The GSIS premium schedule in force. Amounts are centavos.
 *
 * RA 8291 Sec. 5(a): the member pays 9% and the employer 12% of the monthly
 * compensation. Compensation in excess of the Board-set Maximum AMC limit is
 * billed to the member at 2% instead of 9%; the employer rate does not change.
 * GSIS has no salary credit brackets, unlike SSS.
 */
final class GsisSchedule
{
    public function __construct(
        public float $employeeRate = 0.09,
        public float $employerRate = 0.12,
        public int $maxAmcLimit = 0,
        public float $aboveLimitEmployeeRate = 0.02,
        public bool $lifeInsuranceOnly = false,
    ) {}

    /**
     * Regular members: 9% personal and 12% government share on the actual
     * monthly compensation, with no ceiling.
     */
    public static function default(): self
    {
        return new self(
            employeeRate: 0.09,
            employerRate: 0.12,
            maxAmcLimit: Money::ZERO,
        );
    }

    /**
     * Members of the judiciary and constitutional commissions pay 3% personal
     * and 3% employer for life insurance coverage only (RA 8291 Sec. 5(a)).
     */
    public static function judiciaryLifeInsurance(): self
    {
        return new self(
            employeeRate: 0.03,
            employerRate: 0.03,
            maxAmcLimit: Money::ZERO,
            aboveLimitEmployeeRate: 0.03,
            lifeInsuranceOnly: true,
        );
    }

    /**
     * Whether a Maximum AMC limit applies. A zero limit means the whole
     * monthly compensation is billed at the regular member rate.
     */
    public function isCapped(): bool
    {
        return $this->maxAmcLimit > 0;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            employeeRate: (float) ($data['employee_rate'] ?? 0.09),
            employerRate: (float) ($data['employer_rate'] ?? 0.12),
            maxAmcLimit: (int) ($data['max_amc_limit'] ?? 0),
            aboveLimitEmployeeRate: (float) ($data['above_limit_employee_rate'] ?? 0.02),
            lifeInsuranceOnly: (bool) ($data['life_insurance_only'] ?? false),
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'employee_rate' => $this->employeeRate,
            'employer_rate' => $this->employerRate,
            'max_amc_limit' => $this->maxAmcLimit,
            'above_limit_employee_rate' => $this->aboveLimitEmployeeRate,
            'life_insurance_only' => $this->lifeInsuranceOnly,
        ];
    }
}
