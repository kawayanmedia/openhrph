<?php

namespace App\Services\Calculators;

use App\Services\Statutory\Philippines\PagIbig\PagIbigContributionService;
use App\Services\Statutory\Philippines\PagIbig\PagIbigSchedule;
use App\Services\Statutory\Philippines\PhilHealth\PhilHealthContributionService;
use App\Services\Statutory\Philippines\PhilHealth\PhilHealthSchedule;
use App\Services\Statutory\Philippines\Sss\SssContributionService;
use App\Services\Statutory\Philippines\Sss\SssSchedule;
use App\Support\Money;

/**
 * Take-home pay for one payroll period.
 *
 * The compensation lines and withholding tax mirror the BIR calculator.
 * Monthly statutory contributions are computed on the monthly equivalent of
 * the basic salary, then prorated to the selected payroll period and deducted
 * before net pay.
 */
final class TakeHomePayCalculator
{
    public function compute(WithholdingTaxInput $input): TakeHomePayResult
    {
        $withholding = (new WithholdingTaxCalculator)->compute($input);

        $periodsPerYear = $input->period->periodsPerYear();
        $monthlyBasic = (int) Money::round($input->basicSalary * $periodsPerYear / 12);
        $monthlyToPeriod = 12 / $periodsPerYear;

        $sssEmployee = Money::ZERO;
        $sssEmployer = Money::ZERO;
        $philHealthEmployee = Money::ZERO;
        $philHealthEmployer = Money::ZERO;
        $pagIbigEmployee = Money::ZERO;
        $pagIbigEmployer = Money::ZERO;

        if ($monthlyBasic > 0) {
            $sss = (new SssContributionService(SssSchedule::preset2025()))->compute($monthlyBasic);
            $sssEmployee = Money::mul($sss->employeeShare(), $monthlyToPeriod);
            $sssEmployer = Money::mul($sss->employerShare(), $monthlyToPeriod);

            $philHealth = (new PhilHealthContributionService(PhilHealthSchedule::default()))->compute($monthlyBasic);
            $philHealthEmployee = Money::mul($philHealth->employee, $monthlyToPeriod);
            $philHealthEmployer = Money::mul($philHealth->employer, $monthlyToPeriod);

            $pagIbig = (new PagIbigContributionService(PagIbigSchedule::default()))->compute($monthlyBasic);
            $pagIbigEmployee = Money::mul($pagIbig->employee, $monthlyToPeriod);
            $pagIbigEmployer = Money::mul($pagIbig->employer, $monthlyToPeriod);
        }

        $grossPay = Money::add($withholding->grossCompensation, $withholding->nonTaxableBenefits);

        $totalEmployeeDeductions = $sssEmployee
            + $philHealthEmployee
            + $pagIbigEmployee
            + $withholding->withholdingTax;

        $totalEmployerContributions = $sssEmployer + $philHealthEmployer + $pagIbigEmployer;

        return new TakeHomePayResult(
            period: $input->period,
            regularCompensation: $withholding->regularCompensation,
            supplementaryCompensation: $withholding->supplementaryCompensation,
            nonTaxableBenefits: $withholding->nonTaxableBenefits,
            grossPay: $grossPay,
            taxableCompensation: $withholding->taxableCompensation,
            sssEmployee: $sssEmployee,
            sssEmployer: $sssEmployer,
            philHealthEmployee: $philHealthEmployee,
            philHealthEmployer: $philHealthEmployer,
            pagIbigEmployee: $pagIbigEmployee,
            pagIbigEmployer: $pagIbigEmployer,
            withholdingTax: $withholding->withholdingTax,
            totalEmployeeDeductions: $totalEmployeeDeductions,
            netPay: Money::max(Money::sub($grossPay, $totalEmployeeDeductions), Money::ZERO),
            totalEmployerContributions: $totalEmployerContributions,
            totalEmployerCost: Money::add($grossPay, $totalEmployerContributions),
        );
    }
}
