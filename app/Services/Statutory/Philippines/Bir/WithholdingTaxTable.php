<?php

namespace App\Services\Statutory\Philippines\Bir;

use App\Services\Statutory\Philippines\PayFrequency;
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
        return self::trainForPeriod(match ($frequency) {
            PayFrequency::MONTHLY => TaxPeriod::MONTHLY,
            PayFrequency::SEMI_MONTHLY => TaxPeriod::SEMI_MONTHLY,
        });
    }

    /**
     * The BIR withholding table for a payroll period.
     *
     * @return list<TaxBracket>
     */
    public static function trainForPeriod(TaxPeriod $period): array
    {
        return match ($period) {
            TaxPeriod::DAILY => [
                new TaxBracket(from: 0, to: Money::fromPesos(684.99), baseTax: 0, rate: 0),
                new TaxBracket(from: Money::fromPesos(685), to: Money::fromPesos(1095.99), baseTax: 0, rate: 0.15),
                new TaxBracket(from: Money::fromPesos(1096), to: Money::fromPesos(2191.99), baseTax: Money::fromPesos(61.65), rate: 0.20),
                new TaxBracket(from: Money::fromPesos(2192), to: Money::fromPesos(5478.99), baseTax: Money::fromPesos(280.85), rate: 0.25),
                new TaxBracket(from: Money::fromPesos(5479), to: Money::fromPesos(21917.99), baseTax: Money::fromPesos(1102.60), rate: 0.30),
                new TaxBracket(from: Money::fromPesos(21918), to: 0, baseTax: Money::fromPesos(6034.30), rate: 0.35),
            ],
            TaxPeriod::WEEKLY => [
                new TaxBracket(from: 0, to: Money::fromPesos(4807.99), baseTax: 0, rate: 0),
                new TaxBracket(from: Money::fromPesos(4808), to: Money::fromPesos(7691.99), baseTax: 0, rate: 0.15),
                new TaxBracket(from: Money::fromPesos(7692), to: Money::fromPesos(15384.99), baseTax: Money::fromPesos(432.60), rate: 0.20),
                new TaxBracket(from: Money::fromPesos(15385), to: Money::fromPesos(38461.99), baseTax: Money::fromPesos(1971.20), rate: 0.25),
                new TaxBracket(from: Money::fromPesos(38462), to: Money::fromPesos(153845.99), baseTax: Money::fromPesos(7740.45), rate: 0.30),
                new TaxBracket(from: Money::fromPesos(153846), to: 0, baseTax: Money::fromPesos(42355.65), rate: 0.35),
            ],
            TaxPeriod::SEMI_MONTHLY => [
                new TaxBracket(from: 0, to: Money::fromPesos(10416.99), baseTax: 0, rate: 0),
                new TaxBracket(from: Money::fromPesos(10417), to: Money::fromPesos(16666.99), baseTax: 0, rate: 0.15),
                new TaxBracket(from: Money::fromPesos(16667), to: Money::fromPesos(33332.99), baseTax: Money::fromPesos(937.50), rate: 0.20),
                new TaxBracket(from: Money::fromPesos(33333), to: Money::fromPesos(83332.99), baseTax: Money::fromPesos(4270.70), rate: 0.25),
                new TaxBracket(from: Money::fromPesos(83333), to: Money::fromPesos(333332.99), baseTax: Money::fromPesos(16770.70), rate: 0.30),
                new TaxBracket(from: Money::fromPesos(333333), to: 0, baseTax: Money::fromPesos(91770.70), rate: 0.35),
            ],
            TaxPeriod::MONTHLY => [
                new TaxBracket(from: 0, to: Money::fromPesos(20832.99), baseTax: 0, rate: 0),
                new TaxBracket(from: Money::fromPesos(20833), to: Money::fromPesos(33332.99), baseTax: 0, rate: 0.15),
                new TaxBracket(from: Money::fromPesos(33333), to: Money::fromPesos(66666.99), baseTax: Money::fromPesos(1875.00), rate: 0.20),
                new TaxBracket(from: Money::fromPesos(66667), to: Money::fromPesos(166666.99), baseTax: Money::fromPesos(8541.80), rate: 0.25),
                new TaxBracket(from: Money::fromPesos(166667), to: Money::fromPesos(666666.99), baseTax: Money::fromPesos(33541.80), rate: 0.30),
                new TaxBracket(from: Money::fromPesos(666667), to: 0, baseTax: Money::fromPesos(183541.80), rate: 0.35),
            ],
            TaxPeriod::ANNUAL => [
                new TaxBracket(from: 0, to: Money::fromPesos(250000), baseTax: 0, rate: 0),
                new TaxBracket(from: Money::fromPesos(250000.01), to: Money::fromPesos(400000), baseTax: 0, rate: 0.15),
                new TaxBracket(from: Money::fromPesos(400000.01), to: Money::fromPesos(800000), baseTax: Money::fromPesos(22500), rate: 0.20),
                new TaxBracket(from: Money::fromPesos(800000.01), to: Money::fromPesos(2000000), baseTax: Money::fromPesos(102500), rate: 0.25),
                new TaxBracket(from: Money::fromPesos(2000000.01), to: Money::fromPesos(8000000), baseTax: Money::fromPesos(402500), rate: 0.30),
                new TaxBracket(from: Money::fromPesos(8000000.01), to: 0, baseTax: Money::fromPesos(2202500), rate: 0.35),
            ],
        };
    }
}
