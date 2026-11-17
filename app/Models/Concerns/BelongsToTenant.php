<?php

namespace App\Models\Concerns;

use App\Exceptions\Tenancy\CrossTenantWriteException;
use App\Exceptions\Tenancy\TenantNotResolvedException;
use App\Models\Scopes\TenantScope;
use App\Support\Tenancy\TenantContext;
use Illuminate\Database\Eloquent\Builder;

trait BelongsToTenant
{
    /**
     * Boot the tenant behaviour for the model.
     */
    public static function bootBelongsToTenant(): void
    {
        static::addGlobalScope(new TenantScope);

        static::creating(function ($model): void {
            $model->assignTenantOnCreate();
        });

        static::updating(function ($model): void {
            $model->guardTenantOnUpdate();
        });
    }

    /**
     * The tenant foreign key column.
     */
    public static function tenantColumn(): string
    {
        return config('tenancy.foreign_key', 'company_id');
    }

    /**
     * The active tenant id of the record.
     */
    public function tenantId(): ?int
    {
        $value = $this->getAttribute(static::tenantColumn());

        return $value === null ? null : (int) $value;
    }

    /**
     * Query only records for the given tenant, ignoring the active context.
     */
    public function scopeOnlyForTenant(Builder $query, int $tenantId): Builder
    {
        return $query
            ->withoutGlobalScope(TenantScope::class)
            ->where($query->getModel()->qualifyColumn(static::tenantColumn()), $tenantId);
    }

    /**
     * Query across all tenants. Reserved for platform tooling.
     */
    public function scopeWithoutTenant(Builder $query): Builder
    {
        return $query->withoutGlobalScope(TenantScope::class);
    }

    /**
     * Fill the tenant on create and reject explicit foreign writes.
     */
    protected function assignTenantOnCreate(): void
    {
        $column = static::tenantColumn();
        $current = $this->getAttribute($column);
        $tenantId = app(TenantContext::class)->id();

        if ($current === null || $current === '') {
            if ($tenantId === null) {
                if (config('tenancy.strict', false)) {
                    throw TenantNotResolvedException::forModel(static::class);
                }

                return;
            }

            $this->setAttribute($column, $tenantId);

            return;
        }

        if ($tenantId !== null && (int) $current !== $tenantId) {
            throw CrossTenantWriteException::forModel(static::class, $current, $tenantId);
        }
    }

    /**
     * Prevent moving a record between tenants.
     */
    protected function guardTenantOnUpdate(): void
    {
        $column = static::tenantColumn();

        if (! $this->isDirty($column)) {
            return;
        }

        $tenantId = app(TenantContext::class)->id();

        if ($tenantId !== null) {
            throw CrossTenantWriteException::forModel(static::class, $this->getAttribute($column), $tenantId);
        }
    }
}
