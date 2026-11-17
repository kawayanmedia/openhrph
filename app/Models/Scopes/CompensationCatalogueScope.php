<?php

namespace App\Models\Scopes;

use App\Support\Tenancy\TenantContext;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;

/**
 * The compensation catalogue is system-wide. A company only ever sees the
 * shared rows plus the overrides it owns, never another company's copies.
 */
class CompensationCatalogueScope implements Scope
{
    /**
     * Apply the catalogue constraint to the query.
     */
    public function apply(Builder $builder, Model $model): void
    {
        $column = $model->qualifyColumn('company_id');
        $tenantId = app(TenantContext::class)->id();

        $builder->where(function (Builder $query) use ($column, $tenantId): void {
            $query->whereNull($column);

            if ($tenantId !== null) {
                $query->orWhere($column, $tenantId);
            }
        });
    }
}
