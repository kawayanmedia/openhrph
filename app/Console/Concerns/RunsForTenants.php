<?php

namespace App\Console\Concerns;

use App\Support\Tenancy\TenantContext;

trait RunsForTenants
{
    /**
     * Run the callback once per company, each inside its own tenant context.
     *
     * @param  callable(object): void  $callback
     */
    protected function eachTenant(callable $callback): void
    {
        $model = config('tenancy.tenant_model');

        $model::query()
            ->orderBy('id')
            ->get()
            ->each(function ($tenant) use ($callback): void {
                app(TenantContext::class)->run($tenant, fn () => $callback($tenant));
            });
    }

    /**
     * Run the callback inside one company context.
     *
     * @param  callable(object): void  $callback
     */
    protected function runForTenant(int $companyId, callable $callback): bool
    {
        $model = config('tenancy.tenant_model');
        $tenant = $model::query()->find($companyId);

        if ($tenant === null) {
            $this->error("Company [{$companyId}] was not found.");

            return false;
        }

        app(TenantContext::class)->run($tenant, fn () => $callback($tenant));

        return true;
    }

    /**
     * Run the callback once for the --company option or for every company.
     *
     * @param  callable(object): void  $callback
     */
    protected function forTenantOption(callable $callback): void
    {
        $companyId = $this->hasOption('company') ? $this->option('company') : null;

        if ($companyId !== null && $companyId !== '') {
            $this->runForTenant((int) $companyId, $callback);

            return;
        }

        $this->eachTenant($callback);
    }
}
