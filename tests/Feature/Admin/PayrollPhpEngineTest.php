<?php

use App\Models\Employee;
use App\Models\Office;
use App\Models\Payroll;
use App\Models\PayrollGroup;
use App\Models\Timesheet;
use App\Services\Payroll\Engine\PhpPayrollEngine;
use App\Support\Tenancy\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->artisan('payroll:statutory:import')->assertExitCode(0);
});

/**
 * Exercises the PHP driver end to end: load the rows, compute through the
 * parity-locked core, and persist with the Go writer's column mapping.
 */
function phpEnginePayroll(int $id): Payroll
{
    return app(TenantContext::class)->run(9, fn (): Payroll => Payroll::findOrFail($id));
}

function seedPhpEnginePayroll(): int
{
    PayrollGroup::factory()->create([
        'id' => 1,
        'company_id' => 9,
        'frequency' => 'semi-monthly',
        'days_year' => 261,
        'hours_day' => 8,
        'sss_schedule' => 'every_pay',
        'phil_health_schedule' => 'every_pay',
        'hdmf_schedule' => 'every_pay',
        'tax_schedule' => 'every_pay',
    ]);

    Office::factory()->create([
        'id' => 1,
        'company_id' => 9,
        'region' => 'NCR',
        'industry' => 'NON-AGRICULTURE',
    ]);

    Employee::withoutEvents(fn () => Employee::factory()->create([
        'id' => 501,
        'company_id' => 9,
        'payroll_group_id' => 1,
        'office_id' => 1,
        'employee_number' => 'E-0501',
        'first_name' => 'MONTHLY',
        'last_name' => 'EMPLOYEE',
        'basic_salary' => 30000,
        'daily_rate' => 0,
        'dependents' => '0',
        'payment_type' => 'monthly',
    ]));

    Payroll::factory()->create([
        'id' => 77,
        'company_id' => 9,
        'payroll_group_id' => 1,
        'status' => 'open',
        'cut_off' => 'last_pay',
        'date_from' => '2026-03-01',
        'date_to' => '2026-03-15',
        'payroll_date' => '2026-03-15',
        'type' => 'regular',
        'meta' => [],
    ]);

    foreach (range(1, 15) as $day) {
        Timesheet::factory()->create([
            'company_id' => 9,
            'employee_id' => 501,
            'employee_number' => 'E-0501',
            'log_date' => sprintf('2026-03-%02d', $day),
            'day_type' => 'regular',
            'actual_regular' => 8,
        ]);
    }

    return 77;
}

it('processes a payroll through the parity-locked PHP driver', function () {
    $payrollId = seedPhpEnginePayroll();

    $result = (new PhpPayrollEngine)->process(phpEnginePayroll($payrollId));

    $detail = DB::table('payroll_details')->where('payroll_id', $payrollId)->first();

    expect($detail)->not->toBeNull()
        ->and((int) $detail->gross_income)->toBe(1500000)
        ->and((int) $detail->basic_salary)->toBe(1500000)
        ->and((int) $detail->sss_employee)->toBe(50000)
        ->and((int) $detail->sss_provident_employee)->toBe(25000)
        ->and((int) $detail->philhealth_employee)->toBe(37500)
        ->and((int) $detail->hdmf_employee)->toBe(10000)
        ->and((int) $detail->total_employee)->toBe(122500)
        ->and((int) $detail->withholding_tax)->toBeGreaterThan(0);

    expect($result->gross)->toBe('15000.00')
        ->and($result->employees)->toBe(1)
        ->and($result->engineVersion)->toBe('php-legacy');

    expect(DB::table('payrolls')->where('id', $payrollId)->value('status'))->toBe('processed')
        ->and(DB::table('employee_payroll_ytd')->where('employee_id', 501)->value('gross_earned'))->toBe(1500000)
        ->and(DB::table('payroll_audit_events')->where('payroll_id', $payrollId)->value('event'))->toBe('processed');
});

it('replaces rather than accumulates when re-processing', function () {
    $payrollId = seedPhpEnginePayroll();

    (new PhpPayrollEngine)->process(phpEnginePayroll($payrollId));
    (new PhpPayrollEngine)->process(phpEnginePayroll($payrollId));

    expect(DB::table('payroll_details')->where('payroll_id', $payrollId)->count())->toBe(1)
        ->and(DB::table('payroll_detail_revisions')->where('payroll_id', $payrollId)->count())->toBe(1)
        ->and((int) DB::table('employee_payroll_ytd')->where('employee_id', 501)->value('gross_earned'))->toBe(1500000)
        ->and(DB::table('payroll_audit_events')->where('payroll_id', $payrollId)->orderByDesc('id')->value('event'))->toBe('reprocessed');
});

it('previews without writing', function () {
    $payrollId = seedPhpEnginePayroll();

    $result = (new PhpPayrollEngine)->preview(phpEnginePayroll($payrollId));

    expect($result->dryRun)->toBeTrue()
        ->and($result->gross)->toBe('15000.00')
        ->and(DB::table('payroll_details')->count())->toBe(0);
});
