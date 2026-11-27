<?php

namespace App\Policies;

use App\Policies\Concerns\TenantScopedAuthorization;

class ComplianceSourcePolicy
{
    use TenantScopedAuthorization;
}
