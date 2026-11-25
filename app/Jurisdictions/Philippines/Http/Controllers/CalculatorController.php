<?php

namespace App\Jurisdictions\Philippines\Http\Controllers;

use App\Enums\Sector;
use App\Http\Controllers\Controller;
use App\Jurisdictions\Philippines\Calculators\StatutoryContributionCalculator;
use App\Jurisdictions\Philippines\Calculators\TakeHomePayCalculator;
use App\Jurisdictions\Philippines\Calculators\WithholdingTaxCalculator;
use App\Jurisdictions\Philippines\Calculators\WithholdingTaxInput;
use App\Jurisdictions\Philippines\Http\Requests\CalculateTakeHomePayRequest;
use App\Jurisdictions\Philippines\Http\Requests\CalculateWithholdingTaxRequest;
use App\Jurisdictions\Philippines\Statutory\Bir\TaxPeriod;
use App\Support\Money;
use Illuminate\Http\JsonResponse;

class CalculatorController extends Controller
{
    /**
     * Compute the BIR withholding tax for one payroll period.
     */
    public function withholdingTax(
        CalculateWithholdingTaxRequest $request,
        WithholdingTaxCalculator $calculator,
        StatutoryContributionCalculator $contributions,
    ): JsonResponse {
        $data = $request->validated();
        $sector = Sector::fromValue($data['sector'] ?? null);
        $input = self::input($data);
        $contribution = $contributions->compute($input, $sector);
        $result = $calculator->compute($input, $contribution->totalEmployee());

        return response()->json([
            'data' => [
                'sector' => $sector->value,
                'period' => $result->period->value,
                'period_label' => $result->period->label(),
                'currency' => 'PHP',
                'regular_compensation' => self::amount($result->regularCompensation),
                'supplementary_compensation' => self::amount($result->supplementaryCompensation),
                'gross_compensation_income' => self::amount($result->grossCompensation),
                'non_taxable_benefits' => self::amount($result->nonTaxableBenefits),
                'taxable_compensation_income' => self::amount($result->taxableCompensation),
                'deductions' => [
                    'social_insurance' => [
                        'agency' => $contribution->agency(),
                        'employee' => self::amount($contribution->socialInsuranceEmployee),
                    ],
                    'philhealth' => [
                        'employee' => self::amount($contribution->philHealthEmployee),
                    ],
                    'pagibig' => [
                        'employee' => self::amount($contribution->pagIbigEmployee),
                    ],
                    'total_employee' => self::amount($contribution->totalEmployee()),
                ],
                'withholding_tax' => self::amount($result->withholdingTax),
                'effective_tax_rate' => round($result->effectiveTaxRate, 4),
                'breakdown' => self::breakdown($data),
            ],
        ]);
    }

    /**
     * Compute statutory deductions and take-home pay for one payroll period.
     */
    public function takeHomePay(
        CalculateTakeHomePayRequest $request,
        TakeHomePayCalculator $calculator,
    ): JsonResponse {
        $data = $request->validated();
        $result = $calculator->compute(
            self::input($data),
            Sector::fromValue($data['sector'] ?? null),
        );

        return response()->json([
            'data' => [
                'sector' => $result->sector->value,
                'period' => $result->period->value,
                'period_label' => $result->period->label(),
                'currency' => 'PHP',
                'regular_compensation' => self::amount($result->regularCompensation),
                'supplementary_compensation' => self::amount($result->supplementaryCompensation),
                'gross_pay' => self::amount($result->grossPay),
                'non_taxable_benefits' => self::amount($result->nonTaxableBenefits),
                'taxable_compensation_income' => self::amount($result->taxableCompensation),
                'deductions' => [
                    'social_insurance' => [
                        'agency' => $result->agency(),
                        'employee' => self::amount($result->socialInsuranceEmployee),
                        'employer' => self::amount($result->socialInsuranceEmployer),
                    ],
                    'philhealth' => [
                        'employee' => self::amount($result->philHealthEmployee),
                        'employer' => self::amount($result->philHealthEmployer),
                    ],
                    'pagibig' => [
                        'employee' => self::amount($result->pagIbigEmployee),
                        'employer' => self::amount($result->pagIbigEmployer),
                    ],
                    'withholding_tax' => self::amount($result->withholdingTax),
                    'total_employee' => self::amount($result->totalEmployeeDeductions),
                ],
                'net_pay' => self::amount($result->netPay),
                'employer' => [
                    'social_insurance' => self::amount($result->socialInsuranceEmployer),
                    'philhealth' => self::amount($result->philHealthEmployer),
                    'pagibig' => self::amount($result->pagIbigEmployer),
                    'total_contributions' => self::amount($result->totalEmployerContributions),
                    'total_cost' => self::amount($result->totalEmployerCost),
                ],
                'breakdown' => self::breakdown($data),
            ],
        ]);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private static function input(array $data): WithholdingTaxInput
    {
        $pesos = static fn (string $key): int => Money::fromPesos((float) ($data[$key] ?? 0));

        return new WithholdingTaxInput(
            period: TaxPeriod::from($data['period']),
            basicSalary: $pesos('basic_salary'),
            representationAllowance: $pesos('representation_allowance'),
            transportationAllowance: $pesos('transportation_allowance'),
            costOfLivingAllowance: $pesos('cost_of_living_allowance'),
            fixedHousingAllowance: $pesos('fixed_housing_allowance'),
            otherTaxableRegular: $pesos('other_taxable_regular'),
            commission: $pesos('commission'),
            profitSharing: $pesos('profit_sharing'),
            fees: $pesos('fees'),
            thirteenthMonth: $pesos('thirteenth_month'),
            hazardPay: $pesos('hazard_pay'),
            overtimePay: $pesos('overtime_pay'),
            otherTaxableSupplementary: $pesos('other_taxable_supplementary'),
        );
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, array<string, float>>
     */
    private static function breakdown(array $data): array
    {
        $amount = static fn (string $key): float => self::amount(Money::fromPesos((float) ($data[$key] ?? 0)));

        return [
            'regular' => [
                'basic_salary' => $amount('basic_salary'),
                'representation_allowance' => $amount('representation_allowance'),
                'transportation_allowance' => $amount('transportation_allowance'),
                'cost_of_living_allowance' => $amount('cost_of_living_allowance'),
                'fixed_housing_allowance' => $amount('fixed_housing_allowance'),
                'other_taxable_regular' => $amount('other_taxable_regular'),
            ],
            'supplementary' => [
                'commission' => $amount('commission'),
                'profit_sharing' => $amount('profit_sharing'),
                'fees' => $amount('fees'),
                'thirteenth_month' => $amount('thirteenth_month'),
                'hazard_pay' => $amount('hazard_pay'),
                'overtime_pay' => $amount('overtime_pay'),
                'other_taxable_supplementary' => $amount('other_taxable_supplementary'),
            ],
        ];
    }

    private static function amount(int $centavos): float
    {
        return round(Money::pesos($centavos), 2);
    }
}
