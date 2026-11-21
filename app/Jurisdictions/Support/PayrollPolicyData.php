<?php

namespace App\Jurisdictions\Support;

use App\Jurisdictions\Contracts\PayrollPolicy;

/**
 * Neutral payroll policy value object.
 *
 * The defaults are the Philippine baseline so a legacy payroll without a
 * snapshot keeps computing exactly as before. A jurisdiction overrides only
 * the entries that differ.
 */
class PayrollPolicyData implements PayrollPolicy
{
    /**
     * @param  array<string, float>  $dayTypeMultipliers
     * @param  array<string, float>  $separationMonthsPerYear
     * @param  array<string, mixed>  $leaveConversion
     * @param  array<int, string>  $specialRuns
     */
    public function __construct(
        protected array $dayTypeMultipliers = [],
        protected float $overtimeMultiplier = 1.25,
        protected float $premiumDayOvertimeMultiplier = 1.30,
        protected float $nightDifferentialRate = 0.10,
        protected string $nightWindowStart = '22:00',
        protected string $nightWindowEnd = '06:00',
        protected int $thirteenthMonthExemptCentavos = 9_000_000,
        protected bool $minimumWageTaxExempt = true,
        protected int $daysPerYearDefault = 261,
        protected int $hoursPerDayDefault = 8,
        protected array $separationMonthsPerYear = [],
        protected array $leaveConversion = [],
        protected array $specialRuns = ['regular', 'thirteenth_month', 'final_pay', 'annual_adjustment'],
    ) {
        $this->dayTypeMultipliers = $dayTypeMultipliers !== []
            ? $dayTypeMultipliers
            : self::defaultDayTypeMultipliers();

        $this->separationMonthsPerYear = $separationMonthsPerYear !== []
            ? $separationMonthsPerYear
            : self::defaultSeparationMonthsPerYear();
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            dayTypeMultipliers: array_map('floatval', (array) ($data['day_type_multipliers'] ?? [])),
            overtimeMultiplier: (float) ($data['overtime_multiplier'] ?? 1.25),
            premiumDayOvertimeMultiplier: (float) ($data['premium_day_overtime_multiplier'] ?? 1.30),
            nightDifferentialRate: (float) ($data['night_differential_rate'] ?? 0.10),
            nightWindowStart: (string) ($data['night_window_start'] ?? '22:00'),
            nightWindowEnd: (string) ($data['night_window_end'] ?? '06:00'),
            thirteenthMonthExemptCentavos: (int) ($data['thirteenth_month_exempt_centavos'] ?? 9_000_000),
            minimumWageTaxExempt: (bool) ($data['minimum_wage_tax_exempt'] ?? true),
            daysPerYearDefault: (int) ($data['days_per_year_default'] ?? 261),
            hoursPerDayDefault: (int) ($data['hours_per_day_default'] ?? 8),
            separationMonthsPerYear: array_map('floatval', (array) ($data['separation_months_per_year'] ?? [])),
            leaveConversion: (array) ($data['leave_conversion'] ?? []),
            specialRuns: array_values(array_map('strval', (array) ($data['special_runs'] ?? ['regular', 'thirteenth_month', 'final_pay', 'annual_adjustment']))),
        );
    }

    /**
     * @return array<string, float>
     */
    public static function defaultDayTypeMultipliers(): array
    {
        return [
            DayType::REGULAR => 1.00,
            DayType::REST => 1.30,
            DayType::SPECIAL_HOLIDAY => 1.30,
            DayType::REGULAR_HOLIDAY => 2.00,
            DayType::REST_SPECIAL_HOLIDAY => 1.50,
            DayType::REST_REGULAR_HOLIDAY => 2.60,
            DayType::DOUBLE_HOLIDAY => 3.00,
            DayType::REST_DOUBLE_HOLIDAY => 3.90,
            DayType::REGULAR_HOLIDAY_SPECIAL_HOLIDAY => 2.60,
            DayType::REST_REGULAR_HOLIDAY_SPECIAL_HOLIDAY => 3.00,
            DayType::PAID_LEAVE => 1.00,
            DayType::UNPAID_LEAVE => 0.00,
            DayType::ABSENT => 0.00,
            DayType::UNDERTIME => 1.00,
        ];
    }

    /**
     * @return array<string, float>
     */
    public static function defaultSeparationMonthsPerYear(): array
    {
        return [
            'retrenchment' => 1.0,
            'redundancy' => 1.0,
            'closure' => 1.0,
            'business_closure' => 1.0,
            'authorised_cause' => 1.0,
            'authorized_cause' => 1.0,
            'illness' => 0.5,
            'disease' => 0.5,
            'sick' => 0.5,
        ];
    }

    public function dayTypeMultipliers(): array
    {
        return $this->dayTypeMultipliers;
    }

    public function overtimeMultiplier(): float
    {
        return $this->overtimeMultiplier;
    }

    public function premiumDayOvertimeMultiplier(): float
    {
        return $this->premiumDayOvertimeMultiplier;
    }

    public function overtimeMultiplierFor(float $dayRate): float
    {
        return $dayRate > 1 ? $this->premiumDayOvertimeMultiplier : $this->overtimeMultiplier;
    }

    public function nightDifferentialRate(): float
    {
        return $this->nightDifferentialRate;
    }

    public function nightDifferentialWindow(): array
    {
        return ['start' => $this->nightWindowStart, 'end' => $this->nightWindowEnd];
    }

    public function thirteenthMonthExemptCentavos(): int
    {
        return $this->thirteenthMonthExemptCentavos;
    }

    public function minimumWageTaxExempt(): bool
    {
        return $this->minimumWageTaxExempt;
    }

    public function daysPerYearDefault(): int
    {
        return $this->daysPerYearDefault;
    }

    public function hoursPerDayDefault(): int
    {
        return $this->hoursPerDayDefault;
    }

    public function separationMonthsPerYear(): array
    {
        return $this->separationMonthsPerYear;
    }

    public function leaveConversion(): array
    {
        return $this->leaveConversion;
    }

    public function specialRuns(): array
    {
        return $this->specialRuns;
    }

    public function toArray(): array
    {
        return [
            'day_type_multipliers' => $this->dayTypeMultipliers,
            'overtime_multiplier' => $this->overtimeMultiplier,
            'premium_day_overtime_multiplier' => $this->premiumDayOvertimeMultiplier,
            'night_differential_rate' => $this->nightDifferentialRate,
            'night_window_start' => $this->nightWindowStart,
            'night_window_end' => $this->nightWindowEnd,
            'thirteenth_month_exempt_centavos' => $this->thirteenthMonthExemptCentavos,
            'minimum_wage_tax_exempt' => $this->minimumWageTaxExempt,
            'days_per_year_default' => $this->daysPerYearDefault,
            'hours_per_day_default' => $this->hoursPerDayDefault,
            'separation_months_per_year' => $this->separationMonthsPerYear,
            'leave_conversion' => $this->leaveConversion,
            'special_runs' => $this->specialRuns,
        ];
    }
}
