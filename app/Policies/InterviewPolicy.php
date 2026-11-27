<?php

namespace App\Policies;

use App\Policies\Concerns\TenantScopedAuthorization;

class InterviewPolicy
{
    use TenantScopedAuthorization;
}
