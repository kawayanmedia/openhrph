<?php

namespace App\Policies;

use App\Policies\Concerns\TenantScopedAuthorization;

class SelectionRecommendationPolicy
{
    use TenantScopedAuthorization;
}
