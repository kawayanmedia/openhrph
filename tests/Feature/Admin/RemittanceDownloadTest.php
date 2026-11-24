<?php

use App\Models\Company;
use App\Models\Employee;
use App\Models\Payroll;
use App\Models\PayrollDetail;
use App\Models\PayrollGroup;
use App\Models\User;
use App\Support\Tenancy\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('downloads a remittance working paper as CSV', function () {
    $payroll = app(TenantContext::class)->run(1, function (): Payroll {
        $company = Company::factory()->create(['id' => 1, 'type' => 'Private']);
        PayrollGroup::factory()->create(['id' => 1, 'company_id' => 1]);
        $employee = Employee::factory()->create(['company_id' => 1, 'employee_number' => 'E-77', 'sss_number' => '00-0000077-0']);
        $payroll = Payroll::factory()->create([
            'company_id' => 1,
            'payroll_group_id' => 1,
            'status' => Payroll::STATUS_APPROVED,
            'payroll_date' => '2026-11-15',
        ]);

        PayrollDetail::factory()->create([
            'company_id' => 1,
            'payroll_id' => $payroll->getKey(),
            'employee_id' => $employee->getKey(),
            'sss_employee_total' => 1000,
            'sss_employer_total' => 2000,
            'net_pay' => 1000,
        ]);

        return $payroll;
    });

    $user = User::factory()->create(['company_id' => 1, 'owner' => true]);

    $response = $this->actingAs($user)->get(route('admin.reports.remittances.download', [
        'payroll' => $payroll,
        'format' => 'sss',
    ]));

    $response->assertOk();
    expect($response->headers->get('content-type'))->toContain('text/csv')
        ->and($response->streamedContent())->toContain('E-77')
        ->and($response->streamedContent())->toContain('TOTAL');
});
