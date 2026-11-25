<?php

use App\Models\Employee;
use App\Models\Payroll;
use App\Models\PayrollDetail;
use App\Jurisdictions\Philippines\Statutory\Gsis\GsisSchedule;
use App\Jurisdictions\Philippines\Statutory\Schemes\SchemeResolver;
use App\Models\User;

function payslipScheme(string $agency = 'sss'): \App\Jurisdictions\Philippines\Statutory\Schemes\SocialInsuranceScheme
{
    return SchemeResolver::make($agency, [], GsisSchedule::default());
}

function payslipDetailFixture(array $overrides = []): PayrollDetail
{
    return PayrollDetail::factory()->make(array_merge([
        'id' => 9,
        'company_id' => 1,
        'payroll_id' => 1,
        'employee_id' => 1,
        'basic_monthly_salary' => 30000,
        'daily_rate' => 1149.43,
        'basic_salary' => 15000,
        'regular_pay' => 15000,
        'overtime_pay' => 0,
        'night_pay' => 0,
        'rest_day_pay' => 0,
        'holiday_pay' => 0,
        'allowance' => 0,
        'incentive' => 0,
        'bonus' => 0,
        'commission' => 0,
        'thirteenth_month' => 0,
        'leave_conversion' => 0,
        'separation_pay' => 0,
        'absent_days' => 0,
        'absent_days_amount' => 0,
        'gross_income' => 15000,
        'sss_employee' => 500,
        'sss_provident_employee' => 0,
        'philhealth_employee' => 375,
        'hdmf_employee' => 100,
        'withholding_tax' => 1000,
        'loans' => 0,
        'total_employee' => 975,
        'total_deductions' => 1975,
        'net_pay' => 13025,
        'total_employer' => 1475,
        'taxable_income' => 13862.50,
    ], $overrides));
}

it('renders a payslip that masks government ids and names the preparer and approver', function () {
    $preparer = User::factory()->make(['id' => 1, 'name' => 'Maria Cruz']);
    $approver = User::factory()->make(['id' => 2, 'name' => 'Jose Santos']);

    $payroll = Payroll::factory()->make([
        'id' => 51,
        'company_id' => 1,
        'payroll_group_id' => 1,
        'payroll_date' => '2026-11-15',
        'date_from' => '2026-11-01',
        'date_to' => '2026-11-15',
    ]);
    $payroll->setRelation('processedBy', $preparer);
    $payroll->setRelation('approvedBy', $approver);

    $employee = Employee::factory()->make([
        'id' => 1,
        'company_id' => 1,
        'office_id' => null,
        'employee_number' => 'E-001',
        'first_name' => 'Ana',
        'last_name' => 'Reyes',
        'position' => 'Accountant',
        'tin_number' => '123-456-789-000',
        'sss_number' => '34-1234567-8',
        'philhealth_number' => '12-345678901-2',
        'hdmf_number' => '1234-5678-9012',
    ]);

    $this->view('livewire.admin.payroll.payslip', [
        'payroll' => $payroll,
        'employee' => $employee,
        'scheme' => payslipScheme(),
        'detail' => payslipDetailFixture(),
    ])
        ->assertSee('Ana Reyes')
        ->assertSee('E-001')
        ->assertSee('13,025.00')
        ->assertSee('Prepared by: Maria Cruz')
        ->assertSee('Approved by: Jose Santos')
        ->assertDontSee('123-456-789-000')
        ->assertDontSee('34-1234567-8')
        ->assertSee('•'.substr('123-456-789-000', -4))
        ->assertSee('•'.substr('1234-5678-9012', -4));
});

