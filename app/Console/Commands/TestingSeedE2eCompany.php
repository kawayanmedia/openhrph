<?php

namespace App\Console\Commands;

use App\Models\Company;
use App\Models\Employee;
use App\Models\Group;
use App\Models\Office;
use App\Models\Payroll;
use App\Models\PayrollGroup;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

/**
 * Creates an isolated company with a payroll group, employees, attendance and a
 * draft payroll for the browser test suite. Every row is namespaced with the
 * E2E prefix so `testing:purge-e2e-company` can remove it again.
 */
class TestingSeedE2eCompany extends Command
{
    protected $signature = 'testing:seed-e2e-company';

    protected $description = 'Seed the isolated company used by browser tests';

    public function handle(): int
    {
        $this->call('testing:purge-e2e-company');

        $prefix = (string) config('payroll.e2e.company_prefix', 'E2E ');

        $user = User::query()->where('email', config('payroll.e2e.user_email'))->first() ?? new User;

        $user->forceFill([
            'company_id' => null,
            'name' => $prefix.'Admin',
            'first_name' => 'E2E',
            'last_name' => 'Admin',
            'email' => config('payroll.e2e.user_email'),
            'password' => Hash::make(config('payroll.e2e.password')),
            'owner' => 1,
            'is_temporary_password' => 0,
        ])->save();

        $company = new Company;
        $company->forceFill([
            'user_id' => $user->id,
            'name' => $prefix.'Company',
            'code' => 'E2E',
            'type' => 'Private',
            'email' => config('payroll.e2e.user_email'),
        ])->save();

        $user->forceFill(['company_id' => $company->id])->save();

        $group = new Group;
        $group->forceFill([
            'name' => $prefix.'Admin Group',
            'company_id' => $company->id,
            'permissions' => [
                'Company Settings' => true,
                'Offices' => true,
                'Employees' => true,
                'Users' => true,
                'Departments' => true,
                'Devices' => true,
                'Holidays' => true,
            ],
        ])->save();

        $user->forceFill(['group_id' => $group->id])->save();

        $office = new Office;
        $office->forceFill([
            'name' => $prefix.'Head Office',
            'company_id' => $company->id,
            'code' => 'E2E-HQ',
        ])->save();

        $payrollGroup = new PayrollGroup;
        $payrollGroup->forceFill([
            'name' => $prefix.'Semi-monthly',
            'company_id' => $company->id,
            'frequency' => PayrollGroup::FREQUENCY_SEMI_MONTHLY,
            'days_year' => 261,
            'hours_day' => 8,
            'sss_schedule' => PayrollGroup::PAY_SCHEDULE_EVERYPAY,
            'phil_health_schedule' => PayrollGroup::PAY_SCHEDULE_EVERYPAY,
            'hdmf_schedule' => PayrollGroup::PAY_SCHEDULE_EVERYPAY,
            'tax_schedule' => PayrollGroup::PAY_SCHEDULE_EVERYPAY,
            'is_default' => true,
        ])->save();

        $periodStart = now()->startOfMonth()->toDateString();
        $periodEnd = now()->startOfMonth()->addDays(14)->toDateString();

        $employees = [
            // Salary values are pesos; the Employee mutators store centavos.
            ['E2E-0001', 'Ana', 'Reyes', 30000, 1379.31],
            ['E2E-0002', 'Ben', 'Cruz', 20000, 919.54],
        ];

        foreach ($employees as [$number, $first, $last, $basic, $daily]) {
            $employee = new Employee;
            $employee->forceFill([
                'first_name' => $first,
                'last_name' => $last,
                'company_id' => $company->id,
                'employee_number' => $number,
                'basic_salary' => $basic,
                'daily_rate' => $daily,
                'payroll_group_id' => $payrollGroup->id,
                'office_id' => $office->id,
                'payment_type' => 'monthly',
                'status' => 1,
                'date_hired' => now()->subYear()->toDateString(),
            ])->save();

            for ($day = 0; $day < 10; $day++) {
                $date = now()->startOfMonth()->addDays($day);

                if ($date->isWeekend()) {
                    continue;
                }

                DB::table('timesheets')->insert([
                    'company_id' => $company->id,
                    'employee_id' => $employee->id,
                    'employee_number' => $number,
                    'log_date' => $date->toDateString(),
                    'day_type' => 'regular',
                    'actual_regular' => 800,
                    'regular' => 0,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }

        $payroll = new Payroll;
        $payroll->forceFill([
            'company_id' => $company->id,
            'payroll_group_id' => $payrollGroup->id,
            'payroll_date' => $periodEnd,
            'date_from' => $periodStart,
            'date_to' => $periodEnd,
            'cut_off' => 'every_pay',
            'status' => Payroll::STATUS_OPEN,
        ])->save();

        $this->info(json_encode([
            'company_id' => $company->id,
            'payroll_id' => $payroll->id,
            'payroll_group_id' => $payrollGroup->id,
            'user' => config('payroll.e2e.user_email'),
        ]));

        return self::SUCCESS;
    }
}
