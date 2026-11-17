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
    config()->set('payroll.countries.XX', [
        'name' => 'Testland',
        'currency' => 'USD',
        'currency_symbol' => '$',
        'locale' => 'en',
        'timezone' => 'UTC',
        'statutory_driver' => 'generic',
        'components' => [],
        'deductions' => [],
    ]);

    $this->artisan('openhr:install', [
        '--sector' => 'Private',
        '--country' => 'XX',
        '--skip-statutory' => true,
    ])->assertExitCode(0);
});

function seedGenericCountryComponents(): void
{
    $socialId = DB::table('statutory_components')->insertGetId([
        'company_id' => null,
        'country_code' => 'XX',
        'sector' => 'any',
        'code' => 'social',
        'name' => 'Social insurance',
        'category' => 'contribution',
        'applies_to' => 'both',
        'calculation' => 'percentage',
        'base' => 'monthly_basic',
        'employee_rate' => 0.05,
        'employer_rate' => 0.05,
        'reduces_taxable_income' => 1,
        'is_active' => 1,
        'sort_order' => 10,
        'schedule' => 'every_pay',
        'effective_from' => '2026-01-01',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $taxId = DB::table('statutory_components')->insertGetId([
        'company_id' => null,
        'country_code' => 'XX',
        'sector' => 'any',
        'code' => 'income_tax',
        'name' => 'Income tax',
        'category' => 'tax',
        'applies_to' => 'employee',
        'calculation' => 'bracket',
        'base' => 'taxable',
        'reduces_taxable_income' => 0,
        'is_active' => 1,
        'sort_order' => 900,
        'schedule' => 'every_pay',
        'effective_from' => '2026-01-01',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    DB::table('statutory_component_bands')->insert([
        [
            'statutory_component_id' => $taxId,
            'from_centavos' => 0,
            'to_centavos' => 999999,
            'base_centavos' => 0,
            'employee_rate' => 0,
            'employer_rate' => 0,
            'employee_amount_centavos' => 0,
            'employer_amount_centavos' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ],
        [
            'statutory_component_id' => $taxId,
            'from_centavos' => 1000000,
            'to_centavos' => null,
            'base_centavos' => 0,
            'employee_rate' => 0.10,
            'employer_rate' => 0,
            'employee_amount_centavos' => 0,
            'employer_amount_centavos' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ],
    ]);

    expect($socialId)->toBeGreaterThan(0);
}

function seedGenericCountryPayroll(): int
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
        'first_name' => 'GENERIC',
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

function genericCountryPayroll(int $id): Payroll
{
    return app(TenantContext::class)->run(9, fn (): Payroll => Payroll::findOrFail($id));
}

it('computes and persists a non-PH payroll from configured components', function () {
    seedGenericCountryComponents();
    $payrollId = seedGenericCountryPayroll();

    $preview = (new PhpPayrollEngine)->preview(genericCountryPayroll($payrollId));
    $result = (new PhpPayrollEngine)->process(genericCountryPayroll($payrollId));

    $detail = DB::table('payroll_details')->where('payroll_id', $payrollId)->first();

    expect($detail)->not->toBeNull()
        ->and((int) $detail->gross_income)->toBe(1500000)
        ->and((int) $detail->total_employee)->toBe(75000)
        ->and((int) $detail->withholding_tax)->toBe(42500)
        ->and((int) $detail->total_deductions)->toBe(117500)
        ->and((int) $detail->net_pay)->toBe(1382500)
        ->and((int) $detail->sss_employee)->toBe(0)
        ->and((int) $detail->philhealth_employee)->toBe(0);

    expect($result->gross)->toBe('15000.00') // ₱15,000 gross expressed in the installation's currency
        ->and($preview->payslips[0]['statutory_employee'])->toBe('750.00');

    $lines = DB::table('payroll_detail_deductions')
        ->where('payroll_id', $payrollId)
        ->orderBy('sort_order')
        ->get();

    expect($lines)->toHaveCount(2)
        ->and($lines[0]->code)->toBe('social')
        ->and((int) $lines[0]->employee_centavos)->toBe(75000)
        ->and((int) $lines[0]->employer_centavos)->toBe(75000)
        ->and($lines[1]->code)->toBe('income_tax')
        ->and((int) $lines[1]->employee_centavos)->toBe(42500);

    expect((int) DB::table('employee_payroll_ytd')->where('employee_id', 501)->value('contributions'))->toBe(75000);
});

it('refuses to run when the country has no configured components', function () {
    $payrollId = seedGenericCountryPayroll();

    (new PhpPayrollEngine)->process(genericCountryPayroll($payrollId));
})->throws(RuntimeException::class);
