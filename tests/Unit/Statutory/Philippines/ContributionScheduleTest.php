<?php

use App\Services\Statutory\Philippines\ContributionSchedule;
use App\Services\Statutory\Philippines\PayFrequency;

it('normalises the stored schedule variants', function () {
    expect(ContributionSchedule::fromString('everypay'))->toBe(ContributionSchedule::EVERY_PAY)
        ->and(ContributionSchedule::fromString('0'))->toBe(ContributionSchedule::EVERY_PAY)
        ->and(ContributionSchedule::fromString('1'))->toBe(ContributionSchedule::FIRST_PAY)
        ->and(ContributionSchedule::fromString('first-pay'))->toBe(ContributionSchedule::FIRST_PAY)
        ->and(ContributionSchedule::fromString('2'))->toBe(ContributionSchedule::LAST_PAY)
        ->and(ContributionSchedule::fromString('last pay'))->toBe(ContributionSchedule::LAST_PAY)
        ->and(ContributionSchedule::fromString(null))->toBe(ContributionSchedule::EVERY_PAY)
        ->and(ContributionSchedule::fromString('unexpected'))->toBe(ContributionSchedule::EVERY_PAY);
});

it('splits a monthly amount across a semi-monthly run', function () {
    expect(ContributionSchedule::EVERY_PAY->divisor(PayFrequency::SEMI_MONTHLY, 'first_pay'))->toBe(2)
        ->and(ContributionSchedule::EVERY_PAY->divisor(PayFrequency::MONTHLY, ''))->toBe(1);
});

it('takes a scheduled deduction only on its own cut-off', function () {
    expect(ContributionSchedule::FIRST_PAY->divisor(PayFrequency::SEMI_MONTHLY, 'first_pay'))->toBe(1)
        ->and(ContributionSchedule::FIRST_PAY->divisor(PayFrequency::SEMI_MONTHLY, 'last_pay'))->toBe(0)
        ->and(ContributionSchedule::LAST_PAY->divisor(PayFrequency::SEMI_MONTHLY, 'last_pay'))->toBe(1)
        ->and(ContributionSchedule::LAST_PAY->divisor(PayFrequency::MONTHLY, ''))->toBe(0);
});

it('maps the payroll group frequency values', function () {
    expect(PayFrequency::fromString('month'))->toBe(PayFrequency::MONTHLY)
        ->and(PayFrequency::fromString('Monthly'))->toBe(PayFrequency::MONTHLY)
        ->and(PayFrequency::fromString('semi-monthly'))->toBe(PayFrequency::SEMI_MONTHLY)
        ->and(PayFrequency::fromString(null))->toBe(PayFrequency::SEMI_MONTHLY)
        ->and(PayFrequency::MONTHLY->periodsPerMonth())->toBe(1)
        ->and(PayFrequency::MONTHLY->periodsPerYear())->toBe(12)
        ->and(PayFrequency::SEMI_MONTHLY->periodsPerMonth())->toBe(2)
        ->and(PayFrequency::SEMI_MONTHLY->periodsPerYear())->toBe(24);
});
