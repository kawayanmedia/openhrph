<?php

use App\Services\Government\Bir\WithholdingTaxService;
use App\Services\Government\Bir\WithholdingTaxTable;
use App\Services\Government\PayFrequency;
use App\Support\Money;

it('withholds nothing below the semi-monthly exemption', function () {
    $service = new WithholdingTaxService(WithholdingTaxTable::train(PayFrequency::SEMI_MONTHLY));

    expect($service->compute(Money::fromPesos(10416.99)))->toBe(0);
});

it('applies the marginal rate to the excess over the bracket floor', function () {
    $service = new WithholdingTaxService(WithholdingTaxTable::train(PayFrequency::SEMI_MONTHLY));

    expect($service->compute(Money::fromPesos(11417)))->toBe(Money::fromPesos(150));
});

it('adds the base tax of the bracket', function () {
    $service = new WithholdingTaxService(WithholdingTaxTable::train(PayFrequency::SEMI_MONTHLY));

    expect($service->compute(Money::fromPesos(16667)))->toBe(Money::fromPesos(937.50));
});

it('uses the monthly table for a monthly frequency', function () {
    $service = new WithholdingTaxService(WithholdingTaxTable::train(PayFrequency::MONTHLY));

    expect($service->compute(Money::fromPesos(20832.99)))->toBe(0)
        ->and($service->compute(Money::fromPesos(30000)))->toBe(Money::fromPesos(1375.05));
});

it('applies the top rate with no upper bound', function () {
    $service = new WithholdingTaxService(WithholdingTaxTable::train(PayFrequency::SEMI_MONTHLY));

    expect($service->compute(Money::fromPesos(500000)))->toBe(Money::fromPesos(150104.15));
});

it('rejects a taxable amount that no bracket covers', function () {
    expect(fn () => (new WithholdingTaxService([]))->compute(Money::fromPesos(10000)))
        ->toThrow(RuntimeException::class, 'tax: no statutory bracket matches the salary');
});
