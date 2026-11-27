<?php

namespace App\Policies;

use App\Models\AttendanceCorrectionDecision;
use App\Models\User;
use App\Policies\Concerns\TenantScopedAuthorization;

class AttendanceCorrectionDecisionPolicy
{
    use TenantScopedAuthorization;

    public function create(User $user): bool
    {
        return false;
    }

    public function update(User $user, AttendanceCorrectionDecision $model): bool
    {
        return false;
    }

    public function delete(User $user, AttendanceCorrectionDecision $model): bool
    {
        return false;
    }
}
