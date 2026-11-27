<?php

namespace App\Policies;

use App\Policies\Concerns\TenantScopedAuthorization;

class JobDescriptionPolicy
{
    use TenantScopedAuthorization;
}
