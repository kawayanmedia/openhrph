<?php

namespace App\Jurisdictions\Philippines\Calculators;

use App\Jurisdictions\Philippines\Statutory\Bir\TaxPeriod;

/**
 * The compensation lines accepted by the BIR withholding tax calculator.
 * Amounts are centavos for one payroll period.
 */
final class WithholdingTaxInput
{
    public function __construct(
        public TaxPeriod $period,
        public int $basicSalary = 0,
        public int $representationAllowance = 0,
        public int $transportationAllowance = 0,
        public int $costOfLivingAllowance = 0,
        public int $fixedHousingAllowance = 0,
        public int $otherTaxableRegular = 0,
        public int $commission = 0,
        public int $profitSharing = 0,
        public int $fees = 0,
        public int $thirteenthMonth = 0,
        public int $hazardPay = 0,
        public int $overtimePay = 0,
        public int $otherTaxableSupplementary = 0,
    ) {}
}
