<?php

use App\Models\Employee;
use App\Models\Holiday;
use App\Models\Office;
use App\Models\Payroll;
use App\Models\PayrollGroup;
use App\Models\Timesheet;
use App\Services\Payroll\Engine\PhpPayrollEngine;
use App\Support\Tenancy\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->artisan('payroll:statutory:import')->assertExitCode(0);

    PayrollGroup::factory()->create([
        'id' => 1,
        'company_id' => 9,
        'frequency' => 'semi-monthly',
        'days_year' => 261,
        'hours_day' => 8,
        'sss_schedule' => 'every_pay',
        'phil_health_schedule' => 'every_pay',
        'hdmf_schedule' => 'every_pay',
        'tax_schedule' => 'every_pay',
    ]);

    Office::factory()->create([
        'id' => 1,
        'company_id' => 9,
        'region' => 'NCR',
        'industry' => 'NON-AGRICULTURE',
    ]);
});

/**
 * @param  array<string, mixed>  $attributes
 */
function dayTypeEmployee(int $id, string $number, array $attributes = []): void
{
    Employee::withoutEvents(fn () => Employee::factory()->create(array_merge([
        'id' => $id,
        'company_id' => 9,
        'payroll_group_id' => 1,
        'office_id' => 1,
        'employee_number' => $number,
        'first_name' => 'DAYTYPE',
        'last_name' => strtoupper($number),
        'basic_salary' => 26100,
        'daily_rate' => 1200,
        'dependents' => '0',
        'payment_type' => 'daily',
    ], $attributes)));

    // Benefit columns are not mass-assignable on the Employee model.
    DB::table('employees')->where('id', $id)->update([
        'rest_day_pay' => 1,
        'regular_holiday_pay' => 1,
        'special_holiday_pay' => 1,
        'premium_holiday_pay' => 1,
        'regular_holiday_special_holiday_pay' => 1,
        'overtime_pay' => 1,
        'night_differential' => 1,
        'deminimis' => 0,
    ]);
}

function dayTypePayroll(int $id, string $from, string $to, string $payrollDate): void
{
    Payroll::factory()->create([
        'id' => $id,
        'company_id' => 9,
        'payroll_group_id' => 1,
        'status' => 'open',
        'cut_off' => 'last_pay',
        'date_from' => $from,
        'date_to' => $to,
        'payroll_date' => $payrollDate,
        'type' => 'regular',
        'meta' => [],
    ]);
}

function runDayTypePayroll(int $id): object
{
    $payroll = app(TenantContext::class)->run(9, fn (): Payroll => Payroll::findOrFail($id));

    (new PhpPayrollEngine)->process($payroll);

    return DB::table('payroll_details')
        ->where('payroll_id', $id)
        ->where('company_id', 9)
        ->first();
}

it('persists the correct premium for every day type from the timesheet rows', function () {
    dayTypeEmployee(601, 'E-0601');
    dayTypePayroll(91, '2026-11-01', '2026-11-15', '2026-11-15');

    $dayTypes = ['regular', 'rd', 'sh', 'rd-sh', 'rh', 'rd-rh', '2rh', 'rd-2rh', 'rh-sh', 'rd-rh-sh'];

    foreach ($dayTypes as $index => $dayType) {
        Timesheet::factory()->create([
            'company_id' => 9,
            'employee_id' => 601,
            'employee_number' => 'E-0601',
            'log_date' => sprintf('2026-11-%02d', $index + 1),
            'day_type' => $dayType,
            'actual_regular' => 8,
        ]);
    }

    $detail = runDayTypePayroll(91);

    expect($detail)->not->toBeNull()
        ->and((int) $detail->regular_hours)->toBe(80)
        ->and((int) $detail->regular_pay)->toBe(2_664_000)   // 22.20 × ₱1,200.00
        ->and((int) $detail->rest_day_pay)->toBe(36_000)     // 0.30 × ₱1,200.00
        ->and((int) $detail->holiday_pay)->toBe(1_428_000)   // 11.90 × ₱1,200.00
        ->and((int) $detail->gross_income)->toBe(2_664_000)
        ->and((int) $detail->daily_rate)->toBe(120_000)
        ->and((int) $detail->basic_monthly_salary)->toBe(2_610_000);
});

it('pays a paid leave day and deducts an absence through the persisted payroll', function () {
    dayTypeEmployee(602, 'E-0602');
    dayTypeEmployee(603, 'E-0603', ['payment_type' => 'monthly', 'daily_rate' => 0]);
    dayTypePayroll(92, '2026-11-01', '2026-11-15', '2026-11-15');

    // Daily-paid employee: one paid leave day earns the daily rate.
    Timesheet::factory()->create([
        'company_id' => 9,
        'employee_id' => 602,
        'employee_number' => 'E-0602',
        'log_date' => '2026-11-02',
        'day_type' => 'pl',
        'is_leave' => true,
        'is_paid_leave' => true,
    ]);

    // Monthly-paid employee: one absence loses one day from the fixed basic.
    Timesheet::factory()->create([
        'company_id' => 9,
        'employee_id' => 603,
        'employee_number' => 'E-0603',
        'log_date' => '2026-11-02',
        'day_type' => 'absent',
        'is_absent' => true,
    ]);

    $payroll = app(TenantContext::class)->run(9, fn (): Payroll => Payroll::findOrFail(92));

    (new PhpPayrollEngine)->process($payroll);

    $daily = DB::table('payroll_details')->where('payroll_id', 92)->where('employee_id', 602)->first();
    $monthly = DB::table('payroll_details')->where('payroll_id', 92)->where('employee_id', 603)->first();

    expect((int) $daily->regular_pay)->toBe(120_000)
        ->and((int) $daily->gross_income)->toBe(120_000);

    expect((int) $monthly->regular_pay)->toBe(1_185_000) // ₱13,050.00 - ₱1,200.00
        ->and((int) $monthly->absent_days)->toBe(1)
        ->and((int) $monthly->absent_days_amount)->toBe(120_000)
        ->and((int) $monthly->gross_income)->toBe(1_185_000);
});

