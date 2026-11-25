<?php

namespace App\Providers;

use App\Listeners\RestoreQueueTenant;
use App\Listeners\SetDefaultTenant;
use App\Support\Tenancy\TenantContext;
use Illuminate\Auth\Events\Authenticated;
use Illuminate\Queue\Events\JobExceptionOccurred;
use Illuminate\Queue\Events\JobFailed;
use Illuminate\Queue\Events\JobProcessed;
use Illuminate\Queue\Events\JobProcessing;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\ServiceProvider;
use KawayanMedia\Government\Contracts\CompanyResolver;

class TenancyServiceProvider extends ServiceProvider
{
    /**
     * Register tenancy services.
     */
    public function register(): void
    {
        $this->app->scoped(TenantContext::class);
        $this->app->singleton(RestoreQueueTenant::class);

        // Keep the Government package models inside the application tenant.
        $this->app->scoped(CompanyResolver::class, fn (): CompanyResolver => new class implements CompanyResolver
        {
            public function id(): ?int
            {
                return app(TenantContext::class)->id();
            }
        });
    }

    /**
     * Bootstrap tenancy services.
     */
    public function boot(): void
    {
        Event::listen(Authenticated::class, SetDefaultTenant::class);

        Queue::createPayloadUsing(function (): array {
            $tenantId = app(TenantContext::class)->id();

            return $tenantId === null ? [] : ['tenant_id' => $tenantId];
        });

        Event::listen(JobProcessing::class, [RestoreQueueTenant::class, 'processing']);

        foreach ([JobProcessed::class, JobFailed::class, JobExceptionOccurred::class] as $event) {
            Event::listen($event, [RestoreQueueTenant::class, 'finished']);
        }
    }
}
