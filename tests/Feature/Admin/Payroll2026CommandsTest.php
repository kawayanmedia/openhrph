<?php

use App\Models\Company;
use App\Models\Employee;
use App\Models\Office;
use App\Models\Payroll;
use App\Models\PayrollDetail;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

beforeEach(function () {
    Company::factory()->create(['id' => 1, 'name' => 'Test Company']);

    Employee::factory()->create([
        'id' => 1,
        'company_id' => 1,
        'office_id' => null,
        'employee_number' => 'E-001',
        'first_name' => 'Ana',
        'last_name' => 'Reyes',
        'sss_number' => '00-0000000-0',
        'philhealth_number' => '00-000000000-0',
        'hdmf_number' => '0000-0000-0000',
        'tin_number' => '000-000-000-000',
    ]);

    Payroll::factory()->create([
        'id' => 1,
        'company_id' => 1,
        'payroll_date' => '2026-11-15',
        'date_from' => '2026-11-01',
        'date_to' => '2026-11-15',
        'status' => 'processed',
    ]);

    PayrollDetail::factory()->create([
        'id' => 1,
        'company_id' => 1,
        'payroll_id' => 1,
        'employee_id' => 1,
        'basic_monthly_salary' => 30000,
        'gross_income' => 15000,
        'taxable_income' => 13862.5,
        'withholding_tax' => 1000,
        'sss_employee_total' => 500,
        'sss_employer_total' => 1000,
        'philhealth_employee' => 375,
        'philhealth_employer' => 375,
        'hdmf_employee' => 100,
        'hdmf_employer' => 100,
    ]);
});

it('exports employee-level SSS and BIR working papers with a control total', function () {
    $sssPath = sys_get_temp_dir().'/sss-export.csv';
    $birPath = sys_get_temp_dir().'/bir-export.csv';

    $this->artisan('payroll:remittance', ['payroll' => 1, '--format' => 'sss', '--path' => $sssPath])->assertExitCode(0);
    $this->artisan('payroll:remittance', ['payroll' => 1, '--format' => 'bir', '--path' => $birPath])->assertExitCode(0);

    $sss = file_get_contents($sssPath);
    $bir = file_get_contents($birPath);

    expect($sss)->toContain('E-001')
        ->and($sss)->toContain('TOTAL')
        ->and($sss)->toContain('500.00')
        ->and($bir)->toContain('E-001')
        ->and($bir)->toContain('TOTAL')
        ->and($bir)->toContain('1000.00');

    @unlink($sssPath);
    @unlink($birPath);
});

it('imports the 2026 Proclamation 1006 holidays', function () {
    $this->artisan('payroll:holidays:import', ['--year' => 2026, '--company' => 1])->assertExitCode(0);

    expect(DB::table('holidays')->where('company_id', 1)->count())->toBe(19)
        ->and(DB::table('holidays')->where('date', '2026-12-25')->value('code'))->toBe('rh')
        ->and(DB::table('holidays')->where('date', '2026-12-24')->value('code'))->toBe('sh')
        ->and(DB::table('holidays')->where('date', '2026-02-25')->value('code'))->toBe('regular');
});

it('imports the 2026 wage orders and marks them provisional', function () {
    $this->artisan('payroll:wage-orders:import')->assertExitCode(0);

    $ncr28 = DB::table('statutory_min_wage')
        ->where('region', 'NCR')
        ->where('industry', 'NON-AGRICULTURE')
        ->where('wage_order', 'NCR-28')
        ->first();

    expect($ncr28)->not->toBeNull()
        ->and((int) $ncr28->amount_centavos)->toBe(75500)
        ->and(DB::table('statutory_sources')->where('table_name', 'statutory_min_wage')->value('status'))->toBe('provisional');
});

