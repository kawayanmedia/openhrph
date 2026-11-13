<?php

namespace App\Services\Payroll\Computation;

/**
 * Payroll earning rules that are not statutory: day-type premiums, overtime,
 * night differential, absence handling and 13th-month accrual.
 *
 * Mirrors payroll-engine/internal/rules.
 */
final class Rules
{
    public const DAY_REGULAR = 'regular';

    public const DAY_REST = 'rest_day';

    public const DAY_SPECIAL_HOLIDAY = 'special_holiday';

    public const DAY_REGULAR_HOLIDAY = 'regular_holiday';

    public const DAY_REST_SPECIAL_HOLIDAY = 'rest_day_special_holiday';

    public const DAY_REST_REGULAR_HOLIDAY = 'rest_day_regular_holiday';

    public const DAY_DOUBLE_HOLIDAY = 'double_holiday';

    public const DAY_REST_DOUBLE_HOLIDAY = 'rest_day_double_holiday';

    public const DAY_REGULAR_HOLIDAY_SPECIAL_HOLIDAY = 'regular_holiday_special_holiday';

    public const DAY_REST_REGULAR_HOLIDAY_SPECIAL_HOLIDAY = 'rest_day_regular_holiday_special_holiday';

    public const DAY_PAID_LEAVE = 'paid_leave';

    public const DAY_UNPAID_LEAVE = 'unpaid_leave';

    public const DAY_ABSENT = 'absent';

    public const DAY_UNDERTIME = 'undertime';

    /**
     * Codes written to timesheets.day_type (and rates_new.code) mapped to the
     * canonical keys above.
     *
     * @var array<string, string>
     */
    public const STORED_DAY_CODES = [
        'rd' => self::DAY_REST,
        'sh' => self::DAY_SPECIAL_HOLIDAY,
        'rh' => self::DAY_REGULAR_HOLIDAY,
        'rd_sh' => self::DAY_REST_SPECIAL_HOLIDAY,
        'rd_rh' => self::DAY_REST_REGULAR_HOLIDAY,
        '2rh' => self::DAY_DOUBLE_HOLIDAY,
        'rd_2rh' => self::DAY_REST_DOUBLE_HOLIDAY,
        'rh_sh' => self::DAY_REGULAR_HOLIDAY_SPECIAL_HOLIDAY,
        'rd_rh_sh' => self::DAY_REST_REGULAR_HOLIDAY_SPECIAL_HOLIDAY,
        'pl' => self::DAY_PAID_LEAVE,
        'upl' => self::DAY_UNPAID_LEAVE,
    ];

    /** Labor Code overtime premium (125% of the day rate). */
    public const OVERTIME_MULTIPLIER = 1.25;

    /** 10% premium for work between 22:00 and 06:00. */
    public const NIGHT_DIFFERENTIAL_RATE = 0.10;

    /** Tax-free ceiling for 13th-month pay and other benefits. */
    public const THIRTEENTH_MONTH_EXEMPTION = 9000000;

    public static function dayMultiplier(?string $dayType): float
    {
        return match (self::normalize($dayType)) {
            self::DAY_REST => 1.30,
            self::DAY_SPECIAL_HOLIDAY => 1.30,
            self::DAY_REST_SPECIAL_HOLIDAY => 1.50,
            self::DAY_REGULAR_HOLIDAY => 2.00,
            self::DAY_REST_REGULAR_HOLIDAY => 2.60,
            self::DAY_DOUBLE_HOLIDAY => 3.00,
            self::DAY_REST_DOUBLE_HOLIDAY => 3.90,
            self::DAY_REGULAR_HOLIDAY_SPECIAL_HOLIDAY => 2.60,
            self::DAY_REST_REGULAR_HOLIDAY_SPECIAL_HOLIDAY => 3.00,
            default => 1.00,
        };
    }

    public static function isHoliday(?string $dayType): bool
    {
        return in_array(self::normalize($dayType), [
            self::DAY_SPECIAL_HOLIDAY,
            self::DAY_REGULAR_HOLIDAY,
            self::DAY_REST_SPECIAL_HOLIDAY,
            self::DAY_REST_REGULAR_HOLIDAY,
            self::DAY_DOUBLE_HOLIDAY,
            self::DAY_REST_DOUBLE_HOLIDAY,
            self::DAY_REGULAR_HOLIDAY_SPECIAL_HOLIDAY,
            self::DAY_REST_REGULAR_HOLIDAY_SPECIAL_HOLIDAY,
        ], true);
    }

