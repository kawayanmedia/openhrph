<?php

namespace App\Jurisdictions\Philippines\Calculators;

use App\Enums\Sector;
use App\Support\Money;

/**
 * Take-home pay for one payroll period.
 *
 * Mandatory employee contributions are computed first, then BIR withholding
 * tax is applied to the remaining taxable compensation. The private sector
 * contributes to SSS; the government sector contributes to GSIS. PhilHealth
 * and Pag-IBIG apply to both sectors.
 */
final class TakeHomePayCalculator
{
    public function compute(WithholdingTaxInput $input, Sector $sector = Sector::Private): TakeHomePayResult
    {
        $contributions = (new StatutoryContributionCalculator)->compute($input, $sector);
        $withholding = (new WithholdingTaxCalculator)->compute(
            $input,
            $contributions->totalEmployee(),
        );

        $grossPay = Money::add($withholding->grossCompensation, $withholding->nonTaxableBenefits);

        $totalEmployeeDeductions = $contributions->totalEmployee()
            + $withholding->withholdingTax;

        return new TakeHomePayResult(
            sector: $sector,
            period: $input->period,
            regularCompensation: $withholding->regularCompensation,
            supplementaryCompensation: $withholding->supplementaryCompensation,
            nonTaxableBenefits: $withholding->nonTaxableBenefits,
            grossPay: $grossPay,
            taxableCompensation: $withholding->taxableCompensation,
            socialInsuranceEmployee: $contributions->socialInsuranceEmployee,
            socialInsuranceEmployer: $contributions->socialInsuranceEmployer,
            philHealthEmployee: $contributions->philHealthEmployee,
            philHealthEmployer: $contributions->philHealthEmployer,
            pagIbigEmployee: $contributions->pagIbigEmployee,
            pagIbigEmployer: $contributions->pagIbigEmployer,
            withholdingTax: $withholding->withholdingTax,
            totalEmployeeDeductions: $totalEmployeeDeductions,
            netPay: Money::max(Money::sub($grossPay, $totalEmployeeDeductions), Money::ZERO),
            totalEmployerContributions: $contributions->totalEmployer(),
            totalEmployerCost: Money::add($grossPay, $contributions->totalEmployer()),
        );
    }
}
