<?php

use App\Jobs\ApproveOvertimeJob;
use App\Models\Company;
use App\Models\Employee;
use App\Models\OvertimeRequest;
use App\Models\Schedule;
use App\Models\Timesheet;
use App\Models\User;
use App\Services\Attendance\TimesheetCalculator;
use App\Support\Tenancy\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->company = Company::factory()->create(['name' => 'Acme']);

    app(TenantContext::class)->set($this->company->id);

    $this->schedule = Schedule::factory()->create([
        'company_id' => $this->company->id,
        'am_in' => '08:00',
        'am_out' => '12:00',
        'pm_in' => '13:00',
        'pm_out' => '17:00',
        'break_time_start' => null,
        'break_time_end' => null,
    ]);

    $this->employee = Employee::factory()->create([
        'company_id' => $this->company->id,
        'employee_number' => 'E-300',
        'schedule_id' => $this->schedule->id,
    ]);

    $this->user = User::factory()->create(['company_id' => $this->company->id, 'owner' => true]);
    $this->actingAs($this->user);
});

it('records approval without double counting punched overtime', function () {
    $timesheet = Timesheet::factory()->create([
        'company_id' => $this->company->id,
        'employee_id' => $this->employee->id,
        'employee_number' => $this->employee->employee_number,
        'schedule_id' => $this->schedule->id,
        'log_date' => '2026-11-09',
        'logs' => [
            ['time' => '08:00:00', 'log_type' => 0],
            ['time' => '19:00:00', 'log_type' => 1],
        ],
    ]);

    app(TimesheetCalculator::class)->compute($timesheet);

    $request = OvertimeRequest::factory()->create([
        'company_id' => $this->company->id,
        'employee_id' => $this->employee->id,
        'date' => '2026-11-09',
        'hours' => 3,
        'status' => 'Pending',
    ]);

    (new ApproveOvertimeJob($request, $this->employee, (object) ['remarks' => 'Approved'], $this->user->id))->handle();

    $fresh = $timesheet->fresh();

    expect((int) $fresh->approved_ot_minutes)->toBe(180)
        ->and((float) $fresh->actual_ot)->toBe(3.0);
});

it('keeps approved overtime when the day is recomputed', function () {
    $timesheet = Timesheet::factory()->create([
        'company_id' => $this->company->id,
        'employee_id' => $this->employee->id,
        'employee_number' => $this->employee->employee_number,
        'schedule_id' => $this->schedule->id,
        'log_date' => '2026-11-09',
        'logs' => [
            ['time' => '08:00:00', 'log_type' => 0],
            ['time' => '17:00:00', 'log_type' => 1],
        ],
    ]);

    $request = OvertimeRequest::factory()->create([
        'company_id' => $this->company->id,
        'employee_id' => $this->employee->id,
        'date' => '2026-11-09',
        'hours' => 2,
        'status' => 'Pending',
    ]);

    (new ApproveOvertimeJob($request, $this->employee, (object) ['remarks' => 'Approved'], $this->user->id))->handle();

    $computed = app(TimesheetCalculator::class)->compute($timesheet->fresh());

    expect((float) $computed->actual_ot)->toBe(2.0)
        ->and((int) $computed->approved_ot_minutes)->toBe(120);
});
