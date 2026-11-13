<?php

namespace App\Providers;

use App\Models\Company;
use App\Models\Employee;
use App\Models\Holiday;
use App\Models\Leave;
use App\Models\LeaveRequest;
use App\Models\PayrollDetail;
use App\Models\PayrollGroup;
use App\Models\TimesheetRequest;
use App\Models\User;
use App\Observers\CompanyObserver;
use App\Observers\EmployeeObserver;
use App\Observers\HolidayObserver;
use App\Observers\LeaveObserver;
use App\Observers\LeaveRequestObserver;
use App\Observers\PayrollDetailObserver;
use App\Observers\PayrollGroupObserver;
use App\Observers\TimesheetRequestObserver;
use App\Services\Payroll\Engine\EngineManager;
use App\Services\Payroll\Engine\GoPayrollEngine;
use Illuminate\Database\Eloquent\Factories\Factory as FactoryNameResolver;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;
use Laravel\Pennant\Feature;
use Lorisleiva\Actions\Facades\Actions;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        Actions::registerCommands();

        $this->app->singleton(EngineManager::class);
        $this->app->singleton(GoPayrollEngine::class, function () {
            return new GoPayrollEngine;
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Factories live flat in database/factories, so nested model namespaces
        // (App\Models\Government\*, App\Services\...) resolve by class basename.
        FactoryNameResolver::guessFactoryNamesUsing(
            fn (string $modelName): string => 'Database\\Factories\\'.class_basename($modelName).'Factory'
        );

        // if ($this->app->environment('production') || $this->app->environment('staging')) {
        //     URL::forceScheme('https');
        // }

        // Model observers
        PayrollGroup::observe(PayrollGroupObserver::class);

        // Model::preventLazyLoading(
        //     ! $this->app->isProduction()
        // );

        Employee::observe(EmployeeObserver::class);
        LeaveRequest::observe(LeaveRequestObserver::class);
        TimesheetRequest::observe(TimesheetRequestObserver::class);
        Leave::observe(LeaveObserver::class);
        Holiday::observe(HolidayObserver::class);
        PayrollDetail::observe(PayrollDetailObserver::class);
        Company::observe(CompanyObserver::class);

        // New user can use
        // Old user not
        Feature::define('new-design', fn (User $user) => match (true) {
            $user->newUser() => true,
            $user->oldUser() => false,
            default => false,
        });

        Feature::define('employee-requests', fn (User $user) => match (true) {
            $user->newUser() => true,
            $user->oldUser() => false,
            default => false,
        });

        // Check if government

        Gate::define(config('admin.gate'), function (User $user) {
            if (config('admin.allow_all', false)) {
                return true;
            }

            if ($user->owner) {
                return true;
            }

            $permissions = $user->groupForTenant()?->permissions ?? [];

            foreach (config('admin.access_permissions', []) as $permission) {
                if (! empty($permissions[$permission])) {
                    return true;
                }
            }

            return false;
        });
    }
}
