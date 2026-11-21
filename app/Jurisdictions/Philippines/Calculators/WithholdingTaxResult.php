<?php

namespace App\Jurisdictions\Philippines\Calculators;

use App\Jurisdictions\Philippines\Statutory\Bir\TaxPeriod;

/**
 * The withholding tax result for one payroll period. Amounts are centavos.
 */
final class WithholdingTaxResult
{
    public function __construct(
        public TaxPeriod $period,
        public int $regularCompensation,
        public int $supplementaryCompensation,
        public int $grossCompensation,
        public int $nonTaxableBenefits,
        public int $taxableCompensation,
        public int $withholdingTax,
        public float $effectiveTaxRate,
        public int $statutoryDeductions = 0,
    ) {}
}
