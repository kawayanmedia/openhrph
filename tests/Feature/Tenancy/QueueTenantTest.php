<?php

use App\Support\Tenancy\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Queue\Events\JobProcessed;
use Illuminate\Queue\Events\JobProcessing;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Tests\Fixtures\FakeQueueJob;
use Tests\Fixtures\TenantAwareTestJob;

uses(RefreshDatabase::class);

it('adds the active tenant to the queue payload', function () {
    config(['queue.default' => 'database']);
    app(TenantContext::class)->set(9);

    Queue::connection('database')->push(new TenantAwareTestJob);

    $payload = json_decode((string) DB::table('jobs')->value('payload'), true);

    expect($payload['tenant_id'])->toBe(9);
});

it('restores and clears the tenant around a processed job', function () {
    $job = new FakeQueueJob(['tenant_id' => 5]);

    event(new JobProcessing('database', $job));

    expect(app(TenantContext::class)->id())->toBe(5);

    event(new JobProcessed('database', $job));

    expect(app(TenantContext::class)->id())->toBeNull();
});
