<?php

use App\Services\Government\PhilHealth\PhilHealthContributionService;
use App\Services\Government\PhilHealth\PhilHealthSchedule;
use App\Support\Money;

it('charges the premium on the floor for low salaries', function () {
    $contribution = (new PhilHealthContributionService(PhilHealthSchedule::default()))
        ->compute(Money::fromPesos(5000));

    expect($contribution->base)->toBe(Money::fromPesos(10000))
        ->and($contribution->premium)->toBe(Money::fromPesos(500))
        ->and($contribution->employee)->toBe(Money::fromPesos(250))
        ->and($contribution->employer)->toBe(Money::fromPesos(250));
});

it('splits the premium equally inside the schedule', function () {
    $contribution = (new PhilHealthContributionService(PhilHealthSchedule::default()))
        ->compute(Money::fromPesos(25000));

    expect($contribution->base)->toBe(Money::fromPesos(25000))
        ->and($contribution->premium)->toBe(Money::fromPesos(1250))
        ->and($contribution->employee)->toBe(Money::fromPesos(625))
        ->and($contribution->employer)->toBe(Money::fromPesos(625));
});

it('caps the premium at the ceiling', function () {
    $contribution = (new PhilHealthContributionService(PhilHealthSchedule::default()))
        ->compute(Money::fromPesos(250000));

    expect($contribution->base)->toBe(Money::fromPesos(100000))
        ->and($contribution->premium)->toBe(Money::fromPesos(5000))
        ->and($contribution->employee)->toBe(Money::fromPesos(2500))
        ->and($contribution->employer)->toBe(Money::fromPesos(2500));
});

it('keeps both halves equal to the premium when it has an odd centavo', function () {
    $contribution = (new PhilHealthContributionService(PhilHealthSchedule::default()))
        ->compute(2500010);

    expect($contribution->premium)->toBe(125001)
        ->and($contribution->employee)->toBe(62501)
        ->and($contribution->employer)->toBe(62500)
        ->and($contribution->employee + $contribution->employer)->toBe($contribution->premium);
});
