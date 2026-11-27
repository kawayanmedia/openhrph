<?php

namespace App\Policies;

use App\Policies\Concerns\TenantScopedAuthorization;

class OnboardingTaskPolicy
{
    use TenantScopedAuthorization;
}
