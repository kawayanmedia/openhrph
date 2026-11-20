<?php

namespace App\Services\Demo;

use App\Actions\GeneratePayrollDateAction;
use App\Models\Company;
use App\Models\Department;
use App\Models\Employee;
use App\Models\EmploymentType;
use App\Models\Holiday;
use App\Models\Office;
use App\Models\Payroll;
use App\Models\PayrollGroup;
use App\Models\Schedule;
use App\Models\Setting;
use App\Support\Installation\Installation;
use App\Support\Tenancy\TenantContext;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Fills a company with the sample records a new workspace starts from.
 *
 * The public demo uses this for every visitor, and the desktop setup wizard
 * uses it so a local installation opens on the same populated workspace. The
 * rows are found by natural key, so the seeder can run again (from the
 * "Load sample data" action) without duplicating anything.
 */
class DemoWorkspaceSeeder
{
    /**
     * The employee numbers the sample workspace owns.
     *
     * @var array<int, string>
     */
    public const SAMPLE_EMPLOYEE_NUMBERS = ['D-0001', 'D-0002', 'D-0003'];

    public function __construct(private readonly TenantContext $tenants) {}

    public function seed(Company $company): void
    {
        $this->tenants->run($company, function () use ($company): void {
            $this->seedSettings($company);

            $office = $this->office();
            $department = $this->department();
            $schedule = $this->schedule();
            $payrollGroup = $this->payrollGroup();

            $employees = $this->employees($office, $department, $payrollGroup, $schedule);

            [$periodStart, $periodEnd] = $this->currentCutoff();

            $this->seedTimesheets($employees, $periodStart, $periodEnd, $office, $department, $schedule);
            $this->openPayroll($payrollGroup, $periodStart, $periodEnd);

            $this->seedHolidays((int) $company->getKey());
        });
    }

    /**
     * Whether the company already has the sample employees.
     */
    public function hasSampleData(Company $company): bool
    {
        return $this->tenants->run($company, fn (): bool => Employee::query()
            ->whereIn('employee_number', self::SAMPLE_EMPLOYEE_NUMBERS)
            ->exists());
    }

    /**
     * Whether the company has employees that the sample workspace did not create.
     */
    public function hasCustomEmployees(Company $company): bool
    {
        return $this->tenants->run($company, fn (): bool => Employee::query()
            ->whereNotIn('employee_number', self::SAMPLE_EMPLOYEE_NUMBERS)
            ->exists());
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

    private function office(): Office
    {
        $office = Office::query()->where('name', 'Head Office')->first() ?? new Office;

        $office->forceFill([
            'name' => 'Head Office',
            'code' => 'HQ',
            'region' => 'NCR',
            'industry' => 'NON-AGRICULTURE',
        ])->save();

        return $office;
    }

    private function department(): Department
    {
        $department = Department::query()->where('name', 'Human Resources')->first() ?? new Department;

        $department->forceFill([
            'name' => 'Human Resources',
            'code' => 'HR',
        ])->save();

        return $department;
    }

    private function schedule(): Schedule
    {
        $schedule = Schedule::query()->where('name', 'Default 8am to 5pm')->first() ?? new Schedule;

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

        return $schedule;
    }

    private function payrollGroup(): PayrollGroup
    {
        $payrollGroup = PayrollGroup::query()->where('name', 'Default Kinsenas Katapusan')->first() ?? new PayrollGroup;

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

        if ($payrollGroup->wasRecentlyCreated) {
            GeneratePayrollDateAction::run($payrollGroup);
        }

        return $payrollGroup;
    }

    /**
     * @return array<int, Employee>
     */
    private function employees(Office $office, Department $department, PayrollGroup $payrollGroup, Schedule $schedule): array
    {
        $employmentTypeId = EmploymentType::query()->orderBy('id')->value('id');

        $people = [
            ['D-0001', 'Ana', 'Reyes', 'HR Manager', 45000, 1724.14],
            ['D-0002', 'Ben', 'Cruz', 'Payroll Specialist', 32000, 1226.05],
            ['D-0003', 'Carla', 'Diaz', 'Software Engineer', 60000, 2298.85],
        ];

        $employees = [];

        foreach ($people as [$number, $firstName, $lastName, $position, $basicSalary, $dailyRate]) {
            $employee = Employee::query()->where('employee_number', $number)->first() ?? new Employee;

            $employee->forceFill([
                'employee_number' => $number,
                'first_name' => $firstName,
                'last_name' => $lastName,
                'position' => $position,
                'basic_salary' => $basicSalary,
                'daily_rate' => $dailyRate,
                'payment_type' => Employee::PAYMENT_TYPE_MONTHLY,
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
    private function seedTimesheets(array $employees, Carbon $periodStart, Carbon $periodEnd, Office $office, Department $department, Schedule $schedule): void
    {
        $lastDay = Carbon::parse(min($periodEnd->toDateString(), now()->toDateString()));
        $employeeIds = array_map(fn (Employee $employee): int => (int) $employee->getKey(), $employees);

        $existing = DB::table('timesheets')
            ->whereIn('employee_id', $employeeIds)
            ->whereBetween('log_date', [$periodStart->toDateString(), $lastDay->toDateString()])
            ->get(['employee_id', 'log_date'])
            ->mapWithKeys(fn ($row): array => [$row->employee_id.'|'.$row->log_date => true])
            ->all();

        $rows = [];

        foreach ($employees as $employee) {
            for ($day = $periodStart->copy(); $day->lte($lastDay); $day->addDay()) {
                if ($day->isWeekend() || isset($existing[$employee->getKey().'|'.$day->toDateString()])) {
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

    private function openPayroll(PayrollGroup $payrollGroup, Carbon $periodStart, Carbon $periodEnd): void
    {
        $payroll = Payroll::query()
            ->where('payroll_group_id', $payrollGroup->getKey())
            ->whereDate('date_from', $periodStart->toDateString())
            ->whereDate('date_to', $periodEnd->toDateString())
            ->first() ?? new Payroll;

        $payroll->forceFill([
            'payroll_group_id' => $payrollGroup->getKey(),
            'date_from' => $periodStart->toDateString(),
            'date_to' => $periodEnd->toDateString(),
            'payroll_date' => $periodEnd->toDateString(),
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
}