it('shows employer share and taxable income on the payslip', function () {
    $payroll = Payroll::factory()->make([
        'id' => 52,
        'company_id' => 1,
        'payroll_group_id' => 1,
        'payroll_date' => '2026-11-30',
        'date_from' => '2026-11-16',
        'date_to' => '2026-11-30',
    ]);
    $payroll->setRelation('processedBy', null);
    $payroll->setRelation('approvedBy', null);

    $employee = Employee::factory()->make([
        'id' => 2,
        'company_id' => 1,
        'office_id' => null,
        'employee_number' => 'E-002',
        'first_name' => 'Ben',
        'last_name' => 'Lim',
        'position' => null,
    ]);

    $this->view('livewire.admin.payroll.payslip', [
        'payroll' => $payroll,
        'employee' => $employee,
        'scheme' => payslipScheme(),
        'detail' => payslipDetailFixture(['id' => 10, 'net_pay' => 1302500]),
    ])
        ->assertSee('Employer share: ₱1,475.00')
        ->assertSee('15,000.00')
        ->assertSee('Taxable income: ₱13,862.50')
        ->assertSee('Payroll #52')
        ->assertSee('Prepared by: —')
        ->assertSee('Approved by: —')
        ->assertSee('TIN: —')
        ->assertSee('SSS: —');
});

it('itemises each compensation on the payslip instead of hiding it in an incentive total', function () {
    $payroll = Payroll::factory()->make([
        'id' => 53,
        'company_id' => 1,
        'payroll_group_id' => 1,
        'payroll_date' => '2026-11-15',
        'date_from' => '2026-11-01',
        'date_to' => '2026-11-15',
    ]);
    $payroll->setRelation('processedBy', null);
    $payroll->setRelation('approvedBy', null);

    $employee = Employee::factory()->make([
        'id' => 3,
        'company_id' => 1,
        'office_id' => null,
        'employee_number' => 'E-003',
        'first_name' => 'Cara',
        'last_name' => 'Diaz',
        'position' => null,
    ]);

    $this->view('livewire.admin.payroll.payslip', [
        'payroll' => $payroll,
        'employee' => $employee,
        'scheme' => payslipScheme(),
        'detail' => payslipDetailFixture([
            'incentive' => 3000,
            'representation_allowance' => 2000,
            'compensations' => [
                ['code' => 'rice', 'name' => 'Rice Subsidy', 'kind' => 'compensation', 'amount' => 100000, 'taxable' => false],
                ['code' => 'REPRESENTATION', 'name' => 'REPRESENTATION ALLOWANCE', 'kind' => 'compensation', 'amount' => 200000, 'taxable' => true],
            ],
        ]),
    ])
        ->assertSee('Rice Subsidy')
        ->assertSee('1,000.00')
        ->assertSee('REPRESENTATION ALLOWANCE')
        ->assertSee('2,000.00')
        ->assertDontSee('Other incentives');
});

it('surfaces compensation the itemised lines do not account for', function () {
    $payroll = Payroll::factory()->make([
        'id' => 54,
        'company_id' => 1,
        'payroll_group_id' => 1,
        'payroll_date' => '2026-11-15',
        'date_from' => '2026-11-01',
        'date_to' => '2026-11-15',
    ]);
    $payroll->setRelation('processedBy', null);
    $payroll->setRelation('approvedBy', null);

    $employee = Employee::factory()->make([
        'id' => 4,
        'company_id' => 1,
        'office_id' => null,
        'employee_number' => 'E-004',
        'first_name' => 'Dan',
        'last_name' => 'Reyes',
        'position' => null,
    ]);

    $this->view('livewire.admin.payroll.payslip', [
        'payroll' => $payroll,
        'employee' => $employee,
        'scheme' => payslipScheme(),
        'detail' => payslipDetailFixture([
            'incentive' => 3500,
            'compensations' => [
                ['code' => 'rice', 'name' => 'Rice Subsidy', 'kind' => 'compensation', 'amount' => 100000, 'taxable' => false],
            ],
        ]),
    ])
        ->assertSee('Other incentives')
        ->assertSee('2,500.00');
});

