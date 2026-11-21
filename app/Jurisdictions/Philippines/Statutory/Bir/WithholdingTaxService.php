<?php

namespace App\Jurisdictions\Philippines\Statutory\Bir;

use App\Support\Money;
use RuntimeException;

/**
 * BIR withholding tax for a taxable amount.
 *
 * Pure: the brackets are supplied by the caller, so historical runs remain
 * reproducible after a rate change. Mirrors payroll-engine/internal/statutory.
 */
final class WithholdingTaxService
{
    /**
     * @param  list<TaxBracket>  $brackets
     */
    public function __construct(private readonly array $brackets) {}

    /**
     * @throws RuntimeException when the table does not cover the amount
     */
    public function compute(int $taxable): int
    {
        if ($this->brackets === []) {
            throw new RuntimeException('tax: no statutory bracket matches the salary');
        }

        foreach ($this->brackets as $bracket) {
            if ($taxable >= $bracket->from && ($bracket->to === 0 || $taxable <= $bracket->to)) {
                if ($taxable <= $bracket->from) {
                    return $bracket->baseTax;
                }

                return Money::add($bracket->baseTax, Money::mul($taxable - $bracket->from, $bracket->rate));
            }
        }

        throw new RuntimeException(sprintf(
            'tax: no statutory bracket matches the salary (taxable %s)',
            Money::format($taxable),
        ));
    }
}
