<?php

namespace App\Services\Government\Sss;

use App\Support\Money;
use RuntimeException;

/**
 * SSS contribution for a monthly salary credit.
 *
 * Pure: the brackets are supplied by the caller, so historical runs remain
 * reproducible after a rate change. Mirrors payroll-engine/internal/statutory.
 */
final class SssContributionService
{
    /**
     * @param  list<SssBracket>  $brackets
     */
    public function __construct(private readonly array $brackets) {}

    /**
     * @throws RuntimeException when the schedule does not cover the salary
     */
    public function compute(int $monthlyBasic): SssContribution
    {
        if ($this->brackets === []) {
            throw new RuntimeException('sss: no statutory bracket matches the salary');
        }

        foreach ($this->brackets as $bracket) {
            if ($monthlyBasic >= $bracket->mscFrom && ($bracket->mscTo === 0 || $monthlyBasic <= $bracket->mscTo)) {
                return new SssContribution(
                    msc: $bracket->msc,
                    employee: $bracket->ee,
                    employer: $bracket->er,
                    ec: $bracket->ec,
                    wispEmployee: $bracket->wispEE,
                    wispEmployer: $bracket->wispER,
                );
            }
        }

        throw new RuntimeException(sprintf(
            'sss: no statutory bracket matches the salary (basic %s)',
            Money::format($monthlyBasic),
        ));
    }
}
