<?php

namespace App\Policies;

use App\Policies\Concerns\TenantScopedAuthorization;

class EmploymentPolicy
{
    use TenantScopedAuthorization;
}
