<?php

namespace App\Console\Commands;

use App\Models\AttendanceScheduleAssignment;
use App\Models\AttendanceShift;
use App\Models\Company;
use App\Models\Employee;
use App\Models\Group;
use App\Models\Office;
use App\Models\Payroll;
use App\Models\PayrollDate;
use App\Models\PayrollGroup;
use App\Models\User;
use App\Services\Payroll\RateCatalogue;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

/**
 * Creates the isolated tenants the browser suite runs against: a payroll company,
 * a bare onboarding company and an attendance company with three shifts and two
 * semi-monthly cut-offs. Every row is namespaced with the E2E prefix so
 * `testing:purge-e2e-company` can remove it again.
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
            'email_verified_at' => now(),
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
            'permissions' => $this->adminPermissions(),
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

        $onboarding = $this->seedOnboardingCompany($prefix);
        $attendance = $this->seedAttendanceCompany($prefix);

        $this->info(json_encode([
            'company_id' => $company->id,
            'payroll_id' => $payroll->id,
            'payroll_group_id' => $payrollGroup->id,
            'user' => config('payroll.e2e.user_email'),
            'onboarding' => $onboarding,
            'attendance' => $attendance,
        ]));

        return self::SUCCESS;
    }

    /**
     * A tenant for the portal attendance journey: three employees on different
     * shifts, two semi-monthly cut-offs in November and a draft payroll for each.
     *
     * @return array{
     *     company_id: int,
     *     payroll_ids: array{first_half: int, second_half: int},
     *     period: array{from: string, to: string, first_half_end: string, second_half_start: string},
     *     admin: array{email: string, password: string},
     *     employees: array<int, array<string, mixed>>
     * }
     */
    protected function seedAttendanceCompany(string $prefix): array
    {
        $month = now()->setMonth((int) config('payroll.e2e.attendance_month', 11))->startOfMonth();
        $periodStart = $month->toDateString();
        $periodEnd = $month->copy()->endOfMonth()->toDateString();
        $firstHalfEnd = $month->copy()->addDays(14)->toDateString();
        $secondHalfStart = $month->copy()->addDays(15)->toDateString();

        $admin = $this->seedCompanyOwner(
            (string) config('payroll.e2e.attendance_user_email'),
            (string) config('payroll.e2e.attendance_password'),
            $prefix.'Attendance',
            $prefix.'Attendance Co',
            'E2EATT',
            $prefix.'Attendance Group',
        );

        $company = $admin['company'];

        $office = new Office;
        $office->forceFill([
            'name' => $prefix.'Attendance Office',
            'company_id' => $company->id,
            'code' => 'E2E-ATT',
        ])->save();

        $payrollGroup = new PayrollGroup;
        $payrollGroup->forceFill([
            'name' => $prefix.'Attendance Semi-monthly',
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

        RateCatalogue::sync((int) $company->id);

        $cutoffs = [
            ['payroll_date' => $firstHalfEnd, 'start' => $periodStart, 'end' => $firstHalfEnd],
            ['payroll_date' => $periodEnd, 'start' => $secondHalfStart, 'end' => $periodEnd],
        ];

        $payrollIds = [];

        foreach ($cutoffs as $index => $cutoff) {
            $payrollDate = new PayrollDate;
            $payrollDate->forceFill([
                'company_id' => $company->id,
                'payroll_group_id' => $payrollGroup->id,
                'payroll_date' => $cutoff['payroll_date'],
                'attendance_date_start' => $cutoff['start'],
                'attendance_date_end' => $cutoff['end'],
                'status' => PayrollDate::STATUS_OPEN,
            ])->save();

            $payroll = new Payroll;
            $payroll->forceFill([
                'company_id' => $company->id,
                'payroll_group_id' => $payrollGroup->id,
                'payroll_date' => $cutoff['payroll_date'],
                'date_from' => $cutoff['start'],
                'date_to' => $cutoff['end'],
                'cut_off' => 'every_pay',
                'type' => Payroll::TYPE_REGULAR,
                'status' => Payroll::STATUS_OPEN,
            ])->save();

            $payrollIds[$index === 0 ? 'first_half' : 'second_half'] = (int) $payroll->id;
        }

        $employees = [];

        foreach ($this->attendanceEmployees() as $key => $definition) {
            $shift = new AttendanceShift;
            $shift->forceFill([
                'company_id' => $company->id,
                'name' => $definition['shift'],
                'code' => strtoupper($key),
                'type' => $definition['shift_type'],
                'segments' => $definition['segments'],
                'breaks' => $definition['breaks'],
                'night_window_start' => $definition['shift_type'] === AttendanceShift::TYPE_OVERNIGHT ? '22:00' : null,
                'night_window_end' => $definition['shift_type'] === AttendanceShift::TYPE_OVERNIGHT ? '06:00' : null,
                'is_default' => false,
                'is_active' => true,
            ])->save();

            $assignment = new AttendanceScheduleAssignment;
            $assignment->forceFill([
                'company_id' => $company->id,
                'employee_id' => null,
                'shift_id' => $shift->id,
                'weekdays' => [1, 2, 3, 4, 5],
                'effective_from' => $periodStart,
                'reason' => 'Browser test shift',
            ]);

            $employee = new Employee;
            $employee->forceFill([
                'first_name' => $definition['first_name'],
                'last_name' => $definition['last_name'],
                'company_id' => $company->id,
                'employee_number' => $definition['employee_number'],
                'basic_salary' => $definition['basic_salary'],
                'daily_rate' => $definition['daily_rate'],
                'payroll_group_id' => $payrollGroup->id,
                'office_id' => $office->id,
                'payment_type' => 'monthly',
                'status' => 1,
                'date_hired' => now()->subYear()->toDateString(),
            ])->save();

            $assignment->forceFill(['employee_id' => $employee->id])->save();

            $portalUser = User::query()
                ->where('email', config('payroll.e2e.attendance_employee_emails.'.$key))
                ->first() ?? new User;

            $portalUser->forceFill([
                'company_id' => $company->id,
                'group_id' => $admin['group']->id,
                'employee_id' => $employee->id,
                'name' => $definition['first_name'].' '.$definition['last_name'],
                'first_name' => $definition['first_name'],
                'last_name' => $definition['last_name'],
                'email' => config('payroll.e2e.attendance_employee_emails.'.$key),
                'password' => Hash::make(config('payroll.e2e.attendance_password')),
                'owner' => 0,
                'is_temporary_password' => 0,
                'email_verified_at' => now(),
            ])->save();

            $employees[] = [
                'key' => $key,
                'id' => (int) $employee->id,
                'name' => $definition['first_name'].' '.$definition['last_name'],
                'label' => $definition['last_name'].', '.$definition['first_name'],
                'employee_number' => $definition['employee_number'],
                'email' => config('payroll.e2e.attendance_employee_emails.'.$key),
                'password' => config('payroll.e2e.attendance_password'),
                'shift' => $definition['shift'],
                'time_in' => $definition['time_in'],
                'time_out' => $definition['time_out'],
                'daily_rate' => $definition['daily_rate'],
                'basic_salary' => $definition['basic_salary'],
            ];
        }

        return [
            'company_id' => (int) $company->id,
            'payroll_ids' => $payrollIds,
            'period' => [
                'from' => $periodStart,
                'to' => $periodEnd,
                'first_half_end' => $firstHalfEnd,
                'second_half_start' => $secondHalfStart,
            ],
            'admin' => [
                'email' => (string) config('payroll.e2e.attendance_user_email'),
                'password' => (string) config('payroll.e2e.attendance_password'),
            ],
            'employees' => $employees,
        ];
    }

    /**
     * The three shift patterns the attendance journey punches through.
     *
     * Breaks are explicit so a two-punch day is paid for the scheduled span
     * only; the resolver subtracts them from every in/out pair.
     *
     * @return array<string, array<string, mixed>>
     */
    protected function attendanceEmployees(): array
    {
        return [
            'day' => [
                'first_name' => 'Dana',
                'last_name' => 'Day',
                'employee_number' => 'E2E-A-0001',
                'basic_salary' => 30000,
                'daily_rate' => 1379.31,
                'shift' => 'E2E Day Shift',
                'shift_type' => AttendanceShift::TYPE_FIXED,
                'segments' => [['08:00', '12:00'], ['13:00', '17:00']],
                'breaks' => [['12:00', '13:00']],
                'time_in' => '08:00',
                'time_out' => '17:00',
            ],
            'night' => [
                'first_name' => 'Nico',
                'last_name' => 'Night',
                'employee_number' => 'E2E-A-0002',
                'basic_salary' => 32000,
                'daily_rate' => 1471.26,
                'shift' => 'E2E Night Shift',
                'shift_type' => AttendanceShift::TYPE_OVERNIGHT,
                'segments' => [['22:00', '06:00']],
                'breaks' => null,
                'time_in' => '22:00',
                'time_out' => '06:00',
            ],
            'split' => [
                'first_name' => 'Sofia',
                'last_name' => 'Split',
                'employee_number' => 'E2E-A-0003',
                'basic_salary' => 28000,
                'daily_rate' => 1287.36,
                'shift' => 'E2E Split Shift',
                'shift_type' => AttendanceShift::TYPE_SPLIT,
                'segments' => [['06:00', '10:00'], ['14:00', '18:00']],
                'breaks' => [['10:00', '14:00']],
                'time_in' => '06:00',
                'time_out' => '18:00',
            ],
        ];
    }

    /**
     * Create an owner account with its own company and admin group.
     *
     * @return array{company: Company, group: Group}
     */
    protected function seedCompanyOwner(
        string $email,
        string $password,
        string $namePrefix,
        string $companyName,
        string $companyCode,
        string $groupName,
    ): array {
        $user = User::query()->where('email', $email)->first() ?? new User;

        $user->forceFill([
            'company_id' => null,
            'name' => $namePrefix.' Admin',
            'first_name' => 'E2E',
            'last_name' => 'Attendance',
            'email' => $email,
            'password' => Hash::make($password),
            'owner' => 1,
            'is_temporary_password' => 0,
            'email_verified_at' => now(),
        ])->save();

        $company = new Company;
        $company->forceFill([
            'user_id' => $user->id,
            'name' => $companyName,
            'code' => $companyCode,
            'type' => 'Private',
            'email' => $email,
        ])->save();

        $group = new Group;
        $group->forceFill([
            'name' => $groupName,
            'company_id' => $company->id,
            'permissions' => $this->adminPermissions(),
        ])->save();

        $user->forceFill([
            'company_id' => $company->id,
            'group_id' => $group->id,
        ])->save();

        return ['company' => $company, 'group' => $group];
    }

    /**
     * A bare tenant for the setup journey: only an owner and their group exist,
     * so the browser test has to create every payroll prerequisite itself.
     *
     * @return array{company_id: int, user: string, password: string}
     */
    protected function seedOnboardingCompany(string $prefix): array
    {
        $user = User::query()->where('email', config('payroll.e2e.onboarding_user_email'))->first() ?? new User;

        $user->forceFill([
            'company_id' => null,
            'name' => $prefix.'Onboarding Admin',
            'first_name' => 'E2E',
            'last_name' => 'Onboarding',
            'email' => config('payroll.e2e.onboarding_user_email'),
            'password' => Hash::make(config('payroll.e2e.onboarding_password')),
            'owner' => 1,
            'is_temporary_password' => 0,
            'email_verified_at' => now(),
        ])->save();

        $company = new Company;
        $company->forceFill([
            'user_id' => $user->id,
            'name' => $prefix.'Onboarding Co',
            'code' => 'E2EOB',
            'type' => 'Private',
            'email' => config('payroll.e2e.onboarding_user_email'),
            'setup_done' => false,
        ])->save();

        $group = new Group;
        $group->forceFill([
            'name' => $prefix.'Onboarding Group',
            'company_id' => $company->id,
            'permissions' => $this->adminPermissions(),
        ])->save();

        $user->forceFill([
            'company_id' => $company->id,
            'group_id' => $group->id,
        ])->save();

        return [
            'company_id' => $company->id,
            'user' => config('payroll.e2e.onboarding_user_email'),
            'password' => config('payroll.e2e.onboarding_password'),
        ];
    }

    /**
     * Permissions the seeded owners need to reach every admin resource.
     *
     * @return array<string, bool>
     */
    protected function adminPermissions(): array
    {
        return [
            'Company Settings' => true,
            'Offices' => true,
            'Employees' => true,
            'Users' => true,
            'Departments' => true,
            'Devices' => true,
            'Holidays' => true,
        ];
    }
}
