<?php

namespace App\Policies;

use App\Policies\Concerns\TenantScopedAuthorization;

class JobRequisitionPolicy
{
    use TenantScopedAuthorization;
}
