<?php

namespace App\Services\Statutory\Philippines\PagIbig;

use App\Support\Money;

/**
 * Pag-IBIG fund contribution for a monthly salary.
 *
 * Pure: the schedule is supplied by the caller, so historical runs remain
 * reproducible after a rate change. Mirrors payroll-engine/internal/statutory.
 */
final class PagIbigContributionService
{
    public function __construct(private readonly PagIbigSchedule $schedule) {}

    public function compute(int $monthlyBasic): PagIbigContribution
    {
        $base = Money::min($monthlyBasic, $this->schedule->maxFundSalary);

        $rate = $this->schedule->rate;

        if ($monthlyBasic <= $this->schedule->lowThreshold && $this->schedule->lowRate > 0) {
            $rate = $this->schedule->lowRate;
        }

        return new PagIbigContribution(
            base: $base,
            employee: Money::mul($base, $rate),
            employer: Money::mul($base, $this->schedule->rate),
        );
    }
}
