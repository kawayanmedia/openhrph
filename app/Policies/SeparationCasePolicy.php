<?php

namespace App\Policies;

use App\Policies\Concerns\TenantScopedAuthorization;

class SeparationCasePolicy
{
    use TenantScopedAuthorization;
}
