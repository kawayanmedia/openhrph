<?php

namespace App\Services\Government;

/**
 * How often a payroll group pays its employees.
 */
enum PayFrequency: string
{
    case MONTHLY = 'monthly';

    case SEMI_MONTHLY = 'semi-monthly';

    /**
     * Map the values stored on payroll groups to a frequency. Anything that is
     * not explicitly monthly is treated as semi-monthly, the default for
     * Philippine payroll.
     */
    public static function fromString(?string $value): self
    {
        return in_array(strtolower(trim((string) $value)), ['monthly', 'month'], true)
            ? self::MONTHLY
            : self::SEMI_MONTHLY;
    }

    public function periodsPerMonth(): int
    {
        return $this === self::MONTHLY ? 1 : 2;
    }

    public function periodsPerYear(): int
    {
        return $this === self::MONTHLY ? 12 : 24;
    }
}
