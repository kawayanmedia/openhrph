<?php

namespace App\Jurisdictions\Philippines;

use App\Jurisdictions\Philippines\Console\Commands\GeneratePlantillaItemsCommand;
use App\Jurisdictions\Philippines\Console\Commands\GenerateSalaryGradeCommand;
use App\Jurisdictions\Philippines\Console\Commands\PayrollBir2316;
use App\Jurisdictions\Philippines\Console\Commands\PayrollLoyaltyAwards;
use App\Jurisdictions\Philippines\Console\Commands\PayrollStepIncrements;
use App\Jurisdictions\Philippines\Console\Commands\PayrollTerminalLeave;
use App\Jurisdictions\Philippines\Console\Commands\PayrollWageOrderImport;
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
        if (Installation::countryCode() !== 'PH') {
            return;
        }

        if ($this->app->runningInConsole()) {
            $this->commands([
                GeneratePlantillaItemsCommand::class,
                GenerateSalaryGradeCommand::class,
                PayrollBir2316::class,
                PayrollLoyaltyAwards::class,
                PayrollStepIncrements::class,
                PayrollTerminalLeave::class,
                PayrollWageOrderImport::class,
            ]);
        }

        $routes = __DIR__.'/routes.php';

        if (is_file($routes)) {
            Route::middleware('api')
                ->prefix('api')
                ->group($routes);
        }
    }
}
