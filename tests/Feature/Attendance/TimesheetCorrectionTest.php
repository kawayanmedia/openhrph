<?php

use App\Models\Company;
use App\Models\Employee;
use App\Models\Timesheet;
use App\Models\TimesheetRequest;
use App\Models\User;
use App\Services\Attendance\AttendanceCorrection;
use App\Support\Tenancy\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;

uses(RefreshDatabase::class);

beforeEach(function () {
    Carbon::setTestNow('2026-11-12 09:00:00');

    $this->company = Company::factory()->create(['name' => 'Acme']);

    app(TenantContext::class)->set($this->company->id);

    $this->employee = Employee::factory()->create([
        'company_id' => $this->company->id,
        'employee_number' => 'E-0001',
        'biometric_id' => 101,
        'first_name' => 'Ana',
        'last_name' => 'Cruz',
    ]);

    $this->approver = Employee::factory()->create([
        'company_id' => $this->company->id,
        'employee_number' => 'E-9000',
        'first_name' => 'Rita',
        'last_name' => 'Reyes',
    ]);
});

afterEach(function () {
    Carbon::setTestNow();
});

it('applies an approved attendance request to the timesheet logs', function () {
    $request = TimesheetRequest::factory()->create([
        'company_id' => $this->company->id,
        'employee_id' => $this->employee->id,
        'log_date' => '2026-11-10',
        'time' => '08:05:00',
        'log_type' => 0,
        'status' => 'Pending',
    ]);

    $result = app(AttendanceCorrection::class)->apply($request, $this->approver, 'Forgot to punch');

    expect($result['status'])->toBe(AttendanceCorrection::STATUS_APPLIED);

    $timesheet = Timesheet::query()->where('employee_id', $this->employee->id)->firstOrFail();

    expect($timesheet->employee_number)->toBe('E-0001');
    expect($timesheet->log_date->toDateString())->toBe('2026-11-10');
    expect($timesheet->logs)->toHaveCount(1);
    expect($timesheet->logs[0]['log_type'])->toBe(0);
    expect($timesheet->logs[0]['time'])->toBe('08:05:00');
    expect($timesheet->logs[0]['source'])->toBe('attendance_request');
    expect($timesheet->logs[0]['request_id'])->toBe($request->getKey());
    expect($timesheet->am_login)->toBe('08:05');

    $request->refresh();

    expect($request->status)->toBe(STATUS_APPROVED);
    expect($request->approved_user_id)->toBe($this->approver->id);
    expect($request->remarks)->toBe('Forgot to punch');
});

it('refuses a correction when the timesheet is locked', function () {
    Timesheet::factory()->create([
        'company_id' => $this->company->id,
        'employee_id' => $this->employee->id,
        'employee_number' => $this->employee->employee_number,
        'log_date' => '2026-11-10',
        'is_locked' => true,
    ]);

    $request = TimesheetRequest::factory()->create([
        'company_id' => $this->company->id,
        'employee_id' => $this->employee->id,
        'log_date' => '2026-11-10',
        'time' => '08:05:00',
        'log_type' => 0,
        'status' => 'Pending',
    ]);

    $result = app(AttendanceCorrection::class)->apply($request, $this->approver, 'Late punch');

    expect($result['status'])->toBe(AttendanceCorrection::STATUS_LOCKED);
    expect($request->refresh()->status)->toBe('Pending');
});

it('does not fail when the immediate head cannot be notified', function () {
    $head = Employee::factory()->create([
        'company_id' => $this->company->id,
        'employee_number' => 'E-8000',
        'first_name' => 'Head',
        'last_name' => 'Office',
    ]);

    User::factory()->create([
        'company_id' => $this->company->id,
        'employee_id' => $head->id,
        'name' => 'Head Office',
        'email' => 'head@example.com',
    ]);

    $this->employee->immediate_head_employee_id = $head->id;
    $this->employee->save();

    $request = TimesheetRequest::factory()->create([
        'company_id' => $this->company->id,
        'employee_id' => $this->employee->id,
        'log_date' => '2026-11-10',
        'status' => 'Pending',
    ]);

    expect($request->exists)->toBeTrue();
});
