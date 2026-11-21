<?php

namespace App\Jurisdictions\Philippines\Statutory\PhilHealth;

use App\Support\Money;

/**
 * PhilHealth premium for a monthly salary.
 *
 * Pure: the schedule is supplied by the caller, so historical runs remain
 * reproducible after a rate change. Mirrors payroll-engine/internal/statutory.
 */
final class PhilHealthContributionService
{
    public function __construct(private readonly PhilHealthSchedule $schedule) {}

    public function compute(int $monthlyBasic): PhilHealthContribution
    {
        $base = Money::clampTo($monthlyBasic, $this->schedule->floor, $this->schedule->ceiling);
        $premium = Money::mul($base, $this->schedule->rate);
        $employee = Money::half($premium);

        return new PhilHealthContribution(
            base: $base,
            premium: $premium,
            employee: $employee,
            employer: $premium - $employee,
        );
    }
}
