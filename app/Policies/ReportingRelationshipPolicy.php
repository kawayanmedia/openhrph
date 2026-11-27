<?php

namespace App\Policies;

use App\Policies\Concerns\TenantScopedAuthorization;

class ReportingRelationshipPolicy
{
    use TenantScopedAuthorization;
}
