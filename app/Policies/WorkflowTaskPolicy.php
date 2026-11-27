<?php

namespace App\Policies;

use App\Models\User;
use App\Models\WorkflowTask;
use App\Policies\Concerns\TenantScopedAuthorization;
use App\Services\Workflow\ApproverResolver;

class WorkflowTaskPolicy
{
    use TenantScopedAuthorization;

    public function update(User $user, WorkflowTask $task): bool
    {
        return false;
    }

    public function delete(User $user, WorkflowTask $task): bool
    {
        return false;
    }

    public function decide(User $user, WorkflowTask $task): bool
    {
        return $this->sameTenant($user, $task)
            && app(ApproverResolver::class)->canAct($task, $user);
    }
}
