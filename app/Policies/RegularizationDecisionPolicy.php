<?php

namespace App\Policies;

use App\Policies\Concerns\TenantScopedAuthorization;

class RegularizationDecisionPolicy
{
    use TenantScopedAuthorization;
}
