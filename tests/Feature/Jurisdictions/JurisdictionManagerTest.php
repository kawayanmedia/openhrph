<?php

use App\Jurisdictions\Generic\GenericJurisdiction;
use App\Jurisdictions\Philippines\PhilippinesJurisdiction;
use App\Jurisdictions\Support\JurisdictionManager;
use App\Support\Installation\Installation;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('resolves the philippine jurisdiction by default', function () {
    $manager = app(JurisdictionManager::class);

    expect($manager->current())->toBeInstanceOf(PhilippinesJurisdiction::class)
        ->and($manager->current()->code())->toBe('PH')
        ->and($manager->current()->statutoryDriver()->code())->toBe('ph');
});

it('resolves an unknown country to the generic jurisdiction and never to PH', function () {
    config()->set('payroll.countries.ZZ', [
        'name' => 'Testland',
        'currency' => 'USD',
        'currency_symbol' => '$',
        'locale' => 'en',
        'timezone' => 'UTC',
        'statutory_driver' => 'generic',
    ]);

    $jurisdiction = app(JurisdictionManager::class)->for('ZZ');

    expect($jurisdiction)->toBeInstanceOf(GenericJurisdiction::class)
        ->and($jurisdiction->code())->toBe('ZZ')
        ->and($jurisdiction->statutoryDriver()->code())->toBe('generic')
        ->and($jurisdiction->payrollPolicy()->minimumWageTaxExempt())->toBeFalse()
        ->and($jurisdiction->payrollPolicy()->thirteenthMonthExemptCentavos())->toBe(0);
});

it('exposes the policy snapshot and version for payroll', function () {
    expect(Installation::policyVersion())->toBe('PH:ph:v1');

    $policy = Installation::jurisdiction()->payrollPolicy()->toArray();

    expect($policy)->toHaveKeys([
        'day_type_multipliers',
        'overtime_multiplier',
        'night_differential_rate',
        'thirteenth_month_exempt_centavos',
    ]);
});

it('lists registered jurisdictions', function () {
    $this->artisan('openhr:jurisdictions')
        ->expectsOutputToContain('PH')
        ->assertExitCode(0);
});

it('checks a jurisdiction contract', function () {
    $this->artisan('openhr:jurisdiction:check', ['code' => 'PH'])
        ->assertExitCode(0);
});
