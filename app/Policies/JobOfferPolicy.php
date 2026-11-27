<?php

namespace App\Policies;

use App\Policies\Concerns\TenantScopedAuthorization;

class JobOfferPolicy
{
    use TenantScopedAuthorization;
}
