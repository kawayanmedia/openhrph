<?php

use App\Models\Employee;
use App\Models\Schedule;
use App\Models\Timesheet;
use App\Models\User;
use App\Services\Attendance\TimesheetCalculator;
use App\Support\Tenancy\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->user = User::factory()->create(['company_id' => 1, 'owner' => true]);
    $this->actingAs($this->user);
    app(TenantContext::class)->set(1);

    $this->schedule = Schedule::factory()->create([
        'company_id' => 1,
        'am_in' => '08:00',
        'am_out' => '12:00',
        'pm_in' => '13:00',
        'pm_out' => '17:00',
        'break_time_start' => null,
        'break_time_end' => null,
    ]);

    $this->employee = Employee::factory()->create([
        'company_id' => 1,
        'employee_number' => 'E-700',
        'schedule_id' => $this->schedule->id,
        'office_id' => null,
    ]);
});

function calculatedTimesheet(array $logs, Schedule $schedule, Employee $employee): Timesheet
{
    $timesheet = Timesheet::factory()->create([
        'company_id' => 1,
        'employee_id' => $employee->id,
        'employee_number' => $employee->employee_number,
        'schedule_id' => $schedule->id,
        'log_date' => '2026-11-09',
        'logs' => $logs,
    ]);

    return app(TimesheetCalculator::class)->compute($timesheet);
}

it('derives regular, overtime and night hours from punches', function () {
    $timesheet = calculatedTimesheet([
        ['time' => '08:00:00', 'log_type' => 0],
        ['time' => '19:00:00', 'log_type' => 1],
    ], $this->schedule, $this->employee);

    expect((float) $timesheet->actual_regular)->toBe(8.0)
        ->and((float) $timesheet->actual_ot)->toBe(3.0)
        ->and((float) $timesheet->actual_nt)->toBe(0.0);
});

it('counts night differential and night overtime', function () {
    $timesheet = calculatedTimesheet([
        ['time' => '14:00:00', 'log_type' => 0],
        ['time' => '23:00:00', 'log_type' => 1],
    ], $this->schedule, $this->employee);

    // 8 scheduled hours (14:00-22:00) + 1 overtime hour (22:00-23:00).
    expect((float) $timesheet->actual_regular)->toBe(8.0)
        ->and((float) $timesheet->actual_ot)->toBe(1.0)
        ->and((float) $timesheet->actual_nt)->toBe(1.0)
        ->and((float) $timesheet->actual_nt_ot)->toBe(1.0);
});

it('handles an overnight shift', function () {
    $timesheet = calculatedTimesheet([
        ['time' => '22:00:00', 'log_type' => 0],
        ['time' => '06:00:00', 'log_type' => 1],
    ], $this->schedule, $this->employee);

    expect((float) $timesheet->actual_regular)->toBe(8.0)
        ->and((float) $timesheet->actual_ot)->toBe(0.0)
        ->and((float) $timesheet->actual_nt)->toBe(8.0);
});

it('leaves an incomplete punch alone', function () {
    $timesheet = Timesheet::factory()->create([
        'company_id' => 1,
        'employee_id' => $this->employee->id,
        'employee_number' => $this->employee->employee_number,
        'schedule_id' => $this->schedule->id,
        'log_date' => '2026-11-09',
        'logs' => [['time' => '08:00:00', 'log_type' => 0]],
    ]);
    $timesheet->forceFill(['actual_regular' => 8])->save();

    app(TimesheetCalculator::class)->compute($timesheet);

    expect((float) $timesheet->refresh()->actual_regular)->toBe(8.0);
});