it('verifies statutory coverage and fails strict mode on provisional rows', function () {
    DB::table('statutory_sss_brackets')->insert([
        'effective_from' => '2026-01-01', 'msc_from' => 0, 'msc_to' => 524999,
        'msc' => 500000, 'ee' => 25000, 'er' => 50000, 'ec' => 1000,
        'wisp_ee' => 0, 'wisp_er' => 0, 'created_at' => now(), 'updated_at' => now(),
    ]);

    DB::table('statutory_philhealth')->insert([
        'effective_from' => '2026-01-01', 'rate' => 0.05,
        'floor_centavos' => 1000000, 'ceiling_centavos' => 10000000,
        'created_at' => now(), 'updated_at' => now(),
    ]);

    DB::table('statutory_pagibig')->insert([
        'effective_from' => '2026-01-01', 'rate' => 0.02, 'low_rate' => 0.01,
        'low_threshold_centavos' => 150000, 'max_fund_salary_centavos' => 1000000,
        'created_at' => now(), 'updated_at' => now(),
    ]);

    DB::table('statutory_tax_brackets')->insert([
        'effective_from' => '2026-01-01', 'frequency' => 'semi-monthly',
        'from_centavos' => 0, 'to_centavos' => 1041700, 'base_tax_centavos' => 0,
        'rate' => 0, 'created_at' => now(), 'updated_at' => now(),
    ]);

    DB::table('statutory_de_minimis')->insert([
        'code' => 'rice', 'name' => 'Rice allowance', 'ceiling_centavos' => 200000,
        'basis' => 'monthly', 'effective_from' => '2026-01-01',
        'created_at' => now(), 'updated_at' => now(),
    ]);

    foreach ([
        'statutory_sss_brackets', 'statutory_philhealth', 'statutory_pagibig',
        'statutory_tax_brackets', 'statutory_de_minimis',
    ] as $table) {
        DB::table('statutory_sources')->insert([
            'table_name' => $table,
            'effectivity' => '2026-01-01',
            'status' => 'verified',
            'source_url' => 'https://example.test/'.$table,
        ]);
    }

    DB::table('statutory_min_wage')->insert([
        'company_id' => null, 'region' => 'NCR', 'industry' => 'NON-AGRICULTURE',
        'wage_order' => 'NCR-28', 'effective_from' => '2026-01-01', 'amount_centavos' => 75500,
    ]);
    DB::table('statutory_sources')->insert([
        'table_name' => 'statutory_min_wage',
        'effectivity' => '2026-01-01',
        'status' => 'verified',
        'source_url' => 'https://example.test/min-wage',
    ]);

    $this->artisan('payroll:statutory:verify', ['date' => '2026-11-15', '--strict' => true])
        ->assertExitCode(0);

    DB::table('statutory_sources')->where('table_name', 'statutory_pagibig')->update(['status' => 'provisional']);

    $this->artisan('payroll:statutory:verify', ['date' => '2026-11-15'])
        ->assertExitCode(0);
    $this->artisan('payroll:statutory:verify', ['date' => '2026-11-15', '--strict' => true])
        ->assertExitCode(1);

    DB::table('statutory_de_minimis')->delete();

    $this->artisan('payroll:statutory:verify', ['date' => '2026-11-15'])
        ->assertExitCode(1);
});

it('reports payroll master-data quality issues when strict', function () {
    Office::factory()->create([
        'id' => 1,
        'company_id' => 1,
        'name' => 'Head Office',
        'region' => 'NCR',
        'industry' => 'NON-AGRICULTURE',
    ]);

    DB::table('statutory_min_wage')->insert([
        'company_id' => null, 'region' => 'NCR', 'industry' => 'NON-AGRICULTURE',
        'wage_order' => 'NCR-28', 'effective_from' => '2026-01-01', 'amount_centavos' => 75500,
    ]);

    DB::table('employees')->where('id', 1)->update([
        'office_id' => 1, 'basic_salary' => (int) round(755 * 261 * 100),
        'daily_rate' => 75500, 'is_minimum_wage' => true,
    ]);

    $this->artisan('payroll:data-quality', ['--company' => 1, '--json' => true, '--strict' => true])
        ->assertExitCode(0);

    DB::table('employees')->where('id', 1)->update([
        'daily_rate' => 100000, 'tin_number' => '12345',
    ]);

    $this->artisan('payroll:data-quality', ['--company' => 1, '--strict' => true])
        ->assertExitCode(1);
});
