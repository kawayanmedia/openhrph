<?php

use App\Services\Statutory\Philippines\PagIbig\PagIbigContributionService;
use App\Services\Statutory\Philippines\PagIbig\PagIbigSchedule;
use App\Support\Money;

it('uses the lower employee rate at or below 1,500', function () {
    $contribution = (new PagIbigContributionService(PagIbigSchedule::default()))
        ->compute(Money::fromPesos(1500));

    expect($contribution->base)->toBe(Money::fromPesos(1500))
        ->and($contribution->employee)->toBe(Money::fromPesos(15))
        ->and($contribution->employer)->toBe(Money::fromPesos(30));
});

it('charges 2% above the low-income threshold', function () {
    $contribution = (new PagIbigContributionService(PagIbigSchedule::default()))
        ->compute(Money::fromPesos(5000));

    expect($contribution->employee)->toBe(Money::fromPesos(100))
        ->and($contribution->employer)->toBe(Money::fromPesos(100));
});

it('caps the fund salary at 10,000', function () {
    $contribution = (new PagIbigContributionService(PagIbigSchedule::default()))
        ->compute(Money::fromPesos(80000));

    expect($contribution->base)->toBe(Money::fromPesos(10000))
        ->and($contribution->employee)->toBe(Money::fromPesos(200))
        ->and($contribution->employer)->toBe(Money::fromPesos(200));
});
