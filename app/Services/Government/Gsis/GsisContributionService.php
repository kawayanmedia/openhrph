<?php

namespace App\Services\Government\Gsis;

use App\Support\Money;

/**
 * GSIS premium for a monthly compensation.
 *
 * Pure: the schedule is supplied by the caller, so historical runs remain
 * reproducible after a rate change. Mirrors payroll-engine/internal/statutory.
 */
final class GsisContributionService
{
    public function __construct(private readonly GsisSchedule $schedule) {}

    /**
     * The contribution base is the monthly basic compensation (RA 8291
     * Sec. 2(i)); PERA, honoraria, overtime, per diems and bonuses are not
     * part of it.
     */
    public function compute(int $monthlyCompensation): GsisContribution
    {
        $base = Money::max($monthlyCompensation, Money::ZERO);

        if (! $this->schedule->isCapped() || $base <= $this->schedule->maxAmcLimit) {
            $employee = Money::mul($base, $this->schedule->employeeRate);
        } else {
            // Above the Maximum AMC limit the member pays 9% up to the limit
            // and 2% on the excess; the employer still pays 12% throughout.
            $employee = Money::add(
                Money::mul($this->schedule->maxAmcLimit, $this->schedule->employeeRate),
                Money::mul($base - $this->schedule->maxAmcLimit, $this->schedule->aboveLimitEmployeeRate),
            );
        }

        return new GsisContribution(
            base: $base,
            employee: $employee,
            employer: Money::mul($base, $this->schedule->employerRate),
        );
    }
}
