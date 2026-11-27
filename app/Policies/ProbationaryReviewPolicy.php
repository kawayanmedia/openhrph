<?php

namespace App\Policies;

use App\Policies\Concerns\TenantScopedAuthorization;

class ProbationaryReviewPolicy
{
    use TenantScopedAuthorization;
}
