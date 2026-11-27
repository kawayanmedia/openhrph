<?php

namespace App\Policies;

use App\Policies\Concerns\TenantScopedAuthorization;

class RecruitmentDeliberationPolicy
{
    use TenantScopedAuthorization;
}
