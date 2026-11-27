<?php

namespace App\Policies;

use App\Policies\Concerns\TenantScopedAuthorization;

class DevelopmentPlanPolicy
{
    use TenantScopedAuthorization;
}
