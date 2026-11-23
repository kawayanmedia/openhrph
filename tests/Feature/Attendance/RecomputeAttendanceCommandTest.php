<?php

use App\Models\Company;
use App\Models\Employee;
use App\Models\Schedule;
use App\Models\Timesheet;
use App\Models\User;
use App\Support\Tenancy\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

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
        'break_time_start' => '12:00',
        'break_time_end' => '13:00',
    ]);

    $this->employee = Employee::factory()->create([
        'company_id' => $this->company->id,
        'employee_number' => 'E-100',
        'schedule_id' => $this->schedule->id,
    ]);

    $this->user = User::factory()->create(['company_id' => $this->company->id, 'owner' => true]);
    $this->actingAs($this->user);
});

function staleTimesheet(Company $company, Employee $employee, Schedule $schedule): Timesheet
{
    $timesheet = Timesheet::factory()->create([
        'company_id' => $company->id,
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

    DB::table('timesheets')->where('id', $timesheet->id)->update([
        'actual_regular' => 700,
        'actual_ot' => 100,
        'paid_minutes' => 0,
    ]);

    return $timesheet;
}

it('recomputes stored hours from raw punches', function () {
    $timesheet = staleTimesheet($this->company, $this->employee, $this->schedule);

    $this->artisan('attendance:recompute', [
        'from_date' => '2026-11-09',
        'to_date' => '2026-11-09',
        '--company' => $this->company->id,
    ])->assertSuccessful();

    $fresh = $timesheet->fresh();

    expect((int) $fresh->getRawOriginal('actual_regular'))->toBe(800)
        ->and((int) $fresh->getRawOriginal('actual_ot'))->toBe(0)
        ->and((int) $fresh->getRawOriginal('paid_minutes'))->toBe(480);
});

it('reports changes without saving in a dry run', function () {
    $timesheet = staleTimesheet($this->company, $this->employee, $this->schedule);

    $this->artisan('attendance:recompute', [
        'from_date' => '2026-11-09',
        'to_date' => '2026-11-09',
        '--company' => $this->company->id,
        '--dry-run' => true,
    ])->assertSuccessful();

    $fresh = $timesheet->fresh();

    expect((int) $fresh->getRawOriginal('actual_regular'))->toBe(700)
        ->and((int) $fresh->getRawOriginal('actual_ot'))->toBe(100);
});
