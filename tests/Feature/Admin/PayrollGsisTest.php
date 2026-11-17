<?php

use App\Models\Company;
use App\Models\Employee;
use App\Models\Office;
use App\Models\Payroll;
use App\Models\PayrollGroup;
use App\Models\Timesheet;
use App\Services\Payroll\Engine\PayrollEngineException;
use App\Services\Payroll\Engine\PhpPayrollEngine;
use App\Support\Tenancy\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->artisan('payroll:statutory:import')->assertExitCode(0);
});

function gsisPayroll(int $id): Payroll
{
    return app(TenantContext::class)->run(9, fn (): Payroll => Payroll::findOrFail($id));
}

/**
 * A government agency: companies.type = 'Government' switches payroll from
 * SSS to GSIS (RA 8291).
 */
function seedGsisPayroll(string $companyType = 'Government'): int
{
    Company::factory()->create([
        'id' => 9,
        'type' => $companyType,
    ]);

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
        'first_name' => 'GOVERNMENT',
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

it('deducts GSIS instead of SSS for a government agency', function () {
    $payrollId = seedGsisPayroll();

    (new PhpPayrollEngine)->process(gsisPayroll($payrollId));

    $detail = DB::table('payroll_details')->where('payroll_id', $payrollId)->first();

    // ₱15,000 earned for the cut-off: 9% employee and 12% employer.
    expect((int) $detail->gsis_employee)->toBe(135000)
        ->and((int) $detail->gsis_employer)->toBe(180000)
        ->and((int) $detail->gsis_total)->toBe(315000)
        ->and((int) $detail->sss_employee)->toBe(0)
        ->and((int) $detail->sss_employee_total)->toBe(0)
        ->and((int) $detail->sss_employer)->toBe(0)
        ->and((int) $detail->sss_provident_employee)->toBe(0)
        ->and((int) $detail->philhealth_employee)->toBe(37500)
        ->and((int) $detail->hdmf_employee)->toBe(10000)
        ->and((int) $detail->total_employee)->toBe(182500);
});

it('excludes the GSIS employee share from taxable income', function () {
    $payrollId = seedGsisPayroll();

    (new PhpPayrollEngine)->process(gsisPayroll($payrollId));

    $detail = DB::table('payroll_details')->where('payroll_id', $payrollId)->first();

    $expected = (int) $detail->gross_income
        - (int) $detail->non_taxable_income
        - (int) $detail->minimum_wage_exemption
        - (int) $detail->gsis_employee
        - (int) $detail->philhealth_employee
        - (int) $detail->hdmf_employee;

    expect((int) $detail->taxable_income)->toBe($expected)
        ->and((int) $detail->gsis_employee)->toBeGreaterThan(0);
});

it('keeps SSS for a private company', function () {
    $payrollId = seedGsisPayroll('Private');

    (new PhpPayrollEngine)->process(gsisPayroll($payrollId));

    $detail = DB::table('payroll_details')->where('payroll_id', $payrollId)->first();

    expect((int) $detail->sss_employee)->toBe(50000)
        ->and((int) $detail->gsis_employee)->toBe(0)
        ->and((int) $detail->gsis_employer)->toBe(0)
        ->and((int) $detail->gsis_total)->toBe(0);
});

it('does not require the GSIS schedule for a private company', function () {
    DB::table('statutory_gsis')->delete();

    $payrollId = seedGsisPayroll('Private');

    (new PhpPayrollEngine)->process(gsisPayroll($payrollId));

    expect((int) DB::table('payroll_details')->where('payroll_id', $payrollId)->value('sss_employee'))->toBe(50000);
});

it('refuses to process a government payroll without a GSIS schedule', function () {
    DB::table('statutory_gsis')->delete();

    $payrollId = seedGsisPayroll();

    expect(fn () => (new PhpPayrollEngine)->process(gsisPayroll($payrollId)))
        ->toThrow(PayrollEngineException::class, 'statutory_gsis');
});
