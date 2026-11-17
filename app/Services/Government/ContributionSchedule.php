<?php

namespace App\Services\Government;

/**
 * When a statutory deduction is taken from the payroll period.
 */
enum ContributionSchedule: string
{
    case EVERY_PAY = 'every_pay';

    case FIRST_PAY = 'first_pay';

    case LAST_PAY = 'last_pay';

    /**
     * Map the values stored on payroll groups to a schedule. Legacy rows use
     * variants such as "everypay" or numeric cut-off ids. An unrecognised value
     * is treated as every pay so a bad row can never silently skip a
     * remittance.
     */
    public static function fromString(?string $value): self
    {
        $normalized = str_replace(['-', ' '], '_', strtolower(trim((string) $value)));

        return match ($normalized) {
            'first_pay', 'firstpay', '1' => self::FIRST_PAY,
            'last_pay', 'lastpay', '2' => self::LAST_PAY,
            default => self::EVERY_PAY,
        };
    }

    /**
     * How many parts of a monthly amount fall in this run. A zero divisor means
     * the deduction does not apply to this cut-off.
     */
    public function divisor(PayFrequency $frequency, ?string $cutoff): int
    {
        if ($this === self::EVERY_PAY) {
            return $frequency->periodsPerMonth();
        }

        return $this->value === (string) $cutoff ? 1 : 0;
    }
}
