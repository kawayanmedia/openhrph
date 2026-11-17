<?php

namespace Database\Seeders;

use App\Models\Company;
use App\Models\Deduction;
use App\Models\Loan;
use App\Models\Rate;
use App\Support\Payroll\CompensationCatalogue;
use Database\Seeders\Concerns\SeedsDemoTenant;
use Illuminate\Database\Seeder;

class CompensationSeeder extends Seeder
{
    use SeedsDemoTenant;

    public function run(): void
    {
        // The compensation catalogue is system-wide, so it is seeded once
        // outside any company context.
        $this->seedCompensations();

        $this->runForDemoTenant(function (Company $company): void {
            $this->seedRates($company);
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

    /**
     * Every extra earning lives in the single system-wide compensations
     * catalogue. The `group` column tells payroll whether the row is paid as an
     * allowance, bonus, commission or regular compensation.
     */
    private function seedCompensations(): void
    {
        CompensationCatalogue::sync();
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
