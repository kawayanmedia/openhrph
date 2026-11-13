<?php

use App\Exceptions\Tenancy\TenantNotResolvedException;
use App\Support\Tenancy\TenantContext;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Tests\Fixtures\TenantRecord;

beforeEach(function () {
    Schema::dropIfExists('tenant_records');

    Schema::create('tenant_records', function (Blueprint $table): void {
        $table->id();
        $table->unsignedBigInteger('company_id')->nullable();
        $table->string('name');
    });

    app(TenantContext::class)->forget();
});

it('scopes queries to the active tenant', function () {
    TenantRecord::factory()->createMany([
        ['company_id' => 1, 'name' => 'one'],
        ['company_id' => 2, 'name' => 'two'],
    ]);

    app(TenantContext::class)->set(1);

    expect(TenantRecord::query()->pluck('name')->all())->toBe(['one'])
        ->and(TenantRecord::withoutTenant()->count())->toBe(2);
});

it('throws when strict mode is enabled without a tenant', function () {
    config(['tenancy.strict' => true]);

    expect(fn () => TenantRecord::query()->count())
        ->toThrow(TenantNotResolvedException::class);
});

it('fails closed without a tenant when strict mode is disabled', function () {
    config(['tenancy.strict' => false]);

    TenantRecord::factory()->create(['company_id' => 1, 'name' => 'one']);

    expect(TenantRecord::query()->count())->toBe(0);
});
