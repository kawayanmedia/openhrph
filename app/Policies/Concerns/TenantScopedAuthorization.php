<?php

namespace App\Policies\Concerns;

use App\Models\User;
use App\Support\Tenancy\TenantContext;
use Illuminate\Database\Eloquent\Model;

trait TenantScopedAuthorization
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function create(User $user): bool
    {
        return true;
    }

    public function view(User $user, Model $model): bool
    {
        return $this->sameTenant($user, $model);
    }

    public function update(User $user, Model $model): bool
    {
        return $this->sameTenant($user, $model);
    }

    public function delete(User $user, Model $model): bool
    {
        return $this->sameTenant($user, $model);
    }

    public function restore(User $user, Model $model): bool
    {
        return $this->sameTenant($user, $model);
    }

    public function forceDelete(User $user, Model $model): bool
    {
        return $this->sameTenant($user, $model);
    }

    protected function sameTenant(User $user, Model $model): bool
    {
        $companyId = $model->getAttribute('company_id');

        if ($companyId === null) {
            return true;
        }

        $tenantId = app(TenantContext::class)->id() ?? $user->company_id;

        return $tenantId !== null && (int) $tenantId === (int) $companyId;
    }
}
