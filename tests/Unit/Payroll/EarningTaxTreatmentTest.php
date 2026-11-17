<?php

use App\Services\Payroll\Computation\BenefitFlags;
use App\Services\Payroll\Computation\Computer;
use App\Services\Payroll\Computation\Config;
use App\Services\Payroll\Computation\DeMinimisBenefit;
use App\Services\Payroll\Computation\Earning;
use App\Services\Payroll\Computation\Employee;

function taxAuditConfig(): Config
{
    return new Config(
        frequency: Config::FREQUENCY_SEMI_MONTHLY,
        daysPerYear: 261,
        hoursPerDay: 8,
        deMinimis: [new DeMinimisBenefit('rice', 250000, DeMinimisBenefit::BASIS_MONTHLY)],
        payrollDate: '2026-03-15',
    );
}

function taxAuditEmployee(bool $deMinimis = true): Employee
{
    return new Employee(
        id: 1,
        monthlyBasic: 3000000,
        dailyRate: 114943,
        benefits: new BenefitFlags(deMinimis: $deMinimis),
    );
}

it('caps a de minimis allowance at its monthly ceiling and reports the excess', function () {
    $earning = Earning::allowance('rice', 'Rice Subsidy', 300000, taxable: false);

    $result = Computer::earningTaxTreatment(taxAuditConfig(), taxAuditEmployee(), $earning);

    expect($result['taxable'])->toBeTrue()
        ->and($result['ceiling'])->toBe(250000)
        ->and($result['exempt'])->toBe(250000)
        ->and($result['excess'])->toBe(50000)
        ->and($result['basis'])->toBe(DeMinimisBenefit::BASIS_MONTHLY)
        ->and($result['key'])->toBe('rice|2026-03')
        ->and($result['source'])->toBe('statutory de minimis · year-to-date');
});

it('uses only the ceiling still available for the month', function () {
    $earning = Earning::allowance('rice', 'Rice Subsidy', 300000, taxable: false);

    $result = Computer::earningTaxTreatment(
        taxAuditConfig(),
        taxAuditEmployee(),
        $earning,
        ['rice|2026-03' => 200000],
    );

    expect($result['ceiling'])->toBe(50000)
        ->and($result['exempt'])->toBe(50000)
        ->and($result['excess'])->toBe(250000);
});

it('exempts nothing once the period ceiling is exhausted', function () {
    $earning = Earning::allowance('rice', 'Rice Subsidy', 300000, taxable: false);

    $result = Computer::earningTaxTreatment(
        taxAuditConfig(),
        taxAuditEmployee(),
        $earning,
        ['rice|2026-03' => 250000],
    );

    expect($result['ceiling'])->toBe(0)
        ->and($result['exempt'])->toBe(0)
        ->and($result['excess'])->toBe(300000);
});

it('exempts the whole allowance when the employee is not entitled to de minimis benefits', function () {
    $earning = Earning::allowance('rice', 'Rice Subsidy', 300000, taxable: false);

    $result = Computer::earningTaxTreatment(taxAuditConfig(), taxAuditEmployee(deMinimis: false), $earning);

    expect($result['taxable'])->toBeFalse()
        ->and($result['ceiling'])->toBeNull()
        ->and($result['exempt'])->toBe(300000)
        ->and($result['excess'])->toBe(0)
        ->and($result['source'])->toContain('flag off');
});

it('leaves a taxable allowance fully taxable with no ceiling', function () {
    $earning = Earning::allowance('TRANSPORTATION', 'Transportation Allowance', 300000);

    $result = Computer::earningTaxTreatment(taxAuditConfig(), taxAuditEmployee(), $earning);

    expect($result['taxable'])->toBeTrue()
        ->and($result['ceiling'])->toBeNull()
        ->and($result['exempt'])->toBe(0)
        ->and($result['excess'])->toBe(0);
});

it('applies the catalogue maximum to a non-taxable bonus', function () {
    $earning = new Earning(
        code: '13TH MONTH PAY',
        name: '13th Month Pay',
        kind: Earning::KIND_BONUS,
        amount: 10000000,
        taxable: false,
        nonTaxableCeiling: 9000000,
    );

    $result = Computer::earningTaxTreatment(taxAuditConfig(), taxAuditEmployee(), $earning);

    expect($result['taxable'])->toBeTrue()
        ->and($result['ceiling'])->toBe(9000000)
        ->and($result['exempt'])->toBe(9000000)
        ->and($result['excess'])->toBe(1000000)
        ->and($result['source'])->toBe('compensations.maximum_non_tax');
});

it('treats a non-taxable compensation as fully exempt without a ceiling', function () {
    $earning = new Earning(
        code: 'NON TAXABLE COMPENSATION',
        name: 'Non-taxable compensation',
        kind: Earning::KIND_COMPENSATION,
        amount: 500000,
        taxable: false,
    );

    $result = Computer::earningTaxTreatment(taxAuditConfig(), taxAuditEmployee(), $earning);

    expect($result['taxable'])->toBeFalse()
        ->and($result['ceiling'])->toBeNull()
        ->and($result['exempt'])->toBe(500000)
        ->and($result['excess'])->toBe(0);
});
