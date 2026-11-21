<?php

namespace App\Jurisdictions\Philippines\Calculators;

use App\Jurisdictions\Philippines\Statutory\Bir\WithholdingTaxService;
use App\Jurisdictions\Philippines\Statutory\Bir\WithholdingTaxTable;
use App\Support\Money;

/**
 * BIR withholding tax for a payroll period.
 *
 * Taxable compensation is gross regular and supplementary compensation less
 * the mandatory employee contributions (SSS/GSIS, PhilHealth and Pag-IBIG).
 * The 13th month pay and other benefits are exempt only up to the ₱90,000
 * ceiling; any excess is supplementary compensation.
 */
final class WithholdingTaxCalculator
{
    public const THIRTEENTH_MONTH_CEILING = 9000000;

    public function compute(
        WithholdingTaxInput $input,
        int $employeeStatutoryDeductions = 0,
    ): WithholdingTaxResult {
        $regular = $input->basicSalary
            + $input->representationAllowance
            + $input->transportationAllowance
            + $input->costOfLivingAllowance
            + $input->fixedHousingAllowance
            + $input->otherTaxableRegular;

        $nonTaxableBenefits = Money::min($input->thirteenthMonth, self::THIRTEENTH_MONTH_CEILING);
        $thirteenthMonthTaxable = Money::sub($input->thirteenthMonth, $nonTaxableBenefits);

        $supplementary = $input->commission
            + $input->profitSharing
            + $input->fees
            + $input->hazardPay
            + $input->overtimePay
            + $input->otherTaxableSupplementary
            + $thirteenthMonthTaxable;

        $gross = Money::max(Money::add($regular, $supplementary), Money::ZERO);
        $statutoryDeductions = Money::max($employeeStatutoryDeductions, Money::ZERO);
        $taxable = Money::max(Money::sub($gross, $statutoryDeductions), Money::ZERO);

        $withholdingTax = (new WithholdingTaxService(WithholdingTaxTable::trainForPeriod($input->period)))
            ->compute($taxable);

        return new WithholdingTaxResult(
            period: $input->period,
            regularCompensation: $regular,
            supplementaryCompensation: $supplementary,
            grossCompensation: $gross,
            nonTaxableBenefits: $nonTaxableBenefits,
            taxableCompensation: $taxable,
            withholdingTax: $withholdingTax,
            effectiveTaxRate: $gross > 0 ? $withholdingTax / $gross : 0.0,
            statutoryDeductions: $statutoryDeductions,
        );
    }
}