it('shows the GSIS BP number instead of SSS on a government payslip', function () {
    $payroll = Payroll::factory()->make([
        'id' => 55,
        'company_id' => 1,
        'payroll_group_id' => 1,
        'payroll_date' => '2026-11-15',
        'date_from' => '2026-11-01',
        'date_to' => '2026-11-15',
    ]);
    $payroll->setRelation('processedBy', null);
    $payroll->setRelation('approvedBy', null);

    $employee = Employee::factory()->make([
        'id' => 5,
        'company_id' => 1,
        'office_id' => null,
        'employee_number' => 'E-005',
        'first_name' => 'Gina',
        'last_name' => 'Cruz',
        'gsis_number' => '0100-0000-0001',
        'sss_number' => '34-1234567-8',
        'tin_number' => '123-456-789-000',
        'philhealth_number' => '12-345678901-2',
        'hdmf_number' => '1234-5678-9012',
    ]);

    $this->view('livewire.admin.payroll.payslip', [
        'payroll' => $payroll,
        'employee' => $employee,
        'scheme' => payslipScheme('gsis'),
        'detail' => payslipDetailFixture(['gsis_employee' => 1350]),
        'identifier' => '0100-0000-0001',
    ])
        ->assertSee('GSIS BP:')
        ->assertSee('0001')
        ->assertDontSee('SSS:');
});

function payslipTaxReportFixture(): array
{
    return [
        'from_inputs' => true,
        'note' => '',
        'summary' => [
            'gross' => 18500.0,
            'non_taxable' => 3250.0,
            'benefit_exempt' => 0.0,
            'minimum_wage_exemption' => 0.0,
            'taxable' => 15000.0,
        ],
        'rows' => [
            ['label' => 'Basic pay (period)', 'amount' => 15000.0, 'taxable' => true, 'exempt' => 0.0, 'ceiling' => null, 'basis' => '', 'source' => '', 'excess' => 0.0],
            ['label' => 'Rice Subsidy', 'amount' => 3000.0, 'taxable' => true, 'exempt' => 1250.0, 'ceiling' => 1250.0, 'basis' => 'monthly', 'source' => 'statutory de minimis', 'excess' => 1750.0],
            ['label' => 'Non-taxable compensation', 'amount' => 2000.0, 'taxable' => false, 'exempt' => 2000.0, 'ceiling' => null, 'basis' => '', 'source' => '', 'excess' => 0.0],
        ],
    ];
}

function payslipTaxReportPayroll(): Payroll
{
    $payroll = Payroll::factory()->make([
        'id' => 56,
        'company_id' => 1,
        'payroll_group_id' => 1,
        'payroll_date' => '2026-11-15',
        'date_from' => '2026-11-01',
        'date_to' => '2026-11-15',
    ]);
    $payroll->setRelation('processedBy', null);
    $payroll->setRelation('approvedBy', null);

    return $payroll;
}

function payslipTaxReportEmployee(): Employee
{
    return Employee::factory()->make([
        'id' => 6,
        'company_id' => 1,
        'office_id' => null,
        'employee_number' => 'E-006',
        'first_name' => 'Ella',
        'last_name' => 'Torres',
        'position' => null,
    ]);
}

it('shows the earnings tax treatment when debug mode is on', function () {
    config(['app.debug' => true]);

    $this->view('livewire.admin.payroll.payslip', [
        'payroll' => payslipTaxReportPayroll(),
        'employee' => payslipTaxReportEmployee(),
        'scheme' => payslipScheme(),
        'detail' => payslipDetailFixture(['id' => 11]),
        'taxReport' => payslipTaxReportFixture(),
    ])
        ->assertSee('Debug · earnings tax treatment')
        ->assertSee('Rice Subsidy')
        ->assertSee('Partly taxable')
        ->assertSee('per month')
        ->assertSee('₱1,750.00')
        ->assertSee('Non-taxable')
        ->assertSee('Taxable income (persisted)');
});

it('hides the earnings tax treatment outside debug mode', function () {
    config(['app.debug' => false]);

    $this->view('livewire.admin.payroll.payslip', [
        'payroll' => payslipTaxReportPayroll(),
        'employee' => payslipTaxReportEmployee(),
        'scheme' => payslipScheme(),
        'detail' => payslipDetailFixture(['id' => 12]),
        'taxReport' => payslipTaxReportFixture(),
    ])
        ->assertDontSee('Debug · earnings tax treatment')
        ->assertDontSee('Partly taxable')
        ->assertDontSee('₱1,750.00');
});
