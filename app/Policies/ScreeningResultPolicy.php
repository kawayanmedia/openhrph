<?php

namespace App\Policies;

use App\Policies\Concerns\TenantScopedAuthorization;

class ScreeningResultPolicy
{
    use TenantScopedAuthorization;
}
