<?php

use App\Models\Company;
use App\Models\Payroll;
use App\Support\Installation\Installation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

it('marks every company and stores the installation settings', function () {
    Company::factory()->create(['type' => 'Private']);
    Company::factory()->create(['type' => 'Government']);
    Company::factory()->create(['type' => 'Non-Profit']);

    $this->artisan('openhr:install', [
        '--sector' => 'government',
        '--country' => 'PH',
        '--skip-statutory' => true,
    ])->assertExitCode(0);

    expect(Installation::sector())->toBe('Government')
        ->and(Installation::countryCode())->toBe('PH')
        ->and(Installation::currencyCode())->toBe('PHP')
        ->and(Installation::installed())->toBeTrue();

    expect(DB::table('companies')->pluck('type')->unique()->all())->toBe(['Government']);
});

it('accepts the private sector and seeds the deduction templates', function () {
    $company = Company::factory()->create(['type' => 'Government']);

    $this->artisan('openhr:install', [
        '--sector' => 'private',
        '--country' => 'PH',
        '--skip-statutory' => true,
    ])->assertExitCode(0);

    expect($company->fresh()->type)->toBe('Private')
        ->and(DB::table('deductions')->where('company_id', $company->getKey())->where('code', 'union_dues')->exists())->toBeTrue();
});

it('forces companies created after installation onto the chosen sector', function () {
    $this->artisan('openhr:install', [
        '--sector' => 'Government',
        '--country' => 'PH',
        '--skip-statutory' => true,
    ])->assertExitCode(0);

    $company = Company::factory()->create(['type' => 'Private']);

    expect($company->fresh()->type)->toBe('Government');
});

it('refuses a second run without force', function () {
    $this->artisan('openhr:install', [
        '--sector' => 'Private',
        '--country' => 'PH',
        '--skip-statutory' => true,
    ])->assertExitCode(0);

    $this->artisan('openhr:install', [
        '--sector' => 'Government',
        '--country' => 'PH',
        '--skip-statutory' => true,
    ])->assertExitCode(1);

    expect(Installation::sector())->toBe('Private');
});

it('rejects an unknown country', function () {
    $this->artisan('openhr:install', [
        '--sector' => 'Private',
        '--country' => 'ZZ',
        '--skip-statutory' => true,
    ])->assertExitCode(1);

    expect(Installation::installed())->toBeFalse();
});

it('requires the sector and country when interaction is disabled', function () {
    $this->artisan('openhr:install', ['--skip-statutory' => true, '--no-interaction' => true])->assertExitCode(1);

    expect(Installation::installed())->toBeFalse();
});

it('allows a country change with force before any payroll is processed', function () {
    config()->set('payroll.countries.XX', [
        'name' => 'Testland',
        'currency' => 'USD',
        'currency_symbol' => '$',
        'locale' => 'en',
        'timezone' => 'UTC',
        'statutory_driver' => 'generic',
        'components' => [],
        'deductions' => [],
    ]);

    $this->artisan('openhr:install', [
        '--sector' => 'Private',
        '--country' => 'PH',
        '--skip-statutory' => true,
    ])->assertExitCode(0);

    $this->artisan('openhr:install', [
        '--sector' => 'Private',
        '--country' => 'XX',
        '--currency' => 'USD',
        '--skip-statutory' => true,
        '--force' => true,
    ])->assertExitCode(0);

    expect(Installation::countryCode())->toBe('XX')
        ->and(Installation::currencyCode())->toBe('USD');
});

it('warns when the country changes after a processed payroll', function () {
    config()->set('payroll.countries.XX', [
        'name' => 'Testland',
        'currency' => 'USD',
        'currency_symbol' => '$',
        'locale' => 'en',
        'timezone' => 'UTC',
        'statutory_driver' => 'generic',
        'components' => [],
        'deductions' => [],
    ]);

    Company::factory()->create();
    Payroll::factory()->create([
        'company_id' => 1,
        'payroll_group_id' => 1,
        'status' => 'processed',
        'payroll_date' => '2026-03-15',
    ]);

    $this->artisan('openhr:install', [
        '--sector' => 'Private',
        '--country' => 'PH',
        '--skip-statutory' => true,
    ])->assertExitCode(0);

    $this->artisan('openhr:install', [
        '--sector' => 'Private',
        '--country' => 'XX',
        '--skip-statutory' => true,
        '--force' => true,
    ])->expectsOutputToContain('already has processed payrolls')
        ->assertExitCode(0);
});

it('imports the Philippine statutory tables and component catalogue', function () {
    $this->artisan('openhr:install', [
        '--sector' => 'Government',
        '--country' => 'PH',
    ])->assertExitCode(0);

    expect(DB::table('statutory_sss_brackets')->where('country_code', 'PH')->count())->toBe(61)
        ->and(DB::table('statutory_components')->where('country_code', 'PH')->where('code', 'sss')->exists())->toBeTrue();
});
