<?php

use App\Models\BankFileTemplate;
use App\Models\Company;
use App\Models\CompanyBank;
use App\Models\Employee;
use App\Models\Payroll;
use App\Models\PayrollDetail;
use App\Models\PayrollGroup;
use App\Models\User;
use App\Support\Tenancy\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('downloads the payroll bank file for an approved payroll', function () {
    [$payroll, $template] = app(TenantContext::class)->run(1, function (): array {
        $company = Company::factory()->create(['id' => 1, 'type' => 'Private']);
        PayrollGroup::factory()->create(['id' => 1, 'company_id' => 1]);
        $bank = CompanyBank::create(['name' => 'BDO', 'code' => 'BDO', 'account_number' => '0001']);

        $employee = Employee::factory()->create([
            'company_id' => 1,
            'employee_number' => 'E-55',
            'pay_mode' => 'bank',
            'bank_id' => $bank->getKey(),
            'bank_account_name' => 'Ana Cruz',
            'bank_account_number' => '9988776655',
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
            'net_pay' => 1000,
        ]);

        $template = BankFileTemplate::create([
            'company_id' => 1,
            'name' => 'Generic Bank Credit',
            'delimiter' => ',',
            'columns' => [
                ['source' => 'employee_number', 'label' => 'Employee No'],
                ['source' => 'account_number', 'label' => 'Account Number'],
                ['source' => 'amount', 'label' => 'Amount'],
            ],
            'include_control_total' => true,
            'is_active' => true,
        ]);

        return [$payroll, $template];
    });

    $user = User::factory()->create(['company_id' => 1, 'owner' => true]);

    $response = $this->actingAs($user)->get(route('admin.payroll.bank-file.download', [
        'payroll' => $payroll,
        'template_id' => $template->getKey(),
        'payment_date' => '2026-11-30',
    ]));

    $response->assertOk();
    expect($response->headers->get('content-type'))->toContain('text/csv')
        ->and($response->streamedContent())->toContain('9988776655')
        ->and($response->streamedContent())->toContain('1000.00');
});
