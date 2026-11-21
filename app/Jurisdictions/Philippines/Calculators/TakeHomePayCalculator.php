<?php

namespace App\Jurisdictions\Philippines\Calculators;

use App\Enums\Sector;
use App\Jurisdictions\Philippines\Statutory\Gsis\GsisContributionService;
use App\Jurisdictions\Philippines\Statutory\Gsis\GsisSchedule;
use App\Jurisdictions\Philippines\Statutory\PagIbig\PagIbigContributionService;
use App\Jurisdictions\Philippines\Statutory\PagIbig\PagIbigSchedule;
use App\Jurisdictions\Philippines\Statutory\PhilHealth\PhilHealthContributionService;
use App\Jurisdictions\Philippines\Statutory\PhilHealth\PhilHealthSchedule;
use App\Jurisdictions\Philippines\Statutory\Sss\SssContributionService;
use App\Jurisdictions\Philippines\Statutory\Sss\SssSchedule;
use App\Support\Money;

/**
 * Take-home pay for one payroll period.
 *
 * The compensation lines and withholding tax mirror the BIR calculator.
 * Monthly statutory contributions are computed on the monthly equivalent of
 * the basic salary, then prorated to the selected payroll period and deducted
 * before net pay. Private employees contribute to SSS; government employees
 * contribute to GSIS. PhilHealth and Pag-IBIG apply to both sectors.
 */
final class TakeHomePayCalculator
{
    public function compute(WithholdingTaxInput $input, Sector $sector = Sector::Private): TakeHomePayResult
    {
        $withholding = new WithholdingTaxCalculator()->compute($input);

        $periodsPerYear = $input->period->periodsPerYear();
        $monthlyBasic = (int) Money::round($input->basicSalary * $periodsPerYear / 12);
        $monthlyToPeriod = 12 / $periodsPerYear;

        $socialInsuranceEmployee = Money::ZERO;
        $socialInsuranceEmployer = Money::ZERO;
        $philHealthEmployee = Money::ZERO;
        $philHealthEmployer = Money::ZERO;
        $pagIbigEmployee = Money::ZERO;
        $pagIbigEmployer = Money::ZERO;

        if ($monthlyBasic > 0) {
            if ($sector->isGovernment()) {
                $gsis = (new GsisContributionService(GsisSchedule::default()))->compute($monthlyBasic);
                $socialInsuranceEmployee = Money::mul($gsis->employee, $monthlyToPeriod);
                $socialInsuranceEmployer = Money::mul($gsis->employer, $monthlyToPeriod);
            } else {
                $sss = (new SssContributionService(SssSchedule::preset2025()))->compute($monthlyBasic);
                $socialInsuranceEmployee = Money::mul($sss->employeeShare(), $monthlyToPeriod);
                $socialInsuranceEmployer = Money::mul($sss->employerShare(), $monthlyToPeriod);
            }

            $philHealth = (new PhilHealthContributionService(PhilHealthSchedule::default()))->compute($monthlyBasic);
            $philHealthEmployee = Money::mul($philHealth->employee, $monthlyToPeriod);
            $philHealthEmployer = Money::mul($philHealth->employer, $monthlyToPeriod);

            $pagIbig = (new PagIbigContributionService(PagIbigSchedule::default()))->compute($monthlyBasic);
            $pagIbigEmployee = Money::mul($pagIbig->employee, $monthlyToPeriod);
            $pagIbigEmployer = Money::mul($pagIbig->employer, $monthlyToPeriod);
        }

        $grossPay = Money::add($withholding->grossCompensation, $withholding->nonTaxableBenefits);

        $totalEmployeeDeductions = $socialInsuranceEmployee
            + $philHealthEmployee
            + $pagIbigEmployee
            + $withholding->withholdingTax;

        $totalEmployerContributions = $socialInsuranceEmployer + $philHealthEmployer + $pagIbigEmployer;

        return new TakeHomePayResult(
            sector: $sector,
            period: $input->period,
            regularCompensation: $withholding->regularCompensation,
            supplementaryCompensation: $withholding->supplementaryCompensation,
            nonTaxableBenefits: $withholding->nonTaxableBenefits,
            grossPay: $grossPay,
            taxableCompensation: $withholding->taxableCompensation,
            socialInsuranceEmployee: $socialInsuranceEmployee,
            socialInsuranceEmployer: $socialInsuranceEmployer,
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
