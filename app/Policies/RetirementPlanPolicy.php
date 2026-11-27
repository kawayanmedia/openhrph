<?php

namespace App\Policies;

use App\Policies\Concerns\TenantScopedAuthorization;

class RetirementPlanPolicy
{
    use TenantScopedAuthorization;
}
