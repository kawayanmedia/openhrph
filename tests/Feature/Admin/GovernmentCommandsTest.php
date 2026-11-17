<?php

use App\Models\Company;
use App\Models\Employee;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

function installGovernmentForCommands(): Company
{
    $company = Company::factory()->create(['type' => 'Private']);

    test()->artisan('openhr:install', [
        '--sector' => 'Government',
        '--country' => 'PH',
        '--skip-statutory' => true,
    ])->assertExitCode(0);

    return $company;
}

it('reports a step increment and writes it only with apply', function () {
    $company = installGovernmentForCommands();

    $employee = Employee::withoutEvents(fn () => Employee::factory()->create([
        'company_id' => $company->id,
        'salary_grade' => 10,
        'step' => 1,
        'date_hired' => now()->subYears(5)->toDateString(),
        'last_increment' => now()->subYears(4)->toDateString(),
        'date_resigned' => null,
    ]));

    $this->artisan('payroll:step-increments')->assertExitCode(0);

    expect((int) $employee->fresh()->step)->toBe(1);

    $this->artisan('payroll:step-increments', ['--apply' => true])->assertExitCode(0);

    expect((int) $employee->fresh()->step)->toBe(2)
        ->and($employee->fresh()->last_increment)->not->toBeNull();
});

it('creates a loyalty cash gift assignment at the milestone', function () {
    $company = installGovernmentForCommands();

    $employee = Employee::withoutEvents(fn () => Employee::factory()->create([
        'company_id' => $company->id,
        'date_hired' => now()->subYears(10)->toDateString(),
        'date_resigned' => null,
    ]));

    $this->artisan('payroll:loyalty-awards', ['--apply' => true])->assertExitCode(0);

    $compensationId = DB::table('compensations')
        ->whereNull('company_id')
        ->where('code', 'LOYALTY CASH GIFT')
        ->value('id');

    expect($compensationId)->not->toBeNull()
        ->and(DB::table('employees_compensations')
            ->where('employee_id', $employee->id)
            ->where('compensation_id', $compensationId)
            ->exists())->toBeTrue();
});

it('computes terminal leave from the remaining balances', function () {
    $company = installGovernmentForCommands();

    $employee = Employee::withoutEvents(fn () => Employee::factory()->create([
        'company_id' => $company->id,
        'date_resigned' => now()->subMonth()->toDateString(),
        'total_vacation_leave' => 10,
        'total_sick_leave' => 5,
        'daily_rate' => 1000,
        'basic_salary' => 0,
    ]));

    $this->artisan('payroll:terminal-leave', ['--apply' => true])->assertExitCode(0);

    $compensationId = DB::table('compensations')
        ->whereNull('company_id')
        ->where('code', 'TERMINAL LEAVE PAY')
        ->value('id');

    $row = DB::table('employees_compensations')
        ->where('employee_id', $employee->id)
        ->where('compensation_id', $compensationId)
        ->first();

    expect($row)->not->toBeNull()
        ->and((int) $row->amount)->toBe(1_500_000);
});

it('refuses government commands on a private installation', function () {
    $this->artisan('openhr:install', [
        '--sector' => 'Private',
        '--country' => 'PH',
        '--skip-statutory' => true,
    ])->assertExitCode(0);

    $this->artisan('payroll:step-increments')->assertExitCode(1);
    $this->artisan('payroll:loyalty-awards')->assertExitCode(1);
    $this->artisan('payroll:terminal-leave')->assertExitCode(1);
});

it('reports the government document checklist', function () {
    installGovernmentForCommands();

    $this->artisan('payroll:documents')
        ->expectsOutputToContain('Sector: Government')
        ->assertExitCode(0);
});
