<?php

namespace App\Policies;

use App\Models\TimesheetApproval;
use App\Models\User;
use App\Policies\Concerns\TenantScopedAuthorization;

class TimesheetApprovalPolicy
{
    use TenantScopedAuthorization;

    public function create(User $user): bool
    {
        return false;
    }

    public function update(User $user, TimesheetApproval $model): bool
    {
        return false;
    }

    public function delete(User $user, TimesheetApproval $model): bool
    {
        return false;
    }
}
