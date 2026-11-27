<?php

namespace App\Policies;

use App\Policies\Concerns\TenantScopedAuthorization;

class OrganizationUnitPolicy
{
    use TenantScopedAuthorization;
}
