<?php

namespace App\Policies;

use App\Policies\Concerns\TenantScopedAuthorization;

class ServiceRecordPolicy
{
    use TenantScopedAuthorization;
}
