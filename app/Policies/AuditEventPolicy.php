<?php

namespace App\Policies;

use App\Models\AuditEvent;
use App\Models\User;
use App\Policies\Concerns\TenantScopedAuthorization;

class AuditEventPolicy
{
    use TenantScopedAuthorization;

    public function create(User $user): bool
    {
        return false;
    }

    public function update(User $user, AuditEvent $event): bool
    {
        return false;
    }

    public function delete(User $user, AuditEvent $event): bool
    {
        return false;
    }
}
