<?php

use App\Exceptions\Tenancy\CrossTenantWriteException;
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

it('fills the company id from the active tenant on create', function () {
    app(TenantContext::class)->set(7);

    $record = TenantRecord::factory()->create(['name' => 'one']);

    expect($record->company_id)->toBe(7);
});

it('rejects writing an explicit foreign tenant', function () {
    app(TenantContext::class)->set(7);

    expect(fn () => TenantRecord::factory()->create(['company_id' => 8, 'name' => 'two']))
        ->toThrow(CrossTenantWriteException::class);
});

it('rejects moving a record to another tenant', function () {
    $record = TenantRecord::factory()->create(['company_id' => 7, 'name' => 'one']);

    app(TenantContext::class)->set(7);

    $record->company_id = 8;

    expect(fn () => $record->save())->toThrow(CrossTenantWriteException::class);
});
