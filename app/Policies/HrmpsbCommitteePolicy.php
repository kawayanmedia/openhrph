<?php

namespace App\Policies;

use App\Policies\Concerns\TenantScopedAuthorization;

class HrmpsbCommitteePolicy
{
    use TenantScopedAuthorization;
}
