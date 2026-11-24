<?php

use App\Models\Company;
use App\Models\Employee;
use App\Models\Payroll;
use App\Models\PayrollDetail;
use App\Models\PayrollGroup;
use App\Models\PayrollRemittance;
use App\Services\Payroll\RemittanceSchedule;
use App\Support\Tenancy\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function trackingPayrollFixture(): Payroll
{
    return app(TenantContext::class)->run(1, function (): Payroll {
        $company = Company::factory()->create(['id' => 1, 'type' => 'Private']);
        PayrollGroup::factory()->create(['id' => 1, 'company_id' => 1]);
        $employee = Employee::factory()->create(['company_id' => 1]);
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
            'sss_employee_total' => 1000,
            'sss_employer_total' => 2000,
            'philhealth_employee' => 250,
            'philhealth_employer' => 250,
            'hdmf_employee' => 100,
            'hdmf_employer' => 100,
            'withholding_tax' => 500,
            'net_pay' => 1000,
        ]);

        return $payroll;
    });
}

it('generates and updates the remittance register', function () {
    $payroll = trackingPayrollFixture();
    $schedule = app(RemittanceSchedule::class);

    $rows = $schedule->sync($payroll);

    expect($rows->pluck('agency')->all())->toContain('sss', 'philhealth', 'pagibig', 'bir')
        ->and($rows->firstWhere('agency', 'sss')->status)->toBe(PayrollRemittance::STATUS_PENDING)
        ->and($rows->firstWhere('agency', 'sss')->due_date->format('Y-m-d'))->toBe('2026-11-30');

    $sss = $rows->firstWhere('agency', 'sss');

    $schedule->markFiled($sss, 'SSS-REF-1');
    expect($sss->fresh()->status)->toBe(PayrollRemittance::STATUS_FILED)
        ->and($sss->fresh()->reference_number)->toBe('SSS-REF-1');

    $schedule->markPaid($sss, null, 'SSS-REF-1');
    expect($sss->fresh()->status)->toBe(PayrollRemittance::STATUS_PAID)
        ->and($sss->fresh()->paid_at)->not->toBeNull();

    // Re-syncing must not reset a filed/paid row.
    $schedule->sync($payroll);
    expect($sss->fresh()->status)->toBe(PayrollRemittance::STATUS_PAID);
});
