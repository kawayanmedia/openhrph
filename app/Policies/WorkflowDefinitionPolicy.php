<?php

namespace App\Policies;

use App\Policies\Concerns\TenantScopedAuthorization;

class WorkflowDefinitionPolicy
{
    use TenantScopedAuthorization;
}
