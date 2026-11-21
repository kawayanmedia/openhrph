<?php

namespace App\Jurisdictions\Philippines\Statutory\Generic;

use App\Jurisdictions\Philippines\Statutory\ContributionSchedule;
use App\Jurisdictions\Philippines\Statutory\PayFrequency;

/**
 * Computes a generic, data-driven statutory component.
 *
 * Mirrors payroll-engine/internal/statutory/generic.go: percentage components
 * use a floor and cap, bracket components combine a flat band amount with a
 * rate on the excess, and fixed components charge a set amount per period.
 */
final class GenericContributionService
{
    /**
     * @param  array<string, mixed>  $component
     * @return array{employee: int, employer: int, base: int, taxable: bool}
     */
    public static function compute(array $component, int $base): array
    {
        $base = max(0, $base);
        $calculation = strtolower((string) ($component['calculation'] ?? 'percentage'));
        $employee = 0;
        $employer = 0;

        switch ($calculation) {
            case 'fixed':
                $employee = (int) ($component['employee_amount'] ?? 0);
                $employer = (int) ($component['employer_amount'] ?? 0);
                break;

            case 'bracket':
                [$employee, $employer] = self::bracket($component, $base);
                break;

            default:
                [$employee, $employer] = self::percentage($component, $base);
                break;
        }

        $appliesTo = strtolower((string) ($component['applies_to'] ?? 'both'));

        if ($appliesTo === 'employer') {
            $employee = 0;
        }

        if ($appliesTo === 'employee') {
            $employer = 0;
        }

        $employee = self::cap($employee, $component['max_employee'] ?? null);
        $employer = self::cap($employer, $component['max_employer'] ?? null);

        return [
            'employee' => $employee,
            'employer' => $employer,
            'base' => $base,
            'taxable' => (bool) ($component['reduces_taxable_income'] ?? false),
        ];
    }

    /**
     * @param  array<string, mixed>  $component
     * @return array{0: int, 1: int}
     */
    private static function percentage(array $component, int $base): array
    {
        $min = $component['min_base'] ?? null;
        $max = $component['max_base'] ?? null;

        if ($min !== null) {
            $base = max($base, (int) $min);
        }

        if ($max !== null && (int) $max > 0) {
            $base = min($base, (int) $max);
        }

        $employeeRate = (float) ($component['employee_rate'] ?? 0);
        $employerRate = (float) ($component['employer_rate'] ?? 0);

        return [
            self::round($base * $employeeRate, (string) ($component['rounding'] ?? 'half_up')),
            self::round($base * $employerRate, (string) ($component['rounding'] ?? 'half_up')),
        ];
    }

    /**
     * @param  array<string, mixed>  $component
     * @return array{0: int, 1: int}
     */
    private static function bracket(array $component, int $base): array
    {
        foreach ((array) ($component['bands'] ?? []) as $band) {
            $from = (int) ($band['from'] ?? 0);
            $to = $band['to'] ?? null;

            if ($base < $from || ($to !== null && $base > (int) $to)) {
                continue;
            }

            $excess = max(0, $base - $from);
            $flat = (int) ($band['base'] ?? 0);

            $employee = $flat
                + self::round($excess * (float) ($band['employee_rate'] ?? 0), (string) ($component['rounding'] ?? 'half_up'))
                + (int) ($band['employee_amount'] ?? 0);
            $employer = $flat
                + self::round($excess * (float) ($band['employer_rate'] ?? 0), (string) ($component['rounding'] ?? 'half_up'))
                + (int) ($band['employer_amount'] ?? 0);

            return [$employee, $employer];
        }

        return [0, 0];
    }

    /**
     * Divide a monthly component result by the payroll frequency divisor.
     *
     * @param  array{employee: int, employer: int, base: int, taxable: bool}  $result
     * @return array{employee: int, employer: int, base: int, taxable: bool}
     */
    public static function perPeriod(array $result, int $divisor): array
    {
        if ($divisor <= 1) {
            return $result;
        }

        $result['employee'] = intdiv($result['employee'], $divisor);
        $result['employer'] = intdiv($result['employer'], $divisor);

        return $result;
    }

    /**
     * The divisor for a component schedule, mirroring the Go engine.
     */
    public static function divisor(string $schedule, string $frequency, string $cutoff): int
    {
        return ContributionSchedule::fromString($schedule)
            ->divisor(PayFrequency::fromString($frequency), $cutoff);
    }

    private static function cap(int $amount, mixed $cap): int
    {
        if ($cap === null || (int) $cap <= 0) {
            return $amount;
        }

        return min($amount, (int) $cap);
    }

    private static function round(float $amount, string $rounding): int
    {
        return match (strtolower($rounding)) {
            'floor', 'down' => (int) floor($amount),
            'ceil', 'up' => (int) ceil($amount),
            default => (int) round($amount, 0, PHP_ROUND_HALF_UP),
        };
    }
}
