<?php

namespace App\Services\Government\Bir;

use App\Services\Government\PayFrequency;
use App\Support\Money;

/**
 * The BIR withholding tables published for each pay frequency.
 */
final class WithholdingTaxTable
{
    /**
     * The BIR withholding table effective 2023 onwards (TRAIN law).
     *
     * @return list<TaxBracket>
     */
    public static function train(PayFrequency $frequency): array
    {
        if ($frequency === PayFrequency::SEMI_MONTHLY) {
            return [
                new TaxBracket(from: 0, to: Money::fromPesos(10416.99), baseTax: 0, rate: 0),
                new TaxBracket(from: Money::fromPesos(10417), to: Money::fromPesos(16666.99), baseTax: 0, rate: 0.15),
                new TaxBracket(from: Money::fromPesos(16667), to: Money::fromPesos(33332.99), baseTax: Money::fromPesos(937.50), rate: 0.20),
                new TaxBracket(from: Money::fromPesos(33333), to: Money::fromPesos(83332.99), baseTax: Money::fromPesos(4270.70), rate: 0.25),
                new TaxBracket(from: Money::fromPesos(83333), to: Money::fromPesos(333332.99), baseTax: Money::fromPesos(16770.70), rate: 0.30),
                new TaxBracket(from: Money::fromPesos(333333), to: 0, baseTax: Money::fromPesos(91770.70), rate: 0.35),
            ];
        }

        return [
            new TaxBracket(from: 0, to: Money::fromPesos(20832.99), baseTax: 0, rate: 0),
            new TaxBracket(from: Money::fromPesos(20833), to: Money::fromPesos(33332.99), baseTax: 0, rate: 0.15),
            new TaxBracket(from: Money::fromPesos(33333), to: Money::fromPesos(66666.99), baseTax: Money::fromPesos(1875.00), rate: 0.20),
            new TaxBracket(from: Money::fromPesos(66667), to: Money::fromPesos(166666.99), baseTax: Money::fromPesos(8541.80), rate: 0.25),
            new TaxBracket(from: Money::fromPesos(166667), to: Money::fromPesos(666666.99), baseTax: Money::fromPesos(33541.80), rate: 0.30),
            new TaxBracket(from: Money::fromPesos(666667), to: 0, baseTax: Money::fromPesos(183541.80), rate: 0.35),
        ];
    }
}
