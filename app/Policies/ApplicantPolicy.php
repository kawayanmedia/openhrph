<?php

namespace App\Policies;

use App\Policies\Concerns\TenantScopedAuthorization;

class ApplicantPolicy
{
    use TenantScopedAuthorization;
}
