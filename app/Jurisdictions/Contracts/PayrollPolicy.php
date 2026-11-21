<?php

namespace App\Jurisdictions\Contracts;

/**
 * Country payroll earning rules that are not statutory contributions.
 *
 * The payroll engines consume this contract instead of hardcoding one country's
 * labour code. Rates stay out of the contract; a jurisdiction supplies its own
 * implementation.
 */
interface PayrollPolicy
{
    /**
     * @return array<string, float>
     */
    public function dayTypeMultipliers(): array;

    public function overtimeMultiplier(): float;

    public function premiumDayOvertimeMultiplier(): float;

    public function overtimeMultiplierFor(float $dayRate): float;

    public function nightDifferentialRate(): float;

    /**
     * @return array{start: string, end: string}
     */
    public function nightDifferentialWindow(): array;

    public function thirteenthMonthExemptCentavos(): int;

    public function minimumWageTaxExempt(): bool;

    public function daysPerYearDefault(): int;

    public function hoursPerDayDefault(): int;

    /**
     * @return array<string, float>
     */
    public function separationMonthsPerYear(): array;

    /**
     * @return array<string, mixed>
     */
    public function leaveConversion(): array;

    /**
     * @return array<int, string>
     */
    public function specialRuns(): array;

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array;
}
