<?php

use App\Jurisdictions\Philippines\Statutory\Gsis\GsisSchedule;
use App\Jurisdictions\Philippines\Statutory\Schemes\GsisScheme;
use App\Jurisdictions\Philippines\Statutory\Schemes\SchemeResolver;
use App\Jurisdictions\Philippines\Statutory\Schemes\SssScheme;
use App\Jurisdictions\Philippines\Statutory\Sss\SssSchedule;
use App\Support\Money;

it('computes the SSS split from the effective schedule', function () {
    $split = (new SssScheme(SssSchedule::preset2025()))->compute(Money::fromPesos(20000));

    expect($split->employee)->toBe(Money::fromPesos(1000))
        ->and($split->employer)->toBe(Money::fromPesos(2000))
        ->and($split->employerEc)->toBe(Money::fromPesos(30))
        ->and($split->providentEmployee)->toBe(0)
        ->and($split->providentEmployer)->toBe(0);
});

it('computes the GSIS split with no salary credit', function () {
    $split = (new GsisScheme(GsisSchedule::default()))->compute(Money::fromPesos(20000));

    expect($split->employee)->toBe(Money::fromPesos(1800))
        ->and($split->employer)->toBe(Money::fromPesos(2400))
        ->and($split->employerEc)->toBe(0);
});

it('resolves the scheme from the agency code', function () {
    $sss = SchemeResolver::make('sss', SssSchedule::preset2025(), GsisSchedule::default());
    $gsis = SchemeResolver::make('GSIS', SssSchedule::preset2025(), GsisSchedule::default());

    expect($sss)->toBeInstanceOf(SssScheme::class)
        ->and($sss->identifierField())->toBe('sss_number')
        ->and($gsis)->toBeInstanceOf(GsisScheme::class)
        ->and($gsis->identifierField())->toBe('gsis_number');
});
