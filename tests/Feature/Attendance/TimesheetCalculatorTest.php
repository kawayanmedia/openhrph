<?php

use App\Models\AttendanceScheduleAssignment;
use App\Models\AttendanceShift;
use App\Models\Employee;
use App\Models\Schedule;
use App\Models\Timesheet;
use App\Models\User;
use App\Services\Attendance\TimesheetCalculator;
use App\Services\Attendance\WorkScheduleResolver;
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

it('zeroes the buckets when the punch is incomplete', function () {
    $timesheet = Timesheet::factory()->create([
        'company_id' => 1,
        'employee_id' => $this->employee->id,
        'employee_number' => $this->employee->employee_number,
        'schedule_id' => $this->schedule->id,
        'log_date' => '2026-11-09',
        'logs' => [['time' => '08:00:00', 'log_type' => 0]],
    ]);
    $timesheet->forceFill(['actual_regular' => 8])->save();

    $computed = app(TimesheetCalculator::class)->compute($timesheet);

    expect((float) $computed->refresh()->actual_regular)->toBe(0.0)
        ->and($computed->is_paired)->toBeFalse();
});

it('deducts a configured meal break exactly once on a two-punch day', function () {
    $schedule = Schedule::factory()->create([
        'company_id' => 1,
        'am_in' => '08:00',
        'am_out' => '12:00',
        'pm_in' => '13:00',
        'pm_out' => '17:00',
        'break_time_start' => '12:00',
        'break_time_end' => '13:00',
    ]);

    $employee = Employee::factory()->create([
        'company_id' => 1,
        'employee_number' => 'E-701',
        'schedule_id' => $schedule->id,
    ]);

    $timesheet = Timesheet::factory()->create([
        'company_id' => 1,
        'employee_id' => $employee->id,
        'employee_number' => $employee->employee_number,
        'schedule_id' => $schedule->id,
        'log_date' => '2026-11-09',
        'logs' => [
            ['time' => '08:00:00', 'log_type' => 0],
            ['time' => '17:00:00', 'log_type' => 1],
        ],
    ]);

    $computed = app(TimesheetCalculator::class)->compute($timesheet);

    expect((float) $computed->actual_regular)->toBe(8.0)
        ->and((float) $computed->actual_ot)->toBe(0.0)
        ->and((int) $computed->paid_minutes)->toBe(480)
        ->and((int) $computed->scheduled_minutes)->toBe(480);
});

it('deducts the meal break once for a four-punch day', function () {
    $schedule = Schedule::factory()->create([
        'company_id' => 1,
        'am_in' => '08:00',
        'am_out' => '12:00',
        'pm_in' => '13:00',
        'pm_out' => '17:00',
        'break_time_start' => '12:00',
        'break_time_end' => '13:00',
    ]);

    $employee = Employee::factory()->create([
        'company_id' => 1,
        'employee_number' => 'E-702',
        'schedule_id' => $schedule->id,
    ]);

    $timesheet = Timesheet::factory()->create([
        'company_id' => 1,
        'employee_id' => $employee->id,
        'employee_number' => $employee->employee_number,
        'schedule_id' => $schedule->id,
        'log_date' => '2026-11-09',
        'logs' => [
            ['time' => '08:00:00', 'log_type' => 0],
            ['time' => '12:00:00', 'log_type' => 1],
            ['time' => '13:00:00', 'log_type' => 0],
            ['time' => '17:00:00', 'log_type' => 1],
        ],
    ]);

    $computed = app(TimesheetCalculator::class)->compute($timesheet);

    expect((float) $computed->actual_regular)->toBe(8.0)
        ->and((float) $computed->actual_ot)->toBe(0.0)
        ->and((int) $computed->paid_minutes)->toBe(480);
});

