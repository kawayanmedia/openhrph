<?php

namespace App\Policies;

use App\Policies\Concerns\TenantScopedAuthorization;

class OnboardingChecklistPolicy
{
    use TenantScopedAuthorization;
}
