<?php

namespace App\Policies;

use App\Policies\Concerns\TenantScopedAuthorization;

class TrainingEnrollmentPolicy
{
    use TenantScopedAuthorization;
}
