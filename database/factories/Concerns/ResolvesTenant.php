<?php

namespace Database\Factories\Concerns;

use App\Models\Company;
use App\Support\Tenancy\TenantContext;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Resolve the tenant a factory-built record belongs to: the active tenant when
 * one is resolved, otherwise a freshly created company.
 */
trait ResolvesTenant
{
    protected function tenantId(): int|Factory
    {
        return app(TenantContext::class)->id() ?? Company::factory();
    }
}
