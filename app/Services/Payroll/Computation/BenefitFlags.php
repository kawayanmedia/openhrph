<?php

namespace App\Services\Payroll\Computation;

/**
 * Employee-specific entitlements from employees.
 */
final class BenefitFlags
{
    public function __construct(
        public bool $restDayPay = false,
        public bool $regularHolidayPay = false,
        public bool $specialHolidayPay = false,
        public bool $premiumHolidayPay = false,
        public bool $regularHolidaySpecialHolidayPay = false,
        public bool $overtimePay = false,
        public bool $nightDifferential = false,
        public bool $deMinimis = false,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            restDayPay: (bool) ($data['rest_day_pay'] ?? false),
            regularHolidayPay: (bool) ($data['regular_holiday_pay'] ?? false),
            specialHolidayPay: (bool) ($data['special_holiday_pay'] ?? false),
            premiumHolidayPay: (bool) ($data['premium_holiday_pay'] ?? false),
            regularHolidaySpecialHolidayPay: (bool) ($data['regular_holiday_special_holiday_pay'] ?? false),
            overtimePay: (bool) ($data['overtime_pay'] ?? false),
            nightDifferential: (bool) ($data['night_differential'] ?? false),
            deMinimis: (bool) ($data['deminimis'] ?? ($data['de_minimis'] ?? false)),
        );
    }

    /**
     * @return array<string, bool>
     */
    public function toArray(): array
    {
        return [
            'rest_day_pay' => $this->restDayPay,
            'regular_holiday_pay' => $this->regularHolidayPay,
            'special_holiday_pay' => $this->specialHolidayPay,
            'premium_holiday_pay' => $this->premiumHolidayPay,
            'regular_holiday_special_holiday_pay' => $this->regularHolidaySpecialHolidayPay,
            'overtime_pay' => $this->overtimePay,
            'night_differential' => $this->nightDifferential,
            'deminimis' => $this->deMinimis,
        ];
    }
}
