<?php

use App\Livewire\Portal\Timesheets\TimesheetPunch;
use App\Models\Company;
use App\Models\Employee;
use App\Models\Payroll;
use App\Models\PayrollDate;
use App\Models\PayrollGroup;
use App\Models\Schedule;
use App\Models\Timesheet;
use App\Models\User;
use App\Support\Tenancy\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function () {
    Carbon::setTestNow('2026-11-12 08:15:00');

    $this->company = Company::factory()->create(['name' => 'Acme']);

    app(TenantContext::class)->set($this->company->id);

    $this->employee = Employee::factory()->create([
        'company_id' => $this->company->id,
        'employee_number' => 'E-0001',
        'biometric_id' => 101,
        'first_name' => 'Ana',
        'last_name' => 'Cruz',
    ]);

    $this->user = User::factory()->forCompany($this->company)->create([
        'employee_id' => $this->employee->id,
        'name' => 'Ana Cruz',
        'email' => 'ana@example.com',
    ]);
});

afterEach(function () {
    Carbon::setTestNow();
});

function todayTimesheet(Employee $employee, array $attributes = []): Timesheet
{
    return Timesheet::factory()->create(array_merge([
        'company_id' => $employee->company_id,
        'employee_id' => $employee->id,
        'employee_number' => $employee->employee_number,
        'log_date' => '2026-11-12',
        'day_type' => Timesheet::REGULAR_DAY,
        'rate_code' => Timesheet::REGULAR_DAY,
    ], $attributes));
}

it('shows the punch page to portal employees', function () {
    $this->actingAs($this->user, 'portal')
        ->get('/portal/timesheet')
        ->assertOk()
        ->assertSee('Time In')
        ->assertSee('Time Out');
});

it('records a time in punch', function () {
    Livewire::actingAs($this->user)
        ->test(TimesheetPunch::class)
        ->assertSee('Time In')
        ->call('timeIn')
        ->assertHasNoErrors()
        ->assertSee('Time in recorded at 08:15 AM.');

    app(TenantContext::class)->set($this->company->id);

    $timesheet = Timesheet::query()->firstOrFail();

    expect($timesheet->log_date->toDateString())->toBe('2026-11-12');
    expect($timesheet->logs)->toHaveCount(1);
    expect($timesheet->logs[0]['log_type'])->toBe(0);
    expect($timesheet->logs[0]['time'])->toBe('08:15:00');
    expect($timesheet->logs[0]['source'])->toBe('portal');
    expect($timesheet->am_login)->toBe('08:15');
});

it('records a time out punch after a time in', function () {
    Livewire::actingAs($this->user)
        ->test(TimesheetPunch::class)
        ->call('timeIn')
        ->assertHasNoErrors();

    Carbon::setTestNow('2026-11-12 17:05:00');

    Livewire::actingAs($this->user)
        ->test(TimesheetPunch::class)
        ->call('timeOut')
        ->assertHasNoErrors()
        ->assertSee('Time out recorded at 05:05 PM.');

    app(TenantContext::class)->set($this->company->id);

    $timesheet = Timesheet::query()->firstOrFail();

    expect($timesheet->logs)->toHaveCount(2);
    expect($timesheet->logs[1]['log_type'])->toBe(1);
    expect($timesheet->pm_logout)->toBe('17:05');
});

it('requires a time in before timing out', function () {
    Livewire::actingAs($this->user)
        ->test(TimesheetPunch::class)
        ->call('timeOut')
        ->assertHasErrors('punch');

    app(TenantContext::class)->set($this->company->id);

    expect(Timesheet::query()->count())->toBe(0);
});

it('blocks punches on a locked timesheet', function () {
    todayTimesheet($this->employee, ['is_locked' => true]);

    Livewire::actingAs($this->user)
        ->test(TimesheetPunch::class)
        ->call('timeIn')
        ->assertHasErrors('punch');

    app(TenantContext::class)->set($this->company->id);

    expect(Timesheet::query()->firstOrFail()->logs)->toBeNull();
});

it('blocks punches when the payroll period is closed', function () {
    $group = PayrollGroup::factory()->create([
        'company_id' => $this->company->id,
        'name' => 'Monthly',
        'frequency' => PayrollGroup::FREQUENCY_MONTHLY,
    ]);

    $this->employee->payroll_group_id = $group->id;
    $this->employee->save();

    PayrollDate::factory()->create([
        'company_id' => $this->company->id,
        'payroll_group_id' => $group->id,
        'payroll_date' => '2026-11-15',
        'attendance_date_start' => '2026-11-01',
        'attendance_date_end' => '2026-11-15',
        'status' => PayrollDate::STATUS_CLOSED,
    ]);

    Livewire::actingAs($this->user)
        ->test(TimesheetPunch::class)
        ->call('timeIn')
        ->assertHasErrors('punch')
        ->assertSee('Attendance for this payroll period is closed.');
});

it('blocks punches when a payroll run already covers the date', function () {
    $group = PayrollGroup::factory()->create([
        'company_id' => $this->company->id,
        'name' => 'Monthly',
        'frequency' => PayrollGroup::FREQUENCY_MONTHLY,
    ]);

    $this->employee->payroll_group_id = $group->id;
    $this->employee->save();

    Payroll::factory()->create([
        'company_id' => $this->company->id,
        'payroll_group_id' => $group->id,
        'status' => Payroll::STATUS_PROCESSED,
        'date_from' => '2026-11-01',
        'date_to' => '2026-11-15',
    ]);

    Livewire::actingAs($this->user)
        ->test(TimesheetPunch::class)
        ->call('timeIn')
        ->assertHasErrors('punch');
});

it('shows the assigned schedule before the first punch', function () {
    $schedule = Schedule::factory()->create([
        'company_id' => $this->company->id,
        'name' => 'Day Shift',
        'am_in' => '08:00',
        'pm_out' => '17:00',
    ]);

    $this->employee->schedule_id = $schedule->id;
    $this->employee->save();

    Livewire::actingAs($this->user)
        ->test(TimesheetPunch::class)
        ->assertSee('Day Shift')
        ->assertDontSee('Not assigned');
});

it('shows the current payroll period label', function () {
    $group = PayrollGroup::factory()->create([
        'company_id' => $this->company->id,
        'name' => 'Monthly',
        'frequency' => PayrollGroup::FREQUENCY_MONTHLY,
    ]);

    $this->employee->payroll_group_id = $group->id;
    $this->employee->save();

    PayrollDate::factory()->create([
        'company_id' => $this->company->id,
        'payroll_group_id' => $group->id,
        'payroll_date' => '2026-11-30',
        'attendance_date_start' => '2026-11-11',
        'attendance_date_end' => '2026-11-25',
        'status' => PayrollDate::STATUS_OPEN,
    ]);

    Livewire::actingAs($this->user)
        ->test(TimesheetPunch::class)
        ->assertSee('Current payroll period (Nov 11, 2026 - Nov 25, 2026)');
});

it('rejects users without a linked employee record', function () {
    $orphan = User::factory()->create([
        'company_id' => $this->company->id,
        'employee_id' => null,
        'name' => 'No Employee',
        'email' => 'orphan@example.com',
    ]);

    Livewire::actingAs($orphan)
        ->test(TimesheetPunch::class)
        ->call('timeIn')
        ->assertHasErrors('punch')
        ->assertSee('not linked to an employee record');
});
