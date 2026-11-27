<?php

namespace App\Policies;

use App\Models\RetirementCase;
use App\Models\User;
use App\Policies\Concerns\TenantScopedAuthorization;

class RetirementCasePolicy
{
    use TenantScopedAuthorization;

    public function view(User $user, RetirementCase $case): bool
    {
        if (! $this->sameTenant($user, $case)) {
            return false;
        }

        if ($user->owner || $user->ownsAnyCompany()) {
            return true;
        }

        if ($user->employee_id !== null && (int) $user->employee_id === (int) $case->employee_id) {
            return true;
        }

        $permissions = $user->groupForTenant()?->permissions ?? [];

        return ! empty($permissions['Retirement']) || ! empty($permissions['Employees']);
    }

    public function update(User $user, RetirementCase $case): bool
    {
        return $this->view($user, $case) && ! $case->isClosed();
    }

    public function delete(User $user, RetirementCase $case): bool
    {
        return $this->view($user, $case) && ! $case->isClosed();
    }
}