it('uses shift segments for a split shift', function () {
    $shift = AttendanceShift::create([
        'company_id' => 1,
        'name' => 'Split 6-10 / 4-8',
        'type' => AttendanceShift::TYPE_SPLIT,
        'segments' => [['06:00', '10:00'], ['16:00', '20:00']],
        'breaks' => [],
        'is_active' => true,
    ]);

    $timesheet = Timesheet::factory()->create([
        'company_id' => 1,
        'employee_id' => $this->employee->id,
        'employee_number' => $this->employee->employee_number,
        'schedule_id' => null,
        'shift_id' => $shift->id,
        'log_date' => '2026-11-09',
        'logs' => [
            ['time' => '06:00:00', 'log_type' => 0],
            ['time' => '10:00:00', 'log_type' => 1],
            ['time' => '16:00:00', 'log_type' => 0],
            ['time' => '20:00:00', 'log_type' => 1],
        ],
    ]);

    $computed = app(TimesheetCalculator::class)->compute($timesheet);

    expect((float) $computed->actual_regular)->toBe(8.0)
        ->and((float) $computed->actual_ot)->toBe(0.0)
        ->and((int) $computed->paid_minutes)->toBe(480);
});

it('uses the shift night window override', function () {
    $shift = AttendanceShift::create([
        'company_id' => 1,
        'name' => 'Government night',
        'type' => AttendanceShift::TYPE_OVERNIGHT,
        'segments' => [['18:00', '02:00']],
        'breaks' => [],
        'night_window_start' => '18:00',
        'night_window_end' => '06:00',
        'is_active' => true,
    ]);

    $timesheet = Timesheet::factory()->create([
        'company_id' => 1,
        'employee_id' => $this->employee->id,
        'employee_number' => $this->employee->employee_number,
        'schedule_id' => null,
        'shift_id' => $shift->id,
        'log_date' => '2026-11-09',
        'logs' => [
            ['time' => '18:00:00', 'log_type' => 0],
            ['time' => '02:00:00', 'log_type' => 1],
        ],
    ]);

    $computed = app(TimesheetCalculator::class)->compute($timesheet);

    expect((float) $computed->actual_regular)->toBe(8.0)
        ->and((float) $computed->actual_nt)->toBe(8.0)
        ->and((float) $computed->actual_ot)->toBe(0.0);
});

it('deducts the break on an overnight shift', function () {
    $shift = AttendanceShift::create([
        'company_id' => 1,
        'name' => 'Night 22-06',
        'type' => AttendanceShift::TYPE_OVERNIGHT,
        'segments' => [['22:00', '06:00']],
        'breaks' => [['02:00', '03:00']],
        'is_active' => true,
    ]);

    $timesheet = Timesheet::factory()->create([
        'company_id' => 1,
        'employee_id' => $this->employee->id,
        'employee_number' => $this->employee->employee_number,
        'schedule_id' => null,
        'shift_id' => $shift->id,
        'log_date' => '2026-11-09',
        'logs' => [
            ['time' => '22:00:00', 'log_type' => 0],
            ['time' => '06:00:00', 'log_type' => 1],
        ],
    ]);

    $computed = app(TimesheetCalculator::class)->compute($timesheet);

    expect((float) $computed->actual_regular)->toBe(7.0)
        ->and((float) $computed->actual_nt)->toBe(7.0)
        ->and((float) $computed->actual_ot)->toBe(0.0)
        ->and((int) $computed->paid_minutes)->toBe(420);
});

it('resolves a rotation cycle by effective date', function () {
    $day = AttendanceShift::create([
        'company_id' => 1,
        'name' => 'Day',
        'segments' => [['08:00', '12:00'], ['13:00', '17:00']],
        'breaks' => [],
        'is_active' => true,
    ]);

    $night = AttendanceShift::create([
        'company_id' => 1,
        'name' => 'Night',
        'segments' => [['22:00', '06:00']],
        'breaks' => [],
        'is_active' => true,
    ]);

    AttendanceScheduleAssignment::create([
        'company_id' => 1,
        'employee_id' => $this->employee->id,
        'rotation' => [$day->id, $night->id, null],
        'cycle_anchor' => '2026-11-09',
        'effective_from' => '2026-11-01',
    ]);

    $resolver = app(WorkScheduleResolver::class);

    $monday = $resolver->resolve($this->employee, '2026-11-09');
    $tuesday = $resolver->resolve($this->employee, '2026-11-10');
    $wednesday = $resolver->resolve($this->employee, '2026-11-11');

    expect($monday->name)->toBe('Day')
        ->and($monday->scheduledMinutes())->toBe(480)
        ->and($tuesday->name)->toBe('Night')
        ->and($tuesday->crossesMidnight())->toBeTrue()
        ->and($wednesday->isRestDay)->toBeTrue()
        ->and($wednesday->scheduledMinutes())->toBe(480);
});

