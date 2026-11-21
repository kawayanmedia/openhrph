<?php

use App\Enums\Sector;
use App\Jurisdictions\Philippines\Calculators\StatutoryContributionCalculator;
use App\Jurisdictions\Philippines\Calculators\WithholdingTaxInput;
use App\Jurisdictions\Philippines\Statutory\Bir\TaxPeriod;

it('computes private monthly contributions', function () {
    $result = (new StatutoryContributionCalculator)->compute(
        new WithholdingTaxInput(period: TaxPeriod::MONTHLY, basicSalary: 3_000_000),
        Sector::Private,
    );

    expect($result->agency())->toBe('SSS')
        ->and($result->socialInsuranceEmployee)->toBe(150_000)
        ->and($result->socialInsuranceEmployer)->toBe(303_000)
        ->and($result->philHealthEmployee)->toBe(75_000)
        ->and($result->philHealthEmployer)->toBe(75_000)
        ->and($result->pagIbigEmployee)->toBe(20_000)
        ->and($result->pagIbigEmployer)->toBe(20_000)
        ->and($result->totalEmployee())->toBe(245_000)
        ->and($result->totalEmployer())->toBe(398_000);
});

it('computes government monthly contributions with GSIS', function () {
    $result = (new StatutoryContributionCalculator)->compute(
        new WithholdingTaxInput(period: TaxPeriod::MONTHLY, basicSalary: 3_000_000),
        Sector::Government,
    );

    expect($result->agency())->toBe('GSIS')
        ->and($result->socialInsuranceEmployee)->toBe(270_000)
        ->and($result->socialInsuranceEmployer)->toBe(360_000)
        ->and($result->totalEmployee())->toBe(365_000)
        ->and($result->totalEmployer())->toBe(455_000);
});

it('prorates contributions to a semi-monthly period', function () {
    $result = (new StatutoryContributionCalculator)->compute(
        new WithholdingTaxInput(period: TaxPeriod::SEMI_MONTHLY, basicSalary: 1_500_000),
        Sector::Private,
    );

    expect($result->socialInsuranceEmployee)->toBe(75_000)
        ->and($result->socialInsuranceEmployer)->toBe(151_500)
        ->and($result->philHealthEmployee)->toBe(37_500)
        ->and($result->pagIbigEmployee)->toBe(10_000)
        ->and($result->totalEmployee())->toBe(122_500);
});

it('returns zero contributions without basic salary', function () {
    $result = (new StatutoryContributionCalculator)->compute(
        new WithholdingTaxInput(period: TaxPeriod::MONTHLY, basicSalary: 0),
        Sector::Private,
    );

    expect($result->totalEmployee())->toBe(0)
        ->and($result->totalEmployer())->toBe(0);
});
