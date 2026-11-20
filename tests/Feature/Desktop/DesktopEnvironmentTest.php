<?php

use App\Providers\DesktopServiceProvider;
use App\Services\Desktop\DesktopEnvironment;
use Illuminate\Contracts\Http\Kernel;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schema;
use Native\Desktop\Http\Middleware\PreventRegularBrowserAccess;

it('leaves the web deployment profile untouched', function () {
    config([
        'nativephp-internal.running' => false,
        'payroll.engine.driver' => 'go',
        'demo.enabled' => true,
        'mail.default' => 'smtp',
        'filesystems.disks.public.serve' => false,
    ]);

    expect(DesktopEnvironment::isRunning())->toBeFalse();

    DesktopEnvironment::apply();

    expect(config('payroll.engine.driver'))->toBe('go')
        ->and(config('demo.enabled'))->toBeTrue()
        ->and(config('mail.default'))->toBe('smtp')
        ->and(config('filesystems.disks.public.serve'))->toBeFalse();
});

it('switches to the offline desktop profile inside the native runtime', function () {
    config(['nativephp-internal.running' => true]);

    expect(DesktopEnvironment::isRunning())->toBeTrue();

    DesktopEnvironment::apply();

    expect(config('payroll.engine.driver'))->toBe('php')
        ->and(config('demo.enabled'))->toBeFalse()
        ->and(config('mail.default'))->toBe('log')
        ->and(config('broadcasting.default'))->toBe('log')
        ->and(config('queue.default'))->toBe('database')
        ->and(config('session.driver'))->toBe('file')
        ->and(config('telescope.enabled'))->toBeFalse()
        ->and(config('filesystems.disks.public.serve'))->toBeTrue()
        ->and(config('filesystems.disks.public.url'))->toBe('/storage');
});

it('turns on error output for a packaged debug build', function () {
    config([
        'nativephp-internal.running' => true,
        'desktop.debug' => true,
        'app.debug' => false,
        'logging.channels.daily.level' => 'warning',
    ]);

    (new DesktopServiceProvider($this->app))->boot();

    expect(config('app.debug'))->toBeTrue()
        ->and(config('logging.channels.daily.level'))->toBe('debug');
});

it('keeps error output off when the desktop build is not a debug build', function () {
    config([
        'nativephp-internal.running' => true,
        'desktop.debug' => false,
        'app.debug' => false,
    ]);

    DesktopEnvironment::enableDebug();

    expect(config('app.debug'))->toBeFalse();
});

it('builds the database schema on a fresh desktop install', function () {
    config(['nativephp-internal.running' => true]);

    Schema::shouldReceive('hasTable')->once()->with('migrations')->andReturnFalse();
    Artisan::shouldReceive('call')->once()->with('migrate', ['--force' => true]);

    DesktopEnvironment::ensureDatabaseIsMigrated();
});

it('does not migrate when the schema already exists', function () {
    config(['nativephp-internal.running' => true]);

    Schema::shouldReceive('hasTable')->once()->with('migrations')->andReturnTrue();
    Artisan::shouldReceive('call')->never();

    DesktopEnvironment::ensureDatabaseIsMigrated();
});

it('never touches the database outside the native runtime', function () {
    config(['nativephp-internal.running' => false]);

    Schema::shouldReceive('hasTable')->never();
    Artisan::shouldReceive('call')->never();

    DesktopEnvironment::ensureDatabaseIsMigrated();
});

it('does not touch debug mode outside the native runtime', function () {
    config([
        'nativephp-internal.running' => false,
        'desktop.debug' => true,
        'app.debug' => false,
    ]);

    DesktopEnvironment::enableDebug();

    expect(config('app.debug'))->toBeFalse();
});

it('protects the local server with the native secret', function () {
    config([
        'nativephp-internal.running' => true,
        'nativephp-internal.secret' => 'native-secret',
    ]);

    (new DesktopServiceProvider($this->app))->boot();

    $kernel = $this->app->make(Kernel::class);
    $middleware = (new ReflectionObject($kernel))->getProperty('middleware');
    $middleware->setAccessible(true);

    expect($middleware->getValue($kernel))->toContain(PreventRegularBrowserAccess::class);
});

it('hides the native bridge from web deployments', function () {
    $this->postJson('/_native/api/events', ['event' => 'anything'])->assertNotFound();
});
