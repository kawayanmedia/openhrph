<?php

use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('computes monthly withholding tax after mandatory contributions', function () {
    $this->postJson('/api/calculators/withholding-tax', [
        'period' => 'monthly',
        'basic_salary' => 30000,
    ])->assertOk()
        ->assertJsonPath('data.sector', 'Private')
        ->assertJsonPath('data.period', 'monthly')
        ->assertJsonPath('data.regular_compensation', 30000)
        ->assertJsonPath('data.gross_compensation_income', 30000)
        ->assertJsonPath('data.deductions.social_insurance.agency', 'SSS')
        ->assertJsonPath('data.deductions.social_insurance.employee', 1500)
        ->assertJsonPath('data.deductions.philhealth.employee', 750)
        ->assertJsonPath('data.deductions.pagibig.employee', 200)
        ->assertJsonPath('data.deductions.total_employee', 2450)
        ->assertJsonPath('data.taxable_compensation_income', 27550)
        ->assertJsonPath('data.withholding_tax', 1007.55)
        ->assertJsonPath('data.effective_tax_rate', 0.0336);
});

it('computes semi-monthly withholding tax after mandatory contributions', function () {
    $this->postJson('/api/calculators/withholding-tax', [
        'period' => 'semi_monthly',
        'basic_salary' => 11417,
    ])->assertOk()
        ->assertJsonPath('data.period', 'semi-monthly')
        ->assertJsonPath('data.deductions.social_insurance.employee', 575)
        ->assertJsonPath('data.deductions.total_employee', 960.43)
        ->assertJsonPath('data.taxable_compensation_income', 10456.57)
        ->assertJsonPath('data.withholding_tax', 5.94)
        ->assertJsonPath('data.effective_tax_rate', 0.0005);
});

it('computes daily and weekly withholding tax after mandatory contributions', function () {
    $this->postJson('/api/calculators/withholding-tax', [
        'period' => 'daily',
        'basic_salary' => 1000,
    ])->assertOk()
        ->assertJsonPath('data.taxable_compensation_income', 915.91)
        ->assertJsonPath('data.withholding_tax', 34.64)
        ->assertJsonPath('data.effective_tax_rate', 0.0346);

    $this->postJson('/api/calculators/withholding-tax', [
        'period' => 'weekly',
        'basic_salary' => 5000,
    ])->assertOk()
        ->assertJsonPath('data.taxable_compensation_income', 4588.64)
        ->assertJsonPath('data.withholding_tax', 0);
});

it('computes annual withholding tax after mandatory contributions', function () {
    $this->postJson('/api/calculators/withholding-tax', [
        'period' => 'annual',
        'basic_salary' => 300000,
    ])->assertOk()
        ->assertJsonPath('data.deductions.total_employee', 24900)
        ->assertJsonPath('data.taxable_compensation_income', 275100)
        ->assertJsonPath('data.withholding_tax', 3765)
        ->assertJsonPath('data.effective_tax_rate', 0.0126);
});

it('uses GSIS for the withholding calculator government sector', function () {
    $this->postJson('/api/calculators/withholding-tax', [
        'sector' => 'Government',
        'period' => 'monthly',
        'basic_salary' => 30000,
    ])->assertOk()
        ->assertJsonPath('data.sector', 'Government')
        ->assertJsonPath('data.deductions.social_insurance.agency', 'GSIS')
        ->assertJsonPath('data.deductions.social_insurance.employee', 2700)
        ->assertJsonPath('data.deductions.total_employee', 3650)
        ->assertJsonPath('data.taxable_compensation_income', 26350)
        ->assertJsonPath('data.withholding_tax', 827.55)
        ->assertJsonPath('data.effective_tax_rate', 0.0276);
});

