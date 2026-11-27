<?php

namespace App\Policies;

use App\Policies\Concerns\TenantScopedAuthorization;

class HrmpsbMemberPolicy
{
    use TenantScopedAuthorization;
}
