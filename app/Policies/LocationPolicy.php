<?php

namespace App\Policies;

use App\Policies\Concerns\TenantScopedAuthorization;

class LocationPolicy
{
    use TenantScopedAuthorization;
}
