<?php

namespace App\Policies;

use App\Policies\Concerns\TenantScopedAuthorization;

class AssumptionRecordPolicy
{
    use TenantScopedAuthorization;
}