it('adds the thirteenth month excess over ninety thousand to taxable income', function () {
    $this->postJson('/api/calculators/withholding-tax', [
        'period' => 'monthly',
        'basic_salary' => 30000,
        'thirteenth_month' => 100000,
    ])->assertOk()
        ->assertJsonPath('data.non_taxable_benefits', 90000)
        ->assertJsonPath('data.supplementary_compensation', 10000)
        ->assertJsonPath('data.deductions.total_employee', 2450)
        ->assertJsonPath('data.taxable_compensation_income', 37550)
        ->assertJsonPath('data.withholding_tax', 2718.4)
        ->assertJsonPath('data.effective_tax_rate', 0.068);
});

it('rejects invalid withholding tax input', function () {
    $this->postJson('/api/calculators/withholding-tax', [
        'period' => 'fortnightly',
        'basic_salary' => 30000,
    ])->assertUnprocessable()
        ->assertJsonValidationErrors('period');

    $this->postJson('/api/calculators/withholding-tax', [
        'period' => 'monthly',
        'basic_salary' => -1,
    ])->assertUnprocessable()
        ->assertJsonValidationErrors('basic_salary');

    $this->postJson('/api/calculators/withholding-tax', [
        'sector' => 'Foo',
        'period' => 'monthly',
        'basic_salary' => 30000,
    ])->assertUnprocessable()
        ->assertJsonValidationErrors('sector');
});

it('computes monthly statutory deductions and net pay after tax', function () {
    $this->postJson('/api/calculators/take-home-pay', [
        'period' => 'monthly',
        'basic_salary' => 30000,
    ])->assertOk()
        ->assertJsonPath('data.gross_pay', 30000)
        ->assertJsonPath('data.taxable_compensation_income', 27550)
        ->assertJsonPath('data.deductions.social_insurance.employee', 1500)
        ->assertJsonPath('data.deductions.social_insurance.employer', 3030)
        ->assertJsonPath('data.deductions.philhealth.employee', 750)
        ->assertJsonPath('data.deductions.pagibig.employee', 200)
        ->assertJsonPath('data.deductions.withholding_tax', 1007.55)
        ->assertJsonPath('data.deductions.total_employee', 3457.55)
        ->assertJsonPath('data.net_pay', 26542.45)
        ->assertJsonPath('data.employer.total_contributions', 3980)
        ->assertJsonPath('data.employer.total_cost', 33980);
});

it('prorates statutory contributions for a semi-monthly period', function () {
    $this->postJson('/api/calculators/take-home-pay', [
        'period' => 'semi-monthly',
        'basic_salary' => 15000,
    ])->assertOk()
        ->assertJsonPath('data.period', 'semi-monthly')
        ->assertJsonPath('data.gross_pay', 15000)
        ->assertJsonPath('data.taxable_compensation_income', 13775)
        ->assertJsonPath('data.deductions.social_insurance.employee', 750)
        ->assertJsonPath('data.deductions.philhealth.employee', 375)
        ->assertJsonPath('data.deductions.pagibig.employee', 100)
        ->assertJsonPath('data.deductions.withholding_tax', 503.7)
        ->assertJsonPath('data.deductions.total_employee', 1728.7)
        ->assertJsonPath('data.net_pay', 13271.3)
        ->assertJsonPath('data.employer.total_contributions', 1990)
        ->assertJsonPath('data.employer.total_cost', 16990);
});

it('prorates statutory contributions for a weekly period', function () {
    $this->postJson('/api/calculators/take-home-pay', [
        'period' => 'weekly',
        'basic_salary' => 7000,
    ])->assertOk()
        ->assertJsonPath('data.taxable_compensation_income', 6470.46)
        ->assertJsonPath('data.deductions.social_insurance.employee', 318.18)
        ->assertJsonPath('data.deductions.philhealth.employee', 175)
        ->assertJsonPath('data.deductions.pagibig.employee', 36.36)
        ->assertJsonPath('data.deductions.withholding_tax', 249.37)
        ->assertJsonPath('data.net_pay', 6221.09)
        ->assertJsonPath('data.employer.total_contributions', 853.18)
        ->assertJsonPath('data.employer.total_cost', 7853.18);
});

