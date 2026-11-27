<?php

namespace App\Policies;

use App\Policies\Concerns\TenantScopedAuthorization;

class FinalSettlementPolicy
{
    use TenantScopedAuthorization;
}
