<?php

use App\Livewire\Admin\Actions\EditViewTimeSheet;
use App\Models\AttendancePunch;
use App\Models\Company;
use App\Models\Employee;
use App\Models\Schedule;
use App\Models\Timesheet;
use App\Models\User;
use App\Services\Attendance\PunchLedger;
use App\Services\Attendance\TimesheetLogRecorder;
use App\Support\Tenancy\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;

uses(RefreshDatabase::class);

beforeEach(function () {
    Carbon::setTestNow('2026-11-09 08:00:00');

    $this->company = Company::factory()->create(['name' => 'Acme']);

    app(TenantContext::class)->set($this->company->id);

    $this->schedule = Schedule::factory()->create([
        'company_id' => $this->company->id,
        'am_in' => '08:00',
        'am_out' => '12:00',
        'pm_in' => '13:00',
        'pm_out' => '17:00',
        'break_time_start' => '12:00',
        'break_time_end' => '13:00',
    ]);

    $this->employee = Employee::factory()->create([
        'company_id' => $this->company->id,
        'employee_number' => 'E-500',
        'biometric_id' => 500,
        'schedule_id' => $this->schedule->id,
    ]);

    $this->user = User::factory()->create(['company_id' => $this->company->id, 'owner' => true]);
    $this->actingAs($this->user);
});

afterEach(function () {
    Carbon::setTestNow();
});

it('records punches in the append-only ledger and rebuilds the day when one is voided', function () {
    $recorder = app(TimesheetLogRecorder::class);

    $recorder->record($this->employee, Carbon::parse('2026-11-09 08:00:00'), 'portal', null, 0);
    $recorder->record($this->employee, Carbon::parse('2026-11-09 17:00:00'), 'portal', null, 1);

    $timesheet = Timesheet::query()->firstOrFail();

    expect(AttendancePunch::query()->count())->toBe(2)
        ->and((int) $timesheet->paid_minutes)->toBe(480);

    $out = AttendancePunch::query()->where('direction', 1)->firstOrFail();

    expect(app(PunchLedger::class)->void($out, $this->user->id, 'Accidental punch'))->toBeTrue();

    $fresh = $timesheet->fresh();
    $out->refresh();

    expect($out->isVoided())->toBeTrue()
        ->and($fresh->logs)->toHaveCount(1)
        ->and((int) $fresh->paid_minutes)->toBe(0)
        ->and((float) $fresh->actual_regular)->toBe(0.0);
});

it('keeps old punches when a manual edit replaces the day', function () {
    $recorder = app(TimesheetLogRecorder::class);

    $recorder->record($this->employee, Carbon::parse('2026-11-09 08:00:00'), 'portal', null, 0);
    $recorder->record($this->employee, Carbon::parse('2026-11-09 12:00:00'), 'portal', null, 1);

    $timesheet = Timesheet::query()->firstOrFail();

    $fields = [
        'schedule_id' => $this->schedule->id,
        'day_type' => Timesheet::REGULAR_DAY,
        'time_in' => '08:00',
        'time_out' => '17:00',
        'time_out_next_day' => false,
        'replace_punches' => true,
    ];

    (new EditViewTimeSheet($timesheet))->handle(request(), $fields, collect([$timesheet]));

    $fresh = $timesheet->fresh();

    // The 08:00 time-in is the same punch event, so it is restored rather
    // than duplicated; the old 12:00 time-out stays voided for audit.
    expect(AttendancePunch::query()->count())->toBe(3)
        ->and(AttendancePunch::query()->whereNotNull('voided_at')->count())->toBe(1)
        ->and(AttendancePunch::query()->whereNull('voided_at')->count())->toBe(2)
        ->and($fresh->logs)->toHaveCount(2)
        ->and($fresh->logs[0]['source'])->toBe('portal')
        ->and($fresh->logs[1]['source'])->toBe('manual')
        ->and((int) $fresh->paid_minutes)->toBe(480);
});

it('backfills ledger rows from legacy logs', function () {
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

    $this->artisan('attendance:backfill-punches', [
        '--company' => $this->company->id,
    ])->assertSuccessful();

    expect(AttendancePunch::query()->where('timesheet_id', $timesheet->id)->count())->toBe(2);
});
