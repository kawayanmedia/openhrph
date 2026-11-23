<?php

use App\Support\Tenancy\TenantContext;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;

class SyncTenantProbeJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;

    public function handle(): void
    {
        //
    }
}

it('keeps the request tenant when a queued job runs on the sync connection', function () {
    app(TenantContext::class)->set(42);

    SyncTenantProbeJob::dispatch();

    expect(app(TenantContext::class)->id())->toBe(42);
});

it('clears the tenant after a job when no request tenant was set', function () {
    app(TenantContext::class)->forget();

    SyncTenantProbeJob::dispatch();

    expect(app(TenantContext::class)->has())->toBeFalse();
});
