<?php

namespace App\Jurisdictions\Philippines;

use App\Enums\Sector;
use App\Jurisdictions\Philippines\Console\Commands\PayrollBir2316;
use App\Jurisdictions\Philippines\Console\Commands\PayrollWageOrderImport;
use App\Sectors\SectorManager;
use App\Support\Installation\Installation;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

/**
 * Registers commands and routes that only exist under Philippine law.
 */
class PhilippinesServiceProvider extends ServiceProvider
{
    public function register(): void {}

    public function boot(): void
    {
        if (! $this->isPhilippines()) {
            return;
        }

        if ($this->app->runningInConsole()) {
            // Register every sector's commands; each government command extends
            // SectorCommand and refuses to run on a Private installation.
            $packCommands = [];

            foreach (Sector::cases() as $sector) {
                $packCommands = array_merge(
                    $packCommands,
                    app(SectorManager::class)->for($sector)->featurePack()->commands(),
                );
            }

            $this->commands([
                PayrollBir2316::class,
                PayrollWageOrderImport::class,
                ...$packCommands,
            ]);
        }

        $routes = __DIR__.'/routes.php';

        if (is_file($routes)) {
            Route::middleware('api')
                ->prefix('api')
                ->group($routes);
        }
    }

    /**
     * Resolve the active country without requiring a database during boot.
     */
    private function isPhilippines(): bool
    {
        try {
            return Installation::countryCode() === 'PH';
        } catch (\Throwable) {
            return strtoupper((string) config('jurisdictions.default', 'PH')) === 'PH';
        }
    }
}
