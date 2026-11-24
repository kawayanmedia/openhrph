<?php

use App\Models\Company;
use App\Models\Employee;
use App\Models\Payroll;
use App\Models\PayrollDetail;
use App\Models\PayrollGroup;
use App\Services\Payroll\RemittanceReport;
use App\Support\Tenancy\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function remittanceFixture(): array
{
    return app(TenantContext::class)->run(1, function (): array {
        $company = Company::factory()->create(['id' => 1, 'type' => 'Private']);
        PayrollGroup::factory()->create(['id' => 1, 'company_id' => 1]);

        $employee = Employee::factory()->create([
            'company_id' => 1,
            'employee_number' => 'E-1',
            'first_name' => 'Ana',
            'last_name' => 'Cruz',
            'sss_number' => '00-0000001-0',
            'philhealth_number' => '12-345678901-2',
            'hdmf_number' => '1234-5678-9012',
            'tin_number' => '123-456-789-000',
        ]);

        $payroll = Payroll::factory()->create([
            'company_id' => 1,
            'payroll_group_id' => 1,
            'status' => Payroll::STATUS_APPROVED,
            'payroll_date' => '2026-11-15',
            'date_from' => '2026-11-01',
            'date_to' => '2026-11-15',
        ]);

        PayrollDetail::factory()->create([
            'company_id' => 1,
            'payroll_id' => $payroll->getKey(),
            'employee_id' => $employee->getKey(),
            'basic_monthly_salary' => 30000,
            'sss_employee_total' => 1000,
            'sss_employer_total' => 2000,
            'philhealth_employee' => 250,
            'philhealth_employer' => 250,
            'hdmf_employee' => 100,
            'hdmf_employer' => 100,
            'withholding_tax' => 500,
            'taxable_income' => 5000,
            'gross_income' => 6000,
            'total_employee' => 1850,
            'total_deductions' => 1850,
            'net_pay' => 4150,
            'year' => 2026,
        ]);

        return [$company, $payroll, $employee];
    });
}

it('builds the statutory working papers for a payroll', function () {
    [, $payroll] = remittanceFixture();

    $report = app(RemittanceReport::class);

    expect($report->formats())->toHaveKey('sss')
        ->and($report->sectorFormats($payroll))->toContain('sss')
        ->and($report->sectorFormats($payroll))->not->toContain('gsis');

    $summary = collect($report->agencySummary($payroll))->keyBy('code');

    expect($summary->get('sss')['employee'])->toBe(100000)
        ->and($summary->get('sss')['employer'])->toBe(200000)
        ->and($summary->get('sss')['total'])->toBe(300000)
        ->and($summary->get('bir')['total'])->toBe(50000);

    $rows = $report->rows($payroll, 'sss');

    expect($rows[0][0])->toBe('Employee no')
        ->and($rows[1][0])->toBe('E-1')
        ->and($rows[1][2])->toBe('00-0000001-0');

    expect($report->csv($payroll, 'sss'))->toContain('TOTAL');
});
