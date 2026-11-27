<?php

namespace App\Policies;

use App\Policies\Concerns\TenantScopedAuthorization;

class ExternalClaimTrackingPolicy
{
    use TenantScopedAuthorization;
}
