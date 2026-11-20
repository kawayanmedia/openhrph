<?php

use App\Livewire\Desktop\Setup;
use App\Models\Company;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function () {
    config(['nativephp-internal.running' => true]);

    $this->withoutVite();
});

it('is not reachable outside the desktop runtime', function () {
    config(['nativephp-internal.running' => false]);

    $this->get('/setup')->assertNotFound();
});

it('shows the setup wizard on a fresh desktop install', function () {
    $this->get('/setup')
        ->assertOk()
        ->assertSee('Set up OpenHR PH');
});

it('sends an existing owner to the login page', function () {
    User::factory()->create();

    $this->get('/setup')->assertRedirect(route('admin.login'));
});

it('creates the installation, company and owner account', function () {
    Livewire::test(Setup::class)
        ->set('sector', 'Private')
        ->set('country', 'PH')
        ->set('companyName', 'Desktop Company')
        ->set('name', 'Desktop Owner')
        ->set('email', 'owner@desktop.test')
        ->set('password', 'secret-password')
        ->set('password_confirmation', 'secret-password')
        ->call('install')
        ->assertHasNoErrors()
        ->assertRedirect(route('admin.dashboard'));

    $owner = User::query()->where('email', 'owner@desktop.test')->first();

    expect($owner)->not->toBeNull()
        ->and($owner->owner)->toBeTruthy()
        ->and($owner->company_id)->not->toBeNull()
        ->and(Company::query()->whereKey($owner->company_id)->value('name'))->toBe('Desktop Company')
        ->and($this->app['auth']->guard('web')->check())->toBeTrue();

    $this->assertDatabaseHas('installation_settings', [
        'key' => 'sector',
        'value' => 'Private',
    ]);

    $this->assertDatabaseHas('installation_settings', [
        'key' => 'country_code',
        'value' => 'PH',
    ]);
});
