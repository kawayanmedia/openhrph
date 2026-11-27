<?php

namespace App\Policies;

use App\Policies\Concerns\TenantScopedAuthorization;

class EmploymentContractPolicy
{
    use TenantScopedAuthorization;
}
