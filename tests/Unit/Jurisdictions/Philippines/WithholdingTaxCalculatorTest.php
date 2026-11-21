<?php

use App\Jurisdictions\Philippines\Calculators\WithholdingTaxCalculator;
use App\Jurisdictions\Philippines\Calculators\WithholdingTaxInput;
use App\Jurisdictions\Philippines\Statutory\Bir\TaxPeriod;

it('taxes gross compensation when no statutory deductions are supplied', function () {
    $result = (new WithholdingTaxCalculator)->compute(
        new WithholdingTaxInput(period: TaxPeriod::MONTHLY, basicSalary: 3_000_000),
    );

    expect($result->taxableCompensation)->toBe(3_000_000)
        ->and($result->withholdingTax)->toBe(137_505);
});

it('reduces the taxable base by mandatory employee contributions', function () {
    $result = (new WithholdingTaxCalculator)->compute(
        new WithholdingTaxInput(period: TaxPeriod::MONTHLY, basicSalary: 3_000_000),
        245_000,
    );

    expect($result->statutoryDeductions)->toBe(245_000)
        ->and($result->taxableCompensation)->toBe(2_755_000)
        ->and($result->withholdingTax)->toBe(100_755);
});

it('reduces the taxable base for a government employee', function () {
    $result = (new WithholdingTaxCalculator)->compute(
        new WithholdingTaxInput(period: TaxPeriod::MONTHLY, basicSalary: 3_000_000),
        365_000,
    );

    expect($result->taxableCompensation)->toBe(2_635_000)
        ->and($result->withholdingTax)->toBe(82_755);
});

it('never taxes below zero', function () {
    $result = (new WithholdingTaxCalculator)->compute(
        new WithholdingTaxInput(period: TaxPeriod::MONTHLY, basicSalary: 1_000_000),
        2_000_000,
    );

    expect($result->taxableCompensation)->toBe(0)
        ->and($result->withholdingTax)->toBe(0);
});
