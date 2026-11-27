<?php

namespace App\Policies;

use App\Policies\Concerns\TenantScopedAuthorization;

class TrainingSessionPolicy
{
    use TenantScopedAuthorization;
}
