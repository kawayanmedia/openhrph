<?php

namespace App\Providers;

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

class TenancyServiceProvider extends ServiceProvider
{
    /**
     * Register tenancy services.
     */
    public function register(): void
    {
        $this->app->scoped(TenantContext::class);
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

        Event::listen(JobProcessing::class, function (JobProcessing $event): void {
            $tenantId = $event->job->payload()['tenant_id'] ?? null;

            if ($tenantId !== null) {
                app(TenantContext::class)->set((int) $tenantId);
            }
        });

        foreach ([JobProcessed::class, JobFailed::class, JobExceptionOccurred::class] as $event) {
            Event::listen($event, function (): void {
                app(TenantContext::class)->forget();
            });
        }
    }
}
