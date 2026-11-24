<?php

namespace App\Jurisdictions\Philippines\Statutory\Bir;

/**
 * Payroll periods covered by the BIR withholding tax tables.
 */
enum TaxPeriod: string
{
    case DAILY = 'daily';

    case WEEKLY = 'weekly';

    case SEMI_MONTHLY = 'semi-monthly';

    case MONTHLY = 'monthly';

    case ANNUAL = 'annual';

    public static function fromString(?string $value): ?self
    {
        $normalized = str_replace('_', '-', (string) $value |> trim(...) |> strtolower(...));

        return match ($normalized) {
            'daily' => self::DAILY,
            'weekly' => self::WEEKLY,
            'semi-monthly', 'semimonthly' => self::SEMI_MONTHLY,
            'monthly' => self::MONTHLY,
            'annual', 'annually' => self::ANNUAL,
            default => null,
        };
    }

    /**
     * The number of payroll periods in a calendar year, as used by the BIR.
     */
    public function periodsPerYear(): int
    {
        return match ($this) {
            self::DAILY => 264,
            self::WEEKLY => 66,
            self::SEMI_MONTHLY => 24,
            self::MONTHLY => 12,
            self::ANNUAL => 1,
        };
    }

    public function label(): string
    {
        return match ($this) {
            self::DAILY => 'Daily',
            self::WEEKLY => 'Weekly',
            self::SEMI_MONTHLY => 'Semi-monthly',
            self::MONTHLY => 'Monthly',
            self::ANNUAL => 'Annual',
        };
    }
}
