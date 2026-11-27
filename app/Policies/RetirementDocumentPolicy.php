<?php

namespace App\Policies;

use App\Policies\Concerns\TenantScopedAuthorization;

class RetirementDocumentPolicy
{
    use TenantScopedAuthorization;
}
