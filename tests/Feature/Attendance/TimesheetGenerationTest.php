<?php

use App\Gateways\GenerateTimesheetGateway;
use App\Models\Company;
use App\Models\Employee;
use App\Models\Schedule;
use App\Models\Timesheet;
use App\Services\Attendance\TimesheetImport;
use App\Services\Payroll\Computation\Rules;
use App\Support\Tenancy\TenantContext;
use Carbon\CarbonPeriod;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;

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
        'biometric_id' => 101,
        'schedule_id' => $this->schedule->id,
        'office_id' => null,
    ]);
});

afterEach(function () {
    Carbon::setTestNow();
});

it('skips employees without a schedule', function () {
    $this->employee->schedule_id = null;
    $this->employee->save();

    $summary = (new GenerateTimesheetGateway)->addDays($this->employee, CarbonPeriod::create('2026-11-10', '1 day', '2026-11-12'));

    expect($summary)->toBe(['created' => 0, 'updated' => 0, 'skipped' => 3]);

    app(TenantContext::class)->set($this->company->id);

    expect(Timesheet::query()->count())->toBe(0);
});

it('does not overwrite an existing day classification', function () {
    Timesheet::factory()->create([
        'company_id' => $this->company->id,
        'employee_id' => $this->employee->id,
        'employee_number' => $this->employee->employee_number,
        'log_date' => '2026-11-10',
        'day_type' => Timesheet::REST_DAY,
        'day_rate_code' => Timesheet::REST_DAY,
        'schedule_id' => $this->schedule->id,
    ]);

    $summary = (new GenerateTimesheetGateway)->addDays($this->employee, CarbonPeriod::create('2026-11-10', '1 day', '2026-11-10'));

    expect($summary['skipped'])->toBe(1);

    app(TenantContext::class)->set($this->company->id);

    $timesheet = Timesheet::query()->firstOrFail();

    expect($timesheet->day_type)->toBe(Timesheet::REST_DAY);
});

it('follows a schedule reassignment without reclassifying the day', function () {
    $other = Schedule::factory()->create([
        'company_id' => $this->company->id,
        'name' => 'Night Shift',
        'am_in' => null,
        'am_out' => null,
        'pm_in' => '22:00',
        'pm_out' => null,
        'am_out_morning' => '06:00',
    ]);

    $timesheet = Timesheet::factory()->create([
        'company_id' => $this->company->id,
        'employee_id' => $this->employee->id,
        'employee_number' => $this->employee->employee_number,
        'log_date' => '2026-11-10',
        'day_type' => Timesheet::REGULAR_DAY,
        'day_rate_code' => Timesheet::REGULAR_DAY,
        'schedule_id' => $this->schedule->id,
    ]);

    $summary = (new GenerateTimesheetGateway)->addDays($this->employee, CarbonPeriod::create('2026-11-10', '1 day', '2026-11-10'), $other->id);

    expect($summary['updated'])->toBe(1);

    $timesheet->refresh();

    expect($timesheet->schedule_id)->toBe($other->id);
    expect($timesheet->day_type)->toBe(Timesheet::REGULAR_DAY);
});

it('generates only the requested date range', function () {
    $this->artisan('blank_timesheet:generate', [
        'from_date' => '2026-11-10',
        'to_date' => '2026-11-12',
        '--company' => $this->company->id,
    ])->assertSuccessful();

    app(TenantContext::class)->set($this->company->id);

    $dates = Timesheet::query()->orderBy('log_date')->pluck('log_date')
        ->map(fn ($date) => $date->toDateString())
        ->all();

    expect($dates)->toBe(['2026-11-10', '2026-11-11', '2026-11-12']);
});

it('imports punches, merges them and reports unmatched employees', function () {
    Storage::disk('local')->put('logs/import-test.txt', implode("\n", [
        "101\t2026-11-10 08:00:00\t1\t1\t1\t0",
        "101\t2026-11-10 17:00:00\t1\t1\t1\t1",
        "999\t2026-11-10 08:00:00\t1\t1\t1\t0",
    ])."\n");

    $summary = app(TimesheetImport::class)->import('logs/import-test.txt');

    expect($summary['accepted'])->toBe(2);
    expect($summary['unmatched'])->toBe(1);
    expect($summary['timesheets'])->toBe(1);

    app(TenantContext::class)->set($this->company->id);

    $timesheet = Timesheet::query()->where('employee_id', $this->employee->id)->firstOrFail();

    expect($timesheet->logs)->toHaveCount(2);
    expect($timesheet->logs[0]['source'])->toBe('import');

    $repeat = app(TimesheetImport::class)->import('logs/import-test.txt');

    expect($repeat['accepted'])->toBe(0);
    expect($repeat['duplicates'])->toBe(2);

    Storage::disk('local')->delete('logs/import-test.txt');
});

it('derives the paid day type from the canonical day rate code', function () {
    $timesheet = Timesheet::factory()->create([
        'company_id' => $this->company->id,
        'employee_id' => $this->employee->id,
        'employee_number' => $this->employee->employee_number,
        'log_date' => '2026-11-10',
        'day_type' => Timesheet::REGULAR_DAY,
        'day_rate_code' => Timesheet::REGULAR_HOLIDAY,
    ]);

    expect($timesheet->paid_day_type)->toBe(Rules::DAY_REGULAR_HOLIDAY);
});

it('reflects a change to the canonical day rate code', function () {
    $timesheet = Timesheet::factory()->create([
        'company_id' => $this->company->id,
        'employee_id' => $this->employee->id,
        'employee_number' => $this->employee->employee_number,
        'log_date' => '2026-11-10',
        'day_type' => Timesheet::REGULAR_DAY,
        'day_rate_code' => Timesheet::REGULAR_DAY,
    ]);

    $timesheet->day_rate_code = Timesheet::SPECIAL_HOLIDAY;
    $timesheet->save();

    expect($timesheet->fresh()->paid_day_type)->toBe(Rules::DAY_SPECIAL_HOLIDAY);
});

it('backfills and drops the legacy rate code column', function () {
    Schema::table('timesheets', function (Blueprint $table): void {
        $table->string('rate_code', 32)->nullable();
    });

    $timesheet = Timesheet::factory()->create([
        'company_id' => $this->company->id,
        'employee_id' => $this->employee->id,
        'employee_number' => $this->employee->employee_number,
        'log_date' => '2026-11-10',
        'day_type' => Timesheet::REGULAR_DAY,
        'day_rate_code' => null,
    ]);

    DB::table('timesheets')->where('id', $timesheet->id)->update(['rate_code' => Timesheet::REGULAR_HOLIDAY]);

    $migration = require database_path('migrations/2026_11_21_000300_drop_rate_code_from_timesheets_table.php');
    $migration->up();

    expect(DB::table('timesheets')->where('id', $timesheet->id)->value('day_rate_code'))
        ->toBe(Timesheet::REGULAR_HOLIDAY)
        ->and(Schema::hasColumn('timesheets', 'rate_code'))
        ->toBeFalse();
});
