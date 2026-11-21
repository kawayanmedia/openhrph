<?php

namespace App\Jurisdictions\Support;

use Illuminate\Support\Str;

/**
 * Country-neutral day-type vocabulary shared by the payroll engines.
 */
final class DayType
{
    public const REGULAR = 'regular';

    public const REST = 'rest_day';

    public const SPECIAL_HOLIDAY = 'special_holiday';

    public const REGULAR_HOLIDAY = 'regular_holiday';

    public const REST_SPECIAL_HOLIDAY = 'rest_day_special_holiday';

    public const REST_REGULAR_HOLIDAY = 'rest_day_regular_holiday';

    public const DOUBLE_HOLIDAY = 'double_holiday';

    public const REST_DOUBLE_HOLIDAY = 'rest_day_double_holiday';

    public const REGULAR_HOLIDAY_SPECIAL_HOLIDAY = 'regular_holiday_special_holiday';

    public const REST_REGULAR_HOLIDAY_SPECIAL_HOLIDAY = 'rest_day_regular_holiday_special_holiday';

    public const PAID_LEAVE = 'paid_leave';

    public const UNPAID_LEAVE = 'unpaid_leave';

    public const ABSENT = 'absent';

    public const UNDERTIME = 'undertime';

    /**
     * Stored timesheet/rate codes mapped to the canonical vocabulary.
     *
     * @var array<string, string>
     */
    public const STORED_CODES = [
        'rd' => self::REST,
        'sh' => self::SPECIAL_HOLIDAY,
        'rh' => self::REGULAR_HOLIDAY,
        'rd_sh' => self::REST_SPECIAL_HOLIDAY,
        'rd_rh' => self::REST_REGULAR_HOLIDAY,
        '2rh' => self::DOUBLE_HOLIDAY,
        'rd_2rh' => self::REST_DOUBLE_HOLIDAY,
        'rh_sh' => self::REGULAR_HOLIDAY_SPECIAL_HOLIDAY,
        'rd_rh_sh' => self::REST_REGULAR_HOLIDAY_SPECIAL_HOLIDAY,
        'pl' => self::PAID_LEAVE,
        'upl' => self::UNPAID_LEAVE,
    ];

    public static function normalize(?string $dayType): string
    {
        $key = Str::of((string) $dayType)->lower()->trim()->replace(['-', ' '], '_')->toString();

        return self::STORED_CODES[$key] ?? $key;
    }

    public static function isHoliday(?string $dayType): bool
    {
        return in_array(self::normalize($dayType), [
            self::SPECIAL_HOLIDAY,
            self::REGULAR_HOLIDAY,
            self::REST_SPECIAL_HOLIDAY,
            self::REST_REGULAR_HOLIDAY,
            self::DOUBLE_HOLIDAY,
            self::REST_DOUBLE_HOLIDAY,
            self::REGULAR_HOLIDAY_SPECIAL_HOLIDAY,
            self::REST_REGULAR_HOLIDAY_SPECIAL_HOLIDAY,
        ], true);
    }

    public static function isRestDay(?string $dayType): bool
    {
        return self::normalize($dayType) === self::REST;
    }

    public static function isRegularHoliday(?string $dayType): bool
    {
        return in_array(self::normalize($dayType), [
            self::REGULAR_HOLIDAY,
            self::REST_REGULAR_HOLIDAY,
            self::DOUBLE_HOLIDAY,
            self::REST_DOUBLE_HOLIDAY,
            self::REGULAR_HOLIDAY_SPECIAL_HOLIDAY,
            self::REST_REGULAR_HOLIDAY_SPECIAL_HOLIDAY,
        ], true);
    }

    public static function isSpecialHoliday(?string $dayType): bool
    {
        return in_array(self::normalize($dayType), [
            self::SPECIAL_HOLIDAY,
            self::REST_SPECIAL_HOLIDAY,
            self::REGULAR_HOLIDAY_SPECIAL_HOLIDAY,
            self::REST_REGULAR_HOLIDAY_SPECIAL_HOLIDAY,
        ], true);
    }

    public static function isPaidDay(?string $dayType): bool
    {
        return self::normalize($dayType) === self::PAID_LEAVE;
    }

    public static function isUnpaidDay(?string $dayType): bool
    {
        return in_array(self::normalize($dayType), [self::UNPAID_LEAVE, self::ABSENT], true);
    }
}
