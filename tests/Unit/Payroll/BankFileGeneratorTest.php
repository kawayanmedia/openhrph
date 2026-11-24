<?php

use App\Models\BankFileTemplate;
use App\Models\Company;
use App\Models\CompanyBank;
use App\Models\Employee;
use App\Models\Payroll;
use App\Models\PayrollDetail;
use App\Models\PayrollGroup;
use App\Services\Payroll\BankFileGenerator;
use App\Support\Tenancy\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function bankFileFixture(): array
{
    return app(TenantContext::class)->run(1, function (): array {
        $company = Company::factory()->create(['id' => 1, 'type' => 'Private']);
        PayrollGroup::factory()->create(['id' => 1, 'company_id' => 1]);

        $bank = CompanyBank::create([
            'name' => 'BDO Unibank',
            'code' => 'BDO',
            'account_number' => '000000000001',
        ]);

        $employee = Employee::factory()->create([
            'company_id' => 1,
            'employee_number' => 'E-100',
            'first_name' => 'Ana',
            'last_name' => 'Cruz',
            'pay_mode' => 'bank',
            'bank_id' => $bank->getKey(),
            'bank_account_name' => 'Ana Cruz',
            'bank_account_number' => '1234567890',
            'bank_branch' => 'Makati',
        ]);

        $payroll = Payroll::factory()->create([
            'company_id' => 1,
            'payroll_group_id' => 1,
            'status' => Payroll::STATUS_APPROVED,
            'payroll_date' => '2026-11-30',
        ]);

        PayrollDetail::factory()->create([
            'company_id' => 1,
            'payroll_id' => $payroll->getKey(),
            'employee_id' => $employee->getKey(),
            'net_pay' => 1234,
        ]);

        $template = BankFileTemplate::create([
            'company_id' => 1,
            'name' => 'Generic Bank Credit',
            'delimiter' => ',',
            'columns' => [
                ['source' => 'employee_number', 'label' => 'Employee No'],
                ['source' => 'account_name', 'label' => 'Account Name'],
                ['source' => 'account_number', 'label' => 'Account Number'],
                ['source' => 'amount', 'label' => 'Amount'],
            ],
            'amount_in_centavos' => false,
            'include_control_total' => true,
            'date_format' => 'Ymd',
            'is_active' => true,
        ]);

        return [$payroll, $template, $bank, $employee];
    });
}

it('maps payroll details into the bank file columns', function () {
    [$payroll, $template, $bank] = bankFileFixture();

    $result = app(BankFileGenerator::class)->build($payroll, $template, $bank, '2026-11-30');

    expect($result['included'])->toBe(1)
        ->and($result['total'])->toBe(123400)
        ->and($result['rows'][0])->toBe(['E-100', 'Ana Cruz', '1234567890', '1234.00']);

    $csv = app(BankFileGenerator::class)->csv($template, $result);

    expect($csv)->toContain('Employee No')
        ->and($csv)->toContain('Ana Cruz')
        ->and($csv)->toContain('TOTAL,1,1234.00');
});

it('reports employees marked bank-paid without an account number', function () {
    [$payroll, $template] = bankFileFixture();

    $employee = Employee::factory()->create([
        'company_id' => 1,
        'employee_number' => 'E-101',
        'pay_mode' => 'bank',
        'bank_account_number' => null,
    ]);

    PayrollDetail::factory()->create([
        'company_id' => 1,
        'payroll_id' => $payroll->getKey(),
        'employee_id' => $employee->getKey(),
        'net_pay' => 500,
    ]);

    $result = app(BankFileGenerator::class)->build($payroll, $template);

    expect($result['included'])->toBe(1)
        ->and($result['missing'])->toHaveCount(1)
        ->and($result['missing'][0]['employee_number'])->toBe('E-101')
        ->and($result['missing'][0]['reason'])->toContain('account');
});
