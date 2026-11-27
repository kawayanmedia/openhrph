<?php

namespace App\Policies;

use App\Models\EmploymentChange;
use App\Models\User;
use App\Policies\Concerns\TenantScopedAuthorization;

/**
 * Employment changes are written by domain services, never edited by hand.
 */
class EmploymentChangePolicy
{
    use TenantScopedAuthorization;

    public function create(User $user): bool
    {
        return false;
    }

    public function update(User $user, EmploymentChange $change): bool
    {
        return false;
    }

    public function delete(User $user, EmploymentChange $change): bool
    {
        return false;
    }
}
