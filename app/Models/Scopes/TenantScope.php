<?php

namespace App\Models\Scopes;

use App\Exceptions\Tenancy\TenantNotResolvedException;
use App\Support\Tenancy\TenantContext;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;
use Illuminate\Support\Facades\Log;

class TenantScope implements Scope
{
    /**
     * Apply the tenant constraint to the query.
     */
    public function apply(Builder $builder, Model $model): void
    {
        $tenantId = app(TenantContext::class)->id();

        if ($tenantId !== null) {
            $builder->where(
                $model->qualifyColumn(config('tenancy.foreign_key', 'company_id')),
                $tenantId
            );

            return;
        }

        if (config('tenancy.strict', false)) {
            throw TenantNotResolvedException::forModel($model::class);
        }

        Log::warning('Tenant-scoped query without a resolved tenant.', [
            'model' => $model::class,
        ]);

        // Fail closed: never leak another tenant's rows.
        $builder->whereRaw('1 = 0');
    }
}
