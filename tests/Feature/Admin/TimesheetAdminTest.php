<?php

use App\Actions\SetTimesheetAbsent;
use App\Livewire\Admin\Actions\EditViewTimeSheet;
use App\Livewire\Admin\AdminIndex;
use App\Models\Company;
use App\Models\Employee;
use App\Models\Rate;
use App\Models\Schedule;
use App\Models\Timesheet;
use App\Models\User;
use App\Support\Tenancy\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function () {
    Carbon::setTestNow('2026-11-12 09:00:00');

    $this->company = Company::factory()->create(['name' => 'Acme']);

    app(TenantContext::class)->set($this->company->id);

    $this->schedule = Schedule::factory()->create([
        'company_id' => $this->company->id,
        'name' => 'Regular 8-5',
    ]);

    $this->employee = Employee::factory()->create([
        'company_id' => $this->company->id,
        'employee_number' => 'E-0001',
        'first_name' => 'Ana',
        'last_name' => 'Cruz',
        'schedule_id' => $this->schedule->id,
    ]);

    $this->user = User::factory()->create([
        'company_id' => $this->company->id,
        'owner' => true,
        'name' => 'Admin User',
        'email' => 'admin@example.com',
    ]);

    $this->actingAs($this->user);
});

afterEach(function () {
    Carbon::setTestNow();
});

it('refuses to edit a locked timesheet', function () {
    $timesheet = Timesheet::factory()->create([
        'company_id' => $this->company->id,
        'employee_id' => $this->employee->id,
        'employee_number' => $this->employee->employee_number,
        'log_date' => '2026-11-10',
        'is_locked' => true,
    ]);

    $result = (new EditViewTimeSheet($timesheet))->handle(request(), [], collect([$timesheet]));

    expect($result)->toContain('locked');
});

it('marks only scheduled working days without attendance as absent', function () {
    $base = [
        'company_id' => $this->company->id,
        'employee_id' => $this->employee->id,
        'employee_number' => $this->employee->employee_number,
        'log_date' => '2026-11-10',
        'day_type' => Timesheet::REGULAR_DAY,
        'schedule_id' => $this->schedule->id,
        'am_login' => null,
        'am_logout' => null,
        'pm_login' => null,
        'pm_logout' => null,
    ];

    $absent = Timesheet::factory()->create($base);

    $restDay = Timesheet::factory()->create(array_merge($base, [
        'log_date' => '2026-11-11',
        'day_type' => Timesheet::REST_DAY,
    ]));

    $holiday = Timesheet::factory()->create(array_merge($base, [
        'log_date' => '2026-11-12',
        'holiday_id' => 1,
        'holiday_code' => 'rh',
    ]));

    $leave = Timesheet::factory()->create(array_merge($base, [
        'log_date' => '2026-11-13',
        'is_leave' => true,
    ]));

    $locked = Timesheet::factory()->create(array_merge($base, [
        'log_date' => '2026-11-14',
        'is_locked' => true,
    ]));

    $worked = Timesheet::factory()->create(array_merge($base, [
        'log_date' => '2026-11-15',
        'am_login' => '08:00',
    ]));

    $withoutSchedule = Timesheet::factory()->create(array_merge($base, [
        'log_date' => '2026-11-16',
        'schedule_id' => null,
    ]));

    $count = SetTimesheetAbsent::run('2026-11-01', '2026-11-30');

    app(TenantContext::class)->set($this->company->id);

    expect($count)->toBe(1);
    expect((bool) $absent->refresh()->is_absent)->toBeTrue();

    foreach ([$restDay, $holiday, $leave, $locked, $withoutSchedule] as $untouched) {
        expect((bool) $untouched->refresh()->is_absent)->toBeFalse();
    }

    expect((bool) $worked->refresh()->is_absent)->toBeFalse();
});

it('shows worked hours and punch status on the index', function () {
    Timesheet::factory()->create([
        'company_id' => $this->company->id,
        'employee_id' => $this->employee->id,
        'employee_number' => $this->employee->employee_number,
        'log_date' => '2026-11-10',
        'logs_rates' => [
            'regular-regular' => ['hours' => 8, 'actual' => 8, 'rate' => 1],
            'regular-nt' => ['hours' => 0.8, 'actual' => 8, 'rate' => 1.1],
        ],
        'logs' => [
            ['time' => '22:00:00', 'log_type' => 0],
            ['time' => '06:00:00', 'log_type' => 1],
        ],
    ]);

    Timesheet::factory()->create([
        'company_id' => $this->company->id,
        'employee_id' => $this->employee->id,
        'employee_number' => $this->employee->employee_number,
        'log_date' => '2026-11-11',
        'logs' => [['time' => '08:00:00', 'log_type' => 0]],
    ]);

    Livewire::test(AdminIndex::class, ['resource' => 'timesheet'])
        ->assertSee('Worked Hours')
        ->assertSee('8.00')
        ->assertSee('Missing punch');
});

it('records a timesheet audit event when an edit is applied', function () {
    $timesheet = Timesheet::factory()->create([
        'company_id' => $this->company->id,
        'employee_id' => $this->employee->id,
        'employee_number' => $this->employee->employee_number,
        'log_date' => '2026-11-10',
        'day_type' => Timesheet::REGULAR_DAY,
    ]);

    $fields = [
        'day_type' => Timesheet::REST_DAY,
        'rate' => Rate::NO_LOG,
        'hours' => 0,
        'time_in' => '08:00',
        'time_out' => '17:00',
        'time_out_next_day' => false,
    ];

    (new EditViewTimeSheet($timesheet))->handle(request(), $fields, collect([$timesheet]));

    app(TenantContext::class)->set($this->company->id);

    $this->assertDatabaseHas('hr_audit_events', [
        'company_id' => $this->company->id,
        'employee_id' => $this->employee->id,
        'event' => 'timesheet_updated',
    ]);
});

it('runs the absence action from the timesheet toolbar', function () {
    $timesheet = Timesheet::factory()->create([
        'company_id' => $this->company->id,
        'employee_id' => $this->employee->id,
        'employee_number' => $this->employee->employee_number,
        'log_date' => '2026-11-10',
        'day_type' => Timesheet::REGULAR_DAY,
        'schedule_id' => $this->schedule->id,
        'am_login' => null,
        'am_logout' => null,
        'pm_login' => null,
        'pm_logout' => null,
    ]);

    Livewire::test(AdminIndex::class, ['resource' => 'timesheet'])
        ->call('openAction', 'MarkTimesheetAbsent')
        ->set('actionFields.start_date', '2026-11-01')
        ->set('actionFields.end_date', '2026-11-30')
        ->set('actionFields.office_id', '')
        ->call('runAction')
        ->assertHasNoErrors();

    expect((bool) $timesheet->refresh()->is_absent)->toBeTrue();
});

it('includes the selected end date in the date filter', function () {
    Timesheet::factory()->create([
        'company_id' => $this->company->id,
        'employee_id' => $this->employee->id,
        'employee_number' => $this->employee->employee_number,
        'log_date' => '2026-11-10',
        'day_type' => Timesheet::REGULAR_DAY,
    ]);

    Livewire::test(AdminIndex::class, ['resource' => 'timesheet'])
        ->set('filterValues.date-range-to', '2026-11-10')
        ->assertSee('E-0001');
});
