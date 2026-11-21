<?php

use App\Livewire\Admin\Payroll\PayrollPayslip;
use App\Models\Employee;
use App\Models\Office;
use App\Models\Payroll;
use App\Models\PayrollDetail;
use App\Models\PayrollGroup;
use App\Models\Timesheet;
use App\Models\User;
use App\Services\Payroll\EarningTaxReport;
use App\Services\Payroll\Engine\PhpPayrollEngine;
use App\Support\Tenancy\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;

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

it('pays every earning category from the consolidated compensations table', function () {
    $payrollId = seedPhpEnginePayroll();

    $catalog = [
        'allowance' => ['code' => 'RICE', 'name' => 'Rice Subsidy', 'group' => 'Allowance', 'amount' => 20000, 'release_date' => null],
        'bonus' => ['code' => 'BONUS1', 'name' => 'Fixed Bonus', 'group' => 'Bonus', 'amount' => 30000, 'release_date' => '2026-03-10'],
        'commission' => ['code' => 'COMM1', 'name' => 'Sales Commission', 'group' => 'Commission', 'amount' => 40000, 'release_date' => '2026-03-10'],
        'compensation' => ['code' => 'INC1', 'name' => 'Incentive', 'group' => 'Compensation', 'amount' => 50000, 'release_date' => null],
    ];

    foreach ($catalog as $earning) {
        $compensationId = DB::table('compensations')->insertGetId([
            'company_id' => 9,
            'code' => $earning['code'],
            'name' => $earning['name'],
            'group' => $earning['group'],
            'tax_option' => 'fully_taxable',
            'is_taxable' => 1,
            'amount' => 0,
            'status' => 'Active',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('employees_compensations')->insert([
            'company_id' => 9,
            'employee_id' => 501,
            'compensation_id' => $compensationId,
            'is_special' => $earning['release_date'] !== null ? 1 : 0,
            'release_date' => $earning['release_date'],
            'amount' => $earning['amount'],
            'is_recurring' => 0,
            'is_prorated' => 0,
            'on_paid_leave' => 0,
            'on_unpaid_leave' => 0,
            'is_taxable' => 1,
            'is_active' => 1,
            'status' => 'not_release',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    (new PhpPayrollEngine)->process(phpEnginePayroll($payrollId));

    $detail = DB::table('payroll_details')->where('payroll_id', $payrollId)->first();

    expect((int) $detail->allowance)->toBe(20000)
        ->and((int) $detail->bonus)->toBe(30000)
        ->and((int) $detail->commission)->toBe(40000)
        ->and((int) $detail->incentive)->toBe(50000)
        ->and((int) $detail->gross_income)->toBe(1640000)
        ->and(json_decode((string) $detail->compensations, true))->toHaveCount(1);
});

it('explains the tax treatment of each earning on a debug payslip', function () {
    $payrollId = seedPhpEnginePayroll();

    Employee::withoutEvents(fn () => app(TenantContext::class)->run(
        9,
        fn (): int => Employee::whereKey(501)->update(['deminimis' => 1]),
    ));

    $riceId = DB::table('compensations')->insertGetId([
        'company_id' => 9,
        'code' => 'rice',
        'name' => 'Rice Subsidy',
        'group' => 'Allowance',
        'tax_option' => 'fully_nontaxable',
        'is_taxable' => 0,
        'amount' => 0,
        'status' => 'Active',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    DB::table('employees_compensations')->insert([
        'company_id' => 9,
        'employee_id' => 501,
        'compensation_id' => $riceId,
        'is_special' => 0,
        'release_date' => null,
        'amount' => 300000,
        'is_recurring' => 1,
        'is_prorated' => 0,
        'on_paid_leave' => 0,
        'on_unpaid_leave' => 0,
        'is_taxable' => 0,
        'is_active' => 1,
        'status' => 'not_release',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    (new PhpPayrollEngine)->process(phpEnginePayroll($payrollId));

    $detail = app(TenantContext::class)->run(9, fn (): PayrollDetail => PayrollDetail::where('payroll_id', $payrollId)->firstOrFail());

    $report = EarningTaxReport::forPayrollDetail($detail);

    expect($report['from_inputs'])->toBeTrue();

    $rice = collect($report['rows'])->firstWhere('label', 'Rice Subsidy');

    expect($rice)->not->toBeNull()
        ->and($rice['taxable'])->toBeTrue()
        ->and($rice['exempt'])->toBe(2500.0)
        ->and($rice['ceiling'])->toBe(2500.0)
        ->and($rice['excess'])->toBe(500.0)
        ->and($rice['source'])->toBe('statutory de minimis · year-to-date');

    expect(collect($report['rows'])->firstWhere('label', 'Basic pay (period)')['excess'])->toBe(0.0);
});

it('renders the earnings tax treatment on the payslip component when debug is on', function () {
    $payrollId = seedPhpEnginePayroll();

    Employee::withoutEvents(fn () => app(TenantContext::class)->run(
        9,
        fn (): int => Employee::whereKey(501)->update(['deminimis' => 1]),
    ));

    $riceId = DB::table('compensations')->insertGetId([
        'company_id' => 9,
        'code' => 'rice',
        'name' => 'Rice Subsidy',
        'group' => 'Allowance',
        'tax_option' => 'fully_nontaxable',
        'is_taxable' => 0,
        'amount' => 0,
        'status' => 'Active',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    DB::table('employees_compensations')->insert([
        'company_id' => 9,
        'employee_id' => 501,
        'compensation_id' => $riceId,
        'is_special' => 0,
        'release_date' => null,
        'amount' => 300000,
        'is_recurring' => 1,
        'is_prorated' => 0,
        'on_paid_leave' => 0,
        'on_unpaid_leave' => 0,
        'is_taxable' => 0,
        'is_active' => 1,
        'status' => 'not_release',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    (new PhpPayrollEngine)->process(phpEnginePayroll($payrollId));

    config(['app.debug' => true]);

    $this->actingAs(User::factory()->create(['company_id' => 9, 'owner' => true]));

    app(TenantContext::class)->run(9, function () use ($payrollId) {
        Livewire::test(PayrollPayslip::class, ['payroll' => $payrollId, 'employee' => 501])
            ->assertSee('Debug · earnings tax treatment')
            ->assertSee('Rice Subsidy')
            ->assertSee('Partly taxable')
            ->assertSee('statutory de minimis');
    });
});

it('writes each compensation into the payroll detail column for its tax class', function () {
    $payrollId = seedPhpEnginePayroll();

    $catalogue = [
        'REPRESENTATION' => ['Representation Allowance', 25000],
        'HAZARDPAY' => ['Hazard Pay', 10000],
        'OTHSUPP1' => ['Other Supplementary', 5000],
        'BASICSALARY' => ['Basic Pay Adjustment', 7000],
    ];

    foreach ($catalogue as $taxClass => [$name, $amount]) {
        $compensationId = DB::table('compensations')->insertGetId([
            'company_id' => 9,
            'code' => 'TAX-'.$taxClass,
            'name' => $name,
            'tax_class_code' => $taxClass,
            'group' => 'Compensation',
            'tax_option' => 'fully_taxable',
            'is_taxable' => 1,
            'amount' => 0,
            'status' => 'Active',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('employees_compensations')->insert([
            'company_id' => 9,
            'employee_id' => 501,
            'compensation_id' => $compensationId,
            'is_special' => 0,
            'release_date' => null,
            'amount' => $amount,
            'is_recurring' => 0,
            'is_prorated' => 0,
            'on_paid_leave' => 0,
            'on_unpaid_leave' => 0,
            'is_taxable' => 1,
            'is_active' => 1,
            'status' => 'not_release',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    (new PhpPayrollEngine)->process(phpEnginePayroll($payrollId));

    $detail = DB::table('payroll_details')->where('payroll_id', $payrollId)->first();

    // Presentation-only columns: the totals that drive tax and contributions
    // keep counting the whole compensation bucket.
    expect((int) $detail->representation_allowance)->toBe(25000)
        ->and((int) $detail->hazard_pay)->toBe(10000)
        ->and((int) $detail->other_taxable_supplementary)->toBe(5000)
        ->and((int) $detail->other_taxable_regular)->toBe(0)
        ->and((int) $detail->incentive)->toBe(47000)
        ->and((int) $detail->gross_income)->toBe(1547000);

    // A tax class without a dedicated column must not inflate a column that
    // belongs to another class.
    expect(json_decode((string) $detail->compensations, true))->toHaveCount(4);
});

function seedDeMinimisAllowance(string $code, string $name, int $amount, array $overrides = []): void
{
    $compensationId = DB::table('compensations')->insertGetId([
        'company_id' => 9,
        'code' => $code,
        'name' => $name,
        'group' => 'Allowance',
        'tax_option' => 'fully_nontaxable',
        'is_taxable' => 0,
        'amount' => 0,
        'status' => 'Active',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    DB::table('employees_compensations')->insert(array_merge([
        'company_id' => 9,
        'employee_id' => 501,
        'compensation_id' => $compensationId,
        'is_special' => 0,
        'release_date' => null,
        'amount' => $amount,
        'is_recurring' => 1,
        'is_prorated' => 0,
        'on_paid_leave' => 0,
        'on_unpaid_leave' => 0,
        'is_taxable' => 0,
        'is_active' => 1,
        'status' => 'not_release',
        'frequency' => 'every_pay',
        'created_at' => now(),
        'updated_at' => now(),
    ], $overrides));
}

function markEmployeeDeMinimis(int $employeeId = 501): void
{
    Employee::withoutEvents(fn () => app(TenantContext::class)->run(
        9,
        fn (): int => Employee::whereKey($employeeId)->update(['deminimis' => 1]),
    ));
}

it('carries the unused de minimis ceiling into the next payroll of the same month', function () {
    $firstPayroll = seedPhpEnginePayroll();

    markEmployeeDeMinimis();
    seedDeMinimisAllowance('rice', 'Rice Subsidy', 250000);

    Payroll::factory()->create([
        'id' => 78,
        'company_id' => 9,
        'payroll_group_id' => 1,
        'status' => 'open',
        'cut_off' => 'last_pay',
        'date_from' => '2026-03-16',
        'date_to' => '2026-03-31',
        'payroll_date' => '2026-03-31',
        'type' => 'regular',
        'meta' => [],
    ]);

    (new PhpPayrollEngine)->process(phpEnginePayroll($firstPayroll));
    (new PhpPayrollEngine)->process(phpEnginePayroll(78));

    $first = DB::table('payroll_details')->where('payroll_id', $firstPayroll)->first();
    $second = DB::table('payroll_details')->where('payroll_id', 78)->first();

    expect((int) $first->non_taxable_income)->toBe(250000)
        ->and((int) $second->non_taxable_income)->toBe(0);

    expect(json_decode((string) $first->de_minimis_exempt, true))->toBe(['rice|2026-03' => 250000])
        ->and(json_decode((string) $second->de_minimis_exempt, true))->toBe([]);

    expect((int) DB::table('employee_payroll_ytd_de_minimis')
        ->where('company_id', 9)
        ->where('employee_id', 501)
        ->where('period', '2026-03')
        ->where('code', 'rice')
        ->value('exempt_centavos'))->toBe(250000);
});

it('replaces the de minimis ledger contribution when a payroll is re-processed', function () {
    $payrollId = seedPhpEnginePayroll();

    markEmployeeDeMinimis();
    seedDeMinimisAllowance('rice', 'Rice Subsidy', 250000);

    (new PhpPayrollEngine)->process(phpEnginePayroll($payrollId));
    (new PhpPayrollEngine)->process(phpEnginePayroll($payrollId));

    expect((int) DB::table('employee_payroll_ytd_de_minimis')
        ->where('employee_id', 501)
        ->where('period', '2026-03')
        ->where('code', 'rice')
        ->value('exempt_centavos'))->toBe(250000);
});

it('pays a special allowance only in the period that contains its release date', function () {
    $payrollId = seedPhpEnginePayroll();

    markEmployeeDeMinimis();
    seedDeMinimisAllowance('clothing', 'Uniform and Clothing Allowance', 450000, [
        'is_special' => 1,
        'is_recurring' => 0,
        'frequency' => null,
        'release_date' => '2026-02-10',
    ]);

    (new PhpPayrollEngine)->process(phpEnginePayroll($payrollId));

    $detail = DB::table('payroll_details')->where('payroll_id', $payrollId)->first();

    expect((int) $detail->allowance)->toBe(0);
});

it('pays a recurring allowance only in the pay schedule it was given', function () {
    $payrollId = seedPhpEnginePayroll();

    markEmployeeDeMinimis();
    seedDeMinimisAllowance('clothing', 'Uniform and Clothing Allowance', 450000, [
        'frequency' => 'first_pay',
    ]);

    (new PhpPayrollEngine)->process(phpEnginePayroll($payrollId));

    $detail = DB::table('payroll_details')->where('payroll_id', $payrollId)->first();

    expect((int) $detail->allowance)->toBe(0);
});

it('takes a non-recurring employee deduction only in the period it becomes effective', function () {
    $first = seedPhpEnginePayroll();

    $deductionId = DB::table('deductions')->insertGetId([
        'company_id' => 9,
        'name' => 'One-time cash advance',
        'calculation' => 'fixed',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    DB::table('employees_deductions')->insert([
        'company_id' => 9,
        'employee_id' => 501,
        'deduction_id' => $deductionId,
        'amount' => 50000,
        'is_recurring' => 0,
        'frequency' => null,
        'valid_date_from' => '2026-03-05',
        'valid_date_to' => null,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    (new PhpPayrollEngine)->process(phpEnginePayroll($first));

    $firstDeductions = DB::table('payroll_detail_deductions')
        ->where('payroll_id', $first)
        ->where('source', 'deduction')
        ->pluck('employee_centavos')
        ->map(fn ($amount) => (int) $amount)
        ->all();

    expect($firstDeductions)->toContain(50000);

    $second = Payroll::factory()->create([
        'id' => 78,
        'company_id' => 9,
        'payroll_group_id' => 1,
        'status' => 'open',
        'cut_off' => 'first_pay',
        'date_from' => '2026-03-16',
        'date_to' => '2026-03-31',
        'payroll_date' => '2026-03-31',
        'type' => 'regular',
        'meta' => [],
    ]);

    (new PhpPayrollEngine)->process(phpEnginePayroll($second->getKey()));

    $secondDeductions = DB::table('payroll_detail_deductions')
        ->where('payroll_id', $second->getKey())
        ->where('source', 'deduction')
        ->pluck('employee_centavos')
        ->map(fn ($amount) => (int) $amount)
        ->all();

    expect($secondDeductions)->not->toContain(50000);
});
