<?php

namespace App\Providers;

use App\Http\Middleware\BlockNativeRoutes;
use App\Listeners\RecordDesktopUpdateEvent;
use App\Services\Desktop\DesktopEnvironment;
use Illuminate\Contracts\Http\Kernel;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;
use Native\Desktop\Events\AutoUpdater\CheckingForUpdate;
use Native\Desktop\Events\AutoUpdater\DownloadProgress;
use Native\Desktop\Events\AutoUpdater\Error;
use Native\Desktop\Events\AutoUpdater\UpdateAvailable;
use Native\Desktop\Events\AutoUpdater\UpdateCancelled;
use Native\Desktop\Events\AutoUpdater\UpdateDownloaded;
use Native\Desktop\Events\AutoUpdater\UpdateNotAvailable;
use Native\Desktop\Http\Middleware\PreventRegularBrowserAccess;

class DesktopServiceProvider extends ServiceProvider
{
    /**
     * Apply the desktop profile before any storage, queue or payroll service
     * resolves its configuration.
     */
    public function register(): void
    {
        DesktopEnvironment::apply();
    }

    public function boot(): void
    {
        $kernel = $this->app->make(Kernel::class);

        if (! DesktopEnvironment::isRunning()) {
            // NativePHP registers its Electron bridge routes in every
            // environment; close them on the web.
            $kernel->pushMiddleware(BlockNativeRoutes::class);

            return;
        }

        // Runs after NativePHP has resolved its per-user storage and SQLite
        // paths, so a debug build still keeps data out of the app bundle.
        DesktopEnvironment::enableDebug();

        DesktopEnvironment::ensureDatabaseIsMigrated();

        // The desktop profile uses the log broadcasting driver, so updater
        // events are stored in the cache for the software update screen.
        Event::listen([
            CheckingForUpdate::class,
            UpdateAvailable::class,
            UpdateNotAvailable::class,
            DownloadProgress::class,
            UpdateDownloaded::class,
            UpdateCancelled::class,
            Error::class,
        ], RecordDesktopUpdateEvent::class);

        if ((string) config('nativephp-internal.secret') === '') {
            return;
        }

        // NativePHP pushes this onto the concrete kernel class, which is not the
        // singleton bound to the contract in current Laravel versions. Re-apply
        // it to the kernel that actually handles requests so the local PHP
        // server rejects browser requests that do not carry the native secret.
        $kernel->pushMiddleware(PreventRegularBrowserAccess::class);
    }
}
