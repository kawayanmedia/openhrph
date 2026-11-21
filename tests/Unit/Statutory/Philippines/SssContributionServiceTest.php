<?php

use App\Jurisdictions\Philippines\Statutory\Sss\SssContributionService;
use App\Jurisdictions\Philippines\Statutory\Sss\SssSchedule;
use App\Support\Money;

it('charges the first salary credit for salaries in the lowest band', function () {
    $contribution = (new SssContributionService(SssSchedule::preset2025()))
        ->compute(Money::fromPesos(4249.99));

    expect($contribution->msc)->toBe(Money::fromPesos(5000))
        ->and($contribution->employee)->toBe(Money::fromPesos(250))
        ->and($contribution->employer)->toBe(Money::fromPesos(500))
        ->and($contribution->ec)->toBe(Money::fromPesos(10))
        ->and($contribution->wispEmployee)->toBe(0)
        ->and($contribution->employeeShare())->toBe(Money::fromPesos(250))
        ->and($contribution->employerShare())->toBe(Money::fromPesos(510))
        ->and($contribution->total())->toBe(Money::fromPesos(760));
});

it('caps the regular salary credit at 20,000 and moves the excess to WISP', function () {
    $contribution = (new SssContributionService(SssSchedule::preset2025()))
        ->compute(Money::fromPesos(25000));

    expect($contribution->msc)->toBe(Money::fromPesos(25000))
        ->and($contribution->employee)->toBe(Money::fromPesos(1000))
        ->and($contribution->employer)->toBe(Money::fromPesos(2000))
        ->and($contribution->wispEmployee)->toBe(Money::fromPesos(250))
        ->and($contribution->wispEmployer)->toBe(Money::fromPesos(500))
        ->and($contribution->employeeShare())->toBe(Money::fromPesos(1250))
        ->and($contribution->employerShare())->toBe(Money::fromPesos(2530));
});

it('holds the 35,000 salary credit ceiling for higher salaries', function () {
    $service = new SssContributionService(SssSchedule::preset2025());

    $atCeiling = $service->compute(Money::fromPesos(35000));
    $above = $service->compute(Money::fromPesos(120000));

    expect($atCeiling->msc)->toBe(Money::fromPesos(35000))
        ->and($above->msc)->toBe(Money::fromPesos(35000))
        ->and($above->employee)->toBe($atCeiling->employee)
        ->and($above->employeeShare())->toBe(Money::fromPesos(1750))
        ->and($above->employerShare())->toBe(Money::fromPesos(3530));
});

it('raises the employer EC contribution from a 15,000 salary credit', function () {
    $service = new SssContributionService(SssSchedule::preset2025());

    expect($service->compute(Money::fromPesos(14499.99))->msc)->toBe(Money::fromPesos(14500))
        ->and($service->compute(Money::fromPesos(14499.99))->ec)->toBe(Money::fromPesos(10))
        ->and($service->compute(Money::fromPesos(14750))->ec)->toBe(Money::fromPesos(30));
});

it('rejects a salary that no bracket covers', function () {
    expect(fn () => (new SssContributionService([]))->compute(Money::fromPesos(10000)))
        ->toThrow(RuntimeException::class, 'sss: no statutory bracket matches the salary');
});
