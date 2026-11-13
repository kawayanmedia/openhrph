<?php

namespace Database\Seeders;

use App\Models\Allowance;
use App\Models\Bonus;
use App\Models\Commission;
use App\Models\Company;
use App\Models\Compensation;
use App\Models\Deduction;
use App\Models\Loan;
use App\Models\Rate;
use Database\Seeders\Concerns\SeedsDemoTenant;
use Illuminate\Database\Seeder;

class CompensationSeeder extends Seeder
{
    use SeedsDemoTenant;

    public function run(): void
    {
        $this->runForDemoTenant(function (Company $company): void {
            $this->seedRates($company);
            $this->seedCompensations($company);
            $this->seedAllowances($company);
            $this->seedBonuses($company);
            $this->seedCommissions($company);
            $this->seedDeductions($company);
            $this->seedLoans($company);
        });
    }

    private function seedRates(Company $company): void
    {
        $rates = [
            ['regular', 'Regular Day', false, false, 1.00, 1.25, 1.10, 1.375],
            ['rd', 'Rest Day', true, false, 1.30, 1.69, 1.43, 1.859],
            ['sh', 'Special Holiday', false, true, 1.30, 1.69, 1.43, 1.859],
            ['rh', 'Regular Holiday', false, true, 2.00, 2.60, 2.20, 2.86],
            ['rd-sh', 'Rest Day - Special Holiday', true, true, 1.50, 1.95, 1.65, 2.145],
            ['rd-rh', 'Rest Day - Regular Holiday', true, true, 2.60, 3.38, 2.86, 3.718],
            ['2rh', 'Double Regular Holiday', false, true, 3.00, 3.90, 3.30, 4.29],
            ['rd-2rh', 'Rest Day - Double Regular Holiday', true, true, 3.90, 5.07, 4.29, 5.577],
            ['rh-sh', 'Regular Holiday, Special Holiday', false, true, 2.60, 3.38, 2.86, 3.718],
            ['rd-rh-sh', 'Rest Day - Regular Holiday, Special Holiday', true, true, 3.00, 3.90, 3.30, 4.29],
            ['pl', 'Paid Leave', false, false, 1.00, 1.00, 1.00, 1.00],
            ['upl', 'Unpaid Leave', false, false, 1.00, 1.00, 1.00, 1.00],
        ];

        foreach ($rates as [$code, $dayType, $restDay, $holiday, $regular, $ot, $nt, $ntOt]) {
            $this->upsertModel(
                Rate::class,
                ['company_id' => $company->getKey(), 'code' => $code],
                [
                    'day_type' => $dayType,
                    'is_rest_day' => $restDay,
                    'is_holiday' => $holiday,
                    'regular' => $regular,
                    'ot' => $ot,
                    'nt' => $nt,
                    'nt_ot' => $ntOt,
                ],
            );
        }
    }

    private function seedCompensations(Company $company): void
    {
        $compensations = [
            ['BASIC PAY ADJUSTMENT', 'BASIC PAY ADJUSTMENT', 'BASICSALARY', 'Basic Salary', true, 'fully_taxable'],
            ['REG PAY', 'REGULAR PAY', 'BASICSALARY', 'Basic Salary', true, 'fully_taxable'],
            ['REPRESENTATION', 'REPRESENTATION ALLOWANCE', 'REPRESENTATION', 'Representation', true, 'fully_taxable'],
            ['TRANSPORTATION', 'TRANSPORTATION ALLOWANCE', 'TRANSPORTATION', 'Transportation', true, 'fully_taxable'],
            ['COLA', 'COST OF LIVING ALLOWANCE', 'COLA', 'Cost of living allowance', true, 'fully_taxable'],
            ['FIXED HOUSING ALLOW', 'FIXED HOUSING ALLOWANCE', 'FIXEDHOUSING', 'Fixed housing allowance', true, 'fully_taxable'],
            ['OTHER TAXABLE 1', 'OTHER TAXABLE 1', 'OTHER1', 'Others Regular 1', true, 'fully_taxable'],
            ['OTHER TAXABLE 2', 'OTHER TAXABLE 2', 'OTHER2', 'Others Regular 2', true, 'fully_taxable'],
            ['COMMISSION', 'COMMISSION', 'COMMISSION', 'Commission', true, 'fully_taxable'],
            ['PROFIT SHARING', 'PROFIT SHARING', 'PROFITSHARING', 'Profit sharing', true, 'fully_taxable'],
            ['FEES', 'FEES', 'FEES', "Fees including Director's fees", true, 'fully_taxable'],
            ['HAZARD PAY', 'HAZARD PAY', 'HAZARDPAY', 'Hazard pay', true, 'fully_taxable'],
            ['OTHER SUPPLEMENTARY 1', 'OTHER SUPPLEMENTARY 1', 'OTHSUPP1', 'Others Supplementary 1', true, 'fully_taxable'],
            ['OTHER SUPPLEMENTARY 2', 'OTHER SUPPLEMENTARY 2', 'OTHSUPP2', 'Others Supplementary 2', true, 'fully_taxable'],
            ['NON TAXABLE COMPENSATION', 'NON-TAXABLE COMPENSATION', 'NTAXCOMPENSATION', 'Salaries and other forms of compensation', false, 'fully_nontaxable'],
            ['DE MINIMIS', 'DE MINIMIS BENEFITS', 'DEMINIMISBENEFITS', 'De minimis benefits', false, 'fully_nontaxable'],
            ['13TH MONTH PAY', '13TH MONTH PAY', '13THMONTH', '13th month pay', false, 'fully_nontaxable'],
            ['FRINGE BENEFIT', 'FRINGE BENEFIT', 'FRINGEBENEFIT', 'Fringe benefit', true, 'fully_taxable'],
            ['HOLIDAY PAY', 'HOLIDAY PAY', 'HOLIDAYPAY', 'Holiday pay', false, 'fully_nontaxable'],
            ['OVERTIME PAY', 'OVERTIME PAY', 'OVERTIMEPAY', 'Overtime pay', false, 'fully_nontaxable'],
            ['NIGHT DIFFERENTIAL', 'NIGHT DIFFERENTIAL', 'NIGHT_SHIFT_DIFFERENTIAL', 'Night shift differential', false, 'fully_nontaxable'],
            ['SEPARATION PAY', 'SEPARATION PAY', 'OTHSUPP1', 'Others Supplementary 1', false, 'fully_nontaxable'],
        ];

        foreach ($compensations as [$code, $name, $taxClassCode, $taxClass, $isTaxable, $taxOption]) {
            $this->upsertModel(
                Compensation::class,
                ['company_id' => $company->getKey(), 'code' => $code],
                [
                    'name' => $name,
                    'tax_class_code' => $taxClassCode,
                    'tax_class' => $taxClass,
                    'tax_option' => $taxOption,
                    'is_taxable' => $isTaxable,
                    'amount' => 0,
                    'status' => 'Active',
                ],
            );
        }
    }

