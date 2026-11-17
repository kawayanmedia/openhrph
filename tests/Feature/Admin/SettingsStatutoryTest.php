<?php

use App\Livewire\Admin\AdminIndex;
use App\Livewire\Admin\Settings\Settings;
use App\Models\Company;
use App\Models\User;
use App\Support\Installation\Installation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function () {
    Company::factory()->create(['id' => 1, 'type' => 'Private']);

    $this->user = User::factory()->create([
        'company_id' => 1,
        'owner' => true,
    ]);

    $this->actingAs($this->user);

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
});

it('renders the country and statutory settings page', function () {
    $this->get(route('admin.settings', ['page' => 'statutory']))
        ->assertOk()
        ->assertSee('Country & statutory');
});

it('renders the statutory component resources', function () {
    $this->artisan('openhr:install', [
        '--sector' => 'Private',
        '--country' => 'XX',
        '--skip-statutory' => true,
    ])->assertExitCode(0);

    Livewire::test(AdminIndex::class, ['resource' => 'statutory-component'])->assertOk();
    Livewire::test(AdminIndex::class, ['resource' => 'statutory-component-band'])->assertOk();
});

it('saves installation-wide country settings without writing tenant settings', function () {
    $this->artisan('openhr:install', [
        '--sector' => 'Private',
        '--country' => 'PH',
        '--skip-statutory' => true,
    ])->assertExitCode(0);

    Livewire::test(Settings::class, ['page' => 'statutory'])
        ->set('form.installation_country_code', 'XX')
        ->set('form.installation_currency_code', 'USD')
        ->set('form.installation_locale', 'en')
        ->set('form.installation_timezone', 'UTC')
        ->call('save')
        ->assertHasNoErrors();

    expect(Installation::countryCode())->toBe('XX')
        ->and(Installation::currencyCode())->toBe('USD')
        ->and(DB::table('settings')->where('key', 'like', 'installation_%')->count())->toBe(0);
});

it('rejects a country change after a payroll has been processed', function () {
    $this->artisan('openhr:install', [
        '--sector' => 'Private',
        '--country' => 'PH',
        '--skip-statutory' => true,
    ])->assertExitCode(0);

    DB::table('payrolls')->insert([
        'company_id' => 1,
        'payroll_group_id' => 1,
        'status' => 'processed',
        'payroll_date' => '2026-03-15',
        'date_from' => '2026-03-01',
        'date_to' => '2026-03-15',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    Livewire::test(Settings::class, ['page' => 'statutory'])
        ->set('form.installation_country_code', 'XX')
        ->call('save')
        ->assertHasErrors('form.installation_country_code');

    expect(Installation::countryCode())->toBe('PH');
});
