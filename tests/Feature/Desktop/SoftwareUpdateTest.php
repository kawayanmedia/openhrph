<?php

use App\Livewire\Admin\SoftwareUpdate;
use App\Models\User;
use App\Providers\DesktopServiceProvider;
use App\Services\Desktop\DesktopUpdateState;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;
use Native\Desktop\Events\AutoUpdater\UpdateAvailable;
use Native\Desktop\Events\AutoUpdater\UpdateDownloaded;
use Native\Desktop\Events\AutoUpdater\UpdateNotAvailable;

uses(RefreshDatabase::class);

beforeEach(function () {
    config([
        'nativephp-internal.running' => true,
        'nativephp-internal.api_url' => 'http://127.0.0.1:9999',
        'nativephp-internal.secret' => 'test-secret',
        'nativephp.version' => '1.2.3',
    ]);

    DesktopUpdateState::forget();

    $this->user = User::factory()->create([
        'company_id' => 1,
        'owner' => true,
        'name' => 'Admin User',
        'email' => 'admin@example.com',
        'password' => 'secret',
    ]);

    $this->actingAs($this->user);
});

it('is not reachable outside the desktop runtime', function () {
    config(['nativephp-internal.running' => false]);

    $this->withoutVite()
        ->get(route('admin.updates'))
        ->assertNotFound();
});

it('shows the installed version and the update controls', function () {
    Livewire::test(SoftwareUpdate::class)
        ->assertOk()
        ->assertSee('1.2.3')
        ->assertSee('Check for updates')
        ->assertSee('Run migrations');
});

it('renders inside the desktop shell with the version in the sidebar', function () {
    $this->withoutVite()
        ->get(route('admin.updates'))
        ->assertOk()
        ->assertSee('Software update')
        ->assertSee('Desktop v1.2.3');
});

it('asks the shell to check GitHub for a newer release', function () {
    Http::fake();

    Livewire::test(SoftwareUpdate::class)
        ->call('check')
        ->assertHasNoErrors();

    Http::assertSent(fn ($request) => $request->url() === 'http://127.0.0.1:9999/auto-updater/check-for-updates'
        && $request->method() === 'POST');

    expect(DesktopUpdateState::get()['state'])->toBe(DesktopUpdateState::STATE_CHECKING);
});

it('asks the shell to restart and install a downloaded release', function () {
    Http::fake();

    DesktopUpdateState::put(DesktopUpdateState::STATE_DOWNLOADED, ['version' => '2.0.0']);

    Livewire::test(SoftwareUpdate::class)
        ->assertSee('Restart and install')
        ->call('install')
        ->assertHasNoErrors();

    Http::assertSent(fn ($request) => $request->url() === 'http://127.0.0.1:9999/auto-updater/quit-and-install');
});

it('records the updater events the shell reports', function () {
    (new DesktopServiceProvider($this->app))->boot();

    event(new UpdateAvailable('2.0.0', [], now()->toIso8601String()));

    expect(DesktopUpdateState::get())
        ->state->toBe(DesktopUpdateState::STATE_AVAILABLE)
        ->version->toBe('2.0.0');

    Livewire::test(SoftwareUpdate::class)
        ->assertSee('Version 2.0.0 is available');

    event(new UpdateDownloaded('release.zip', '2.0.0', [], now()->toIso8601String()));

    Livewire::test(SoftwareUpdate::class)
        ->assertSee('Version 2.0.0 is ready to install');

    event(new UpdateNotAvailable('2.0.0', [], now()->toIso8601String()));

    expect(DesktopUpdateState::get()['state'])->toBe(DesktopUpdateState::STATE_UP_TO_DATE);
});

it('runs the migrator from the update screen', function () {
    Artisan::shouldReceive('call')->once()->with('migrate', ['--force' => true]);
    Artisan::shouldReceive('output')->once()->andReturn('Nothing to migrate.');

    Livewire::test(SoftwareUpdate::class)
        ->assertSee('Up to date')
        ->call('migrate')
        ->assertHasNoErrors();
});