    private function seedAllowances(Company $company): void
    {
        $allowances = [
            ['Representation Allowance', 2000, 'fully_taxable'],
            ['Transportation Allowance', 1500, 'fully_taxable'],
            ['Cost of Living Allowance (COLA)', 1000, 'fully_taxable'],
            ['Fixed Housing Allowance', 5000, 'fully_taxable'],
            ['Other Taxable Regular Compensation', 0, 'fully_taxable'],
            ['Rice Subsidy', 2000, 'fully_nontaxable'],
            ['Uniform and Clothing Allowance', 6000, 'fully_nontaxable'],
            ['Medical Cash Allowance', 1500, 'fully_nontaxable'],
            ['Laundry Allowance', 300, 'fully_nontaxable'],
        ];

        foreach ($allowances as [$name, $amount, $taxOption]) {
            $this->upsertModel(
                Allowance::class,
                ['company_id' => $company->getKey(), 'name' => $name],
                ['amount' => $amount, 'tax_option' => $taxOption],
            );
        }
    }

    private function seedBonuses(Company $company): void
    {
        $bonuses = [
            ['13th Month Pay', Bonus::AMOUNT_BASIS_SALARY_BASED, Bonus::FREQUENCY_ONE_TIME, false, 90000],
            ['Fixed Taxable Bonus', Bonus::AMOUNT_BASIS_FIXED, Bonus::FREQUENCY_ONE_TIME, true, null],
            ['Fixed Non-Taxable Bonus', Bonus::AMOUNT_BASIS_FIXED, Bonus::FREQUENCY_PERIODIC, false, 90000],
        ];

        foreach ($bonuses as [$name, $amountBasis, $frequency, $isTaxable, $maximumNonTax]) {
            $this->upsertModel(
                Bonus::class,
                ['company_id' => $company->getKey(), 'name' => $name],
                [
                    'amount_basis' => $amountBasis,
                    'frequency' => $frequency,
                    'is_taxable' => $isTaxable,
                    'maximum_non_tax' => $maximumNonTax,
                ],
            );
        }
    }

    private function seedCommissions(Company $company): void
    {
        $commissions = [
            ['Fixed Sales Commission', Bonus::AMOUNT_BASIS_FIXED, true, null],
            ['Salary Based Sales Commission', Bonus::AMOUNT_BASIS_SALARY_BASED, true, null],
        ];

        foreach ($commissions as [$name, $amountBasis, $isTaxable, $maximumNonTax]) {
            $this->upsertModel(
                Commission::class,
                ['company_id' => $company->getKey(), 'name' => $name],
                [
                    'amount_basis' => $amountBasis,
                    'is_taxable' => $isTaxable,
                    'maximum_non_tax' => $maximumNonTax,
                ],
            );
        }
    }

    private function seedDeductions(Company $company): void
    {
        $deductions = [
            'SSS Loan Payment',
            'Pag-IBIG Loan Payment',
            'Company Loan Payment',
            'Uniform Deduction',
            'Cash Advance',
        ];

        foreach ($deductions as $name) {
            $this->upsertModel(
                Deduction::class,
                ['company_id' => $company->getKey(), 'name' => $name],
            );
        }
    }

    private function seedLoans(Company $company): void
    {
        $loans = [
            ['SSS', 'SSS-SALARY', 'SSS Salary Loan'],
            ['SSS', 'SSS-EMERGENCY', 'SSS Emergency Loan'],
            ['HDMF', 'HDMF-MPL', 'Pag-IBIG Multi-Purpose Loan'],
            ['HDMF', 'HDMF-CALAMITY', 'Pag-IBIG Calamity Loan'],
            ['Company', 'COMPANY-LOAN', 'Company Loan'],
        ];

        foreach ($loans as [$agency, $code, $name]) {
            $this->upsertModel(
                Loan::class,
                ['company_id' => $company->getKey(), 'code' => $code],
                [
                    'loan_agency' => $agency,
                    'name' => $name,
                    'is_default' => true,
                    'is_active' => true,
                ],
            );
        }
    }
}
