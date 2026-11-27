<?php

namespace App\Policies;

use App\Policies\Concerns\TenantScopedAuthorization;

class QualificationStandardPolicy
{
    use TenantScopedAuthorization;
}
