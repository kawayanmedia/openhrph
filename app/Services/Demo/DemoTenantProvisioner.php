<?php

namespace App\Services\Demo;

use App\Actions\GeneratePayrollDateAction;
use App\Models\Company;
use App\Models\Department;
use App\Models\Employee;
use App\Models\EmploymentType;
use App\Models\Group;
use App\Models\Holiday;
use App\Models\Office;
use App\Models\Payroll;
use App\Models\PayrollGroup;
use App\Models\Schedule;
use App\Models\Setting;
use App\Models\User;
use App\Sectors\Catalogues\SectorCatalogue;
use App\Support\Installation\Installation;
use App\Support\Tenancy\TenantContext;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Creates a disposable demo company for a visitor who signed up.
 *
 * Every demo account owns its own tenant, so visitors can edit or delete the
 * sample records without affecting anyone else.
 */
class DemoTenantProvisioner
{
    public function __construct(private readonly TenantContext $tenants) {}

    /**
     * Create the owner account and their seeded demo company.
     *
     * @param  array{name: string, email: string, company_name: string, password: string}  $attributes
     */
    public function provision(array $attributes): User
    {
        return DB::transaction(function () use ($attributes): User {
            [$firstName, $lastName] = $this->splitName($attributes['name']);

            $user = new User;
            $user->forceFill([
                'account_id' => 0,
                'name' => Str::substr($attributes['name'], 0, 32),
                'first_name' => Str::substr($firstName, 0, 25),
                'last_name' => Str::substr($lastName, 0, 25),
                'email' => $attributes['email'],
                'password' => $attributes['password'],
                'owner' => 1,
                'is_temporary_password' => 0,
            ])->save();

            $company = new Company;
            $company->forceFill([
                'user_id' => $user->getKey(),
                'name' => Str::substr($attributes['company_name'], 0, 64),
                'code' => 'DEMO-'.Str::upper(Str::random(6)),
                'type' => Installation::sector() ?? Installation::SECTOR_PRIVATE,
                'email' => $attributes['email'],
                'setup_done' => true,
            ])->save();

            $group = new Group;
            $group->forceFill([
                'company_id' => $company->getKey(),
                'name' => 'Demo Administrator',
                'permissions' => $this->adminPermissions(),
            ])->save();

            $user->forceFill([
                'company_id' => $company->getKey(),
                'group_id' => $group->getKey(),
            ])->save();

            DB::table('company_user')->updateOrInsert(
                [
                    'company_id' => $company->getKey(),
                    'user_id' => $user->getKey(),
                ],
                [
                    'is_owner' => 1,
                    'group_id' => $group->getKey(),
                    'created_at' => now(),
                    'updated_at' => now(),
                ],
            );

            $this->tenants->run($company, function () use ($company): void {
                $this->seedWorkspace($company);
            });

            return $user->fresh();
        });
    }

    private function seedWorkspace(Company $company): void
    {
        SectorCatalogue::seedForCompany((int) $company->getKey());

        $this->seedSettings($company);

        $office = new Office;
        $office->forceFill([
            'name' => 'Head Office',
            'code' => 'HQ',
            'region' => 'NCR',
            'industry' => 'NON-AGRICULTURE',
        ])->save();

        $department = new Department;
        $department->forceFill([
            'name' => 'Human Resources',
            'code' => 'HR',
        ])->save();

        $schedule = new Schedule;
        $schedule->forceFill([
            'name' => 'Default 8am to 5pm',
            'am_in' => '08:00',
            'am_out' => '12:00',
            'pm_in' => '13:00',
            'pm_out' => '17:00',
            'break_time_start' => '12:00',
            'break_time_end' => '13:00',
            'is_active' => true,
            'is_default' => true,
        ])->save();

        $payrollGroup = $this->createPayrollGroup();

        $employees = $this->createEmployees($office, $department, $payrollGroup, $schedule);

        [$periodStart, $periodEnd] = $this->currentCutoff();

        $this->createTimesheets($employees, $periodStart, $periodEnd, $office, $department, $schedule);
        $this->createPayroll($payrollGroup, $periodStart, $periodEnd);

        $this->seedHolidays((int) $company->getKey());
    }

    private function seedSettings(Company $company): void
    {
        $sectorDefaults = Installation::sectorPolicy()->defaults();

        $settings = [
            'company_name' => (string) $company->name,
            'trade_name' => (string) $company->name,
            'type' => Installation::sector() ?? Installation::SECTOR_PRIVATE,
            'tin' => '',
            'rdo' => '',
            'sss' => '',
            'hdmf' => '',
            'phil_health' => '',
            'maximum_pairing_range' => '20',
            'minimum_pairing_range' => '2',
            'night_shift_differential_start' => (string) ($sectorDefaults['night_shift_differential_start'] ?? '22:00'),
            'night_shift_differential_end' => (string) ($sectorDefaults['night_shift_differential_end'] ?? '06:00'),
            'daily_paid' => (string) PayrollGroup::NUMBER_WORKING_DAYS_MONFRI,
            'days_year_no_weeked' => (string) PayrollGroup::NUMBER_WORKING_DAYS_MONFRI,
            'days_year_with_weeked' => (string) PayrollGroup::NUMBER_WORKING_DAYS_MONSAT,
            'deduct_tax' => '1',
            'sss_schedule' => PayrollGroup::PAY_SCHEDULE_EVERYPAY,
            'phil_health_schedule' => PayrollGroup::PAY_SCHEDULE_EVERYPAY,
            'hdmf_schedule' => PayrollGroup::PAY_SCHEDULE_EVERYPAY,
        ];

        foreach ($settings as $key => $value) {
            Setting::query()->updateOrCreate(['key' => $key], ['value' => $value]);
        }
    }

