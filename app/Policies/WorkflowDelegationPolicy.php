<?php

namespace App\Policies;

use App\Policies\Concerns\TenantScopedAuthorization;

class WorkflowDelegationPolicy
{
    use TenantScopedAuthorization;
}