it('records late minutes against the shift start', function () {
    $timesheet = calculatedTimesheet([
        ['time' => '08:17:00', 'log_type' => 0],
        ['time' => '12:00:00', 'log_type' => 1],
        ['time' => '13:00:00', 'log_type' => 0],
        ['time' => '17:00:00', 'log_type' => 1],
    ], $this->schedule, $this->employee);

    expect((int) $timesheet->late_minutes)->toBe(17)
        ->and((float) $timesheet->actual_regular)->toBe(7.72);
});

it('records undertime once the shift has ended', function () {
    $timesheet = calculatedTimesheet([
        ['time' => '08:00:00', 'log_type' => 0],
        ['time' => '12:00:00', 'log_type' => 1],
        ['time' => '13:00:00', 'log_type' => 0],
        ['time' => '16:00:00', 'log_type' => 1],
    ], $this->schedule, $this->employee);

    expect((int) $timesheet->undertime_minutes)->toBe(60)
        ->and((float) $timesheet->actual_regular)->toBe(7.0);
});

it('pays approved overtime without double counting punched overtime', function () {
    $timesheet = Timesheet::factory()->create([
        'company_id' => 1,
        'employee_id' => $this->employee->id,
        'employee_number' => $this->employee->employee_number,
        'schedule_id' => $this->schedule->id,
        'log_date' => '2026-11-09',
        'approved_ot_minutes' => 120,
        'logs' => [
            ['time' => '08:00:00', 'log_type' => 0],
            ['time' => '17:00:00', 'log_type' => 1],
        ],
    ]);

    $computed = app(TimesheetCalculator::class)->compute($timesheet);

    expect((float) $computed->actual_regular)->toBe(8.0)
        ->and((float) $computed->actual_ot)->toBe(2.0);
});

it('counts night hours on a shift that starts at midnight', function () {
    $shift = AttendanceShift::create([
        'company_id' => 1,
        'name' => 'Midnight to 8',
        'type' => AttendanceShift::TYPE_OVERNIGHT,
        'segments' => [['00:00', '08:00']],
        'breaks' => [],
        'is_active' => true,
    ]);

    $timesheet = Timesheet::factory()->create([
        'company_id' => 1,
        'employee_id' => $this->employee->id,
        'employee_number' => $this->employee->employee_number,
        'schedule_id' => null,
        'shift_id' => $shift->id,
        'log_date' => '2026-11-09',
        'logs' => [
            ['time' => '00:00:00', 'log_type' => 0],
            ['time' => '08:00:00', 'log_type' => 1],
        ],
    ]);

    $computed = app(TimesheetCalculator::class)->compute($timesheet);

    expect((float) $computed->actual_regular)->toBe(8.0)
        ->and((float) $computed->actual_nt)->toBe(6.0);
});

it('deducts a break from the second half of an overnight split', function () {
    $shift = AttendanceShift::create([
        'company_id' => 1,
        'name' => 'Overnight split',
        'type' => AttendanceShift::TYPE_OVERNIGHT,
        'segments' => [['22:00', '02:00'], ['03:00', '06:00']],
        'breaks' => [['03:30', '04:00']],
        'is_active' => true,
    ]);

    $timesheet = Timesheet::factory()->create([
        'company_id' => 1,
        'employee_id' => $this->employee->id,
        'employee_number' => $this->employee->employee_number,
        'schedule_id' => null,
        'shift_id' => $shift->id,
        'log_date' => '2026-11-09',
        'logs' => [
            ['time' => '22:00:00', 'log_type' => 0],
            ['time' => '02:00:00', 'log_type' => 1],
            ['time' => '03:00:00', 'log_type' => 0],
            ['time' => '06:00:00', 'log_type' => 1],
        ],
    ]);

    $computed = app(TimesheetCalculator::class)->compute($timesheet);

    expect((int) $computed->paid_minutes)->toBe(390)
        ->and((int) $computed->scheduled_minutes)->toBe(390)
        ->and((float) $computed->actual_ot)->toBe(0.0)
        ->and((float) $computed->actual_nt)->toBe(6.5);
});