it('pays a holiday premium when the timesheet was classified by generation', function () {
    app(TenantContext::class)->run(9, fn () => Holiday::factory()->create([
        'company_id' => 9,
        'year' => 2026,
        'name' => 'Founders Day',
        'holiday_type' => 'regular',
        'code' => 'rh',
        'date' => '2026-11-02',
    ]));

    dayTypeEmployee(604, 'E-0604');
    dayTypePayroll(93, '2026-11-01', '2026-11-15', '2026-11-15');

    // This is the row shape GenerateTimesheetGateway writes: the day is
    // classified regular/rest and the payable code lives on day_rate_code.
    Timesheet::factory()->create([
        'company_id' => 9,
        'employee_id' => 604,
        'employee_number' => 'E-0604',
        'log_date' => '2026-11-02',
        'day_type' => 'regular',
        'day_rate_code' => 'rh',
        'holiday_code' => 'rh',
        'actual_regular' => 8,
    ]);

    $detail = runDayTypePayroll(93);

    expect((int) $detail->regular_pay)->toBe(240_000)
        ->and((int) $detail->holiday_pay)->toBe(120_000);
});

it('pays a punch-only timesheet row', function () {
    dayTypeEmployee(605, 'E-0605');
    dayTypePayroll(94, '2026-11-01', '2026-11-15', '2026-11-15');

    Timesheet::factory()->create([
        'company_id' => 9,
        'employee_id' => 605,
        'employee_number' => 'E-0605',
        'log_date' => '2026-11-02',
        'day_type' => 'regular',
        'day_rate_code' => 'regular',
        'actual_regular' => null,
        'logs' => [
            ['time' => '08:00:00', 'log_type' => 0],
            ['time' => '12:00:00', 'log_type' => 1],
            ['time' => '13:00:00', 'log_type' => 0],
            ['time' => '17:00:00', 'log_type' => 1],
        ],
    ]);

    $detail = runDayTypePayroll(94);

    expect((int) $detail->regular_pay)->toBe(120_000)
        ->and((int) $detail->regular_hours)->toBe(8);
});

it('pays an overnight punch-only timesheet', function () {
    dayTypeEmployee(606, 'E-0606');
    dayTypePayroll(95, '2026-11-01', '2026-11-15', '2026-11-15');

    Timesheet::factory()->create([
        'company_id' => 9,
        'employee_id' => 606,
        'employee_number' => 'E-0606',
        'log_date' => '2026-11-02',
        'day_type' => 'regular',
        'day_rate_code' => 'regular',
        'actual_regular' => null,
        'logs' => [
            ['time' => '22:00:00', 'log_type' => 0],
            ['time' => '06:00:00', 'log_type' => 1],
        ],
    ]);

    $detail = runDayTypePayroll(95);

    expect((int) $detail->regular_pay)->toBe(120_000)
        ->and((int) $detail->regular_hours)->toBe(8);
});

it('does not count approved overtime twice on a punch-only row', function () {
    dayTypeEmployee(607, 'E-0607');
    dayTypePayroll(96, '2026-11-01', '2026-11-15', '2026-11-15');

    $timesheet = Timesheet::factory()->create([
        'company_id' => 9,
        'employee_id' => 607,
        'employee_number' => 'E-0607',
        'log_date' => '2026-11-02',
        'day_type' => 'regular',
        'day_rate_code' => 'regular',
        'actual_regular' => null,
        'logs' => [
            ['time' => '08:00:00', 'log_type' => 0],
            ['time' => '12:00:00', 'log_type' => 1],
            ['time' => '13:00:00', 'log_type' => 0],
            ['time' => '20:00:00', 'log_type' => 1],
        ],
    ]);

    // ApproveOvertimeJob writes the approved hours to the legacy `ot` column.
    $timesheet->ot = 3;
    $timesheet->save();

    $detail = runDayTypePayroll(96);

    expect((int) $detail->regular_pay)->toBe(120_000)
        ->and((int) $detail->regular_hours)->toBe(8)
        ->and((int) $detail->overtime_pay)->toBe(56_250)
        ->and((int) $detail->overtime_hours)->toBe(3);
});

it('pays the premium stored on the canonical day rate code', function () {
    dayTypeEmployee(608, 'E-0608');
    dayTypePayroll(97, '2026-11-01', '2026-11-15', '2026-11-15');

    Timesheet::factory()->create([
        'company_id' => 9,
        'employee_id' => 608,
        'employee_number' => 'E-0608',
        'log_date' => '2026-11-02',
        'day_type' => 'regular',
        'day_rate_code' => 'rh',
        'actual_regular' => 8,
    ]);

    $detail = runDayTypePayroll(97);

    expect((int) $detail->regular_pay)->toBe(240_000)
        ->and((int) $detail->holiday_pay)->toBe(120_000);
});
