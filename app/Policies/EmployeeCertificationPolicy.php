<?php

namespace App\Policies;

use App\Policies\Concerns\TenantScopedAuthorization;

class EmployeeCertificationPolicy
{
    use TenantScopedAuthorization;
}
