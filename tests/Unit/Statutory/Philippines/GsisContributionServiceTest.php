<?php

use App\Services\Statutory\Philippines\Gsis\GsisContributionService;
use App\Services\Statutory\Philippines\Gsis\GsisSchedule;
use App\Support\Money;

it('charges 9% to the member and 12% to the employer', function () {
    $contribution = (new GsisContributionService(GsisSchedule::default()))
        ->compute(Money::fromPesos(40000));

    expect($contribution->base)->toBe(Money::fromPesos(40000))
        ->and($contribution->employee)->toBe(Money::fromPesos(3600))
        ->and($contribution->employer)->toBe(Money::fromPesos(4800))
        ->and($contribution->total())->toBe(Money::fromPesos(8400));
});

it('has no salary ceiling', function () {
    $contribution = (new GsisContributionService(GsisSchedule::default()))
        ->compute(Money::fromPesos(100000));

    expect($contribution->employee)->toBe(Money::fromPesos(9000))
        ->and($contribution->employer)->toBe(Money::fromPesos(12000));
});

it('never produces a negative premium', function () {
    $contribution = (new GsisContributionService(GsisSchedule::default()))
        ->compute(-Money::fromPesos(500));

    expect($contribution->base)->toBe(0)
        ->and($contribution->employee)->toBe(0)
        ->and($contribution->employer)->toBe(0);
});

it('bills the member 2% on compensation above the maximum AMC limit', function () {
    $schedule = new GsisSchedule(
        employeeRate: 0.09,
        employerRate: 0.12,
        maxAmcLimit: Money::fromPesos(20000),
        aboveLimitEmployeeRate: 0.02,
    );

    $contribution = (new GsisContributionService($schedule))->compute(Money::fromPesos(30000));

    expect($contribution->employee)->toBe(Money::fromPesos(2000))
        ->and($contribution->employer)->toBe(Money::fromPesos(3600));
});

it('applies the regular rate at the maximum AMC limit', function () {
    $schedule = new GsisSchedule(
        employeeRate: 0.09,
        employerRate: 0.12,
        maxAmcLimit: Money::fromPesos(20000),
        aboveLimitEmployeeRate: 0.02,
    );

    $contribution = (new GsisContributionService($schedule))->compute(Money::fromPesos(20000));

    expect($contribution->employee)->toBe(Money::fromPesos(1800));
});

it('charges 3% each side for the judiciary and constitutional commissions', function () {
    $contribution = (new GsisContributionService(GsisSchedule::judiciaryLifeInsurance()))
        ->compute(Money::fromPesos(30000));

    expect($contribution->employee)->toBe(Money::fromPesos(900))
        ->and($contribution->employer)->toBe(Money::fromPesos(900));
});

it('round-trips the schedule through an array', function () {
    $schedule = new GsisSchedule(
        employeeRate: 0.09,
        employerRate: 0.12,
        maxAmcLimit: Money::fromPesos(20000),
        aboveLimitEmployeeRate: 0.02,
        lifeInsuranceOnly: false,
    );

    expect(GsisSchedule::fromArray($schedule->toArray())->toArray())->toBe($schedule->toArray());
});
