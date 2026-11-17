<?php

use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('computes monthly withholding tax', function () {
    $this->postJson('/api/calculators/withholding-tax', [
        'period' => 'monthly',
        'basic_salary' => 30000,
    ])->assertOk()
        ->assertJsonPath('data.period', 'monthly')
        ->assertJsonPath('data.regular_compensation', 30000)
        ->assertJsonPath('data.gross_compensation_income', 30000)
        ->assertJsonPath('data.taxable_compensation_income', 30000)
        ->assertJsonPath('data.withholding_tax', 1375.05)
        ->assertJsonPath('data.effective_tax_rate', 0.0458);
});

it('computes semi-monthly withholding tax', function () {
    $this->postJson('/api/calculators/withholding-tax', [
        'period' => 'semi_monthly',
        'basic_salary' => 11417,
    ])->assertOk()
        ->assertJsonPath('data.period', 'semi-monthly')
        ->assertJsonPath('data.withholding_tax', 150);
});

it('computes daily and weekly withholding tax', function () {
    $this->postJson('/api/calculators/withholding-tax', [
        'period' => 'daily',
        'basic_salary' => 1000,
    ])->assertOk()
        ->assertJsonPath('data.withholding_tax', 47.25);

    $this->postJson('/api/calculators/withholding-tax', [
        'period' => 'weekly',
        'basic_salary' => 5000,
    ])->assertOk()
        ->assertJsonPath('data.withholding_tax', 28.8);
});

it('computes annual withholding tax', function () {
    $this->postJson('/api/calculators/withholding-tax', [
        'period' => 'annual',
        'basic_salary' => 300000,
    ])->assertOk()
        ->assertJsonPath('data.withholding_tax', 7500);
});

it('adds the thirteenth month excess over ninety thousand to taxable income', function () {
    $this->postJson('/api/calculators/withholding-tax', [
        'period' => 'monthly',
        'basic_salary' => 30000,
        'thirteenth_month' => 100000,
    ])->assertOk()
        ->assertJsonPath('data.non_taxable_benefits', 90000)
        ->assertJsonPath('data.supplementary_compensation', 10000)
        ->assertJsonPath('data.taxable_compensation_income', 40000)
        ->assertJsonPath('data.withholding_tax', 3208.4);
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
});

it('computes monthly statutory deductions and net pay', function () {
    $this->postJson('/api/calculators/take-home-pay', [
        'period' => 'monthly',
        'basic_salary' => 30000,
    ])->assertOk()
        ->assertJsonPath('data.gross_pay', 30000)
        ->assertJsonPath('data.deductions.social_insurance.employee', 1500)
        ->assertJsonPath('data.deductions.social_insurance.employer', 3030)
        ->assertJsonPath('data.deductions.philhealth.employee', 750)
        ->assertJsonPath('data.deductions.pagibig.employee', 200)
        ->assertJsonPath('data.deductions.withholding_tax', 1375.05)
        ->assertJsonPath('data.deductions.total_employee', 3825.05)
        ->assertJsonPath('data.net_pay', 26174.95)
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
        ->assertJsonPath('data.deductions.social_insurance.employee', 750)
        ->assertJsonPath('data.deductions.philhealth.employee', 375)
        ->assertJsonPath('data.deductions.pagibig.employee', 100)
        ->assertJsonPath('data.deductions.withholding_tax', 687.45)
        ->assertJsonPath('data.deductions.total_employee', 1912.45)
        ->assertJsonPath('data.net_pay', 13087.55)
        ->assertJsonPath('data.employer.total_contributions', 1990)
        ->assertJsonPath('data.employer.total_cost', 16990);
});

it('prorates statutory contributions for a weekly period', function () {
    $this->postJson('/api/calculators/take-home-pay', [
        'period' => 'weekly',
        'basic_salary' => 7000,
    ])->assertOk()
        ->assertJsonPath('data.deductions.social_insurance.employee', 318.18)
        ->assertJsonPath('data.deductions.philhealth.employee', 175)
        ->assertJsonPath('data.deductions.pagibig.employee', 36.36)
        ->assertJsonPath('data.deductions.withholding_tax', 328.8)
        ->assertJsonPath('data.net_pay', 6141.66)
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
        ->assertJsonPath('data.taxable_compensation_income', 40000)
        ->assertJsonPath('data.deductions.withholding_tax', 3208.4)
        ->assertJsonPath('data.deductions.total_employee', 5658.4)
        ->assertJsonPath('data.net_pay', 124341.6);
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
        ->assertJsonPath('data.deductions.social_insurance.agency', 'GSIS')
        ->assertJsonPath('data.deductions.social_insurance.employee', 2700)
        ->assertJsonPath('data.deductions.social_insurance.employer', 3600)
        ->assertJsonPath('data.deductions.philhealth.employee', 750)
        ->assertJsonPath('data.deductions.pagibig.employee', 200)
        ->assertJsonPath('data.deductions.withholding_tax', 1375.05)
        ->assertJsonPath('data.deductions.total_employee', 5025.05)
        ->assertJsonPath('data.net_pay', 24974.95)
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
        ->assertJsonPath('data.deductions.social_insurance.agency', 'GSIS')
        ->assertJsonPath('data.deductions.social_insurance.employee', 1350)
        ->assertJsonPath('data.deductions.social_insurance.employer', 1800)
        ->assertJsonPath('data.deductions.total_employee', 2512.45)
        ->assertJsonPath('data.net_pay', 12487.55);
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
