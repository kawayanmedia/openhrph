<?php

namespace App\Listeners;

use App\Support\Tenancy\TenantContext;
use Illuminate\Queue\Events\JobProcessing;

/**
 * Keeps the tenant of the surrounding request alive while a queued job runs
 * inline on the sync connection, and still clears it between jobs so a worker
 * cannot leak one tenant into the next.
 */
class RestoreQueueTenant
{
    /** @var list<int|null> */
    private array $previous = [];

    public function processing(JobProcessing $event): void
    {
        $context = app(TenantContext::class);

        $this->previous[] = $context->id();

        $tenantId = $event->job->payload()['tenant_id'] ?? null;

        if ($tenantId !== null) {
            $context->set((int) $tenantId);
        }
    }

    public function finished(): void
    {
        $context = app(TenantContext::class);
        $previous = array_pop($this->previous);

        if ($previous === null) {
            $context->forget();

            return;
        }

        $context->set($previous);
    }
}
