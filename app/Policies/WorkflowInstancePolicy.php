<?php

namespace App\Policies;

use App\Models\User;
use App\Models\WorkflowInstance;
use App\Policies\Concerns\TenantScopedAuthorization;

class WorkflowInstancePolicy
{
    use TenantScopedAuthorization;

    public function update(User $user, WorkflowInstance $instance): bool
    {
        return false;
    }

    public function delete(User $user, WorkflowInstance $instance): bool
    {
        return false;
    }

    public function cancel(User $user, WorkflowInstance $instance): bool
    {
        return $this->sameTenant($user, $instance)
            && ((int) $instance->initiated_by === (int) $user->getKey() || $user->can(config('admin.gate')));
    }
}
