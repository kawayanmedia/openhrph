<?php

use App\Models\Company;
use App\Models\Employee;
use App\Models\Payroll;
use App\Models\PayrollGroup;
use App\Models\Timesheet;
use App\Services\Attendance\TimesheetPeriodLock;
use App\Support\Tenancy\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;

uses(RefreshDatabase::class);

beforeEach(function () {
    Carbon::setTestNow('2026-11-12 09:00:00');

    $this->company = Company::factory()->create(['name' => 'Acme']);

    app(TenantContext::class)->set($this->company->id);
});

afterEach(function () {
    Carbon::setTestNow();
});

it('locks only the timesheets of the payroll group', function () {
    $groupA = PayrollGroup::factory()->create([
        'company_id' => $this->company->id,
        'name' => 'Rank and file',
    ]);

    $groupB = PayrollGroup::factory()->create([
        'company_id' => $this->company->id,
        'name' => 'Executives',
    ]);

    $employeeA = Employee::factory()->create([
        'company_id' => $this->company->id,
        'employee_number' => 'E-1000',
        'payroll_group_id' => $groupA->id,
    ]);

    $employeeB = Employee::factory()->create([
        'company_id' => $this->company->id,
        'employee_number' => 'E-2000',
        'payroll_group_id' => $groupB->id,
    ]);

    $timesheetA = Timesheet::factory()->create([
        'company_id' => $this->company->id,
        'employee_id' => $employeeA->id,
        'employee_number' => $employeeA->employee_number,
        'log_date' => '2026-11-10',
    ]);

    $timesheetEdge = Timesheet::factory()->create([
        'company_id' => $this->company->id,
        'employee_id' => $employeeA->id,
        'employee_number' => $employeeA->employee_number,
        'log_date' => '2026-11-15',
    ]);

    $timesheetB = Timesheet::factory()->create([
        'company_id' => $this->company->id,
        'employee_id' => $employeeB->id,
        'employee_number' => $employeeB->employee_number,
        'log_date' => '2026-11-10',
    ]);

    $payroll = Payroll::factory()->create([
        'company_id' => $this->company->id,
        'payroll_group_id' => $groupA->id,
        'date_from' => '2026-11-01',
        'date_to' => '2026-11-15',
    ]);

    $lock = app(TimesheetPeriodLock::class);

    expect($lock->lock($payroll))->toBe(2);

    app(TenantContext::class)->set($this->company->id);

    expect((bool) $timesheetA->refresh()->is_locked)->toBeTrue();
    expect((bool) $timesheetEdge->refresh()->is_locked)->toBeTrue();
    expect((bool) $timesheetB->refresh()->is_locked)->toBeFalse();

    expect($lock->unlock($payroll))->toBe(2);

    expect((bool) $timesheetA->refresh()->is_locked)->toBeFalse();
});
