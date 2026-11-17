<?php

namespace App\Http\Controllers\Api;

use App\Enums\Sector;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\CalculateTakeHomePayRequest;
use App\Http\Requests\Api\CalculateWithholdingTaxRequest;
use App\Services\Calculators\TakeHomePayCalculator;
use App\Services\Calculators\WithholdingTaxCalculator;
use App\Services\Calculators\WithholdingTaxInput;
use App\Services\Statutory\Philippines\Bir\TaxPeriod;
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
    ): JsonResponse {
        $data = $request->validated();
        $result = $calculator->compute(self::input($data));

        return response()->json([
            'data' => [
                'period' => $result->period->value,
                'period_label' => $result->period->label(),
                'currency' => 'PHP',
                'regular_compensation' => self::amount($result->regularCompensation),
                'supplementary_compensation' => self::amount($result->supplementaryCompensation),
                'gross_compensation_income' => self::amount($result->grossCompensation),
                'non_taxable_benefits' => self::amount($result->nonTaxableBenefits),
                'taxable_compensation_income' => self::amount($result->taxableCompensation),
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
                        'agency' => $result->sector->isGovernment() ? 'GSIS' : 'SSS',
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
