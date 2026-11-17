<?php

namespace Database\Seeders;

use App\Models\Company;
use App\Models\Department;
use App\Models\EmploymentType;
use App\Models\Office;
use App\Models\PayrollGroup;
use App\Models\Position;
use App\Models\Schedule;
use App\Models\Tardiness;
use Database\Seeders\Concerns\SeedsDemoTenant;
use Illuminate\Database\Seeder;

class OrganizationSeeder extends Seeder
{
    use SeedsDemoTenant;

    public function run(): void
    {
        $this->runForDemoTenant(function (Company $company): void {
            $this->seedOffices($company);
            $departments = $this->seedDepartments($company);

            $this->seedPositions($company, $departments);
            $this->seedEmploymentTypes($company);
            $this->seedSchedule($company);
            $this->seedTardiness($company);
            $this->seedPayrollGroup($company);
        });
    }

    private function seedOffices(Company $company): void
    {
        $this->upsertModel(
            Office::class,
            ['company_id' => $company->getKey(), 'code' => 'HQ'],
            [
                'name' => 'Head Office',
                'location' => 'internal',
                'region' => 'NCR',
                'industry' => 'NON-AGRICULTURE',
                'account_number' => '0000-0000-00',
                'disbursing_officer' => '',
            ],
        );

        $this->upsertModel(
            Office::class,
            ['company_id' => $company->getKey(), 'code' => 'BR1'],
            [
                'name' => 'Branch Office',
                'location' => 'internal',
                'region' => 'NCR',
                'industry' => 'NON-AGRICULTURE',
                'account_number' => '0000-0000-01',
                'disbursing_officer' => '',
            ],
        );
    }

    /**
     * @return array<string, Department>
     */
    private function seedDepartments(Company $company): array
    {
        $departments = [
            'HR' => 'Human Resources',
            'FIN' => 'Finance',
            'IT' => 'Information Technology',
            'ADMIN' => 'Administration',
        ];

        return collect($departments)
            ->mapWithKeys(fn (string $name, string $code): array => [
                $code => $this->upsertModel(
                    Department::class,
                    ['company_id' => $company->getKey(), 'code' => $code],
                    ['name' => $name],
                ),
            ])
            ->all();
    }

    /**
     * @param  array<string, Department>  $departments
     */
    private function seedPositions(Company $company, array $departments): void
    {
        $positions = [
            ['HRM', 'Human Resources Manager', 'HR', 80000],
            ['PAY', 'Payroll Accountant', 'FIN', 45000],
            ['DEV', 'Software Developer', 'IT', 70000],
            ['ADM', 'Administrative Assistant', 'ADMIN', 25000],
        ];

        foreach ($positions as [$code, $name, $departmentCode, $basicPay]) {
            $this->upsertModel(
                Position::class,
                ['company_id' => $company->getKey(), 'code' => $code],
                [
                    'name' => $name,
                    'department_id' => $departments[$departmentCode]->getKey(),
                    'basic_pay' => $basicPay,
                    'is_vacant' => false,
                ],
            );
        }
    }

    private function seedEmploymentTypes(Company $company): void
    {
        $types = [
            ['REG', 'Regular', true],
            ['PROB', 'Probationary', true],
            ['CON', 'Contractual', true],
            ['CONLT', 'Consultant', true],
            ['INT', 'Intern', false],
            ['TRAI', 'Trainee', true],
            ['CAS', 'Casual', true],
            ['ONCALL', 'On-call', true],
            ['UNAG', 'Under Agency', true],
            ['SEP', 'Separated', true],
        ];

        foreach ($types as [$code, $name, $isPayroll]) {
            $this->upsertModel(
                EmploymentType::class,
                ['company_id' => $company->getKey(), 'code' => $code],
                ['name' => $name, 'is_payroll' => $isPayroll],
            );
        }
    }

    private function seedSchedule(Company $company): void
    {
        $this->upsertModel(
            Schedule::class,
            ['company_id' => $company->getKey(), 'name' => 'Regular 8am-5pm'],
            [
                'am_in' => '08:00',
                'am_out' => '12:00',
                'pm_in' => '13:00',
                'pm_out' => '17:00',
                'break_time_start' => '12:00',
                'break_time_end' => '13:00',
                'is_default' => true,
                'is_active' => true,
            ],
        );
    }

    private function seedTardiness(Company $company): void
    {
        $this->upsertModel(
            Tardiness::class,
            ['company_id' => $company->getKey(), 'name' => 'Standard 15-minute grace'],
            [
                'grace_period' => 15,
                'start_deducting' => 0,
                'affected' => null,
            ],
        );
    }

    private function seedPayrollGroup(Company $company): void
    {
        $this->upsertModel(
            PayrollGroup::class,
            ['company_id' => $company->getKey(), 'name' => 'Default Semi-Monthly'],
            [
                'days_year' => PayrollGroup::NUMBER_WORKING_DAYS_MONFRI,
                'hours_day' => 8,
                'frequency' => PayrollGroup::FREQUENCY_SEMI_MONTHLY,
                'first_cut_off' => 10,
                'first_pay_date' => 15,
                'second_cut_off' => 25,
                'second_pay_date' => 30,
                'first_run' => 15,
                'second_run' => 30,
                'pay_date_non_working' => PayrollGroup::NON_WORKING_DATE_BEFORE,
                'tax_schedule' => PayrollGroup::PAY_SCHEDULE_EVERYPAY,
                'sss_schedule' => PayrollGroup::PAY_SCHEDULE_EVERYPAY,
                'phil_health_schedule' => PayrollGroup::PAY_SCHEDULE_EVERYPAY,
                'hdmf_schedule' => PayrollGroup::PAY_SCHEDULE_EVERYPAY,
                'compute_tardiness' => false,
                'compute_overbreak' => false,
                'compute_undertime' => false,
                'is_default' => true,
            ],
        );
    }
}
