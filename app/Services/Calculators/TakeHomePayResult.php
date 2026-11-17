<?php

namespace App\Services\Calculators;

use App\Enums\Sector;
use App\Services\Statutory\Philippines\Bir\TaxPeriod;

/**
 * The take-home pay result for one payroll period. Amounts are centavos.
 */
final class TakeHomePayResult
{
    public function __construct(
        public Sector $sector,
        public TaxPeriod $period,
        public int $regularCompensation,
        public int $supplementaryCompensation,
        public int $nonTaxableBenefits,
        public int $grossPay,
        public int $taxableCompensation,
        public int $socialInsuranceEmployee,
        public int $socialInsuranceEmployer,
        public int $philHealthEmployee,
        public int $philHealthEmployer,
        public int $pagIbigEmployee,
        public int $pagIbigEmployer,
        public int $withholdingTax,
        public int $totalEmployeeDeductions,
        public int $netPay,
        public int $totalEmployerContributions,
        public int $totalEmployerCost,
    ) {}
}
