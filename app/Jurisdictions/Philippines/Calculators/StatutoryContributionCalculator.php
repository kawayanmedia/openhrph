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
 * Mandatory Philippine contributions for one payroll period.
 *
 * Monthly amounts are computed on the monthly equivalent of the basic salary,
 * then prorated to the selected period. Private employees contribute to SSS;
 * government employees contribute to GSIS. PhilHealth and Pag-IBIG apply to
 * both sectors.
 */
final class StatutoryContributionCalculator
{
    public function compute(
        WithholdingTaxInput $input,
        Sector $sector = Sector::Private,
    ): StatutoryContributionResult {
        $periodsPerYear = $input->period->periodsPerYear();
        $monthlyBasic = (int) Money::round($input->basicSalary * $periodsPerYear / 12);
        $monthlyToPeriod = 12 / $periodsPerYear;

        if ($monthlyBasic <= 0) {
            return new StatutoryContributionResult($sector);
        }

        if ($sector->isGovernment()) {
            $social = (new GsisContributionService(GsisSchedule::default()))->compute($monthlyBasic);
            $socialEmployee = Money::mul($social->employee, $monthlyToPeriod);
            $socialEmployer = Money::mul($social->employer, $monthlyToPeriod);
        } else {
            $social = (new SssContributionService(SssSchedule::preset2025()))->compute($monthlyBasic);
            $socialEmployee = Money::mul($social->employeeShare(), $monthlyToPeriod);
            $socialEmployer = Money::mul($social->employerShare(), $monthlyToPeriod);
        }

        $philHealth = (new PhilHealthContributionService(PhilHealthSchedule::default()))->compute($monthlyBasic);
        $pagIbig = (new PagIbigContributionService(PagIbigSchedule::default()))->compute($monthlyBasic);

        return new StatutoryContributionResult(
            sector: $sector,
            socialInsuranceEmployee: $socialEmployee,
            socialInsuranceEmployer: $socialEmployer,
            philHealthEmployee: Money::mul($philHealth->employee, $monthlyToPeriod),
            philHealthEmployer: Money::mul($philHealth->employer, $monthlyToPeriod),
            pagIbigEmployee: Money::mul($pagIbig->employee, $monthlyToPeriod),
            pagIbigEmployer: Money::mul($pagIbig->employer, $monthlyToPeriod),
        );
    }
}
