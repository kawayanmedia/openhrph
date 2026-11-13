<?php

use App\Models\Setting;
use App\Support\Tenancy\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('keeps settings isolated per tenant', function () {
    app(TenantContext::class)->set(1);
    Setting::factory()->create(['key' => 'trade_name', 'value' => 'Alpha Corp']);

    app(TenantContext::class)->set(2);
    Setting::factory()->create(['key' => 'trade_name', 'value' => 'Beta Corp']);

    app(TenantContext::class)->set(1);
    expect(nova_get_setting('trade_name'))->toBe('Alpha Corp');

    app(TenantContext::class)->set(2);
    expect(nova_get_setting('trade_name'))->toBe('Beta Corp');
});
