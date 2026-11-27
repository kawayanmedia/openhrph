<?php

namespace App\Policies;

use App\Policies\Concerns\TenantScopedAuthorization;

class ClearanceTaskPolicy
{
    use TenantScopedAuthorization;
}