    public static function isRestDay(?string $dayType): bool
    {
        return self::normalize($dayType) === self::DAY_REST;
    }

    public static function isRegularHoliday(?string $dayType): bool
    {
        return in_array(self::normalize($dayType), [
            self::DAY_REGULAR_HOLIDAY,
            self::DAY_REST_REGULAR_HOLIDAY,
            self::DAY_DOUBLE_HOLIDAY,
            self::DAY_REST_DOUBLE_HOLIDAY,
            self::DAY_REGULAR_HOLIDAY_SPECIAL_HOLIDAY,
            self::DAY_REST_REGULAR_HOLIDAY_SPECIAL_HOLIDAY,
        ], true);
    }

    public static function isSpecialHoliday(?string $dayType): bool
    {
        return in_array(self::normalize($dayType), [
            self::DAY_SPECIAL_HOLIDAY,
            self::DAY_REST_SPECIAL_HOLIDAY,
            self::DAY_REGULAR_HOLIDAY_SPECIAL_HOLIDAY,
            self::DAY_REST_REGULAR_HOLIDAY_SPECIAL_HOLIDAY,
        ], true);
    }

    /**
     * Only the premium part of the day multiplier (e.g. 0.30 for a rest day).
     */
    public static function premiumPortion(?string $dayType): float
    {
        return self::dayMultiplier($dayType) - 1;
    }

    public static function isPaidDay(?string $dayType): bool
    {
        return self::normalize($dayType) === self::DAY_PAID_LEAVE;
    }

    public static function isUnpaidDay(?string $dayType): bool
    {
        return in_array(self::normalize($dayType), [self::DAY_UNPAID_LEAVE, self::DAY_ABSENT], true);
    }

    public static function normalize(?string $dayType): string
    {
        $key = strtolower(trim((string) $dayType));
        $key = str_replace(['-', ' '], '_', $key);

        return self::STORED_DAY_CODES[$key] ?? $key;
    }

    public static function dailyRate(int $monthly, int $daysPerYear): int
    {
        if ($daysPerYear <= 0) {
            $daysPerYear = 261;
        }

        return Money::mul($monthly, 12 / $daysPerYear);
    }

    public static function hourlyRate(int $monthly, int $daysPerYear, int $hoursPerDay): int
    {
        if ($hoursPerDay <= 0) {
            $hoursPerDay = 8;
        }

        return Money::mul(self::dailyRate($monthly, $daysPerYear), 1 / $hoursPerDay);
    }

    public static function linePay(int $hourly, float $hours, ?string $dayType, bool $overtime): int
    {
        if ($hours <= 0) {
            return Money::ZERO;
        }

        $rate = self::dayMultiplier($dayType);

        if ($overtime) {
            $rate *= self::OVERTIME_MULTIPLIER;
        }

        return Money::mul($hourly, $hours * $rate);
    }

    public static function nightDifferential(int $hourly, float $hours, ?string $dayType): int
    {
        if ($hours <= 0) {
            return Money::ZERO;
        }

        return Money::mul($hourly, $hours * self::dayMultiplier($dayType) * self::NIGHT_DIFFERENTIAL_RATE);
    }

    public static function absenceDeduction(int $daily, float $days): int
    {
        if ($days <= 0) {
            return Money::ZERO;
        }

        return Money::mul($daily, $days);
    }

    /**
     * 1/12 of the basic salary earned in the year.
     */
    public static function thirteenthMonth(int $totalBasicForYear): int
    {
        return Money::round($totalBasicForYear / 12);
    }

    /**
     * @return array{0: int, 1: int} exempt and taxable portions
     */
    public static function splitThirteenthMonth(int $amount): array
    {
        if ($amount <= self::THIRTEENTH_MONTH_EXEMPTION) {
            return [$amount, 0];
        }

        return [self::THIRTEENTH_MONTH_EXEMPTION, $amount - self::THIRTEENTH_MONTH_EXEMPTION];
    }

    /**
     * Round half up on a float, mirroring the payroll package helper.
     */
    public static function roundHalfUp(float $value): int
    {
        return $value < 0
            ? -(int) floor(-$value + 0.5)
            : (int) floor($value + 0.5);
    }
}
