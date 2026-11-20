<?php

namespace App\Providers;

use App\Http\Middleware\BlockNativeRoutes;
use App\Services\Desktop\DesktopEnvironment;
use Illuminate\Contracts\Http\Kernel;
use Illuminate\Support\ServiceProvider;
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