    private function createPayrollGroup(): PayrollGroup
    {
        $payrollGroup = new PayrollGroup;
        $payrollGroup->forceFill([
            'name' => 'Default Kinsenas Katapusan',
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
            'is_default' => true,
        ])->save();

        GeneratePayrollDateAction::run($payrollGroup);

        return $payrollGroup;
    }

    /**
     * @return array<int, Employee>
     */
    private function createEmployees(Office $office, Department $department, PayrollGroup $payrollGroup, Schedule $schedule): array
    {
        $employmentTypeId = EmploymentType::query()->orderBy('id')->value('id');

        $people = [
            ['D-0001', 'Ana', 'Reyes', 'HR Manager', 45000, 1724.14],
            ['D-0002', 'Ben', 'Cruz', 'Payroll Specialist', 32000, 1226.05],
            ['D-0003', 'Carla', 'Diaz', 'Software Engineer', 60000, 2298.85],
        ];

        $employees = [];

        foreach ($people as [$number, $firstName, $lastName, $position, $basicSalary, $dailyRate]) {
            $employee = new Employee;
            $employee->forceFill([
                'employee_number' => $number,
                'first_name' => $firstName,
                'last_name' => $lastName,
                'position' => $position,
                'basic_salary' => $basicSalary,
                'daily_rate' => $dailyRate,
                'payment_type' => 'monthly',
                'payroll_group_id' => $payrollGroup->getKey(),
                'employment_type_id' => $employmentTypeId,
                'office_id' => $office->getKey(),
                'department_id' => $department->getKey(),
                'schedule_id' => $schedule->getKey(),
                'date_hired' => now()->subYears(2)->startOfMonth()->toDateString(),
                'status' => 1,
                'is_monday_friday' => true,
                'regular_holiday_pay' => true,
                'special_holiday_pay' => true,
                'premium_holiday_pay' => true,
                'regular_holiday_special_holiday_pay' => true,
                'rest_day_pay' => true,
                'overtime_pay' => true,
                'night_differential' => true,
            ])->save();

            $employees[] = $employee;
        }

        return $employees;
    }

    /**
     * @param  array<int, Employee>  $employees
     */
    private function createTimesheets(array $employees, Carbon $periodStart, Carbon $periodEnd, Office $office, Department $department, Schedule $schedule): void
    {
        $lastDay = Carbon::parse(min($periodEnd->toDateString(), now()->toDateString()));

        $rows = [];

        foreach ($employees as $employee) {
            for ($day = $periodStart->copy(); $day->lte($lastDay); $day->addDay()) {
                if ($day->isWeekend()) {
                    continue;
                }

                $rows[] = [
                    'company_id' => $employee->company_id,
                    'employee_id' => $employee->getKey(),
                    'employee_number' => (string) $employee->employee_number,
                    'log_date' => $day->toDateString(),
                    'day_type' => 'regular',
                    'regular' => 0,
                    'actual_regular' => 800,
                    'am_login' => '08:00',
                    'am_logout' => '12:00',
                    'pm_login' => '13:00',
                    'pm_logout' => '17:00',
                    'office_id' => $office->getKey(),
                    'department_id' => $department->getKey(),
                    'schedule_id' => $schedule->getKey(),
                    'created_at' => now(),
                    'updated_at' => now(),
                ];
            }
        }

        foreach (array_chunk($rows, 100) as $chunk) {
            DB::table('timesheets')->insert($chunk);
        }
    }

    private function createPayroll(PayrollGroup $payrollGroup, Carbon $periodStart, Carbon $periodEnd): void
    {
        $payroll = new Payroll;
        $payroll->forceFill([
            'payroll_group_id' => $payrollGroup->getKey(),
            'payroll_date' => $periodEnd->toDateString(),
            'date_from' => $periodStart->toDateString(),
            'date_to' => $periodEnd->toDateString(),
            'cut_off' => PayrollGroup::PAY_SCHEDULE_EVERYPAY,
            'status' => Payroll::STATUS_OPEN,
        ])->save();
    }

    private function seedHolidays(int $companyId): void
    {
        if (Installation::countryCode() !== 'PH') {
            return;
        }

        $holidays = config('holidays.'.now()->year);

        if (! is_array($holidays)) {
            return;
        }

        foreach ($holidays as $holiday) {
            Holiday::query()->firstOrCreate(
                ['company_id' => $companyId, 'date' => $holiday['date']],
                [
                    'year' => (int) $holiday['year'],
                    'name' => $holiday['name'],
                    'holiday_type' => $holiday['holiday_type'],
                    'code' => $holiday['code'],
                ],
            );
        }
    }

    /**
     * The semi-monthly cutoff that contains today.
     *
     * @return array{0: Carbon, 1: Carbon}
     */
    private function currentCutoff(): array
    {
        $today = now();

        if ($today->day <= 15) {
            return [$today->copy()->startOfMonth(), $today->copy()->setDay(15)];
        }

        return [$today->copy()->setDay(16), $today->copy()->endOfMonth()];
    }

    /**
     * @return array{0: string, 1: string}
     */
    private function splitName(string $name): array
    {
        $parts = preg_split('/\s+/', trim($name), 2) ?: [];

        return [$parts[0] ?? $name, $parts[1] ?? ''];
    }

    /**
     * Permissions the demo owner needs to reach every admin resource.
     *
     * @return array<string, bool>
     */
    private function adminPermissions(): array
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
