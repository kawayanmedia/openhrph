<?php

namespace App\Policies;

use App\Policies\Concerns\TenantScopedAuthorization;

class PositionRequirementPolicy
{
    use TenantScopedAuthorization;
}