it('includes exempt thirteenth month pay in gross pay but not taxable income', function () {
    $this->postJson('/api/calculators/take-home-pay', [
        'period' => 'monthly',
        'basic_salary' => 30000,
        'thirteenth_month' => 100000,
    ])->assertOk()
        ->assertJsonPath('data.gross_pay', 130000)
        ->assertJsonPath('data.non_taxable_benefits', 90000)
        ->assertJsonPath('data.taxable_compensation_income', 37550)
        ->assertJsonPath('data.deductions.withholding_tax', 2718.4)
        ->assertJsonPath('data.deductions.total_employee', 5168.4)
        ->assertJsonPath('data.net_pay', 124831.6);
});

it('returns zero contributions and net pay when there is no compensation', function () {
    $this->postJson('/api/calculators/take-home-pay', [
        'period' => 'monthly',
        'basic_salary' => 0,
    ])->assertOk()
        ->assertJsonPath('data.deductions.social_insurance.employee', 0)
        ->assertJsonPath('data.deductions.philhealth.employee', 0)
        ->assertJsonPath('data.deductions.pagibig.employee', 0)
        ->assertJsonPath('data.net_pay', 0);
});

it('uses GSIS instead of SSS for the government sector', function () {
    $this->postJson('/api/calculators/take-home-pay', [
        'sector' => 'Government',
        'period' => 'monthly',
        'basic_salary' => 30000,
    ])->assertOk()
        ->assertJsonPath('data.sector', 'Government')
        ->assertJsonPath('data.taxable_compensation_income', 26350)
        ->assertJsonPath('data.deductions.social_insurance.agency', 'GSIS')
        ->assertJsonPath('data.deductions.social_insurance.employee', 2700)
        ->assertJsonPath('data.deductions.social_insurance.employer', 3600)
        ->assertJsonPath('data.deductions.philhealth.employee', 750)
        ->assertJsonPath('data.deductions.pagibig.employee', 200)
        ->assertJsonPath('data.deductions.withholding_tax', 827.55)
        ->assertJsonPath('data.deductions.total_employee', 4477.55)
        ->assertJsonPath('data.net_pay', 25522.45)
        ->assertJsonPath('data.employer.total_contributions', 4550)
        ->assertJsonPath('data.employer.total_cost', 34550);
});

it('prorates GSIS contributions for a semi-monthly government period', function () {
    $this->postJson('/api/calculators/take-home-pay', [
        'sector' => 'government',
        'period' => 'semi-monthly',
        'basic_salary' => 15000,
    ])->assertOk()
        ->assertJsonPath('data.sector', 'Government')
        ->assertJsonPath('data.taxable_compensation_income', 13175)
        ->assertJsonPath('data.deductions.social_insurance.agency', 'GSIS')
        ->assertJsonPath('data.deductions.social_insurance.employee', 1350)
        ->assertJsonPath('data.deductions.social_insurance.employer', 1800)
        ->assertJsonPath('data.deductions.withholding_tax', 413.7)
        ->assertJsonPath('data.deductions.total_employee', 2238.7)
        ->assertJsonPath('data.net_pay', 12761.3);
});

it('defaults to the private sector', function () {
    $this->postJson('/api/calculators/take-home-pay', [
        'period' => 'monthly',
        'basic_salary' => 30000,
    ])->assertOk()
        ->assertJsonPath('data.sector', 'Private')
        ->assertJsonPath('data.deductions.social_insurance.agency', 'SSS');
});

it('rejects invalid take-home pay input', function () {
    $this->postJson('/api/calculators/take-home-pay', [
        'basic_salary' => 30000,
    ])->assertUnprocessable()
        ->assertJsonValidationErrors('period');

    $this->postJson('/api/calculators/take-home-pay', [
        'period' => 'monthly',
        'basic_salary' => -10,
    ])->assertUnprocessable()
        ->assertJsonValidationErrors('basic_salary');

    $this->postJson('/api/calculators/take-home-pay', [
        'sector' => 'Foo',
        'period' => 'monthly',
        'basic_salary' => 30000,
    ])->assertUnprocessable()
        ->assertJsonValidationErrors('sector');
});
